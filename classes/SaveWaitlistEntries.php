<?php

/**
 *     Manages the waitlist functionality
 */


class SaveWaitlistEntries
{
    public $logger;
    protected $db;
    protected $tables;
    protected $CQModel;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once __DIR__ . '/config.php';

        require_once __DIR__ . '/../db/db-reservations.php';

        $this->db = new reservationsDb();
        $this->tables = new CQConfig;

        $this->db->setLogger($this->logger);
    }

    /**
     * Takes a single string from the queue grid, or an array of strings. If the first value is a character, it'll ensure it is a Q, and ignore the rest
     * if the first value is an int, then we'll assume camper-camp-week-mobile format and process all as waitlist entries
     * returns an array of the full waitlist entries so that a success status can be easily displayed.
     * Jan 4, 2024 - I added errorRecovery as the abilitiy to convert failed registrations to queue entries
     */
    function saveEntries($account, $entries, $errorRecovery = false)
    {

        if (!is_array($entries)) {
            $entry = $entries;
            unset($entries);
            $entries[] = $entry; // we run a for-each, so let's ensure it's an array
        }

        if (empty($account) || !is_numeric($account)) {
            wp_die("An account number must be provided to saveEntries().");
            return false;
        }

        // $this->logger->d_bug("Processing the following waitlist entries", $entries);

        foreach ($entries as $string) {
            // split apart the string into its components
            $e = explode('-', $string);

            //check to see if we have an action - if so, ensure it is "Q"
            // if it is not a number, and it's not a Q - then it's some other letter and we'll ignore it
            // if error reocvery is true, and the incoming element is an A or a R, save it too
            if (
                !is_numeric($e[0]) &&
                ((!$errorRecovery && $e[0] != "Q") || ($errorRecovery && ($e[0] != "R" && $e[0] != "A")))
            ) {
                continue;
            }

            // now, we need to standardize our array. It is possible that the first element is either the action, or the camper. 
            // We are going to dump the action, and make it always the camper as all actions are confirmed to be correct now.
            if (!is_numeric($e[0])) {
                array_shift($e);
            }

            // we now have 0 = camper, 1 = camp, 2 = week and option 3 = if mobile (we'll ignore that at this stage in the process)
            $this->writeToDatabase($account, $e);

            // store the details into a value for return
            $result[] = $this->getSummary($string);
        }

        return empty($result) ? true : $result; // if there are no waitlist entries, then the result is undfined
    }

    /** 
     * Does the database work of saving a waitlist entry
     * This can take the exploded string, or each element individually
     */
    function writeToDatabase($account, $entry, $camp = null, $week = null)
    {
        if (is_array($entry)) {
            $camper = $entry[0];
            $camp = $entry[1];
            $week = $entry[2];
        } else {
            $camper = $entry;
        }

        if (empty($account) || !is_numeric($account)) {
            wp_die("An account number must be provided to writeToDatabase().");
            return false;
        }

        // ensure that we have all that we need
        if (empty($camper) || empty($camp) || empty($week)) {
            wp_die("Incomplete data provided to writeToDatabase().");
            return false;
        }

        // sql uses East Coast time zone, insert pacific time here so that the retrival is in the same timezone as the inster
        $date_added = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO waitlist (accountId, camperId, week_num, campId, date_added) VALUES (?,?,?,?,?)';

        // need to find out if we're inserting a new record, updating an inactive record, or ignoring a duplicate
        $sqlDupe = "SELECT id, active FROM waitlist WHERE camperId = ? AND week_num = ? AND campId = ? ORDER by id DESC LIMIT 1";
        try {
            $dupeCheck = $this->db->runQuery($sqlDupe, 'iii', array($camper, $week, $camp));
        } catch (Exception $e) {
            wp_die("Unable to dupe check the waitlist: " . $e->getMessage());
            // $this->logger->info($sqlDupe, array($camperId, $w, $c));
        }

        // if there isn't a row, or if the row is inactive - insert a new row
        if (!isset($dupeCheck) || $dupeCheck[0]['active'] == 0) {
            try {
                $this->db->insert($sql, 'iiiis', array($account, $camper, $week, $camp, $date_added));
                // $this->logger->d_bug("Inserting Waitlist - accountId, camperId, week_num, campId, date_added", array($account, $camper, $week, $camp, $date_added));
            } catch (Exception $e) {
                wp_die("Unable to insert waitlist row", $e->getMessage());
            }
        } // if above isn't true, it's a duplicate, so we ignore it
    }

    /**
     * Takes an element that was just processed and returns an array of details that were the result of that element
     */
    function getSummary($element)
    {
        $e = explode('-', $element);

        if (!is_numeric($e[0])) {
            $result['action'] = array_shift($e);
            $result['camper'] = $this->CQModel->getCamperName($e[0]);
            $result['camp'] = $this->CQModel->getCamp($e[1]);
            $result['week'] = array('weekNum' => $e[2], 'date' => $this->CQModel->getWeek($e[2]));
        }

        return $result;
    }

    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new SaveWaitlistDummyLogger();
    }

    function setCQModel($obj)
    {
        $this->CQModel = $obj;
        return true;
    }
}

class SaveWaitlistDummyLogger
{

    function d_bug($arg1, $arg2 = null)
    {
        return true;
    }
    function debug($arg1, $arg2 = null)
    {
        return true;
    }
    function info($arg1, $arg2 = null)
    {
        return true;
    }
    function warning($arg1, $arg2 = null)
    {
        return true;
    }
    function error($arg1, $arg2 = null)
    {
        throw new Exception($arg1);
        return true;
    }
}
