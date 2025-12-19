<?php
// ajax/getExistingSelections.php - Retrieves existing Play Pass selections for a camper

session_start();

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';

// require_once __DIR__ . '../logger/plannerLogger.php';
$validator = new ValidateLogin($logger);

if (empty($_COOKIE['key']) || empty($_COOKIE['account']) || !$validator->validate($_COOKIE['key'], $_COOKIE['account'])) {
    // Return error in JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required', 'key' => $_COOKIE]);
    exit;
}

// Get camper ID
$camperId = filter_input(INPUT_POST, 'camper_id', FILTER_VALIDATE_INT);

if (!$camperId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid camper ID']);
    exit;
}

// Check if we have any Play Pass selections
if (empty($_SESSION['playPassSelections'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'selections' => []]);
    exit;
}

// Filter selections for the specified camper
$camperSelections = [];
foreach ($_SESSION['playPassSelections'] as $index => $selection) {
    if ($selection['data']['camper_id'] == $camperId) {
        // Add index to the data
        $selection['index'] = $index;
        $camperSelections[] = $selection;
    }
}

// Return selections
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'selections' => $camperSelections
]);
exit;
