<?php
/**
 * Delete Incident
 * File: admin/delete_incident.php
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';

// Get incident ID
$incident_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($incident_id <= 0) {
    header('Location: dashboard.php?error=Invalid incident ID');
    exit;
}

// Delete related alerts first
mysqli_query($conn, "DELETE FROM alerts WHERE incident_id = $incident_id");

// Delete incident
$stmt = mysqli_prepare($conn, "DELETE FROM incidents WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $incident_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    header('Location: dashboard.php?success=Incident deleted successfully');
} else {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    header('Location: dashboard.php?error=Failed to delete incident');
}
exit;
?>