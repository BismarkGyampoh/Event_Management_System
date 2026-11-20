<?php
/**
 * Logout Script - Attendee
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Log logout action if user was logged in
if (isLoggedIn()) {
    logAuditAction(
        getCurrentUserId(),
        getCurrentUserRole(),
        'LOGOUT',
        'Attendees_007',
        getCurrentUserId()
    );
}

// Destroy session
$_SESSION = array();
session_destroy();

// Start new session for flash message
session_start();
$_SESSION['flash_message'] = [
    'type' => 'success',
    'message' => 'You have been logged out successfully.'
];

// Redirect to login page
header('Location: ' . getBaseUrl() . 'login.php');
exit;
