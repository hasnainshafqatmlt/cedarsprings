<?php
// ajax/getPlayPassDays.php - Get day options for Play Pass registration

session_start();

// Check login status
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../classes/ValidateLogin.php';
require_once __DIR__ . '/../includes/ultracamp.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';

$validator = new ValidateLogin($logger);

if (empty($_COOKIE['key']) || empty($_COOKIE['account']) || !$validator->validate($_COOKIE['key'], $_COOKIE['account'])) {
    // Return error in JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Initialize necessary classes
$uc = new UltracampModel($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Get parameters
$camperId = filter_input(INPUT_POST, 'camper', FILTER_VALIDATE_INT);
$weekNum = filter_input(INPUT_POST, 'week', FILTER_VALIDATE_INT);
$editMode = filter_input(INPUT_POST, 'edit_mode', FILTER_VALIDATE_INT) === 1;
$editIndex = filter_input(INPUT_POST, 'edit_index', FILTER_VALIDATE_INT);

if (!$camperId || !$weekNum) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing camper or week number']);
    exit;
}

// Get day options
PluginLogger::log("debug:: Getting day options for camper $camperId in week $weekNum");

$dayOptions = $playPassManager->getDayOptions($weekNum, $camperId);

PluginLogger::log("debug:: Day options result:", $dayOptions);
if (isset($dayOptions['error'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $dayOptions['error']]);
    exit;
}

// If in edit mode, add existing selection information
if ($editMode && isset($_SESSION['playPassSelections'][$editIndex])) {
    PluginLogger::log("debug:: Adding existing selection to response for edit mode index: $editIndex");
    $dayOptions['existing_selection'] = $_SESSION['playPassSelections'][$editIndex]['data'];
}

// Return day options as JSON
header('Content-Type: application/json');
echo json_encode($dayOptions);
exit;
