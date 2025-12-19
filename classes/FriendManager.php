<?php

/**
 *     Interacts with the Friend Finder System on behalf of the camper queue
 *      2/9/23 (BN)
 */

date_default_timezone_set('America/Los_Angeles');

class FriendManager
{

    public $logger;
    protected $db;
    protected $tables;

    protected $uc;
    protected $production;
    protected $UltracampFriends;
    protected $FriendFinder;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once __DIR__ . '/config.php';

        require_once __DIR__ . '/../db/db-reservations.php';
        require_once __DIR__ . '/../includes/ultracamp.php';
        require_once __DIR__ . '/../friends/classes/UltracampFriends.php';
        require_once __DIR__ . '/../friends/classes/SaveFriend.php';

        $this->db = new reservationsDb();
        $this->tables = new CQConfig;

        $this->db->setLogger($this->logger);
        $this->uc = new UltracampModel($this->logger);

        $this->UltracampFriends = new UltracampFriends($this->logger);
        $this->FriendFinder = new SaveFriend($this->logger);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }

        if ($this->production) {
            PluginLogger::log("Friend Manager is Operating in production!");
        } else {
            PluginLogger::log("Friend Manager is Operating in Dev Mode");
        }
    }

    // set siblings as friends for an account - send in an array of the campers
    function friendlySiblings($campers)
    {
        // to do this, we'll need to get all of the people in an account that are camper aged, and then save them as friends

        // itterate through all of the campers and save them as friends of each other
        foreach ($campers as $key => $kid) {
            // itterate through their friends
            foreach ($campers as $siblingKey => $sibling) {

                // don't friend yourself
                if ($siblingKey == $key) {
                    continue;
                }

                //check to ensure that they're not already in the DB as friends - skip if they are
                $sqlDupeCheck = "SELECT id FROM " . $this->tables->friends_table . " WHERE camper_ucid = ? AND friend_ucid = ? LIMIT 1";
                try {
                    $dupeCheck = $this->db->runQuery($sqlDupeCheck, 'ii', array($key, $siblingKey));
                } catch (Exception $e) {
                    wp_die("Unable to dupe check the friends table in friendlySiblings(): " . $e->getMessage());
                    return false;
                }

                if (!empty($dupeCheck)) {
                    continue;
                } // the friend pair already exists, move to the next one

                // add the siblings to the friend database
                $sqlInsert = "INSERT INTO " . $this->tables->friends_table . " (camper_ucid, friend_ucid, date_added, friend_source, match_explanation)
                                VAlUES (?,?,?,'Camper Queue Siblings',?)";


                try {
                    $this->db->insert($sqlInsert, 'iiss', array($key, $siblingKey, date("Y-m-d H:i:s"), $sibling['firstName'] . ' ' . $sibling['lastName']));
                } catch (Exception $e) {
                    wp_die("Unable to insert sibling friends in friendlySiblings(): " . $e->getMessage());
                }
            }
        }
    }

    /**
     * The inverse of friendly siblings, when set, we'll remove any friendly sibling friend entries which were set by the queue
     * We'll keep explicit sibling matches entered by the user however
     */
    function unfriendlySiblings($campers)
    {
        // itterate through all of the campers and save them as friends of each other
        foreach ($campers as $key => $kid) {
            // itterate through their friends
            foreach ($campers as $siblingKey => $sibling) {
                // don't un-friend yourself
                if ($siblingKey == $key) {
                    continue;
                }

                //check to ensure that they're in the DB as friends - skip if they are not
                $sqlCheck = "SELECT id FROM " . $this->tables->friends_table
                    . " WHERE camper_ucid = ? 
                                    AND friend_ucid = ? 
                                    AND friend_source = 'Camper Queue Siblings'
                                    LIMIT 1";
                try {
                    $sibCheck = $this->db->runQuery($sqlCheck, 'ii', array($key, $siblingKey));
                } catch (Exception $e) {
                    wp_die("Unable to check the friends table in unfriendlySiblings(): " . $e->getMessage());
                    return false;
                }

                if (empty($sibCheck)) {
                    continue;
                } // the friend pair doesn't exist, move to the next one

                // remove the siblings from the friend database
                $sqlDelete = "DELETE FROM " . $this->tables->friends_table . " WHERE id = ? LIMIT 1";

                try {
                    $this->db->update($sqlDelete, 'i', $sibCheck[0]['id']);
                } catch (Exception $e) {
                    wp_die("Unable to delete sibling friends in friendlySiblings(): " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Takes a camper, the camp and week in question, and returns all pods with friends registered
     */
    function findFriendsInCamp($camper, $camp, $week)
    {
        // List all friends -  
        PluginLogger::log("Looking for friends.", array("camper" => $camper, "camp" => $camp, "week" => $week));
        $sql = "SELECT friend_ucid FROM " . $this->tables->friends_table . " WHERE camper_ucid = ?";
        try {
            $friends = $this->db->runQuery($sql, 'i', $camper);
        } catch (Exception $e) {
            wp_die("Unable to collect friends from the database in findFriendsInCamp() for camper $camper:" . $e->getMessage());
            return false;
        }

        // if there are not any friends, then we're done here
        if (empty($friends)) {
            PluginLogger::log("No Friends Found.");
            return true;
        }

        // Check each to see if they have reservations this week, for this camp
        $sql =   "SELECT pod FROM " . $this->tables->reservations
            . " WHERE person_ucid = ? AND week_number = ? AND camp_name = (
                    SELECT name FROM " . $this->tables->camp_options . " 
                    WHERE templateid = ?)";

        $pods = array(); // quick search for ensuring we don't do duplicates
        PluginLogger::log(count($friends) . " Friends Found, looking for shared camps",  array($friends, $week, $camp));

        foreach ($friends as $f) {
            try {
                $p = $this->db->runQuery($sql, 'iii', array($f['friend_ucid'], $week, $camp));
            } catch (Exception $e) {
                wp_die("Unable to query the DB for friend pods: " . $e->getMessage());
                return false;
            }

            // if we happen to find a friend in this camp, record their pod
            if (!empty($p)) {
                // check to see that the pod isn't already in our list
                if (!array_search($p[0]['pod'], $pods)) {
                    $pods[] = $p[0]['pod'];
                }
            }
        }

        PluginLogger::log("Returning matching Pods", $pods);

        return $pods;
    }

    /**
     * Takes a camperid and a string of friends and attemps to create friend matches in the database for that string
     */
    function saveFriends($camper, $friends)
    {
        PluginLogger::log("Starting saveFriends with camper $camper and friends $friends.");

        // use Friend Finder to tease names out of the string
        $strings = $this->UltracampFriends->findNames($friends);

        // build a quick "WHERE" list for the counts - we're only going to try to campers who have attended in the past two seasons, or are here this season
        $year = date("Y");
        $counts = " (count_$year > 0 OR count_" . $year - 1 . " > 0 OR count_" . $year - 2 . " > 0)";
        $sqlSearch = "SELECT person_ucid, last_name, first_name FROM campers WHERE last_name = ? AND $counts";

        // unlike friend finder, we're not using the find camper API - but instead we're just going to check the database for this camper
        // this is significantly faster (no API to UC), and since the customer is waiting on the result, we don't have time to use the UC API
        // also, our DB is now large enough that we know at least as much as Ultracamp does about our customers at this point (10,709 camper records as I write this on 2/16/23)
        foreach ($strings['matches'] as $name) {

            // split the string apart into distinct words (names)
            $names = explode(" ", trim($name));
            // We're going make sure the array is only three values long, just in case someone types something stupid
            $names = array_slice($names, -3);
            // assume that the last entry is the last name
            $names = array_reverse($names);

            // we'll keep checking names until we get a hit or run out of names to try
            PluginLogger::log("Looking for match by name.", $names);
            unset($match);

            foreach ($names as $k => $n) {
                // ensure that we aren't searching for a middle initial or something
                // also skips when there are no names supplied
                if (strlen($n) < 3) {
                    PluginLogger::log("Skipping the name $n as it is too short.");
                    continue;
                }

                try {
                    $search = $this->db->runQuery($sqlSearch, 's', $n);
                } catch (Exception $e) {
                    wp_die("Unable to query the campers table for friend with last name $n: " . $e->getMessage());
                }

                // check to see if this is a candidate for a match
                // matches should see the first and last name align with the incoming array
                if (!empty($search)) {
                    foreach ($names as $a => $b) {
                        foreach ($search as $dbResult) {
                            if ($a != $k && $dbResult['first_name'] == $b) {
                                $match = $dbResult;
                                PluginLogger::log("Friend Match found. Entry: $name, Result", $match);
                                break 3;
                            }
                        }
                    }
                }
            }


            // if friend finder makes a reaonsable match, save it
            if (!empty($match)) {
                // Friend Finder doesn't have a good method to just drop in vetted entries - so I'm going to make one here - terrible Idea, but I don't want to break Friend Finder

                $sqlDupeCheck = "SELECT id FROM " . $this->tables->friends_table . " WHERE camper_ucid = ? AND friend_ucid = ?";
                try {
                    $dupeCheck = $this->db->runQuery($sqlDupeCheck, 'ii', array($camper, $match['person_ucid']));
                } catch (Exception $e) {
                    wp_die("Unable to dupe-check the firends table: " . $e->getMessage());
                    continue;
                }

                if (!empty($dupeCheck)) {
                    PluginLogger::log("Friend already exists in the database.");
                    continue;
                }

                $sql = "INSERT INTO " . $this->tables->friends_table . " (
                          camper_ucid
                        , friend_ucid
                        , date_added
                        , friend_source
                        , source_data
                        , match_explanation
                ) VALUES ( ?,?,?,?,?,?)";

                $values = array(
                    $camper,
                    $match['person_ucid'],
                    date("Y-m-d H:i:s"),
                    'Camper Queue Entry',
                    'CQ',
                    $name
                );
                $params = 'iissss';


                try {
                    $this->db->insert($sql, $params, $values);
                } catch (Exception $e) {
                    wp_die("Unable to save the friend match: " . $e->getMessage());
                    continue;
                }
            } else {
                // Need to save names that don't come back with a hit (in case they are mis-spelled or something else of the sort)
                PluginLogger::log("Recording low confidence result.", $name);

                $reason = "Unable to match a camper with customer entry in the Camper Queue.";
                if (!$this->FriendFinder->snoozeEarlyFriends($camper, $name, 'Camper Queue Entry', time(), $reason)) {
                    $databaseStatus = false;
                    // $UltracampFriends->logger->warning('SaveFriend->storeError() failed on camper ' . $reservation->PersonId);
                    // $UltracampFriends->logger->d_bug("Failed to Save low confidence error", $name);
                }
            }
        }

        // Need to save errors for processing
        if (count($strings['errors']) > 0) {
            PluginLogger::log("Recording errors resulting from the name finder.", $strings['errors']);

            foreach ($strings['errors'] as $name) {
                // source (i.e. ultracamp)
                $source = 'Camper Queue';
                // source ID (uc reservation id)
                $sourceId = time();
                // reason
                $reason = "Unknown text - unable to convert to names.";

                if (!$this->FriendFinder->storeError($camper, $name, $source, $sourceId, $reason)) {
                    $databaseStatus = false;
                    wp_die('FriendFinder->storeError() failed on camper ' . $camper . ' with name: ' . $name);
                }
            }
        }
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new FriendManagerDummyLogger();
    }
}

class FriendManagerDummyLogger
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
