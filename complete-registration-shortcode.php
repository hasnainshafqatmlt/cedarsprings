<?php
// Start session at the very beginning of the plugin
add_action('init', 'start_session_early', 1);
// require_once plugin_dir_path(__FILE__) . 'classes/PluginLogger.php';

function start_session_early()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Register the [complete_registration] shortcode
add_action('init', function () {
    add_shortcode('complete_registration', 'complete_registration_shortcode');
});

function complete_registration_shortcode($atts)
{

    ob_start();

    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return '<!-- hidden in editor -->';
    }

    // echo '<pre>';
    // echo print_r($_SESSION, true);
    // echo print_r($_POST, true);
    // echo '</pre>';
    PluginLogger::clear();
    require_once plugin_dir_path(__FILE__) . 'queue/process.php';


    // $ucLink = isset($ucLink) ? $ucLink : '';
    // $displayError = isset($displayError) ? $displayError : false;
    // $displayActiveQueue = isset($displayActiveQueue) ? $displayActiveQueue : false;
    // $displayRegistration = isset($displayRegistration) ? $displayRegistration : false;
    // $displayCampChange = isset($displayCampChange) ? $displayCampChange : false;
    // $displayAddOn = isset($displayAddOn) ? $displayAddOn : false;
    // $campersWithQueues = isset($campersWithQueues) ? $campersWithQueues : 1;



    // if we're in the app, skip this screen and go straight to Ultracamp
    if (!empty($_SESSION['appLogin']) && $_SESSION['appLogin'] ===  true  && !empty($ucLink)) {
        echo 'in redirect';
        // header("Location: " . $ucLink);
        exit();
    }
    // This file relied on require 'process.php' to set variables like $ucLink, $displayError, etc.
    // TODO: Port the process.php logic into the plugin or include appropriate classes.
    // For now, assume these variables might be preset by earlier steps.
    // Example placeholders:


?>
    <form method="post" action='process.php'>
        <div id="fh5co-title">

            <div class="complete-reg-block">
                <div class="row text-center fh5co-heading">
                    <h2 class="heading">Cedar Springs Camper Queue</h2>
                    <p class="sub-heading" style="margin-top: 10px">You are almost done, a great summer is just ahead!</p>

                </div>
            </div>
        </div>




        <div id="fh5co-featured">

            <div class="container">

                <div class="row">
                    <div id="camps-wrapper" class="fh5co-grid">
                        <?php
                        /* We encountered a blocking error - usually with the creation of the Ultracamp cart */
                        //if(true) {
                        if ($displayError) {
                        ?>
                            <!-- The Error section -->
                            <div class="fh5co-v-half camp-row">
                                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                                    <span class="pricing">Uh Oh....</span>

                                    <p class="description ">We are afraid that something has gone terribly and horribly wrong.</p>
                                    <p class="description">This generally happens when our payment processing vendor is unable to take the registration request due to technical issues. We know that this is very inconvenient, and we are sorry for the hassle!</p>
                                    <p class="description">All is not lost however. We have recorded your camp selections into our camper queue system, saving your camper's choices and place in the camps selected. You will get an email shortly with the opportunity to retry processing the reservation, and hopefully the technical issues will be sorted out.</p>


                                </div>
                                <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/wp-content/uploads/2025/11/error-no-signal.jpg); background-position: top"></div>

                            </div>

                        <?php /* Registration (and Active) Display */
                        }

                        /* Registration (and Active) Display */
                        if ($displayActiveQueue || $displayRegistration) :
                        ?>
                            <!-- The registration section -->
                            <div class="fh5co-v-half camp-row">
                                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                                    <span class="pricing">One Final Step</span>
                                    <a href="<?php echo  $ucLink; ?>" class="btn-green" id="loginBtn" target=_BLANK style="max-width: 300px; margin: 0 auto 20px;">
                                        Complete Registration</a>
                                    <p class="description ">Click the button above to review your order, setup your payment method, and complete your summer registration.</p>
                                    <p class="description ">Shortly after you have completed the payment process (by clicking the button above), you will receive confirmation emails detailing your summer schedule, and the payment receipts.</p>
                                    <p class="description" style="font-weight:bold">We're excited to host your family at camp.<br /> - Long Live Summer!</p>



                                </div>
                                <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/wp-content/uploads/2025/11/grid-price.jpg); background-position: top"></div>

                            </div>

                        <?php /* Registration (and Active) Display */
                        endif;

                        if ($displayCampChange) :
                        ?>
                            <div class="fh5co-v-half camp-row">

                                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1 ">
                                    <span class="pricing">Registration Update</span>

                                    <p class="description">Your camper queue registration applies to a week for which your camper is already registered. Updating your reservation to this new camp can take up to 2 business days to complete. Your camper's place in the new camp is secure, and we'll send you an email as soon as your reservation has been fully updated.</p>
                                    <p class="description">We are excited that you were able to take advantage of the opening in this new camp.</p>

                                </div>
                                <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/wp-content/uploads/2025/12/day_camp_sign.jpg)"></div>
                            </div>
                        <?php
                        endif; // displayCampChange


                        if ($displayAddOn) :
                        ?>
                            <div class="fh5co-v-half camp-row">

                                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1 ">
                                    <span class="pricing">Your Camp Experience Got Even Better!</span>

                                    <p class="description">We're excited to confirm that your campfire night + day camp combo updates have been applied to your reservation. Our staff will finalize the details behind the scenes, which can take up to two business days.</p>
                                    <ul class="description" style="font-size: .9em;">
                                        <li style="text-align:left"><b>Payment Adjustments:</b> If you've already paid in full, we'll charge your payment method on file for the added cost. If you're on a payment plan, we'll simply fold the new charges into your remaining payments.
                                        <li style="text-align:left"><b>Confirmation Notice:</b> Your camper's spot is secured. We'll send you an updated reservation confirmation email once everything is completely set.
                                    </ul>
                                    <p class="description">Please feel free to reach out to us with any questions! We are excited to see you at camp!</p>

                                </div>
                                <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/wp-content/uploads/2025/12/day_camp_sign.jpg)"></div>
                            </div>
                        <?php
                        endif; // displayAddOn

                        /* Waitlist Entry Display */
                        if ($displayWaitlist) {
                            // get the header right based on campers on the waitlist - the count comes from process.php
                            if ($campersWithQueues > 1) {
                                $header = 'You have added your campers to the Camper Queue.';
                            } else {
                                $header = 'You have added your camper to the Camper Queue.';
                            }

                            // get the contact info that we'll use to reach out to the customer
                            $person = $cart->getPrimaryAccountPerson($_COOKIE['account']);
                        ?>
                            <div class="fh5co-v-half camp-row">

                                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1 ">
                                    <span class="pricing">Camper Queue</span>
                                    <p class="description no-top-padding"><?php echo $header; ?></p>

                                    <p class="description">Campers on the camper queue are frequently called upon to claim newly available spaces in the camps chosen. These spaces are the result of both cancellations within the camp and new capacity becoming available. While we are not able to predict if or when a camper will have a place open, we will keep you updated whenever your camper's chosen camp has space.</p>
                                    <p class="description">To contact you regarding openings in your camper queue, we will email you at <?php echo $person->Email; ?> <!--and send a text message to <?php echo $person->PrimaryPhoneNumber; ?> -->. If you need to update this information, you can do so here:
                                        <a href="https://www.ultracamp.com/publicaccounts/publicpersondetail.aspx?idCamp=107&campcode=CP7&lang=en-Us&idperson=<?php echo $person->Id; ?>" target=_BLANK>Update Contact Info.</a>
                                    </p>

                                </div>
                                <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/wp-content/uploads/2025/11/waitlist-display.jpg)"></div>
                            </div>

                        <?php /* Waitlist Entry Display */
                        }

                        ?>
                        <div style="width:100%; max-width: 300px; text-align:center; margin: 30px auto 0;">
                            <a href="/camps/queue/status/" class="btn-green" style="margin-bottom:15px;">Review Camper Status</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        // remove cookies used to manage the cart
        jQuery(document).ready(function() {
            setTimeout(() => {
                // deleteCookie('formInput');
                // deleteCookie('reAuth');
            }, 1000);
        })
    </script>

    <style>
        .complete-reg-block {
            width: 460px;
            max-width: 100%;
            margin: 0 auto;
        }

        .complete-reg-footer {
            display: inline-block;
            width: 100%;
            background-image: url(/wp-content/uploads/2025/11/Camp-IMAGE-2.webp);
            background-size: cover;
            background-repeat: no-repeat;
            height: 300px;
            background-position: center bottom;
            margin-top: 100px;
        }

        .complete-reg-block .heading {
            font-size: 24px;
            text-align: center;
        }

        .complete-reg-block .sub-heading {
            text-align: center;
        }

        .complete-reg-block .btn-green {
            margin-bottom: 25px;
        }



        #fh5co-featured .fh5co-grid>.fh5co-v-half.camp-row {
            width: 100%;
        }

        #fh5co-featured .fh5co-grid>.fh5co-v-half {
            width: 50%;
            text-align: center;
            position: relative;
            display: -webkit-box;
            display: -moz-box;
            display: -ms-flexbox;
            display: -webkit-flex;
            display: flex;
            flex-wrap: wrap;
            -webkit-flex-wrap: wrap;
        }

        #fh5co-featured .fh5co-grid>.fh5co-v-half>.fh5co-v-col-2 {
            width: 50%;
            padding: 20px;
            position: relative;
        }

        @media screen and (max-width: 768px) {
            #fh5co-featured .fh5co-grid>.fh5co-v-half>.fh5co-v-col-2 {
                width: 100%;
            }
        }

        @media screen and (max-width: 768px) {

            #fh5co-featured .fh5co-grid>.fh5co-v-half>.fh5co-v-col-2.fh5co-bg-img,
            #fh5co-featured .fh5co-grid>.fh5co-v-half>.fh5co-v-col-2.fh5co-bg-img.img-pair {
                height: 200px;
            }
        }
    </style>
<?php
    return ob_get_clean();
}
