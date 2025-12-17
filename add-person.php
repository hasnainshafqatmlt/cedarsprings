<?php
/* Built March / April 2022 as the first pass at the waitlist */
/* Re-Built January / February / March 2023 as the full implementation of a shopping cart / wait list */
session_start();

/** */
$debug = false;
/** */

require_once(plugin_dir_path(__FILE__) . 'counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/addPerson.php', $_SERVER['REMOTE_ADDR']);

// // get the logger
// require_once './logger/plannerLogger.php';
// if($debug) {
//     $logger->pushHandler($dbugStream);
// }

// the ability to ensure the user is logged in
require_once plugin_dir_path(__FILE__) . 'classes/ValidateLogin.php';
$validator = new ValidateLogin($logger);

// check that we're logged in
$key = empty($_COOKIE['key']) ? $_SESSION['key'] : $_COOKIE['key'];
$account = empty($_COOKIE['account']) ? $_SESSION['account'] : $_COOKIE['account'];

$queryParams = '';
if (isset($_GET['duckfeet'])) {
    $queryParams = '?duckfeet=true';
}

if (empty($key) || empty($account) || !$validator->validate($key, $account)) {
    // instruct the grid to collect login info without running to Ultracamp (a second time) to see if the UC session is valid
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    // send the browser back to the grid
    echo '<script>window.location.href = "/camps/queue' . $queryParams . '";</script>';
    return false;
}


?>


<!-- Modal -->

<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="tw-max-w-[900px] tw-mx-auto tw-mt-[160px]" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title tw-mt-0 tw-mb-2 tw-font-bold" id="myModalLabel">...</h4>
            </div>
            <div class="modal-body  tw-text-base">
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

<div id="fh5co-featured">
    <div class="container">
        <div id="camps-wrapper" class="fh5co-grid">
            <?php if (!empty($_COOKIE['modifiedUserName']) && $_COOKIE['modifiedUserName'] != 'undefined') : ?>
                <div class="camp-row tw-bg-[#FFF8F0] tw-p-4">
                    <p style="font-size: 20px; line-height:1.5; text-align:center;">Please note that your new user id for your account has been updated from your email address.<br /><br />Your user name is now: <span class="font-weight:bold"><?= $_COOKIE['modifiedUserName']; ?></span></p>
                </div>
            <?php endif; ?>
            <div class="fh5co-v-half camp-row" id="login-section">
                <div class="fh5co-v-col-2 fh5co-text fh5co-special-1" id="loginBox">
                    <span class="pricing" id="loginInstructions">Add a Camper to your Account</span>
                    <form id="frmAddPerson" onsubmit="return false">
                        <span class="server_error">There was an error processing your request. Please try again.<br /></span>
                        <span class="server_success tw-p-1">Your camper was successfully added to your account.<br />
                            <!--<a href="./" class="btn btn-title-action btn-outline no-top-padding" style="margin-bottom:15px;">return to registration page</a><br />-->
                        </span>


                        <div class="form-group new-account">
                            <div class="new-account-label">
                                <label for="camperFirstName">Campers's First Name</label>
                            </div>
                            <div class="new-account-input">
                                <input type="text" id="camperFirstName" name="camperFirstName" class="form-control new-account bio-info" />
                            </div>
                        </div>

                        <div class="form-group new-account">
                            <div class="new-account-label">
                                <label for="camperLastName">Camper's Last Name</label>
                            </div>
                            <div class="new-account-input">
                                <input type="text" id="camperLastName" name="camperLastName" class="form-control new-account bio-info" />
                            </div>
                        </div>

                        <div class="form-group new-account">
                            <div class="new-account-label">
                                <label for="camperDOB">Camper's Date of Birth</label>
                            </div>
                            <div class="new-account-input">
                                <input type="text" id="camperDOB" name="camperDOB" class="form-control new-account bio-info" />
                            </div>
                        </div>

                        <div class="form-group new-account">
                            <div class="new-account-label">
                                <label for="camperDOB">Camper's Gender</label>
                            </div>
                            <div class="new-account-input tw-flex tw-flex-wrap tw-gap-3">
                                <div class=" tw-flex tw-items-center tw-gap-2">
                                    <input type="radio" id="camperMale" name="camperGender" value="male" />
                                    <label for="camperMale">Male<label>
                                </div>

                                <div class=" tw-flex tw-items-center tw-gap-2">
                                    <input type="radio" id="camperFemale" name="camperGender" value="female" />
                                    <label for="camperFemale">Female<label>
                                </div>
                            </div>
                        </div>

                        <p id="formValidationMsg">&nbsp;</p>
                        <button class="btn btn-title-action btn-outline no-top-padding" id="loginBtn" onclick="return submitAddPerson()">Add Camper</button>
                        <input type="submit" style="display:none" />
                    </form>
                    <a href="/camps/queue/<?= $queryParams ?>" class="credentials">return to registration page</a>

                </div>
                <div class="fh5co-v-col-2 fh5co-bg-img" style="background-image: url(/images/climbing_wall2.jpg)" id="loginBoxImg"></div>

            </div>


        </div>

    </div>

</div>


<script>
    var adminAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';


    var defaultContactFormTopic = "Day Camps";

    $(document).ready(function() {
        // see if we need to alert regarding an updated user name
        if (checkCookie('modifiedUserName')) {
            deleteCookie('modifiedUserName');
        }
    });
</script>
<style>
    @media (max-width: 768px) {
        #login-section {
            padding: 0px !important;
            margin: 0;
        }

        #fh5co-featured .container {
            padding: 0;
        }
    }
</style>

<?php
// <!-- Bootstrap DateTimePicker -->
wp_enqueue_script('bootstrap-min-js', plugin_dir_url(__FILE__) . 'js/bootstrap.min.js', array('jquery'), '2.0.0', true);
wp_enqueue_script('modalDialog-js', plugin_dir_url(__FILE__) . 'js/modalDialog.js', array('jquery'), '1.0.0', true);
wp_enqueue_script('addPerson-js', plugin_dir_url(__FILE__) . 'js/addPerson.js', array('jquery'), '5.0.0', true);
wp_enqueue_style('bootstrap-modal-css', plugin_dir_url(__FILE__) . 'css/bootstrap-modal.css', array(), '1.0.0');
wp_enqueue_style('custom-camp-planner-css', plugin_dir_url(__FILE__) . 'css/planner.css', array(), '1.0.0');
?>