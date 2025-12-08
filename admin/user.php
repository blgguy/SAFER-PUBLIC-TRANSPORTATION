<?php
//
// File: admin/user.php
// Project: Safe Transport Reporting System
// Layer: 7 - Admin Actions
// Description: Allows admin to create and view other admin user accounts, with secure deletion logic.
//

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// NOTE: Assuming db.php and functions.php define get_db_connection(), sanitize_input() and is_admin_logged_in()
require_once '../config/db.php';
require_once '../config/functions.php';

$message = '';
if (!$conn) {
    die("Database connection failed.");
}

// --- 1. Handle New Admin Creation ---
// ... (The creation logic remains unchanged from the previous version) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check (Basic check is better than none)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $message = '<div class="success-message error">❌ Security check failed. Please refresh and try again.</div>';
    } else {
        $new_username = sanitize_input($_POST['new_username'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_username) || empty($new_password) || empty($confirm_password)) {
            $message = '<div class="success-message error">❌ Error: All fields are required for creating a new user.</div>';
        } elseif ($new_password !== $confirm_password) {
            $message = '<div class="success-message error">❌ Error: Passwords do not match.</div>';
        } elseif (strlen($new_password) < 8) {
            $message = '<div class="success-message error">❌ Error: Password must be at least 8 characters long.</div>';
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_insert = "INSERT INTO admin_users (username, password) VALUES (?, ?)";
            if ($stmt = $conn->prepare($sql_insert)) {
                $stmt->bind_param("ss", $new_username, $password_hash);
                
                if ($stmt->execute()) {
                    $message = '<div class="success-message">✅ New administrator account **' . htmlspecialchars($new_username) . '** created successfully!</div>';
                } else {
                    if ($conn->errno == 1062) {
                        $message = '<div class="success-message error">❌ Error: Username **' . htmlspecialchars($new_username) . '** already exists.</div>';
                    } else {
                        $message = '<div class="success-message error">❌ Database error creating user.</div>';
                    }
                }
                $stmt->close();
            } else {
                $message = '<div class="success-message error">❌ Database error preparing insert statement.</div>';
            }
        }
    }
}

// --- 2. Fetch Existing Admin Users ---
$admin_users = [];
$sql_fetch = "SELECT id, username, created_at FROM admin_users ORDER BY id ASC";
$result_fetch = mysqli_query($conn, $sql_fetch);
if ($result_fetch) {
    while ($row = mysqli_fetch_assoc($result_fetch)) {
        $admin_users[] = $row;
    }
}

// Handle message from GET parameter (e.g., from delete_user.php redirect)
if (isset($_GET['msg'])) {
    $get_msg = htmlspecialchars($_GET['msg']);
    if (strpos($get_msg, 'Error') !== false || strpos($get_msg, 'Failed') !== false) {
        $message = '<div class="success-message error">❌ ' . $get_msg . '</div>';
    } else {
        $message = '<div class="success-message">✅ ' . $get_msg . '</div>';
    }
}

// Close connection if using procedural style
if (function_exists('close_connection')) {
    close_connection($conn);
} else {
    mysqli_close($conn);
}

// Generate new CSRF token for forms and links
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management - Safe Transport</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        a { text-decoration: none; font-weight: bold; }
        .header { background: #0066cc; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .nav-menu { background: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav-menu a { margin-right: 20px; color: #0066cc; text-decoration: none; font-weight: bold; }
        .nav-menu a:hover { text-decoration: underline; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: bold; }
        .success-message.error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        
        /* Form Specific Styles */
        .form-container { max-width: 500px; margin-bottom: 30px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        .btn-submit { background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; width: 100%; }
        .btn-submit:hover { background: #1e7e34; }

        /* Table Specific Styles */
        table.user-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.user-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: bold; color: #333; border-bottom: 2px solid #dee2e6; }
        table.user-table td { padding: 12px; border-bottom: 1px solid #eee; }
        table.user-table tr:hover { background: #f8f9fa; }
        .status-active { color: #28a745; font-weight: bold; }
        .current-user-tag { background: #0066cc; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.75em; margin-left: 5px; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }


        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
            padding-top: 50px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 400px;
            border-radius: 8px;
            text-align: center;
        }
        .modal-content h3 { color: #dc3545; margin-bottom: 15px; }
        .modal-content p { margin-bottom: 20px; }
        .modal-buttons { display: flex; justify-content: space-around; }
        .modal-buttons a, .modal-buttons button { width: 45%; }
        .btn-cancel { background: #ccc; color: #333; }
        .btn-cancel:hover { background: #bbb; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; }
            .form-container { max-width: 100%; }
             table.user-table, table.user-table thead, table.user-table tbody, table.user-table th, table.user-table td, table.user-table tr { 
                display: block; 
            }
            table.user-table thead tr { 
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            table.user-table td { 
                border: none;
                border-bottom: 1px solid #eee; 
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            table.user-table td:before { 
                position: absolute;
                left: 6px;
                width: 45%; 
                padding-right: 10px; 
                white-space: nowrap;
                font-weight: bold;
                content: attr(data-label);
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ Safe Transport Admin</h1>
        <div class="user-info">
            <a href="profile.php"><span>👤 <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span></a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav-menu">
        <a href="dashboard.php">Dashboard</a>
        <a href="dashboard.php">All Incidents</a>
        <a href="create_alert.php">Create Alert</a>
        <a href="user.php" style="text-decoration: underline;">Admins</a>
        <a href="../index.html" target="_blank">View Site</a>
    </div>

    <div class="container">
        <h1>Admin User Management</h1>

        <?php echo $message; ?>
        
        <div class="form-container">
            <h2>➕ Create New Admin Account</h2>
            <form action="user.php" method="POST">
                
                <div class="form-group">
                    <label for="new_username">Username:</label>
                    <input type="text" id="new_username" name="new_username" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="new_password">Password (Min 8 characters):</label>
                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                </div>
                
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <button type="submit" class="btn-submit">✅ Create User</button>
            </form>
        </div>

        <div class="incidents-section">
            <div class="section-header">
                <h2>👥 Existing Admin Users (<?php echo count($admin_users); ?> Total)</h2>
            </div>

            <?php if (count($admin_users) > 0): ?>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Created At</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admin_users as $user): ?>
                    <tr>
                        <td data-label="ID">#<?php echo htmlspecialchars($user['id']); ?></td>
                        <td data-label="Username">
                            <?php echo htmlspecialchars($user['username']); ?>
                            <?php if ($user['id'] == ($_SESSION['admin_id'] ?? 0)): ?>
                                <span class="current-user-tag">(You)</span>
                            <?php endif; ?>
                            <?php if ($user['id'] == 1): ?>
                                <span class="current-user-tag" style="background: #333;">(Primary Admin)</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Created At"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                        <td data-label="Status"><span class="status-active">Active</span></td>
                        <td data-label="Action">
                            <?php 
                            // Only allow deletion if the user is NOT the primary admin (ID 1)
                            if ((int)$user['id'] !== 1): 
                            ?>
                                <button 
                                    class="btn btn-danger" 
                                    onclick="showDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                >
                                    Delete
                                </button>
                            <?php else: ?>
                                <span style="color: #6c757d;">(Protected)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; padding: 40px; color: #666;">No admin users found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>⚠️ Confirm User Deletion</h3>
            <p>Are you sure you want to permanently delete admin user: **<span id="modal-username"></span>** (ID: <span id="modal-userid"></span>)?</p>
            <div class="modal-buttons">
                <button class="btn btn-cancel" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                <a id="modal-delete-link" href="#" class="btn btn-danger">Confirm Delete</a>
            </div>
        </div>
    </div>

    <script>
        // JavaScript for the Delete Modal
        const modal = document.getElementById('deleteModal');
        const modalUsername = document.getElementById('modal-username');
        const modalUserId = document.getElementById('modal-userid');
        const modalDeleteLink = document.getElementById('modal-delete-link');
        const csrfToken = "<?= $csrf_token ?>";

        function showDeleteModal(id, username) {
            modalUsername.textContent = username;
            modalUserId.textContent = id;
            // Set the href for the delete script, passing the ID and CSRF token
            modalDeleteLink.href = `delete_user.php?id=${id}&token=${csrfToken}`;
            modal.style.display = 'block';
        }

        // Close the modal when the user clicks anywhere outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>