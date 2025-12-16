<?php
/* Built March / April 2022 as the first pass at the waitlist */
/* Re-Built January / February 2023 as the full implementation of a shopping cart / wait list */
session_start();


require_once(plugin_dir_path(__FILE__) . 'counter/view_counter.php');

$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/createAccount', $_SERVER['REMOTE_ADDR']);
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

<div class="fh5co-v-col-2 fh5co-text fh5co-special-1 tw-max-w-[600px] tw-bg-white tw-rounded-[20px] tw-mx-auto tw-py-[20px] tw-px-[52px] tw-box-border" id="loginBox">
    <h2 class="pricing tw-text-[#3B89F0] tw-font-bold tw-text-center tw-text-[22px]" id="loginInstructions">Create an Account</h2>

    <form onsubmit="return false">

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="parentFirstName">Parent's First Name <span class="required-field">*</span></label>
            </div>
            <div class="new-account-input">
                <input type="text" id="parentFirstName" name="parentFirstName" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="parentLastName">Parent's Last Name <span class="required-field">*</span></label>
            </div>
            <div class="new-account-input">
                <input type="text" id="parentLastName" name="parentLastName" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="phoneNumber">Cell Phone Number <span class="required-field">*</span></label>
                <a href=# data-toggle="modal" data-target="#myModal" data-msg="cellphone"><img src="<?= plugin_dir_url(__FILE__) ?>/images/question-icon.svg" alt="Why Cell Phone?" class="question-icon" /></a>
            </div>
            <div class="new-account-input">
                <input type="text" id="phoneNumber" name="phoneNumber" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="emailAddress">Email Address <span class="required-field">*</span></label>
            </div>
            <div class="new-account-input">
                <input type="email" id="emailAddress" name="emailAddress" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="password">Password <span class="required-field">*</span></label>
                <a href=# data-toggle="modal" data-target="#myModal" data-msg="passwordReqs"><img src="<?= plugin_dir_url(__FILE__) ?>/images/question-icon.svg" alt="Password Requirements?" class="question-icon" /></a>
            </div>
            <div class="new-account-input">
                <input type="password" id="password" name="password" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="passwordConfirm">Confirm Password <span class="required-field">*</span></label>
            </div>
            <div class="new-account-input">
                <input type="password" id="passwordConfirm" name="passwordConfirm" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="address">Street Address <span class="required-field">*</span></label>
            </div>
            <div class="new-account-input">
                <input type="text" id="address" name="address" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="city">City <span class="required-field">*</span></label>
            </div>
            <div class="new-account-input">
                <input type="text" id="city" name="city" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="state">State <span class="required-field">*</span></label>
            </div>
            <div class="new-account-input">
                <input type="text" id="state" name="state" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <div class="form-group new-account">
            <div class="new-account-label">
                <label for="zip">Zip Code <span class="required-field">*</span></label>
            </div>
            <div class="new-account-input">
                <input type="text" id="zip" name="zip" class="form-control new-account " required
                    aria-required="true" />
            </div>
        </div>

        <p id="formValidationMsg"></p>

        <div id='server_error_block' class=" tw-text-base">
            <div class="server_error">There was an error processing your request. Please try again.<br /></div>
            <div class="tw-mb-2 tw-inline-block">
                <div class="ultracamp_error "></div>
            </div>
        </div>

        <button class="tw-btn-secondary tw-font-bold tw-w-full" id="loginBtn" onclick="return submitCreateAccount()">Create Account</button>
        <input type="submit" style="display:none" />
    </form>
    <div class=" tw-text-center tw-w-full tw-text-sm tw-text-[#0073aa]">

        <a href="/camps/queue/" class=" tw-text-base tw-no-underline tw-not-italic tw-mt-3 tw-inline-block  hover:tw-text-[#0fa514]  ">return to login</a>
    </div>

</div>
<script>
    var adminAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
</script>

<?php
// <!-- Bootstrap DateTimePicker -->
wp_enqueue_script('bootstrap-min-js', plugin_dir_url(__FILE__) . 'js/bootstrap.min.js', array('jquery'), '2.0.0', true);
wp_enqueue_script('modalDialog-js', plugin_dir_url(__FILE__) . 'js/modalDialog.js', array('jquery'), '1.0.0', true);
wp_enqueue_script('createAccount-js', plugin_dir_url(__FILE__) . 'js/createAccount.js', array('jquery'), '4.0.0', true);
wp_enqueue_style('bootstrap-modal-css', plugin_dir_url(__FILE__) . 'css/bootstrap-modal.css', array(), '1.0.0');
wp_enqueue_style('custom-camp-planner-css', plugin_dir_url(__FILE__) . 'css/planner.css', array(), '1.0.0');
?>