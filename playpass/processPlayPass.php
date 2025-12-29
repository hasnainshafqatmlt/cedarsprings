<?php
// processPlayPass.php - Processes Play Pass registrations

session_start();

// Use an .env to ensure that we know what environment we are in.
$isDevEnvironment = false;


require_once(plugin_dir_path(__FILE__) . '../counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/process', $_SERVER['REMOTE_ADDR']);

// get the logger
// require_once './logger/plannerLogger.php';

// disable debugging if we're not in dev
// if ($isDevEnvironment) {
//     $logger->pushHandler($dbugStream);
// }

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';
$validator = new ValidateLogin($logger);

// Required classes
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';
require_once __DIR__ . '/../includes/ultracamp.php';
require_once __DIR__ . '/../classes/UltracampCart.php';

// SECURE APPROACH: Use session-based authentication
$authKey = null;
$authAccount = null;

// Try session first (most secure)
if (!empty($_SESSION['ultracamp_auth_key']) && !empty($_SESSION['ultracamp_auth_account'])) {
    $authKey = $_SESSION['ultracamp_auth_key'];
    $authAccount = $_SESSION['ultracamp_auth_account'];

    if (!$validator->validate($authKey, $authAccount)) {
        unset($_SESSION['ultracamp_auth_key']);
        unset($_SESSION['ultracamp_auth_account']);
        $authKey = null;
        $authAccount = null;
    }
}

// Fallback to cookies
if (empty($authKey) || empty($authAccount)) {
    if (!empty($_COOKIE['key']) && !empty($_COOKIE['account'])) {
        $authKey = $_COOKIE['key'];
        $authAccount = $_COOKIE['account'];

        if ($validator->validate($authKey, $authAccount)) {
            $_SESSION['ultracamp_auth_key'] = $authKey;
            $_SESSION['ultracamp_auth_account'] = $authAccount;
        }
    }
}

// Last resort: POST body (for path-restricted cookies)
if (empty($authKey) || empty($authAccount)) {
    $postKey = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
    $postAccount = filter_input(INPUT_POST, 'account', FILTER_SANITIZE_STRING);

    if (!empty($postKey) && !empty($postAccount) && $validator->validate($postKey, $postAccount)) {
        $authKey = $postKey;
        $authAccount = $postAccount;
        $_SESSION['ultracamp_auth_key'] = $authKey;
        $_SESSION['ultracamp_auth_account'] = $authAccount;
    }
}

// Confirm that the user is still logged in
if (empty($authKey) || empty($authAccount) || !$validator->validate($authKey, $authAccount)) {
    // Redirect back to login
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    echo json_encode(['success' => true, 'error' => 'Authentication required', 'redirect' => '/camps/queue']);
    // echo '<script>window.location.href = "/camps/queue";</script>';
    exit;
}

// Initialize classes
$uc = new UltracampModel($logger);
$cart = new UltracampCart($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Validate form submission
$camperId = filter_input(INPUT_POST, 'camper_id', FILTER_VALIDATE_INT);
$weekNum = filter_input(INPUT_POST, 'week_num', FILTER_VALIDATE_INT);
$selectedDays = isset($_POST['selected_days']) ? $_POST['selected_days'] : [];
$editMode = filter_input(INPUT_POST, 'edit_mode', FILTER_VALIDATE_INT) === 1;
$editIndex = filter_input(INPUT_POST, 'edit_index', FILTER_VALIDATE_INT);
$transportationWindow = filter_input(INPUT_POST, 'transportation_window', FILTER_SANITIZE_STRING);
$friendsList = filter_input(INPUT_POST, 'friends', FILTER_SANITIZE_STRING);

if (!$camperId || !$weekNum || empty($selectedDays) || empty($transportationWindow)) {
    PluginLogger::log("Invalid Play Pass registration data");
    $_SESSION['playPassError'] = 'Invalid registration data. Please try again.';

    // Check if this is a WordPress AJAX request
    $isAjaxRequest = defined('DOING_AJAX') && DOING_AJAX;
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Invalid registration data. Please try again.',
            'redirect' => '/camps/queue/playpass'
        ]);
    } else {
        echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    }
    exit;
}

// Check for duplicate entry (same camper and week) if not in edit mode
if (!$editMode && isset($_SESSION['playPassSelections']) && !empty($_SESSION['playPassSelections'])) {
    foreach ($_SESSION['playPassSelections'] as $index => $selection) {
        if ($selection['data']['camper_id'] == $camperId && $selection['data']['week'] == $weekNum) {
            // Duplicate found - set error message and redirect
            PluginLogger::log("Duplicate Play Pass registration attempted for camper $camperId in week $weekNum");
            $_SESSION['playPassError'] = 'This camper is already registered for the selected week. Please edit the existing selection instead.';

            // Check if this is a WordPress AJAX request
            $isAjaxRequest = defined('DOING_AJAX') && DOING_AJAX;
            if ($isAjaxRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'This camper is already registered for the selected week. Please edit the existing selection instead.',
                    'redirect' => '/camps/queue/playpass'
                ]);
            } else {
                echo '<script>window.location.href = "/camps/queue/playpass";</script>';
            }
            exit;
        }
    }
}

// Add debug logging for the transportation window selection
PluginLogger::log("Form submission data:", [
    'raw_post' => $_POST,
    'transport_window' => $_POST['transportation_window'] ?? 'Not set',
    'camper_id' => $camperId,
    'week_num' => $weekNum
]);

// Make sure to pass the exact value from the form
$registrationData = [
    'camper_id' => $camperId,
    'account_id' => $authAccount,
    'week' => $weekNum,
    'days' => [],
    'lunch' => [],
    'morning_care' => [],
    'afternoon_care' => [],
    'transportation_window' => $transportationWindow,
    'friends' => $friendsList
];

// Process selected days
foreach ($selectedDays as $day) {
    $dayNum = filter_var($day, FILTER_VALIDATE_INT);
    if ($dayNum) {
        $registrationData['days'][] = $dayNum;

        // Check for lunch option
        if (isset($_POST["lunch_day_$dayNum"]) && $_POST["lunch_day_$dayNum"] == $dayNum) {
            $registrationData['lunch'][] = $dayNum;
        }

        // Check for morning extended care
        if (isset($_POST["morning_care_day_$dayNum"]) && $_POST["morning_care_day_$dayNum"] == $dayNum) {
            $registrationData['morning_care'][] = $dayNum;
        }

        // Check for afternoon extended care
        if (isset($_POST["afternoon_care_day_$dayNum"]) && $_POST["afternoon_care_day_$dayNum"] == $dayNum) {
            $registrationData['afternoon_care'][] = $dayNum;
        }
    }
}

// Get transportation template ID based on window selection
$transportationTemplateId = ($transportationWindow === 'Window A') ? 122575 : 122577;
$registrationData['transportation_template_id'] = $transportationTemplateId;

// Log registration data
PluginLogger::log("Play Pass registration data", $registrationData);

// Process registration
$result = $playPassManager->processRegistration($registrationData);

// Check if this is a WordPress AJAX request
$isAjaxRequest = defined('DOING_AJAX') && DOING_AJAX;

if ($result['success']) {
    // Add cart entry to session
    if (!isset($_SESSION['playPassSelections'])) {
        $_SESSION['playPassSelections'] = [];
    }

    $_SESSION['playPassSelections'][] = [
        'entry' => $result['cart_entry'],
        'data' => $registrationData
    ];

    // Set success message
    $_SESSION['playPassMessage'] = 'Play Pass added to cart! You can add more or proceed to checkout.';

    // Handle response based on request type
    if ($isAjaxRequest) {
        // Return JSON for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $_SESSION['playPassMessage'],
            'redirect' => '/camps/queue/playpass'
        ]);
    } else {
        // Redirect for normal form submissions
        echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    }
    exit;
} else {
    // Error - redirect back to form with error message
    $_SESSION['playPassError'] = $result['message'];

    // Handle response based on request type
    if ($isAjaxRequest) {
        // Return JSON for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'redirect' => '/camps/queue/playpass'
        ]);
    } else {
        // Redirect for normal form submissions
        echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    }
    exit;
}
