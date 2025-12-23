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
// Store validated credentials in session for secure AJAX requests (avoids passing in POST body)
if (empty($_COOKIE['key']) || empty($_COOKIE['account']) || !$validator->validate($_COOKIE['key'], $_COOKIE['account'])) {
    // Instruct the grid to collect login info
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    // Set redirect cookie to return to playpass.php after login
    setCookie('redirectAfterLogin', 'playpass', time() + 3600, '/camps/queue');
    // Send the browser back to the grid
    echo '<script>window.location.href = "/camps/queue";</script>';
    exit;
}

// Store validated credentials in session for AJAX requests
// This allows AJAX to use session instead of passing credentials in POST body
$_SESSION['ultracamp_auth_key'] = $_COOKIE['key'];
$_SESSION['ultracamp_auth_account'] = $_COOKIE['account'];

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
        <div class="play-pass-navigation tw-flex tw-justify-between tw-flex-wrap tw-mb-5">
            <a href="/camps/queue/" class="tw-btn-secondary">Return to Camp Grid</a>
            <a href="/camps/queue/status/" class="tw-btn-secondary">View Registrations</a>
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
        <form id="playPassForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
            <input type="hidden" name="action" value="processPlayPass">
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
                <button type="submit" class="btn-title-action tw-btn-secondary">Add to Cart</button>
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
                                <button type="button" class="tw-btn-danger"
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
                    <button onclick="handleProcessPlayPassCheckout()" class="btn tw-btn-secondary"
                        id="playpass-checkout-btn">Proceed to Checkout</button>
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
    var formBaseUrl = '<?php echo plugin_dir_url(__FILE__) . 'playpass/'; ?>';
</script>
<style>
    .registration-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        position: relative;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }

    .registration-steps::before {
        content: "";
        position: absolute;
        top: 15px;
        left: 40px;
        right: 20px;
        height: 2px;
        background-color: rgba(0, 0, 0, 0.3);
        z-index: 1;
    }

    .step {
        position: relative;
        z-index: 2;
        text-align: center;
    }

    .step.completed .step-number {
        background-color: #4CAF50;
        border-color: #fff;
    }

    .step.active .step-number {
        background-color: #99C941;
        border-color: #fff;
    }

    .step-number {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: #777;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 8px;
        font-weight: bold;
    }

    .step-label {
        font-size: 12px;
    }

    .step.active .step-label {
        font-weight: bold;
    }

    .play-pass-section {
        margin-bottom: 30px;
        background-color: #FFF8F0;
        padding: 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .play-pass-section h3 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 24px;
        font-weight: 600;
        display: flex;
        align-items: center;
        color: #3B89F0;
    }

    .camper-list,
    .week-list {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }

    .camper-option,
    .week-option {
        background-color: #f2dcc3;
        padding: 15px;
        border-radius: 5px;
        min-width: 200px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        border: 2px solid transparent;
        margin-bottom: 8px;
        width: calc(33.333% - 15px);
        flex-grow: 1;
    }

    .camper-option input[type="radio"],
    .week-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        cursor: pointer;
        height: 0;
        width: 0;
    }

    .camper-option label,
    .week-option label {
        font-weight: 500;
        cursor: pointer;
        display: block;
        padding-left: 5px;
        width: 100%;
        position: relative;
    }

    .camper-option input[type="radio"]:checked+label,
    .week-option input[type="radio"]:checked+label {
        font-weight: bold;
    }

    .camper-option input[type="radio"]:checked+label::before,
    .week-option input[type="radio"]:checked+label::before {
        content: "";
        position: absolute;
        right: 5px;
        top: 5px;
        width: 20px;
        height: 20px;
        background-color: #99C941;
        border-radius: 50%;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'%3E%3Cpath fill='%23ffffff' d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/%3E%3C/svg%3E");
        background-size: 14px;
        background-position: center;
        background-repeat: no-repeat;
    }

    .camper-option:hover,
    .week-option:hover {
        background-color: #CAEC96;
        transform: translateY(-2px);
    }

    .camper-option.selected,
    .week-option.selected {
        background-color: #f2dcc3;
        border-color: #99C941;
    }

    .play-pass-calendar {
        display: flex;
        flex-direction: column;
        width: 100%;
        margin-bottom: 20px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .calendar-row {
        display: flex;
        width: 100%;
    }

    .calendar-cell {
        flex: 1;
        padding: 15px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        text-align: center;
    }

    .calendar-cell.header {
        background-color: #CAEC96;
        font-weight: 600;
        padding: 10px 15px;
    }

    .calendar-row {
        display: flex;
        width: 100%;
    }

    .calendar-cell.day {
        background-color: #f2dcc3;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        position: relative;
    }

    .day-date {
        font-size: 14px;
        margin-bottom: 10px;
        font-weight: 600;
        color: #3B89F0;
    }

    .calendar-cell.day input[type="checkbox"] {
        position: absolute;
        opacity: 0;
    }

    .calendar-cell.day input[type="checkbox"]+label {
        position: relative;
        cursor: pointer;
        padding: 0;
        display: block;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .day-status {
        font-size: 12px;
        margin-top: 10px;
        font-style: italic;
        background-color: rgba(0, 0, 0, 0.2);
        padding: 4px 8px;
        border-radius: 4px;
    }

    .calendar-cell.day.newly-selected {
        border: 2px solid #99C941;
        background-color: rgba(153, 201, 65, 0.2);
    }

    .calendar-cell.day input[type="checkbox"]:checked+label::after {
        content: "";
        position: absolute;
        top: 10px;
        right: 10px;
        width: 20px;
        height: 20px;
        background-color: #99C941;
        border-radius: 50%;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'%3E%3Cpath fill='%23ffffff' d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/%3E%3C/svg%3E");
        background-size: 14px;
        background-position: center;
        background-repeat: no-repeat;
    }

    .calendar-cell.day:hover:not(.unavailable):not(.registered) {
        background-color: rgba(153, 201, 65, 0.3);
    }

    .calendar-cell.day:not(.unavailable):not(.july-fourth):hover {
        background-color: rgba(153, 201, 65, 0.2);
        transform: translateY(-2px);
        transition: all 0.2s ease;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .pricing-note,
    .care-note {
        background-color: #f2dcc3;
        padding: 15px 15px 15px 50px;
        border-radius: 5px;
        margin-top: 20px;
        position: relative;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .care-note::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'%3E%3Cpath fill='%2399C941' d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/%3E%3C/svg%3E");
    }

    .pricing-note::before,
    .care-note::before {
        content: "";
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        width: 24px;
        height: 24px;
        background-size: contain;
        background-repeat: no-repeat;
    }

    .pricing-note::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'%3E%3Cpath fill='%2399C941' d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/%3E%3C/svg%3E");
    }

    .transport-options-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin: 25px 0;
    }

    .transport-option {
        flex: 1;
        min-width: 250px;
        background-color: #f2dcc3;
        border-radius: 8px;
        padding: 20px;
        transition: all 0.3s ease;
        position: relative;
        border: 2px solid transparent;
        cursor: pointer;
    }

    .transport-option.selected {
        border-color: #99C941;
        background-color: rgba(153, 201, 65, 0.2);
    }

    .transport-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }

    .transport-option label {
        cursor: pointer;
        display: block;
        width: 100%;
    }

    .transport-option h4 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 20px;
    }

    .transport-times {}

    .transport-times p {
        margin: 8px 0;
    }

    .transport-option input[type="radio"]:checked+label::after {
        content: "";
        position: absolute;
        top: 10px;
        right: 10px;
        width: 24px;
        height: 24px;
        background-color: #99C941;
        border-radius: 50%;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'%3E%3Cpath fill='%23ffffff' d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/%3E%3C/svg%3E");
        background-size: 14px;
        background-position: center;
        background-repeat: no-repeat;
    }

    .transport-info {
        background-color: #f2dcc3;
        border-left: 4px solid #99C941;
        padding: 15px;
        border-radius: 4px;
        margin-top: 20px;
        font-size: 14px;
    }

    .transport-info p {
        margin: 0;
    }

    .transport-info i {
        margin-right: 8px;
        color: #99C941;
    }

    .option-group {
        margin-bottom: 30px;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        clear: both;
    }

    #lunch-options,
    #camp-choices {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-content: flex-start;
    }


    .option-group h4 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 18px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    #lunch-options .lunch-options-container {
        float: none;
        clear: both;
        width: 100%;
        margin-left: 0;
        text-align: left;
    }

    .options-flex-container {
        display: flex;
        flex-direction: column;
        width: 100%;
        margin-bottom: 20px;
        background-color: #f2dcc3;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .options-flex-row {
        display: flex;
        width: 100%;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .options-header-row {
        background-color: #CAEC96;
        font-weight: 600;
    }

    .options-flex-cell {
        flex: 1;
        padding: 12px 15px;
        display: flex;
        align-items: center;
    }

    .options-label-cell {
        flex: 0 0 120px;
        font-weight: 500;
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    .options-flex-row:last-child {
        border-bottom: none;
    }

    .options-flex-row:last-child {
        border-bottom: none;
    }

    .checkbox-container {
        display: flex;
        align-items: center;
    }

    .options-checkbox {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        width: 20px !important;
        height: 20px;
        background-color: transparent;
        border: 2px solid gray;
        border-radius: 4px;
        cursor: pointer;
        position: relative;
        transition: background-color 0.2s, border-color 0.2s;
    }

    .options-checkbox:checked {
        background-color: #99C941;
        border-color: #99C941;
    }

    .options-checkbox:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(153, 201, 65, 0.4);
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
        user-select: none;
        margin-left: 10px;
    }

    .friends-container {
        margin-bottom: 20px;
    }

    #friends-input {
        width: 100%;
        background-color: rgba(255, 255, 255, 0.9);
        border: 1px solid #ddd;
        border-radius: 4px;
        color: #333;
        font-size: 16px;
        padding: 10px;
        resize: vertical;
    }

    .options-checkbox:checked::after {
        content: "";
        position: absolute;
        left: 6px;
        top: 0;
        width: 6px;
        height: 12px;
        border: solid white;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }



    .cost-summary {
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .cost-summary h4 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 18px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .cost-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .cost-value {
        color: #99C941;
        font-weight: 600;
    }

    .selections-list {
        margin-bottom: 20px;
    }

    .selection-item {
        background-color: #FFF8F0;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .selection-info {
        flex: 1;
    }

    .selection-info strong {
        color: #99C941;
    }

    .selection-transport {
        margin-top: 5px;
        font-style: italic;
        font-size: 0.95em;
    }

    .total-section {
        margin-bottom: 20px;
        padding: 15px;
        background-color: rgba(153, 201, 65, 0.1);
        border-radius: 8px;
        text-align: right;
    }

    .total-section .total-label {
        font-size: 18px;
    }

    .total-section .total-amount {
        font-size: 24px;
        font-weight: bold;
        color: #99C941;
    }

    .checkout-section {
        margin-top: 30px;
        text-align: center;
        padding: 20px;
        border-radius: 8px;
    }
</style>