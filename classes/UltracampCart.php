<?php
require_once plugin_dir_path(__FILE__) . '/../classes/PluginLogger.php';
/**
 *     Manages the shopping cart functionality
 */

date_default_timezone_set('America/Los_Angeles');
require_once plugin_dir_path(__FILE__) . '../api/ultracamp/CartAndUser.php';
require_once plugin_dir_path(__FILE__) . '../db/db-reservations.php';

require_once plugin_dir_path(__FILE__) . 'CQModel.php';
require_once plugin_dir_path(__FILE__) . 'config.php';
class UltracampCart extends CartAndUser
{

    public $logger;
    protected $db;
    protected $tables;

    protected $CQModel;
    protected $playPassManager;

    function __construct($logger = null)
    {
        parent::__construct($logger);

        $this->db = new reservationsDb($logger);
        $this->tables = new CQConfig;
        $this->CQModel = new CQModel($logger);

        // Initialize Play Pass Manager
        require_once(__DIR__ . '/PlayPassManager.php');
        $this->playPassManager = new PlayPassManager($logger);
        $this->playPassManager->setCQModel($this->CQModel);
    }

    /**
     * Takes a week number and returns the session ID
     */
    function getSessionId($weekNum)
    {
        if (empty($weekNum)) {
            PluginLogger::log("Error:: No week number supplied in getSessionId().");
            return false;
        }

        $sql = "SELECT session_id FROM " . $this->tables->summer_weeks . " WHERE week_num = ?";
        try {
            $result = $this->db->runQuery($sql, 'i', $weekNum);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to get the session ID from the database for week number $weekNum: " . $e->getMessage());
            return false;
        }

        return $result[0]['session_id'];
    }

    /**
     * Takes the template ID and the session ID and returns the specific session option ID for that week and element
     */
    function getSessionOptionId($templateid, $sessionid)
    {

        if (empty($templateid) || empty($sessionid)) {
            PluginLogger::log("Error:: Unable to look up session option ID without both the templateid and sessionid provided.", compact('templateid', 'sessionid'));
            return [];
        }

        // assume that we have the information in the database, go to Ultracamp if we don't. Ultracamp is really slow (4 sec) to respond however
        $sql = "SELECT optionid FROM camp_option_mapping WHERE sessionid = ? AND templateid = ? LIMIT 1";
        try {
            $result = $this->db->runQuery($sql, 'ii', array($sessionid, $templateid));
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to select the optionid from the database: " . $e->getMessage());
        }

        if (!empty($result)) {
            return $result[0]['optionid'];
        }


        $url = "https://rest.ultracamp.com/api/camps/107/sessionOptions?sessionOptionTemplateIds=" . $templateid . "&sessionIds=" . $sessionid;
        $result = $this->processRequest($url);

        // we should only get one response back. Invalid queries currently return ALL of the options, that too isn't helpful
        if (empty($result) || count($result) > 1) {
            PluginLogger::log("Error:: Unable to get the session option ID from Ultracamp either.");
            return false;
        }

        return $result[0]->SessionOptionId;
    }

    /**
     * Takes the template ID and the session ID and returns the specific session option ID for the Active Queue option for that week and element
     */
    function getActiveQueueSessionOptionId($templateid, $sessionid)
    {

        // we need to take the name of the camp passed in, and look up the template ID for the camp queue equivilent.
        // we'll then look up the session specific option ID
        $sqlFind = "SELECT templateid FROM camp_options WHERE category = 'Camp Queue' AND name = (SELECT name from camp_options WHERE templateid = ?)";
        try {
            $finder = $this->db->runQuery($sqlFind, 'i', $templateid);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to query the camp options for an equivalent camper queue option: " . $e->getMessage());
        }

        if (empty($finder)) {
            PluginLogger::log("Error:: There is not an equivalent camper queue option template for templateid $templateid");
            return false;
        }

        // return to the database to look up the camp queue optionid
        $sql = "SELECT optionid FROM camp_option_mapping WHERE sessionid = ? AND templateid = ? LIMIT 1";
        try {
            $result = $this->db->runQuery($sql, 'ii', array($sessionid, $finder[0]['templateid']));
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to select the camp queue optionid from the database: " . $e->getMessage());
        }

        if (!empty($result)) {
            return $result[0]['optionid'];
        }

        return false;
    }

    /**
     * Gets the cost of an option for the cart
     */
    function getOptionCost($optionid)
    {
        $sql = "SELECT cost FROM camp_option_mapping WHERE optionid = ?";

        try {
            $result = $this->db->runQuery($sql, 'i', $optionid);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to collect the option cost from the database: " . $e->getMessage());
            return false;
        }

        if (empty($result)) {
            PluginLogger::log("warning:: Unable to retrive a cost from the database for option $optionid");
            return false;
        }

        return $result[0]['cost'];
    }

    /**
     * Takes an array of week days from the webform and returns the templateid and option name for each
     * Also needs the session ID to not try and lookup lunch on July 4th
     */
    function processLunch($lunches, $sessionId)
    {
        $result = array();

        if (empty($lunches)) {
            return $result;
        }

        foreach ($lunches as $day) {
            if ($day == 'selectall') {
                continue;
            }

            // remove July 4th from the list of options
            if ($sessionId == $this->tables->july4thSessionId && $day == $this->tables->july4thDay) {
                continue;
            }

            $day .= ' lunch';
            $sql = 'SELECT templateid, name FROM ' . $this->tables->camp_options . ' WHERE category = "Camp Hot Lunch" and name = ? LIMIT 1';
            try {
                $dbResult = $this->db->runQuery($sql, 's', $day);
            } catch (Exception $e) {
                PluginLogger::log("Error:: Unable to load lunch days from the database: " . $e->getMessage());
                continue;
            }

            if (empty($dbResult)) {
                PluginLogger::log("warning:: No lunch options were returned with the day $day.");
                continue;
            }

            $result[] = $dbResult[0];
        }
        return $result;
    }

    /**
     * Takes the extended care flage(A, P or F) and returns an array with one or two extended care options
     */
    function processExtCare($key)
    {

        if (empty($key)) {
            return array();
        }

        switch ($key) {
            case 'A':
                $sql = "SELECT templateid, name FROM " . $this->tables->camp_options . " WHERE category = 'Extended Care' AND name LIKE 'morning%' AND parent <> 'Play Pass Extended Care'";
                break;

            case 'P':
                $sql = "SELECT templateid, name FROM " . $this->tables->camp_options . " WHERE category = 'Extended Care' AND name LIKE 'afternoon%' AND parent <> 'Play Pass Extended Care'";
                break;

            case 'F':
                $sql = "SELECT templateid, name FROM " . $this->tables->camp_options . " WHERE category = 'Extended Care' AND parent <> 'Play Pass Extended Care'";
                break;

            default:
                PluginLogger::log("Error:: Invalid key of '$key' submitted to processExtCare().");
                return false;
        }

        try {
            $dbResult = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to retrieve extended care information from the database: " . $e->getMessage());
            return false;
        }

        PluginLogger::log("ProcessExtCart($key)", [$dbResult]);

        if (!is_array($dbResult)) {
            PluginLogger::log("warning:: There were no extended care options found for $key type ext care request.");
            return [];
        }

        return $dbResult;
    }

    /**
     * Takes the account number for the reservations, and the cartOptions array from the shopping cart page and adds each of the array elements to the cart
     * it returns the UC SSO URL
     */
    function addEntriesToCart($account, $reservations)
    {
        if (empty($account) || !is_numeric($account)) {
            PluginLogger::log("Error:: An account number must be provided to addEntriesToCart().");
            return false;
        }

        // First, reorganize reservations by session/camper pairs
        $consolidatedReservations = [];
        foreach ($reservations as $k => $entry) {
            $key = $entry['camper']['personid'] . '-' . $entry['week']['sessionid'];

            // Check if this is a Play Pass entry
            if (isset($entry['playPassDays'])) {
                // Handle Play Pass entries differently
                $consolidatedReservations[$key] = $entry;
                continue;
            }

            // Check if this is an Accelerate registration
            if (isset($entry['camp']) && $entry['camp']['templateid'] == 9999) {
                // Always prioritize Accelerate registrations
                $consolidatedReservations[$key] = $entry;
                continue;
            }

            // If this is a campfire night entry
            if (isset($entry['camp']) && $entry['camp']['name'] === 'Campfire Nights') {
                if (isset($consolidatedReservations[$key])) {
                    // Don't add campfire nights to Accelerate registrations
                    if (!isset($consolidatedReservations[$key]['accelSunday'])) {
                        $consolidatedReservations[$key]['campfireNight'] = $entry['camp'];
                    }
                } else {
                    // Store campfire night entry until we find its matching camp
                    $consolidatedReservations[$key] = $entry;
                }
            } else if (isset($entry['camp'])) {
                if (isset($consolidatedReservations[$key])) {
                    // If we already have a campfire night entry, merge it in
                    $entry['campfireNight'] = $consolidatedReservations[$key]['camp'];
                }
                $consolidatedReservations[$key] = $entry;
            } else {
                // This handles any other entries that don't have a camp (like Accelerate)
                $consolidatedReservations[$key] = $entry;
            }
        }

        // Process each consolidated reservation
        foreach ($consolidatedReservations as $key => $entry) {
            $sessionId = $entry['week']['sessionid'];
            $options = [];

            // Check if this is a Play Pass entry
            if (isset($entry['playPassDays'])) {
                // Process Play Pass entries

                $playPassOptions = $this->processPlayPassEntry($entry, $options);
                if ($playPassOptions) {
                    $options = $playPassOptions;
                }
            } else {
                if (!empty($entry['camp'])) {
                    // Regular camp and campfire night handling
                    if ($entry['camp']['name'] !== 'Campfire Nights') {
                        $campOptionId = $this->getSessionOptionId($entry['camp']['templateid'], $sessionId);
                        $campCost = $this->getOptionCost($campOptionId);

                        if (!empty($campOptionId)) {
                            $options[] = array(
                                'IdSessionOption' => (int)$campOptionId,
                                'Name' => $entry['camp']['name'],
                                'Amount' => $campCost,
                                'Quantity' => 1
                            );
                        }
                    } else {
                        // This is a standalone campfire night - process it and skip other options
                        $campfireOptionId = $this->getSessionOptionId($entry['camp']['templateid'], $sessionId);
                        $campfireCost = $this->getOptionCost($campfireOptionId);

                        if (!empty($campfireOptionId)) {
                            $options[] = array(
                                'IdSessionOption' => (int)$campfireOptionId,
                                'Name' => $entry['camp']['name'],
                                'Amount' => $campfireCost,
                                'Quantity' => 1
                            );
                        }

                        // Skip to the administrative tracking option
                        $optionId = $this->getSessionOptionId('129682', $sessionId);
                        if (!empty($optionId)) {
                            $options[] = array(
                                'IdSessionOption' => $optionId,
                                'Name' => 'Website Registration',
                                'Amount' => 0,
                                'Quantity' => 1
                            );
                        }

                        // Process this reservation and continue to next entry
                        if (!$this->addReservation($sessionId, $entry['camper']['personid'], $account, $options)) {
                            return false;
                        }
                        continue;
                    }
                } else {
                    // Accelerate transportation handling
                    if (!empty($entry['accelSunday'])) {
                        if ($entry['accelSunday'] == 'bus') {
                            $accelTemplate = 83718;
                            $accelName = 'Monday Ride to Camp';
                        } else {
                            $accelTemplate = 116688; // direct
                            $accelName = 'Monday Direct Drop Off';
                        }

                        $optionId = $this->getSessionOptionId($accelTemplate, $sessionId);
                        if (!empty($optionId)) {
                            $options[] = array(
                                'IdSessionOption' => $optionId,
                                'Name' => $accelName,
                                'Amount' => $this->getOptionCost($optionId),
                                'Quantity' => 1
                            );
                        }
                    }

                    if (!empty($entry['accelFriday'])) {
                        if ($entry['accelFriday'] == 'bus') {
                            $accelTemplate = 83719;
                            $accelName = 'Friday Ride Home From Camp';
                        } else {
                            $accelTemplate = 116687; // direct
                            $accelName = 'Friday Direct Pickup';
                        }

                        $optionId = $this->getSessionOptionId($accelTemplate, $sessionId);
                        if (!empty($optionId)) {
                            $options[] = array(
                                'IdSessionOption' => $optionId,
                                'Name' => $accelName,
                                'Amount' => $this->getOptionCost($optionId),
                                'Quantity' => 1
                            );
                        }
                    }
                }

                // Add campfire night if present
                if (!empty($entry['campfireNight'])) {
                    $campfireOptionId = $this->getSessionOptionId($entry['campfireNight']['templateid'], $sessionId);
                    $campfireCost = $this->getOptionCost($campfireOptionId);

                    if (!empty($campfireOptionId)) {
                        $options[] = array(
                            'IdSessionOption' => (int)$campfireOptionId,
                            'Name' => $entry['campfireNight']['name'],
                            'Amount' => $campfireCost,
                            'Quantity' => 1
                        );
                    }
                }

                // Camp Pod
                if (!empty($entry['campPod'])) {
                    $optionId = $this->getSessionOptionId($entry['campPod']['templateid'], $sessionId);
                    if (!empty($optionId)) {
                        $options[] = array(
                            'IdSessionOption' => $optionId,
                            'Name' => $entry['campPod']['name'],
                            'Amount' => $this->getOptionCost($optionId),
                            'Quantity' => 1
                        );
                    }
                }

                // Transportation
                if (!empty($entry['transportation'])) {
                    // Lake Stevens Drop Off special handling
                    if (substr($entry['transportation']['name'], 0, 6) == "Window") {
                        $optionId = $this->getSessionOptionId(107198, $sessionId);
                        if (!empty($optionId)) {
                            $options[] = array(
                                'IdSessionOption' => $optionId,
                                'Name' => 'Lake Stevens - Direct Drop-Off',
                                'Amount' => $this->getOptionCost($optionId),
                                'Quantity' => 1
                            );
                        }
                    }

                    $optionId = $this->getSessionOptionId($entry['transportation']['templateid'], $sessionId);
                    if (!empty($optionId)) {
                        $options[] = array(
                            'IdSessionOption' => $optionId,
                            'Name' => $entry['transportation']['name'],
                            'Amount' => $this->getOptionCost($optionId),
                            'Quantity' => 1
                        );
                    }
                }

                // Bus Pod
                if (!empty($entry['busPod'])) {
                    $optionId = $this->getSessionOptionId($entry['busPod']['templateid'], $sessionId);
                    if (!empty($optionId)) {
                        $options[] = array(
                            'IdSessionOption' => $optionId,
                            'Name' => $entry['busPod']['name'],
                            'Amount' => $this->getOptionCost($optionId),
                            'Quantity' => 1
                        );
                    }
                }

                // Lunch
                if (!empty($entry['lunch'])) {
                    foreach ($this->processLunch($entry['lunch'], $sessionId) as $lunch) {
                        $optionId = $this->getSessionOptionId($lunch['templateid'], $sessionId);
                        if (!empty($optionId)) {
                            $options[] = array(
                                'IdSessionOption' => $optionId,
                                'Name' => $lunch['name'],
                                'Amount' => $this->getOptionCost($optionId),
                                'Quantity' => 1
                            );
                        }
                    }
                }

                // Extended Care
                if (!empty($entry['extCare'])) {
                    PluginLogger::log("UltracampCart Adding Weekly Extended Care", [$entry['extCare']]);

                    foreach ($this->processExtCare($entry['extCare']) as $extCare) {
                        $optionId = $this->getSessionOptionId($extCare['templateid'], $sessionId);
                        if (!empty($optionId)) {
                            $options[] = array(
                                'IdSessionOption' => $optionId,
                                'Name' => $extCare['name'],
                                'Amount' => $this->getOptionCost($optionId),
                                'Quantity' => 1
                            );
                        }
                    }
                }

                // Administrative tracking option
                $optionId = $this->getSessionOptionId('129682', $sessionId);
                if (!empty($optionId)) {
                    $options[] = array(
                        'IdSessionOption' => $optionId,
                        'Name' => 'Website Registration',
                        'Amount' => 0,
                        'Quantity' => 1
                    );
                }
            }

            // while Ultracamp won't allow us to set prices in the option through the API, we're going to instead send in a discount code
            PluginLogger::log("Play Pass Entry", $entry);
            $discounts = [];

            // if the reservation has the Play Pass camp, add the NRF Adjustment Discount
            /*
            // Ultracamp changed something and now the option pricing structure is in play, causing the discount to be a real problem. 5/16/25 (BN)
            if(!empty($entry['camp']) && $entry['camp']['templateid'] == 150233) {
                $discounts[] = $this->buildPlayPassNRFAdjustmentDiscount($entry['week']['weekNum']);
            }
            */

            if (!$this->addReservation($sessionId, $entry['camper']['personid'], $account, $options, $discounts)) {
                return false;
            }
        }

        return $this->cartContainer;
    }

    /**
     * Process Play Pass entries and build cart options
     * 
     * @param array $entry The Play Pass entry data
     * @param array $options Existing options array
     * @return array Updated options array with Play Pass options added
     */
    function processPlayPassEntry($entry, $options)
    {
        $sessionId = $entry['week']['sessionid'];
        $weekNum = $entry['week']['weekNum'];

        // Get pricing information from PlayPassManager
        $dayCost = $this->playPassManager->getPlayPassDayCost($weekNum);
        $lunchCost = $this->playPassManager->getPlayPassLunchCost($weekNum);
        $extCareCost = $this->playPassManager->getPlayPassExtCareCost($weekNum);


        if (isset($entry['transportation']) && isset($entry['transportation']['name'])) {
            $transportationWindowName = $entry['transportation']['name'];
        } else if (isset($entry['transportation_window'])) {
            $transportationWindowName = $entry['transportation_window'];
        }

        // Add Play Pass main option
        $playPassOptionId = $this->getSessionOptionId(150233, $sessionId);
        if (!empty($playPassOptionId)) {
            $options[] = [
                'IdSessionOption' => (int)$playPassOptionId,
                'Name' => 'Play Pass',
                'Amount' => $this->getOptionCost($playPassOptionId),
                'Quantity' => 1
            ];
        }

        // Add transportation options - Lake Stevens Direct Drop-Off is required for Play Pass
        // First add the base Lake Stevens option
        $lakeStevensOptionId = $this->getSessionOptionId(107198, $sessionId);
        if (!empty($lakeStevensOptionId)) {
            $options[] = [
                'IdSessionOption' => (int)$lakeStevensOptionId,
                'Name' => 'Lake Stevens - Direct Drop-Off',
                'Amount' => $this->getOptionCost($lakeStevensOptionId),
                'Quantity' => 1
            ];
        }

        $transportationWindowName =
            (isset($entry['transportation']) && isset($entry['transportation']['name']))
            ? $entry['transportation']['name']
            : ($entry['transportation_window'] ?? 'Window B');

        // Determine window template ID based on window name
        $windowTemplateId = ($transportationWindowName === 'Window A') ? 122575 : 122577;
        $zoneTemplateId = ($transportationWindowName === 'Window A') ? 122578 : 122581;
        $zoneName = ($transportationWindowName === 'Window A') ? 'Zone 1' : 'Zone A';


        // Add window option
        $windowOptionId = $this->getSessionOptionId($windowTemplateId, $sessionId);
        if (!empty($windowOptionId)) {
            $options[] = [
                'IdSessionOption' => (int)$windowOptionId,
                'Name' => $transportationWindowName,
                'Amount' => $this->getOptionCost($windowOptionId),
                'Quantity' => 1
            ];
        }

        // Zones fill up, and I don't have the capacity management in place to deal with it
        // Until that's corrected, we're going to skip zones in the registration and fix it on the backend
        /*
        // Add appropriate zone option based on window selection
        $zoneOptionId = $this->getSessionOptionId($zoneTemplateId, $sessionId);
        if(!empty($zoneOptionId)) {
            $options[] = [
                'IdSessionOption' => (int)$zoneOptionId,
                'Name' => $zoneName,
                'Amount' => $this->getOptionCost($zoneOptionId),
                'Quantity' => 1
            ];
        }

        */
        // Add day-specific options
        $dayMap = [
            1 => ['name' => 'Monday', 'templateid' => 150234],
            2 => ['name' => 'Tuesday', 'templateid' => 150235],
            3 => ['name' => 'Wednesday', 'templateid' => 150236],
            4 => ['name' => 'Thursday', 'templateid' => 150237],
            5 => ['name' => 'Friday', 'templateid' => 150238]
        ];

        // Flag to track if we've already added a first day (for deposit adjustment)
        $firstDayAdded = false;

        if (isset($entry['playPassDays']) && is_array($entry['playPassDays'])) {
            foreach ($entry['playPassDays'] as $dayNum) {
                if (isset($dayMap[$dayNum])) {
                    $dayOptionId = $this->getSessionOptionId($dayMap[$dayNum]['templateid'], $sessionId);
                    if (!empty($dayOptionId)) {
                        // Use day cost from PlayPassManager
                        $currentDayCost = $dayCost;

                        // If this is the first day, reduce the cost by the deposit amount
                        if (!$firstDayAdded) {
                            // because Ultracamp doesn't use the cost, we'll hardcode here an use a discount
                            // when UC fixes that, we'll return to useing the dynamic cost
                            // $currentDayCost -= $this->tables->sessionDepositAmount;
                            $currentDayCost = $dayCost;
                            $firstDayAdded = true;
                        }

                        $options[] = [
                            'IdSessionOption' => (int)$dayOptionId,
                            'Name' => $dayMap[$dayNum]['name'],
                            'Amount' => $currentDayCost,
                            'Quantity' => 1
                        ];
                    }
                }
            }
        }

        PluginLogger::log("Day cost for day {$dayNum}: {$currentDayCost}");


        // Add lunch options if selected
        if (!empty($entry['lunch']) && is_array($entry['lunch'])) {
            foreach ($entry['lunch'] as $dayNum) {
                if (isset($dayMap[$dayNum])) {
                    $lunchTemplateId = $this->getLunchTemplateId($dayNum);
                    $lunchOptionId = $this->getSessionOptionId($lunchTemplateId, $sessionId);
                    if (!empty($lunchOptionId)) {
                        $options[] = [
                            'IdSessionOption' => (int)$lunchOptionId,
                            'Name' => $dayMap[$dayNum]['name'] . ' Lunch',
                            'Amount' => $lunchCost, // Use lunch cost from PlayPassManager
                            'Quantity' => 1
                        ];
                    }
                }
            }
        }

        // Add extended care options
        if (!empty($entry['extendedCare'])) {
            PluginLogger::log("UltracampCart Adding Play Pass Extended Care", $entry['extendedCare']);

            if (!empty($entry['extendedCare']['morning']) && is_array($entry['extendedCare']['morning'])) {
                foreach ($entry['extendedCare']['morning'] as $dayNum) {
                    $templateId = 0;
                    switch ($dayNum) {
                        case 1:
                            $templateId = 150259;
                            break; // Monday morning
                        case 2:
                            $templateId = 150260;
                            break; // Tuesday morning
                        case 3:
                            $templateId = 150261;
                            break; // Wednesday morning
                        case 4:
                            $templateId = 150263;
                            break; // Thursday morning
                        case 5:
                            $templateId = 150264;
                            break; // Friday morning
                    }

                    if ($templateId) {
                        $careOptionId = $this->getSessionOptionId($templateId, $sessionId);
                        if (!empty($careOptionId)) {
                            $options[] = [
                                'IdSessionOption' => (int)$careOptionId,
                                'Name' => 'Morning Extended Care - ' . $dayMap[$dayNum]['name'],
                                'Amount' => $extCareCost, // Use extended care cost from PlayPassManager
                                'Quantity' => 1
                            ];
                        }
                    }
                }
            }

            if (!empty($entry['extendedCare']['afternoon']) && is_array($entry['extendedCare']['afternoon'])) {
                foreach ($entry['extendedCare']['afternoon'] as $dayNum) {
                    $templateId = 0;
                    switch ($dayNum) {
                        case 1:
                            $templateId = 150265;
                            break; // Monday afternoon
                        case 2:
                            $templateId = 150266;
                            break; // Tuesday afternoon
                        case 3:
                            $templateId = 150267;
                            break; // Wednesday afternoon
                        case 4:
                            $templateId = 150268;
                            break; // Thursday afternoon
                        case 5:
                            $templateId = 150269;
                            break; // Friday afternoon
                    }

                    if ($templateId) {
                        $careOptionId = $this->getSessionOptionId($templateId, $sessionId);
                        if (!empty($careOptionId)) {
                            $options[] = [
                                'IdSessionOption' => (int)$careOptionId,
                                'Name' => 'Afternoon Extended Care - ' . $dayMap[$dayNum]['name'],
                                'Amount' => $extCareCost, // Use extended care cost from PlayPassManager
                                'Quantity' => 1
                            ];
                        }
                    }
                }
            }
        }

        // Add administrative tracking option
        $optionId = $this->getSessionOptionId('129682', $sessionId);
        if (!empty($optionId)) {
            $options[] = [
                'IdSessionOption' => $optionId,
                'Name' => 'Website Registration',
                'Amount' => 0,
                'Quantity' => 1
            ];
        }


        return $options;
    }

    /**
     * Get lunch template ID for a specific day
     * 
     * @param int $dayNum Day number (1-5)
     * @return int Template ID
     */
    function getLunchTemplateId($dayNum)
    {
        $lunchMap = [
            1 => 18994, // Monday Lunch
            2 => 18995, // Tuesday Lunch
            3 => 18996, // Wednesday Lunch
            4 => 18997, // Thursday Lunch
            5 => 18998  // Friday Lunch
        ];

        return $lunchMap[$dayNum] ?? 0;
    }

    function buildPlayPassNRFAdjustmentDiscount($weekNum)
    {
        //While setting the option price in Ultracamp doesn't work, we'll use a discount instead

        $sessionDiscountMap = [
            2 =>  ['sessionid' => '496230', 'discountid' => 1383370],
            3 =>  ['sessionid' => '496231', 'discountid' => 1383413],
            4 =>  ['sessionid' => '496233', 'discountid' => 1383455],
            5 =>  ['sessionid' => '496234', 'discountid' => 1383497],
            6 =>  ['sessionid' => '496235', 'discountid' => 1383539],
            7 =>  ['sessionid' => '496236', 'discountid' => 1383581],
            8 =>  ['sessionid' => '496237', 'discountid' => 1383623],
            9 =>  ['sessionid' => '496238', 'discountid' => 1383667],
            10 => ['sessionid' => '496240', 'discountid' => 1383710],
            11 => ['sessionid' => '496241', 'discountid' => 1383752]
        ];

        PluginLogger::log("Looking up Play Pass Discount Code for Week $weekNum", $sessionDiscountMap[$weekNum]);

        if (empty($sessionDiscountMap[$weekNum]['discountid'])) {
            return [];
        }

        return [
            "IdDiscount" => $sessionDiscountMap[$weekNum]['discountid'],
            "Amount" => 50,
            "Status" => true,
            "Qualifier" => "Custom Discount",
            "Name" => "Play Pass NRF Adjustment"
        ];
    }
}
