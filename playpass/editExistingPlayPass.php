<?php
// editExistingPlayPass.php - Processes edits to existing Play Pass registrations
// This is different from editPlayPassSelection.php which handles in-cart edits

session_start();

// Use an .env to ensure that we know what environment we are in.
$isDevEnvironment = false;

require_once(plugin_dir_path(__FILE__) . '../counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/editExistingPlayPass', $_SERVER['REMOTE_ADDR']);

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';
$validator = new ValidateLogin($logger);

// Required classes
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';
require_once __DIR__ . '/../includes/ultracamp.php';
require_once __DIR__ . '/../classes/UltracampCart.php';
require_once __DIR__ . '/../classes/EmailChangeOrder.php';

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
$uc = new UltracampModel($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);
$emailChangeOrder = new EmailChangeOrder($logger);

// Validate form submission
$camperId = filter_input(INPUT_POST, 'camper_id', FILTER_VALIDATE_INT);
$weekNum = filter_input(INPUT_POST, 'week_num', FILTER_VALIDATE_INT);
$selectedDays = isset($_POST['selected_days']) ? $_POST['selected_days'] : [];
$transportationWindow = filter_input(INPUT_POST, 'transportation_window', FILTER_SANITIZE_STRING);
$friendsList = filter_input(INPUT_POST, 'friends', FILTER_SANITIZE_STRING);

if (!$camperId || !$weekNum || empty($selectedDays) || empty($transportationWindow)) {
    PluginLogger::log("error:: Invalid Play Pass edit data for existing registration");
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

// Get original registration details
$originalRegistration = $playPassManager->getPlayPassRegistrationDetails($camperId, $weekNum);

if (empty($originalRegistration) || $originalRegistration['camp_name'] !== 'Play Pass') {
    PluginLogger::log("error:: No valid Play Pass registration found for camper $camperId in week $weekNum");
    $_SESSION['playPassError'] = 'No valid Play Pass registration found to edit. Please try again.';

    // Check if this is a WordPress AJAX request
    $isAjaxRequest = defined('DOING_AJAX') && DOING_AJAX;
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No valid Play Pass registration found to edit. Please try again.',
            'redirect' => '/camps/queue/playpass'
        ]);
    } else {

        echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    }
    exit;
}

// Collect new registration data
$newRegistrationData = [
    'camper_id' => $camperId,
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
        $newRegistrationData['days'][] = $dayNum;

        // Check for lunch option
        if (isset($_POST["lunch_day_$dayNum"]) && $_POST["lunch_day_$dayNum"] == $dayNum) {
            $newRegistrationData['lunch'][] = $dayNum;
        }

        // Check for morning extended care
        if (isset($_POST["morning_care_day_$dayNum"]) && $_POST["morning_care_day_$dayNum"] == $dayNum) {
            $newRegistrationData['morning_care'][] = $dayNum;
        }

        // Check for afternoon extended care
        if (isset($_POST["afternoon_care_day_$dayNum"]) && $_POST["afternoon_care_day_$dayNum"] == $dayNum) {
            $newRegistrationData['afternoon_care'][] = $dayNum;
        }
    }
}

// Get camper information for the email
$camperInfo = $CQModel->getCamperName($camperId);

// Generate a unique edit ID
$editId = 'edit_' . uniqid();

// Store the edit in the session
if (!isset($_SESSION['playPassEdits'])) {
    $_SESSION['playPassEdits'] = [];
}

$_SESSION['playPassEdits'][$editId] = [
    'original' => $originalRegistration,
    'new' => $newRegistrationData,
    'camper_info' => $camperInfo
];

// Log the edit
PluginLogger::log("debug:: Play Pass existing registration edit: $editId", [
    'original' => $originalRegistration,
    'new' => $newRegistrationData
]);

// Set success message
$_SESSION['playPassMessage'] = 'Play Pass registration edit has been added to your cart. You will need to checkout to complete the submission.';

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
