<?php
//
// File: admin/delete_user.php
// Project: Safe Transport Reporting System
// Layer: 7 - Admin Actions
// Description: Securely deletes an admin user account.
//

session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if admin is logged in
if (!is_admin_logged_in()) {
    redirect('login.php?error=' . urlencode('Access denied.'));
}

$target_user_id = $_GET['id'] ?? null;
$csrf_token = $_GET['token'] ?? null;

// Basic validation
if (empty($target_user_id) || !is_numeric($target_user_id) || empty($csrf_token) || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    redirect('user.php?msg=' . urlencode('Error: Invalid request or security token missing.'));
}

// CRITICAL SECURITY CHECK: Prevent deletion of the first/primary admin (ID 1)
// We assume the first admin created (the super-admin) has ID 1.
if ((int)$target_user_id === 1) {
    redirect('user.php?msg=' . urlencode('Error: The primary admin account (ID 1) cannot be deleted.'));
}

// $conn = get_db_connection();
if (!$conn) {
    redirect('user.php?msg=' . urlencode('Error: Database connection failed.'));
}

// Delete the user using a prepared statement
$sql = "DELETE FROM admin_users WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $target_user_id);
    
    if ($stmt->execute()) {
        $deleted_username = "User ID " . $target_user_id; // Simple name if we didn't fetch it first
        
        // Check if the deleted user was the currently logged-in admin (shouldn't happen if we protected ID 1)
        if ((int)$target_user_id === (int)$_SESSION['admin_id']) {
            // This case should be blocked by ID 1 check, but as a general safety net:
            session_destroy();
            redirect('login.php?msg=' . urlencode('Your account was deleted by another admin. You have been logged out.'));
        }
        
        $msg = urlencode("Success: Admin user #{$target_user_id} has been deleted.");
        redirect('user.php?msg=' . $msg);
    } else {
        $msg = urlencode('Error: Failed to delete user. ' . $stmt->error);
        redirect('user.php?msg=' . $msg);
    }
    $stmt->close();
} else {
    $msg = urlencode('Error: Could not prepare statement.');
    redirect('user.php?msg=' . $msg);
}

mysqli_close($conn);
?>