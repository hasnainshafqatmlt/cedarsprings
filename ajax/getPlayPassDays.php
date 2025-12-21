<?php
// ajax/getPlayPassDays.php - Get day options for Play Pass registration

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

// Validate authentication
if (empty($authKey) || empty($authAccount) || !$validator->validate($authKey, $authAccount)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Initialize necessary classes
$uc = new UltracampModel($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Get parameters
$camperId = filter_input(INPUT_POST, 'camper', FILTER_VALIDATE_INT);
$weekNum = filter_input(INPUT_POST, 'week', FILTER_VALIDATE_INT);
$editMode = filter_input(INPUT_POST, 'edit_mode', FILTER_VALIDATE_INT) === 1;
$editIndex = filter_input(INPUT_POST, 'edit_index', FILTER_VALIDATE_INT);

if (!$camperId || !$weekNum) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing camper or week number']);
    exit;
}

// Get day options
PluginLogger::log("debug:: Getting day options for camper $camperId in week $weekNum");

$dayOptions = $playPassManager->getDayOptions($weekNum, $camperId);

PluginLogger::log("debug:: Day options result:", $dayOptions);
if (isset($dayOptions['error'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $dayOptions['error']]);
    exit;
}

// If in edit mode, add existing selection information
if ($editMode && isset($_SESSION['playPassSelections'][$editIndex])) {
    PluginLogger::log("debug:: Adding existing selection to response for edit mode index: $editIndex");
    $dayOptions['existing_selection'] = $_SESSION['playPassSelections'][$editIndex]['data'];
}

// Return day options as JSON
header('Content-Type: application/json');
echo json_encode($dayOptions);
exit;
