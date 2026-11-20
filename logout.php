<?php
/**
 * Logout Script
 * Event Management System 007
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Log logout action if user was logged in
if (isLoggedIn()) {
    $userRole = getCurrentUserRole();
    // Only log if we have a valid user type (admin, organizer, or attendee)
    if ($userRole && in_array($userRole, ['admin', 'organizer', 'attendee'])) {
        logAuditAction(
            getCurrentUserId(),
            $userRole,
            'LOGOUT',
            null,
            null
        );
    }
}

// Rotate the session identifier and retain only the logout notice.
$_SESSION = [];
session_regenerate_id(true);
setFlashMessage('success', 'You have been logged out successfully.');

// Redirect to home page
redirect(getBaseUrl() . 'index.php');
