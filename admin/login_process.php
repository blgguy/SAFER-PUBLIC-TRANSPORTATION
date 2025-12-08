<?php
/**
 * Admin Login Processing
 * Handles admin authentication
 * File: admin/login_process.php
 */

session_start();

// Include database connection
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Get and sanitize input
$username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$remember = isset($_POST['remember']) ? true : false;

// Validate input
if (empty($username) || empty($password)) {
    header('Location: login.php?error=empty');
    exit;
}

// Check for too many failed attempts (simple rate limiting)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = time();
}

// Reset counter after 15 minutes
if (time() - $_SESSION['last_attempt'] > 900) {
    $_SESSION['login_attempts'] = 0;
}

// Block if too many attempts
if ($_SESSION['login_attempts'] >= 5) {
    header('Location: login.php?error=locked');
    exit;
}

// Query database for user
$stmt = mysqli_prepare($conn, "SELECT id, username, password, email FROM admin_users WHERE username = ?");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Login successful - reset attempts
        $_SESSION['login_attempts'] = 0;
        
        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_email'] = $user['email'];
        
        // Update last login time
        $update_stmt = mysqli_prepare($conn, "UPDATE admin_users SET last_login = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // Redirect to dashboard
        header('Location: dashboard.php');
        exit;
    }
}

// Login failed - increment attempts
$_SESSION['login_attempts']++;
$_SESSION['last_attempt'] = time();

mysqli_stmt_close($stmt);
close_connection();

// Redirect back with error
header('Location: login.php?error=invalid');
exit;
?>