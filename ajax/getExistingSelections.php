<?php
// ajax/getExistingSelections.php - Retrieves existing Play Pass selections for a camper

session_start();

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';

// require_once __DIR__ . '../logger/plannerLogger.php';
$validator = new ValidateLogin($logger);

// SECURE APPROACH: Use session-based authentication
// After initial validation on playpass.php, credentials are stored in session
// This avoids passing sensitive data in POST body

// First, try to get auth from session (preferred - most secure)
$authKey = null;
$authAccount = null;

if (!empty($_SESSION['ultracamp_auth_key']) && !empty($_SESSION['ultracamp_auth_account'])) {
    $authKey = $_SESSION['ultracamp_auth_key'];
    $authAccount = $_SESSION['ultracamp_auth_account'];

    // Validate session-based auth
    if ($validator->validate($authKey, $authAccount)) {
        // Session auth is valid, proceed
    } else {
        // Session auth expired or invalid, clear it
        unset($_SESSION['ultracamp_auth_key']);
        unset($_SESSION['ultracamp_auth_account']);
        $authKey = null;
        $authAccount = null;
    }
}

// Fallback: If session doesn't have auth, try cookies (for backwards compatibility)
if (empty($authKey) || empty($authAccount)) {
    if (!empty($_COOKIE['key']) && !empty($_COOKIE['account'])) {
        $authKey = $_COOKIE['key'];
        $authAccount = $_COOKIE['account'];

        // If cookies are valid, store in session for future requests
        if ($validator->validate($authKey, $authAccount)) {
            $_SESSION['ultracamp_auth_key'] = $authKey;
            $_SESSION['ultracamp_auth_account'] = $authAccount;
        }
    }
}

// Last resort: Accept from POST body (less secure, but needed for path-restricted cookies)
// This is a temporary workaround - session-based auth is preferred
if (empty($authKey) || empty($authAccount)) {
    $postKey = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
    $postAccount = filter_input(INPUT_POST, 'account', FILTER_SANITIZE_STRING);

    if (!empty($postKey) && !empty($postAccount)) {
        // Validate POST credentials
        if ($validator->validate($postKey, $postAccount)) {
            $authKey = $postKey;
            $authAccount = $postAccount;

            // Store in session for future requests (so we don't need POST next time)
            $_SESSION['ultracamp_auth_key'] = $authKey;
            $_SESSION['ultracamp_auth_account'] = $authAccount;
        }
    }
}

// Final validation
if (empty($authKey) || empty($authAccount) || !$validator->validate($authKey, $authAccount)) {
    // Return error in JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Get camper ID
$camperId = filter_input(INPUT_POST, 'camper_id', FILTER_VALIDATE_INT);

if (!$camperId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid camper ID']);
    exit;
}

// Check if we have any Play Pass selections
if (empty($_SESSION['playPassSelections'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'selections' => []]);
    exit;
}

// Filter selections for the specified camper
$camperSelections = [];
foreach ($_SESSION['playPassSelections'] as $index => $selection) {
    if ($selection['data']['camper_id'] == $camperId) {
        // Add index to the data
        $selection['index'] = $index;
        $camperSelections[] = $selection;
    }
}

// Return selections
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'selections' => $camperSelections
]);
exit;
