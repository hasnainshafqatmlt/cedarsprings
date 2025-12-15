<?php

/**
 * Camper Queue, on the customer portal, started by displaying a message that the reservation details lag by 5 minutes, this is a terrible thing
 * so this reservation import is here to take an account number and update the reservations for that account only
 * 3/21/23 (BN) - I have honestly lost track of how long I've been working on CQ at this point
 */


date_default_timezone_set('America/Los_Angeles');

$debug = false;

// I've found that having debug running in test is really useful as the output gives me a progress report
// therefore, I'm setting debug = true whenever I'm not in production, regardless of the production flag
$host = gethostname();
if (isset($host) && $host != 'host.cedarsprings.camp') {
	$debug = true;
}


// require_once('logger/ReservationsLogger.php');

// if($debug) {
// 	$logger->pushHandler($dbugStream);
// }

if (empty($account) || !is_numeric($account)) {
	PluginLogger::log("Error:: Invalid account included in reservation import: $account");
	return true;
}

require_once(__DIR__ . '/../../../api/ultracamp/ultracamp.php');
require_once(__DIR__ . '/classes/SummerModel.php');
require_once(__DIR__ . '/classes/AccelerateModel.php');

// setup classes
$db = new SummerModel($logger);
$Accelerate = new AccelerateModel($logger);
$uc = new UltracampModel($logger);

PluginLogger::log("d_bug:: Starting Account Based Import.");

$today = date("Y-m-d");

$chooseImport = $chooseImport ?? false;
$importNewOrders = $importNewOrders ?? false;
$importModifiedOrders = $importModifiedOrders ?? false;

// Need to filter out the reservations that we want to keep
// We'll get CPDA, Belieze, Off Season, and Parties in here too - so only keep those for summer
// Accelerate is also different than day camp, so that needs to be handled as well

//Day Camp Session IDs
foreach ($db->getSeasonSessions() as $dc) {
	$dayCampSessions[] = $dc['ucid'];
}
//Accelerate Session IDs
foreach ($Accelerate->getSessions() as $ac) {
	$accelerateSessions[] = $ac['ucid'];
}

// Load reservations placed today by this account
if (!$chooseImport || ($chooseImport && $importNewOrders)) {
	$newData = $uc->getRecentReservationsByAccount($account);
}

// I thought that I'd want to import modified orders - I don't think so however.
// Left the code though, just in case.
if ($chooseImport && $importModifiedOrders) {
	$date = date("Y-m-d H:i:s");
	$modifiedData = $uc->getReservationsByModificationDate($date, null, $account);
}

// Initialize an empty array to store combined data
$ucData = [];

// Add modified reservations if they exist
if (!empty($modifiedData)) {
	$ucData = array_merge($ucData, $modifiedData);
	PluginLogger::log("d_bug:: Added " . count($modifiedData) . " modified reservations to processing queue");
}

// Add new reservations if they exist
if (!empty($newData)) {
	$ucData = array_merge($ucData, $newData);
	PluginLogger::log("d_bug:: Added " . count($newData) . " new reservations to processing queue");
}

if (!empty($ucData)) {
	PluginLogger::log("d_bug:: " . count($ucData) . " reservations found which have been placed by account $account.");

	// loop through the results and process the reservations
	foreach ($ucData as $reservation) {
		// ensure that we're only looking at summer sessions, and seperate accelerate out from day camp
		if (in_array($reservation->SessionId, $dayCampSessions)) {
			try {
				PluginLogger::log("d_bug:: Processing reservation " . $reservation->ReservationId . " for camper " . $reservation->FirstName . ' ' . $reservation->LastName);
				$db->processReservation($reservation);
			} catch (Exception $e) {
				$logger->error("Unable to process the reservation. " . $e->getMessage());
				throw new Exception("Unable to process the reservation.", 0, $e);
			}
		} else if (in_array($reservation->SessionId, $accelerateSessions)) {
			try {
				$Accelerate->processReservation($reservation);
			} catch (Exception $e) {
				$logger->error("Unable to process the reservation. " . $e->getMessage());
				throw new Exception("Unable to process the reservation.", 0, $e);
			}
		} else {
			PluginLogger::log("d_bug:: Ignoring reservations " . $reservation->ReservationId . " as it is for " . $reservation->SessionName);
			PluginLogger::log("d_bug:: Day Camp Sessions", $dayCampSessions);
			PluginLogger::log("d_bug:: Reservation Session", $reservation->SessionId);
		}
	}
}

// Once that is complete, then run the various counts to build the tabulator data in report_summer
require_once('update-registration-count.php');
