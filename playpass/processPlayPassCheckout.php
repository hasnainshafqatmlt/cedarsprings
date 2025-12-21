<?php
// processPlayPassCheckout.php - Process all Play Pass selections and redirect to processPlayPassCart.php

session_start();

// Check login status
require_once __DIR__ . '/../classes/ValidateLogin.php';
require_once __DIR__ . '/../classes/EmailChangeOrder.php';
require_once __DIR__ . '/../classes/CQModel.php';
require_once __DIR__ . '/../includes/ultracamp.php';
require_once __DIR__ . '/../classes/FriendManager.php';

$validator = new ValidateLogin($logger);

// SECURE APPROACH: Use session-based authentication
$authKey = null;
$authAccount = null;

// Try session first (most secure)
if (!empty($_SESSION['ultracamp_auth_key']) && !empty($_SESSION['ultracamp_auth_account'])) {
    $authKey = $_SESSION['ultracamp_auth_key'];
    $authAccount = $_SESSION['ultracamp_auth_account'];

    if (!$validator->validate($authKey, $authAccount)) {
        unset($_SESSION['ultracamp_auth_key']);
        unset($_SESSION['ultracamp_auth_account']);
        $authKey = null;
        $authAccount = null;
    }
}

// Fallback to cookies
if (empty($authKey) || empty($authAccount)) {
    if (!empty($_COOKIE['key']) && !empty($_COOKIE['account'])) {
        $authKey = $_COOKIE['key'];
        $authAccount = $_COOKIE['account'];

        if ($validator->validate($authKey, $authAccount)) {
            $_SESSION['ultracamp_auth_key'] = $authKey;
            $_SESSION['ultracamp_auth_account'] = $authAccount;
        }
    }
}

// Last resort: POST body (for path-restricted cookies)
if (empty($authKey) || empty($authAccount)) {
    $postKey = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
    $postAccount = filter_input(INPUT_POST, 'account', FILTER_SANITIZE_STRING);

    if (!empty($postKey) && !empty($postAccount) && $validator->validate($postKey, $postAccount)) {
        $authKey = $postKey;
        $authAccount = $postAccount;
        $_SESSION['ultracamp_auth_key'] = $authKey;
        $_SESSION['ultracamp_auth_account'] = $authAccount;
    }
}

// Confirm that the user is still logged in
if (empty($authKey) || empty($authAccount) || !$validator->validate($authKey, $authAccount)) {
    // Redirect back to login
    setCookie('reAuth', 'submitForm', time() + 3600, '/camps/queue');
    echo '<script>window.location.href = "/camps/queue";</script>';
    exit;
}

// Flag to track if we have edits to existing registrations
$hasEdits = false;

// Check if we have any Play Pass selections (new registrations)
$hasNewSelections = !empty($_SESSION['playPassSelections']);

// Check if we have any Play Pass edits (changes to existing registrations)
$hasEdits = !empty($_SESSION['playPassEdits']);

// If we have no items to process, redirect back
if (!$hasNewSelections && !$hasEdits) {
    echo '<script>window.location.href = "/camps/queue/playpass";</script>';
    exit;
}

// Initialize FriendManager
$friendManager = new FriendManager($logger);

// Process new selections
if ($hasNewSelections) {
    // Initialize playPassData if needed
    if (!isset($_SESSION['playPassData'])) {
        $_SESSION['playPassData'] = [];
    }

    // Add all Play Pass selections to playPassData
    foreach ($_SESSION['playPassSelections'] as $selection) {
        $_SESSION['playPassData'][$selection['entry']] = $selection['data'];

        // Process friends data if available
        if (isset($selection['data']['friends']) && !empty($selection['data']['friends'])) {
            $camperId = $selection['data']['camper_id'];
            $friendsList = $selection['data']['friends'];

            PluginLogger::log("debug:: Processing friends for camper $camperId from new selection: $friendsList");

            // Save friends using the FriendManager
            $friendManager->saveFriends($camperId, $friendsList);
        }
    }

    // Clear the play pass selections after adding them to playPassData
    unset($_SESSION['playPassSelections']);

    PluginLogger::log("debug:: processPlayPassCheckout.php: processing new registrations", array_keys($_SESSION['playPassData']));
}

// Process edits to existing registrations
if ($hasEdits) {
    PluginLogger::log("debug:: processPlayPassCheckout.php: processing registration edits", array_keys($_SESSION['playPassEdits']));

    // Set flag for completion page to show edit notification
    $_SESSION['displayPlayPassEdits'] = true;

    // Setup CQModel for getting camper information
    $uc = new UltracampModel($logger);
    $CQModel = new CQModel($logger);
    $CQModel->setUltracampObj($uc);

    // Create email change orders for each edit
    $emailChangeOrder = new EmailChangeOrder($logger);

    foreach ($_SESSION['playPassEdits'] as $editId => $editData) {
        // Process friends data if available
        if (isset($editData['new']['friends']) && !empty($editData['new']['friends'])) {
            $camperId = $editData['new']['camper_id'];
            $friendsList = $editData['new']['friends'];

            PluginLogger::log("debug:: Processing friends for camper $camperId from edit $editId: $friendsList");

            // Save friends using the FriendManager
            $friendManager->saveFriends($camperId, $friendsList);
        }

        // Send change order email for this edit
        $emailChangeOrder->createPlayPassChangeOrder(
            $editData['original'],
            $editData['new'],
            $editData['camper_info']
        );

        PluginLogger::log("debug:: Sent change order email for edit: $editId");
    }

    // Store the edits count for the completion page
    $_SESSION['playPassEditsCount'] = count($_SESSION['playPassEdits']);

    // Clear the edits after processing
    unset($_SESSION['playPassEdits']);
}

// Always process cart for new selections if they exist
if ($hasNewSelections) {
    // If we have new registrations, process them through the cart
    header('Location: processPlayPassCart.php');
} else {
    // If we only have edits (no new registrations), go directly to completion page
    header('Location: completePlayPassRegistration.php');
}
exit;
