<?php

// echos the camp capacities in JSON for javascript to use on the index page.
// The resulting array is used by the form validation on the grid and mobile page
// to understand when we've run out of space for a camp, and remaining options should be turned
// into queue selections.  This script started early on (January 23) as a simple SQL query
// which displayed capacity minus registrations. In April however, it evolved into the script here
// where it accounts for the total column (which didn't exist until March) and the number of campers
// in queue as well. 4/6/23 (BN)


$debug = false;


// get the pod size list
require_once __DIR__ . '/../classes/config.php';
require_once __DIR__ . '/../db/conn.php';
$config = new CQConfig;
$podSizes = $config->podSizes;

// skipping usual login checks as this not protected information

require_once __DIR__ . '/../db/db-reservations.php';
$db = new dbConnection();


//build out the sql to get our full list of camps and capacities
$sql = "SELECT    ucid
                , cap01, reg01, total01
                , cap02, reg02, total02
                , cap03, reg03, total03
                , cap04, reg04, total04
                , cap05, reg05, total05
                , cap06, reg06, total06
                , cap07, reg07, total07
                , cap08, reg08, total08
                , cap09, reg09, total09
                , cap10, reg10, total10
                , cap11, reg11, total11
                , cap12, reg12, total12
        FROM report_summer
        WHERE ucid IN (SELECT templateid FROM camp_options WHERE category IN ( 'Summer Day Camp Options', 'Keep the Fun Alive') )";

try {
    $results = $db->runBaseQuery($sql);
} catch (Exception $e) {
    echo json_encode(array()); // no need to totally fail
    return false;
}

// need to take the DB result, do math to get actual capacity remaining, and then set it up as a very simple array
// the key is wk#cmp# with the value as the capacity
// normalize null and negative capacities into zero


foreach ($results as $r) {


    for ($w = 1; $w < 13; $w++) {
        $a = 'wk' . $w; // week number for the results array
        $key = $a . 'cmp' . $r['ucid']; // array key for the results array
        $column = ($w < 10) ? "0$w" : "$w"; // zero padded week number for looking up values in the database results

        // here is where we do math to find the remaining capacity
        if ($r["total$column"] == NULL) {
            $podsize = (empty($podSizes[$r['ucid']])) ? 10 : $podSizes[$r['ucid']];
            $r["total$column"] = (($r["cap$column"] + 3) % $podsize == 0) ? $r["cap$column"] + 3 : $r["cap$column"];
        }

        // find how many are in queue
        $sql = "SELECT count(*) AS queues FROM waitlist 
                WHERE campId = ? 
                AND week_num = ? 
                AND active > 0 
                AND (active_expire_date >= CURRENT_DATE OR active_expire_date IS NULL)
                AND (snoozed_until IS NULL OR snoozed_until < CURRENT_DATE)";

        try {
            $result = $db->runQuery($sql, 'ii', array($r['ucid'], $w));
        } catch (Exception $e) {
            wp_die("Unable to count the waitlist entries for a camp in the campCapacities file: " . $e->getMessage());
        }

        $queued = (!empty($result)) ? $result[0]['queues'] : 0;

        // calculate the remaining capacity
        $capacity = $r["total$column"] - ($r["reg$column"] + $queued);

        $value = ($capacity > 0) ? $capacity : 0;



        $data[$key] = $value;

        /*    $logger->d_bug("Adding Key $key and Value $value", 
                    array('total' => $r["total$column"]
                        , 'capacity' => $r["cap$column"]
                        , 'registered' => $r["reg$column"]
                        , 'queued' => $queued));    
    */
    }
}

// Respect people with glasses. They paid money to see you **

echo json_encode($data);
