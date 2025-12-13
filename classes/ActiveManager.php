<?php

/**
 *     More Camper Queue - I'm going to start asking ChatGPT to write me verses of the woes of the never ending project (3/24/23 BN)
 * 
 *      This class is to manage the functionality of supporting waitlists when a camp reservation is already present
 *      Because I cannot modify existing reservations through the API, I have to both hack the process, and involve humans
 *      This class does that by confirming if an Active entry is actually a Change Order, and then sends an email to the office
 *      Outlining the work to be done. It also snoozes the queue entry so that it doesn't display for the customer until the office
 *      gets to it.
 */

date_default_timezone_set('America/Los_Angeles');

class ActiveManager
{

    public $logger;
    protected $db;
    protected $tables;

    protected $uc;
    protected $production;
    protected $podBuilder;
    protected $busManager;

    private $currentEntry;
    private $matchingReservation;

    function __construct($logger = null)
    {

        require_once plugin_dir_path(__FILE__) . 'Transportation.php';

        $this->setLogger($logger);

        require_once plugin_dir_path(__FILE__) . 'config.php';

        require_once plugin_dir_path(__FILE__) . '../db/conn.php';
        require_once plugin_dir_path(__FILE__) . '../includes/ultracamp.php';


        $this->db = new reservationsDb();
        $this->tables = new CQConfig;

        $this->db->setLogger($this->logger);
        $this->uc = new UltracampModel($this->logger);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }

        if ($this->production) {
            $this->logger->d_bug("Operating in production!");
        } else {
            $this->logger->d_bug("Operating in Dev Mode");
        }
    }

    /**
     * Takes an object of the pod builder - used to id the correct pod for the new camp
     */
    function setPodBuilder($obj)
    {
        $this->podBuilder = $obj;
        return true;
    }

    function setBusManager($obj)
    {
        $this->busManager = $obj;
        return true;
    }

    // Takes an entry ID, and returns the camp name and overnight choice of any reservations for the camper during the same week
    function checkIfChangeOrder($entry)
    {
        $this->logger->d_bug("Checking entry for change order", $entry);
        if (is_array($entry)) {
            $e = $entry;
        } else {
            // action-camper-camp-week
            $e = explode('-', $entry);
        }

        if ($e[0] != 'A') {
            // not an active entry element, we're not going to do anything with it
            return false;
        }

        $change = ['changeOrder' => false];

        $sql = "SELECT reservation_id, reservation_ucid, camp_name, overnight_choice 
                FROM {$this->tables->reservations}
                WHERE person_ucid = ? AND week_number = ?";

        try {
            $result = $this->db->runQuery($sql, 'ii', [$e[1], $e[3]]);
        } catch (Exception $e) {
            $this->logger->error("Unable to verify existing reservation: " . $e->getMessage());
            return false;
        }

        if (!empty($result)) {
            $this->currentEntry = $e;
            $this->matchingReservation = $result[0];
            $this->logger->debug("Returning as a change order due to matching reseveration for the week", $result);

            $change['changeOrder'] = true;
            $change['campName'] = $result[0]['camp_name'];
            $change['campfireNight'] = isset($result[0]['overnight_choice']);

            return $change;
        }

        return false;
    }

    // Used by process.php to check if a change order is due to the inclusion of an add on, rather than a camp change
    function checkForAddOn($entry)
    {
        $this->logger->d_bug("Checking changeorder for addon", $entry);
        if (is_array($entry)) {
            $e = $entry;
        } else {
            // action-camper-camp-week
            $e = explode('-', $entry);
        }

        $change = ['changeOrder' => false];

        if ($e[0] != 'C') {
            // not a change order entry element, we're not going to do anything with it
            return $change;
        }

        $sql = "SELECT reservation_id, reservation_ucid, camp_name, overnight_choice 
                FROM {$this->tables->reservations}
                WHERE person_ucid = ? AND week_number = ?";

        try {
            $result = $this->db->runQuery($sql, 'ii', [$e[1], $e[3]]);
        } catch (Exception $e) {
            $this->logger->error("Unable to verify existing reservation: " . $e->getMessage());
            return false;
        }

        if (!empty($result)) {
            $change['changeOrder'] = true;
            $change['campName'] = $result[0]['camp_name'];
            $change['campfireNight'] = isset($result[0]['overnight_choice']);
        }

        return $change;
    }


    /**
     * Takes an active entry and processes the message to the office as a change order
     */
    function notifyOfficeOfChange($entry)
    {
        if (is_array($entry)) {
            $e = $entry;
        } else {
            // action-camper-camp-week
            $e = explode('-', $entry);
        }

        if ($e[0] != 'C') {
            // not an active entry element, we're not going to do anything with it
            return false;
        }

        $this->logger->d_bug("Running notifyOfficeOfChange()", $entry);

        // we need the corosponding reservation - check the cache for it
        if ($e == $this->currentEntry) {
            $reservation = $this->matchingReservation;
        } else {
            // if it's not the cached one, go get the reservation from the database
            $sql = "SELECT reservation_id, reservation_ucid, camp_name, overnight_choice FROM {$this->tables->reservations} WHERE person_ucid = ? AND week_number = ?";
            try {
                $result = $this->db->runQuery($sql, 'ii', array($e[1], $e[3]));
            } catch (Exception $e) {
                $this->logger->error("Unable to verify an existing reservation in convertActiveToChange(): " . $e->getMessage());
                return false;
            }

            $this->logger->debug("Find matching reservation", $result);

            if (!empty($result)) {
                $reservation = $result[0];
            } else {
                $this->logger->error("There was not a mathching reservation for a changelog in the database", $entry);
                return false;
            }
        }

        // If this isn't a campfire nights only reservation, we're going to 
        // check really quickly to ensure that we're not changing to the same camp - shouldn't happen, but who knows
        if (isset($reservation['camp_name'])) {
            $sql = "SELECT templateid FROM {$this->tables->camp_options} WHERE name = ? AND category = 'Summer Day Camp Options'";
            try {
                $res = $this->db->runQuery($sql, 's', $reservation['camp_name']);
            } catch (Exception $e) {
                $this->logger->error("Unable to get a template ID from camp options for " . $reservation['camp_name'] . ': ' . $e->getMessage());
                return false;
            }

            if (!empty($res)) {
                if ($res[0]['templateid'] == $e[2]) {
                    $this->logger->warning("We're trying to process a change order for the same camp" . $entry);
                    return true;
                }
            } else {
                $this->logger->warning($reservation['camp_name'] . " did not return with a template ID in notifyOfficeOfChange()" . $entry);
            }
        }

        // we need to understand if this is a change order, or if we need to hold a space in a camp or campfire nights for the 
        // addition of one or the other to an existing reservation

        $addingCamp = false;
        $addingCampfire = false;
        $changingCamps = false;
        $pod = null;
        $additionalElements = []; // for storing transportation, lunch and exended care for when adding a camp to a campfire night week

        // 1) Check to see if we're adding a camp to a campfire night
        if (isset($e[2]) && !isset($reservation['camp_name'])) {
            $addingCamp = true;
            // look up the correct pod to include the camper in so that the office doesn't have to guess
            $pod = $this->podBuilder->getPod(implode('-', $e));
            $transportation = $this->busManager->getTransportation(implode('-', $e));
            $bus = $this->busManager->getPod(implode('-', $e));
            $extCare = $this->busManager->getExtendedCare(implode('-', $e));
            $lunch = $_POST['lunchoption'] ?? [];

            $additionalElements = compact('pod', 'transportation', 'bus', 'extCare', 'lunch');
        }
        // 2) Check to see if we're adding a campfire night to a camp
        elseif ($e[2] == 73523 && $reservation['overnight_choice'] == null) {
            $addingCampfire = true;
        }
        // Otherwise, assume that we're changing camps
        else {
            $changingCamps = true;
        }

        require_once plugin_dir_path(__FILE__) . '../classes/EmailChangeOrder.php';
        $email = new EmailChangeOrder($this->logger);

        if ($email->createChangeOrder($e, $reservation, $pod, $additionalElements)) {
            if ($changingCamps) {
                try {
                    $this->processWaitlistEntry($e);
                } catch (Exception $error) {
                    $this->logger->error("Unable to udpate the database with the new camp status", $error->getMessage());
                    return false;
                }
            } elseif ($addingCamp) {
                try {
                    $this->processAddedCamp($e);
                } catch (Exception $error) {
                    $this->logger->error("Unable to add the camp to the database", $error->getMessage());
                    return false;
                }
            } elseif ($addingCampfire) {
                try {
                    $this->processAddedCampfire($e);
                } catch (Exception $error) {
                    $this->logger->error("Unable to add the campfire night to the database", $error->getMessage());
                    return false;
                }
            }

            return true;
        }
    }

    // sets a waitlist entry with the claimed status for the chosen camp in order to hold the space while the office has a chance to udpate the reservation
    function processAddedCamp($entry)
    {
        $camper = $entry[1];
        $week = $entry[2];
        $camp = $entry[3];

        $sql = "INSERT INTO {$this->tables->waitlist} 
                (accountId, camperId, week_num, campId, active)
                VALUES
                ((SELECT account_ucid as accountId FROM campers WHERE person_ucid = ? LIMIT 1),?,?,?,2)";

        try {
            $this->db->insert($sql, 'iiii', [$camper, $camper, $camp, $week]);
        } catch (Exception $e) {
            $this->logger->error("Unable to insert the newly added camp to the waitlist: " . $e->getMessage());
            return false;
        }

        return true;
    }

    function processAddedCampfire($entry)
    {
        // I thought that this may need different handling, but apparently not - keeping it seperate, just in case I was right initially
        return $this->processAddedCamp(($entry));
    }

    function processWaitlistEntry($entry)
    {
        if (is_array($entry)) {
            $e = $entry;
        } else {
            // action-camper-camp-week
            $e = explode('-', $entry);
        }

        $sql = "UPDATE {$this->tables->waitlist} SET active = 2 WHERE camperId = ? AND week_num = ? AND campId = ? AND active > 0";
        try {
            $update = $this->db->update($sql, 'iii', array($e[1], $e[3], $e[2]));
        } catch (Exception $e) {
            $this->logger->error("Unable to mark a change order as processed due to a SQL error: " . $e->getMessage());
            //  $this->logger->d_bug($sql, array($e[1], $e[3], $e[2]));
            return false;
        }

        $this->logger->d_bug("Affected Rows: ", $update);
        return $update;
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new TransportationDummyLogger();
    }
}

class ActiveManagerDummyLogger
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
