<?php
/**
 * Recent Incidents List
 * Displays recent incident reports with filters and map integration
 * File: incidents.php
 */

require_once 'config/db.php';
require_once 'config/functions.php';

// Get filter parameters
$filter_severity = isset($_GET['severity']) ? sanitize_input($_GET['severity']) : '';
$filter_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$filter_time = isset($_GET['time']) ? sanitize_input($_GET['time']) : '30';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where = [];
$params = [];
$types = '';

// Time filter
switch($filter_time) {
    case '1':
        $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        break;
    case '7':
        $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30':
    default:
        $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
}

// Severity filter
if (!empty($filter_severity)) {
    $where[] = "severity = ?";
    $params[] = $filter_severity;
    $types .= 's';
}

// Type filter
if (!empty($filter_type)) {
    $where[] = "type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count total records
$count_query = "SELECT COUNT(*) as total FROM incidents $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Fetch incidents
$query = "SELECT * FROM incidents $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$incidents = [];
while ($row = mysqli_fetch_assoc($result)) {
    $incidents[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Incidents - SAFER PUBLIC TRANSPORTATION IN INDIA | By Aminu Muhammed BCA-DBMS</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        header { background: #0066cc; color: white; padding: 1rem; text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .filter-group select { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #0066cc; color: white; }
        .btn-primary:hover { background: #0052a3; }
        .btn-map { padding: 6px 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-map:hover { background: #218838; }
        .stats { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-box { flex: 1; min-width: 150px; background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-number { font-size: 28px; font-weight: bold; color: #0066cc; }
        .stat-label { font-size: 14px; color: #666; margin-top: 5px; }
        .incidents-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0066cc; color: white; padding: 15px; text-align: left; font-weight: bold; }
        td { padding: 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f8f9fa; }
        .severity-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; color: white; display: inline-block; }
        .severity-critical { background: #dc3545; }
        .severity-high { background: #fd7e14; }
        .severity-medium { background: #ffc107; color: #333; }
        .severity-low { background: #28a745; }
        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-new { background: #17a2b8; color: white; }
        .status-resolved { background: #28a745; color: white; }
        .distance-badge { display: inline-block; padding: 3px 8px; background: #007bff; color: white; border-radius: 10px; font-size: 11px; margin-left: 5px; }
        .incident-card { display: none; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a { padding: 8px 12px; background: white; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #0066cc; }
        .pagination a.active { background: #0066cc; color: white; }
        .pagination a:hover { background: #e7f3ff; }
        
        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); }
        .modal-content { background-color: white; margin: 2% auto; padding: 20px; width: 90%; max-width: 1000px; border-radius: 8px; max-height: 90vh; overflow-y: auto; }
        .close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px; }
        .close-modal:hover { color: #000; }
        #map { height: 500px; width: 100%; border-radius: 8px; margin-top: 15px; }
        .map-info { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 15px; }
        .map-info h3 { margin-bottom: 10px; color: #333; }
        .map-info p { margin: 5px 0; color: #666; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px; }
        .info-item { background: white; padding: 10px; border-radius: 4px; }
        .info-item strong { display: block; color: #333; margin-bottom: 5px; }
        
        @media (max-width: 768px) {
            .incidents-table { display: none; }
            .incident-card { display: block; background: white; margin-bottom: 15px; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .card-header { display: flex; justify-content: space-between; margin-bottom: 10px; align-items: center; }
            .card-body { font-size: 14px; line-height: 1.6; }
            .card-row { margin: 8px 0; }
            .modal-content { width: 95%; margin: 5% auto; padding: 15px; }
            #map { height: 400px; }
        }
    </style>
</head>
<body>
    <header>
        <h1>📋 Recent Incidents</h1>
        <p>View safety reports from the community</p>
    </header>

    <div class="container">
        
        <div style="margin-bottom: 10px;">
            <a href="index.php" style="color: #0066cc; text-decoration: none;">← Back to Home</a>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo $total_records; ?></div>
                <div class="stat-label">Total Reports</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count(array_filter($incidents, fn($i) => $i['severity'] === 'Critical')); ?></div>
                <div class="stat-label">Critical</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count(array_filter($incidents, fn($i) => $i['status'] === 'new')); ?></div>
                <div class="stat-label">New Reports</div>
            </div>
        </div>

        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Time Period</label>
                        <select name="time">
                            <option value="1" <?php echo $filter_time == '1' ? 'selected' : ''; ?>>Last 24 Hours</option>
                            <option value="7" <?php echo $filter_time == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30" <?php echo $filter_time == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Severity</label>
                        <select name="severity">
                            <option value="">All Severities</option>
                            <option value="Critical" <?php echo $filter_severity == 'Critical' ? 'selected' : ''; ?>>Critical</option>
                            <option value="High" <?php echo $filter_severity == 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Medium" <?php echo $filter_severity == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Low" <?php echo $filter_severity == 'Low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Incident Type</label>
                        <select name="type">
                            <option value="">All Types</option>
                            <option value="Harassment" <?php echo $filter_type == 'Harassment' ? 'selected' : ''; ?>>Harassment</option>
                            <option value="Theft" <?php echo $filter_type == 'Theft' ? 'selected' : ''; ?>>Theft</option>
                            <option value="Violence" <?php echo $filter_type == 'Violence' ? 'selected' : ''; ?>>Violence</option>
                            <option value="Suspicious Activity" <?php echo $filter_type == 'Suspicious Activity' ? 'selected' : ''; ?>>Suspicious Activity</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>

        <?php if (count($incidents) > 0): ?>
        <!-- Desktop Table View -->
        <div class="incidents-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Severity</th>
                        <th>Transport</th>
                        <th>Location</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
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
                        <td style="font-size: 12px;">
                            <?php echo number_format($incident['latitude'], 4) . ', ' . number_format($incident['longitude'], 4); ?>
                            <span class="distance-badge" id="distance-<?php echo $incident['id']; ?>">...</span>
                        </td>
                        <td><?php echo time_ago($incident['created_at']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $incident['status']; ?>">
                                <?php echo ucfirst($incident['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn-map" onclick="showIncidentMap(<?php echo htmlspecialchars(json_encode($incident)); ?>)">
                                🗺️ Map
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <?php foreach ($incidents as $incident): ?>
        <div class="incident-card">
            <div class="card-header">
                <div>
                    <strong>#<?php echo str_pad($incident['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                    <span class="distance-badge" id="distance-mobile-<?php echo $incident['id']; ?>">...</span>
                </div>
                <span class="severity-badge severity-<?php echo strtolower($incident['severity']); ?>">
                    <?php echo htmlspecialchars($incident['severity']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="card-row"><strong>Type:</strong> <?php echo htmlspecialchars($incident['type']); ?></div>
                <div class="card-row"><strong>Transport:</strong> <?php echo htmlspecialchars($incident['transport_mode']); ?></div>
                <div class="card-row"><strong>Time:</strong> <?php echo time_ago($incident['created_at']); ?></div>
                <div class="card-row"><strong>Status:</strong> 
                    <span class="status-badge status-<?php echo $incident['status']; ?>">
                        <?php echo ucfirst($incident['status']); ?>
                    </span>
                </div>
                <div class="card-row">
                    <button class="btn-map" onclick="showIncidentMap(<?php echo htmlspecialchars(json_encode($incident)); ?>)">
                        🗺️ View on Map
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&severity=<?php echo $filter_severity; ?>&type=<?php echo $filter_type; ?>&time=<?php echo $filter_time; ?>" 
                   class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px;">
            <h2>No Incidents Found</h2>
            <p style="color: #666; margin-top: 10px;">Try adjusting your filters to see more results.</p>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" style="color: #0066cc; text-decoration: none;">← Back to Home</a> | 
            <a href="alerts.php" style="color: #0066cc; text-decoration: none;">View Active Alerts</a>
        </div>
    </div>

    <!-- Map Modal -->
    <div id="mapModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeMap()">&times;</span>
            <h2 id="modalTitle">Incident Location</h2>
            <div class="map-info">
                <h3>Incident Details</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Incident ID</strong>
                        <span id="incidentId"></span>
                    </div>
                    <div class="info-item">
                        <strong>Type</strong>
                        <span id="incidentType"></span>
                    </div>
                    <div class="info-item">
                        <strong>Severity</strong>
                        <span id="incidentSeverity"></span>
                    </div>
                    <div class="info-item">
                        <strong>Transport Mode</strong>
                        <span id="incidentTransport"></span>
                    </div>
                    <div class="info-item">
                        <strong>Reported</strong>
                        <span id="incidentTime"></span>
                    </div>
                    <div class="info-item">
                        <strong>Status</strong>
                        <span id="incidentStatus"></span>
                    </div>
                </div>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                    <p><strong>Your Location:</strong> <span id="userLocation">Detecting...</span></p>
                    <p><strong>Incident Location:</strong> <span id="incidentLocation"></span></p>
                    <p><strong>Distance:</strong> <span id="modalDistance">Calculating...</span></p>
                </div>
            </div>
            <div id="map"></div>
        </div>
    </div>

    <script>
        let map;
        let userLat, userLng;
        const incidents = <?php echo json_encode($incidents); ?>;

        // Calculate distance using Haversine formula
        function calculateDistance(lat1, lng1, lat2, lng2) {
            const R = 6371; // Earth's radius in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                     Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                     Math.sin(dLng/2) * Math.sin(dLng/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        // Format distance for display
        function formatDistance(km) {
            if (km < 1) {
                return (km * 1000).toFixed(0) + 'm';
            } else {
                return km.toFixed(2) + 'km';
            }
        }

        // Get user's current location
        function getUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        userLat = position.coords.latitude;
                        userLng = position.coords.longitude;
                        updateAllDistances();
                    },
                    function(error) {
                        console.log('Geolocation error:', error);
                        updateAllDistances(true);
                    }
                );
            } else {
                updateAllDistances(true);
            }
        }

        // Update distance badges for all incidents
        function updateAllDistances(error = false) {
            incidents.forEach(incident => {
                const badge = document.getElementById('distance-' + incident.id);
                const badgeMobile = document.getElementById('distance-mobile-' + incident.id);
                
                const updateBadge = (el) => {
                    if (!el) return;
                    if (error || !userLat || !userLng) {
                        el.textContent = 'N/A';
                        el.style.background = '#6c757d';
                    } else {
                        const distance = calculateDistance(userLat, userLng, parseFloat(incident.latitude), parseFloat(incident.longitude));
                        el.textContent = formatDistance(distance);
                    }
                };
                
                updateBadge(badge);
                updateBadge(badgeMobile);
            });
        }

        // Format time ago
        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'Just now';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + ' min ago';
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            const days = Math.floor(hours / 24);
            return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        }

        // Show incident on map
        function showIncidentMap(incident) {
            const modal = document.getElementById('mapModal');
            modal.style.display = 'block';
            
            // Update incident details
            document.getElementById('modalTitle').textContent = incident.type + ' - Incident Location';
            document.getElementById('incidentId').textContent = '#' + String(incident.id).padStart(6, '0');
            document.getElementById('incidentType').textContent = incident.type;
            
            const severityEl = document.getElementById('incidentSeverity');
            severityEl.textContent = incident.severity;
            severityEl.className = 'severity-badge severity-' + incident.severity.toLowerCase();
            
            document.getElementById('incidentTransport').textContent = incident.transport_mode;
            document.getElementById('incidentTime').textContent = formatTimeAgo(incident.created_at);
            
            const statusEl = document.getElementById('incidentStatus');
            statusEl.textContent = incident.status.charAt(0).toUpperCase() + incident.status.slice(1);
            statusEl.className = 'status-badge status-' + incident.status;
            
            document.getElementById('incidentLocation').textContent = 
                parseFloat(incident.latitude).toFixed(6) + ', ' + parseFloat(incident.longitude).toFixed(6);

            // Initialize or reset map
            if (map) {
                map.remove();
            }

            const lat = parseFloat(incident.latitude);
            const lng = parseFloat(incident.longitude);

            map = L.map('map').setView([lat, lng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            // Get marker color based on severity
            let markerColor = '#dc3545';
            switch(incident.severity.toLowerCase()) {
                case 'high': markerColor = '#fd7e14'; break;
                case 'medium': markerColor = '#ffc107'; break;
                case 'low': markerColor = '#28a745'; break;
            }

            // Add incident marker
            const incidentIcon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="background: ${markerColor}; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
                iconSize: [30, 30]
            });
            
            L.marker([lat, lng], {icon: incidentIcon})
                .addTo(map)
                .bindPopup(`<b>${incident.type}</b><br>Severity: ${incident.severity}<br>Transport: ${incident.transport_mode}`)
                .openPopup();

            // Add user location marker if available
            if (userLat && userLng) {
                const userIcon = L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background: #007bff; width: 25px; height: 25px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                    iconSize: [25, 25]
                });

                L.marker([userLat, userLng], {icon: userIcon})
                    .addTo(map)
                    .bindPopup('<b>Your Location</b>');

                // Draw line between locations
                L.polyline([[userLat, userLng], [lat, lng]], {
                    color: '#007bff',
                    weight: 3,
                    opacity: 0.6,
                    dashArray: '10, 10'
                }).addTo(map);

                // Fit bounds to show both markers
                const bounds = L.latLngBounds([[userLat, userLng], [lat, lng]]);
                map.fitBounds(bounds, {padding: [50, 50]});

                // Update distance
                const distance = calculateDistance(userLat, userLng, lat, lng);
                document.getElementById('userLocation').textContent = `${userLat.toFixed(6)}, ${userLng.toFixed(6)}`;
                document.getElementById('modalDistance').textContent = formatDistance(distance) + ' away';
            } else {
                document.getElementById('userLocation').textContent = 'Location not available';
                document.getElementById('modalDistance').textContent = 'Unable to calculate';
            }

            // Invalidate size after modal is shown
            setTimeout(() => map.invalidateSize(), 100);
        }

        // Close map modal
        function closeMap() {
            document.getElementById('mapModal').style.display = 'none';
            if (map) {
                map.remove();
                map = null;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('mapModal');
            if (event.target === modal) {
                closeMap();
            }
        }

        // Initialize on page load
        window.onload = function() {
            getUserLocation();
        }
    </script>
</body>
</html>
<?php close_connection(); ?>