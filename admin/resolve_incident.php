<?php
/**
 * Mark Incident as Resolved
 * File: admin/resolve_incident.php
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';
require_once '../config/functions.php';

// Get incident ID
$incident_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($incident_id <= 0) {
    header('Location: dashboard.php?error=Invalid incident ID');
    exit;
}

// Update incident status to resolved
$stmt = mysqli_prepare($conn, "UPDATE incidents SET status = 'resolved' WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $incident_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    close_connection();
    header('Location: dashboard.php?success=Incident marked as resolved');
} else {
    mysqli_stmt_close($stmt);
    close_connection();
    header('Location: dashboard.php?error=Failed to update incident');
}
exit;
?>