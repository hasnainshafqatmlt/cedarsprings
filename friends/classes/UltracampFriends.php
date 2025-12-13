<?php

// the object that manages the work for looking up ultracamp records, matching them with accounts, and then saving them to the database


class UltracampFriends
{

    // Various objects that we're going to work with
    public      $logger;
    protected   $db;
    public      $finder;
    public      $record;
    protected   $config;
    protected   $debug;

    function __construct($debug = false)
    {

        // Allow passing in an external logger without breaking the existing (old) logger methodology (2/16/23 (BN) Done for camper queue)
        if (!is_object($debug)) {
            // require_once (__DIR__ .'/../logger/logger.php'); 
            $this->logger = '';
            $this->debug = $debug;

            // look for a debug flag. If it exists, turn on the FirePHPHandler for viewing debug info in the browswer
            if ($this->debug) {
                // $this->logger->pushHandler($dbugStream);
            }
        } else {
            $this->setLogger($debug);
        }

        require_once __DIR__ . '/../../db/db-reservations.php';
        $this->db = new reservationsDb($this->logger);

        require_once __DIR__ . '/../../api/FindPerson/php/FindPerson.php';
        $this->finder = new FindPerson(false);

        // pull in the seasonal information such as table names and year
        require_once __DIR__ . '/../../tools/SeasonalConfigElements.php';
        $this->config = new SeasonalConfigElements;

        // $this->logger->debug("---------------------------------------------- -");
        // $this->logger->debug("--  UltracampFriends() has been initialized -- -");
        // $this->logger->debug("---------------------------------------------- -");
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

    // loads the specified number of records from the database. Will only return those that need processing
    function retrieveRecord($qty = 1)
    {

        // build the query
        $sql = "SELECT * FROM " . $this->config->ultracampTable . " WHERE processed IS NULL AND (snoozed_until < CURRENT_DATE OR snoozed_until IS NULL) LIMIT " . $qty;
        try {
            $result = $this->db->runBaseQuery($sql);
        } catch (Exception $e) {
            $this->logger->error("Unable to query the database for ultracamp friend data.");
            return false;
        }

        $this->record = isset($result) ? $result : NULL;
        return isset($result) ? $result : false;
    }

    function findNames($data)
    {
        //        $this->logger->d_bug("findNames sees", $data);
        $result = array('matches' => array(), 'errors' => array());

        // remove anything in parenthese
        $data = trim(preg_replace('/\([^)]*\)/', '', $data));

        // now, remove any remaining parenthese - this happens on snoozed names
        $data = trim(preg_replace('/([()])/', '', $data));

        // Check first to see if we have a single name
        // This should looke like "Firstname Lastname"

        $pattern = '/^[a-zA-Z\-]{2,20}\s[a-zA-Z\-]{2,20}$/';
        if (preg_match($pattern, trim($data))) {
            $result['matches'][] = trim($data);
            return $result;
        }

        // found a couple of times where there are too many spaces - need to fix that too
        $data =  preg_replace('/\s{2,}/', ' ', $data);

        // try to remove 'and' and '&', and replace with ', ';
        $data = preg_replace('/\s*(&|\band\b|[,;])\s*/', ', ', $data);

        // next, look for names seperated by a comma
        $csv = explode(", ", $data);
        if (count($csv) > 0) {
            foreach ($csv as $name) {
                if (preg_match($pattern, trim($name))) {
                    $result['matches'][] = trim($name);
                } else {
                    $result['errors'][] = trim($name);
                }
            }

            return $result;
        }

        if (strlen(trim($data)) > 1) {
            $result['errors'][] = trim($data);
        }


        return $result;
    }

    // marks an ultracamp_friend_request as processed
    function markProcessed($record)
    {
        $sqlDate = date("Y-m-d H:i:s"); // don't mark records future snoozed as processed

        if (!empty($record['reservation_id'])) {
            $sql = "UPDATE " . $this->config->ultracampTable . " SET processed = 1 WHERE reservation_id = ? AND (snoozed_until IS NULL OR snoozed_until < ?)";
            $values = array($record['reservation_id'], $sqlDate);
            // $this->logger->d_bug("Marking reservation ID " . $record['reservation_id'] . " as processed with snoozed date less than $sqlDate.");
        } else {
            $sql = "UPDATE " . $this->config->ultracampTable . " SET processed = 1 WHERE id = ? AND (snoozed_until IS NULL OR snoozed_until < ?)";
            $values = array($record['id'], $sqlDate);
            // $this->logger->d_bug("Marking ultracamp_friend_request id " . $record['id'] . " as processed with snoozed date less than $sqlDate.");
        }

        try {
            $this->db->update($sql, 'is', $values);
        } catch (Exception $e) {
            wp_die("Unable to mark a record as processed. " . $e->getMessage());
            // $this->logger->d_bug("Sql Error: $sql", $record['reservation_id']);
            return false;
        }

        return true;
    }
}
