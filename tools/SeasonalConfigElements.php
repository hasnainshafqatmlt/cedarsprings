<?php

/**
 * config items for data base tables, seasonal values, camp pod sizes, and other variable elements
 * so that I don't have to hunt them down all over the code base
 * Built 4/13/2023 - adopted as the waitlist is upgraded to be camper queue
 *  * Updated for season variables on 12/14/23 (BN)

 */

// We can access the database because some config elements are stored there
date_default_timezone_set('America/Los_Angeles');
require_once(__DIR__ . '/../db/conn.php');
require_once __DIR__ . '/../db/db-reservations.php';


class SeasonalConfigElements
{

    // Things that change each year
    public $currentYear = 2025;
    public $friends_table = 'friends_2025';
    public $reservations = 'reservations_summer_2025';
    public $daily_reservations = 'reservations_daily_2025';

    public $registrationStartDate = '2025-01-01';
    public $rebistrationEndDate = '2025-08-29';

    // Offseason camp start and end dates - also change each year 
    // session_id => date
    public $offseasonStartDates = [
        492018 => '2024-12-23', // Christmas Break
        506714 => '2025-02-13', // Midwinter Break
        506729 => '2025-04-07', // Spring Break week 1
        506752 => '2025-04-25' // Spring Break week 2
    ];

    public $offseasonEndDates = [
        492018 => '2025-01-03', // Christmas Break
        506714 => '2025-02-21',  // Midwinter Break
        506729 => '2025-04-11', // Spring Break week 1
        506752 => '2025-04-25', // Spring Break week 2
    ];

    // to keep from trying to purchase lunch on the 4th of July, identify the session and the day here
    public $july4thSessionId = 496231;
    public $july4thDay = 'friday';

    // Add the bus cost, so I don't have to hunt it down throughout the website
    public $transportationCost = 59;

    public $playPassDailyCost = 69;
    public $sessionDepositAmount = 50; // needed for play pass to reduce the cost of the first day by the deposit

    // this will cause basecamp waitlist entries to get the basecamp confirmation email rather than the standard waitlist email
    public $basecampConfirmationEmail = true;

    // Option Template IDs daily templates. Used to determine if an option, or reservation containing these options, needs daily considerations
    public $dailyOptionTemplates = [
        150233, // Play Pass (Summer Day Camp Options)
        150234, // Monday (Play Pass Days)
        150235, // Tuesday (Play Pass Days)
        150236, // Wednesday (Play Pass Days)
        150237, // Thursday (Play Pass Days)
        150238, // Friday (Play Pass Days)
        150239, // Monday - Pod 1 (Play Pass Pods)
        150240, // Tuesday - Pod 1 (Play Pass Pods)
        150241, // Wednesday - Pod 1 (Play Pass Pods)
        150242, // Thursday - Pod 1 (Play Pass Pods)
        150243, // Friday - Pod 1 (Play Pass Pods)
        150259, // Monday - Morning Extended Care (Extended Care)
        150260, // Tuesday - Morning Extended Care (Extended Care)
        150261, // Wednesday - Morning Extended Care (Extended Care)
        150263, // Thursday - Morning Extended Care (Extended Care)
        150264, // Friday - Morning Extended Care (Extended Care)
        150265, // Monday - Evening Extended Care (Extended Care)
        150266, // Tuesday - Evening Extended Care (Extended Care)
        150267, // Wednesday - Evening Extended Care (Extended Care)
        150268, // Thursday - Evening Extended Care (Extended Care)
        150269, // Friday - Evening Extended Care (Extended Care)
        150270, // Friday - Pod 2 (Play Pass Pods)
        150271, // Friday - Pod 3 (Play Pass Pods)
        150272, // Monday - Pod 2 (Play Pass Pods)
        150273, // Monday - Pod 3 (Play Pass Pods)
        150274, // Thursday - Pod 2 (Play Pass Pods)
        150275, // Thursday - Pod 3 (Play Pass Pods)
        150276, // Tuesday - Pod 2 (Play Pass Pods)
        150277, // Tuesday - Pod 3 (Play Pass Pods)
        150278, // Wednesday - Pod 2 (Play Pass Pods)
        150279  // Wednesday - Pod 3 (Play Pass Pods)
    ];
    // Tables that should be consistent year to year
    public $campers = 'campers';
    public $camp_options = 'camp_options';
    public $summer_weeks = 'summer_weeks';
    public $accelerate_weeks = 'accelerate_weeks';
    public $waitlist = 'waitlist';
    public $report_summer = 'report_summer';
    public $changelog = 'changelog_summer';
    public $offseason_reservations = 'reservations_offseason_2021';

    // Friend Finder Tables
    public $conflictTable = 'friend_conflicts';
    public $exceptionsTable = 'friend_exceptions';
    public $ultracampTable = 'ultracamp_friend_requests';

    // Calculated values
    public $numberOfWeeks;

    // variables internal to the configClass
    private $db;
    public $logger;

    public function __construct($logger = null)
    {

        $this->setLogger($logger);

        $this->logger->d_bug("We have a logger");

        // populate calculated values
        $this->numberOfWeeks = $this->getNumberOfWeeks();
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new ConfigDummyLogger();
    }



    // for waitlist, here are the modified pod size numbers for calculating capacity - anything not listed is assumed to have a pod size of 10
    public $podSizes = array(
        107189 => 12, // Adventure
        107186 => 12 // Outdoor Cooking

    );


    /**
     * Looks up the week count in the database and returns the number of weeks running for the season
     * This is invoked in the contstruct method and stored in the property numberOfWeeks;
     *
     * this isn't used in many places, but i'm adding it here in Jan '24 so it's available
     * first place it's used is in the registrationCount.php file as a part of the Reservations system
     */
    protected function getNumberOfWeeks()
    {
        // connect to the database if we've not done so yet
        if (empty($this->db)) {
            $this->db = new reservationsDb();
        }

        $sql = 'SELECT week_num FROM ' . $this->summer_weeks . ' ORDER BY week_num DESC LIMIT 1';
        try {
            $result = $this->db->runBaseQuery($sql);
            PluginLogger::log("Result", $result);
        } catch (exception $e) {
            wp_die("Unable to query the database for the number of weeks in the summer to to a database error.");
            wp_die($e->getMessage());
            //return a default
            return 12;
        }

        if (empty($result)) {
            wp_die("The database did not return a week number count for the SeasonalConfigElements. Using 12 as a default.");
            return 12;
        }

        return $result[0]['week_num'];
    }

    // There are several places on the website that change behavior when registration opens. I'm attempting to consolidate all of those checks
    // into a single place here. (8/28/24 BN)
    function isRegistrationOpen()
    {

        // Get the current date and time
        $now = new DateTime();

        // Format the date for logging
        $nowFormatted = $now->format('Y-m-d H:i:s');

        // Log the current date


        // Define the date boundaries
        if ($now < new DateTime($this->registrationStartDate) || $now > new DateTime($this->rebistrationEndDate)) {
            return false;
        }

        return true;
    }
}

class ConfigDummyLogger
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
