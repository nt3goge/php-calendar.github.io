<?php

declare(strict_types=1);

class Calendar extends DB_Connect
{
    private $_userDate;

    private $_m;

    private $_y;

    private $_daysInMonth;

    private $_startDay;

    public function __construct($dbo = NULL, $useData = NULL)
    {
        parent::__construct($dbo);

        $this->_userDate = date('Y-m-d H:i:s');

        if (isset($useDate)) {
            $this->_userDate = $useData;
        }

        $ts = strtotime($this->_userDate);
        $this->_m = (int)date('m', $ts);
        $this->_y = (int)date('Y', $ts);

        $this->_daysInMonth = cal_days_in_month(
            CAL_GREGORIAN,
            $this->_m,
            $this->_y
        );

        $ts = mktime(0, 0, 0, $this->_m, 1, $this->_y);
        $this->_startDay = (int)date('w', $ts);
    }

    private function _loadEventData($id = NULL)
    {
        $sql = "SELECT * FROM events";

        if (!empty($id)) {
            $sql .= " WHERE event_id =:id LIMIT 1";
        } else {
            $startTS = mktime(0, 0, 0, $this->_m, 1, $this->_y);
            $endTS = mktime(23, 59, 59, $this->_m + 1, 0, $this->_y);
            $startDate = date('Y-m-d H:i:s', $startTS);
            $endDate = date('Y-m-d H:i:s', $endTS);

            $sql .= " WHERE event_start BETWEEN '" . $startDate . "' AND '" . $endDate . "' ORDER BY event_start";
        }

        try {
            $stmt = $this->db->prepare($sql);

            if (!empty($id)) {
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $results;
        } catch (EXception $e) {
            die($e->getMessage());
        }
    }

    private function _createEventObj()
    {
        $arr = $this->_loadEventData();

        $events = array();

        foreach ($arr as $event) {
            $day = date('j', strtotime($event['event_start']));

            try {
                $events[$day][] = new Event($event);
            } catch (Exception $e) {
                die($e->getMessage());
            }
        }

        return $events;
    }

    public function buildCalendar()
    {
        define('WEEKDAYS', array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'));

        $calMonth = date('F Y', strtotime($this->_userDate));
        $calID = date('Y-m', strtotime($this->_userDate));
        $html = '<h2 id="month-' . $calID . '">' . $calMonth . '</h2>';
        for ($d = 0, $labels = NULL; $d < 7; ++$d) {
            $labels .= '<li>' . WEEKDAYS[$d] . '</li>';
        }
        $html .= '<ul class="weekdays">' . $labels . '</ul>';

        $events = $this->_createEventObj();

        $html .= '<ul>';
        for ($i = 1, $c = 1, $t = date('j'), $m = date('m'), $y = date('Y'); $c <= $this->_daysInMonth; ++$i) {
            $class = $i <= $this->_startDay ? 'fill' : NULL;

            if ($c + 1 == $t && $m == $this->_m && $y == $this->_y) {
                $class = 'today';
            }

            $ls = sprintf('<li class="%s">', $class);
            $le = '</li>';

            $date = '&nbsp;';
            $eventInfo = NULL;
            
            if ($this->_startDay < $i && $this->_daysInMonth >= $c) {
                if (isset($events[$c])) {
                    foreach ($events[$c] as $event) {
                        $link = '<a href="public/view.php?event_id=' . $event->id . '">' . $event->title . '</a>';
                        $eventInfo .= $link;
                    }
                } 

                $date = sprintf('<b>%02d</b>', $c++);
            }

            $wrap = $i != 0 && $i % 7 == 0 ? '</ul><ul>' : NULL;
            $html .= $ls . $date . $eventInfo . $le . $wrap;
        }

        while ($i % 7 != 1) {
            $html .= '<li class="fill">&nbsp;</li>';
            ++$i;
        }

        $html .= '</ul>';

        $admin = $this->_adminGeneralOptions();
        
        return $html . $admin;
    }

    private function _loadEventById($id)
    {
        if (empty($id)) {
            return NULL;
        }

        $event = $this->_loadEventData($id);

        if (isset($event[0])) {
            return new Event($event[0]);
        } else {
            return NULL;
        }
    }

    public function displayEvent($id)
    {
        if (empty($id)) {
            return NULL;
        }

        $id = preg_replace('/[^0-9]/', '', $id);
        $event = $this->_loadEventById($id);
        $ts = strtotime($event->start);
        $date = date('F d, Y', $ts);
        $start = date('g:ia', $ts);
        $end = date('g:ia', strtotime($event->end));

        $admin = $this->_adminEntryOptions($id);
        $html = '<h2>' . $event->title . '</h2>';
        $html .= '<p class="dates">' . $date . ', ' . $start . '&mdash;' . $end . '</p>';
        $html .= '<p>' . $event->description . '</p>';
        $html .= $admin;

        return $html;
    }

    public function displayForm()
    {
        if (isset($_POST['event_id'])) {
            $id = (int)$_POST['event_id'];
        } else {
            $id = NULL;
        }

        $submit = 'Create a New Event';
        $event = new stdClass;
        $event->id = '';
        $event->title = '';
        $event->start = '';
        $event->end = '';
        $event->description = '';

        if (!empty($id)) {
            $event = $this->_loadEventById($id);

            if (!is_object($event)) {
                return NULL;
            }
    
            $submit = 'Edit This Event';
        }

        $html = <<<FORM_MARKUP
            <form action="public/assets/inc/process.inc.php" method="post">
                <fieldset>
                    <legend>$submit</legend>

                    <label for="event_title">Event Title</label>
                    <input type="text" name="event_title"
                            id="event_title" value="$event->title" />

                    <label for="event_start">Start Time</label>
                    <input type="text" name="event_start"
                            id="event_start" value="$event->start" />

                    <label for="event_end">End Time</label>
                    <input type="text" name="event_end"
                            id="event_end" value="$event->end" />

                    <label for="event_description">Event Description</label>
                    <textarea type="text" name="event_description"
                            id="event_description">$event->description</textarea>
                        
                    <input type="hidden" name="event_id" value="$event->id" />
                    <input type="hidden" name="token" value="$_SESSION[token]" />
                    <input type="hidden" name="action" value="event_edit" />
                    <input type="submit" name="event_submit" value="$submit" />
                    or <a href="./">cancel</a>
                </fieldset>
            </form>
FORM_MARKUP;

        return $html;
    }

    public function processForm()
    {
        if ($_POST['action'] != 'event_edit')  {
            return 'The method processForm was accessed incorrectly';
        }

        $title = htmlentities($_POST['event_title'], ENT_QUOTES);
        $description = htmlentities($_POST['event_description'], ENT_QUOTES);
        $start = htmlentities($_POST['event_start'], ENT_QUOTES);
        $end = htmlentities($_POST['event_end'], ENT_QUOTES);

        if (!$this->_validDate($start) || !$this->_validDate($end)) {
            return 'Invalid date format! Use YYYY-MM-DD HH:MM:SS';
        }

        if (empty($_POST['event_id'])) {
            $sql = 'INSERT INTO events (event_title, event_desc, event_start, event_end) VALUES (:title, :description, :start, :end)'; 
        } else {
            $id = (int)$_POST['event_id'];
            $sql = 'UPDATE events SET event_title =:title, event_desc =:description, event_start =:start, event_end =:end WHERE event_id =' . $id;
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':start', $start, PDO::PARAM_STR);
            $stmt->bindParam(':end', $end, PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    private function _validDate($date)
    {
        $pattern = '/^(\d{4}(-\d{2}){2} (\d{2})(:\d{2}){2})$/';
        return preg_match($pattern, $date) == 1 ? true : false;
    }

    private function _adminGeneralOptions()
    {
        if (isset($_SESSION['user'])) {
            return <<<ADMIN_OPTIONS
                <a href="public/admin.php" class="admin">+ Add a New Event</a>
                <form action="public/assets/inc/process.inc.php" method="post">
                    <div>
                        <input type="submit" value="Log Out" class="logout" />
                        <input type="hidden" name="token" value="$_SESSION[token]" />
                        <input type="hidden" name="action" value="user_logout" />
                    </div>
                </form>
ADMIN_OPTIONS;
        } else {
            return <<<ADMIN_OPTIONS
                <a href="public/login.php">Log in</a>
ADMIN_OPTIONS;
        }
    }
    
    private function _adminEntryOptions($id)
    {
        if (isset($_SESSION['user'])) {
            return <<<ADMIN_OPTIONS
                <div class="admin-options">
                    <form action="public/admin.php" method="post">
                        <p>
                            <input type="submit" name="edit_event"
                                value="Edit This Event" />
                            <input type="hidden" name="event_id"
                                value="$id" />
                        </p>
                    </form>
                    <form action="public/confirm_delete.php" method="post">
                        <p>
                            <input type="submit" name="delete_event"
                                value="Delete This Event" />
                            <input type="hidden" name="event_id"
                                value="$id" />
                        </p>
                    </form>
                </div>
ADMIN_OPTIONS;
        } else {
            return NULL;
        }
    }

    public function confirmDelete($id)
    {
        if (empty($id)) {
            return NULL;
        }

        $id = preg_replace('/[^0-9]/', '', $id);

        if (isset($_POST['confirm_delete']) && $_POST['token'] == $_SESSION['token']) {
            if ($_POST['confirm_delete'] == 'Yes, Delete It') {
                $sql = 'DELETE FROM `events` WHERE `event_id` =:id LIMIT 1';

                try {
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                    $stmt->execute();
                    $stmt->closeCursor();
                    header('Location: ./');
                    return;
                } catch (Exception $e) {
                    return $e->getMessage();
                }
            } else {
                header('Location: ./');
                return;
            }
        }

        $event = $this->_loadEventById($id);

        if (!is_object($event)) {
            header('Location: ./');
        }

        return <<<CONFIRM_DELETE
            <form action="public/confirm_delete.php" method="post">
                <h2>Are you sure want to delete "$event->title"?</h2>
                <p>There is <b>no undo</b> if you continue.</p>
                <p>
                    <input type="submit" name="confirm_delete"
                        value="Yes, Delete It" />
                    <input type="submit" name="confirm_delete"
                        value="Note! Just Kidding!" />
                    <input type="hidden" name="event_id"
                        value="$event->id" />
                    <input type="hidden" name="token"
                        value="$_SESSION[token]" />
                </p>
            </form>
CONFIRM_DELETE;
    }
}
?>