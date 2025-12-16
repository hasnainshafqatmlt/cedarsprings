<?php

/**
 * Manages the Play Pass functionality for Cedar Springs Camper Queue
 * Handles daily registrations, capacity checks, and form generation
 */

date_default_timezone_set('America/Los_Angeles');

class PlayPassManager
{
    public $logger;
    protected $db;
    protected $tables;
    protected $CQModel;
    protected $uc;
    protected $production;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once plugin_dir_path(__FILE__) . '../classes/config.php';
        require_once plugin_dir_path(__FILE__) . '../db/db-reservations.php';
        require_once plugin_dir_path(__FILE__) . '../api/ultracamp/CartAndUser.php';

        $this->db = new reservationsDb();
        $this->tables = new CQConfig;
        $this->db->setLogger($this->logger);
        $this->uc = new CartAndUser($this->logger);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }

        if ($this->production) {
            $this->logger->d_bug("PlayPassManager is operating in production!");
        } else {
            $this->logger->d_bug("PlayPassManager is operating in Dev Mode");
        }
    }

    /**
     * Sets the CQModel object for accessing camper information
     */
    function setCQModel($model)
    {
        $this->CQModel = $model;
        return true;
    }

    /**
     * Retrieves available summer weeks for Play Pass registration
     */
    function getAvailableWeeks()
    {
        $sql = "SELECT session_id, start_date, end_date, week_num, short_name 
                FROM " . $this->tables->summer_weeks . " 
                WHERE start_date >= CURDATE() 
                ORDER BY week_num";

        try {
            $weeks = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            $this->logger->error("Unable to load available weeks in PlayPassManager: " . $e->getMessage());
            return [];
        }

        return $weeks;
    }

    /**
     * Generates HTML for camper selection options
     * Automatically checks the radio button when there's only one camper
     */
    function generateCamperOptions($accountId)
    {
        if (empty($accountId)) {
            $this->logger->error("No account ID provided for camper options generation");
            return '<p class="error">Error loading campers. Please try again.</p>';
        }

        // Get campers for account from Ultracamp
        $people = $this->uc->getPeopleByAccount($accountId);

        if (empty($people)) {
            $this->logger->error("No campers found for account $accountId");
            return '<p class="error">No eligible campers found on this account.</p>';
        }

        $html = '';
        $camperCount = 0;
        $eligibleCampers = [];

        // First pass to count eligible campers and store them
        foreach ($people as $person) {
            // Check if person has a birthdate and is of camper age (5-10)
            if (isset($person->BirthDate)) {
                $personAge = $this->CQModel->getAge($person->BirthDate, 12);

                if ($personAge >= 5 && $personAge <= 10) {
                    $camperCount++;
                    $eligibleCampers[] = $person;
                }
            }
        }

        // Second pass to generate the HTML
        foreach ($eligibleCampers as $person) {
            $checkedAttr = ($camperCount === 1) ? ' checked="checked"' : '';

            $html .= '<div class="camper-option" onclick="document.getElementById(\'camper-' . $person->Id . '\').click();">';
            $html .= '<input type="radio" name="selected_camper" id="camper-' . $person->Id .
                '" value="' . $person->Id . '"' . $checkedAttr . '>';
            $html .= '<label for="camper-' . $person->Id . '">' . $person->FirstName . ' ' .
                $person->LastName . '</label>';
            $html .= '</div>';
        }

        if ($camperCount == 0) {
            $html = '<p class="notice">No eligible campers found for Play Pass (ages 5-10). <a href="/camps/queue/addperson/">Add a camper</a></p>';
        }

        return $html;
    }

    /**
     * Gets the day options for a specific week
     */
    function getDayOptions($weekNum, $camperId)
    {
        if (empty($weekNum) || empty($camperId)) {
            return ['error' => 'Missing week or camper information'];
        }

        // Check for regular (non-Play Pass) registration
        $regularRegistration = $this->hasRegularRegistration($camperId, $weekNum);
        if ($regularRegistration) {
            return [
                'error' => 'Cannot add Play Pass days for this week',
                'regular_registration' => true,
                'camp_name' => $regularRegistration['camp_name']
            ];
        }

        // Get week information
        $sql = "SELECT start_date, session_id FROM {$this->tables->summer_weeks} WHERE week_num = ?";
        try {
            $weekInfo = $this->db->runQuery($sql, 'i', $weekNum);
        } catch (Exception $e) {
            $this->logger->error("Unable to get week information: " . $e->getMessage());
            return ['error' => 'Error retrieving week information'];
        }

        if (empty($weekInfo)) {
            return ['error' => 'Week not found'];
        }

        $weekStart = strtotime($weekInfo[0]['start_date']);
        $sessionId = $weekInfo[0]['session_id'];

        // Get Play Pass template ID
        $playPassTemplateId = 150233; // From your camp_options table for Play Pass

        // Check if camper is already registered for any days this week
        $sql = "SELECT rd.day 
                FROM {$this->tables->daily_reservations} rd
                JOIN {$this->tables->reservations} rs ON rd.reservation_id = rs.reservation_id
                WHERE rs.person_ucid = ? AND rs.week_number = ?";

        try {
            $registeredDays = $this->db->runQuery($sql, 'ii', [$camperId, $weekNum]);
        } catch (Exception $e) {
            $this->logger->error("Unable to check registered days: " . $e->getMessage());
            $registeredDays = [];
        }

        $registeredDays = is_array($registeredDays) ? $registeredDays : [];

        // Format registered days into array
        $registeredDayNums = [];
        foreach ($registeredDays as $day) {
            $registeredDayNums[] = $day['day'];
        }

        // Get day options from camp_options table
        $sql = "SELECT co.templateid, co.name 
                FROM {$this->tables->camp_options} co
                WHERE co.category = 'Play Pass Days'
                ORDER BY co.id";

        try {
            $dayOptions = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            $this->logger->error("Unable to get day options: " . $e->getMessage());
            return ['error' => 'Error retrieving day options'];
        }

        // get the reservation details for the transportation window
        $reservationDetails = $this->getPlayPassRegistrationDetails($camperId, $weekNum);

        // Build response
        $options = [
            'week_start' => date('Y-m-d', $weekStart),
            'session_id' => $sessionId,
            'template_id' => $playPassTemplateId,
            'registered_days' => $registeredDayNums,
            'transportation_window' => $reservationDetails['transportation_window'] ?? '',
            'days' => []
        ];

        // Map day names to day numbers
        $dayMap = [
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5
        ];

        foreach ($dayOptions as $option) {
            $dayNum = $dayMap[$option['name']] ?? 0;
            if ($dayNum > 0) {
                // Check availability for this day
                $available = $this->checkDayAvailability($option['templateid'], $sessionId);

                // Add day to options
                $options['days'][] = [
                    'day_num' => $dayNum,
                    'name' => $option['name'],
                    'template_id' => $option['templateid'],
                    'available' => $available,
                    'registered' => in_array($dayNum, $registeredDayNums)
                ];
            }
        }

        $dayCost = $this->getPlayPassDayCost($weekNum);
        $options['day_cost'] = $dayCost;

        $this->logger->debug("Day Pass Options", $options);

        return $options;
    }

    /**
     * Checks availability for a specific day
     */
    function checkDayAvailability($templateId, $sessionId)
    {
        // Get option ID for this day + session combination
        $sql = "SELECT optionid, cq_capacity, uc_capacity 
                FROM camp_option_mapping 
                WHERE templateid = ? AND sessionid = ?";

        try {
            $option = $this->db->runQuery($sql, 'ii', [$templateId, $sessionId]);
        } catch (Exception $e) {
            $this->logger->error("Unable to check day availability: " . $e->getMessage());
            return false;
        }

        if (empty($option)) {
            return false;
        }

        $optionId = $option[0]['optionid'];
        $capacity = $option[0]['cq_capacity'] ?? $option[0]['uc_capacity'] ?? 0;

        if ($capacity <= 0) {
            return false;
        }

        // Count current registrations for this day
        $sql = "SELECT COUNT(*) as count 
                FROM {$this->tables->daily_reservations} rd
                JOIN {$this->tables->reservations} rs ON rd.reservation_id = rs.reservation_id
                WHERE rd.session_template = ? AND rs.session_ucid = ?";

        try {
            $count = $this->db->runQuery($sql, 'ii', [$templateId, $sessionId]);
        } catch (Exception $e) {
            $this->logger->error("Unable to count current registrations: " . $e->getMessage());
            return false;
        }

        $currentCount = $count[0]['count'] ?? 0;

        // Check if space is available
        return ($currentCount < $capacity);
    }

    /**
     * Gets pod options for a specific day
     */
    function getPodOptions($day, $sessionId, $camperId)
    {
        // Get age of camper
        $camper = $this->CQModel->getCamperBio($camperId);
        if (empty($camper) || empty($camper['birth_date'])) {
            return [];
        }

        // Calculate age
        $weekStart = $this->CQModel->getWeekStartFromWeekNumber($sessionId);
        $age = $this->CQModel->calculateAge($camper['birth_date'], $weekStart);

        // Get day template ID
        $dayTemplateMap = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday'
        ];

        $dayName = $dayTemplateMap[$day] ?? '';
        if (empty($dayName)) {
            return [];
        }

        // Get pod options for this day and age range
        $sql = "SELECT co.templateid, co.name 
                FROM {$this->tables->camp_options} co
                WHERE co.parent = ? AND co.category = 'Play Pass Pods'
                ORDER BY co.id";

        try {
            $pods = $this->db->runQuery($sql, 's', $dayName);
        } catch (Exception $e) {
            $this->logger->error("Unable to get pod options: " . $e->getMessage());
            return [];
        }

        // Filter pods by availability and age appropriateness
        $availablePods = [];
        foreach ($pods as $pod) {
            // TODO: Implement age range checking for pods
            // For now, return all pods
            $availablePods[] = [
                'id' => $pod['templateid'],
                'name' => $pod['name']
            ];
        }

        return $availablePods;
    }


    /**
     * Process Play Pass registration
     * 
     * @param array $data Registration data containing camper_id, week, days, lunch, morning_care, afternoon_care, and transportation_window
     * @return array Success status and message
     */
    function processRegistration($data)
    {
        if (empty($data['camper_id']) || empty($data['week']) || empty($data['days'])) {
            return ['success' => false, 'message' => 'Missing required registration information'];
        }

        // Validate transportation window is selected
        if (empty($data['transportation_window'])) {
            return ['success' => false, 'message' => 'Please select a transportation window'];
        }

        $this->logger->debug("Process Registration Data", $data);

        // Get week and session info
        $sql = "SELECT session_id, start_date FROM {$this->tables->summer_weeks} WHERE week_num = ?";
        try {
            $weekInfo = $this->db->runQuery($sql, 'i', $data['week']);
            if (empty($weekInfo)) {
                return ['success' => false, 'message' => 'Invalid week selected'];
            }
            $sessionId = $weekInfo[0]['session_id'];
        } catch (Exception $e) {
            $this->logger->error("Unable to get session ID for week {$data['week']}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error retrieving week information'];
        }

        // Get PlayPass template ID
        $playPassTemplateId = 150233; // Main Play Pass template ID

        // Get camper account ID
        $sql = "SELECT account_ucid FROM {$this->tables->campers} WHERE person_ucid = ?";
        try {
            $accountResult = $this->db->runQuery($sql, 'i', $data['camper_id']);

            if (empty($accountResult)) {
                // probably means that we have a new camper, use the account ID in the cookie
                if (!empty($data['account_id'])) {
                    $accountId = $data['account_id'];
                } else {
                    return ['success' => false, 'message' => 'Camper account not found'];
                }
            } else {
                $accountId = $accountResult[0]['account_ucid'];
            }
        } catch (Exception $e) {
            $this->logger->error("Unable to get account ID for camper {$data['camper_id']}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error retrieving camper information'];
        }

        // Set up cart entry for UltracampCart
        $cartEntry = "P-{$data['camper_id']}-{$playPassTemplateId}-{$data['week']}";

        // Get transportation template ID based on window
        $transportationTemplateId = ($data['transportation_window'] === 'Window A') ? 122575 : 122577;

        // Store the selected days and options in the session for processing
        $_SESSION['playPassData'][$cartEntry] = [
            'camper_id' => $data['camper_id'],
            'week' => $data['week'],
            'session_id' => $sessionId,
            'days' => $data['days'],
            'lunch' => $data['lunch'] ?? [],
            'morning_care' => $data['morning_care'] ?? [],
            'afternoon_care' => $data['afternoon_care'] ?? [],
            'transportation_window' => $data['transportation_window'],
            'transportation_template_id' => $transportationTemplateId,
            'account_id' => $accountId
        ];

        $this->logger->debug("Play Pass data stored in session", $_SESSION['playPassData'][$cartEntry]);

        return [
            'success' => true,
            'message' => 'Registration added to cart successfully',
            'cart_entry' => $cartEntry
        ];
    }

    /**
     * Builds cart options for UltracampCart from Play Pass data
     * 
     * @param string $cartEntry The cart entry key
     * @param array $data Play Pass registration data
     * @return array Cart options for UltracampCart
     */
    function buildCartOptions($cartEntry, $data)
    {
        if (empty($data)) {
            $this->logger->error("No Play Pass data provided for cart entry: $cartEntry");
            return false;
        }

        $this->logger->debug("buildCartOptions Data", $data);

        // Get camper info
        $camper = $this->CQModel->getCamperName($data['camper_id']);
        if (empty($camper)) {
            $this->logger->error("Unable to get camper information for ID: {$data['camper_id']}");
            return false;
        }

        // Get session ID if not provided
        $sessionId = isset($data['session_id']) ? $data['session_id'] : null;
        if (!$sessionId) {
            $sessionId = $this->CQModel->getSessionIdFromWeekNumber($data['week']);
            $this->logger->debug("Looking up session ID for week {$data['week']}: $sessionId");

            if (!$sessionId) {
                $this->logger->error("Unable to determine session ID for week {$data['week']}");
                return false;
            }
        }

        // Determine proper transportation details
        $windowName = isset($data['transportation_window']) ? $data['transportation_window'] : 'Window B';
        $windowTemplateId = ($windowName === 'Window A') ? 122575 : 122577;

        // Set template IDs based on window selection
        if ($windowName === 'Window A') {
            $windowTemplateId = 122575;
            $zoneTemplateId = 122578; // Zone 1 for Window A
            $zoneName = 'Zone 1';
        } else {
            $windowTemplateId = 122577; // Window B
            $zoneTemplateId = 122581; // Zone A for Window B
            $zoneName = 'Zone A';
        }

        // Format the cart entry with all necessary properties
        $cartOptions[$cartEntry] = [
            'camper' => [
                'personid' => $data['camper_id'],
                'firstName' => $camper['FirstName'],
                'lastName' => $camper['LastName']
            ],
            'week' => [
                'weekNum' => $data['week'],
                'sessionid' => $sessionId
            ],
            'camp' => [
                'name' => 'Play Pass',
                'templateid' => 150233 // Play Pass template ID
            ],
            'playPassDays' => $data['days'],
            'lunch' => !empty($data['lunch']) ? $data['lunch'] : [],
            'extendedCare' => [
                'morning' => !empty($data['morning_care']) ? $data['morning_care'] : [],
                'afternoon' => !empty($data['afternoon_care']) ? $data['afternoon_care'] : []
            ],
            // Include transportation directly in the base data structure
            'transportation' => [
                'name' => $windowName,
                'templateid' => $windowTemplateId
            ],
            // Include bus pod directly in base structure
            'busPod' => [
                'name' => $zoneName,
                'templateid' => $zoneTemplateId
            ],
            // Still keep these for backward compatibility
            'transportation_window' => $windowName,
            'transportation_template_id' => $windowTemplateId
        ];

        $this->logger->debug("Cart options built with window: $windowName, zone: $zoneName", [
            'cart_structure' => json_encode($cartOptions[$cartEntry])
        ]);

        return $cartOptions;
    }

    /**
     * Get day name from day number
     * 
     * @param int $dayNum Day number (1-5)
     * @return string|false Day name or false if invalid
     */
    private function getDayName($dayNum)
    {
        $dayMap = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday'
        ];

        return isset($dayMap[$dayNum]) ? $dayMap[$dayNum] : false;
    }

    /**
     * Get template ID for morning extended care
     * 
     * @param string $dayName Day name
     * @return int Template ID
     */
    private function getMorningCareTemplateId($dayName)
    {
        $templateMap = [
            'Monday' => 150259,
            'Tuesday' => 150260,
            'Wednesday' => 150261,
            'Thursday' => 150263,
            'Friday' => 150264
        ];

        return $templateMap[$dayName] ?? 0;
    }

    /**
     * Get template ID for afternoon extended care
     * 
     * @param string $dayName Day name
     * @return int Template ID
     */
    private function getAfternoonCareTemplateId($dayName)
    {
        $templateMap = [
            'Monday' => 150265,
            'Tuesday' => 150266,
            'Wednesday' => 150267,
            'Thursday' => 150268,
            'Friday' => 150269
        ];

        return $templateMap[$dayName] ?? 0;
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out
    function setLogger($logger = null)
    {
        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new PlayPassDummyLogger();
    }

    /**
     * Store daily reservations after Ultracamp processing
     * 
     * @param int $reservationId Main reservation ID
     * @param array $playPassData Play Pass registration data
     * @return bool Success status
     */
    function storeDailyReservations($reservationId, $playPassData)
    {
        if (empty($reservationId) || empty($playPassData)) {
            $this->logger->error("Missing reservation ID or Play Pass data for daily reservations");
            return false;
        }

        // Insert daily entries
        $success = true;

        // Process days
        foreach ($playPassData['days'] as $dayNum) {
            $dayTemplateId = $this->getDayTemplateId($dayNum);
            if (!$dayTemplateId) {
                $this->logger->warning("No template ID found for day $dayNum");
                continue;
            }

            // Insert daily reservation record
            $sql = "INSERT INTO {$this->tables->daily_reservations} 
                    (reservation_id, type, day, session_template) 
                    VALUES (?, 'day', ?, ?)";

            try {
                $this->db->insert($sql, 'iii', [$reservationId, $dayNum, $dayTemplateId]);
            } catch (Exception $e) {
                $this->logger->error("Unable to store daily reservation for day $dayNum: " . $e->getMessage());
                $success = false;
            }
        }

        // Process lunch options
        if (!empty($playPassData['lunch'])) {
            foreach ($playPassData['lunch'] as $dayNum) {
                $sql = "INSERT INTO {$this->tables->daily_reservations} 
                        (reservation_id, type, day, session_template) 
                        VALUES (?, 'lunch', ?, ?)";

                try {
                    $lunchTemplateId = $this->getLunchTemplateId($dayNum);
                    $this->db->insert($sql, 'iii', [$reservationId, $dayNum, $lunchTemplateId]);
                } catch (Exception $e) {
                    $this->logger->error("Unable to store lunch option for day $dayNum: " . $e->getMessage());
                    $success = false;
                }
            }
        }

        // Process extended care options
        if (!empty($playPassData['morning_care'])) {
            foreach ($playPassData['morning_care'] as $dayNum) {
                $sql = "INSERT INTO {$this->tables->daily_reservations} 
                        (reservation_id, type, day, session_template) 
                        VALUES (?, 'morning_care', ?, ?)";

                try {
                    $careTemplateId = $this->getMorningCareTemplateId($this->getDayName($dayNum));
                    $this->db->insert($sql, 'iii', [$reservationId, $dayNum, $careTemplateId]);
                } catch (Exception $e) {
                    $this->logger->error("Unable to store morning care for day $dayNum: " . $e->getMessage());
                    $success = false;
                }
            }
        }

        if (!empty($playPassData['afternoon_care'])) {
            foreach ($playPassData['afternoon_care'] as $dayNum) {
                $sql = "INSERT INTO {$this->tables->daily_reservations} 
                        (reservation_id, type, day, session_template) 
                        VALUES (?, 'afternoon_care', ?, ?)";

                try {
                    $careTemplateId = $this->getAfternoonCareTemplateId($this->getDayName($dayNum));
                    $this->db->insert($sql, 'iii', [$reservationId, $dayNum, $careTemplateId]);
                } catch (Exception $e) {
                    $this->logger->error("Unable to store afternoon care for day $dayNum: " . $e->getMessage());
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Get the cost of a Play Pass day for a specific session
     * 
     * @param int $weekNum Week number
     * @return float|int Cost of a day for the Play Pass
     */
    function getPlayPassDayCost($weekNum)
    {
        /**
         * Because the options use a pricing structure, their daily rates in the database are not valid
         */

        try {
            // Try to use the config value
            if (isset($this->tables->playPassDailyCost) && $this->tables->playPassDailyCost > 0) {
                $configCost = $this->tables->playPassDailyCost;
                $this->logger->debug("Using config cost for Play Pass: $configCost");
                return $configCost;
            }

            // If we get here, the config value is missing or invalid
            $this->logger->error("Failed to retrieve Play Pass day cost for week $weekNum. " .
                "Config value: " . (isset($this->tables->playPassDailyCost) ? $this->tables->playPassDailyCost : "not set"));

            // Return fallback value
            return 68;
        } catch (Exception $e) {
            $this->logger->error("Exception in getPlayPassDayCost for week $weekNum: " . $e->getMessage());
            return 68;
        }
    }

    /**
     * Get the cost of a Play Pass day of Extended Care
     * 
     * @param int $weekNum Week number
     * @return float|int Cost of a day for the Play Pass
     */
    function getPlayPassExtCareCost($weekNum)
    {
        return $this->getPlayPassDailyCost($weekNum, 150259) ?? 0;
    }

    /**
     * Get the cost of a Play Pass day of Lunch
     * 
     * @param int $weekNum Week number
     * @return float|int Cost of a day for the Play Pass
     */
    function getPlayPassLunchCost($weekNum)
    {
        return $this->getPlayPassDailyCost($weekNum, 18994) ?? 0;
    }



    /**
     * Get the cost of specific Play Pass Daily elements
     * 
     * @param int $weekNum Week number
     * @param int $mondayTemplateId Option template Id for the monday element
     * @return float|int Cost of a day for the Play Pass
     */
    function getPlayPassDailyCost($weekNum, $mondayTemplateId)
    {

        // Get session ID for the week
        $sessionId = $this->CQModel->getSessionIdFromWeekNumber($weekNum);
        if (!$sessionId) {
            $this->logger->error("Unable to determine session ID for week {$weekNum}");
            return 0;
        }

        // Get the option ID for this day + session combination
        $sql = "SELECT optionid, cost FROM camp_option_mapping WHERE templateid = ? AND sessionid = ?";

        try {
            $option = $this->db->runQuery($sql, 'ii', [$mondayTemplateId, $sessionId]);
        } catch (Exception $e) {
            $this->logger->error("Unable to get day cost: " . $e->getMessage());
            return 0;
        }

        $this->logger->debug("getPlayPassDayCost($weekNum)", $option ?: []);

        if (empty($option)) {
            $this->logger->debug("getPlayPassDailyCost returned no cost value for", compact('weekNum', 'mondayTemplateId'));
            return 0;
        }

        return $option[0]['cost'] ?? 0;
    }

    /**
     * Get lunch template ID for a specific day
     * 
     * @param int $dayNum Day number (1-5)
     * @return int Template ID
     */
    private function getLunchTemplateId($dayNum)
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



    /**
     * Get template ID for a specific day
     * 
     * @param int $dayNum Day number (1-5)
     * @return int Template ID
     */
    private function getDayTemplateId($dayNum)
    {
        $templateMap = [
            1 => 150234, // Monday
            2 => 150235, // Tuesday
            3 => 150236, // Wednesday
            4 => 150237, // Thursday
            5 => 150238  // Friday
        ];

        return $templateMap[$dayNum] ?? 0;
    }

    /**
     * Get registered days for a camper in a specific week
     * 
     * @param int $camperId Camper ID
     * @param int $weekNum Week number
     * @return array Array of registered day numbers
     */
    function getRegisteredDays($camperId, $weekNum)
    {
        // Get week and session info
        $sql = "SELECT session_id FROM {$this->tables->summer_weeks} WHERE week_num = ?";
        try {
            $weekInfo = $this->db->runQuery($sql, 'i', $weekNum);
            if (empty($weekInfo)) {
                $this->logger->error("Invalid week selected");
                return [];
            }
            $sessionId = $weekInfo[0]['session_id'];
        } catch (Exception $e) {
            $this->logger->error("Unable to get session ID for week {$weekNum}: " . $e->getMessage());
            return [];
        }

        // Get reservation for this camper and week
        $sql = "SELECT r.reservation_id, r.reservation_ucid 
                FROM {$this->tables->reservations} r
                WHERE r.person_ucid = ? AND r.week_number = ?";

        try {
            $reservations = $this->db->runQuery($sql, 'ii', [$camperId, $weekNum]);
            if (empty($reservations)) {
                $this->logger->debug("No reservations found for camper {$camperId} in week {$weekNum}");
                return [];
            }
        } catch (Exception $e) {
            $this->logger->error("Error retrieving reservations: " . $e->getMessage());
            return [];
        }

        // Get daily registrations for this reservation
        $reservationId = $reservations[0]['reservation_id'];

        // Update type='day' to type='camp'
        $sql = "SELECT day FROM {$this->tables->daily_reservations} 
                WHERE reservation_id = ? AND type = 'camp'";

        try {
            $results = $this->db->runQuery($sql, 'i', $reservationId);
            if (empty($results)) {
                $this->logger->debug("No daily reservations found for reservation {$reservationId}");
                return [];
            }
        } catch (Exception $e) {
            $this->logger->error("Error retrieving daily reservations: " . $e->getMessage());
            return [];
        }

        // Extract day numbers
        $days = [];
        foreach ($results as $row) {
            $days[] = (int)$row['day'];
        }

        $this->logger->debug("Registered days for camper {$camperId} in week {$weekNum}: " . implode(', ', $days));
        return $days;
    }

    /**
     * Get weeks with existing registrations for a camper
     * 
     * @param int $camperId Camper ID
     * @return array Array of week numbers with registrations
     */
    function getRegisteredWeeks($camperId)
    {
        if (!$camperId) {
            $this->logger->error("Invalid camper ID for getRegisteredWeeks");
            return [];
        }

        // Get weeks with registrations for this camper
        $sql = "SELECT DISTINCT week_number 
                FROM {$this->tables->reservations} 
                WHERE person_ucid = ?";

        try {
            $results = $this->db->runQuery($sql, 'i', $camperId);

            if (empty($results)) {
                $this->logger->debug("No registered weeks found for camper $camperId");
                return [];
            }

            // Extract week numbers
            $weeks = [];
            foreach ($results as $row) {
                $weeks[] = (int)$row['week_number'];
            }

            $this->logger->debug("Registered weeks for camper $camperId: " . implode(', ', $weeks));
            return $weeks;
        } catch (Exception $e) {
            $this->logger->error("Error retrieving registered weeks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Play Pass registration details
     * 
     * @param int $camperId Camper ID
     * @param int $weekNum Week number
     * @return array Registration details including days, lunch, morning care, afternoon care, and transportation
     */
    function getPlayPassRegistrationDetails($camperId, $weekNum)
    {
        // Get week and session info
        $sql = "SELECT session_id FROM {$this->tables->summer_weeks} WHERE week_num = ?";
        try {
            $weekInfo = $this->db->runQuery($sql, 'i', $weekNum);
            if (empty($weekInfo)) {
                $this->logger->error("Invalid week selected");
                return [];
            }
            $sessionId = $weekInfo[0]['session_id'];
        } catch (Exception $e) {
            $this->logger->error("Unable to get session ID for week {$weekNum}: " . $e->getMessage());
            return [];
        }

        // Get reservation for this camper and week
        $sql = "SELECT r.reservation_id, r.reservation_ucid, r.camp_name, 
                    r.transportation, r.transportation_option, r.pickup_window 
                FROM {$this->tables->reservations} r
                WHERE r.person_ucid = ? AND r.week_number = ?";

        try {
            $reservations = $this->db->runQuery($sql, 'ii', [$camperId, $weekNum]);
            if (empty($reservations)) {
                $this->logger->error("No reservations found for camper {$camperId} in week {$weekNum}");
                return [];
            }
        } catch (Exception $e) {
            $this->logger->error("Error retrieving reservations: " . $e->getMessage());
            return [];
        }

        // Get daily registrations for this reservation
        $reservationId = $reservations[0]['reservation_id'];
        $reservationUcid = $reservations[0]['reservation_ucid'];
        $campName = $reservations[0]['camp_name'];
        $transportationWindow = $reservations[0]['pickup_window']; // Window A or Window B

        // Initialize result array
        $result = [
            'camper_id' => $camperId,
            'week' => $weekNum,
            'session_id' => $sessionId,
            'reservation_id' => $reservationId,
            'reservation_ucid' => $reservationUcid,
            'camp_name' => $campName,
            'transportation_window' => $transportationWindow,
            'days' => [],
            'lunch' => [],
            'morning_care' => [],
            'afternoon_care' => []
        ];

        // If this is not a Play Pass registration, just return basic details
        if ($campName !== 'Play Pass') {
            $this->logger->debug("Not a Play Pass registration: {$campName}");
            return $result;
        }

        // Get all daily options for this reservation
        $sql = "SELECT type, day, session_template FROM {$this->tables->daily_reservations} 
                WHERE reservation_id = ?";

        try {
            $options = $this->db->runQuery($sql, 'i', $reservationId);
            if (empty($options)) {
                $this->logger->debug("No daily options found for reservation {$reservationId}");
                return $result; // Return with empty arrays for options
            }

            $this->logger->debug("Retrieved daily options:", $options);

            // Define template IDs for extended care
            $morningExtendedCareIds = [150259, 150260, 150261, 150263, 150264];
            $afternoonExtendedCareIds = [150265, 150266, 150267, 150268, 150269];

            // Sort options into appropriate categories
            foreach ($options as $option) {
                $day = (int)$option['day'];
                $type = $option['type'];
                $templateId = isset($option['session_template']) ? (int)$option['session_template'] : 0;

                switch ($type) {
                    case 'camp':
                        $result['days'][] = $day;
                        break;
                    case 'lunch':
                        $result['lunch'][] = $day;
                        break;
                    case 'ExtendedCare':
                        // Check template ID to determine if it's morning or afternoon
                        if (in_array($templateId, $morningExtendedCareIds)) {
                            $result['morning_care'][] = $day;
                        } elseif (in_array($templateId, $afternoonExtendedCareIds)) {
                            $result['afternoon_care'][] = $day;
                        } else {
                            $this->logger->debug("Unknown extended care template ID: {$templateId}");
                        }
                        break;
                    case 'morning_care':
                        $result['morning_care'][] = $day;
                        break;
                    case 'afternoon_care':
                        $result['afternoon_care'][] = $day;
                        break;
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Error retrieving daily options: " . $e->getMessage());
            return $result; // Return with empty arrays for options
        }

        return $result;
    }

    /**
     * Checks if a camper has a regular (non-Play Pass) registration for a specific week
     * 
     * @param int $camperId Camper ID
     * @param int $weekNum Week number
     * @return array|false Regular camp details or false if no regular registration
     */
    function hasRegularRegistration($camperId, $weekNum)
    {
        $sql = "SELECT r.reservation_id, r.reservation_ucid, r.camp_name 
                FROM {$this->tables->reservations} r
                WHERE r.person_ucid = ? AND r.week_number = ? AND r.camp_name != 'Play Pass'";

        try {
            $registrations = $this->db->runQuery($sql, 'ii', [$camperId, $weekNum]);
            if (empty($registrations)) {
                return false;
            }

            return $registrations[0];
        } catch (Exception $e) {
            $this->logger->error("Error checking for regular registration: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a camper has a Play Pass registration for a specific week
     * 
     * @param int $camperId Camper ID
     * @param int $weekNum Week number
     * @return bool True if camper has a Play Pass registration for the week
     */
    function hasPlayPassRegistration($camperId, $weekNum)
    {
        $sql = "SELECT r.reservation_id, r.reservation_ucid 
                FROM {$this->tables->reservations} r
                WHERE r.person_ucid = ? AND r.week_number = ? AND r.camp_name = 'Play Pass'";

        try {
            $registrations = $this->db->runQuery($sql, 'ii', [$camperId, $weekNum]);
            if (empty($registrations)) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error("Error checking for Play Pass registration: " . $e->getMessage());
            return false;
        }
    }
}

class PlayPassDummyLogger
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
