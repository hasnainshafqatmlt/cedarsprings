<?php

date_default_timezone_set('America/Los_Angeles');

$debug = false;
// require_once('logger/ReservationsLogger.php');

// if ($debug) {
// 	$logger->pushHandler($dbugStream);
// }


require_once(__DIR__ . '/classes/registrationCount.php');

try {
	$import = new registrationCount($logger);
} catch (Exception $e) {
	throw new Exception("Unable to process the option.", 0, $e);
}
