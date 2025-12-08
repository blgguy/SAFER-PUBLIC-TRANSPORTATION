<?php
//
// File: admin/profile.php
// Project: Safe Transport Reporting System
// Layer: 7 - Admin Actions
// Description: Allows the logged-in administrator to change their password.
//

// Start session and include config files
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once '../config/db.php';
include_once '../config/functions.php';

// Check admin session
if (!is_admin_logged_in()) {
    redirect('login.php?error=' . urlencode('Access denied. Please log in.'));
}

$message = '';
$admin_id = $_SESSION['admin_id'];

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Simple CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert error">Security check failed. Please refresh and try again.</div>';
    } else {
        
        // 1. Get and sanitize inputs
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // 2. Validate passwords
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = '<div class="alert error">All password fields are required.</div>';
        } elseif ($new_password !== $confirm_password) {
            $message = '<div class="alert error">New password and confirmation do not match.</div>';
        } elseif (strlen($new_password) < 8) {
            $message = '<div class="alert error">New password must be at least 8 characters long.</div>';
        } else {
            
            $conn = get_db_connection();
            if ($conn) {
                
                // 3. Verify current password
                $sql = "SELECT password FROM admin_users WHERE id = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();

                    if ($user && password_verify($current_password, $user['password'])) {
                        
                        // 4. Hash the new password and update
                        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        $update_sql = "UPDATE admin_users SET password = ? WHERE id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("si", $new_password_hash, $admin_id);
                            
                            if ($update_stmt->execute()) {
                                $message = '<div class="alert success">Password updated successfully!</div>';
                            } else {
                                $message = '<div class="alert error">Database error during update.</div>';
                            }
                            $update_stmt->close();
                        } else {
                            $message = '<div class="alert error">Database error preparing update statement.</div>';
                        }
                    } else {
                        $message = '<div class="alert error">Current password is incorrect.</div>';
                    }
                } else {
                    $message = '<div class="alert error">Database error preparing verification statement.</div>';
                }
                $conn->close();
            } else {
                $message = '<div class="alert error">Database connection failed.</div>';
            }
        }
    }
}

// Generate new CSRF token if needed
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Change Password</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        .header { background: #0066cc; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .nav-menu { background: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .nav-menu a { margin-right: 20px; color: #0066cc; text-decoration: none; font-weight: bold; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        input, select, textarea { width: 100%; padding: 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 6px; }
        textarea { min-height: 120px; font-family: Arial, sans-serif; resize: vertical; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .btn-primary { background: #0066cc; color: white; width: 100%; }
        .btn-primary:hover { background: #0052a3; }
        .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .required { color: #dc3545; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; text-decoration: none; }
        
        .form-container { max-width: 500px; background-color: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .form-group { margin-bottom: 20px; }
        
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 16px; }

        .btn-submit { background-color: var(--primary-blue); color: var(--white); padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #0056b3; }

        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
        .alert.error { background-color: #f8d7da; color: var(--danger-red); border: 1px solid #f5c6cb; }
        .alert.success { background-color: #d4edda; color: var(--success-green); border: 1px solid #c3e6cb; }
        
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ Create Safety Alert</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="nav-menu">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>


    <div class="container">
        <h1>Admin Profile: Change Password</h1>

        <?= $message ?>
        
        <div class="form-container">
            <form action="profile.php" method="POST">
                
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>

                <div class="form-group">
                    <label for="new_password">New Password (Min 8 characters):</label>
                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                </div>
                
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <button type="submit" class="btn btn-primary">🔒 Update Password</button>
            </form>
        </div>
    </div>
</body>
</html>