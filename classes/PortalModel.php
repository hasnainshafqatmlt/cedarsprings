<?php

/**
 * Manages all of the queue entries which  on the status/portal page
 */


date_default_timezone_set('America/Los_Angeles');

class PortalModel
{

    public $logger;
    protected $db;
    protected $tables;
    protected $CQModel;

    protected $uc;
    protected $production;

    function __construct($logger = null)
    {
        // $this->setLogger($logger);

        require_once __DIR__ . '/config.php';
        require_once 'CQModel.php';

        require_once __DIR__ . '/../db/db-reservations.php';
        require_once __DIR__ . '/../includes/ultracamp.php';

        $this->db = new reservationsDb();
        $this->tables = new CQConfig;

        $this->db->setLogger($this->logger);

        $this->uc = new UltracampModel($this->logger);

        $this->CQModel = new CQModel($this->logger);
        $this->CQModel->setUltracampObj($this->uc);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }

        if ($this->production) {
            PluginLogger::log("debug:: Operating in production!");
        } else {
            PluginLogger::log("debug:: Operating in Dev Mode");
        }
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new PortalModelDummyLogger();
    }

    /**
     * Takes an account number and return with all active queues, or false if none
     */
    function getActiveQueues($account)
    {

        $sql = "SELECT id, camperId, week_num, campId, date_added, active, active_expire_date
            FROM " . $this->tables->waitlist . " 
            WHERE active = 1
            AND active_expire_date IS NOT NULL
            AND active_expire_date >= NOW()
            AND (snoozed_until IS NULL OR snoozed_until <= NOW())
            AND accountId = ?
            ORDER BY active_expire_date ASC";

        try {
            $result = $this->db->runQuery($sql, 'i', $account);
        } catch (Exception $e) {
            PluginLogger::log("error:: Unable to query waitlist for active entries for account $account: " . $e->getMessage());
            return false;
        }

        return empty($result) ? false : $result;
    }

    /**
     * Builds the array of camper / week / camps for the pending queue and registration information
     */
    function getPendingQueues($account)
    {
        // Need to get all of the campers
        $actPeople = $this->uc->getPeopleByAccount($account);

        // loop through everyone on the account
        foreach ($actPeople as $person) {
            // if the person has a birthdate in the system . . .
            if (isset($person->BirthDate)) {
                $personDOB = $person->BirthDate;
                $personAge = $this->CQModel->getAge($personDOB, 12);

                // ensure that they're within the age range for us to list them on the form
                if ($personAge >= 5 && $personAge < 15) {
                    // if they are, store their details
                    $campers[$person->Id] = array(
                        'first' => $person->FirstName,
                        'last' => $person->LastName
                    );
                }
            }
        }

        if (empty($campers)) {
            PluginLogger::log("debug:: No campers were found to be of age.");
            return false;
        }

        PluginLogger::log("debug:: Campers", $campers);

        // need to build a list of the weeks where they have a status
        foreach ($campers as $id => $c) {

            // start with the weeks for which they have reservations
            $camps = $this->getRegisteredCampsByCamper($id);

            if (!empty($camps)) {
                foreach ($camps as $camp) {
                    $campers[$id]['registered'][$camp['week_number']] = $camp['camp_name'];
                    $campers[$id]['campfireNight'][$camp['week_number']] = $camp['overnight_choice'];

                    // For Play Pass registrations, store the specific days
                    if ($camp['camp_name'] === 'Play Pass' && !empty($camp['playpass_days'])) {
                        $campers[$id]['playpass_days'][$camp['week_number']] = $camp['playpass_days'];
                    }
                }
            }

            // need to record any queued camps
            $queues = $this->getQueuedCampsByCamper($id);
            if (!empty($queues)) {
                foreach ($queues as $q) {
                    $campers[$id]['queued'][$q['week_num']][$q['campId']]['name'] = $q['name'];
                    $campers[$id]['queued'][$q['week_num']][$q['campId']]['date_added'] = $q['date_added'];
                    $campers[$id]['queued'][$q['week_num']][$q['campId']]['expire_date'] = $q['active_expire_date'];
                    $campers[$id]['queued'][$q['week_num']][$q['campId']]['snoozed_until'] = $q['snoozed_until'];
                }
            }
        }

        // send the whole thing to the view as an array and it can sort out how to display it
        PluginLogger::log("debug:: Camper Status", $campers);
        return $campers;
    }

    /**
     * Takes a person ID and returns the week and camp 
     */
    function getRegisteredCampsByCamper($camperId)
    {
        PluginLogger::log("debug:: Getting registered camps for camper $camperId");
        $sql = "SELECT week_number, camp_name, overnight_choice, reservation_id FROM " . $this->tables->reservations . " WHERE person_ucid = ? ORDER BY week_number ASC";
        try {
            $reservations = $this->db->runQuery($sql, 'i', $camperId);
            PluginLogger::log("debug:: Found reservations for camper $camperId", $reservations ?: []);
        } catch (Exception $e) {
            PluginLogger::log("error:: Unable to query the database for reservations in getRegisteredCampsByCamper(): " . $e->getMessage());
            return false;
        }

        // For Play Pass registrations, get the specific days
        if ($reservations) {
            foreach ($reservations as &$reservation) {
                if ($reservation['camp_name'] === 'Play Pass') {
                    PluginLogger::log("debug:: Found Play Pass registration", $reservation);
                    $playPassDays = $this->getPlayPassDays($reservation['reservation_id']);
                    $reservation['playpass_days'] = $playPassDays;
                    PluginLogger::log("debug:: Added Play Pass days to reservation", $reservation);
                }
            }
        }

        return $reservations;
    }

    /**
     * Get the specific days for a Play Pass registration
     * @param int $reservationId The reservation ID
     * @return array Array of day numbers that the camper is registered for
     */
    function getPlayPassDays($reservationId)
    {
        $sql = "SELECT day FROM {$this->tables->daily_reservations} WHERE reservation_id = ? AND type = 'camp' ORDER BY day ASC";
        PluginLogger::log("debug:: Getting Play Pass days for reservation $reservationId", ['sql' => $sql]);
        try {
            $result = $this->db->runQuery($sql, 'i', $reservationId);
            PluginLogger::log("debug:: Play Pass days query result", ['reservation_id' => $reservationId, 'result' => $result]);
            if ($result && !empty($result)) {
                $days = array_column($result, 'day');
                PluginLogger::log("debug:: Extracted days from daily_reservations", ['days' => $days]);
                return $days;
            }
        } catch (Exception $e) {
            PluginLogger::log("error:: Unable to query Play Pass days: " . $e->getMessage());
        }
        return [];
    }

    /**
     * Get camper queue entries by camper id
     */
    function getQueuedCampsByCamper($camperId)
    {
        $sql = "SELECT W.week_num, W.snoozed_until, W.active_expire_date, W.date_added, O.name, W.campId FROM " . $this->tables->waitlist . " W, " . $this->tables->camp_options . " O
                     WHERE W.camperId = ?
                     AND  O.templateid = W.campId
                     AND W.active = 1
                     ORDER BY W.week_num ASC";
        try {
            $reservations = $this->db->runQuery($sql, 'i', $camperId);
        } catch (Exception $e) {
            PluginLogger::log("error:: Unable to query the database for queues in getQueuedCampsByCamper(): " . $e->getMessage());
            return false;
        }

        PluginLogger::log("debug:: Pending Result: ", $reservations);
        return $reservations;
    }

    // Takes a camp name, or a template ID and returns the tag for looking up the image file
    function getCampTag($camp)
    {
        // If the incoming camp is empty, return some fallback or an empty string
        if (empty($camp)) {
            PluginLogger::log("warning:: Empty value passed to getCampTag()");
            return 'trailblazers'; //  fallback image tag 
        }
        PluginLogger::log("debug:: getcampTag Camp", [$camp]);

        // campfires send in a full data packet, get the regsitered camp name from the mess
        if (is_array($camp)) {
            $camp = array_key_first($camp);
        }

        if (is_numeric($camp)) {
            $sql = 'SELECT name, tag FROM ' . $this->tables->camp_options . ' WHERE templateid = ? LIMIT 1';
            $param = 'i';
        } else {
            $sql = 'SELECT name, tag FROM ' . $this->tables->camp_options . ' WHERE name = ? AND category IN ("Summer Day Camp Options", "Keep the Fun Alive") LIMIT 1';
            $param = 's';
        }

        try {
            $result = $this->db->runQuery($sql, $param, $camp);
        } catch (Exception $e) {
            PluginLogger::log("error:: Unable to lookup a camp tag in getCampTag($camp): " . $e->getMessage());
            PluginLogger::log("debug:: $sql", $camp);
            return false;
        }

        // return the tag if it exists, otherwise run the name through the camelCase function and return that
        return empty($result[0]['tag']) ? $this->camelCase($result[0]['name']) : $result[0]['tag'];
    }

    function camelCase($str, array $noStrip = [])
    {

        $str = ucwords($str);

        // non-alpha and non-numeric characters removed$str = ucwords($str);
        $str = preg_replace('/[\W]/', '', $str);
        $str = trim($str);

        // uppercase the first character of each word
        $str = str_replace(" ", "", $str);
        $str = lcfirst($str);

        return $str;
    }
}

class PortalModelDummyLogger
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
