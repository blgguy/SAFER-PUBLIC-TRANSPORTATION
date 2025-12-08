<?php
/**
 * Admin Login Page
 * File: admin/login.php
 */

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Get error message if any
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Safe Transport</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0066cc 0%, #004999 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #0066cc;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 6px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #0066cc;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .remember-me input {
            width: auto;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #0066cc;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #0052a3;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #0066cc;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .lock-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="lock-icon">🔐</div>
        <div class="login-header">
            <h1>Admin Login</h1>
            <p>Safe Transport Management System</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php 
            if ($error === 'invalid') {
                echo '❌ Invalid username or password';
            } elseif ($error === 'empty') {
                echo '❌ Please enter both username and password';
            } elseif ($error === 'locked') {
                echo '❌ Too many failed attempts. Please try again later.';
            } else {
                echo '❌ An error occurred. Please try again.';
            }
            ?>
        </div>
        <?php endif; ?>

        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember" style="font-weight: normal; margin: 0;">Remember me</label>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="back-link">
            <a href="../index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>