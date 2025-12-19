<?php

// This class enables the saving of a confirmed friend to the database

class SaveFriend
{

    public $logger;
    protected $db;
    protected $finder;
    public $debug;
    protected $config;
    //protected $Ultracamp;

    function __construct($debug = false)
    {
        $this->debug = $debug;

        // Allow passing in an external logger without breaking the existing (old) logger methodology (2/16/23 (BN) Done for camper queue)
        if (!is_object($debug)) {

            // require_once (__DIR__ .'/../logger/FindFriendLogger.php'); 
            $this->logger = null;

            if ($this->debug) {
                // $this->logger->pushHandler($dbugStream);
            }
        } else {
            $this->setLogger($debug);
        }

        require_once __DIR__ . '/../../db/db-reservations.php';
        $this->db = new reservationsDb($this->logger);

        /*
        require_once __DIR__ .'/../../../../api/ultracamp/ultracamp.php';
        $this->Ultracamp = new UltracampModel($this->logger);
        */

        // pull in the seasonal information such as table names and year
        require_once __DIR__ . '/../../tools/SeasonalConfigElements.php';
        $this->config = new SeasonalConfigElements;
    }

    /**
     * Allows outside callers to set their own logger - this became my standard practice after friend finder was built
     * I've included this here so that this can support it as Camper Queue is using this class
     */
    function setLogger($logger)
    {
        $this->logger = $logger;
        return true;
    }

    // saves the ultracamp matches, with all of the additional information that comes with it
    // I built this first, it works, and rather than refactoring it to take a generic save, 
    // I'm isolating this function to be exclusively this, and will build a generic save method as well
    function save($camperId, $friend, $reservationId, $matchExplanation)
    {

        // takes the camper ID of the account that has made the friend request
        // the friend object from the friend finder

        // don't allow camper and friend to be the same
        if ($camperId == $friend['personId']) {
            return true;
        }

        // check to ensure that we don't already have this pairing
        $sql = "SELECT id FROM " . $this->config->friends_table . " WHERE camper_ucid = ? AND friend_ucid = ? LIMIT 1";
        $values = array($camperId, $friend['personId']);
        try {
            $exists = $this->db->runQuery($sql, 'ii', $values);
        } catch (Exception $e) {
            wp_die("Unable to query the database for existing friend pairs in SaveFriend->save()");
            return false;
        }

        if (isset($exists) && count($exists) > 0) {
            // $this->logger->d_bug("A matching friend pair for " . $camperId . ":" . $friend['personId'] . " already exists.");
            return true;
        }

        // we don't pass this point if there is a duplicate match in the database

        // Store the match into the friends table
        $sql = "INSERT INTO " . $this->config->friends_table . " (
                      camper_ucid
                    , friend_ucid
                    , date_added
                    , friend_source
                    , match_confidence
                    , match_explanation
                    , source_data
            ) VALUES ( ?,?,?,?,?,?,?)";

        $values = array(
            $camperId,
            $friend['personId'],
            date("Y-m-d H:i:s"),
            "Ultracamp",
            $friend['confidence'],
            $matchExplanation,
            $reservationId
        );

        try {
            $this->db->insert($sql, 'iissisi', $values);
        } catch (Exception $e) {
            wp_die("Unable to save the friend match.");
            wp_die($e->getMessage());
            return false;
        }

        // $this->logger->d_bug("A friend pair has been entered into the database: " . $camperId . ":" . $friend['personId']);

        return true;
    }


    function storeError($camper, $name, $source, $sourceId, $reason, $snooze_count = 0, $first_record = NULL)
    {

        if (empty($first_record) || $first_record == 0) {
            $first_record = $sourceId;
            $this->logger->info("storeError was passed a NULL value for origional data - instead, it is using the source Id $sourceId");
        }

        // don't save blank strings
        if (empty($name) || strlen($name) < 2) {
            // $this->logger->d_bug("Abandoning storeError due to blank string: $name");
            return true;
        }

        // need to remove the 'CQ-' from early Camper Queue errors
        if (substr($sourceId, 0, 3) == 'CQ-') {
            $sourceId = substr($sourceId, 2);
        }

        // check to see the date - if we're before May 15th, then we'll just snooze unmatched campers and try again in a week
        $year = date("Y");
        $targetDate = strtotime("May 15 $year");

        // only send to snooze if the reason for the error is a low confidence in match
        $lowConfidenceMsg = "Low confidence in account match";

        if (strtotime("now") < $targetDate && $lowConfidenceMsg == substr($reason, 0, 31)) {
            return $this->snoozeEarlyFriends($camper, $name, $source, $sourceId, $reason, $snooze_count, $first_record);
        }

        // duplicates are a thing, so we're not going to save multiples
        $sql = "SELECT id FROM " . $this->config->conflictTable . " WHERE camper_ucid = ? AND conflict_text = ?";
        try {
            $dupes = $this->db->runQuery($sql, 'is', array($camper, $name));
        } catch (Exception $e) {
            wp_die("Unable to select ID from storeError " . $this->config->conflictTable . "in SaveFriend. " . $e->getMessage());
            wp_die($sql, array($camper, $name));
            return false;
        }

        if (isset($dupes) && count($dupes) > 0) {
            // $this->logger->d_bug("Duplicate error found in storeError. Skipping the database.");
            return true;
        }

        // error log review shows an issue here where we are passing in a null for source_id and it's failing to save as a result
        if (empty($sourceId)) {
            PluginLogger::log("SourceID is Null for storeError() in SaveFriends.php. The SQL will reject this. Setting sourceId to 0 as an interim solution. This should not be possible, but somehow has happened.", array('sourceId' => $sourceId, 'firstRecord' => $first_record, 'reason' => $reason));

            $sourceId = 0;
        }


        $sql = "INSERT INTO " . $this->config->conflictTable . " (
                              camper_ucid
                            , conflict_text
                            , source
                            , source_id
                            , reason
                            , snooze_count
                            , first_record) VALUES (?,?,?,?,?,?,?)";
        $values = array($camper, $name, $source, $sourceId, $reason, $snooze_count, $first_record);
        try {
            $this->db->insert($sql, 'issssii', $values);
        } catch (Exception $e) {
            wp_die("Unable to storeError in SaveFriend. " . $e->getMessage());
            wp_die($sql, $values);
            return false;
        }

        // $this->logger->d_bug("Unable to match a name. Error state stored in the databse: ", $values);

        return true;
    }

    // takes an INT camperID, and IN friend person Id, and a source and stores them in the friends table in the DB
    // also looks for -admin and -avoid attached to friends in order to set the right flag
    // checking first for duplicates
    function adminSave($camper, $friend, $source, $adminUser, $conflictId = NULL, $conflictText = NULL)
    {

        // check for flags - currently only supports one flag, and looks only for admin or avoid
        $input = explode('-', $friend, 2);
        $friend = $input[0];

        if (!is_numeric($friend)) {
            wp_die("Invalid friend ID encountered. It is not numeric: $friend");
            return false;
        }

        // don't allow camper and friend to be the same
        if ($camper == $friend) {
            return true;
        }

        // check to ensure that we don't already have this pairing
        $sql = "SELECT id FROM " . $this->config->friends_table . " WHERE camper_ucid = ? AND friend_ucid = ? LIMIT 1";
        $values = array($camper, $friend);
        try {
            $exists = $this->db->runQuery($sql, 'ii', $values);
        } catch (Exception $e) {
            wp_die("Unable to query the database for existing friend pairs in SaveFriend->save()");
            return false;
        }

        if (isset($exists) && count($exists) > 0) {
            // $this->logger->d_bug("A matching friend pair for " . $camper . ":" . $friend . " already exists.");
            return true;
        }

        // source_data for the admin tool can hold a lot of info, so we're going to JSON this thing
        $explanation = is_numeric($conflictId) ? json_encode(array('conflictId' => $conflictId, 'conflictText' => $conflictText)) : null;

        // we don't pass this point if there is a duplicate match in the database

        // if there are flags coming in, we're going to set the SQL here
        if (isset($input[1])) {
            // avoid flag - also sets admin as they are mutually inclusive
            if ($input[1] == 'avoid') {
                $sql = "INSERT INTO " . $this->config->friends_table . " (
                    camper_ucid
                    , friend_ucid
                    , date_added
                    , friend_source
                    , admin_only
                    , avoid_contact
                    , source_data
                    , match_explanation
            ) VALUES ( ?,?,?,?,?,?,?,?)";

                $values = array(
                    $camper,
                    $friend,
                    date("Y-m-d H:i:s"),
                    $source,
                    1,
                    1,
                    $adminUser,
                    $explanation
                );

                $params = 'iissiiss';
            }

            // admin only flag
            if ($input[1] == 'admin') {
                $sql = "INSERT INTO " . $this->config->friends_table . " (
                    camper_ucid
                    , friend_ucid
                    , date_added
                    , friend_source
                    , admin_only
                    , source_data
                    , match_explanation
            ) VALUES ( ?,?,?,?,?,?,?)";

                $values = array(
                    $camper,
                    $friend,
                    date("Y-m-d H:i:s"),
                    $source,
                    1,
                    $adminUser,
                    $explanation
                );

                $params = 'iississ';
            }
        } else {

            // Store the match into the friends table without flags
            $sql = "INSERT INTO " . $this->config->friends_table . " (
                        camper_ucid
                        , friend_ucid
                        , date_added
                        , friend_source
                        , source_data
                        , match_explanation
                ) VALUES ( ?,?,?,?,?,?)";

            $values = array(
                $camper,
                $friend,
                date("Y-m-d H:i:s"),
                $source,
                $adminUser,
                $explanation
            );
            $params = 'iissss';
        }

        try {
            $this->db->insert($sql, $params, $values);
        } catch (Exception $e) {
            wp_die("Unable to save the friend match.");
            wp_die($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Built 1/4/2023
     * Friend finder was turned on very early this year (yay me) but it means that many friends are not in the system yet
     * To deal with this, instead of just marking non-matches as errors, we're going to snooze one week at a time until May 15th
     */
    function snoozeEarlyFriends($camper, $name, $source, $sourceId, $reason, $snooze_count = 0, $first_record = NULL)
    {
        // create a new row in the ultracamp queue with a snooze date
        $snooze = date('Y-m-d', strtotime('+1 week'));
        $today = date('Y-m-d');

        // $this->logger->d_bug("Marking UC ID $sourceId as snoozed due to early season no match.", array($camper, $name, $source, $sourceId, $reason, $snooze_count, $first_record));

        // 3/13/23 - found that this was injecting a host of duplicated rows because it wasn't parking the previous ones as processed.
        // Therefore, dupe check logic now exists
        $sqlDupe = "SELECT id FROM " . $this->config->ultracampTable
            . " WHERE person_ucid = ? 
                        AND friend_data = ? 
                        AND snoozed_until >= ? 
                        AND processed IS NULL";

        try {
            $dupeCheck = $this->db->runQuery($sqlDupe, 'iss', array($camper, $name, $today));
        } catch (Exception $e) {
            wp_die("Unable to dupe check in snoozeEarlyFriends(): " . $e->getMessage());
            return false;
        }


        // if there is any result, then we're not going to process the insert
        if (!$dupeCheck) {

            $sqlInsert = "INSERT INTO " . $this->config->ultracampTable . " (reservation_id, person_ucid, friend_data, snoozed_until, snooze_count, first_record) VALUES (?,?,?,?,?,?)";
            $values = array($sourceId, $camper, $name, $snooze, $snooze_count + 1, $first_record);

            // $this->logger->d_bug("Snooze Early Friends is adding a new row.", $values);

            try {
                $this->db->insert($sqlInsert, 'iissii', $values);
            } catch (Exception $e) {
                wp_die("Error in snoozeEarlyFriends()", array('error' => 'Unable to insert the snoozed row into the database. ' . $e->getMessage()));
                return false;
            }
        } else {
            // $this->logger->d_bug("snoozeEarlyFriends dupe check returned " . count($dupeCheck) . " results.");
        }

        return true;
    }

    /**
     * Built 3/8/2023
     * To allow for updateAndSnooze access to the database, I'm putting it's conflict ID look up here so I don't have to include the DB files in that Ajax page
     */
    function getConflict($conflictId)
    {

        $sql = "SELECT source_id, snooze_count, first_record, camper_ucid FROM " . $this->config->conflictTable . " WHERE id = ?";
        try {
            $result = $this->db->runQuery($sql, 'i', $conflictId);
        } catch (Exception $e) {
            wp_die("Unable to lookup a conflict ID in classes/SaveFriend.php: " . $e->getMessage());
            return false;
        }

        return $result[0];
    }
}
