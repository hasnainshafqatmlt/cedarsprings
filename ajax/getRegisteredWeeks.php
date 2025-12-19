<?php
// ajax/getRegisteredWeeks.php - Get weeks with existing registrations for a camper

session_start();

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';
// require_once __DIR__ . '/../logger/plannerLogger.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../includes/ultracamp.php';

$validator = new ValidateLogin($logger);

if (empty($_COOKIE['key']) || empty($_COOKIE['account']) || !$validator->validate($_COOKIE['key'], $_COOKIE['account'])) {
    // Return error in JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Initialize necessary classes
$uc = new UltracampModel($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Get camper ID
$camperId = filter_input(INPUT_POST, 'camper_id', FILTER_VALIDATE_INT);

if (!$camperId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid camper ID']);
    exit;
}

// Get registered weeks for this camper
$registeredWeeks = $playPassManager->getRegisteredWeeks($camperId);

// Return registered weeks
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'registeredWeeks' => $registeredWeeks
]);
exit;
