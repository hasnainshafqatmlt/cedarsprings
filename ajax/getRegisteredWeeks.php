<?php
// ajax/getRegisteredWeeks.php - Get weeks with existing registrations for a camper

session_start();

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';
// require_once __DIR__ . '/../logger/plannerLogger.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../includes/ultracamp.php';

$validator = new ValidateLogin($logger);

// SECURE APPROACH: Use session-based authentication (same pattern as getExistingSelections.php)
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
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Initialize necessary classes
$uc = new UltracampModel($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Get camper ID
$camperId = filter_input(INPUT_POST, 'camper_id', FILTER_VALIDATE_INT);

if (!$camperId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid camper ID']);
    exit;
}

// Get registered weeks for this camper
$registeredWeeks = $playPassManager->getRegisteredWeeks($camperId);

// Return registered weeks
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'registeredWeeks' => $registeredWeeks
]);
exit;
