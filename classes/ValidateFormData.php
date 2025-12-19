<?php


date_default_timezone_set('America/Los_Angeles');

class ValidateFormData
{

    public $logger;

    function __construct($logger = null)
    {
        $this->setLogger($logger);
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new ValidateDataLogger();
    }

    // checks to see if the incoming string matches the format of a grid request
    function basicValidation($string)
    {
        $value = explode('-', $string);

        if (count($value) < 3) {
            // this simply doesn't match our format, so we're done here
            return false;
        }

        // the first character must be A, Q, C, P or R
        // A - Active Registration, Q - Queue Request, R - Register, C - Change, P - Playpass
        if ($value[0] != "A" && $value[0] != "Q" && $value[0] != "R" && $value[0] != "C" && $value[0] != "P") {
            PluginLogger::log("Failed input validation for $string due to an invalid first character.");
            return false;
        }

        // the second value is the camper and the third is the camp
        if (!is_numeric($value[1]) || !is_numeric($value[2])) {
            PluginLogger::log("Failed input validation for $string due to an invalid camper or camp id.", $value);
            return false;
        }

        // the fourth value is the week - it needs to be values of 1 through 12
        if (!is_numeric($value[3]) || (int)$value[2] < 1 || (int)$value[3] > 12) {
            PluginLogger::log("Failed input validation for $string due to an invalid week number.");
            return false;
        }

        // finally, the 5th value can include an option "M" for mobile rows (future functionality maybe)
        if (!empty($value[4]) && $value[4] != "M") {
            PluginLogger::log("Failed input validation for $string due to an invalid fifth value (M).");
            return false;
        }

        // passes basic validation 
        return true;
    }
}


class ValidateDataLogger
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
