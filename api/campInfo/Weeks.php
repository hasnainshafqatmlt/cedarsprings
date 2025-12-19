<?php

/**
 * Loads the current summer weeks from the database so that they don't have to be re-typed each year
 * 1/6/2023 BN
 */

class Weeks
{


    // internal variables
    protected $db;
    public $logger;
    public $weeks;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once plugin_dir_path(__FILE__) . '../../classes/config.php';
        require_once plugin_dir_path(__FILE__) . '../../db/db-reservations.php';
        $this->db = new reservationsDb($this->logger);
    }

    function setLogger($logger = null)
    {
        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new WeeksDummyLogger();
    }


    // The database holds the strings with zeropadded week numbers.
    // Sometimes it looks good to keep them, other times, to remove them.
    // zeropad = keeps the zeros
    function listWeeks($weekNumbers = true, $zeropad = false)
    {
        $sql = "SELECT * FROM summer_weeks ORDER BY week_num";

        try {
            $r = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            PluginLogger::log("Unable to list the weeks from the database: " . $e->getMessage());
            return false;
        }

        // default condition, if there are week numbers, and if they are not zero padded
        if ($weekNumbers) {
            if (!$zeropad) {
                foreach ($r as $key => $value) {
                    if (substr($value['short_name'], 0, 1) == 0) {
                        $r[$key]['short_name'] = substr($value['short_name'], 1);
                    }
                }
            }
        } else {
            // if there are not week numbers, remove then entierly
            foreach ($r as $key => $value) {
                $r[$key]['short_name'] = preg_replace('/^\d{1,2}\s-\s/', '', $value['short_name']);
            }
        }


        $this->weeks = $r;

        return $r;
    }

    // Can return the weeks in a list of DIVs with a string of provided classnames
    function encloseInDiv($className = NULL)
    {
        // because we may provide some formating values to the weeks, we can access the previous call to that method
        // if just this is called, then you get week numbers included with no zero padding (default string)
        $weeks = empty($this->weeks) ? $this->listWeeks() : $this->weeks;

        $r = '';
        $class = empty($className) ? '' : ' class="' . $className . '"';

        foreach ($weeks as $w) {
            $r .= '<div id="session_' . $w['session_id'] . '"';
            $r .= $class . '>';
            $r .= $w['short_name'];
            $r .= '</div>';
            $r .= "\n";
        }

        return $r;
    }

    /**
     * for really unique tag setups (i.e. the grid), just pass in the tags and attributes needed, and they'll be returned
     * */
    function customTags($openTag, $closeTag, $idVariable = null)
    {
        // because we may provide some formating values to the weeks, we can access the previous call to that method
        // if just this is called, then you get week numbers included with no zero padding (default string)
        $weeks = empty($this->weeks) ? $this->listWeeks() : $this->weeks;

        // $this->logger->d_bug("customTags", array('openTag' => $openTag, 'closeTag' => $closeTag, 'weeks' =>$weeks));

        $r = '';
        foreach ($weeks as $w) {
            $sessionID = $w['session_id'];

            // if we have an ID variable, include it, otherwise leave it out

            $r .= $openTag;

            $r .= $w['short_name'];
            $r .= $closeTag;

            $r .= "\n";
        }

        return $r;
    }
    /**
     * Gets the current summer week, based on today's date. If we're prior to summer, the result is 0, if we're after summer, the results is 13. 
     * The break for before and after is the new year
     */
    function getCurrentWeek()
    {
        // protect from July 4th by skipping forward to the fifth
        $currentDate = (date('m-d') == '07-04') ? date('Y-m-d', strtotime("+1 day")) : date('Y-m-d');

        // date interval on end date allows us to account for the weekends
        // by skipping July 4th as a valid date, we don't have to do the same to the start date
        $sql = 'SELECT week_num FROM summer_weeks WHERE start_date <= ? AND end_date >= DATE(? - INTERVAL 2 DAY)';

        //    $this->logger->d_bug("getting current week - currentDate: $currentDate", $sql);

        try {
            $result = $this->db->runQuery($sql, 'ss', array($currentDate, $currentDate));
        } catch (Exception $e) {
            PluginLogger::log("Unable to select the week number from the summer_weeks database");
            PluginLogger::log($e->getMessage());

            // return zero in the event of an error
            return 0;
        }

        // return 0 or 13 for out of bounds
        if (!isset($result)) {
            //        $this->logger->d_bug("Current Data has found not matching weeks. Month: " .date("n"));
            if ((int)date("m") <= 6) {
                return 0;
            } else {
                return 13;
            }
        }
        $this->logger->d_bug("Current Week is returning ", $result[0]['week_num']);
        return $result[0]['week_num'];
    }

    function weekCount()
    {
        $r = $this->listWeeks();

        return count($r);
    }
}

class WeeksDummyLogger
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
