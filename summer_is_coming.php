<?php


require_once(plugin_dir_path(__FILE__) . 'counter/view_counter.php');
require_once(__DIR__ . '/tools/SeasonalConfigElements.php');
$config = new SeasonalConfigElements();

$counter = new ViewCounter();
$counter->recordVisit('/camps/summer/summer_is_coming.php/', $_SERVER['REMOTE_ADDR']);

// timezone is set in the counter page for west coast
// Get the current date
$now = new DateTime();

// Extract month and day
$month = intval($now->format('m'));
$day = intval($now->format('d'));

// Check if the current date is between January 1st and August 30th
if ($config->isRegistrationOpen()) {
    // Redirect to the index page
    echo '<script>window.location.href = "/camps/summer";</script>';
    // Use return when included in shortcode, exit when called directly
    if (defined('ABSPATH')) {
        return; // Being included in WordPress shortcode
    } else {
        exit; // Being called directly
    }
}

?>


<div id="fh5co-container">
    <div id="fh5co-home" class="js-fullheight" data-section="home">

        <div class="p-rel">

            <div class="fh5co-overlay"></div>
            <div class="is-coming-main ">
                <div class="container-is-coming p-rel">
                    <div class="row">

                        <img class="to-animate" src="/wp-content/uploads/2025/12/cedar-springs-logo.png" style="width:20%; max-width:500px;">
                        <br /> <br />

                        <h2 class="to-animate">
                            Summer Camp Registration Opens January 1<sup>st</sup>!
                        </h2>
                    </div>

                    <div class="to-animate time-counter-block">

                        <div class="col-md-1 col-md-offset-4 "><span id="countdownDay" class="countdownValue"></span><br />
                            <span class="countdownLbl">days</span>
                        </div>

                        <div class="col-md-1"><span id="countdownHour" class="countdownValue"></span><br />
                            <span class="countdownLbl">hours</span>
                        </div>

                        <div class="col-md-1"><span id="countdownMinute" class="countdownValue"></span><br />
                            <span class="countdownLbl">min</span>
                        </div>

                        <div class="col-md-1"><span id="countdownSecond" class="countdownValue"></span><br />
                            <span class="countdownLbl">sec</span>
                        </div>

                    </div>

                    <div class="to-animate row" style="margin-top: 25px">
                        <a href="../mail" class="btn btn-primary ">Join the Mailing List</a>
                    </div>
                    <div class="to-animate row" style="margin-top: 25px">
                        <a href="../summer" class="btn btn-primary ">View the Summer Catalog</a>
                    </div>
                    <!--
                            <div class="to-animate row" style="margin-top: 25px">
                                <a href="../../brochure" class="btn btn-primary ">The 2024 Summer Brochure</a>
                            </div>
                            -->

                </div>
            </div>



        </div>

    </div>


    <!--		
		<div id="fh5co-type" data-section="book" style="background-image: url(/images/wood_2.jpg);" data-stellar-background-ratio="1">
            <div class="fh5co-overlay"></div>
            <div class="container">
                <div class="row text-center fh5co-heading row-padded">
                    <h2 class="heading" style="color:#ffffff;">Things to do while you wait</h2>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="fh5co-type">
                            <a href="/camps/summer"><h3 class="with-icon icon-1">Preview Day Camps for 2024</h3></a>
                            <p>Take an advanced look at the camp options available for 2024!</p>
                            <a href="/camps/summer" class="btn btn-primary btn-outline">Explore</a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="fh5co-type">
                            <a href="/parties/"><h3 class="with-icon icon-7">Book a Party</h3></a>
                            <p>Host a Battlefield Live laser tag party or get-together with us!</p>
                            <a href="/camps/" class="btn btn-primary btn-outline">Party!</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    -->
    <div id="contact-wrap"></div>

</div>

<?php
// <!-- Bootstrap DateTimePicker -->
wp_enqueue_script('custom-moment-js', plugin_dir_url(__FILE__) . 'js/plugin/moment.js', array('jquery'), '1.0.0', true);
wp_enqueue_script('custom-bootstrap-datetimepicker-min-js', plugin_dir_url(__FILE__) . 'js/plugin/bootstrap-datetimepicker.min.js', array('jquery'), '1.0.0', true);
?>

<script>
    function CountDownTimer() {
        var now = new Date();
        var year = now.getFullYear();
        var month = now.getMonth() + 1; // JavaScript months are 0-11
        var day = now.getDate();

        // Determine if the current date is within the window
        var isWithinWindow = (month === 1 && day >= 1) || (month > 1 && month < 8) || (month === 8 && day <= 30);

        // Set the target date to the next January 1st
        var targetYear = year + 1;
        var end = new Date(`1/1/${targetYear} 12:00 AM`);

        var _second = 1000;
        var _minute = _second * 60;
        var _hour = _minute * 60;
        var _day = _hour * 24;
        var timer;

        function showRemaining() {
            var now = new Date();
            var distance = end - now;
            if (distance < 0) {
                clearInterval(timer);
                window.location.replace("/camps/summer/queue");
                console.log("Redirecting to camps page as we're within the registratin window.")
                return;
            }

            var days = Math.floor(distance / _day);
            var hours = Math.floor((distance % _day) / _hour);
            var minutes = Math.floor((distance % _hour) / _minute);
            var seconds = Math.floor((distance % _minute) / _second);

            document.getElementById('countdownDay').innerHTML = days;
            document.getElementById('countdownHour').innerHTML = hours;
            document.getElementById('countdownMinute').innerHTML = minutes;
            document.getElementById('countdownSecond').innerHTML = seconds;
        }

        timer = setInterval(showRemaining, 1000);
    }

    CountDownTimer();
</script>