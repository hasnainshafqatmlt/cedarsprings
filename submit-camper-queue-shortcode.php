<?php
// Register the [submit_camper_queue] shortcode
require_once plugin_dir_path(__FILE__) . 'classes/PluginLogger.php';
add_action('init', function () {
    add_shortcode('submit_camper_queue', 'submit_camper_queue_shortcode');
});


function submit_camper_queue_shortcode($atts)
{
    if (is_admin()) return;

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    ob_start();



    // NOTE: This is a partial port. Some dependencies/classes may need to be ported or stubbed.
    // TODO: Replace direct session/cookie usage with WordPress equivalents if needed.
    // TODO: Replace any header() redirects with WordPress-friendly redirects or error messages.
    // TODO: Adapt includes/requires to use plugin_dir_path(__FILE__)

    require_once(plugin_dir_path(__FILE__) . 'counter/view_counter.php');

    $counter = new ViewCounter();
    $counter->recordVisit('/camps/queue/submitcamperqueue/', $_SERVER['REMOTE_ADDR']);

    $logger = "";
    // the ability to ensure the user is logged in
    require_once plugin_dir_path(__FILE__) . 'classes/ValidateLogin.php';

    $validator = new ValidateLogin($logger);
    // get the class for validating the input values
    require_once plugin_dir_path(__FILE__) . 'classes/ValidateFormData.php';
    $formValidation = new ValidateFormData($logger);

    // get the object for loading transportation options
    require_once plugin_dir_path(__FILE__) . 'classes/Transportation.php';
    $transportation = new Transportation($logger);

    // we need to know what the summer schedule is - grab that from my API tools
    require_once plugin_dir_path(__FILE__) . 'api/campInfo/Weeks.php';

    $summerWeeks = new Weeks($logger);

    // Ultracamp
    require_once plugin_dir_path(__FILE__) . 'classes/UltracampCart.php';

    $uc = new UltracampCart($logger);

    require_once plugin_dir_path(__FILE__) . 'classes/ActiveManager.php';

    $activeManager = new ActiveManager($logger);
    // basic utilities
    require_once plugin_dir_path(__FILE__) . 'classes/CQModel.php';
    $CQModel = new CQModel($logger);
    $CQModel->setUltracampObj($uc);

    $debug = false;

    // Initialize variables
    $gridValues = array();
    $displayWaitlist = false;
    $displayRegistration = false;
    $displayActiveQueue = false;
    $displayAccelerate = false;
    $displayAddingcamp = false;
    $busCampers = array();
    $accelCampers = 0;
    $weeks = array();
    $accelWeeks = array();
    $changes = array();

    // Check if there's session data that should be treated as POST data
    if (isset($_SESSION['apiData'])) {
        $_POST['apiData'] = $_SESSION['apiData'];
        unset($_SESSION['apiData']);
    }

    // Process POST data
    foreach ($_POST as $q) {
        // check to see if the incoming post is for one of our form values

        if (!is_array($q) && isset($formValidation) && $formValidation->basicValidation($q)) {

            // snag the first character from each registration element and set a couple of flags for display elements on the page
            $value = explode('-', $q);

            // Accelerate is totally different
            if ($value[2] == 9999 && $value[0] != 'Q') {
                $displayAccelerate = true;
                $accelCampers++;
                // store the week numbers of registration requests for bus capacity check
                $accelWeeks[] = $value[3];

                // store the form information - including modified actions for change orders
                $gridValues[] = implode('-', array($value[0], $value[1], $value[2], $value[3]));

                continue; // don't need to be adding counts for transportation and the like so continue through the list
            }

            // check to see if we have change orders among the Active entries
            // change them from A to C if we do
            if (isset($activeManager)) {
                $changeOrder = $activeManager->checkIfChangeOrder($value);

                if ($value[0] == 'A' && $changeOrder['changeOrder']) {
                    $value[0] = 'C';
                    $changes[implode('-', $value)] = $activeManager->checkForAddOn($value);
                }
            }

            switch ($value[0]) {
                case 'A':
                    $displayActiveQueue = true;
                case 'R':
                    // don't set the flag if the option is campfire nights, we don't need it
                    if ($value[2] == "73523")
                        break;

                    $displayRegistration = true;
                    break;
                case 'Q':
                case 'C':
                    if (isset($changeOrder) && $changeOrder['changeOrder'] == true && $changeOrder['campName'] === NULL) {
                        // display registration options for adding a camp to an existing campfire night reservations
                        $displayRegistration = true;
                        $displayAddingcamp = true;
                    } else {
                        $displayWaitlist = true;
                    }
                    break;
            }

            // If we are not adding the reservation to a queue, or if the change isn't a change order, 
            // except if it is a change order, it's to add a camp to a campfire only reservation
            // then do some tracking for the transportation system.
            if (($value[0] != 'Q' &&  $value[0] != 'C') ||
                ($value[0] == 'C' && isset($changeOrder) && $changeOrder['campName'] === NULL)
            ) {
                // store the week numbers of registration requests for bus capacity check
                $weeks[] = $value[3];

                // store the number of campers making a reservation - also for the transportation system
                if (!in_array($value[1], $busCampers)) {
                    $busCampers[] = $value[1];
                }
            }

            // store the form information - including modified actions for change orders
            $gridValues[] = implode('-', array($value[0], $value[1], $value[2], $value[3]));
        }

        // mobile page can send in arrays of queue requests - check for those
        if (is_array($q)) {
            foreach ($q as $x) {
                if (isset($formValidation) && $formValidation->basicValidation($x)) {
                    // store the form information
                    $gridValues[] = $x;
                    $displayWaitlist = true;
                }
            }
        }
    }

    // if we don't have valid form data - just reject the submission and go back to the form
    if (count($gridValues) < 1) {
        echo '<div class="error">Invalid form data. Please try again.</div>';
        echo '<script>window.location.href = "/camps/queue";</script>';
        return ob_get_clean();
    }

    // Store the form information in a session variable

    $_SESSION['formInput'] = $gridValues;

    // store the incoming form values into a cookie for the grid to use to re-populate on back button or error
    setCookie('formInput', implode('_', $gridValues), time() + 86400, '/camps/queue');
    setCookie('formInput', implode('_', $gridValues), time() + 86400, '/camps/queue/complete_registration');
    setCookie('formInput', implode('_', $gridValues), time() + 86400, '/camps/queue/submitcamperqueue/');

    // confirm that the user is still logged in and their session has not expired
    if (
        empty($_COOKIE['key']) ||
        empty($_COOKIE['account']) ||
        (isset($validator) && !$validator->validate($_COOKIE['key'], $_COOKIE['account']))
    ) {
        // instruct the grid to collect login info without running to Ultracamp
        setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
        setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue/complete_registration');
        setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue/submitcamperqueue/');
        echo '<div class="error">Please log in again to continue.</div>';
        echo '<script>window.location.href = "/camps/queue";</script>';
        return ob_get_clean();
    }

    // If the user has only submitted waitlist requests and no registration requests, skip past this page and go straight to completion
    if ($displayWaitlist && !$displayRegistration && !$displayActiveQueue && !$displayAccelerate) {
        PluginLogger::log("Redirecting to completion...*");
        echo '<script>window.location.href = "/camps/queue/complete_registration";</script>';
        return ob_get_clean();
    }

    // If the user has only submitted campfire night reservations without any other details, we can also skip this page and go straight to completion.
    if (!$displayRegistration && !$displayActiveQueue && !$displayAccelerate) {
        PluginLogger::log("Redirecting to completion...**");
        echo '<script>window.location.href = "/camps/queue/complete_registration";</script>';
        return ob_get_clean();
    }
    // --- BEGIN: HTML OUTPUT (without head/body/html tags) ---


?>
    <div class="registration-second-wrapper">
        <div class="registration-wrapper-container">
            <div class="registration-block">
                <form method="post" action='<?php echo home_url('/camps/queue/complete_registration/'); ?>' onsubmit="return submitCamperQueueForm()">
                    <div class="registration-cell tw-pt-[22px] tw-pb-[12px] md:tw-px-[66px] tw-px-[44px]">
                        <h2 class="heading">Camper Details</h2>
                    </div>

                    <div id="fh5co-featured">
                        <div class="container">
                            <div class="row">
                                <div id="camps-wrapper" class="fh5co-grid">
                                    <?php
                                    // Start Accelerate Section
                                    if ($displayAddingcamp) :
                                    ?>
                                        <!-- Add Camp Section -->
                                        <div class="fh5co-v-half camp-row">
                                            <div class="fh5co-v-col-2 fh5co-text fh5co-special-1 ">
                                                <span class="pricing ">Camp Add On</span>

                                                <p class="description no-top-padding">Yay! You are making your camp week better. The additions to your existing reservations that you've chosen include additional costs for the week. We will update your payment for the week with these changes.</p>

                                                <ul class="description">The additions are:
                                                    <?php
                                                    if (isset($changes) && isset($CQModel)) {
                                                        foreach ($changes as $entry => $change) {
                                                            if ($change['changeOrder']) {
                                                                $a = explode('-', $entry);
                                                                echo '<li style="text-align:left">' . $CQModel->getWeek($a[3]) . ': ';
                                                                echo $CQModel->getCamperName($a[1])['FirstName'] . " - ";
                                                                echo $CQModel->getCamp($a[2]) . ' (+$';

                                                                // Need to map the option template to the specific week to get the cost
                                                                if (isset($uc)) {
                                                                    $optionId = $uc->getSessionOptionId($a[2], $CQModel->getSessionIdFromWeekNumber($a[3]));
                                                                    echo $uc->getOptionCost($optionId) . ')';
                                                                }
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </ul>
                                            </div>

                                        </div>
                                    <?php
                                    // end Add camp to addon
                                    endif;

                                    /* Registration (and Active) Display */
                                    if ($displayActiveQueue || $displayRegistration) {
                                    ?>
                                        <!-- Transportation section -->
                                        <div class="registration-cell tw-pt-[22px] tw-pb-[12px] md:tw-px-[66px] tw-px-[0px]">
                                            <div class="fh5co-v-half camp-row">
                                                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1 ">
                                                    <div class="form-group has-feedback">
                                                        <div class=" tw-flex tw-gap-5 tw-flex-col md:tw-flex-row">
                                                            <label class=" tw-font-bold" for="transportation">Transportation Option</label>
                                                            <select class=" tw-w-full" id="transportation" name="transportation" required>
                                                                <option value="">Make a Selection</option>

                                                                <optgroup label="Direct Drop Off &amp; Pick Up" class="shuttle">
                                                                    <?php
                                                                    if (isset($transportation)) {
                                                                        echo $transportation->getDropOffOptionHTML($weeks, count($busCampers));
                                                                    }
                                                                    ?>
                                                                </optgroup>

                                                                <optgroup label="Bus Transportation (+$<?php echo isset($transportation) ? $transportation->config->transportationCost : '0'; ?>/camper/week)" class="bus">
                                                                    <?php
                                                                    if (isset($transportation)) {
                                                                        echo $transportation->getTransportationOptionHTML($weeks, count($busCampers));
                                                                    }
                                                                    ?>
                                                                </optgroup>
                                                            </select>
                                                        </div>

                                                        <div id="additionalBusesSection" style="display:none">
                                                            <p class="description">The transportation option chosen is not available for each week in your reservation. Please choose an alternate for when your primary choice is not available.</p>
                                                            <div id="additionalBusesSectionFormFields" class=" tw-flex tw-gap-5 tw-flex-col md:tw-flex-row"></div>
                                                        </div>

                                                        <p class="description" id="transportationDescription">Select a drop off and pick up location and we will take care of the rest.<br /> Bus transportation is $<?php echo isset($transportation) ? $transportation->config->transportationCost : '0'; ?> per camper per week. For details on each of the bus locations, <a href="/locations" target=_BLANK>click here</a>.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Lunch Section -->
                                        <div class="registration-cell tw-pt-[22px] tw-pb-[12px] md:tw-px-[66px] tw-px-[0px]">
                                            <div class="fh5co-v-half camp-row">
                                                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                                                    <div class="fh5co-flex">
                                                        <label class="control-label">Add Hot Lunch</label>
                                                    </div>

                                                    <p class="description no-top-padding">We have reimagined hot lunch! Campers can choose from three different options each day. Each option comes with sides, a beverage and a dessert. Options include corn dogs, chicken strips, cheeseburger, pizza and more! Lunch costs $9 per day.</p>
                                                    <div id="lunch-options">
                                                        <div class="lunch-option">
                                                            <input type="checkbox" name='lunchoption[]' class='lunch-choice-checkbox' id="lunch_select-all" onchange="handleSelectAll()" value="selectall"><label for="lunch_select-all">Select All</label>
                                                        </div>

                                                        <div class="lunch-option">
                                                            <input type="checkbox" name='lunchoption[]' value="monday" class='lunch-choice-checkbox' id="lunch_monday" onchange="handleCheckboxChange()"><label for="lunch_monday">Monday</label>
                                                        </div>

                                                        <div class="lunch-option">
                                                            <input type="checkbox" name='lunchoption[]' value="tuesday" class='lunch-choice-checkbox' id="lunch_tuesday" onchange="handleCheckboxChange()"><label for="lunch_tuesday">Tuesday</label>
                                                        </div>

                                                        <div class="lunch-option">
                                                            <input type="checkbox" name='lunchoption[]' value="wednesday" class='lunch-choice-checkbox' id="lunch_wednesday" onchange="handleCheckboxChange()"><label for="lunch_wednesday">Wednesday</label>
                                                        </div>

                                                        <div class="lunch-option">
                                                            <input type="checkbox" name='lunchoption[]' value="thursday" class='lunch-choice-checkbox' id="lunch_thursday" onchange="handleCheckboxChange()"><label for="lunch_thursday">Thursday</label>
                                                        </div>

                                                        <div class="lunch-option">
                                                            <input type="checkbox" name='lunchoption[]' value="friday" class='lunch-choice-checkbox' id="lunch_friday" onchange="handleCheckboxChange()"><label for="lunch_friday">Friday</label>
                                                        </div>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>
                                    <?php
                                        // End Active / Registration Section
                                    }

                                    // Start Accelerate Section
                                    if ($displayAccelerate) {
                                    ?>
                                        <!-- ACCELERATE SECTION -->
                                        <div class="registration-cell tw-pt-[22px] tw-pb-[12px] md:tw-px-[66px] tw-px-[0px]">
                                            <div class="fh5co-v-half camp-row">

                                                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1 ">
                                                    <span class="pricing ">Accelerate Transportation</span>

                                                    <p class="description no-top-padding">Please choose how you would like your camper to get to camp on Monday, and how you would like them to come home on Friday.</p>

                                                    <p class="description">Accelerate Week-long Overnight Camp takes place in Naches Washington at our camp Lost Creek Village. The address is 1260 Lost Creek Rd, Naches, WA.</p>

                                                    <div class="form-group sibling-option">
                                                        <div class="accelTransportationGroup">
                                                            <p class="description" style="font-weight:bold" for="transportation">Monday: The Start of Camp</p>
                                                            <?php
                                                            if (isset($transportation)) {
                                                                echo $transportation->accelSundayOption($accelWeeks, $accelCampers);
                                                            }
                                                            ?>
                                                        </div>
                                                        <div class="accelTransportationGroup">
                                                            <p class="description" style="font-weight:bold" for="transportation">Friday: The End of Camp</p>
                                                            <?php
                                                            if (isset($transportation)) {
                                                                echo $transportation->accelFridayOption($accelWeeks, $accelCampers);
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                        // End Accelerate Sections
                                    }
                                    ?>
                                    <div class="registration-cell tw-pt-[22px] tw-pb-[12px] md:tw-px-[66px] tw-px-[0px]">
                                        <div class="fh5co-v-half camp-row">

                                            <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                                                <div class="fh5co-flex">
                                                    <label class="control-label">Camp is Better with Friends!</label>
                                                </div>
                                                <p class="description no-top-padding">We have found that camp is so much fun when you can share the experience with your friends. If you have friends who are signing up for camp with you, and you would like to ensure that your campers are enrolled in the same pod, please let us know the name of the friends. If you have multiple friends, feel free to list them all here, but we do need first and last names in order to make a match.</p>


                                                <p class="description "><i>If you have multiple friends, feel free to list them all here, but we do need first and last names in order to make a match.</i></p>

                                                <?php
                                                // display a friend field for each camper
                                                if (isset($_SESSION['formInput']) && isset($CQModel)) {
                                                    $displayedCampers = array();
                                                    foreach ($_SESSION['formInput'] as $entry) {
                                                        $a = explode("-", $entry);
                                                        if (empty($displayedCampers[$a[1]])) {
                                                            $displayedCampers[$a[1]] = true;
                                                            $camperName = $CQModel->getCamperName($a[1])['FirstName'];

                                                            echo '<div class="form-group ">';
                                                            echo '<label for="friends" class="friends-input-label">' . $camperName . '\'s Friend Requests</label>';
                                                            echo '<input type="text" id="friends-' . $a[1] . '" name="friends-' . $a[1] . '" class="form-control bio-info" placeholder="Friend\'s names, both first and last." />';
                                                            echo '</div>';
                                                        }
                                                    }
                                                }
                                                ?>

                                                <?php
                                                // show sibling options section if there are more than one camper on the account
                                                if (!empty($_POST['showSiblingOptions']) && $_POST['showSiblingOptions'] == 'true') {
                                                ?>
                                                    <div id="sibling-container" style="margin-bottom:5px;">
                                                        <p class="description">Would you like the campers on your account to also be included in friend pods wherever possible?</p>
                                                        <div class="flex flex-wrap gap-4">
                                                            <div class="sibling-option no-top-padding flex gap-1">
                                                                <input type="radio" name='sibling-choice' class='lunch-choice-checkbox' id="sibling-yes" value="yes" /><label for="sibling-yes" class="bold">Yes Please</label>
                                                            </div>

                                                            <div class="sibling-option no-top-padding flex gap-1">
                                                                <input type="radio" name='sibling-choice' value="no" class='lunch-choice-checkbox' id="sibling-no" checked /><label for="sibling-no" class="bold">Not Necessarily</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>

                                            </div>
                                        </div>
                                    </div>
                                    <!-- The submit button section -->
                                    <div class="registration-cell tw-pt-[22px] tw-pb-[12px] md:tw-px-[66px] tw-px-[0px] ">
                                        <div class="fh5co-v-half camp-row">
                                            <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">

                                                <p class="description no-top-padding text-blue bold text-center">If everything looks correct here, we're ready to move on to confirming your choices and setting up a payment method.</p>
                                                <input type="submit" class="btn-green" id="submitBtn" value="Save Choices" />

                                            </div>


                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function submitCamperQueueForm() {
                $('#submitBtn').val('Loading...');
                $('#submitBtn').prop('disabled', true);
                return true;
            }
        </script>

        <?php
        // only include this if we have a registration coming in
        if ($displayRegistration && isset($transportation)) {
        ?>
            <script type="text/javascript">
                var transportationOptions = {
                    <?php
                    echo $transportation->getTransportationOptionJS($weeks);
                    echo $transportation->getDropOffOptionJS($weeks);
                    ?>
                };

                var weeksFull = <?php echo $transportation->getWeeksFull(); ?>;

                // listed summer schedule from the database - used by the transportation segment
                var summerSchedule = [
                    <?php
                    if (isset($summerWeeks)) {
                        $summerWeeks->listWeeks(false); // false removes week numbers
                        echo $summerWeeks->customTags('"', '",');
                    }
                    ?>
                ];
                var adminAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            </script>
        <?php } ?>

    <?php
    // --- END: HTML OUTPUT ---
    return ob_get_clean();
}
