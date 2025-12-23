<?php
// completePlayPassRegistration.php - Standalone completion page for Play Pass registrations
// session_start();

// Check if we have at least one of the session flags - either for new registrations or for edits
if (!isset($_SESSION['ucLink']) && !isset($_SESSION['displayRegistration']) && !isset($_SESSION['displayPlayPassEdits'])) {
    // Redirect back to playpass.php if we don't have any necessary session data
    echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    exit;
}

// Get variables from session
$ucLink = isset($_SESSION['ucLink']) ? $_SESSION['ucLink'] : '';
$displayRegistration = isset($_SESSION['displayRegistration']) ? $_SESSION['displayRegistration'] : false;
$displayError = isset($_SESSION['displayError']) ? $_SESSION['displayError'] : false;
$displayPlayPassEdits = isset($_SESSION['displayPlayPassEdits']) ? $_SESSION['displayPlayPassEdits'] : false;
$playPassEditsCount = isset($_SESSION['playPassEditsCount']) ? $_SESSION['playPassEditsCount'] : 0;

// Clear session variables to avoid duplicate displays on page refresh
if (isset($_SESSION['ucLink'])) unset($_SESSION['ucLink']);
if (isset($_SESSION['displayRegistration'])) unset($_SESSION['displayRegistration']);
if (isset($_SESSION['displayError'])) unset($_SESSION['displayError']);

// if we're in the app, skip this screen and go straight to Ultracamp
if (!empty($_SESSION['appLogin']) && $_SESSION['appLogin'] === true && !empty($ucLink)) {
    // header("Location: " . $ucLink);
    echo '<script>window.location.href = "/camps/queue/' . $ucLink . '";</script>';

    exit();
}

// Set other display variables to false
$displayWaitlist = false;
$displayActiveQueue = false;
$displayCampChange = false;
$displayAddOn = false;
$campersWithQueues = 0;

// Include the view counter
require_once(plugin_dir_path(__FILE__) . 'counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/completePlayPassRegistration', $_SERVER['REMOTE_ADDR']);
?>


<div id="fh5co-featured" style="background-image: url(/images/wood_1.png); background-position: 0px 0px; background-ratio:0; padding: 0;">
    <div class="container">
        <div class="row">
            <div id="camps-wrapper" class="fh5co-grid">
                <?php
                /* We encountered a blocking error - usually with the creation of the Ultracamp cart */
                if ($displayError) {
                ?>
                    <!-- The Error section -->
                    <div class="fh5co-v-half camp-row">
                        <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                            <span class="pricing">Uh Oh....</span>

                            <p class="description">We are afraid that something has gone terribly and horribly wrong.</p>
                            <p class="description">This generally happens when our payment processing vendor is unable to take the registration request due to technical issues. We know that this is very inconvenient, and we are sorry for the hassle!</p>
                            <p class="description">All is not lost however. We have recorded your camp selections into our camper queue system, saving your camper's choices and place in the camps selected. You will get an email shortly with the opportunity to retry processing the reservation, and hopefully the technical issues will be sorted out.</p>
                        </div>
                        <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/camps/summer/images/error-no-signal.jpg); background-position: top"></div>
                    </div>
                <?php
                }

                /* Registration Display - show this if there are new registrations */
                if ($displayRegistration && !empty($ucLink)) :
                ?>
                    <!-- The registration section -->
                    <div class="fh5co-v-half camp-row">
                        <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                            <span class="pricing">One Final Step</span>
                            <a href="<?php echo $ucLink; ?>" class="btn btn-complete-registration" id="loginBtn" target=_BLANK>
                                Click Here to <br />Complete Registration</a>
                            <p class="description">Click the button above to review your order, setup your payment method, and complete your summer registration.</p>
                            <p class="description">Shortly after you have completed the payment process (by clicking the button above), you will receive confirmation emails detailing your summer schedule, and the payment receipts.</p>
                            <p class="description" style="font-weight:bold">We're excited to host your family at camp.<br /> - Long Live Summer!</p>
                        </div>
                        <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/camps/summer/images/grid-price.jpg); background-position: top"></div>
                    </div>
                <?php
                endif;

                /* Display for Play Pass Edits */
                if ($displayPlayPassEdits) :
                    // This variable is set in processPlayPassCheckout.php
                    $editsCount = $playPassEditsCount ?: 0;
                    unset($_SESSION['displayPlayPassEdits']);
                    unset($_SESSION['playPassEditsCount']);
                ?>
                    <!-- Play Pass Edits Section -->
                    <div class="fh5co-v-half camp-row">
                        <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                            <span class="pricing">Play Pass Registration Updates</span>

                            <h3 style="margin-top: 0; color: #ffffff;">Your changes have been submitted!</h3>
                            <p class="description">We have received your request to update <?php echo $editsCount; ?> existing Play Pass registration<?php echo $editsCount !== 1 ? 's' : ''; ?>. Our office staff will process these changes within 2 business days.</p>
                            <p class="description">You will receive a confirmation email once the changes have been processed. If there are any issues or if we need additional information, we will contact you.</p>
                            </p>
                            <p class="description">If you have any questions about these changes, please contact our office at <strong>camps@cedarsprings.camp</strong> or call <strong>(425) 334-6215</strong>.</p>
                        </div>
                        <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/camps/summer/images/grid-price.jpg); background-position: top"></div>
                    </div>
                <?php
                endif;
                ?>
                <div style="width:100%; text-align:center">
                    <a href="/camps/queue/status/" class="btn btn-title-action btn-outline no-top-padding" style="margin-bottom:15px;">Review Camper Status</a>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
    var defaultContactFormTopic = "Day Camps";

    // remove cookies used to manage the cart
    deleteCookie('formInput');
    deleteCookie('reAuth');
</script>