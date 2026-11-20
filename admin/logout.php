<?php
/**
 * Logout Script - Admin
 * Event Management System 007
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Log logout action if user was logged in
if (isLoggedIn()) {
    // Map user types to their actual table names (no 'Admins_007' table exists)
    $auditTableMap = [
        'admin'     => 'System',
        'organizer' => 'Organizers_007',
        'attendee'  => 'Attendees_007'
    ];
    $role = getCurrentUserRole();
    $auditTable = $auditTableMap[$role] ?? 'System';
    logAuditAction(
        getCurrentUserId(),
        $role,
        'LOGOUT',
        $auditTable,
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
