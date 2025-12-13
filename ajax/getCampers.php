<?php

$debug = false;

// we first ensure that the user is still running a valid api key
// if they are, we then look up the camper aged people in their account and return them

$key = FILTER_INPUT(INPUT_POST, 'key');
$account = FILTER_INPUT(INPUT_POST, 'account');

if (!$key || !$account) {
    PluginLogger::log("Failing to process get campers due to invalid or missing key and account number.");
    wp_die(json_encode(array("StatusCode" => 400, "Message" => "Account information provided is invalid")));
}

require_once plugin_dir_path(__FILE__) . '../api/ultracamp/CartAndUser.php';
$uc = new CartAndUser();

// validate that the user is still authenticated
try {
    $auth = $uc->validateAccountKey($key, $account);
} catch (Exception $e) {
    PluginLogger::log('error: Unable to validate the api key: ' . $e->getMessage());
    wp_die(json_encode(array('Authenticated' => false, 'Message' => 'Unable to validate the api key: ' . $e->getMessage())));
}

if (isset($auth['Authenticated']) && $auth['Authenticated'] == true) {
    $result['Authenticated'] = true;
} else {
    wp_die(json_encode(array('Authenticated' => false)));
}

// users is valid - moving on

// Check if testmode is enabled
$testmode = (FILTER_INPUT(INPUT_POST, 'testmode') === 'true');

require_once plugin_dir_path(__FILE__) . '../classes/CampStatus.php';
$status = new CampStatus();
$status->setTestMode($testmode);

require_once plugin_dir_path(__FILE__) . '../classes/CQModel.php';
$CQModel = new CQModel();
$CQModel->setUltracampObj($uc);

// get the people on the account
$actPeople = $uc->getPeopleByAccount($account);

// loop through the results and keep the names and ages of those who will be camper aged during the summer

$campers = array();

// loop through everyone on the account
foreach ($actPeople as $person) {
    // if the person has a birthdate in the system . . .
    if (isset($person->BirthDate)) {
        $personDOB = $person->BirthDate;

        // ensure that they're within the age range for us to list them on the form (check their age) at the boundries -individual camps will enforce their own limits
        if ($CQModel->getAge($personDOB, 12) >= 5 && $CQModel->getAge($personDOB, 1) < 15) {
            // PluginLogger::log("Adding " . $person->FirstName . " to the results.");
            // if they are, get their camp details
            $campers[] = array(
                'id' => $person->Id,
                'first' => $person->FirstName,
                'last' => $person->LastName,
                'campStatus' => $status->setCamper($person->Id)
            );
        }
    }
}

$result['campers'] = $campers;
wp_die(json_encode($result));
