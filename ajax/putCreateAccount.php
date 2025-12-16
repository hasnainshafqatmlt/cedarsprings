<?php

// March 3rd - the Camper Queue saga continues(BN)

/**
 * This AJAX page takes the javascript input for creating an account
 * If succesful, we send back the account and key for the authentication
 * If not, we send back the server error for the user to deal wit
 * We handle "username already exists" errors specifically however
 */

$debug = false;

// require_once('../logger/plannerLogger.php');
// if($debug) {
//     $logger->pushHandler($dbugStream);
// }

// the ability to ensure the user is logged in
$logger = null;
require_once __DIR__ . '/../classes/ValidateLogin.php';
$validator = new ValidateLogin($logger);

require_once __DIR__ . '/../classes/UltracampCart.php';
$uc = new UltracampCart($logger);

//build an array with camper information to send to the Ultracamp object
$accountInfo['firstName'] = filter_input(INPUT_POST, 'firstName');
$accountInfo['lastName'] = filter_input(INPUT_POST, 'lastName');
$accountInfo['cellPhone'] = filter_input(INPUT_POST, 'phoneNumber');
$accountInfo['email'] = filter_input(INPUT_POST, 'emailAddress', FILTER_VALIDATE_EMAIL);
$accountInfo['password'] = filter_input(INPUT_POST, 'password');
$accountInfo['address'] = filter_input(INPUT_POST, 'address');
$accountInfo['city'] = filter_input(INPUT_POST, 'city');
$accountInfo['state'] = filter_input(INPUT_POST, 'state');
$accountInfo['zip'] = filter_input(INPUT_POST, 'zip');

try {
    $response = $uc->createAccount($accountInfo);
} catch (Exception $e) {
    PluginLogger::log("Unable to create a new account in ajax/putCreateAccount: " . $e->getMessage());
    //echo json_encode(array('The server did not respond with a valid confirmation - please try again.'));

    return false;
}

// if we don't get back an array (should be status=>error/success, message=>details), then something is wrong
if (!is_array($response)) {
    //echo json_encode(array('The server did not respond - please try again.'));
    PluginLogger::log('Ultracamp did not respond to the create account API request.');
    return json_encode(array('status' => 'error', 'message' => 'There was an unexpected server error when attempting to create your account.'));
}

// Duplicate account error
if ($response['status'] == 'error' && $response['message'] == 'User name already exists') {
    echo  json_encode(array('User name already exists'));
    return false;
}

// if we get back an error, and it's not a duplciate account error, send it to the client
if (empty($response['status'] || $response['status'] == 'error')) {
    // Handle duplicate account separately
    if ($response['status'] == 'error' && $response['message'] == 'User name already exists') {
        echo json_encode(array('User name already exists'));
        return false;
    }

    // Handle all other errors through our error handler
    $errorResponse = handleAccountCreationError($response, $accountInfo, $logger);
    echo json_encode($errorResponse);
    return false;
}

// we should have a properly formated response from Ultracamp at this point
$newAccount = $response['message'];
$user = $uc->authenticateUser($newAccount['UserName'], $accountInfo['password'], $newAccount['IdAccount']);

if (!isset($user['Authenticated']) || $user['Authenticated'] !== true) {
    // there was an error - we should always be able to login as we just created the user with these credentials
    echo json_encode(array('There was an unknown error creating your account.'));
    PluginLogger::log("Unable to log into newly created account " . $newAccount['IdAccount'] . " with email address " . $accountInfo['email']);
    return true;
}

$result['key'] = $user['q'];
$result['account'] = $user['AccountId'];
$result['name'] = $accountInfo['firstName'];
$result['Authenticated'] = true;

// the user name SHOULD match the entered email address, but it does not always
// capture the returned user name, and include that in the results so that a message can be displayed to the user
// if it was changed - this happens when they have their email address assocoiated with another camp
if (strtolower($accountInfo['email'] != strtolower($newAccount['UserName']))) {
    PluginLogger::log("User name modification took place for " . $accountInfo['email'], $newAccount);
    $result['username'] = $newAccount['UserName'];
}

echo json_encode($result);

function handleAccountCreationError($response, $accountInfo)
{
    global $logger;

    $errorResponse = [
        'status' => 'error',
        'code' => $response['responseCode'] ?? 500
    ];

    // Handle specific known error cases
    if (isset($response['message']) && is_string($response['message'])) {
        if (strpos($response['message'], "Address -- Is Empty") !== false) {
            $errorResponse['message'] = "Please provide a complete mailing address.";
            $errorResponse['field'] = 'address';
        } else if (strpos($response['message'], "Email -- Required") !== false) {
            $errorResponse['message'] = "Please provide a valid email address.";
            $errorResponse['field'] = 'email';
        } else if (strpos($response['message'], "Parameter name: test") !== false) {
            $errorResponse['message'] = "temporarily_unavailable";
            $errorResponse['redirect'] = "https://www.ultracamp.com/createNewAccount.aspx?idCamp=107&campCode=CP7";
        }
        // Add other specific error cases here
    }

    // Log the error with context
    PluginLogger::log('Account creation error', [
        'error' => $response,
        'account_info' => $accountInfo,
        'handled_response' => $errorResponse
    ]);

    return $errorResponse;
}
