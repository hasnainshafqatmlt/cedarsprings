<?php
// Template for the [custom_login_form] shortcode.
// Expects $help_text to be defined by the caller before including this file.
?>
<div id="login-section">
    <div id="loginBox">
        <span class="pricing" id="loginInstructions"><?php echo esc_html($help_text); ?></span>
        <form>
            <span class="invalid_credentials" style="display:none;">The email address or password is incorrect.<br /></span>
            <span class="server_error" style="display:none;">There was an error processing your request. Please try again.<br /></span>
            <div class="login-field-block">
                <label>Username/Email:</label>
                <input type="text" id="userEmail" name="userEmail" />
            </div>
            <div class="login-field-block">
                <label>Password:</label>
                <input type="password" id="userPassword" name="userPassword" />
            </div>
            <br />
            <button class="btn btn-title-action btn-outline no-top-padding" id="loginBtn" onclick="return submitLoginForm()">Log In</button>
            <a href="#" class="btn-blue">create account</a>
            <input type="submit" style="display:none" />
        </form>
        <span>&nbsp;&nbsp;&nbsp;</span>
        <a href="#" class="credentials" data-toggle="modal" data-target="#myModal" data-msg="resetAccount">Forgot your login?</a>
    </div>
</div>

<!-- Loading DIV -->
<div id="loading-section" style="display:none;">
    <div id="loadingBox">
        <span class="pricing" style="padding: 0 !important;">Working . . . </span>
    </div>
</div>

<!-- Success Section -->
<div id="loggedin-section" style="display:none;">
    <div id="contactName"></div>
    <div id="campGrid"></div>
    <a href="#" onclick="return customLogout()">log out</a>
</div>

<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="tw-max-w-[900px] tw-mx-auto tw-mt-[160px]" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title tw-mt-0 tw-mb-2 tw-text-[#3B89F0]" id="myModalLabel">...</h4>
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

<?php
// <!-- Bootstrap DateTimePicker -->
wp_enqueue_script('bootstrap-min-js', plugin_dir_url(__FILE__) . 'js/bootstrap.min.js', array('jquery'), '2.0.0', true);
wp_enqueue_script('modalDialog-js', plugin_dir_url(__FILE__) . 'js/modalDialog.js', array('jquery'), '1.0.0', true);
wp_enqueue_style('bootstrap-modal-css', plugin_dir_url(__FILE__) . 'css/bootstrap-modal.css', array(), '1.0.0');
?>