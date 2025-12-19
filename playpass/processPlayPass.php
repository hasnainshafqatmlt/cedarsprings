<?php
// processPlayPass.php - Processes Play Pass registrations

session_start();

// Use an .env to ensure that we know what environment we are in.
$isDevEnvironment = false;
// $envPath = __DIR__ . '/../../../../.env';
// if (file_exists($envPath)) {
//     $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//     foreach ($envLines as $line) {
//         // Skip comments and empty lines
//         if (empty($line) || strpos(trim($line), '#') === 0) {
//             continue;
//         }

//         // Look for APP_ENV setting
//         if (preg_match('/^APP_ENV\s*=\s*(\w+)/', $line, $matches)) {
//             $isDevEnvironment = (trim($matches[1]) === 'development');
//             break;
//         }
//     }
// }


require_once(plugin_dir_path(__FILE__) . '../counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/process', $_SERVER['REMOTE_ADDR']);

// get the logger
require_once './logger/plannerLogger.php';

// disable debugging if we're not in dev
// if ($isDevEnvironment) {
//     $logger->pushHandler($dbugStream);
// }

// Check login status
require_once '../classes/ValidateLogin.php';
$validator = new ValidateLogin($logger);

// Required classes
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';
require_once __DIR__ . '/../includes/ultracamp.php';
require_once __DIR__ . '/../classes/UltracampCart.php';

// Confirm that the user is still logged in
if (empty($_COOKIE['key']) || empty($_COOKIE['account']) || !$validator->validate($_COOKIE['key'], $_COOKIE['account'])) {
    // Redirect back to login
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    echo '<script>window.location.href = "/camps/queue";</script>';
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
    echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    exit;
}

// Check for duplicate entry (same camper and week) if not in edit mode
if (!$editMode && isset($_SESSION['playPassSelections']) && !empty($_SESSION['playPassSelections'])) {
    foreach ($_SESSION['playPassSelections'] as $index => $selection) {
        if ($selection['data']['camper_id'] == $camperId && $selection['data']['week'] == $weekNum) {
            // Duplicate found - set error message and redirect
            PluginLogger::log("Duplicate Play Pass registration attempted for camper $camperId in week $weekNum");
            $_SESSION['playPassError'] = 'This camper is already registered for the selected week. Please edit the existing selection instead.';
            echo '<script>window.location.href = "/camps/queue/playpass";</script>';
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
    'account_id' => $_COOKIE['account'],
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

    // Redirect back to play pass page
    echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    exit;
} else {
    // Error - redirect back to form with error message
    $_SESSION['playPassError'] = $result['message'];
    echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    exit;
}
