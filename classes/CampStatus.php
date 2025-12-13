<?php

// 1/12/23 (BN)
/**
 * A class to take a camper ID and return the full status of the camps that apply to them.
 * To Include:
 *  Camps they are eligable to attend
 *  Camps they have a status for, but are ineligable to attend (exceptions such as a camper registered for a camp they're not the right age for)
 *  Then - for each camp, it will indicated the following status
 *  [-] Registered  [-] Camper Queue (CQ) Active (can register)  [-] CQ Pending (on waitlist)  [-] Space Available  [-] Camp Full [-] Ineleigable (age)
 *  4/6/23 - Added [-] Total space availble for when Ultracamp doesn't have room, but the total does. This appears to the customer as a registration option, 
 *     but processes as a camper queue option as UC shows full.
 */

date_default_timezone_set('America/Los_Angeles');

class CampStatus
{

    public $logger;
    protected $db;
    protected $tables;
    public $uc;
    protected $production;
    public $CQModel;
    protected $testmode = false;

    public $camperId;
    public $campStatus;

    public $camperFirstName;
    public $camperLastName;
    public $camperDob;
    public $summerWeeks;

    function __construct($logger = null)
    {
        // $this->setLogger($logger);

        require_once __DIR__ . '/../classes/config.php';
        require_once __DIR__ . '/../classes/CQModel.php';
        require_once __DIR__ . '/../db/conn.php';
        // require_once plugin_dir_path(__FILE__) . 'classes/PluginLogger.php';


        // require_once __DIR__ .'/../../../../api/db/db-reservations.php';
        require_once __DIR__ . '/../includes/ultracamp.php';


        $this->db = new dbConnection();
        $this->tables = new CQConfig;

        // $this->db->setLogger($this->logger);
        $this->uc = new UltracampModel($this->logger);

        $this->CQModel = new CQModel($logger);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new CampStatusDummyLogger();
    }

    /**
     * Set test mode for date spoofing
     * @param bool $testmode Whether to enable test mode (spoofs date as June 1, 2025)
     */
    function setTestMode($testmode = false)
    {
        $this->testmode = (bool)$testmode;
        return true;
    }

    // take a camper ID and get their data
    function setCamper($camperId)
    {

        // ensure that we're getting an integer - this should be the camper's ultracamp ID
        if (!is_int($camperId)) {
            error_log("An invalid camper ID was passed to CampStatus.");
            return false;
        }

        $this->camperId = $camperId;

        return $this->getCampStatus();
    }

    // get the status of all of the camps for the camper
    function getCampStatus()
    {

        // 1) Load the camper's basic info (so we can do age checks, etc.)
        $this->getCamperBio();

        // Prepare the array that ultimately gets returned:
        $campStatus = array();

        // 2) Get all “registered” reservations (including Campfire Nights if present)
        //    Each row might have either:
        //      - camp_name = e.g. "Skillshot" (day camp)
        //      - overnight_choice = e.g. "Campfire Nights"
        //      - or both, if they selected both at once.
        $registeredCamps = $this->getRegisteredCamps();

        // This will hold data like:
        //    $weeksRegistered[5] = [ 'camps' => ['Skillshot', 'Campfire Nights'] ];
        $weeksRegistered = [];

        if (!empty($registeredCamps)) {
            foreach ($registeredCamps as $row) {
                $week = $row['week_number'];

                // Initialize an array to accumulate any camps for this week
                if (!isset($weeksRegistered[$week])) {
                    // Just storing all camps in an array, so we can handle multiple
                    // day camps + campfire nights if that ever occurs (shouldn’t in practice, but this is robust).
                    $weeksRegistered[$week] = [
                        'camps' => []
                    ];
                }

                // If there's a day camp name, store it
                if (!empty($row['camp_name'])) {
                    $campName = $row['camp_name'];

                    // Mark the status as "registered" for that row in $campStatus
                    $campStatus[$campName]['weeks'][$week] = 'registered';

                    // Keep track in $weeksRegistered too
                    $weeksRegistered[$week]['camps'][] = $campName;

                    // $this->logger->d_bug("Registered For Day Camp: $campName, Week: $week");
                }

                // If there's Campfire Nights
                if ($row['overnight_choice'] === 'Campfire Nights') {
                    $campStatus['Campfire Nights']['weeks'][$week] = 'registered';

                    $weeksRegistered[$week]['camps'][] = 'Campfire Nights';
                    // $this->logger->d_bug("Registered For Campfire Nights, Week: $week");
                }
            }
        }

        // 3) Retrieve active and pending CQ items and mark them in $campStatus
        $activeCQ = $this->getActiveCQ();
        if (!empty($activeCQ)) {
            foreach ($activeCQ as $q) {
                // active=2 means “registered”; else “active”
                $campStatus[$q['camp_name']]['weeks'][$q['week_number']] =
                    ($q['active'] == 2) ? 'registered' : 'active';
            }
        }

        $pendingCQ = $this->getPendingCQ();
        if (!empty($pendingCQ)) {
            foreach ($pendingCQ as $q) {
                $campStatus[$q['camp_name']]['weeks'][$q['week_number']] = 'queued';
            }
        }

        // 4) Get the list of all camps from DB (day camps + “Keep the Fun Alive”).
        $camps = $this->getAllCamps();

        // And your summer schedule (for e.g. 11 or 12 weeks).
        $weeks = $this->getSummerSchedule();
        if (empty($weeks)) {
            error_log("No Summer Weeks found—cannot build the grid properly.");
            return $campStatus; // or return false
        }

        // For each camp row, figure out if the camper is age-eligible
        // and build out the blank statuses if not yet set.

        foreach ($camps as $camp) {
            $campName   = $camp['camp_name'];
            $templateId = $camp['templateid'];

            // Check if camper is *ever* eligible in any week (i.e., not out-of-range for the entire summer)
            $everEligible = false;
            for ($a = 0; $a < count($weeks); $a++) {
                if ($this->checkEligibility($camp['minAge'], $camp['maxAge'], $weeks[$a]['start_date'])) {
                    $campStatus[$campName]['id'] = $templateId;
                    $everEligible = true;
                    break;
                }
            }

            if (!$everEligible) {
                // If truly never eligible, skip out of the loop
                // Comment this out if you want to see all inelligable camps in the grid
                continue;
            }

            // Loop each possible week
            for ($j = 1; $j <= count($weeks); $j++) {
                // If $campStatus[$campName]['weeks'][$j] is *already* set (like registered, queued, active, etc.) 
                // then we leave it alone, unless you want to override if it’s “age ineligible”
                if (!empty($campStatus[$campName]['weeks'][$j])) {
                    continue;
                }

                // Age check for this specific week
                $isEligible = $this->checkEligibility($camp['minAge'], $camp['maxAge'], $weeks[$j - 1]['start_date']);
                if (!$isEligible) {
                    // If they are not eligible, set “ineligible” only if not already set
                    $campStatus[$campName]['weeks'][$j] = 'ineligible';
                    continue;
                }

                // If the camper *is* eligible, figure out availability (available/full/total/unavailable)
                $availability = $this->getRegistrationAvailability($templateId, $j);

                $campStatus[$campName]['weeks'][$j] = $availability;
            }
        }


        // 5) Now we make unavaialable the camps on weeks where the camper is registered elsewhere.
        //    We convert the array of “camps” per week into flags: “hasDayCamp” or “hasCampfireNights.”
        $weekRegistrations = [];

        foreach ($weeksRegistered as $weekNum => $data) {
            // data['camps'] is an array of camp names for that week
            $weekRegistrations[$weekNum] = [
                'hasDayCamp'  => false,
                'hasCampfire' => false,
                'hasAccelerate' => false
            ];

            foreach ($data['camps'] as $campName) {
                if ($campName === 'Campfire Nights') {
                    $weekRegistrations[$weekNum]['hasCampfire'] = true;
                } else if ($campName === 'Accelerate: Week Long Overnight Camp') {
                    // Note Accelerate, as both day camp and campfire nights are not available when this is registered
                    $weekRegistrations[$weekNum]['hasAccelerate'] = true;
                    $weekRegistrations[$weekNum]['hasDayCamp'] = true;
                } else {
                    // Otherwise assume day camp
                    $weekRegistrations[$weekNum]['hasDayCamp'] = true;
                }
            }
        }

        // Single pass to handle your day-camp + campfire logic
        foreach ($campStatus as $campName => &$campArray) {
            foreach ($campArray['weeks'] as $weekNum => $currentState) {
                // If it’s already “registered,” “queued,” or “active,” or “registered elsewhere,” skip
                if (in_array($currentState, ['registered', 'queued', 'active', 'registered elsewhere'])) {
                    continue;
                }

                // If there's no registration data for that week, do nothing
                if (!isset($weekRegistrations[$weekNum])) {
                    continue;
                }

                $hasDayCamp  = $weekRegistrations[$weekNum]['hasDayCamp'];
                $hasCampfire = $weekRegistrations[$weekNum]['hasCampfire'];
                $hasAccelerate = $weekRegistrations[$weekNum]['hasAccelerate'];

                // Identify if this row is “Campfire Nights”
                $thisIsCampfireNights = ($campName === 'Campfire Nights');

                //1) Has Accelerate → mark both day camps and campfire as registered elsewhere
                if ($hasAccelerate) {
                    $campArray['weeks'][$weekNum] = 'registered elsewhere';
                }

                // 2) Has both day camp + campfire → block other day camps
                elseif ($hasDayCamp && $hasCampfire) {
                    if (!$thisIsCampfireNights) {
                        // Another day camp → block it
                        $campArray['weeks'][$weekNum] = 'registered elsewhere';
                    }
                    // If it *is* Campfire Nights, do nothing (remain “registered”)

                    // 3) Has day camp only → block other day camps
                } elseif ($hasDayCamp && !$hasCampfire) {
                    if (!$thisIsCampfireNights) {
                        // Another day camp row → block
                        $campArray['weeks'][$weekNum] = 'registered elsewhere';
                    }
                    // If Campfire Nights row → set as Active to allow for change order
                    else {
                        // Only set as add-on if it's not already marked as unavailable or ineligible
                        if ($campArray['weeks'][$weekNum] !== 'unavailable' && $campArray['weeks'][$weekNum] !== 'ineligible') {
                            $campArray['weeks'][$weekNum] = 'add-on';
                        }
                        // If it's already marked as unavailable or ineligible, leave it that way
                    }

                    // 4) Has campfire only → set daycamp as active but block accelerate
                } elseif (!$hasDayCamp && $hasCampfire) {
                    if ($campName === 'Accelerate: Week Long Overnight Camp') {
                        $campArray['weeks'][$weekNum] = 'registered elsewhere';
                    } else {
                        $campArray['weeks'][$weekNum] = 'add-on';
                    }
                }
            }
        }

        // 6) Sort the camps so Explorers come first, Campfire Nights is 3rd priority, etc.
        uksort($campStatus, function ($a, $b) {
            $priority = [
                'Play Pass'                               => 0,
                'Explorers - Cubs'                        => 1,
                'Explorers - Grizzlies'                   => 1,
                'Campfire Nights'                         => 3,
                'Accelerate: Week Long Overnight Camp'    => 4
            ];
            $aPriority = $priority[$a] ?? 2;
            $bPriority = $priority[$b] ?? 2;

            if ($aPriority === $bPriority) {
                return strcmp($a, $b); // Alphabetically if same priority
            }
            return $aPriority <=> $bPriority;
        });
        // $this->logger->debug("Camp Status", $campStatus);

        return $campStatus;
    }

    /**
     * get the camper's name and age, first from the DB, and then from ultracamp if the DB doesn't have them
     */
    function getCamperBio()
    {

        if (empty($this->camperId)) {
            error_log("Set a camper ID prior to getting Camper Info.");
            return false;
        }

        // See if we have this camper in the database
        $sql = 'SELECT first_name, last_name, birth_date FROM ' . $this->tables->campers . ' WHERE person_ucid = ? LIMIT 1';
        try {
            $dbCamper = $this->db->runQuery($sql, 'i', $this->camperId);
        } catch (Exception $e) {
            error_log("Unable to query the camper database in getCamperBio(): " . $e->getMessage());
            // continue on though because we have Ultracamp as a backup
        }

        // if the DB gave a result, store the answers and quit
        if (!empty($dbCamper)) {
            $this->camperFirstName = $dbCamper[0]['first_name'];
            $this->camperLastName = $dbCamper[0]['last_name'];
            $this->camperDob = date("m/d/Y", strtotime($dbCamper[0]['birth_date']));
            return true;
        }

        // if we're here, the DB returned a null result and we need to check in with Ultracamp
        try {
            $ucInfo = $this->uc->getPersonById($this->camperId);
        } catch (Exception $e) {
            error_log("Unable to get camper bio info from Ultracamp: " . $e->getMessage());
            return false;
        }

        if (empty($ucInfo)) {
            error_log("Ultracamp did not return camper info for camper ID " . $this->camperId);
            return false;
        }

        $this->camperFirstName = $ucInfo->FirstName;
        $this->camperLastName = $ucInfo->LastName;
        $this->camperDob = $ucInfo->BirthDate;
        return true;
    }

    /**
     * Returns the weeks and dates for the weeks of the summer
     */
    function getSummerSchedule()
    {
        $sql = 'SELECT session_id, start_date, end_date, week_num, short_name FROM ' . $this->tables->summer_weeks . ' ORDER BY week_num';
        try {
            $weeks = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            error_log("Unable to load the summer schedule in CampStatus: " . $e->getMessage());
            return false;
        }

        $this->summerWeeks = $weeks;
        return $weeks;
    }

    /**
     * Returns all of the camp options in the database
     */
    function getAllCamps()
    {
        $sql = 'SELECT name AS camp_name
                    , templateid
                    , minAge
                    , maxAge 
                FROM ' . $this->tables->camp_options
            . ' WHERE category IN ( "Summer Day Camp Options", "Keep the Fun Alive" )
                ORDER BY name ASC';
        try {
            $camps = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            error_log("Unable to load the options from the database in getCampStatus(): " . $e->getMessage());
            return false;
        }

        return $camps;
    }

    /**
     * Takes the camper's DOB and the date of the camp and returns an (int) Age for that camper
     */
    function calculateAge($dob, $targetDate)
    {
        $targetDate = strtotime($targetDate);
        $birthDate = explode("/", $dob);

        $age = date("md", strtotime($dob)) > date("md", $targetDate)
            ? ((date("Y", $targetDate) - $birthDate[2]) - 1)
            : (date("Y", $targetDate) - $birthDate[2]);

        return $age;
    }

    function checkEligibility($minAge, $maxAge, $campStart)
    {
        if (empty($this->camperDob)) {
            error_log("Set a camper ID prior to running checkEligibility.");
            return false;
        }

        // get the camper's age at the start of camp, and return if it fits within the min/max range
        $age = $this->calculateAge($this->camperDob, $campStart);

        return ($age >= $minAge && $age <= $maxAge);
    }

    function getRegisteredCamps()
    {
        if (empty($this->camperId)) {
            error_log("Set a camper ID prior to running getRegisteredCamps().");
            return false;
        }

        $sql = 'SELECT 
                      reservation_ucid
                    , session_ucid
                    , week_number
                    , camp_name
                    , overnight_choice 
                FROM ' . $this->tables->reservations
            . ' WHERE person_ucid = ?
                 ORDER BY week_number ASC';

        try {
            $camps = $this->db->runQuery($sql, 'i',  $this->camperId);
        } catch (Exception $e) {
            error_log("Unable to retrieve reservations from the database: " . $e->getMessage());
            $this->logger->info($sql);
            return false;
        }

        return $camps;
    }

    /**
     * Lists camps forwhich the queue is active and they can sign up
     */
    function getActiveCQ()
    {
        if (empty($this->camperId)) {
            error_log("Set a camper ID prior to running getPendingCq().");
            return false;
        }

        $sql = 'SELECT w.id, o.name AS camp_name, w.week_num AS week_number, active FROM ' . $this->tables->waitlist
            . ' w JOIN ' . $this->tables->camp_options
            . ' o ON o.templateid = w.campId
                WHERE camperId = ?
                AND active > 0
                AND (snoozed_until IS NULL OR snoozed_until < NOW())
                AND (active_expire_date > NOW())';

        try {
            $q = $this->db->runQuery($sql, 'i', $this->camperId);
        } catch (Exception $e) {
            error_log("Unable to list the queued camps in getActiveCQ: " . $e->getMessage());
            return false;
        }

        return $q;
    }

    /**
     * Lists the camps which the camper is in queue for - i.e. on the waitlist
     */
    function getPendingCQ()
    {
        if (empty($this->camperId)) {
            error_log("Set a camper ID prior to running getPendingCq().");
            return false;
        }

        $sql = 'SELECT w.id, o.name AS camp_name, w.week_num AS week_number FROM ' . $this->tables->waitlist
            . ' w JOIN ' . $this->tables->camp_options
            . ' o ON o.templateid = w.campId
                WHERE camperId = ?
                AND active > 0
                AND (active_expire_date IS NULL)';

        try {
            $q = $this->db->runQuery($sql, 'i', $this->camperId);
        } catch (Exception $e) {
            error_log("Unable to list the queued camps in getPendingCQ: " . $e->getMessage());
            return false;
        }

        return $q;
    }

    /**
     * Takes a camp name and a week and returns unavailable, full, available, or total (which is the key word for room in CQ but not UC)
     */
    function getRegistrationAvailability($camp, $week)
    {
        // account for zero padded numbers as week names
        if (strlen($week) == 1) {
            $week = "0" . $week;
        }

        // Special handling for Play Pass current week
        if ($camp == 150233) { // Play Pass template ID
            // For Play Pass, check if we're still within the week (end date hasn't passed)
            $weekEnd = $this->CQModel->getWeekEndFromWeekNumber($week);
            $currentTimestamp = getTestModeTimestamp($this->testmode);
            if (strtotime($weekEnd) < $currentTimestamp) {
                return 'unavailable'; // Week has completely passed
            }
            // If we're still within the week, continue with normal availability check
        } else {
            // Non-Play Pass camps - existing logic
            // if the week number coming in is in the past - then show the camp as unavailable
            $weekStart = $this->CQModel->getWeekStartFromWeekNumber($week);
            $currentTimestamp = getTestModeTimestamp($this->testmode);
            if (strtotime($weekStart) < $currentTimestamp) {
                return 'unavailable';
            }
        }

        $sql = "SELECT cap" . $week . " AS capacity, reg" . $week . " AS registered, total" . $week . " AS total FROM " . $this->tables->report_summer . " WHERE ucid = ?";

        try {
            $numbers = $this->db->runQuery($sql, 'i', $camp);
        } catch (Exception $e) {
            $this->logger->error("Unable to query the capacity numbers in getRegistrationAvailability: " . $e->getMessage());
            return false;
        }

        $capacity = $numbers[0]['capacity'] ?? 0;
        $registered = $numbers[0]['registered'] ?? 0;
        $total = $numbers[0]['total'] ?? null;

        if ($capacity == 0) {
            return "unavailable";
        }

        if ($capacity > $registered) {
            // Basecamp always shows as full whenever it is not unavailable
            if ($camp == 107195) {
                return 'full';
            }
            return "available";
        }

        // a camp returns 'total' full when registered >= capacity AND registered+queued < Total
        if ($numbers[0]['registered'] >= $numbers[0]['capacity']) {

            // for this to work, we have to be able to calculate null totals
            if ($numbers[0]['total'] == NULL) {
                $podsize = (empty($this->tables->podSizes[$camp])) ? 10 : $this->tables->podSizes[$camp];
                $numbers[0]['total'] = (($numbers[0]['capacity'] + 3) % $podsize == 0) ? $numbers[0]['capacity'] + 3 : $numbers[0]['capacity'];
            }

            // we also need to know how many people are in queue
            $queued = $this->countQueues($camp, $week);
            if ($queued == 'error') {
                return "full"; // there was an error, just mark it as full so we don't break anything
            }

            //        $this->logger->d_bug("Total Check for $camp and week $week with queue $queued", $numbers[0]);

            // check to see if there is any room in the total count, beyond what Ultracamp allows for
            if ($numbers[0]['total'] > ($queued + $numbers[0]['registered'])) {
                return 'total';
            }
        }

        // to get here simply means that the camp is full
        return "full";
    }

    function countQueues($templateid, $week)
    {
        $sql = "SELECT count(*) AS queues FROM waitlist 
                WHERE campId = ? 
                AND week_num = ? 
                AND active > 0 
                AND (active_expire_date >= CURRENT_DATE OR active_expire_date IS NULL)
                AND (snoozed_until IS NULL OR snoozed_until < CURRENT_DATE)";

        try {
            $result = $this->db->runQuery($sql, 'ii', array($templateid, $week));
        } catch (Exception $e) {
            error_log("Unable to count the waitlist entries for a camp: " . $e->getMessage());
            return 'error';
        }

        if (!empty($result)) {
            return $result[0]['queues'];
        }

        return 0;
    }
}


class CampStatusDummyLogger
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
