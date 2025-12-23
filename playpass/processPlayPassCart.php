<?php
// processPlayPassCart.php - Handles Play Pass registrations only
session_start();

// Use an .env to ensure that we know what environment we are in.
$isDevEnvironment = false;


require_once(plugin_dir_path(__FILE__) . '../counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/processPlayPassCart', $_SERVER['REMOTE_ADDR']);


// Initialize necessary classes
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';
require_once __DIR__ . '/../includes/ultracamp.php';
require_once __DIR__ . '/../classes/UltracampCart.php';
require_once __DIR__ . '/../classes/ValidateLogin.php';

$validator = new ValidateLogin($logger);
$cart = new UltracampCart($logger);
$CQModel = new CQModel($logger);
PluginLogger::log((' **** in 1'));
$CQModel->setUltracampObj($cart);
PluginLogger::log((' **** in 2'));
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Validate user login
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
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    echo json_encode([
        'success' => true,
        'error' => 'Authentication required',
        'redirect' => '/camps/queue'
    ]);
    exit;
}
PluginLogger::log((' **** in 3'));
// IMPORTANT: Validate the account key with Ultracamp
// This sets up the API key for subsequent cart operations
if (!$cart->validateAccountKey($_COOKIE['key'], $_COOKIE['account'])) {
    PluginLogger::log("error:: Failed to validate account key with Ultracamp");
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    echo json_encode([
        'success' => true,
        'redirect' => '/camps/queue'
    ]);
    exit;
}
PluginLogger::log((' **** in 4'));
// Check if we have Play Pass data
if (empty($_SESSION['playPassData'])) {
    PluginLogger::log("error:: No Play Pass data found in session");
    echo json_encode([
        'success' => true,
        'redirect' => '/camps/queue/playpass'
    ]);
    exit;
}

// Set display flags for the completion page
$_SESSION['displayRegistration'] = true;
$_SESSION['displayError'] = false;

PluginLogger::log(' **** in 4-1');
// Store the camper information for access in the model

$CQModel->setCampersByAccount(FILTER_VAR($_COOKIE['account'], FILTER_VALIDATE_INT));
PluginLogger::log((' **** in 5'));
// Process Play Pass entries
$cartOptions = [];

PluginLogger::log("debug:: Processing Play Pass entries", array_keys($_SESSION['playPassData']));

foreach ($_SESSION['playPassData'] as $cartEntry => $playPassData) {

    PluginLogger::log("debug:: Processing cart entry with window: {$playPassData['transportation_window']}");

    $options = $playPassManager->buildCartOptions($cartEntry, $playPassData);

    // Make sure the transportation window is being passed along
    PluginLogger::log("debug:: Using transportation window: {$playPassData['transportation_window']}");
    PluginLogger::log("debug:: PlayPassCart Options", $options);

    if ($options) {
        foreach ($options as $key => $entry) {
            $cartOptions[$key] = $entry;
        }
    }
}
PluginLogger::log((' **** in 6'));

// Add entries to cart
if (!empty($cartOptions)) {
    PluginLogger::log("debug:: Adding Play Pass entries to cart", [
        'account' => $_COOKIE['account'],
        'numOptions' => count($cartOptions),
        'cartOptions' => $cartOptions
    ]);

    if (!$cart->addEntriesToCart($_COOKIE['account'], $cartOptions)) {
        $_SESSION['displayRegistration'] = false;
        $_SESSION['displayError'] = true;
        PluginLogger::log("error:: Failed to create cart for Play Pass registrations");
    } else {
        $completedCart = $cart->completeCart();
        $_SESSION['ucLink'] = "https://www.ultracamp.com/" . $completedCart['SsoUri'];
        PluginLogger::log("debug:: Cart created successfully", ['ssoUri' => $completedCart['SsoUri']]);

        // Set the time that we were on the page, for the portal
        $_SESSION['registrationPage'] = time();
    }
} else {
    PluginLogger::log("error:: No cart options generated from Play Pass data");
    $_SESSION['displayRegistration'] = false;
    $_SESSION['displayError'] = true;
}
PluginLogger::log((' **** in 7'));
// Store any relevant information for the completeRegistration page
$_SESSION['campersWithQueues'] = 0; // No waitlist entries for Play Pass

// Clear Play Pass data from session
$_SESSION['playPassData'] = null;

// Redirect to completion page
echo json_encode([
    'success' => true,
    'redirect' => '/camps/queue/completePlayPassRegistration'
]);

exit;
