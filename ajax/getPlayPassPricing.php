<?php
// getPlayPassPricing.php - AJAX endpoint to retrieve dynamic pricing for Play Pass

header('Content-Type: application/json');

// Begin session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the logger
// require_once '../logger/plannerLogger.php';

// Required classes
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../classes/ValidateLogin.php';
require_once __DIR__ . '/../includes/ultracamp.php';
require_once __DIR__ . '/../classes/PlayPassManager.php';

// Check login status
$validator = new ValidateLogin($logger);

if (empty($_COOKIE['key']) || empty($_COOKIE['account']) || !$validator->validate($_COOKIE['key'], $_COOKIE['account'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required',
        'redirect' => '/camps/queue'
    ]);
    exit;
}

// Get week parameter
$weekNum = filter_input(INPUT_POST, 'week', FILTER_VALIDATE_INT);

if (!$weekNum) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid week number'
    ]);
    exit;
}

// Initialize necessary classes
$uc = new UltracampModel($logger);
$CQModel = new CQModel($logger);
$CQModel->setUltracampObj($uc);
$playPassManager = new PlayPassManager($logger);
$playPassManager->setCQModel($CQModel);

// Get pricing information
$dayCost = $playPassManager->getPlayPassDayCost($weekNum);
$lunchCost = $playPassManager->getPlayPassLunchCost($weekNum);
$extCareCost = $playPassManager->getPlayPassExtCareCost($weekNum);

// Return pricing data
echo json_encode([
    'success' => true,
    'dayCost' => $dayCost,
    'lunchCost' => $lunchCost,
    'extCareCost' => $extCareCost
]);

exit;
