<?php

// March 21st - the Camper Queue saga continues ever onward(BN)

/**
 * This AJAX page takes the javascript for modifying queue entries from the customer portal and takes the appropriate actions
 */

$debug = false;

// require_once('../logger/plannerLogger.php');
// if($debug) {
//     $logger->pushHandler($dbugStream);
// }

// we first ensure that the user is still running a valid api key
// if they are, we then look up the camper aged people in their account and return them

$key = FILTER_INPUT(INPUT_POST, 'key');
$account = FILTER_INPUT(INPUT_POST, 'account');

if (!$key || !$account) {
    PluginLogger::log("Failing to process get campers due to invalid or missing key and account number.");
    return json_encode(array("StatusCode" => 400, "Message" => "Account information provided is invalid", "Authenticated" => false));
}
require_once __DIR__ . '/../api/ultracamp/CartAndUser.php';
$uc = new CartAndUser($logger);

require_once __DIR__ . '/../classes/config.php';
require_once __DIR__ . '/../db/db-reservations.php';
$db = new reservationsDb($logger);
$tables = new CQConfig;


// validate that the user is still authenticated
try {
    $auth = $uc->validateAccountKey($key, $account);
} catch (Exception $e) {
    PluginLogger::log('error:: Unable to validate the api key: ' . $e->getMessage());
}

if (isset($auth['Authenticated']) && $auth['Authenticated'] == true) {
    $result['Authenticated'] = true;
} else {
    echo json_encode(array('Authenticated' => false));
    return false;
}

// users is valid - moving on

// split out the entry element
// camper-camp-week
$id = filter_input(INPUT_POST, 'id');
$entry = explode('-', $id);

// ID the action that we're taking
$action = filter_input(INPUT_POST, 'action');

switch ($action) {
    case 'snooze':
        $result = snoozeEntry($entry);
        echo json_encode(array('Authenticated' => true, 'id' => $id, 'action' => $action, 'result' => $result));
        break;

    case 'reactivate':
        $result = reactivateEntry($entry);
        echo json_encode(array('Authenticated' => true, 'id' => $id, 'action' => $action, 'result' => $result));
        break;

    case 'cancel':
        $result = cancelEntry($entry);
        echo json_encode(array('Authenticated' => true, 'id' => $id, 'action' => $action, 'result' => $result));
        break;
}

function snoozeEntry($entry)
{
    global $account, $db, $logger, $tables;
    $account = FILTER_INPUT(INPUT_POST, 'account');

    // Ensure config object is available even if globals were not initialized in this scope
    if (!isset($tables) || !is_object($tables)) {
        $tables = new CQConfig($logger);
    }
    if (!isset($db) || !is_object($db)) {
        $db = new reservationsDb($logger);
    }

    $sql = "UPDATE " . $tables->waitlist . " SET snoozed_until = ?, active_expire_date = ? WHERE accountId = ? AND camperId = ? AND week_num = ? AND campId = ? AND active > 0";
    try {
        $update = $db->update($sql, 'ssiiii', array(date("Y-m-d H:i:s", strtotime('+1 week')), NULL, $account, $entry[0], $entry[2], $entry[1]));
    } catch (Exception $e) {
        PluginLogger::log("error:: Unable to snooze a queue entry due to a SQL error: " . $e->getMessage());
        PluginLogger::log('debug:: ' . $sql . array(date("Y-m-d H:i:s", strtotime('+1 week')), $account, $entry[0], $entry[2], $entry[1]));
        return false;
    }

    PluginLogger::log("Affected Rows: ", $update);
    return $update;
}

function reactivateEntry($entry)
{
    global $account, $db, $logger, $tables;
    $account = FILTER_INPUT(INPUT_POST, 'account');

    // Ensure config object is available even if globals were not initialized in this scope
    if (!isset($tables) || !is_object($tables)) {
        $tables = new CQConfig($logger);
    }
    if (!isset($db) || !is_object($db)) {
        $db = new reservationsDb($logger);
    }

    $sql = "UPDATE " . $tables->waitlist . " SET snoozed_until = ?, active_expire_date = ? WHERE accountId = ? AND camperId = ? AND week_num = ? AND campId = ? AND active > 0";
    try {
        PluginLogger::log("Reactivate Queue", array(NULL, NULL, $account, $entry[0], $entry[2], $entry[1]));
        $update = $db->update($sql, 'ssiiii', array(NULL, NULL, $account, $entry[0], $entry[2], $entry[1]));
    } catch (Exception $e) {
        PluginLogger::log("error:: Unable to reactivate a queue entry due to a SQL error: " . $e->getMessage());
        PluginLogger::log($sql, array(date("Y-m-d H:i:s", strtotime('+1 week')), $account, $entry[0], $entry[2], $entry[1]));
        return false;
    }

    PluginLogger::log("Affected Rows: ", $update);
    return $update;
}

function cancelEntry($entry)
{
    global $account, $db, $logger, $tables;
    $account = FILTER_INPUT(INPUT_POST, 'account');
    // Ensure config object is available even if globals were not initialized in this scope
    if (!isset($tables) || !is_object($tables)) {
        $tables = new CQConfig($logger);
    }
    if (!isset($db) || !is_object($db)) {
        $db = new reservationsDb($logger);
    }


    $sql = "UPDATE " . $tables->waitlist . " SET active = 0 WHERE accountId = ? AND camperId = ? AND week_num = ? AND campId = ? AND active > 0";

    try {
        $update = $db->update($sql, 'iiii', array($account, $entry[0], $entry[2], $entry[1]));
    } catch (Exception $e) {
        PluginLogger::log("error:: Unable to reactivate a queue entry due to a SQL error: " . $e->getMessage());
        PluginLogger::log($sql, array(date("Y-m-d H:i:s", strtotime('+1 week')), $account, $entry[0], $entry[2], $entry[1]));
        return false;
    }

    return $update;
}
