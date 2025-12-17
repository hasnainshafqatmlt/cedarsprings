<?php

$debug = false;

// require_once('../logger/plannerLogger.php');
// if($debug) {
//     $logger->pushHandler($dbugStream);
// }

/** Takes the incoming week number and returns the available transportation HTML for that week
 * This is used on the submitCamperQueue page to create select options when the prefered transportation method isn't available for all of the weeks chosen
 */

// take the week from the input
$week = FILTER_INPUT(INPUT_POST, 'week', FILTER_VALIDATE_INT);
$logger = '';

if (empty($week)) {
    echo json_encode(array('error' => 'Invalid week value provided.'));
    return false;
}


require_once plugin_dir_path(__FILE__) . '../classes/Transportation.php';

$transportation = new Transportation($logger);
// Get the local drop off options
$dropoff = $transportation->getDropOffOptions($week);
foreach ($dropoff as $w) {
    if ($w == 122575) {
        $result[] = array('templateid' => 122575, 'name' => 'Lake Stevens (8:30-4:00)');
        $result[] = array('templateid' => '122575-A', 'name' => 'Lake Stevens w/ Ext. Care (7:00-4:00)');
        $result[] = array('templateid' => '122575-P', 'name' => 'Lake Stevens w/ Ext. Care (8:30-6:00)');
    } else if ($w == 122577) {
        $result[] = array('templateid' => 122577, 'name' => 'Lake Stevens (9:30-5:00)');
        $result[] = array('templateid' => '122577-A', 'name' => 'Lake Stevens w/ Ext. Care (7:00-5:00)');
        $result[] = array('templateid' => '122577-P', 'name' => 'Lake Stevens w/ Ext. Care (9:00-6:00)');
        $result[] = array('templateid' => '122577-F', 'name' => 'Lake Stevens w/ Full Ext. Care (7:00-6:00)');
    }
}

// get the available bus options
$values = $transportation->getTransportationOptions($week);
foreach ($values as $x) {
    $result[] = array('templateid' => $x['templateid'], 'name' => $x['name']);
}

echo json_encode($result);
return true;
