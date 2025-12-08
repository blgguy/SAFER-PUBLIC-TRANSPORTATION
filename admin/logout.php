<?php
//
// File: admin/logout.php
// Project: Safe Transport Reporting System
// Description: Clears admin session and redirects to login page.
//

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
include_once '../config/functions.php';
redirect('login.php');
?>