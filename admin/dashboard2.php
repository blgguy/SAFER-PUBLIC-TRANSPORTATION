<?php
/**
 * Admin Dashboard
 * Main admin control panel
 * File: admin/dashboard.php
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';
require_once '../config/functions.php';

// Get statistics
$stats = [];

// Today's reports
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM incidents WHERE DATE(created_at) = CURDATE()");
$stats['today'] = mysqli_fetch_assoc($result)['count'];

// This week's reports
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM incidents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['week'] = mysqli_fetch_assoc($result)['count'];

// Critical incidents (last 24 hours)
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM incidents WHERE severity = 'Critical' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stats['critical'] = mysqli_fetch_assoc($result)['count'];

// Total admins
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM admin_users");
$stats['admins'] = mysqli_fetch_assoc($result)['count'];

// Get recent incidents (last 20)
$query = "SELECT * FROM incidents ORDER BY created_at DESC LIMIT 20";
$incidents_result = mysqli_query($conn, $query);
$incidents = [];
while ($row = mysqli_fetch_assoc($incidents_result)) {
    $incidents[] = $row;
}

// Get success message if any
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Safe Transport</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        a { margin-right: 20px; text-decoration: none; font-weight: bold; }
        .header { background: #0066cc; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .nav-menu { background: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav-menu a { margin-right: 20px; color: #0066cc; text-decoration: none; font-weight: bold; }
        .nav-menu a:hover { text-decoration: underline; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-number { font-size: 36px; font-weight: bold; color: #0066cc; }
        .stat-card.critical .stat-number { color: #dc3545; }
        .incidents-section { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: bold; color: #333; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f8f9fa; }
        .severity-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; color: white; }
        .severity-critical { background: #dc3545; }
        .severity-high { background: #fd7e14; }
        .severity-medium { background: #ffc107; color: #333; }
        .severity-low { background: #28a745; }
        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-new { background: #17a2b8; color: white; }
        .status-resolved { background: #28a745; color: white; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; }
        .description-cell { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            table { font-size: 12px; }
            .description-cell { max-width: 150px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ Safe Transport Admin</h1>
        <div class="user-info">
            <a href="profile.php"><span>👤 <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span></a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav-menu">
        <a href="dashboard.php">Dashboard</a>
        <a href="dashboard.php">All Incidents</a>
        <a href="create_alert.php">Create Alert</a>
        <a href="user.php">Admins</a>
        <a href="../index.html" target="_blank">View Site</a>
    </div>

    <div class="container">
        <?php if (!empty($success)): ?>
        <div class="success-message">
            ✅ <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>REPORTS TODAY</h3>
                <div class="stat-number"><?php echo $stats['today']; ?></div>
            </div>
            <div class="stat-card">
                <h3>REPORTS THIS WEEK</h3>
                <div class="stat-number"><?php echo $stats['week']; ?></div>
            </div>
            <div class="stat-card critical">
                <h3>CRITICAL (24 HOURS)</h3>
                <div class="stat-number"><?php echo $stats['critical']; ?></div>
            </div>
            <div class="stat-card">
                <h3>TOTAL ADMINS</h3>
                <div class="stat-number"><?php echo $stats['admins']; ?></div>
            </div>
        </div>

        <div class="incidents-section">
            <div class="section-header">
                <h2>Recent Incidents</h2>
                <a href="create_alert.php" class="btn btn-success">+ Create Alert</a>
            </div>

            <?php if (count($incidents) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Severity</th>
                        <th>Transport</th>
                        <th>Description</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $incident): ?>
                    <tr>
                        <td>#<?php echo str_pad($incident['id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($incident['type']); ?></td>
                        <td>
                            <span class="severity-badge severity-<?php echo strtolower($incident['severity']); ?>">
                                <?php echo htmlspecialchars($incident['severity']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($incident['transport_mode']); ?></td>
                        <td class="description-cell" title="<?php echo htmlspecialchars($incident['description']); ?>">
                            <?php echo htmlspecialchars(substr($incident['description'], 0, 50)) . '...'; ?>
                        </td>
                        <td><?php echo time_ago($incident['created_at']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $incident['status']; ?>">
                                <?php echo ucfirst($incident['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($incident['status'] === 'new'): ?>
                            <a href="resolve_incident.php?id=<?php echo $incident['id']; ?>" 
                               class="btn btn-success"
                               onclick="return confirm('Mark this incident as resolved?')">
                                Resolve
                            </a>
                            <?php endif; ?>
                            <a href="delete_incident.php?id=<?php echo $incident['id']; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to delete this incident?')">
                                Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; padding: 40px; color: #666;">No incidents reported yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php close_connection(); ?>