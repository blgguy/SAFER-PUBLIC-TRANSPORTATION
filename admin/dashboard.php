<?php
/**
 * Admin Dashboard
 * Main admin control panel
 * File: admin/dashboard.php
 * * UPDATED: Includes pagination, fetches associated images, adds a loader, and uses a JS slideshow modal.
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';
require_once '../config/functions.php';

// --- CONFIGURATION ---
$incidents_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1


// --- PHP DATA COLLECTION FOR CHARTS & STATS ---

// 1. Get statistics (Original Code - No Change)
$stats = [];
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM incidents WHERE DATE(created_at) = CURDATE()");
$stats['today'] = mysqli_fetch_assoc($result)['count'];
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM incidents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['week'] = mysqli_fetch_assoc($result)['count'];
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM incidents WHERE severity = 'Critical' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stats['critical'] = mysqli_fetch_assoc($result)['count'];
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM admin_users");
$stats['admins'] = mysqli_fetch_assoc($result)['count'];

// 2. Data for Daily Incident Trend (Line/Bar Chart) - Last 7 Days (No Change)
$trend_data = [];
$query_trend = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM incidents 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY date ORDER BY date ASC";
$result_trend = mysqli_query($conn, $query_trend);
while ($row = mysqli_fetch_assoc($result_trend)) {
    $trend_data[date('M d', strtotime($row['date']))] = $row['count'];
}
$chart_labels = [];
$chart_counts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('M d', strtotime("-$i days"));
    $chart_labels[] = $date;
    $chart_counts[] = $trend_data[$date] ?? 0;
}

// 3. Data for Severity Breakdown (Pie/Doughnut Chart) (No Change)
$severity_breakdown = [];
$query_severity = "SELECT severity, COUNT(*) as count 
                   FROM incidents 
                   WHERE status != 'resolved' 
                   GROUP BY severity";
$result_severity = mysqli_query($conn, $query_severity);
while ($row = mysqli_fetch_assoc($result_severity)) {
    $severity_breakdown[$row['severity']] = $row['count'];
}

// --- PAGINATION AND INCIDENT FETCHING ---

// 4.1. Get total number of incidents
$total_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM incidents");
$total_incidents = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_incidents / $incidents_per_page);

// 4.2. Calculate the OFFSET
$offset = ($current_page - 1) * $incidents_per_page;
if ($offset < 0) $offset = 0;

// 4.3. Get incidents for the current page
$query = "SELECT * FROM incidents ORDER BY created_at DESC LIMIT $incidents_per_page OFFSET $offset";
$incidents_result = mysqli_query($conn, $query);

$incidents = [];
$base_dir = __DIR__ . '/../'; // Project root relative to this admin script

while ($row = mysqli_fetch_assoc($incidents_result)) {
    // 4.4. Fetch associated pictures for each incident
    $pictures = get_incident_pictures($conn, $row['id']);
    
    // 4.5. Server-side check for file existence (Crucial for "null" check)
    $valid_pictures = [];
    foreach ($pictures as $picture_path) {
        // Check if the physical file exists on the server
        if (file_exists($base_dir . $picture_path)) {
            $valid_pictures[] = $picture_path;
        }
    }
    
    $row['pictures'] = $valid_pictures; // Store only existing paths
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
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* Existing CSS for layout, stats, and table... */
        /* --- LOADER STYLES --- */
        #loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            transition: opacity 0.3s;
        }
        .spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #0066cc;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* --- MODAL SLIDESHOW STYLES --- */
        .modal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 2000;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.9);
        }
        .modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 800px;
            height: auto;
        }
        #caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            text-align: center;
            color: #ccc;
            padding: 10px 0;
            height: 50px;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }
        .prev, .next {
            cursor: pointer;
            position: absolute;
            top: 50%;
            width: auto;
            padding: 16px;
            margin-top: -50px;
            color: white;
            font-weight: bold;
            font-size: 20px;
            transition: 0.6s ease;
            border-radius: 0 3px 3px 0;
            user-select: none;
            background-color: rgba(0,0,0,0.5);
        }
        .next {
            right: 0;
            border-radius: 3px 0 0 3px;
        }
        .prev:hover, .next:hover, .close:hover {
            opacity: 0.8;
        }
        .slide-image {
            width: 100%;
            max-height: 80vh;
            object-fit: contain;
            user-select: none;
            cursor: zoom-in;
        }
        /* Existing CSS for table, stats, etc. (Not repeated here) */
        
          /* Existing CSS... */
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
        
        /* Chart Styles */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); height: 400px; }
        .chart-container h2 { margin-bottom: 15px; font-size: 18px; color: #333; }
        .chart-canvas { height: 100%; width: 100%; }
        
        /* Incidents Table Styles */
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
        .description-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        
        /* New Styles for Pictures and Pagination */
        .picture-preview { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 5px; cursor: pointer; border: 1px solid #ddd; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            background: #e9ecef;
            color: #0066cc;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
        }
        .pagination a:hover { background: #0066cc; color: white; }
        .pagination .active { background: #0066cc; color: white; pointer-events: none; }
        .pagination .disabled { background: #f1f1f1; color: #ccc; pointer-events: none; }
        
        @media (max-width: 992px) {
             .charts-grid { grid-template-columns: 1fr; }
             .chart-container { height: 350px; }
             .header { flex-direction: column; gap: 10px; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            table { font-size: 11px; }
            .description-cell { max-width: 80px; }
            .picture-preview { width: 30px; height: 30px; }
            th:nth-child(5), td:nth-child(5) { display: none; } /* Hide Description on small screens */
        }
    </style>
</head>
<body style="visibility: hidden;"> 

    <div id="loader-overlay">
        <div class="spinner"></div>
    </div>

    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <div class="modal-content">
            <img class="slide-image" id="modalImage">
            
            <a class="prev" onclick="plusSlides(-1)">&#10094;</a>
            <a class="next" onclick="plusSlides(1)">&#10095;</a>
        </div>
        <div id="caption"></div>
    </div>

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
            <div class="stat-card"><h3>REPORTS TODAY</h3><div class="stat-number"><?php echo $stats['today']; ?></div></div>
            <div class="stat-card"><h3>REPORTS THIS WEEK</h3><div class="stat-number"><?php echo $stats['week']; ?></div></div>
            <div class="stat-card critical"><h3>CRITICAL (24 HOURS)</h3><div class="stat-number"><?php echo $stats['critical']; ?></div></div>
            <div class="stat-card"><h3>TOTAL ADMINS</h3><div class="stat-number"><?php echo $stats['admins']; ?></div></div>
        </div>

        <div class="charts-grid">
            <div class="chart-container">
                <h2>Daily Incident Trend (Last 7 Days)</h2>
                <canvas id="dailyTrendChart" class="chart-canvas"></canvas> 
            </div>
            <div class="chart-container">
                <h2>Severity Breakdown</h2>
                <canvas id="severityChart" class="chart-canvas"></canvas>
            </div>
        </div>
        
        <div class="incidents-section">
            <div class="section-header">
                <h2>All Incidents (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)</h2>
                <a href="create_alert.php" class="btn btn-success">+ Create Alert</a>
            </div>
            <?php if (count($incidents) > 0): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Transport</th>
                            <th style="min-width: 100px;">Pictures</th> <th>Description</th>
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
                            
                            <td>
                                <?php if (!empty($incident['pictures'])): ?>
                                    <button 
                                        class="btn btn-success" 
                                        onclick="openModal('<?php echo implode(',', $incident['pictures']); ?>')"
                                        style="padding: 4px 8px; font-size: 11px;">
                                        View (<?php echo count($incident['pictures']); ?>)
                                    </button>
                                <?php else: ?>
                                    <span style="color: #6c757d;">N/A</span>
                                <?php endif; ?>
                            </td>
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
            </div>
            
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>">Previous</a>
                <?php else: ?>
                    <span class="disabled">Previous</span>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $current_page): ?>
                        <span class="active"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>">Next</a>
                <?php else: ?>
                    <span class="disabled">Next</span>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <p style="text-align: center; padding: 40px; color: #666;">No incidents found on this page.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // --- LOADER SCRIPT ---
        window.addEventListener('load', () => {
            const loader = document.getElementById('loader-overlay');
            const body = document.body;
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.style.display = 'none';
                body.style.visibility = 'visible';
            }, 300); // Wait for fade out
        });

        // --- CHART SCRIPTS (Omitted for brevity) ---
        
        const dailyTrendLabels = <?php echo json_encode($chart_labels); ?>;
        const dailyTrendData = <?php echo json_encode($chart_counts); ?>;
        
        const severityLabels = <?php echo json_encode(array_keys($severity_breakdown)); ?>;
        const severityData = <?php echo json_encode(array_values($severity_breakdown)); ?>;

        function renderDailyTrendChart() {
            const ctx = document.getElementById('dailyTrendChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dailyTrendLabels,
                    datasets: [{
                        label: 'Reports per Day',
                        data: dailyTrendData,
                        backgroundColor: 'rgba(0, 102, 204, 0.5)',
                        borderColor: '#0066cc',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Number of Reports' } },
                        x: { grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

        function renderSeverityChart() {
            const ctx = document.getElementById('severityChart').getContext('2d');
            
            const severityColors = {
                'Critical': '#dc3545',
                'High': '#fd7e14',
                'Medium': '#ffc107',
                'Low': '#28a745'
            };
            
            const backgroundColors = severityLabels.map(label => severityColors[label] || '#999');

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: severityLabels,
                    datasets: [{
                        data: severityData,
                        backgroundColor: backgroundColors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        title: { display: false }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderDailyTrendChart();
            renderSeverityChart();
        });


        // --- SLIDESHOW MODAL SCRIPT ---
        let slideIndex = 1;
        let currentPictures = [];

        function openModal(picturePathsString) {
            // Convert comma-separated string back to array of relative paths
            currentPictures = picturePathsString.split(',');
            if (currentPictures.length === 0 || currentPictures[0] === "") return;
            
            document.getElementById('imageModal').style.display = "block";
            showSlides(1);
        }

        function closeModal() {
            document.getElementById('imageModal').style.display = "none";
            currentPictures = []; // Clear pictures when closing
        }

        function plusSlides(n) {
            showSlides(slideIndex += n);
        }

        function showSlides(n) {
            if (currentPictures.length === 0) return;

            // Loop slides
            if (n > currentPictures.length) { slideIndex = 1 }
            if (n < 1) { slideIndex = currentPictures.length }

            const imagePath = currentPictures[slideIndex - 1];
            const modalImage = document.getElementById('modalImage');
            const captionText = document.getElementById('caption');

            // The image path must be relative to the browser's current location, 
            // which is ../uploads/pictures/... from the admin folder.
            modalImage.src = '../' + imagePath; 
            captionText.innerHTML = `Image ${slideIndex} of ${currentPictures.length}`;

            // Handle navigation button visibility if only one image
            const prevBtn = document.querySelector('.prev');
            const nextBtn = document.querySelector('.next');
            if (currentPictures.length <= 1) {
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'none';
            } else {
                prevBtn.style.display = 'block';
                nextBtn.style.display = 'block';
            }
        }

        // Close modal when user clicks outside the content
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php close_connection(); ?>