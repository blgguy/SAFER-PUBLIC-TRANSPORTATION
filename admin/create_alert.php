<?php
/**
 * Create Manual Alert
 * Allows admin to create safety alerts
 * File: admin/create_alert.php
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';
require_once '../config/functions.php';

$success = '';
$error = '';

$unresolved_incidents = [];

// --- 1. Fetch all UNRESOLVED incidents for the dropdown ---
$sql_unresolved = "SELECT id, type, severity, description, created_at FROM incidents WHERE status != 'Resolved' ORDER BY created_at DESC LIMIT 50";
$result_unresolved = $conn->query($sql_unresolved);

if ($result_unresolved) {
    while ($row = $result_unresolved->fetch_assoc()) {
        $unresolved_incidents[] = $row;
    }
} else {
    // This is not a fatal error, but should be logged/displayed
    $error .= '<div class="alert error">Error fetching unresolved incidents: ' . $conn->error . '</div>';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $severity = sanitize_input($_POST['severity']);
    $message = sanitize_input($_POST['message']);
    $expires_in = intval($_POST['expires_in']);
    $incident_id = !empty($_POST['incident_id']) ? intval($_POST['incident_id']) : null;
    
    // Validate
    if (empty($severity) || empty($message)) {
        $error = 'Severity and message are required';
    } else {
        // Calculate expiry time
        $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in hours"));
        
        // Insert alert
        $stmt = mysqli_prepare($conn, "INSERT INTO alerts (incident_id, severity, message, expires_at) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $incident_id, $severity, $message, $expires_at);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Alert created successfully!';
        } else {
            $error = 'Failed to create alert';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Alert - Admin</title>
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
        <?php if (!empty($success)): ?>
        <div class="success-message">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="error-message">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2 style="margin-bottom: 20px; color: #333;">Create New Alert</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label for="severity">Severity Level <span class="required">*</span></label>
                    <select id="severity" name="severity" required>
                        <option value="">-- Select Severity --</option>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message">Alert Message <span class="required">*</span></label>
                    <textarea id="message" name="message" placeholder="Enter alert message for public..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="expires_in">Expires In <span class="required">*</span></label>
                    <select id="expires_in" name="expires_in" required>
                        <option value="1">1 Hour</option>
                        <option value="6" selected>6 Hours</option>
                        <option value="12">12 Hours</option>
                        <option value="24">24 Hours</option>
                    </select>
                </div>

                <!--<div class="form-group">-->
                <!--    <label for="incident_id">Related Incident ID (Optional)</label>-->
                <!--    <input type="number" id="incident_id" name="incident_id" placeholder="Enter incident ID if related to a specific report">-->
                <!--</div>-->
                
                <div class="form-group">
                    <label for="incident_id">Related Incident ID (Unresolved Reports):</label>
                    <select id="incident_id" name="incident_id">
                        <option value="0">-- Not Related to a Specific Incident --</option>
                        <?php if (empty($unresolved_incidents)): ?>
                            <option value="0" disabled>No unresolved incidents found.</option>
                        <?php else: ?>
                            <?php 
                            foreach ($unresolved_incidents as $incident) {
                                // Truncate description for display
                                $desc_preview = htmlspecialchars(substr($incident['description'], 0, 45));
                                if (strlen($incident['description']) > 45) {
                                    $desc_preview .= '...';
                                }
                                $time_ago = date('M j, l H:i a', strtotime($incident['created_at']));

                                // Create the option tag
                                echo "<option value=\"{$incident['id']}\">";
                                echo "{$incident['severity']} | {$incident['type']} | ({$time_ago}) - {$desc_preview}";
                                echo "</option>";
                            }
                            ?>
                        <?php endif; ?>
                    </select>
                </div>

                <?php 
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
                ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <button type="submit" class="btn btn-primary">Create Alert</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php close_connection(); ?>