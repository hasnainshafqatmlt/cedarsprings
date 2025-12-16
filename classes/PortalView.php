<?php

/**
 *    Manages the creation of the HTML elements for the portal page
 */

date_default_timezone_set('America/Los_Angeles');

class PortalView
{

    public $logger;

    protected $model;
    public $activeQueues;
    public $accountId;
    public $activeQueueCount;
    protected $CQModel;
    private $imageStore;
    protected $pendingQueues;

    function __construct($logger = null)
    {
        // $this->setLogger($logger);

        require_once 'PortalModel.php';
        $this->model = new PortalModel($this->logger);

        require_once 'CQModel.php';
        $this->CQModel = new CQModel($logger);

        // load the queues
        $this->getActiveQueues();

        // count the queues
        $this->countQueues();

        PluginLogger::log("debug:: Active Queues ", $this->activeQueueCount);
        if ($this->activeQueueCount > 0) {
            PluginLogger::log("debug:: Queues", $this->activeQueues);
        }

        // load pending queues
        $this->getPendingQueues();
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new PortalViewDummyLogger();
    }

    // set the current account ID
    function setAccountId($id = null)
    {
        // we'll take it explicitly if sent, otherwise, check for it in the cookie
        if (is_numeric($id)) {
            $this->accountId = $id;
            return $this->accountId;
        }

        if (is_numeric($_COOKIE['account'])) {
            $this->accountId = $_COOKIE['account'];
            return $this->accountId;
        }
    }


    // Load the active queues, if they exist
    function getActiveQueues()
    {

        // let's ensure that the account Id is present
        if (!$this->accountId) {
            $account = $this->setAccountId();
            if (!$account) {
                PluginLogger::log("error:: Unable to get active queues without an account ID.");
                return false;
            }
        }

        $this->activeQueues = $this->model->getActiveQueues($this->accountId);
    }

    // Count the number of active queues present for the account
    function countQueues()
    {
        if (empty($this->activeQueues)) {
            return 0;
        }

        $this->activeQueueCount = count($this->activeQueues);
        return $this->activeQueueCount;
    }

    // create the HTML for the active queue check boxes and other related dynamic content
    function createActiveQueueHTML()
    {
        if ($this->activeQueueCount == 0) {
            return false;
        }

        /*
            <div class='listingCheckboxContainer'><input type='checkbox' /></div>
            <div>
                <p class="activeRow"><span style="font-weight: bold;">Max:</span> July 7-21: Adventure</p>
                <p class="expiryRow">Available until March 25th at 12:00 PM</p>
            </div>
            */

        $html = '';
        foreach ($this->activeQueues as $Q) {

            // build the queue entry string
            $entryElements = array('A', $Q['camperId'], $Q['campId'], $Q['week_num']);
            $entry = implode('-', $entryElements);

            // used for JS IDs - is the entry, but without the action
            $btnId = implode('-', array($Q['camperId'], $Q['campId'], $Q['week_num']));

            // build the DIV that holds the checkbox
            $html .= "<div class='listingCheckboxContainer' id='chkcontainer-" . $btnId . "'>";
            // create the checkbox itself
            $html .= "<input type='checkbox' name='$entry' id='$entry' value='$entry' />";
            $html .= "</div>";

            // create a basic div for the text - this div interacts with the grid layout of the container
            $html .= '<div id="activetextbox-' . $btnId . '">';
            // Camper's name, week string, and camp name
            $html .= '<p class="activeRow"><span style="font-weight: bold;">';
            $html .= $this->CQModel->getCamperName($Q['camperId'])['FirstName'];
            $html .= ':</span> ';
            $html .= $this->CQModel->getWeek($Q['week_num']);
            $html .= ': ';
            $html .= $this->CQModel->getCamp($Q['campId']);
            $html .= "</p>\n";

            // Expiry Date
            $html .= '<p class="expiryRow">Available until ';
            $html .= date("F j<\s\u\p><\u>S</\u></\s\u\p>", strtotime($Q['active_expire_date'])) . ' at ' . date("g:i A", strtotime($Q['active_expire_date']));

            // Snooze Button
            $btnId = implode('-', array($Q['camperId'], $Q['campId'], $Q['week_num']));
            $btnMsg = json_encode(array('id' => $btnId, 'camper' => $this->CQModel->getCamperName($Q['camperId'])['FirstName'], 'week' => $this->CQModel->getWeek($Q['week_num']), 'camp' => $this->CQModel->getCamp($Q['campId'])));
            $html .= "<br /><a href=# data-toggle='modal' data-target='#myModal' data-action='snooze' data-msg='$btnMsg'>Snooze Queue</a>";

            $html .= "</p>\n";
            $html .= "</div>\n";
        }

        return $html;
    }

    // Load the pending queues, if they exist
    function getPendingQueues()
    {

        // let's ensure that the account Id is present
        if (!$this->accountId) {
            $account = $this->setAccountId();
            if (!$account) {
                PluginLogger::log("error:: Unable to get pending queues without an account ID.");
                return false;
            }
        }

        $this->pendingQueues = $this->model->getPendingQueues($this->accountId);

        return true;
    }

    // takes the pending queue info and creates the HTML for the website
    function createPendingQueueHTML()
    {
        $html = '';

        if (empty($this->pendingQueues)) {
            // post a 'no camers here' message
            $html .= '<div class="tw-bg-[#FFF8F0] tw-text-center tw-mt-4 tw-px-6 tw-py-4 tw-rounded-xl">';
            $html .= '<div style="text-align:center; width:100%"" class=" tw-mt-4"><p class="description instructions" style="color:white">No one listed on your account falls within the camper age range of 5 to 13 years old. Would you like to add someone to your account?<br /><a class="tw-btn-primary" href="/camps/queue/addperson/">Add a Person</a></p>';
            $html .= '</div>';
            PluginLogger::log("debug:: No campers were found on the account.");
            return $html;
        }

        // loop through the campers and create their elements in the list
        foreach ($this->pendingQueues as $personId => $camper) {
            // create the camper header
            $html .= '<section class="tw-bg-[#CAEC96]/25 tw-py-6 tw-px-8 tw-rounded-[20px] tw-mb-[22px]">';
            $html .= '<div class="fh5co-v-half camp-row ">' . "\n";
            $html .= '<div class="fh5co-v-col-4 fh5co-text fh5co-special-1 camperName tw-mb-4">' . "\n";
            $html .= '<p class="tw-text-[#3B89F0] tw-font-bold tw-text-2xl tw-my-0">' . $camper['first'] . '</p>' . "\n";

            if (empty($camper['registered']) && empty($camper['queued'])) {
                // display a message stating that there isn't anything to see for this camper
                $html .= "<p class=' tw-text-sm'>There are no registrations or queued camps for " . $camper['first'] . ".</p>";
                $html .= '<a href="./../" class="btn btn-title-action btn-outline no-top-padding go-back-button" >View Registration Options</a>';
                $html .= "</div>\n</section>\n\n";
                continue;
            }

            // closes the camper name box if there is details to show - it's closed in the if statement otherwise
            $html .= "</div>\n</div>\n\n";

            // check to see if the camper has any registration or queue entries

            // if we're here, then there is something to display - loop through the weeks and display anything found
            $html .= '<table class="tw-w-full tw-bg-white border-collapse tw-border-collapse tw-rounded-tl-[5px] tw-rounded-tr-[5px] tw-overflow-hidden">';
            $html .= '<tr class=" tw-bg-[#3B89F0] tw-text-white tw-text-sm tw-font-bold">';
            $html .= '<th class="tw-py-2 tw-px-4 tw-text-left">DATE</th>';
            $html .= '<th class="tw-py-2 tw-px-4">CAMP</th>';

            $html .= '</tr>';
            for ($w = 1; $w <= 12; $w++) {
                $registered     = isset($camper['registered'][$w])     ? $camper['registered'][$w]     : null;
                $campfireNight = isset($camper['campfireNight'][$w]) ? $camper['campfireNight'][$w] : null;
                $queued         = isset($camper['queued'][$w])         ? $camper['queued'][$w]         : null;
                $playPassDays   = isset($camper['playpass_days'][$w]) ? $camper['playpass_days'][$w] : null;

                if (!$queued && !$registered && !$campfireNight) {
                    PluginLogger::log("debug:: No activity for camper " . $camper['first'] . " and week $w.");
                    continue;
                }

                // there is something here, so build the div block for the week
                $image = $this->getQueueImage($camper, $w, $personId);
                // add the camper's name and the week for which we're displaying information
                $html .= '<tr class="fh5co-v-half camp-row border-b tw-border-[#D9D9D9] tw-text-sm">                            
                            <td class="tw-px-4">' . $this->CQModel->getWeek($w) . ' </td>
                            
                            <td class="tw-px-4 tw-text-center">
                                <div class="fh5co-v-col-2 fh5co-bg-img queued-image" style="background-image: url(' . plugin_dir_url(__FILE__) . '../Images/' . $image . '.jpg)"></div>
                                 <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                                    <div class="campListing">';

                // add the registered banner, if applicable
                if ($registered || $campfireNight) {
                    $html .= '<p class="registeredCamp">';

                    // If there's a registered camp, display it
                    if ($registered) {
                        if ($registered === 'Play Pass') {
                            PluginLogger::log("debug:: Processing Play Pass display", ['playPassDays' => $playPassDays, 'week' => $w, 'camper' => $camper['first']]);
                            if (!empty($playPassDays)) {
                                // Show specific days for Play Pass
                                $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
                                $selectedDayNames = [];
                                foreach ($playPassDays as $dayNum) {
                                    if (isset($dayNames[$dayNum])) {
                                        $selectedDayNames[] = $dayNames[$dayNum];
                                    }
                                }
                                $html .= 'Registered for Play Pass: ' . implode(', ', $selectedDayNames);
                                PluginLogger::log("debug:: Play Pass display with days", ['selectedDayNames' => $selectedDayNames]);
                            } else {
                                $html .= 'Registered for Play Pass';
                                PluginLogger::log("debug:: Play Pass display without days - playPassDays is empty");
                            }
                        } else {
                            $html .= 'Registered for ' . $registered;
                        }
                    }

                    // If both are present, add a line break
                    if ($registered && $campfireNight) {
                        $html .= '<br />with ';
                    }
                    // If only campfire is present, add "Registered for"
                    else if (!$registered && $campfireNight) {
                        $html .= 'Registered for ';
                    }

                    // If there’s a campfire event, display it
                    if ($campfireNight) {
                        $html .= 'Campfire Nights - Friday to Saturday';
                    }

                    $html .= '</p>';
                }

                // add any queued options
                if (!empty($queued)) {
                    $html .= '<div class="queueListings">' . "\n";

                    foreach ($queued as $campId => $queue) {
                        // create a string for the confirmation dialogs
                        $btnId = implode('-', array($personId, $campId, $w));
                        $btnMsg = json_encode(array('id' => $btnId, 'camper' => $camper['first'], 'week' => $this->CQModel->getWeek($w), 'camp' => $queue['name']));

                        // determine if this record is snoozed
                        $snoozed = false;
                        if (!empty($queue['snoozed_until']) && strtotime($queue['snoozed_until']) > time()) {
                            $snoozed = true;
                        }

                        // determine if this record is active
                        $active = false;
                        if (!empty($queue['expire_date']) && strtotime($queue['expire_date']) >= time()) {
                            $active = true;
                        }

                        // determine if this record is expired
                        $expired = false;
                        if (!empty($queue['expire_date']) && strtotime($queue['expire_date']) < time()) {
                            $expired = true;
                        }

                        $html .= '<div class="queueStatusColor" id="status-' . $btnId . '"> </div>' . "\n";
                        $html .= '<div class="queueOptionsContainer" id="container-' . $btnId . '">' . "\n";
                        $html .= '<p class="queuedCamp">';
                        $html .= $queue['name'];
                        $html .= "</p>\n";
                        $html .= '<p class="addedDate" id="addedtext-' . $btnId . '">Added to Queue: ';
                        $html .= date("F jS", strtotime($queue['date_added']));
                        $html .= "</p>\n";

                        // add snoozed until if applicable
                        if ($snoozed) {
                            $html .= '<p class="addedDate">Snoozed Until: ';
                            $html .= date("F jS \a\\t g:i A", strtotime($queue['snoozed_until']));
                            $html .= "</p>\n";
                        }

                        // add expired until if applicable
                        if ($expired) {
                            $html .= '<p class="addedDate" id="expiredtext-' . $btnId . '">Expired: ';
                            $html .= date("F jS \a\\t g:i A", strtotime($queue['expire_date']));
                            $html .= "</p>\n";
                            $html .= '<p class="addedDate"  id="expireddetail-' . $btnId . '"style="font-weight:bold; margin-top:10px">A space was available but not claimed in time. You must re-activate this queue to be notified of future available space in this camp.</p>';
                        }

                        $html .= "<a href=# data-toggle='modal' data-target='#myModal' data-action='cancel' data-msg='$btnMsg'>Remove Queue</a>";

                        // do we show the snooze button
                        if (!$snoozed && $active) {
                            $html .= " <a href=#  id='snoozeBtn-$btnId' data-toggle='modal' data-target='#myModal' data-action='snooze' data-msg='$btnMsg'>Snooze Queue</a>\n";
                        }

                        // do we show the reactivate button?
                        // shouldn't ever have snoozed and expired as snoozing nulls expire date, but just in case
                        if (!$snoozed && $expired) {
                            $html .= " <a href=# id='reactivatebtn-" . $btnId . "' data-toggle='modal' data-target='#myModal' data-action='reactivate' data-msg='$btnMsg'>Reactivate Expired Queue</a>\n";
                        }


                        $html .= "</div>\n\n";
                    }

                    $html .= "</div>\n";
                }
                $html .= "</div>\n</div>\n\n";
                $html .= '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '</section>';
        }

        return $html;
    }

    // Takes a camper record from the model and picks an image to display along side the week information
    // if a person Id is passed in, we'll try not to duplicate images to often
    function getQueueImage($record, $week, $person = null)
    {
        // Default/fallback image
        $chosenImage = $defaultImage = 'trailblazers';

        // Determine which source to use, in priority order
        $prioritySources = ['registered', 'campfireNight', 'queued'];
        $setSource = null;

        // promote campfire to a regestered entry if there isn't a camp that day so that we can get it's image
        if ((!isset($record['registered'][$week]) || $record['registered'][$week] === null)
            && isset($record['campfireNight'][$week]) && $record['campfireNight'][$week] !== null
        ) {

            $record['registered'][$week] = $record['campfireNight'][$week];
        }

        foreach ($prioritySources as $src) {
            if (!empty($record[$src][$week])) {
                $setSource = $src;
                break;
            }
        }

        // If none of the sources are available for this week, log and stick to fallback.
        if (is_null($setSource)) {
            PluginLogger::log(
                "No image source was provided for the Portal View",
                compact('record', 'week', 'person')
            );
        } else {
            $chosenImage = $this->model->getCampTag($record[$setSource][$week]);
        }

        // Usage-tracking logic, depending on source
        if ($setSource === 'registered' || $setSource === 'campfireNight') {
            // Single tag to track
            PluginLogger::log("debug:: Portal View get Camp tag", compact('setSource', 'record'));
            $tag = $this->model->getCampTag($record[$setSource][$week]);
            if ($person) {
                if (empty($this->imageStore[$person][$tag])) {
                    $this->imageStore[$person][$tag] = 1;
                } else {
                    $this->imageStore[$person][$tag]++;
                }
            }
        } elseif ($setSource === 'queued') {
            // Multiple possible tags; pick least-used or an unused random if available
            $minCount = 100;
            $minTag = '';
            $tag = '';

            for ($j = 0; $j < count($record['queued'][$week]); $j++) {
                // Randomly pick one queued option
                $randChoice = array_rand($record['queued'][$week], 1);
                $proposedTag = $this->model->getCampTag($randChoice);

                // If this person hasn’t used the proposed tag yet, take it
                if ($person && empty($this->imageStore[$person][$proposedTag])) {
                    $this->imageStore[$person][$proposedTag] = 1;
                    $tag = $proposedTag;
                    break;
                }

                // Otherwise, track the least-used one
                if (!isset($this->imageStore[$person][$proposedTag])) {
                    $this->imageStore[$person][$proposedTag] = 0;
                }
                if ($this->imageStore[$person][$proposedTag] < $minCount) {
                    $minCount = $this->imageStore[$person][$proposedTag];
                    $minTag = $proposedTag;
                }
            }

            if (empty($tag)) {
                $tag = $minTag;
                if (!empty($tag) && $person) {
                    $this->imageStore[$person][$tag]++;
                }
            }

            // Update chosen image if we found a queued tag
            if (!empty($tag)) {
                $chosenImage = $tag;
            }
        }

        // Check if file actually exists
        if (!file_exists(__DIR__ . "/../Images/{$chosenImage}.jpg")) {
            PluginLogger::log(
                "Unable to load image file " . __DIR__ . '/../Images/' . $chosenImage . '.jpg.',
                $record
            );
            $chosenImage = $defaultImage;
        }

        return $chosenImage;
    }
}

class PortalViewDummyLogger
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
