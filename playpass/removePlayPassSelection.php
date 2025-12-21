<?php
// removePlayPassSelection.php - Remove a Play Pass selection from the session

session_start();

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';
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

// Confirm that the user is still logged in
if (empty($authKey) || empty($authAccount) || !$validator->validate($authKey, $authAccount)) {
    // Return error in JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get selection index
$index = filter_input(INPUT_POST, 'index', FILTER_VALIDATE_INT);

if ($index === false || !isset($_SESSION['playPassSelections'][$index])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid selection index']);
    exit;
}

// Remove the selection
unset($_SESSION['playPassSelections'][$index]);

// Reindex the array
$_SESSION['playPassSelections'] = array_values($_SESSION['playPassSelections']);

// Return success
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
