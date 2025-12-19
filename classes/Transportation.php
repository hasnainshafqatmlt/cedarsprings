<?php

/**
 *     Goes to the database and gets the capacities for the transportation options for display on the Process Camper Queue Page
 */

date_default_timezone_set('America/Los_Angeles');

class Transportation
{

    public $logger;
    protected $db;
    public $config;
    public $transportationResults;
    public $dropoffResults;
    protected $weeksFull;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once plugin_dir_path(__FILE__) . '../classes/config.php';

        require_once plugin_dir_path(__FILE__) . '../db/db-reservations.php';
        $this->db = new reservationsDb();
        $this->config = new CQConfig;
        $this->db->setLogger($this->logger);
    }

    /**
     * Gets the bus transportation options from the database which are not full
     * if weeks is included, it must be an int or an array of integers, and it will filter the results to those which have space during those week
     * This is used to return the valid options for every week in a query
     */
    function getTransportationOptions($weeks = null, $minCapacity = 1)
    {

        // run to the DB, get the transportation options
        $sql = "SELECT id, name, templateid, parent, uc_description, web_description 
                FROM " . $this->config->camp_options . " 
                WHERE category = 'Transportation'
                AND parent <> 'Lake Stevens Drop-Off'
                ORDER BY name";
        try {
            $trans = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            PluginLogger::log("Unable to load the transportation options from the database: " . $e->getMessage());
            return false;
        }

        // see if we have specific weeks, otherwise, get the list from the DB (checking for number too as I hate sending in single element arrays)
        if (!is_array($weeks)) {
            $weeks = is_numeric($weeks) ? array($weeks) : $this->getSummerWeeks();
        }

        // ensure that there are not duplicate values coming in the weeks array
        $weeks = array_unique($weeks);

        // itterate through the options, get their capacity values, dump those not in the weeks array and other stuff
        foreach ($trans as $t) {
            PluginLogger::log("Checking " . $t['name'] . " for a capacity of $minCapacity");
            $cap = false;
            foreach ($weeks as $w) {
                if ($this->getCapacity($t['templateid'], $w) >= $minCapacity) {
                    $cap = true;
                } else {
                    $this->weeksFull[$t['templateid']][$w] = true;
                }
            }

            // if we find capacity anywhere in the requested weeks, return the result
            if ($cap) {
                $result[] = $t;
            }
        }
        $this->transportationResults = $result;

        return $result;
    }

    /**
     * Like the getTransportationOptions method, this return the available options for Accelerate
     * Currently, it will only show options that are available for all chosen weeks (few family do more than one)
     * In the future, like day camp, it should have the ability to create exceptions for weeks which have an option full
     */


    /**
     * Gets the bus transportation options from the database which are not full
     * if weeks is included, it must be an int or an array of integers, and it will filter the results to those which have space during those week
     * This is used to return the valid options for every week in a query
     */
    function getAccelTransportation($weeks, $minCapacity = 1)
    {

        // run to the DB, get the transportation options
        $sql = "SELECT id, name, templateid, parent, uc_description, web_description 
            FROM " . $this->config->camp_options . " 
            WHERE category = 'Overnight Transportation'
            ORDER BY name";
        try {
            $trans = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            PluginLogger::log("Unable to load the transportation options from the database: " . $e->getMessage());
            return false;
        }

        // ensure that there are not duplicate values coming in the weeks array
        $weeks = array_unique($weeks);

        // itterate through the options, get their capacity values, dump those not in the weeks array and other stuff
        foreach ($trans as $t) {
            PluginLogger::log("Checking " . $t['name'] . " for a capacity of $minCapacity");
            $cap = false;
            foreach ($weeks as $w) {
                if ($this->getCapacity($t['templateid'], $w) >= $minCapacity) {
                    $cap = true;
                } else {
                    $this->weeksFull[$t['templateid']][$w] = true;
                }
            }

            // if we find capacity anywhere in the requested weeks, return the result
            if ($cap) {
                $result[] = $t;
            }
        }
        $this->transportationResults = $result;

        return $result;
    }
    // Like get transportation, but because we do a bunch of hard-coding and specific DB lookups for local, we're putting this in it's own method
    function getDropOffOptions($weeks = null, $minCapacity = 1)
    {

        // Unfortunately - the pickup zones in Ultracamp are not very helpful for the dropdown format
        // therefore, we're just going to build the options here, and check on the capacities available

        // see if we have specific weeks, otherwise, get the list from the DB (checking for number too as I hate sending in single element arrays)
        if (!is_array($weeks)) {
            $weeks = is_numeric($weeks) ? array($weeks) : $this->getSummerWeeks();
        }

        // ensure that there are not duplicate values coming in the weeks array
        $weeks = array_unique($weeks);

        // get the templateids for the local pickup windows
        $trans = array(122575, 122577);

        // itterate through the options, get their capacity values, dump those not in the weeks array and other stuff
        foreach ($trans as $t) {

            $cap = false;
            foreach ($weeks as $w) {

                if ($this->getCapacity($t, $w) >= $minCapacity) {
                    $cap = true;
                } else {
                    $this->weeksFull[$t][$w] = true;

                    // include the extended care options as full as well
                    $this->weeksFull[$t . '-A'][$w] = true;
                    $this->weeksFull[$t . '-P'][$w] = true;
                    $this->weeksFull[$t . '-F'][$w] = true;
                }
            }

            // if we find capacity anywhere in the requested weeks, return the result
            if ($cap) {
                $result[] = $t;
            }
        }

        return $result;
    }

    function getDropOffOptionHTML($weeks, $minCapacity = 1)
    {
        // get the windows with capacity
        $windows = $this->getDropOffOptions($weeks, $minCapacity);
        $html = '';
        if (!empty($windows)) { // don't error out when there aren't any options
            // encapsulate the result into HTML for the transportation drop down
            // note that this code is duplicated in ajax/getAdditionalTransportationOptions.php for the exception select elements
            foreach ($windows as $w) {
                if ($w == 122575) {
                    $html .= '<option value="122575">Lake Stevens (8:30-4:00)</option>';
                    $html .= '<option value="122575-A">Lake Stevens w/ Ext. Care (7:00-4:00)</option>';
                    $html .= '<option value="122575-P">Lake Stevens w/ Ext. Care (8:30-6:00)</option>';
                } else if ($w == 122577) {
                    $html .= '<option value="122577">Lake Stevens (9:30-5:00)</option>';
                    $html .= '<option value="122577-A">Lake Stevens w/ Ext. Care (7:00-5:00)</option>';
                    $html .= '<option value="122577-P">Lake Stevens w/ Ext. Care (9:00-6:00)</option>';
                    $html .= '<option value="122577-F">Lake Stevens w/ Full Ext. Care (7:00-6:00)</option>';
                }
            }
        }

        return $html;
    }

    function getTransportationOptionHTML($weeks, $minCapacity = 1)
    {

        if (empty($this->transportationResults)) {
            $this->getTransportationOptions($weeks, $minCapacity);
        }

        // encapsulate the result into HTML for the transportation drop down
        $html = '';
        foreach ($this->transportationResults as $bus) {
            $html .= '<option value="';
            $html .= $bus['templateid'] . '">';
            $html .= $bus['name'];
            $html .= '</option>';
        }

        return $html;
    }

    function getTransportationOptionJS($weeks, $minCapacity = 1)
    {
        if (empty($this->transportationResults)) {
            $this->getTransportationOptions($weeks, $minCapacity);
        }

        $sql = 'select max(cost) as cost from camp_option_mapping where templateid = ?';

        // encapsulate the result into HTML for the transportation drop down
        $js = '';
        foreach ($this->transportationResults as $bus) {
            $js .= $bus['templateid'] . ' : "';


            $busCost = '';
            // get the price for the bus transportation
            try {
                $result = $this->db->runQuery($sql, 'i', [$bus['templateid']]);
            } catch (Exception $e) {
                PluginLogger::log("Unable to get the bus cost from the database for day camp: " . $e->getMessage());
            }

            if (isset($result) && $result[0]['cost'] > 0) {
                $busCost = "(+\${$result[0]['cost']}) ";
            }


            // remove newline characters from the string - it breaks the JS
            $desc = empty($bus['web_description']) ?
                str_replace(array("\r", "\n"), '', nl2br($bus['uc_description']))
                : str_replace(array("\r", "\n"), '', $bus['web_description']);

            $js .= $busCost . $desc;
            $js .= "\",\n";
        }

        return $js;
    }

    // hard coded drop off description text
    function getDropOffOptionJS()
    {
        // Get the cost for the pickup elements (so that they're not hardcoded) - 1/20/25 (BN)
        // 126474 - AM Ext care
        // 126475 - PM Ext care
        $sql = 'SELECT templateid, MAX(cost) AS cost FROM camp_option_mapping WHERE templateid IN (126475, 126474) GROUP BY templateid';
        $morningCost = $eveningCost = $fullCost = '';

        try {
            $result = $this->db->runBaseQuery($sql);

            if ($result && is_array($result)) {
                foreach ($result as $row) {
                    $cost = "(+$" . $row['cost'] . ") ";
                    if ($row['templateid'] == 126474) {
                        $morningCost = $cost;
                    } elseif ($row['templateid'] == 126475) {
                        $eveningCost = $cost;
                    }
                }

                // Calculate full cost only if both morning and evening costs exist
                if ($morningCost && $eveningCost) {
                    $fullCost = "(+$" . (
                        (int)str_replace(['(+$', ') '], '', $morningCost) +
                        (int)str_replace(['(+$', ') '], '', $eveningCost)
                    ) . ") ";
                }
            }
        } catch (Exception $e) {
            PluginLogger::log("Unable to get the extended care cost from the database for day camp: " . $e->getMessage());
        }

        $js = "122575 : \"Drop off and pick up directly from camp in Lake Stevens.<br /><br />Morning check in: <b>8:30 - 8:45 AM</b><br />Afternoon check out: <b>4:00 - 4:15 PM</b>\",\n";

        $js .= "122577 : \"Drop off and pick up directly from camp in Lake Stevens.<br /><br />Morning check in: <b>9:00 - 9:30 AM</b><br />Afternoon check out: <b>5:00 - 5:15 PM</b>\",\n";

        $js .= "'122575-A' : \"$morningCost Enjoy the flexibility of morning extended care when dropping of and picking up at Lake Stevens. For an additional $35 a week, campers can check in anytime after 7 AM.<br /><br />Morning check in: <b>7:00 - 9:30 AM</b><br />Afternoon check out: <b>4:00 - 4:15 PM</b>\",\n";

        $js .= "'122577-A' : \"$morningCost Enjoy the flexibility of morning extended care when dropping of and picking up at Lake Stevens. For an additional $35 a week, campers can check in anytime after 7 AM.<br /><br />Morning check in: <b>7:00 - 9:30 AM</b><br />Afternoon check out: <b>5:00 - 5:15 PM</b>\",\n";

        $js .= "'122577-P' : \"$eveningCost Enjoy the flexibility of afternoon extended care when dropping of and picking up at Lake Stevens. For an additional $35 a week, campers can check out anytime prior to 6 PM.<br /><br />Morning check in: <b>9:00 - 9:30 AM</b><br />Afternoon check out: <b>5:00 - 6:00 PM</b>\",\n";

        $js .= "'122575-P' : \"$eveningCost Enjoy the flexibility of afternoon extended care when dropping of and picking up at Lake Stevens. For an additional $35 a week, campers can check out anytime prior to 6 PM.<br /><br />Morning check in: <b>8:30 - 8:45 AM</b><br />Afternoon check out: <b>5:00 - 6:00 PM</b>\",\n";

        $js .= "'122577-F' : \"$fullCost By adding morning and afternoon extended care, campers are able to check in anytime after 7 AM and be picked up as late as 6 PM (add $70 each week).<br /><br />Morning check in: <b>7:00 - 9:30 AM</b><br />Afternoon check out: <b>5:00 - 6:00 PM</b>\",\n";

        $js .= "'' : 'Select a drop off and pick up location and we will take care of the rest. For details on each of the bus locations, <a href=\"/locations\" target=_BLANK>click here</a>.'\n";

        return $js;
    }

    // takes a template ID and a week and returns the current capacity
    function getCapacity($option, $week)
    {
        // make sure that we don't have odd values coming in
        if (!is_numeric($week)) {
            PluginLogger::log("The week number provided ($week)for getCapacity is not numeric.");
            return false;
        }
        if (!is_numeric($option)) {
            PluginLogger::log("The option templateid provided ($option) for getCapacity is not numeric.");
            return false;
        }

        // zero pad the week numbers
        $week = (strlen($week) == 1) ? '0' . $week : $week;

        $sql = "SELECT cap" . $week . ', reg' . $week . ' FROM ' . $this->config->report_summer . ' WHERE ucid = ?';
        try {
            $z = $this->db->runQuery($sql, 'i', $option);
        } catch (Exception $e) {
            PluginLogger::log("Unable to retrived the capacity for $option on week $week: " . $e->getMessage());
            return false;
        }

        // ensure we have a clean result
        $reg = is_numeric($z[0]['reg' . $week]) ? $z[0]['reg' . $week] : 0;
        $cap = is_numeric($z[0]['cap' . $week]) ? $z[0]['cap' . $week] : 0;

        //        PluginLogger::log("Capacity for $option is: ". $cap - $reg);

        return $cap - $reg;
    }

    // lists the week numbers for the current season (should be 1 through 12, but sometimes is 11)
    // if futureOnly is true, then only the weeks still to come are returned
    function getSummerWeeks($futureOnly = true)
    {

        $sql  = 'SELECT week_num FROM ' . $this->config->summer_weeks;
        $sql .= $futureOnly ? ' WHERE start_date > \'' . date("Y-m-d") . '\' ' : ' ';
        $sql .= 'ORDER BY week_num ASC';

        try {
            $w = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            PluginLogger::log("Unable to collect the summer weeks from the database: " . $e->getMessage());
            return false;
        }

        foreach ($w as $x) {
            $y[] = $x['week_num'];
        }

        PluginLogger::log("Getting summer weeks from the database", $y);
        return $y;
    }

    function getWeeksFull()
    {

        return json_encode($this->weeksFull);
    }


    function accelSundayOption($weeks, $count)
    {
        PluginLogger::log('Accel Sunday Options', array($weeks, $count));
        $sundayBus = 83718;
        $busCapacity = true;

        // check to ensure that the bus is available for each week that they've chosen
        foreach ($weeks as $w) {
            if ($this->getCapacity($sundayBus, $w) < $count) {
                $busCapacity = false;
                break;
            }
        }

        // get the price for the bus transportation
        $sql = 'select max(cost) as cost from camp_option_mapping where templateid = ?';
        try {
            $result = $this->db->runQuery($sql, 'i', [$sundayBus]);
        } catch (Exception $e) {
            PluginLogger::log("Unable to get the Friday accelerate cost from the database: " . $e->getMessage());
        }

        if (isset($result) && $result[0]['cost'] > 0) {
            $sundayBusCost = "(+\${$result[0]['cost']})";
        } else {
            $sundayBusCost = '';
        }


        // build the HTML for the website and return it - I really shouldn't be building HTML here, but I am
        $html = "<div class='sibling-option no-top-padding'>";
        $html .= "<input type='radio' class='lunch-choice-checkbox' name='accelSundayChoice' id='accelSundayCar' value='direct' checked />";
        $html .= "<label for='accelSundayCar'>Direct drop off at camp</label>";
        $html .= "</div>";


        $html .= "<div class='sibling-option no-top-padding'>";
        $html .= "<input type='radio' class='lunch-choice-checkbox' name='accelSundayChoice' id='accelSundayBus' value='bus' ";
        if (!$busCapacity) {
            $html .= 'disabled ';
        }
        $html .= "/>";

        $html .= "<label for='accelSundayBus'>Bus transportation from Bothell $sundayBusCost</label>";

        if (!$busCapacity) {
            $html .= "<p class='no-top-padding' style='color:white; font-size:small'>Bus transportation is full</p>";
        }

        $html .= "</div>";

        return $html;
    }

    function accelFridayOption($weeks, $count)
    {
        PluginLogger::log('Accel Friday Options', array($weeks, $count));
        $fridayBus = 83719;
        $busCapacity = true;

        // check to ensure that the bus is available for each week that they've chosen
        foreach ($weeks as $w) {
            if ($this->getCapacity($fridayBus, $w) < $count) {
                $busCapacity = false;
                break;
            }
        }

        // get the price for the bus transportation
        $sql = 'select max(cost) as cost from camp_option_mapping where templateid = ?';
        try {
            $result = $this->db->runQuery($sql, 'i', [$fridayBus]);
        } catch (Exception $e) {
            PluginLogger::log("Unable to get the Friday accelerate cost from the database: " . $e->getMessage());
        }

        if (isset($result) && $result[0]['cost'] > 0) {
            $fridayBusCost = "(+\${$result[0]['cost']})";
        } else {
            $fridayBusCost = '';
        }

        // build the HTML for the website and return it - I really shouldn't be building HTML here, but I am
        $html  = "<div class='sibling-option no-top-padding'>";
        $html .= "<input type='radio' class='lunch-choice-checkbox' name='accelFridayChoice' id='accelFridayCar' value='direct' checked />";
        $html .= "<label for='accelFridayCar'>Direct pick up from camp</label>";
        $html .= '</div>';

        $html .= "<div class='sibling-option no-top-padding'>";
        $html .= "<input type='radio' class='lunch-choice-checkbox' name='accelFridayChoice' id='accelFridayBus' value='bus' ";
        if (!$busCapacity) {
            $html .= 'disabled ';
        }
        $html .= "/>";

        $html .= "<label for='accelFridayBus'>Bus transportation to Bothell $fridayBusCost</label>";

        if (!$busCapacity) {
            $html .= "<p class='no-top-padding' style='color:white; font-size:small'>Bus transportation is full</p>";
        }
        $html .= '</div>';

        return $html;
    }






    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new TransportationDummyLogger();
    }
}

class TransportationDummyLogger
{

    function d_bug($arg1, $arg2 = null)
    {
        return true;
    }
    function debug($arg1, $arg2 = null)
    {
        return true;
    }
    function info($arg1, $arg2 = null)
    {
        return true;
    }
    function warning($arg1, $arg2 = null)
    {
        return true;
    }
    function error($arg1, $arg2 = null)
    {
        throw new Exception($arg1);
        return true;
    }
}
