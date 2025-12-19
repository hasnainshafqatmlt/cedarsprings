<?php

/**
 *     Determines the best transportation option for inclusion in the shopping cart
 *     2/9/23 (BN)
 */

date_default_timezone_set('America/Los_Angeles');

class TransportationPods
{

    public $logger;
    protected $db;
    protected $tables;

    protected $uc;
    protected $production;
    protected $CQModel;

    protected $podChoices = array();
    private $podRanking = array();
    protected $entries;

    protected $FriendManager;

    protected $friendlySiblings = false;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/FriendManager.php';

        require_once __DIR__ . '/../db/db-reservations.php';
        require_once __DIR__ . '/../includes/ultracamp.php';

        // Deals with all of the work surrounding Friends and Friend Finder
        $this->FriendManager = new FriendManager($logger);

        $this->db = new reservationsDb($logger);
        $this->tables = new CQConfig;

        $this->db->setLogger($this->logger);
        $this->uc = new UltracampModel($this->logger);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }

        if ($this->production) {
            PluginLogger::log("Pod Builder is Operating in production!");
        } else {
            PluginLogger::log("Pod Builder is Operating in Dev Mode");
        }
    }

    /**
     * Takes an object of the CQModel, including its utility functions and the array of campers for this transaction
     */
    function setCQModel($obj)
    {
        $this->CQModel = $obj;
        return true;
    }

    /**
     * Takes the full list of entries and assigns a pod to each entry
     */
    function processBuses($entries, $primary, $exceptions = null)
    {

        // don't completly break if we don't have a choice coming in - just don't assign a transportation option and let Proofreader deal with it later
        if (empty($primary) || $primary == 'none') {
            PluginLogger::log("No transportation choice was provided to TransportationPods->processBuses().");
            return false;
        }

        // Need to build a collection of all of the pod options and see if we can align multiple ones across campers
        foreach ($entries as $entry) {
            $i = explode('-', $entry);

            // If there is an action, we'll ensure it isn't Q (no need to do the work) and the remove the action element
            if (!is_numeric($i[0])) {
                if ($i[0] == 'Q' || $i[2] == 9999) {
                    continue;
                }

                array_shift($i);
            }

            $camper = $i[0];
            $camp = $i[1];
            $week = $i[2];

            // load the current entry into the podRanking Array
            $this->podRanking[$entry]['camper'] = $camper;
            $this->podRanking[$entry]['week'] = $week;

            // load the transportation choices
            if (!is_array($exceptions) || empty($exceptions[$week])) {
                $t = explode('-', $primary);

                $this->podRanking[$entry]['transportation'] = $t[0];
                if (!empty($t[1])) {
                    $this->podRanking[$entry]['extCare'] = $t[1];
                }
            } else {
                PluginLogger::log("Setting exception transportation for week $week.", $exceptions[$week]);
                $t = explode('-', $exceptions[$week]);
                if (!empty($t[1])) {
                    $this->podRanking[$entry]['extCare'] = $t[1];
                }
                $this->podRanking[$entry]['transportation'] = $t[0];
            }

            PluginLogger::log("Entry $entry has become", $this->podRanking[$entry]);

            // load all of the possible pods for this camper
            $this->podRanking[$entry]['pods'] = $this->listAvailablePods($camper, $this->podRanking[$entry]['transportation'], $week);
        }

        // with each entry in the system and the potential pods listed, we need to start collecting information about which we want to choose
        // we're first going to find out which pods contain friends
        foreach ($this->podRanking as &$entry) {
            $friendPods = $this->FriendManager->findFriendsInCamp($entry['camper'], $entry['transportation'], $entry['week']);

            // if we have a friend match, give matching pods preference - IF those friend pods are in the list of available pods for the camper
            if (!empty($friendPods) && is_array($friendPods)) {

                foreach ($friendPods as $f) {
                    foreach ($entry['pods'] as $k => $p) {

                        if ($p['name'] == $f) {

                            $entry['pods'][$k]['friends'] =
                                (!empty($entry['pods'][$k]['friends'])) ?
                                $entry['pods'][$k]['friends'] + 1 : 1; // count the number of friends in the pod

                        }
                    }
                }
            }
        }

        // For buses - friendly sibling is always a thing
        PluginLogger::log("Checking friendly sibling pod matches.");
        // itterate through the pods, record the number of siblings who would also be looking at this pod



        // Itterate through all of the entries
        foreach ($this->podRanking as $a => $record) {

            PluginLogger::log("Looking through the camps for camper " . $this->CQModel->campers[$record['camper']]['firstName']);

            // If there is an issue with available capacity, or an error, let's not spring up a bunch of warnings
            if (empty($record['pods'])) {
                PluginLogger::log("There are no available pods for transportation option " . $record['transportation'] . ", on entry $a.");
                continue;
            }

            // itterate through the record's pods
            foreach ($record['pods'] as $p) {

                // loop again through the full list, looking for matching pods among the siblings
                foreach ($this->podRanking as $b => $sibling) {

                    // if we're looking at ourselves, move on
                    if ($record['camper'] == $sibling['camper']) {
                        continue;
                    }
                    // only compare entries of the same week
                    if ($record['week'] != $sibling['week']) {
                        continue;
                    }

                    // look through the siblings pods and mark as a match if found
                    foreach ($sibling['pods'] as $c => $sibPod) {

                        if ($sibPod['name'] == $p['name']) {
                            PluginLogger::log("Adding Sibling Pod: " . $sibPod['name']);
                            $this->podRanking[$b]['pods'][$c]['sibling_pod'][] = $this->podRanking[$a]['camper'];
                        }
                    }
                }
            }
        }

        // now that we have friends, siblings, capacity, and age all sorted, pick the best pod for each camp
        // ensure that if we're using sibling pods, the siblings land in the same pod, assuming there is capacity
        foreach ($this->podRanking as $k => $v) {

            // If there is an issue with available capacity, or an error, let's not spring up a bunch of warnings
            if (empty($v['pods'])) {
                continue;
            }

            // let's get a count of the highest number of sibling pods and friend so we know what "perfect" looks like
            // a perfect pod is one that has the most friends and has room for all of the siblings
            $maxSiblings = 0;
            $maxFriends = 0;
            foreach ($v['pods'] as $pod) {
                if (!empty($pod['sibling_pod']) && count($pod['sibling_pod']) > $maxSiblings) {
                    $maxSiblings = count($pod['sibling_pod']);
                }

                if (!empty($pod['friends']) && $pod['friends'] > $maxFriends) {
                    $maxFriends = $pod['friends'];
                }
            }

            // save this information in the entry so we can use it as our test of a good pod candidate
            PluginLogger::log("For camper " . $this->CQModel->campers[$v['camper']]['firstName'] . ", maxFriends = $maxFriends and maxSiblings = $maxSiblings.");
            $this->podRanking[$k]['maxFriends'] = $maxFriends;
            $this->podRanking[$k]['maxSiblings'] = $maxSiblings;
        }

        // Now that we know what the best option looks like (room for all siblings and includes all friends),
        // see if we can find a pod that meets the criteria

        foreach ($this->podRanking as $k => $v) {
            // if there is already a pod choice (from a sibling pod), move on
            if (!empty($v['podChoice'])) {
                PluginLogger::log("Skipping entry $k due to existing pod choice", $v['podChoice']);
                continue;
            }

            // if there are no friends to consider and no siblings trying to get into the same camp, then just pick the first pod in the list
            // remember that to be on the list, it already has been confirmed to have capacity for 1 and be of the right age group
            if ($v['maxFriends'] == 0 && $v['maxSiblings'] == 0) {
                $this->podRanking[$k]['podChoice'] = $v['pods'][0];
                PluginLogger::log("Setting pod " . $v['pods'][0]['name'] . " for camper " . $this->CQModel->campers[$v['camper']]['firstName'] . " with no friend or sibling considerations.", $k);

                // we need to mark the capacity of the chosen pod down by one so that we don't keep trying to put campers into it
                // we also need to remove it from the "availble" list for any campers if it goes to zero
                $this->reducePodCapacity($v['pods'][0]['templateid'], $v['week']);

                continue; // we've got a pod, move to the next entry
            }

            // next easiest is to find campers with friend considerations, but no sibling concerns
            if ($v['maxFriends'] > 0 && $v['maxSiblings'] == 0) {
                //go with the pod that has the most friends
                foreach ($v['pods'] as $pod) {
                    // find the first pod that has the same max friend count that we encountered when we checked all of the friend counts
                    if ($pod['friends'] == $v['maxFriends']) {
                        $this->podRanking[$k]['podChoice'] = $pod;
                        PluginLogger::log("Setting pod " . $pod['name'] . " for camper " . $this->CQModel->campers[$v['camper']]['firstName'] . " with " . $v['maxFriends'] . " friend and no siblings.", $k);

                        // we need to mark the capacity of the chosen pod down by one so that we don't keep trying to put campers into it
                        // we also need to remove it from the "availble" list for any campers if it goes to zero
                        $this->reducePodCapacity($pod['templateid'], $v['week']);

                        break; // we've got a pod, we can stop looking
                    }
                }
                continue; // we've got a pod, move to the next entry
            }

            // siblings with friends are tricky
            // I have friends and capacities that I have to ensure are correct before I can pick a pod
            $tempRanking = array(); // holds potential candidates that all siblings may use
            foreach ($v['pods'] as $pod) {

                // do we have any options where all the siblings can be together?
                // If so, use the one that has the highest friend count potential

                // if not remove the sibling requirement all together and just place people.

                // add pods with capacity to consideration - include the friend count
                $siblingCount = empty($pod['sibling_pod']) ? 0 : count($pod['sibling_pod']); // if there aren't any, then it's just undefined
                if (
                    $siblingCount == $v['maxSiblings'] &&
                    $pod['capacity'] >= $v['maxSiblings'] + 1
                ) {

                    // convert undefined to zero, or store the number of friends in this pod
                    $friendCount = empty($pod['friends']) ? 0 : $pod['friends'];

                    // save the friend count to the temporary array so that we can compare total friend counts across campers later
                    if (empty($tempRanking[$pod['templateid']]['friendCount'])) {
                        $tempRanking[$pod['templateid']] = $friendCount;
                    } else {
                        $tempRanking[$pod['templateid']] += $friendCount;
                    }

                    // loop through the siblings and add their friend counts to the temporary array
                    foreach ($tempRanking as $tempid => $tempCount) {
                        // check each sibling
                        foreach ($pod['sibling_pod'] as $siblingId) {
                            $sibEntry = $this->findEntryByCamper($siblingId, $v['week']);
                            foreach ($this->podRanking[$sibEntry]['pods'] as $sibPod) {
                                if ($sibPod['templateid'] == $tempid) {
                                    $tempCount += empty($sibPod['friends']) ? 0 : $sibPod['friends'];
                                }
                            }
                        }
                    }
                }
            }

            // if there are any options available in the tempRanking - it means that we have an option that will hold all of the siblings
            // use the pod which has the highest number of mathching friends (friend count is a sum of friends from all the siblings).
            if (!empty($tempRanking)) {
                // sort the array by the highest value, and then pick the first in the list as the pod for all siblings
                arsort($tempRanking);
                $podId = array_key_first($tempRanking);

                PluginLogger::log("Chosen Pod for siblings: $podId", $tempRanking);

                // loop through the pods until we find this one, and then set it as the pod choice for the current camper and all associated siblings
                foreach ($v['pods'] as $p) {
                    if ($p['templateid'] == $podId) {
                        $chosenPod = $p;
                        break; // found it - get out of the loop
                    }
                }

                $this->podRanking[$k]['podChoice'] = $chosenPod;
                PluginLogger::log("Setting pod " . $chosenPod['name'] . " for camper " . $this->CQModel->campers[$v['camper']]['firstName'] . " with " . $v['maxFriends'] . " friend and " . $v['maxSiblings'] . " sibling match.", $k);

                $this->reducePodCapacity($chosenPod['templateid'], $v['week']);

                // loop through the siblings and add the same pod
                foreach ($chosenPod['sibling_pod'] as $siblingId) {
                    PluginLogger::log("Searching for sibling $siblingId, week $week to add sibling pod choice", $chosenPod);
                    $sibEntry = $this->findEntryByCamper($siblingId, $v['week']);

                    // removing data from sibling entry, such as invalid friend count and irrelevant sibling link
                    $this->podRanking[$sibEntry]['podChoice'] = array('templateid' => $chosenPod['templateid'], 'name' => $chosenPod['name']);

                    PluginLogger::log("Setting pod " . $chosenPod['name'] . " for camper " . $this->CQModel->campers[$siblingId]['firstName'] . " on account of a sibling match.", $k);

                    $this->reducePodCapacity($chosenPod['templateid'], $v['week']);
                }

                // we've got pods
                continue;
            }

            // if tempranking is empty, then there are not any pods big enough to hold all of the siblings - we'll just give up on siblings and go with friends
            // -- this is copy/pasta from above
            foreach ($v['pods'] as $pod) {
                if (empty($pod['friends']) || $pod['friends'] == $v['maxFriends']) {
                    $this->podRanking[$k]['podChoice'] = $pod;
                    PluginLogger::log("Setting pod " . $pod['name'] . " for camper " . $this->CQModel->campers[$v['camper']]['firstName'] . " with " . $v['maxFriends'] . " friends. Sibling considerations were ignored on account of pod capacities.", $k);

                    $this->reducePodCapacity($pod['templateid'], $v['week']);

                    break; // we've got a pod, we can stop looking
                }
            }

            continue; // we've got a pod, move to the next entry
        }



        PluginLogger::log("Pod Ranking", $this->podRanking);

        return $this->podRanking;
    }

    // Takes a camper ID and week and returns the matching entry ID
    function findEntryByCamper($camperId, $weekNum)
    {
        foreach ($this->podRanking as $entry => $values) {
            if ($values['camper'] == $camperId && $values['week'] == $weekNum) {
                return $entry;
            }
        }
    }

    /**
     * Takes the elements of an entry and returns the pods that are valid options
     * it does not take into account any elements other than camper age and capacity for the single camper
     */
    function listAvailablePods($camper, $transportation, $week)
    {

        // Options are paired with their camps by name, so we need to get that
        $transportationName = $this->CQModel->getCamp($transportation);

        PluginLogger::log("ID: $transportation, Name: $transportationName");

        // Due to a lack of foresite - I don't have any logical system to the parent / child relationship for buses - very annoying
        switch ($transportationName) {
            case 'Bothell Early Bus':
            case 'Bothell Bus Stop':
                // Bothell Buses 1 through 3
                $sql = "SELECT templateid, name FROM " . $this->tables->camp_options .
                    " WHERE name IN ('Bothell Bus 1', 'Bothell Bus 2', 'Bothell Bus 3')";
                try {
                    $result = $this->db->runBaseQuery($sql);
                } catch (Exception $e) {
                    PluginLogger::log("Unable to list the CPC HS Buses: " . $e->getMessage());
                    return false;
                }
                break;

            case 'Window A':
            case 'Window B':
                // Lake Stevens Wndows
                $sql = "SELECT templateid, name FROM " . $this->tables->camp_options .
                    " WHERE parent = ?";
                try {
                    $result = $this->db->runQuery($sql, 's', $transportationName);
                } catch (Exception $e) {
                    PluginLogger::log("Unable to list the LKS Drop Off Zones for $transportationName: " . $e->getMessage());
                    return false;
                }
                break;

            default:
                // remaining bus options
                $sql = "SELECT templateid, name FROM " . $this->tables->camp_options .
                    " WHERE parent = ?";
                try {
                    $result = $this->db->runQuery($sql, 's', substr($transportationName, 0, -9));
                } catch (Exception $e) {
                    PluginLogger::log("Unable to list buses $transportationName: " . $e->getMessage());
                    return false;
                }
        }



        // if we don't get any pods back, something is wrong, and we're done here
        if (empty($result)) {
            PluginLogger::log("Unable to find any available transportation pods for transportation $transportationName.");
            PluginLogger::log($sql, substr($transportationName, 0, -9));
            return false;
        }

        // finally, we need to return any that have capacity
        $cap = ($week < 10) ? "cap0" . $week : "cap" . $week;
        $reg = ($week < 10) ? "reg0" . $week : "reg" . $week;
        $sql = "SELECT $cap AS capacity, $reg AS registered FROM " . $this->tables->report_summer . " WHERE ucid = ?";
        foreach ($result as $r) {
            try {
                $capResults = $this->db->runQuery($sql, 'i', $r['templateid']);
            } catch (Exception $e) {
                PluginLogger::log("Unable to list the capacity of pod " . $r['name'] . ' :' . $e->getMessage());
                continue;
            }

            if (!empty($capResults)) {
                $capacity = $capResults[0]['capacity'] - $capResults[0]['registered'];
                if ($capacity > 0) {
                    $availablePods[] = array('templateid' => $r['templateid'], 'name' => $r['name'], 'capacity' => $capacity);
                }
            }
        }

        return $availablePods;
    }

    /**
     * Takes a pod templateId and a week number and itterates through the full podPreference array and updates the matching pod information
     * This is important so that we are updating the pod capacities in real-time as we assign campers to them. Otherwise, we'll singly assign
     * campers to the same pod beyond it's actual capacity
     */
    function reducePodCapacity($templateId, $week)
    {
        PluginLogger::log("Starting ReducePodCapacity($templateId, $week).");
        // Itterate through the entries where a pod choice hasn't been made.
        // Update the capacity everywhere the template is found, removing the entry whereever it goes to zero 
        foreach ($this->podRanking as $key => $entry) {

            if ($entry['week'] != $week) {
                continue;
            } // we're only looking for the current week

            if (empty($entry['pods'])) {
                continue;
            } // if there are not any pods available, don't bother

            if (!empty($entry['podChoice'])) {
                PluginLogger::log("Skipping cap reduction due to already set podChoice for $key.", $entry['podChoice']);
                continue;
            } // no need to mess with those which already have a pod choice

            // loop through the pods and find this one
            foreach ($entry['pods'] as $podId => $pods) {
                if ($pods['templateid'] == $templateId) {
                    // determine if we're reducing the capacity, or removing it from the array
                    //                    PluginLogger::log("Key: $key, podId: $podId");
                    if ($pods['capacity'] == 1) {
                        // remove the entry
                        PluginLogger::log("Removing full pod", $this->podRanking[$key]['pods'][$podId]);
                        unset($this->podRanking[$key]['pods'][$podId]);
                    } else {
                        PluginLogger::log("Reducing the capacity of a pod", $this->podRanking[$key]['pods'][$podId]);
                        $this->podRanking[$key]['pods'][$podId]['capacity']--;
                    }
                }
            }
        }
    }

    /**
     * Takes an entry ID and returns the chosen pod for that row - null of there isn't one
     */
    function getPod($entry)
    {
        // if there was an error - don't return anything
        if (empty($this->podRanking[$entry])) {
            return null;
        }

        return $this->podRanking[$entry]['podChoice'];
    }

    /**
     * We sort which transportation option is correct in here too - so the cart needs that info in order to know
     * which option to include each week
     */
    function getTransportation($entry)
    {
        // if there was an error - don't return anything
        if (empty($this->podRanking[$entry])) {
            return null;
        }

        return array("templateid" => $this->podRanking[$entry]['transportation'], 'name' => $this->CQModel->getCamp($this->podRanking[$entry]['transportation']));
    }

    /**
     * Returns the extended care info for the chosen transportation
     */

    function getExtendedCare($entry)
    {
        return empty($this->podRanking[$entry]['extCare']) ? null : $this->podRanking[$entry]['extCare'];
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new TransportationPodsDummyLogger();
    }
}

class TransportationPodsDummyLogger
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
