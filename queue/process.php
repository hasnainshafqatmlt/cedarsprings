<?php
require_once plugin_dir_path(__FILE__) . '/../classes/PluginLogger.php';
// Where all of the camper queue work gets done
// saving wait lists, processing registration with ultracamp, confirmation for the customer
// February 2023 (BN)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use an .env to ensure that we know what environment we are in.
$isDevEnvironment = false;
// $envPath = __DIR__ . '/../../../../.env';
// if (file_exists($envPath)) {
//     $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//     foreach ($envLines as $line) {
//         // Skip comments and empty lines
//         if (empty($line) || strpos(trim($line), '#') === 0) {
//             continue;
//         }

//         // Look for APP_ENV setting
//         if (preg_match('/^APP_ENV\s*=\s*(\w+)/', $line, $matches)) {
//             $isDevEnvironment = (trim($matches[1]) === 'development');
//             break;
//         }
//     }
// }


require_once(__DIR__ . '/../counter/view_counter.php');

$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/process.php', $_SERVER['REMOTE_ADDR']);

// get the logger
// require_once './logger/plannerLogger.php';

// disable debugging if we're not in dev
// if ($isDevEnvironment) {
// create a d-bug logger so that only the objects we're testing create debug noise
// $dbugLogger = new PlannerLogger('DBug-Queue');
//     // bind it to a logger object
//     $dbugLogger->pushHandler($stream);
//     $dbugLogger->pushHandler($dbugStream);
// } else {
//     $dbugLogger = $logger;
// }


// the ability to ensure the user is logged in

require_once __DIR__ . '/../classes/ValidateLogin.php';
$validator = new ValidateLogin($logger);

// class to process registration requests
require_once __DIR__ . '/../classes/UltracampCart.php';
$cart = new UltracampCart($dbugLogger);

PluginLogger::log("formInput::" . print_r([$_SESSION['formInput']], true));

// On refresh, we loose the session - this intentional so that customers don't resubmit the cart data twice
// however, it breaks this page, so redirect them back to the grid, without reauth prompt
// Add this in both shortcodes to debug
// echo ('Session ID: ' . session_id());
// echo '<br>';
// echo ('Session data: ' . print_r($_SESSION, true));

// echo '<pre>';
// print_r($_SESSION);
// echo '</pre>';
if (!$_SESSION['formInput'] && !is_array($_SESSION['formInput'])) {
    // send the browser back to the grid
    // header('Location: /camps/queue', true);
    echo '<script>window.location.href = "/camps/queue";</script>';
    exit;
}

// Confirm that the user is still logged in and their session has not expired
// also checking here to ensure that all of the submitted camper IDs belong to the logged in account
if (
    empty($_COOKIE['key']) ||
    empty($_COOKIE['account']) ||
    !$cart->validateAccountKey($_COOKIE['key'], $_COOKIE['account']) ||
    !$validator->validateCampers($_COOKIE['account'], $_SESSION['formInput'])
) {
    // instruct the grid to collect login info without running to Ultracamp (a second time) to see if the UC session is valid
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');

    // send the browser back to the grid
    // header('Location: /camps/queue', true);
    echo '<script>window.location.href = "/camps/queue";</script>';


    exit;
}

// class to save waitlists
require_once __DIR__ . '/../classes/SaveWaitlistEntries.php';
$waitlists = new SaveWaitlistEntries($logger);

// Friend Manager
require_once __DIR__ . '/../classes/FriendManager.php';
$FriendManager = new FriendManager($logger);

// Manages Pod Assignments
require_once __DIR__ . '/../classes/PodBuilder.php';
$pods = new PodBuilder($logger);
$pods->setFriendManager($FriendManager);

// Manages Transportation Pods
require_once __DIR__ . '/../classes/TransportationPods.php';
$buses = new TransportationPods($logger);

// get the class for validating the input values
require_once __DIR__ . '/../classes/ValidateFormData.php';
$formValidation = new ValidateFormData($logger);

// Brings in the library of CQ functions
require_once __DIR__ . '/../classes/CQModel.php';
$CQModel = new CQModel($dbugLogger);
$CQModel->setUltracampObj($cart);

// Deals with queue promotions when there is already a reservation in place
require_once __DIR__ . '/../classes/ActiveManager.php';

$activeManager = new ActiveManager($dbugLogger);

$activeManager->setPodBuilder($pods);

$activeManager->setBusManager($buses);


// store the camper information into the model so that we don't have to keep going to Ultracamp for person details
// This is found again by getting $CQModel->campers
$CQModel->setCampersByAccount(FILTER_VAR($_COOKIE['account'], FILTER_VALIDATE_INT));

$pods->setCQModel($CQModel);
$buses->setCQModel($CQModel);
$waitlists->setCQModel($CQModel);

// a couple of flags for the display section of the page
$displayWaitlist = false;
$displayRegistration = false;
$displayActiveQueue = false;
$displayCampChange = false;
$displayError = false;
$displayAddOn = false;

$busesAndPods = false; // if we're only processing Accelerate, then don't try to get buses and pods for day camps
$campAddOn = false; // If we process an add on, we check to see if it is a full camp in order to process transportation and the like


// find out what types of transactions we're processing. There is a lot of work that doesn't have to happen if we're only doing waitlists
foreach ($_SESSION['formInput'] as $form) {

    // make sure that we're ok with the incoming string
    if (!$formValidation->basicValidation($form)) {
        continue;
    }

    $b = explode('-', $form);
    // $dbugLogger->d_bug('Incoming Action: ' . $b[0], $b);

    switch ($b[0]) {
        case 'A':
        case 'R':
            $displayActiveQueue = true;

            // set the time that we were on the page, so that the portal knows to do an account level import
            $_SESSION['registrationPage'] = time();

            // I built this out because I got too deep in the weeds and thought that campfire night add-ons, being a reservation modification
            // wouldn't appear in the portal quick enough. However, I failed to recall that the customer doesn't make reservation modifications
            // therefore they wouldn't have a timing issue I was trying to prevent. I'm leaving the logic though, as it cannot hurt.
            $_SESSION['loadNewReservations'] = true; // indicate that we specifically want new reservations (as opposed to modifications)

            if ($b[2] != 9999) { // if we have even one instance of no accelerate, then we'll look at buses and pods
                $busesAndPods = true;
            }

            break;

        case 'Q':
            $displayWaitlist = true;
            // while we have it loaded, let's do the database work for the waitlist entries
            $waitlists->saveEntries($_COOKIE['account'], $form);
            break;

        case 'C':
            // If we have a change coming in, check to see if it's an addon related to campfire nights
            // store the result in an array with the form id as the key
            $changes[$form] = $activeManager->checkForAddOn($b);
            $dbugLogger->d_bug("Changes", $changes);

            // if the change coming in is an add-on, then show that block
            if ($changes[$form]['changeOrder']) {
                $displayAddOn = true;

                // we also need to flag if we're adding on a camp so that the bus and friend logic triggers
                if ($changes[$form]['campName'] === null) {
                    $campAddOn = true;
                    $busesAndPods = true;
                }
            }

            // else the change is a camp change, and so we need to show that block
            else {
                $displayCampChange = true;
            }

            break;
    }
}

// the work for things that only matter if we're processing a registration
if ($displayActiveQueue || $displayRegistration || $campAddOn) {

    // If we're making siblings friends, do that now so that pod assignments account for that
    if (!empty($_POST['sibling-choice']) && $_POST['sibling-choice'] == 'yes') {
        $pods->friendlySiblings($CQModel->campers);
    } else if (!empty($_POST['sibling-choice']) && $_POST['sibling-choice'] == 'no') {
        // remove friendly sibling matches in the database if they exist - but only do this if the choice isn't empty
        // this is to ensure that friend finder doesn't get annoyed that the queue didn't match siblings up as friends in the reservation
        $FriendManager->unfriendlySiblings($CQModel->campers);
    }

    // manage the incoming transportation
    $primaryTransportation = filter_input(INPUT_POST, 'transportation');
    $exceptionTransportation = null;

    // work the remaining inputs
    foreach ($_POST as $field => $value) {
        if (substr($field, 0, 15) == "additionalBuses") {
            $week = substr($field, 16);
            $exceptionTransportation[$week] = filter_input(INPUT_POST, $field);
        } else if (substr($field, 0, 7) == "friends" && strlen(filter_input(INPUT_POST, $field)) > 4) {
            //   $dbugLogger->d_bug("Friend Value: " .filter_input(INPUT_POST, $field));
            $FriendManager->saveFriends(substr($field, 8), filter_input(INPUT_POST, $field));
        }
    }

    if ($busesAndPods) {
        $busThinking = $buses->processBuses($_SESSION['formInput'], $primaryTransportation, $exceptionTransportation);
        $podChoices = $pods->processPods($_SESSION['formInput']);
    }

    // create an array of options to feed into the cart
    $cartOptions = array();
    foreach ($_SESSION['formInput'] as $entry) {

        // make sure that we're ok with the incoming string
        if (!$formValidation->basicValidation($entry)) {
            continue;
        }

        // Don't add to the cart those camps which are change orders (at least until Ultracamp allows me to modify existing reservations)
        if (isset($changes[$entry])) {
            PluginLogger::log("Skipping cart info for change order $entry" . print_r($changes[$entry], true));
            continue;
        }

        $a = explode('-', $entry);
        if ($a[0] != "Q") {
            $cartOptions[$entry]['camper']          = $CQModel->campers[$a[1]];

            //look out for Accelerate - lots of things are different there
            if ($a[2] == 9999) {

                $cartOptions[$entry]['week']             = array('weekNum' => $a[3], 'sessionid' => $CQModel->getAccelSessionIdFromWeekNumber($a[3]));
                $cartOptions[$entry]['accelSunday']      = filter_input(INPUT_POST, 'accelSundayChoice');
                $cartOptions[$entry]['accelFriday']      = filter_input(INPUT_POST, 'accelFridayChoice');
                continue; // don't process the remaining items
            }

            $cartOptions[$entry]['week']            = array('weekNum' => $a[3], 'sessionid' => $CQModel->getSessionIdFromWeekNumber($a[3]));

            $cartOptions[$entry]['camp']            = array('name' => $CQModel->getCamp([$a[2]]), 'templateid' => (int)$a[2]);

            if (!empty($pods->getPod($entry))) {
                $cartOptions[$entry]['campPod']         = $pods->getPod($entry);
            }

            if (!empty($buses->getTransportation($entry))) {
                $cartOptions[$entry]['transportation']  = $buses->getTransportation($entry);
            }

            if (!empty($buses->getPod($entry))) {
                $cartOptions[$entry]['busPod']          = $buses->getPod($entry);
            }

            if (!empty($buses->getExtendedCare($entry))) {
                $cartOptions[$entry]['extCare']         = $buses->getExtendedCare($entry);
            }

            if (!empty($_POST['lunchoption'])) {
                $cartOptions[$entry]['lunch']           = $_POST['lunchoption'];
            }
        }
    }
    $addResult = $cart->addEntriesToCart($_COOKIE['account'], $cartOptions);

    // if we gt a false back from the add entries, something errored out. The log file will have the details, but we need to store the 
    // reservation information into the camper queue and alert the customer to the error.
    // However, don't even try if there are not any options to store - this could be the result of a reservation with only change orders
    if (!empty($cartOptions) && $addResult === false) {
        // hide the success messages (we leave the camper queue success messages alone)
        $displayRegistration = false;
        $displayActiveQueue = false;
        // show the error message
        $displayError = true;

        PluginLogger::log("warning:: This following reservations were saved to the queue due to failed reservation processing.");

        // Save each as queue entries
        foreach ($_SESSION['formInput'] as $form) {
            $waitlists->saveEntries($_COOKIE['account'], $form, true);
            PluginLogger::log("warning:: Queue Entry: " . $form);
        }
    }

    // if (!empty($cartOptions) && !$cart->addEntriesToCart($_COOKIE['account'], $cartOptions)) {

    //     // hide the success messages (we leave the camper queue success messages alone as this doesn't impact them)
    //     $displayRegistration = false;
    //     $displayActiveQueue = false;
    //     // show the error message
    //     $displayError = true;

    //     // re-run the queue work, with errorRecovery = true to convert registration requests into queue requests
    //     PluginLogger::log("warning:: This following reservations were saved to the queue as a result of the failed attempt to process the reservation.");
    //     foreach ($_SESSION['formInput'] as $form) {
    //         $waitlists->saveEntries($_COOKIE['account'], $form, true);
    //         PluginLogger::log("warning:: Queue Entry: " . print_r($form, true));
    //     }
    // }

    // Because a reservation of only change orders can get this deep, don't try to close an empty cart
    if (!empty($cartOptions)) {
        $completedCart = $cart->completeCart();
        $ucLink = "https://www.ultracamp.com/" . $completedCart['SsoUri'];
    }
} // end if registration work

// if we have a change order, we have work to do
if ($displayCampChange || $displayAddOn) {
    //$dbugLogger->d_bug("Looking into the changelog");

    // if we don't have pods, then we've not run pod builder yet - need to do that
    if ($displayCampChange && empty($podChoices)) {
        $podChoices = $pods->processPods($_SESSION['formInput']);
    }

    // itterate the entries again, this time sending them to the ActiveManager class to process change orders
    // the ActiveManager already has the podbuilder object and so can read that directly
    foreach ($_SESSION['formInput'] as $g) {
        $activeManager->notifyOfficeOfChange($g);
    }
}

// We have a plural on the completeRegistration.php page that needs to know if one or more than one camper 
// had a waitlist added. Before we clear out the form info, we'll just count that really quickly and save it
$campersWithQueues = $CQModel->countWaitlistCampers($_SESSION['formInput']);

// clear the forminput session -- comment out when testing so you can refresh submit page
// however a customer refreshing the submit page and resending their data is not a good thing
$_SESSION['formInput'] = null;


// Show all of the behind the curtain logic - but ensure that we're in dev, and flip the true/false to enable/disable it.
if ($isDevEnvironment && false) {

    echo "<pre>";
    foreach ($_SESSION['formInput'] as $entry) {
        $a = explode('-', $entry);
        echo "Entry: " . $entry;
        echo "<br /><b>Camper: </b> " . $CQModel->campers[$a[1]]['firstName'];
        echo "<br /><b>Week: </b> " . $a[3];
        echo "<br /><b>Camp: </b> " . $CQModel->getCamp([$a[2]]);
        echo "<br /><b>Pod: </b> " . @$pods->getPod($entry)['name'];
    }
    echo "</pre>";

    echo "Form Fields<br />";
    echo "<pre>";
    echo isset($_POST) ? print_r($_POST, true) : "N/A";
    echo "</pre>";

    echo "Display Fields<br />";
    echo "<pre>";
    echo print_r(compact(
        'displayWaitlist',
        'displayRegistration',
        'displayActiveQueue',
        'displayCampChange',
        'displayError',
        'displayAddOn',
        'busesAndPods',
        'campAddOn'
    ), true);
    echo "</pre>";

    echo "Change Orders<br />";
    echo "<pre>";
    echo isset($changes) ? print_r($changes, true) : "N/A";
    echo "</pre>";


    echo "Cart Options<br />";
    echo "<pre>";
    echo isset($cartOptions) ? print_r($cartOptions, true) : "N/A";
    echo "</pre>";

    echo "Pod Choices<br />";
    echo '<pre>';
    echo isset($podChoices) ? print_r($podChoices, true) : "N/A";
    echo '</pre>';

    echo "Bus Thinking";
    echo "<pre>";
    echo isset($busThinking) ? print_r($busThinking, true) : "N/A";
    echo "</pre>";

    echo "Selected Reservation Options:<br />";
    echo "<pre>";
    foreach ($_SESSION['formInput'] as $entry) {
        $a = explode('-', $entry);
        echo "Entry: " . $entry;
        echo "<br /><b>Camper: </b> " . $CQModel->campers[$a[1]]['firstName'];
        echo "<br /><b>Week: </b> " . $a[3];
        echo "<br /><b>Camp: </b> " . $CQModel->getCamp([$a[2]]);
        echo "<br /><b>Pod: </b> " . @$pods->getPod($entry)['name'];

        if (isset($buses) && null !== $buses->getTransportation($entry) && substr($buses->getTransportation($entry)['name'], 0, 6) == "Window") {
            echo "<br /><b>Lake Stevens Drop Off</b>";
        }

        echo "<br /><b>Transportation: </b> " . @$buses->getTransportation($entry)['name'];
        echo "<br /><b>Bus Option: </b> " . @$buses->getPod($entry)['name'];
        echo "<br /><b>Extended Care: </b> " . @$buses->getExtendedCare($entry);
        echo "<br /><b>Lunch: </b> ";
        echo isset($_POST['lunchoption']) ? print_r($_POST['lunchoption'], true) : 'N/A';
    }

    echo "</pre>";

    echo "Campers:<br />";
    echo "<pre>";
    echo null !==  $CQModel->campers ? print_r($CQModel->campers, true) : "N/A";
    echo "</pre>";
}
