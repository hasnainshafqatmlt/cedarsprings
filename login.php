<?php

/**
 * Plugin Name: Custom Login Plugin
 * Description: A custom login plugin that integrates with Ultracamp API
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
require_once plugin_dir_path(__FILE__) . 'camp-queue-form-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'submit-camper-queue-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'complete-registration-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'classes/PluginLogger.php';

class CustomLoginPlugin
{

    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_custom_login', array($this, 'handle_login_ajax'));
        add_action('wp_ajax_nopriv_custom_login', array($this, 'handle_login_ajax'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_shortcode('custom_login_form', array($this, 'login_form_shortcode'));
        add_shortcode('summer_is_coming', array($this, 'summer_is_coming_shortcode'));
        add_shortcode('status_portal', array($this, 'status_portal_shortcode'));
        add_shortcode('create_account_shortcode', array($this, 'handle_create_account_shortcode'));
        add_shortcode('create_add_person', array($this, 'handle_create_add_person'));
        add_shortcode('create_playpass', array($this, 'handle_create_playpass'));
        // Register logout AJAX
        add_action('wp_ajax_custom_logout', array($this, 'handle_logout_ajax'));
        add_action('wp_ajax_nopriv_custom_logout', array($this, 'handle_logout_ajax'));
        // Register get_campers AJAX (alias for getCampers)
        // here wp_ajax_ "get_campers" is the ajax action name if action "get_campers" then it will call the relevent php method e-g here is "handle_getCampers_ajax"
        add_action('wp_ajax_get_campers', array($this, 'handle_getCampers_ajax'));
        add_action('wp_ajax_nopriv_get_campers', array($this, 'handle_getCampers_ajax'));
        // Register summer schedule AJAX
        add_action('wp_ajax_get_summer_schedule', array($this, 'handle_get_summer_schedule_ajax'));
        add_action('wp_ajax_nopriv_get_summer_schedule', array($this, 'handle_get_summer_schedule_ajax'));
        add_action('wp_ajax_get_camp_capacities', 'get_camp_capacities_callback');
        add_action('wp_ajax_nopriv_get_camp_capacities', 'get_camp_capacities_callback');
        add_action('wp_ajax_cancel', 'handle_putQueueActions_ajax');
        add_action('wp_ajax_nopriv_cancel', 'handle_putQueueActions_ajax');
        add_action('wp_ajax_create_account', 'handle_create_account_ajax');
        add_action('wp_ajax_nopriv_create_account', 'handle_create_account_ajax');
        add_action('wp_ajax_add_person', 'handle_add_person_ajax');
        add_action('wp_ajax_nopriv_add_person', 'handle_add_person_ajax');
        add_action('wp_ajax_getAdditionalTransportationOptions', 'handle_getAdditionalTransportationOptions_ajax');
        add_action('wp_ajax_nopriv_getAdditionalTransportationOptions', 'handle_getAdditionalTransportationOptions_ajax');
        add_action('wp_ajax_getExistingSelections', 'handle_getExistingSelections_ajax');
        add_action('wp_ajax_nopriv_getExistingSelections', 'handle_getExistingSelections_ajax');
        add_action('wp_ajax_getRegisteredWeeks', 'handle_getRegisteredWeeks_ajax');
        add_action('wp_ajax_nopriv_getRegisteredWeeks', 'handle_getRegisteredWeeks_ajax');
        add_action('wp_ajax_getPlayPassPricing', 'handle_getPlayPassPricing_ajax');
        add_action('wp_ajax_nopriv_getPlayPassPricing', 'handle_getPlayPassPricing_ajax');
        add_action('wp_ajax_getPlayPassDays', 'handle_getPlayPassDays_ajax');
        add_action('wp_ajax_nopriv_getPlayPassDays', 'handle_getPlayPassDays_ajax');
    }

    public function init()
    {
        // Initialize plugin
    }

    public function plugin_log($message)
    {
        $log_file = plugin_dir_path(__FILE__) . 'plugin.log';
        $time = date('Y-m-d H:i:s');
        $formatted = "[{$time}] {$message}\n";
        file_put_contents($log_file, $formatted, FILE_APPEND | LOCK_EX);
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery');

        // Tailwind compiled CSS (prefixed to avoid conflicts)
        $tailwind_path = plugin_dir_path(__FILE__) . 'assets/css/tailwind.css';
        if (file_exists($tailwind_path)) {
            wp_enqueue_style(
                'cscamp-tailwind',
                plugin_dir_url(__FILE__) . 'assets/css/tailwind.css?v1',
                array(),
                filemtime($tailwind_path)
            );
        }

        // Only enqueue login script if the create_account_shortcode is NOT present on the current page
        $should_enqueue_login_script = true;

        if (!is_admin()) {
            global $post;

            if ($post instanceof WP_Post && (has_shortcode($post->post_content, 'create_account_shortcode') ||
                has_shortcode($post->post_content, 'create_add_person') ||
                has_shortcode($post->post_content, 'create_playpass')
            )) {
                $should_enqueue_login_script = false;
            }
        }

        wp_enqueue_script('custom-cookie-management', plugin_dir_url(__FILE__) . 'js/cookie-management.js', array('jquery'), '2.0.0', true);
        if ($should_enqueue_login_script) {
            wp_enqueue_script('custom-camp-form-builder', plugin_dir_url(__FILE__) . 'js/form-builder.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('custom-camp-camper-action', plugin_dir_url(__FILE__) . 'js/camper-action.js', array('jquery'), '3.0.0', true);

            wp_enqueue_script('custom-login-js', plugin_dir_url(__FILE__) . 'js/login-action.js', array('jquery'), '1.0.0', true);
            wp_localize_script('custom-login-js', 'custom_login_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('custom_login_nonce')
            ));
            wp_enqueue_style('custom-login-css', plugin_dir_url(__FILE__) . 'css/style.css', array(), '3.0.0');
            wp_localize_script('custom-camp-camper-action', 'custom_camp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }
    }

    public function add_admin_menu()
    {
        add_options_page(
            'Custom Login Settings',
            'Custom Login',
            'manage_options',
            'custom-login-settings',
            array($this, 'admin_page')
        );
    }

    public function admin_init()
    {
        register_setting('custom_login_options', 'custom_login_api_key');
        register_setting('custom_login_options', 'custom_login_camp_id');
        register_setting('custom_login_options', 'custom_login_help_text');
        // Register DB credentials
        register_setting('custom_login_options', 'custom_login_db_host');
        register_setting('custom_login_options', 'custom_login_db_user');
        register_setting('custom_login_options', 'custom_login_db_password');
        register_setting('custom_login_options', 'custom_login_db_name');
    }

    public function admin_page()
    {
?>
        <div class="wrap">
            <h1>Custom Login Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('custom_login_options');
                do_settings_sections('custom_login_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Ultracamp API Key</th>
                        <td>
                            <input type="text" name="custom_login_api_key" value="<?php echo esc_attr(get_option('custom_login_api_key', '7EJWNDKAMG496K9Q')); ?>" class="regular-text" />
                            <p class="description">The API key for Ultracamp authentication</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Camp ID</th>
                        <td>
                            <input type="text" name="custom_login_camp_id" value="<?php echo esc_attr(get_option('custom_login_camp_id', '107')); ?>" class="regular-text" />
                            <p class="description">The Ultracamp camp ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Help Text</th>
                        <td>
                            <textarea name="custom_login_help_text" rows="5" cols="50" class="large-text"><?php echo esc_textarea(get_option('custom_login_help_text', 'Log in to Your Account')); ?></textarea>
                            <p class="description">Text to display above the login form</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">DB Host</th>
                        <td>
                            <input type="text" name="custom_login_db_host" value="<?php echo esc_attr(get_option('custom_login_db_host', 'localhost')); ?>" class="regular-text" />
                            <p class="description">Database host for reservations</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">DB User</th>
                        <td>
                            <input type="text" name="custom_login_db_user" value="<?php echo esc_attr(get_option('custom_login_db_user', '')); ?>" class="regular-text" />
                            <p class="description">Database user for reservations</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">DB Password</th>
                        <td>
                            <input type="password" name="custom_login_db_password" value="<?php echo esc_attr(get_option('custom_login_db_password', '')); ?>" class="regular-text" autocomplete="new-password" />
                            <p class="description">Database password for reservations</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">DB Name</th>
                        <td>
                            <input type="text" name="custom_login_db_name" value="<?php echo esc_attr(get_option('custom_login_db_name', '')); ?>" class="regular-text" />
                            <p class="description">Database name for reservations</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2>Usage</h2>
            <p>Use the shortcode <code>[custom_login_form]</code> to display the login form on any page or post.</p>
        </div>
<?php
    }

    public function login_form_shortcode($atts)
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '<!-- hidden in editor -->';
        }

        PluginLogger::clear();

        require_once __DIR__ . '/tools/SeasonalConfigElements.php';

        $config = new SeasonalConfigElements();

        if (isset($_GET['duckfeet'])) {
            $testmode = true;
        }
        PluginLogger::log('isRegistrationOpen ::' . print_r($config->isRegistrationOpen(), true));

        // Check if registration is open
        if (!$config->isRegistrationOpen() && !isset($testmode)) {
            // Redirect to the summer is coming page
            echo '<script>window.location.href = "/camps/summer_is_coming";</script>';
        }

        $help_text = get_option('custom_login_help_text', 'Log in to Your Account');

        if (!empty($_COOKIE['key']) || !empty($_COOKIE['account'])) {
            echo '<script>window.location.href = "/camps-summer-queue-registration";</script>';
        }

        ob_start();

        // Include the separated template for the login form markup.
        include plugin_dir_path(__FILE__) . 'login-form-template.php';

        return ob_get_clean();
    }

    public function handle_login_ajax()
    {
        check_ajax_referer('custom_login_nonce', 'nonce');

        $user = sanitize_text_field($_POST['user']);
        $pass = sanitize_text_field($_POST['pass']);

        if (!$user || !$pass) {
            wp_die(json_encode(array("StatusCode" => 400, "Message" => "Username or password not provided.")));
        }

        // Include the Ultracamp authentication class
        require_once plugin_dir_path(__FILE__) . 'api/ultracamp/CartAndUser.php';

        // Create a simple logger
        $logger = new UCDummyLogger();
        $uc = new CartAndUser($logger);

        // Authenticate user
        $accountAPI = $uc->authenticateUser($user, $pass);

        // If not authenticated, send response back
        if (!isset($accountAPI['Authenticated']) || $accountAPI['Authenticated'] !== true) {
            wp_die(json_encode($accountAPI));
        }

        // Set custom session flag
        if (!session_id()) {
            session_start();
        }
        $_SESSION['ultracamp_logged_in'] = true;

        // Get contact information for the primary account holder
        $person = $uc->getPrimaryAccountPerson($accountAPI['AccountId']);
        $contact = array('FirstName' => $person->FirstName, 'LastName' => $person->LastName);

        $result = $accountAPI;
        $result['contact'] = $contact;

        wp_die(json_encode($result));
    }

    public function handle_logout_ajax()
    {
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION['ultracamp_logged_in']);
        wp_die(json_encode(['success' => true]));
    }

    public function handle_getCampers_ajax()
    {
        PluginLogger::log("handle_getCampers_ajax start: ");
        include plugin_dir_path(__FILE__) . 'ajax/getCampers.php';
        PluginLogger::log("handle_getCampers_ajax end: ");
    }

    public function handle_get_summer_schedule_ajax()
    {
        require_once plugin_dir_path(__FILE__) . 'ajax/getSummerSchedule.php';
        $futureOnly = isset($_POST['futureOnly']) && $_POST['futureOnly'] === 'true';
        $result = get_summer_schedule($futureOnly);
        wp_die(json_encode($result));
    }

    public function summer_is_coming_shortcode($atts)
    {
        // // Start session at the very beginning of the plugin
        // add_action('init', 'start_session_early', 1);
        // // require_once plugin_dir_path(__FILE__) . 'classes/PluginLogger.php';

        // function start_session_early()
        // {
        //     if (session_status() === PHP_SESSION_NONE) {
        //         session_start();
        //     }
        // }


        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '<!-- hidden in editor -->';
        }

        ob_start();
        include plugin_dir_path(__FILE__) . 'summer_is_coming.php';
        return ob_get_clean();
    }
    public function status_portal_shortcode($atts)
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '<!-- hidden in editor -->';
        }
        ob_start();
        include plugin_dir_path(__FILE__) . 'status_portal.php';
        return ob_get_clean();
    }
    public function handle_create_account_shortcode($atts)
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '<!-- hidden in editor -->';
        }
        ob_start();
        include plugin_dir_path(__FILE__) . 'create-account.php';
        return ob_get_clean();
    }
    public function handle_create_add_person($atts)
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '<!-- hidden in editor -->';
        }
        ob_start();
        include plugin_dir_path(__FILE__) . 'add-person.php';
        return ob_get_clean();
    }
    public function handle_create_playpass($atts)
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '<!-- hidden in editor -->';
        }
        ob_start();
        include plugin_dir_path(__FILE__) . 'playpass.php';
        return ob_get_clean();
    }
}

// Initialize the plugin
new CustomLoginPlugin();

// Conditionally enqueue camp queue form assets if the shortcode is present
add_action('wp', function () {
    global $post;
    if (isset($post) && (has_shortcode($post->post_content, 'custom_camp_queue_form'))) {

        wp_enqueue_style('custom-camp-planner-css', plugin_dir_url(__FILE__) . 'css/planner.css', array(), '1.0.0');
    }
    if (isset($post) && has_shortcode($post->post_content, 'submit_camper_queue')) {
        // Functionality for the transportation segment 
        wp_enqueue_script('custom-transportationManagement', plugin_dir_url(__FILE__) . 'js/transportationManagement.js', array('jquery'), '3.0.0', true);
        // Functionality for the lunch segment
        wp_enqueue_script('custom-lunchSelections', plugin_dir_url(__FILE__) . 'js/lunchSelections.js', array('jquery'), '1.0.0', true);
    }
    if (isset($post) && has_shortcode($post->post_content, 'complete_registration')) {
    }
    if (isset($post) && has_shortcode($post->post_content, 'create_add_person')) {
        wp_enqueue_style('custom-login-css', plugin_dir_url(__FILE__) . 'css/style.css', array(), '3.0.0');
    }
});

function get_camp_capacities_callback()
{
    include plugin_dir_path(__FILE__) . 'ajax/campCapacities.php';
    wp_die();
}
function handle_putQueueActions_ajax()
{
    include plugin_dir_path(__FILE__) . 'ajax/putQueueActions.php';
    wp_die();
}
function handle_create_account_ajax()
{
    include plugin_dir_path(__FILE__) . 'ajax/putCreateAccount.php';
    wp_die();
}
function handle_add_person_ajax()
{
    include plugin_dir_path(__FILE__) . 'ajax/putAddPerson.php';
    wp_die();
}
function handle_getAdditionalTransportationOptions_ajax()
{
    include plugin_dir_path(__FILE__) . 'ajax/getAdditionalTransportationOptions.php';
    wp_die();
}
function handle_getExistingSelections_ajax()
{
    include plugin_dir_path(__FILE__) . 'ajax/getExistingSelections.php';
    wp_die();
}
function handle_getRegisteredWeeks_ajax()
{
    include plugin_dir_path(__FILE__) . 'ajax/getRegisteredWeeks.php';
    wp_die();
}
function handle_getPlayPassPricing_ajax()
{
    include plugin_dir_path(__FILE__) . 'ajax/getPlayPassPricing.php';
    wp_die();
}
function handle_getPlayPassDays_ajax()
{
    include plugin_dir_path(__FILE__) . 'ajax/getPlayPassDays.php';
    wp_die();
}
