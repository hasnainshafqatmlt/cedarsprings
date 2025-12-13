<?php
// Register the camp queue form shortcode and enqueue assets only when used
require_once plugin_dir_path(__FILE__) . 'classes/PluginLogger.php';
add_action('init', function () {
    add_shortcode('custom_camp_queue_form', 'custom_camp_queue_form_shortcode');
});

function custom_camp_queue_form_shortcode($atts)
{
    if (is_admin()) return;

    if (isset($_GET['duckfeet'])) {
        $testmode = true;
    }
    // Get dynamic logo URL
    $logo_image_url = home_url('/wp-content/uploads/2025/03/Logo.svg');
    // Get dynamic background image URL
    $background_image_url = home_url('/wp-content/uploads/2025/07/SUMMER-CAMPS-2.webp');
    // Enqueue JS and CSS only for this shortcode
    ob_start();
?>
    <div id="camp-queue-form-wrapper">
        <div class="camper-hero-section" style="background-image: url('<?php echo esc_url($background_image_url); ?>') ;">
            <div class="logo">
                <img src="<?php echo esc_url($logo_image_url); ?>" />
            </div>
            <h2>Cedar Springs Summer Registration</h2>
            <p>Welcome, <span id="contactName"></span></p>
            <div class="logout-link"><a href="javascript:" onclick="return customLogout()" class="text-white">LOG OUT</a></div>
            <div>
                <button type="button" class="btn btn-info">
                    Current Registrations and Queues
                </button>
            </div>
            <div>
                <button type="button" class="btn btn-primary">
                    Add A Camper
                </button>
            </div>
        </div>

        <div id="legend">
            <h1 class="camper-heading">Registration HQ</h1>
            <div class="camper-head-guide">
                <h2>Register Susie for Camp</h2>
                <div class="camper-guide-grid">
                    <div class="guide-block">
                        <i class="check-circle available"></i>
                        <span>Available</span>
                    </div>
                    <div class="guide-block">
                        <i class="check-circle booked"></i>
                        <span>You’re booked!</span>
                    </div>
                    <div class="guide-block">
                        <i class="check-circle conflict"></i>
                        <span>Schedule conflict</span>
                    </div>
                    <div class="guide-block">
                        <i class="check-circle waiting-available"></i>
                        <span>Waitlist Only Available</span>
                    </div>
                    <div class="guide-block">
                        <i class="check-circle waiting-list"></i>
                        <span>You’re on the waitlist!</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="fh5co-v-half camp-row" id="camper-section">
            <form id="camperQueueForm" name="camperQueueForm" method="post" action="<?php echo home_url('/camps/queue/submitcamperqueue/'); ?>" onSubmit="setLoading();">
                <input type="hidden" id="showSiblingOptions" name="showSiblingOptions" value='false' />
                <div class="fh5co-v-col-4 fh5co-text fh5co-special-1" id="amper-section">
                    <div class="table-responsive" id="fullsized-grid">
                        <table id="campGridTable" class="table grid-table">
                            <!-- Javascript adds camp rows here with form-builder.js -->
                        </table>
                    </div>
                </div>
            </form>
        </div>
        <div class="fh5co-v-half camp-row" id="loading-section" style="display:none;">
            <div class="fh5co-v-col-4 fh5co-text fh5co-special-1" id="loadingBox">
                <span class="pricing" style="padding: 0 !important;"><img src="/wp-content/uploads/2025/12/loading-animated.svg" id="loading-img" /> Loading</span>
            </div>
        </div>
        <div style="text-align:center; margin:10px 10px 30px 10px; width:100%;" id="bottomSubmit">
            <button class="btn btn-submit" style="margin:10px" id='formSubmitBtn2'>Submit Your Choices</button>
        </div>
    </div>
    <script>
        // get the camp capacities via AJAX
        jQuery(document).ready(function($) {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'get_camp_capacities'
                },
                dataType: 'json',
                success: function(response) {
                    window.capacities = response;
                    console.log('Capacities added:');
                },
                error: function(xhr, status, error) {
                    console.error('Failed to fetch capacities:', error);
                }
            });
        });
    </script>
    <script>
        var defaultContactFormTopic = "Day Camps";
        // $("#contact-wrap").load('/partials/contact.html');
        // $("#footer-wrap").load('/partials/footer.html');


        jQuery(document).ready(function() {
            // jQuery("#formSubmitBtn").click(function () {
            // 	jQuery('#camperQueueForm').submit();
            // });
            jQuery("#formSubmitBtn2").click(function() {
                jQuery('#camperQueueForm').submit();
            });
        });

        // toggle the legend on and off
        function toggleLegend() {
            var content = document.getElementById('legend-content');
            var arrow = document.getElementById('arrow');
            if (content.style.display === 'none') {
                content.style.display = 'block';
                arrow.style.transform = 'rotate(180deg)';
            } else {
                content.style.display = 'none';
                arrow.style.transform = 'rotate(0deg)';
            }
        }

        // get the camp capacities - this is to manage the toggle of registration to camper queue and back if
        // more siblings are attempting to get into a camp than there are available spots
    </script>
    <script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'js/capacityManagement.js'); ?>"></script>
<?php
    return ob_get_clean();
}
