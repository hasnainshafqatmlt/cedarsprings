<?php
// ajax/getCampTypeForWeek.php - Get camp type for a specific week

session_start();

// Check login status
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../classes/ValidateLogin.php';
require_once __DIR__ . '/../includes/ultracamp.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';

$validator = new ValidateLogin($logger);
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

if (empty($authKey) || empty($authAccount) || !$validator->validate($authKey, $authAccount)) {
    // Return error in JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Initialize necessary classes
$uc = new UltracampModel($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Get parameters
$camperId = filter_input(INPUT_POST, 'camper_id', FILTER_VALIDATE_INT);
$weekNum = filter_input(INPUT_POST, 'week_num', FILTER_VALIDATE_INT);

if (!$camperId || !$weekNum) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Check for Play Pass registration
$isPlayPass = $playPassManager->hasPlayPassRegistration($camperId, $weekNum);

// Check for regular camp registration
$regularCamp = $playPassManager->hasRegularRegistration($camperId, $weekNum);

$result = [
    'success' => true,
    'week_number' => $weekNum,
    'is_play_pass' => false,
    'camp_name' => ''
];

if ($isPlayPass) {
    $result['is_play_pass'] = true;
    $result['camp_name'] = 'Play Pass';
} elseif ($regularCamp) {
    $result['is_play_pass'] = false;
    $result['camp_name'] = $regularCamp['camp_name'];
}

PluginLogger::log("debug:: getcampTypeForWeek", $result);

header('Content-Type: application/json');
echo json_encode($result);
exit;
