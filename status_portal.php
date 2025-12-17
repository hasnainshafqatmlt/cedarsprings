<?php

// Landing page once logged into the camper queue status page 
// March 2023 (BN)

// // Use environment-based debug setting
// require_once __DIR__ . '/../../../../tools/assets/EnvironmentLoader.php';
// use App\Config\EnvironmentLoader;

// $env = EnvironmentLoader::getInstance();
// $debug = ($env->getLogLevel() === 'DEBUG');

require_once plugin_dir_path(__FILE__) . 'classes/PluginLogger.php';
require_once(plugin_dir_path(__FILE__) . 'counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/status', $_SERVER['REMOTE_ADDR']);

// get the logger
// require_once './../logger/plannerLogger.php';
// if($debug) {
//     $logger->pushHandler($dbugStream);
// }

// the ability to ensure the user is logged in
require_once plugin_dir_path(__FILE__) . 'classes/ValidateLogin.php';
$validator = new ValidateLogin();

// confirm that the user is still logged in and their session has not expired -- also ensure that we have form data left after running validation
if (
    empty($_COOKIE['key']) ||
    empty($_COOKIE['account']) ||
    !$validator->validate($_COOKIE['key'], $_COOKIE['account'])
) {
    // instruct the grid to collect login info without running to Ultracamp (a second time) to see if the UC session is valid
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    // send the browser back to the login page
    echo '<script>window.location.href = "/camps/queue/";</script>';
    return false;
}

$account = FILTER_VAR($_COOKIE['account'], FILTER_VALIDATE_INT);

// If we've been on the registration page in the past hour minutes, then do an import on reservations for this account                
if (!empty($_SESSION['registrationPage']) && $_SESSION['registrationPage'] + 3600 > time()) {
    if (!empty($_SESSION['loadModifiedReservations'])) {
        $importModifiedOrders = true;
        $chooseImport = true;
        unset($_SESSION['loadModifiedReservations']);
    } elseif (!empty($_SESSION['loadNewReservations'])) {
        $importNewOrders = true;
        $chooseImport = true;
        unset($_SESSION['loadNewReservations']);
    }

    PluginLogger::log("d_bug:: Running account level import.");

    require_once(plugin_dir_path(__FILE__) . 'tools/pages/reservations/camperQueue-AccountImport.php');

    unset($_SESSION['registrationPage']);
}


// load the classes specific to the portal
require_once(plugin_dir_path(__FILE__) . 'classes/PortalView.php');
$view = new PortalView();
$background_image_url = home_url('/wp-content/uploads/2025/07/SUMMER-CAMPS-2.webp');
$logo_image_url = home_url('/wp-content/uploads/2025/03/Logo.svg');
?>
<!-- Modal -->

<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="tw-max-w-[900px] tw-mx-auto tw-mt-[160px]" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title tw-mt-0 tw-mb-2" id="myModalLabel">...</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Dialog Box Message Lands Here via JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="tw-btn-neutral"
                    data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="camper-hero-section tw-mb-7 " style="background-image: url('<?php echo esc_url($background_image_url); ?>') ;">
    <div class="logo">
        <img src="<?php echo esc_url($logo_image_url); ?>" />
    </div>
    <h2>Cedar Springs Summer Registration</h2>
    <p>Welcome, <span id="contactName"></span></p>
    <div class="logout-link"><a href="javascript:" onclick="return customLogout()" class="text-white">LOG OUT</a></div>
    <div>
        <a href="/camps/queue" class=" tw-inline-block">
            <button type="button" class="btn btn-info !tw-bg-[#5FB34A]">
                Registration HQ
            </button>
        </a>
    </div>
    <div>
        <a href="/camps/queue/addperson" class=" tw-inline-block">
            <button type="button" class="btn btn-primary">
                Add A Camper
            </button>
        </a>
    </div>
</div>

<div id="fh5co-featured" class=" tw-px-4">

    <div class="tw-max-w-[600px] tw-mx-auto">

        <div class="row">
            <div id="camps-wrapper" class="fh5co-grid">

                <!-- Active Options -->
                <?php if ($view->activeQueueCount > 0) : ?>
                    <div class="fh5co-v-half camp-row tw-bg-[#FFF8F0] tw-py-6 tw-px-8 tw-rounded-[20px] tw-mb-6" id="activeElementRow">
                        <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/camps/images/contact-us-grid.jpg)"></div>
                        <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                            <span class="pricing">There is space Available!</span>

                            <p class="description no-top-padding  tw-text-sm">Your patience on the camper queue has paid off and there is now space available. Select which camps you would like to register for.</p>
                            </p>
                            <form id="frmActive" method="post" action="./../submitCamperQueue.php" onSubmit='return validateActiveForm();'>
                                <div class="activeListingContainer" id="activeElementContainer">
                                    <?= $view->createActiveQueueHTML(); ?>
                                </div>
                                <span class="errorDescription" id="frmValidationErrorMsg">Please choose a camp to register.<br /></span>
                                <input type="submit" class="btn btn-title-action btn-outline no-top-padding" id="submitBtn" value="Register" />
                            </form>

                        </div>
                    </div>
                <?php endif; ?>


                <!-- Instructions -->
                <div class="fh5co-v-half camp-row tw-bg-[#FFF8F0] tw-py-6 tw-px-8 tw-rounded-[20px] tw-mb-6">
                    <div class="fh5co-v-col-2 fh5co-text fh5co-special-1">
                        <span class="pricing">Schedule and Queue Status</span>

                        <p class="description no-top-padding  tw-text-sm">Your family's summer schedule and queue status is listed below. If you would like to make additional registrations, or add your campers to additional queues, <a href="./../">return to the camper queue</a>.</p>
                        <p class="description tw-text-sm">For camps which your campers are in queue, you can cancel their place on the queue, removing them completely; you can snooze them on the queue, skipping any available space for the upcoming week; or you can re-activate expired queues, returning your camper to the top of the list for camps in which an available space was not claimed.</p>

                    </div>
                    <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/camps/daycamps/images/kids_hike.jpg)"></div>

                </div>

                <!-- Family Status Elements -->

                <?= $view->createPendingQueueHTML(); ?>


            </div>
        </div>

        <!-- Ultracamp and FAQ Links -->
        <div class="row" style="margin-bottom:30px;">
            <div class="fh5co-grid">
                <div class="fh5co-v-half">
                    <div class="fh5co-h-row-2 fh5co-reversed">
                        <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/images/computer.jpg)"></div>
                        <div class="fh5co-v-col-2 fh5co-text arrow-right">
                            <span class="pricing">Your Registration Account</span>
                            <p>You can also log directly into your Ultracamp registration account to update your family's information, modify existing reservations and register for camps individually.</p>
                            <?php
                            if (!empty($_COOKIE['uc-token'])) :
                            ?>
                                <a href="https://www.ultracamp.com/sso/login.aspx?idCamp=107&tkn=<?= $_COOKIE['uc-token']; ?>" target="_BLANK" class="btn btn-collage-action btn-outline" id="ucLogin-button">Access<br />Ultracamp</a>
                            <?php
                            else:
                            ?>
                                <a href="https://www.ultracamp.com/clientlogin.aspx?idCamp=107&campCode=CP7" target="_BLANK" class="btn btn-collage-action btn-outline" id="ucLogin-button">Login To<br />Ultracamp</a>

                            <?php
                            endif;
                            ?>
                        </div>
                    </div>
                </div>

                <div class="fh5co-v-half">
                    <div class="fh5co-h-row-2">
                        <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/camps/daycamps/images/pnw-trails.jpg)"></div>
                        <div class="fh5co-v-col-2 fh5co-text arrow-left">
                            <span class="pricing">F.A.Q. &amp; Other Details</span>
                            <p>There is a lot of information available about what camp looks like. Check out our knowledge base for answers to many of the frequently asked questions.</p>
                            <a href="/welcome" class="btn btn-collage-action btn-outline">Explore</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="contact-wrap"></div>


<?php
// <!-- Bootstrap DateTimePicker -->
wp_enqueue_script('bootstrap-min-js', plugin_dir_url(__FILE__) . 'js/bootstrap.min.js', array('jquery'), '2.0.0', true);
wp_enqueue_script('portalConfirmationDialog-js', plugin_dir_url(__FILE__) . 'js/portalConfirmationDialog.js', array('jquery'), '1.0.0', true);
wp_enqueue_style('bootstrap-modal-css', plugin_dir_url(__FILE__) . 'css/bootstrap-modal.css', array(), '1.0.0');
wp_enqueue_style('custom-camp-planner-css', plugin_dir_url(__FILE__) . 'css/planner.css', array(), '1.0.0');
?>

<script>
    var defaultContactFormTopic = "Day Camps";

    function validateActiveForm() {
        // confirm at least one check box is checked
        if ($("#frmActive input:checkbox:checked").length > 0) {
            // any one is checked
            $('#frmValidationErrorMsg').hide();
            $('#submitBtn').val('Loading...');
            $('#submitBtn').prop('disabled', true);
            return true;
        }

        // Display an error
        $('#frmValidationErrorMsg').show();
        return false;

    }

    function takeAction(id, action) {
        $("#myModal")
            .find(".modal-body")
            .html(
                '<div class="tw-text-center"><img src="/wp-content/uploads/2025/12/loading-animated.svg" id="loading-img" /> Loading</span></div>'
            );

        const formData = {
            action: action,
            id: id,
            account: getCookie("account"),
            key: getCookie("key"),
        };

        // run an ajax lookup for the camp info - then display the dialog box
        $.ajax({
                type: "POST",
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                data: {
                    // action: 'putQueueActions',
                    ...formData
                }, // our data object
                dataType: "json",
            })
            .done(function(data) {
                if (data.Authenticated !== true) {
                    setCookie("reAuth", "queue-management");
                    returnToHomepage();
                }

                // re-draw the modified elements of the webpage

                // ensure action was taken
                if (data.result == 0) {
                    console.log(
                        "While there was no apparent error, no work was done either."
                    );
                    return true;
                }

                switch (data.action) {
                    case "cancel":
                        // remove the entire queue element
                        $("#status-" + data.id).remove();
                        $("#container-" + data.id).remove();
                        break;

                    case "reactivate":
                        // remove the text refering to the element as expired
                        $("#expiredtext-" + data.id).remove();
                        $("#expireddetail-" + data.id).remove();
                        $("#reactivatebtn-" + data.id).remove();
                        break;

                    case "snooze":
                        // hide both the active elements
                        $("#chkcontainer-" + data.id).remove();
                        $("#activetextbox-" + data.id).remove();
                        // if we emptied the active section, hide it
                        if (
                            $("#activeElementContainer .listingCheckboxContainer").length == 0
                        ) {
                            $("#activeElementRow").remove();
                        }

                        // add a snoozed until date to the pending section
                        $("#addedtext-" + data.id).append(
                            '<p class="addedDate">Snoozed for 1 Week</p>'
                        );
                        // remove the snooze button on the pending section
                        $("#snoozeBtn-" + data.id).remove();
                }

                $("#myModal").modal("hide");
                return true;
            })
            .fail(function(data) {
                modal.find(".modal-title").text("There was an error...");
                modal
                    .find(".modal-body")
                    .html(
                        "Something has gone wrong when attempting to update the status of your camper queue entry. Please try again."
                    );
            });
    }
</script>