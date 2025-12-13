<?php
require_once __DIR__ . '/../db/db-reservations.php';

function get_summer_schedule($futureOnly = false)
{
    $db = new reservationsDb();
    // Check if testmode is enabled
    $testmode = (FILTER_INPUT(INPUT_POST, 'testmode') === 'true');

    $sql = "SELECT * FROM summer_weeks";
    if ($futureOnly) {
        $sql .= " WHERE start_date > '" . getTestModeDateString($testmode) . "'";
    }
    $sql .= " ORDER BY week_num";

    try {
        $r = $db->runBaseQuery($sql);
    } catch (Exception $e) {
        return array('error' => 'Unable to list the weeks from the database: ' . $e->getMessage());
    }
    // if there are not week numbers, remove them entirely
    foreach ($r as $key => $value) {
        $r[$key]['short_name'] = preg_replace('/^\d{1,2}\s-\s/', '', $value['short_name']);
    }

    $result = array();
    foreach ($r as $w) {
        $result[] = $w['short_name'];
    }
    return $result;
}
