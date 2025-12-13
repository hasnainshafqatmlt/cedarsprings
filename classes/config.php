<?php

/**
 * config items for the public Camper Queue
 * Built 1/13/2023
 * Updated for seaons variables on 12/14/23 (BN)
 */
require_once(__DIR__ . '/../tools/SeasonalConfigElements.php');

// for 2024 - I've just extended the tools config file so that I don't have to duplicate code
// the two files have the same information in them.
class CQConfig extends SeasonalConfigElements
{
    function __construct() {}
}


/**
 * Helper function to return a spoofed date for test mode
 * When testmode is enabled, returns June 1, 2025 timestamp
 * Otherwise returns current timestamp
 *
 * @param bool $testmode Whether test mode is enabled
 * @return int Unix timestamp
 */
function getTestModeTimestamp($testmode = false)
{
    return $testmode ? strtotime('2025-06-01') : time();
}

/**
 * Helper function to return a spoofed date string for test mode
 * When testmode is enabled, returns June 1, 2025 as Y-m-d string
 * Otherwise returns current date as Y-m-d string
 *
 * @param bool $testmode Whether test mode is enabled
 * @return string Date in Y-m-d format
 */
function getTestModeDateString($testmode = false)
{
    return $testmode ? '2025-06-01' : date('Y-m-d');
}
