<?php

/**
 * Basic functionality that is shared among other classes - calling it a model from the idea of an MCV methodology
 * 2/7/23 (BN)
 */


date_default_timezone_set('America/Los_Angeles');

class CQModel
{

    public $logger;
    protected $db;
    protected $tables;

    protected $production;
    protected $uc; // passed in from the outside

    public $campers;
    public $camperInfo;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once __DIR__ . '/config.php';

        require_once __DIR__ . '/../db/db-reservations.php';

        $this->db = new reservationsDb();
        $this->tables = new CQConfig;

        $this->db->setLogger($this->logger);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }

        if ($this->production) {
            //     $this->logger->d_bug("Class CQModel is operating in production!");
        } else {
            //     $this->logger->d_bug("Class CQModel is operating in Dev Mode");
        }
    }

    function setUltracampObj($obj)
    {
        $this->uc = $obj;
        return true;
    }

    function getCamperName($camperId)
    {
        if (empty($camperId)) {
            wp_die("No camper ID was specificed in getCamperName()");
            return false;
        }

        // check to see if we've looked this camper up already
        if (!empty($this->camperInfo[$camperId])) {
            return array('id' => $camperId, 'FirstName' => $this->camperInfo[$camperId]['FirstName'], 'LastName' => $this->camperInfo[$camperId]['LastName']);
        }

        // for some reason - I don't go first to the database and I don't know why. So I'm adding this here for portal, but hoping it doesn't break queue
        // the actual reason is probably because queue assumes they don't have a reservation, so the DB wouldn't have had that info (3/16/23 BN)
        $sql = "SELECT person_ucid as id, first_name as FirstName, last_name as LastName FROM " . $this->tables->campers . " WHERE person_ucid = ?";
        try {
            $camper = $this->db->runQuery($sql, 'i', $camperId);
        } catch (Exception $e) {
            wp_die("Unable to query the campers table for camper $camperId: " . $e->getMessage());
            // continue - we'll try UC next
        }

        // if we got a result from the database, return it without going to UC
        // also, not cacheing the result, because it's in the database and looking it up again is fast
        if (is_array($camper) && count($camper) > 0) {
            return $camper[0];
        }

        if (empty($this->uc)) {
            $this->logger->warning("Camper $camperId was not found in CQModel->getCamperName($camperId) using the database. No Ultracamp object was provided as backup.");
            return false;
        }

        // If we don't find anything in the database, then go to Ultracamp.
        try {
            $camper = $this->uc->getPersonById($camperId);
        } catch (Exception $e) {
            wp_die("Unable to get the camper name from Ultracamp in getCamperName()");
            return false;
        }

        if (!empty($camper)) {
            $this->camperInfo[$camperId] = array('FirstName' => $camper->FirstName, 'LastName' => $camper->LastName);
            return array('id' => $camperId, 'FirstName' => $camper->FirstName, 'LastName' => $camper->LastName);
        }
    }

    // Returns the camp name when the template ID is passed in
    function getCamp($campId)
    {
        if (empty($campId)) {
            wp_die("No camp ID was specificed in getCamp()");
            return false;
        }

        $sql = "SELECT name FROM " . $this->tables->camp_options . " WHERE templateid = ? LIMIT 1";
        try {
            $result = $this->db->runQuery($sql, 'i', $campId);
        } catch (Exception $e) {
            wp_die("Unable to retrive the camp name from the database in getCamp(): " . $e->getMessage());
            return false;
        }

        if (empty($result)) {
            $this->logger->warning("Unable to get the option name for optionID $campId from the database in getCamp().");
            return false;
        }

        return $result[0]['name'];
    }

    function getWeek($weekNum)
    {
        if (empty($weekNum)) {
            wp_die("No week number specificed for getWeek()");
            return false;
        }

        $sql = "SELECT SUBSTRING(short_name, 5) as week FROM " . $this->tables->summer_weeks . " WHERE week_num = ?";
        try {
            $result = $this->db->runQuery($sql, 'i', $weekNum);
        } catch (Exception $e) {
            wp_die("Unable to retrieve the week information from the database: " . $e->getMessage());
            return false;
        }

        return $result[0]['week'];
    }

    /**
     * Get the session ID from a week number
     */
    function getSessionIdFromWeekNumber($weekNum)
    {

        $sql = "SELECT session_id as week FROM " . $this->tables->summer_weeks . " WHERE week_num = ?";
        try {
            $result = $this->db->runQuery($sql, 'i', $weekNum);
        } catch (Exception $e) {
            wp_die("Unable to retrieve the sessionID information from the database: " . $e->getMessage());
            return false;
        }

        return $result[0]['week'];
    }
    /**
     * Get the Accelerate session ID from a week number
     */
    function getAccelSessionIdFromWeekNumber($weekNum)
    {

        $sql = "SELECT session_id as week FROM accelerate_weeks WHERE week_num = ?";
        try {
            $result = $this->db->runQuery($sql, 'i', $weekNum);
        } catch (Exception $e) {
            wp_die("Unable to retrieve the sessionID information from the database: " . $e->getMessage());
            return false;
        }

        return $result[0]['week'];
    }

    /**
     * Send in a week number, get the first day back in SQL format
     */
    function getWeekStartFromWeekNumber($weekNum)
    {
        $sql = "SELECT start_date week FROM " . $this->tables->summer_weeks . " WHERE week_num = ?";
        try {
            $result = $this->db->runQuery($sql, 'i', $weekNum);
        } catch (Exception $e) {
            wp_die("Unable to retrieve the week start date information from the database: " . $e->getMessage());
            return false;
        }

        return $result[0]['week'];
    }

    /**
     * Send in a week number, get the last day back in SQL format
     */
    function getWeekEndFromWeekNumber($weekNum)
    {
        $sql = "SELECT end_date week FROM " . $this->tables->summer_weeks . " WHERE week_num = ?";
        try {
            $result = $this->db->runQuery($sql, 'i', $weekNum);
        } catch (Exception $e) {
            PluginLogger::log("Unable to retrieve the week end date information from the database: " . $e->getMessage());
            return false;
        }

        return $result[0]['week'];
    }

    // this function needs outside access to an Ultracamp Object
    function setCampersByAccount($accountId)
    {

        // start with getting all of the people
        $people = $this->uc->getPeopleByAccount($accountId);
        if (empty($people)) {
            wp_die("Ultracamp returned an invalid response for the people on the account.");
            return false;
        }

        // determine which are camper aged, and save them to an array
        foreach ($people as $person) {
            // assume anyone younger than 15 is of camper age
            if ($this->getAge($person->BirthDate, 12) < 15) {
                $this->campers[$person->Id] = array(
                    'firstName'   => $person->FirstName,
                    'lastName'    => $person->LastName,
                    'DOB'         => $person->BirthDate,
                    'personid'    => $person->Id
                );
            }
        }
    }

    // takes a birthdate and a week number
    // returns the camper's age on the first day of the session
    function getAge($dob, $week)
    {

        // because we ask for the age at week 12 at times (getCampers.php anyone - looking to ensure they're old enough at somepoint during the season)
        // we need to guard against looking for week 12 in an 11 week summer
        $sql = "SELECT week_num FROM " . $this->tables->summer_weeks . " ORDER BY week_num DESC LIMIT 1";
        try {
            $result = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            wp_die("Unable to collect any weeks from the database: " . $e->getMessage());
            return false;
        }

        if (!is_array($result)) {
            wp_die("There was an error reading the highest week number in the CQModel.php getAge method.");
            return false;
        }

        if ($result[0]['week_num'] < $week) {
            // if the week number provided exceeds the highest week number in the system, just use the next highest
            // only log this if the max week is lower than 11 (we don't need every 11 week look up to break on a week 12 request)
            if ($result[0]['week_num'] < 11) {
                $this->logger->warning("A camper's age during week $week was requested, but there are only " . $result[0]['week_num'] . " weeks in the database.");
            }

            // $this->logger->d_bug("A camper's age during week $week was requested, but there are only " .$result[0]['week_num'] ." weeks in the database.");
            $week = $result[0]['week_num'];
        }


        // get date of the first day of the session
        $sql = "SELECT start_date FROM " . $this->tables->summer_weeks . " WHERE week_num = ? LIMIT 1";
        try {
            $result = $this->db->runQuery($sql, 'i', $week);
        } catch (Exception $e) {
            wp_die("Unable to collect the startdate for week $week from the database: " . $e->getMessage());
            return false;
        }

        if (!is_array($result)) {

            $this->logger->d_bug("No result returned for week start date", $week);
            $this->logger->d_bug("Result", $result);
            return 0;
        }

        $diff = date_diff(date_create($result[0]['start_date']), date_create($dob));
        $age = $diff->format('%y');

        //        $this->logger->d_bug("Campers age is $age during the week $week.");
        //        $this->logger->d_bug("Week Start {$result[0]['start_date']}. and camper DOB $dob");
        return $age;
    }

    // counts the unique campers with waitlist entries - this is for the confirmation page to show plural or singular in the heading
    function countWaitlistCampers($entries)
    {
        $campers = array();
        foreach ($entries as $entry) {
            $a = explode('-', $entry);
            if ($a[0] == 'Q') {
                if (!in_array($a[1], $campers)) {
                    $campers[] = $a[1];
                }
            }
        }

        return count($campers);
    }


    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it

    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new CQModdelDummyLogger();
    }
}

class CQModdelDummyLogger
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
