<?php
// editPlayPassSelection.php - Processes edits to Play Pass registrations

session_start();

// Use an .env to ensure that we know what environment we are in.
$isDevEnvironment = false;

require_once(plugin_dir_path(__FILE__) . '../counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/editPlayPassSelection', $_SERVER['REMOTE_ADDR']);

// get the logger
require_once './logger/plannerLogger.php';

// // disable debugging if we're not in dev
// if($isDevEnvironment) {
//     // create a d-bug logger so that only the objects we're testing create debug noise
//     $dbugLogger = new PlannerLogger('DBug-Queue');
//         // bind it to a logger object
//         $dbugLogger->pushHandler($stream);
//         $dbugLogger->pushHandler($dbugStream);
// } else {
//     $dbugLogger = $logger;
// }

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';
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

    // Check if this is a WordPress AJAX request
    $isAjaxRequest = defined('DOING_AJAX') && DOING_AJAX;
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required',
            'redirect' => '/camps/queue'
        ]);
    } else {

        echo '<script>window.location.href = "/camps/queue";</script>';
    }
    exit;
}

// Initialize classes
$uc = new UltracampModel($dbugLogger);
$cart = new UltracampCart($dbugLogger);
$CQModel = new CQModel($dbugLogger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($dbugLogger);
$playPassManager->setCQModel($CQModel);

PluginLogger::log("debug:: Handling edit request for index: $editIndex with data:", $_POST);

// Validate form submission
$camperId = filter_input(INPUT_POST, 'camper_id', FILTER_VALIDATE_INT);
$weekNum = filter_input(INPUT_POST, 'week_num', FILTER_VALIDATE_INT);
$selectedDays = isset($_POST['selected_days']) ? $_POST['selected_days'] : [];
$editIndex = filter_input(INPUT_POST, 'edit_index', FILTER_VALIDATE_INT);
$transportationWindow = filter_input(INPUT_POST, 'transportation_window', FILTER_SANITIZE_STRING);

if (!$camperId || !$weekNum || empty($selectedDays) || !isset($_SESSION['playPassSelections'][$editIndex]) || empty($transportationWindow)) {
    PluginLogger::log("error:: Invalid Play Pass edit data");
    $_SESSION['playPassError'] = 'Invalid edit data. Please try again.';

    // Check if this is a WordPress AJAX request
    $isAjaxRequest = defined('DOING_AJAX') && DOING_AJAX;
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid edit data. Please try again.',
            'redirect' => '/camps/queue/playpass'
        ]);
    } else {

        echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    }
    exit;
}

// Collect options for each day
$registrationData = [
    'camper_id' => $camperId,
    'week' => $weekNum,
    'days' => [],
    'lunch' => [],
    'morning_care' => [],
    'afternoon_care' => [],
    'transportation_window' => $transportationWindow
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

// Get the transportation template ID based on the window
$transportationTemplateId = ($transportationWindow === 'Window A') ? 122575 : 122577;
$registrationData['transportation_template_id'] = $transportationTemplateId;

// Log registration data
PluginLogger::log("debug:: Play Pass edit data", $registrationData);

// Get the previous cart entry
$previousEntry = $_SESSION['playPassSelections'][$editIndex]['entry'];

// Remove the previous selection
$_SESSION['playPassSelections'][$editIndex] = [
    'entry' => $previousEntry,
    'data' => $registrationData
];

// Set success message
$_SESSION['playPassMessage'] = 'Play Pass selection updated successfully!';

// Check if this is a WordPress AJAX request
$isAjaxRequest = defined('DOING_AJAX') && DOING_AJAX;
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
