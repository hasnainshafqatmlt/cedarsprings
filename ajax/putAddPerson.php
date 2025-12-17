<?php

// March 2nd - the Camper Queue saga continues(BN)

/**
 * This AJAX page takes the javascript input for adding a camper to an account
 */

$debug = false;

// require_once('../logger/plannerLogger.php');
// if($debug) {
//     $logger->pushHandler($dbugStream);
// }

// we first ensure that the user is still running a valid api key
// if they are, we then look up the camper aged people in their account and return them

$key = FILTER_INPUT(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$account = FILTER_INPUT(INPUT_POST, 'account', FILTER_SANITIZE_STRING);

if (!$key || !$account) {
    PluginLogger::log("Failing to process get campers due to invalid or missing key and account number.");
    return json_encode(array("StatusCode" => 400, "Message" => "Account information provided is invalid"));
}

require_once __DIR__ . '/../api/ultracamp/CartAndUser.php';

$uc = new CartAndUser($logger);

// validate that the user is still authenticated
try {
    $auth = $uc->validateAccountKey($key, $account);
} catch (Exception $e) {
    PluginLogger::log('Unable to validate the api key: ' . $e->getMessage());
    return false;
}

if (isset($auth['Authenticated']) && $auth['Authenticated'] == true) {
    $result['Authenticated'] = true;
} else {
    echo json_encode(array('Authenticated' => false));
    return false;
}

// users is valid - moving on

//build an array with camper information to send to the Ultracamp object
/*
        'firstName'  : camperFirstName,
        'lastName'  : camperLastName,
        'camperDob' : camperDOB,
        'gender'    : camperGender,
        'account'   : getCookie('account'),
        'key'       : getCookie('key')
*/

$camper['firstName']    = filter_input(INPUT_POST, 'firstName');
$camper['lastName']     = filter_input(INPUT_POST, 'lastName');
$camper['camperDob']    = filter_input(INPUT_POST, 'camperDob');
$camper['gender']       = filter_input(INPUT_POST, 'gender');
$camper['account']      = filter_input(INPUT_POST, 'account');

try {
    $camperResult = $uc->addCamperToAccount($camper);
} catch (Exception $e) {
    PluginLogger::log("Unable to create a new camper record: " . $e->getMessage());
    return false;
}

if ($camperResult) {
    echo json_encode(array('Success' => true));
}
