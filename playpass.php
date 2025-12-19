<?php
// Play Pass registration page for Cedar Springs Camper Queue
// Allows registration for individual days (1-5) within a camp week

session_start();

require_once(plugin_dir_path(__FILE__) . 'counter/view_counter.php');
$counter = new ViewCounter();
$counter->recordVisit('/camps/queue/playpass.php', $_SERVER['REMOTE_ADDR']);

// Get the logger
// require_once './logger/plannerLogger.php';
// $logger->pushHandler($dbugStream);


// Require necessary class files
require_once __DIR__ . '/classes/CQModel.php';
require_once __DIR__ . '/classes/ValidateLogin.php';
require_once __DIR__ . '/includes/ultracamp.php';
require_once __DIR__ . '/classes/PlayPassManager.php'; // New class we'll create

// Load configuration
require_once __DIR__ . '/tools/SeasonalConfigElements.php';
$config = new SeasonalConfigElements($logger);

$testmode = true;
// Check if registration is open
if (!$config->isRegistrationOpen() && !isset($testmode)) {
    // Redirect to the summer is coming page
    echo '<script>window.location.href = "/camps/summer_is_coming";</script>';
    exit;
}

// Initialize necessary classes
$validator = new ValidateLogin($logger);
$uc = new UltracampModel($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Confirm that the user is still logged in and their session has not expired
print_r($_COOKIE);
if (empty($_COOKIE['key']) || empty($_COOKIE['account']) || !$validator->validate($_COOKIE['key'], $_COOKIE['account'])) {
    // Instruct the grid to collect login info
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    // Set redirect cookie to return to playpass.php after login
    setCookie('redirectAfterLogin', 'playpass', time() + 3600, '/camps/queue');
    // Send the browser back to the grid
    echo '<script>window.location.href = "/camps/queue";</script>';
    exit;
}

// Get available weeks
$summerWeeks = $playPassManager->getAvailableWeeks();

// Get pricing information for display
$defaultWeek = !empty($summerWeeks) ? $summerWeeks[0]['week_num'] : 1;
$dayCost = $playPassManager->getPlayPassDayCost($defaultWeek);
$lunchCost = $playPassManager->getPlayPassLunchCost($defaultWeek);
$extCareCost = $playPassManager->getPlayPassExtCareCost($defaultWeek);

// Format costs as currency
$dayCostFormatted = '$' . number_format($dayCost, 2);
$lunchCostFormatted = '$' . number_format($lunchCost, 2);
$extCareCostFormatted = '$' . number_format($extCareCost, 2);
?>

<!-- Main content section -->
<div id="fh5co-featured" style="background-image: url(/images/wood_1.png); background-position: 0px 0px; padding-top: 0;">
    <div class="container">
        <!-- Registration steps indicator -->
        <div class="registration-steps">
            <div class="step active" id="step-1">
                <div class="step-number">1</div>
                <div class="step-label">Select Camper</div>
            </div>
            <div class="step" id="step-2">
                <div class="step-number">2</div>
                <div class="step-label">Choose Week</div>
            </div>
            <div class="step" id="step-3">
                <div class="step-number">3</div>
                <div class="step-label">Select Days</div>
            </div>
            <div class="step" id="step-4">
                <div class="step-number">4</div>
                <div class="step-label">Transportation</div>
            </div>
            <div class="step" id="step-5">
                <div class="step-number">5</div>
                <div class="step-label">Add Options</div>
            </div>
            <div class="step" id="step-6">
                <div class="step-number">6</div>
                <div class="step-label">Review</div>
            </div>
        </div>


        <!-- Navigation links -->
        <div class="play-pass-navigation">
            <a href="/camps/queue/" class="btn">Return to Camp Grid</a>
            <a href="/camps/queue/status/" class="btn">View Registrations</a>
        </div>

        <!-- Messages container for success/error messages -->
        <div id="messages-container">
            <?php if (isset($_SESSION['playPassMessage'])): ?>
                <div class="play-pass-message success">
                    <?php echo $_SESSION['playPassMessage']; ?>
                </div>
                <?php unset($_SESSION['playPassMessage']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['playPassError'])): ?>
                <div class="play-pass-message error">
                    <?php echo $_SESSION['playPassError']; ?>
                </div>
                <?php unset($_SESSION['playPassError']); ?>
            <?php endif; ?>
        </div>

        <!-- Main form -->
        <form id="playPassForm" method="post" action="processPlayPass.php">
            <!-- Camper selection section -->
            <div class="play-pass-section" id="camper-selection">
                <h3>1. Select Camper</h3>
                <div class="camper-list">
                    <?php echo $playPassManager->generateCamperOptions($_COOKIE['account']); ?>
                </div>
            </div>

            <!-- Week selection section -->
            <div class="play-pass-section" id="week-selection" style="display: none;">
                <h3>2. Choose Week</h3>
                <div class="week-list">
                    <?php foreach ($summerWeeks as $week): ?>
                        <div class="week-option" onclick="selectWeek(this, <?php echo $week['week_num']; ?>)">
                            <input type="radio" name="selected_week" id="week-<?php echo $week['week_num']; ?>" value="<?php echo $week['week_num']; ?>" data-week-num="<?php echo $week['week_num']; ?>">
                            <label for="week-<?php echo $week['week_num']; ?>"><?php echo $week['short_name']; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Day selection section (displayed dynamically based on week selection) -->
            <div class="play-pass-section" id="day-selection" style="display: none;">
                <h3>3. Select Days</h3>
                <div class="day-list">
                    <!-- Day options will be loaded here via JavaScript -->
                </div>
            </div>

            <!-- Transportation Selection -->
            <div class="play-pass-section" id="transportation-section" style="display: none;">
                <h3>4. Select Transportation</h3>
                <div class="transportation-options">
                    <p class="transport-note">Play Pass campers are dropped off and picked up at our Lake Stevens campus.
                        Currently, bus service is not available for Play Pass campers. Please select your preferred drop-off and pick-up window:</p>

                    <div class="transport-options-grid">
                        <div class="transport-option">
                            <input type="radio" name="transportation_window" id="window-a" value="Window A">
                            <label for="window-a">
                                <h4>Window A</h4>
                                <div class="transport-times">
                                    <p><strong>Drop-off:</strong> 8:00 AM - 8:30 AM</p>
                                    <p><strong>Pick-up:</strong> 4:00 PM - 4:15 PM</p>
                                </div>
                            </label>
                        </div>

                        <div class="transport-option">
                            <input type="radio" name="transportation_window" id="window-b" value="Window B" checked>
                            <label for="window-b">
                                <h4>Window B</h4>
                                <div class="transport-times">
                                    <p><strong>Drop-off:</strong> 9:00 AM - 9:30 AM</p>
                                    <p><strong>Pick-up:</strong> 5:00 PM - 5:15 PM</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="transport-info">
                        <p><i class="icon-info-circle"></i> This selection applies to all days in your Play Pass registration.
                            Consistency in drop-off and pick-up times helps us ensure a smooth experience for all campers.</p>
                    </div>
                </div>
            </div>

            <!-- Options section (displayed after transportation selection) -->
            <div class="play-pass-section" id="options-section" style="display: none;">
                <h3>5. Additional Options</h3>

                <!-- Hot lunch options -->
                <div class="option-group" id="lunch-options">
                    <h4>Hot Lunch (<?php echo $lunchCostFormatted; ?>/day)</h4>
                    <div class="lunch-options-container">
                        <!-- Lunch options populated via JavaScript -->
                    </div>
                </div>

                <!-- Extended care options -->
                <div class="option-group" id="extended-care-options">
                    <h4>Extended Care</h4>
                    <div class="extended-care-container">
                        <!-- Extended care options populated via JavaScript -->
                    </div>
                </div>

                <!-- Friends section -->
                <div class="option-group" id="friends-section">
                    <h4>Friends at Camp</h4>
                    <p class="friend-description">We believe that camp is better with friends. If you have friends attending camp with you, and you would like to ensure that they are placed in the same pod as your camper, please enter their full first and last name here. You can list several friends, if you separate their names with a comma.</p>
                    <div class="friends-container">
                        <textarea name="friends" id="friends-input" class="form-control" rows="3" placeholder="e.g., John Smith, Jane Doe"></textarea>
                    </div>
                </div>

                <!-- Cost summary section -->
                <div class="cost-summary" id="cost-summary" style="display: none;">
                    <div id="cost-breakdown">
                        <!-- Will be populated dynamically via JavaScript -->
                    </div>
                </div>

                <!-- Submit button -->
                <button type="submit" class="btn btn-title-action">Add to Cart</button>
            </div>
        </form>

        <!-- Current Selections -->
        <?php if (
            isset($_SESSION['playPassSelections']) && !empty($_SESSION['playPassSelections']) ||
            isset($_SESSION['playPassEdits']) && !empty($_SESSION['playPassEdits'])
        ): ?>
            <div class="play-pass-section" id="current-selections">
                <h3>6. Review Your Selections</h3>
                <div class="selections-list">
                    <?php
                    $totalCost = 0;

                    // Process regular selections (new registrations)
                    if (isset($_SESSION['playPassSelections']) && !empty($_SESSION['playPassSelections'])):
                        foreach ($_SESSION['playPassSelections'] as $index => $selection):
                            $camper = $CQModel->getCamperName($selection['data']['camper_id']);
                            $week = $CQModel->getWeek($selection['data']['week']);
                            $dayCount = count($selection['data']['days']);
                            $lunchCount = count($selection['data']['lunch'] ?? []);
                            $morningCareCount = count($selection['data']['morning_care'] ?? []);
                            $afternoonCareCount = count($selection['data']['afternoon_care'] ?? []);
                            $transportationWindow = $selection['data']['transportation_window'] ?? 'Window B';

                            // Calculate costs
                            $dayCost = $playPassManager->getPlayPassDayCost($selection['data']['week']);
                            $lunchCost = $playPassManager->getPlayPassLunchCost($selection['data']['week']);
                            $extCareCost = $playPassManager->getPlayPassExtCareCost($selection['data']['week']);

                            $selectionCost = ($dayCount * $dayCost) +
                                ($lunchCount * $lunchCost) +
                                (($morningCareCount + $afternoonCareCount) * $extCareCost);

                            $totalCost += $selectionCost;
                    ?>
                            <div class="selection-item">
                                <div class="selection-info">
                                    <strong><?php echo $camper['FirstName'] . ' ' . $camper['LastName']; ?></strong>:
                                    <?php echo $week; ?> - <?php echo $dayCount; ?> day<?php echo $dayCount !== 1 ? 's' : ''; ?>
                                    <?php if ($lunchCount > 0): ?>
                                        with <?php echo $lunchCount; ?> lunch<?php echo $lunchCount !== 1 ? 'es' : ''; ?>
                                    <?php endif; ?>
                                    <?php if ($morningCareCount > 0 || $afternoonCareCount > 0): ?>
                                        and
                                        <?php
                                        $careOptions = [];
                                        if ($morningCareCount > 0) $careOptions[] = $morningCareCount . ' morning care';
                                        if ($afternoonCareCount > 0) $careOptions[] = $afternoonCareCount . ' afternoon care';
                                        echo implode(', ', $careOptions);
                                        ?>
                                    <?php endif; ?>
                                    <div class="selection-transport">
                                        Transportation: <?php echo ($transportationWindow === 'Window A') ?
                                                            'Drop-off 8:00-8:30 AM, Pick-up 4:00-4:15 PM' :
                                                            'Drop-off 9:00-9:30 AM, Pick-up 5:00-5:15 PM'; ?>
                                    </div>
                                    <div class="selection-cost">Cost: $<?php echo number_format($selectionCost, 2); ?></div>
                                </div>
                                <button type="button" class="btn btn-remove"
                                    onclick="removeSelection(<?php echo $index; ?>)">Remove</button>
                            </div>
                    <?php endforeach;
                    endif; ?>

                    <?php
                    // Process edits to existing registrations
                    if (isset($_SESSION['playPassEdits']) && !empty($_SESSION['playPassEdits'])):
                        foreach ($_SESSION['playPassEdits'] as $editId => $editData):
                            $camper = $editData['camper_info'];
                            $week = $CQModel->getWeek($editData['new']['week']);

                            // Original data
                            $originalDayCount = count($editData['original']['days']);
                            $originalLunchCount = count($editData['original']['lunch'] ?? []);
                            $originalMorningCareCount = count($editData['original']['morning_care'] ?? []);
                            $originalAfternoonCareCount = count($editData['original']['afternoon_care'] ?? []);
                            $originalTransportWindow = $editData['original']['transportation_window'] ?? 'Window B';

                            // New data
                            $newDayCount = count($editData['new']['days']);
                            $newLunchCount = count($editData['new']['lunch'] ?? []);
                            $newMorningCareCount = count($editData['new']['morning_care'] ?? []);
                            $newAfternoonCareCount = count($editData['new']['afternoon_care'] ?? []);
                            $newTransportWindow = $editData['new']['transportation_window'] ?? 'Window B';

                            // Calculate costs
                            $dayCost = $playPassManager->getPlayPassDayCost($editData['new']['week']);
                            $lunchCost = $playPassManager->getPlayPassLunchCost($editData['new']['week']);
                            $extCareCost = $playPassManager->getPlayPassExtCareCost($editData['new']['week']);

                            // Calculate original cost
                            $originalCost = ($originalDayCount * $dayCost) +
                                ($originalLunchCount * $lunchCost) +
                                (($originalMorningCareCount + $originalAfternoonCareCount) * $extCareCost);

                            // Calculate new cost
                            $newCost = ($newDayCount * $dayCost) +
                                ($newLunchCount * $lunchCost) +
                                (($newMorningCareCount + $newAfternoonCareCount) * $extCareCost);

                            // Calculate cost difference
                            $costDifference = $newCost - $originalCost;
                            $totalCost += $costDifference;

                            // Determine days added/removed
                            $daysAddedCount = 0;
                            $daysRemovedCount = 0;

                            foreach ($editData['new']['days'] as $day) {
                                if (!in_array($day, $editData['original']['days'])) {
                                    $daysAddedCount++;
                                }
                            }

                            foreach ($editData['original']['days'] as $day) {
                                if (!in_array($day, $editData['new']['days'])) {
                                    $daysRemovedCount++;
                                }
                            }

                            // Check for transportation window change
                            $transportChanged = $originalTransportWindow !== $newTransportWindow;
                    ?>
                            <div class="selection-item edit-item">
                                <div class="selection-info">
                                    <strong><?php echo $camper['FirstName'] . ' ' . $camper['LastName']; ?></strong>:
                                    <?php echo $week; ?> - Registration Changes
                                    <div class="edit-details">
                                        <?php if ($daysAddedCount > 0 || $daysRemovedCount > 0): ?>
                                            <div class="edit-days">
                                                <?php if ($daysAddedCount > 0): ?>
                                                    <span class="added">+<?php echo $daysAddedCount; ?> day<?php echo $daysAddedCount !== 1 ? 's' : ''; ?> added</span>
                                                <?php endif; ?>
                                                <?php if ($daysRemovedCount > 0): ?>
                                                    <span class="removed">-<?php echo $daysRemovedCount; ?> day<?php echo $daysRemovedCount !== 1 ? 's' : ''; ?> removed</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php
                                        $lunchAdded = $newLunchCount - $originalLunchCount;
                                        $morningCareAdded = $newMorningCareCount - $originalMorningCareCount;
                                        $afternoonCareAdded = $newAfternoonCareCount - $originalAfternoonCareCount;

                                        if ($lunchAdded !== 0): ?>
                                            <div class="edit-lunch">
                                                <?php if ($lunchAdded > 0): ?>
                                                    <span class="added">+<?php echo $lunchAdded; ?> lunch<?php echo $lunchAdded !== 1 ? 'es' : ''; ?> added</span>
                                                <?php else: ?>
                                                    <span class="removed"><?php echo $lunchAdded; ?> lunch<?php echo abs($lunchAdded) !== 1 ? 'es' : ''; ?> removed</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($morningCareAdded !== 0): ?>
                                            <div class="edit-morning-care">
                                                <?php if ($morningCareAdded > 0): ?>
                                                    <span class="added">+<?php echo $morningCareAdded; ?> morning care added</span>
                                                <?php else: ?>
                                                    <span class="removed"><?php echo $morningCareAdded; ?> morning care removed</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($afternoonCareAdded !== 0): ?>
                                            <div class="edit-afternoon-care">
                                                <?php if ($afternoonCareAdded > 0): ?>
                                                    <span class="added">+<?php echo $afternoonCareAdded; ?> afternoon care added</span>
                                                <?php else: ?>
                                                    <span class="removed"><?php echo $afternoonCareAdded; ?> afternoon care removed</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($transportChanged): ?>
                                            <div class="edit-transport">
                                                <span class="changed">Transportation window changed:
                                                    <?php echo ($newTransportWindow === 'Window A') ?
                                                        'Now Drop-off 8:00-8:30 AM, Pick-up 4:00-4:15 PM' :
                                                        'Now Drop-off 9:00-9:30 AM, Pick-up 5:00-5:15 PM'; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="selection-cost <?php echo $costDifference >= 0 ? 'cost-added' : 'cost-removed'; ?>">
                                        Cost change: <?php echo $costDifference >= 0 ? '+' : ''; ?>$<?php echo number_format($costDifference, 2); ?>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-remove"
                                    onclick="removeEdit('<?php echo $editId; ?>')">Remove</button>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>

                <div class="total-section">
                    <div class="total-label">Total <?php echo isset($_SESSION['playPassEdits']) && !empty($_SESSION['playPassEdits']) ? 'Cost Change' : 'Cost'; ?>:</div>
                    <div class="total-amount <?php echo $totalCost >= 0 ? 'cost-added' : 'cost-removed'; ?>">
                        <?php echo $totalCost >= 0 ? '+' : ''; ?>$<?php echo number_format($totalCost, 2); ?>
                    </div>
                </div>

                <div class="checkout-section">
                    <a href="processPlayPassCheckout.php" class="btn btn-title-action">Proceed to Checkout</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php
// <!-- Bootstrap DateTimePicker -->
wp_enqueue_script('bootstrap-min-js', plugin_dir_url(__FILE__) . 'js/bootstrap.min.js', array('jquery'), '2.0.0', true);
wp_enqueue_script('portalConfirmationDialog-js', plugin_dir_url(__FILE__) . 'js/portalConfirmationDialog.js', array('jquery'), '1.0.0', true);
wp_enqueue_script('playpass-js', plugin_dir_url(__FILE__) . 'js/playpass.js', array('jquery'), '1.0.0', true);
wp_enqueue_style('bootstrap-modal-css', plugin_dir_url(__FILE__) . 'css/bootstrap-modal.css', array(), '1.0.0');
?>

<!-- Include pricing data for JavaScript -->
<script>
    var pricingData = {
        dayCost: <?php echo $dayCost; ?>,
        lunchCost: <?php echo $lunchCost; ?>,
        extCareCost: <?php echo $extCareCost; ?>
    };

    var defaultContactFormTopic = "Day Camps";
    var adminAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
</script>