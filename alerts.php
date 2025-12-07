<?php
/**
 * Public Safety Alerts Page
 * Displays active alerts from the last 24 hours with map integration
 * File: alerts.php
 */

// Include database connection and helper functions
require_once 'config/db.php';
require_once 'config/functions.php';

// Fetch active alerts (not expired, last 24 hours)
$query = "SELECT a.*, i.type as incident_type, i.transport_mode, i.latitude, i.longitude 
          FROM alerts a 
          LEFT JOIN incidents i ON a.incident_id = i.id 
          WHERE a.expires_at > NOW() 
          AND a.is_active = 1
          ORDER BY 
            FIELD(a.severity, 'Critical', 'High', 'Medium', 'Low'),
            a.created_at DESC";

$result = mysqli_query($conn, $query);
$alerts = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $alerts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300"> <!-- Auto-refresh every 5 minutes -->
    <title>Alerts - SAFER PUBLIC TRANSPORTATION IN INDIA</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        header { background: #0066cc; color: white; padding: 1rem; text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .page-title { text-align: center; margin: 30px 0; color: #333; }
        .alert-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
        .alert-card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 20px; border-left: 5px solid; }
        .alert-card.critical { border-left-color: #dc3545; }
        .alert-card.high { border-left-color: #fd7e14; }
        .alert-card.medium { border-left-color: #ffc107; }
        .alert-card.low { border-left-color: #28a745; }
        .severity-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; color: white; margin-bottom: 10px; }
        .severity-badge.critical { background: #dc3545; }
        .severity-badge.high { background: #fd7e14; }
        .severity-badge.medium { background: #ffc107; color: #333; }
        .severity-badge.low { background: #28a745; }
        .alert-meta { display: flex; justify-content: space-between; font-size: 14px; color: #666; margin-bottom: 10px; }
        .alert-message { color: #333; line-height: 1.6; margin: 15px 0; }
        .location-info { background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 14px; margin-top: 10px; }
        .map-btn { display: inline-block; padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; margin-top: 10px; cursor: pointer; border: none; }
        .map-btn:hover { background: #218838; }
        .distance-badge { display: inline-block; padding: 4px 10px; background: #007bff; color: white; border-radius: 12px; font-size: 12px; margin-left: 10px; }
        .no-alerts { text-align: center; padding: 60px 20px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .no-alerts-icon { font-size: 60px; margin-bottom: 20px; }
        .refresh-note { text-align: center; color: #666; font-size: 14px; margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #0066cc; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .btn:hover { background: #0052a3; }
        
        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); }
        .modal-content { background-color: white; margin: 2% auto; padding: 20px; width: 90%; max-width: 1000px; border-radius: 8px; max-height: 90vh; overflow-y: auto; }
        .close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px; }
        .close-modal:hover { color: #000; }
        #map { height: 500px; width: 100%; border-radius: 8px; margin-top: 15px; }
        .map-info { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 15px; }
        .map-info h3 { margin-bottom: 10px; color: #333; }
        .map-info p { margin: 5px 0; color: #666; }
        
        @media (max-width: 768px) { 
            .alert-grid { grid-template-columns: 1fr; } 
            .modal-content { width: 95%; margin: 5% auto; padding: 15px; }
            #map { height: 400px; }
        }
    </style>
</head>
<body>
    <header>
        <h1>🚨 Active Safety Alerts</h1>
        <p>Stay informed about safety incidents in your area</p>
    </header>

    <div class="container">
        
        <div style="margin-buttom: 10;">
            <a href="index.php" style="color: #0066cc; text-decoration: none;">← Back to Home</a>
        </div><br>
        <div class="page-title">
            <h2>Current Alerts</h2>
            <p style="color: #666;">Showing active alerts from the last 24 hours</p>
        </div>

        <?php if (count($alerts) > 0): ?>
        <div class="alert-grid">
            <?php foreach ($alerts as $alert): ?>
            <div class="alert-card <?php echo strtolower($alert['severity']); ?>">
                <span class="severity-badge <?php echo strtolower($alert['severity']); ?>">
                    <?php echo htmlspecialchars($alert['severity']); ?>
                </span>
                
                <div class="alert-meta">
                    <span><strong>Type:</strong> <?php echo htmlspecialchars($alert['incident_type'] ?? 'General'); ?></span>
                    <span><?php echo time_ago($alert['created_at']); ?></span>
                </div>

                <?php if ($alert['transport_mode']): ?>
                <div style="font-size: 14px; color: #666; margin-bottom: 10px;">
                    <strong>Transport:</strong> <?php echo htmlspecialchars($alert['transport_mode']); ?>
                </div>
                <?php endif; ?>

                <div class="alert-message">
                    <?php echo htmlspecialchars($alert['message']); ?>
                </div>

                <?php if ($alert['latitude'] && $alert['longitude']): ?>
                <div class="location-info">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            📍 <strong>Location:</strong><br>
                            <span style="font-size: 12px;">
                                Lat: <?php echo number_format($alert['latitude'], 6); ?>, 
                                Lng: <?php echo number_format($alert['longitude'], 6); ?>
                            </span>
                            <span class="distance-badge" id="distance-<?php echo $alert['id']; ?>">Calculating...</span>
                        </div>
                    </div>
                    <button class="map-btn" onclick="showMap(<?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>, '<?php echo htmlspecialchars(addslashes($alert['incident_type'] ?? 'Alert')); ?>', '<?php echo htmlspecialchars(addslashes($alert['severity'])); ?>', <?php echo $alert['id']; ?>)">
                        🗺️ View on Map
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-alerts">
            <div class="no-alerts-icon">✓</div>
            <h2 style="color: #28a745;">No Active Alerts</h2>
            <p style="color: #666; margin-top: 10px;">
                There are currently no active safety alerts in your area.<br>
                This page updates automatically every 5 minutes.
            </p>
            <a href="incidents.php" class="btn">View Recent Incidents</a>
        </div>
        <?php endif; ?>

        <div class="refresh-note">
            Page automatically refreshes every 5 minutes | 
            <a href="index.php" style="color: #0066cc;">Back to Home</a> | 
            <a href="report.php" style="color: #0066cc;">Report Incident</a>
        </div>
    </div>

    <!-- Map Modal -->
    <div id="mapModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeMap()">&times;</span>
            <h2 id="modalTitle">Alert Location</h2>
            <div class="map-info">
                <h3>Distance Information</h3>
                <p><strong>Your Location:</strong> <span id="userLocation">Detecting...</span></p>
                <p><strong>Alert Location:</strong> <span id="alertLocation"></span></p>
                <p><strong>Distance:</strong> <span id="modalDistance">Calculating...</span></p>
                <p><strong>Severity:</strong> <span id="modalSeverity"></span></p>
            </div>
            <div id="map"></div>
        </div>
    </div>

    <script>
        let map;
        let userLat, userLng;
        const alerts = <?php echo json_encode($alerts); ?>;

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
                return (km * 1000).toFixed(0) + ' meters away';
            } else {
                return km.toFixed(2) + ' km away';
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

        // Update distance badges for all alerts
        function updateAllDistances(error = false) {
            alerts.forEach(alert => {
                const badge = document.getElementById('distance-' + alert.id);
                if (badge) {
                    if (error || !userLat || !userLng) {
                        badge.textContent = 'Location unavailable';
                        badge.style.background = '#6c757d';
                    } else if (alert.latitude && alert.longitude) {
                        const distance = calculateDistance(userLat, userLng, alert.latitude, alert.longitude);
                        badge.textContent = formatDistance(distance);
                    }
                }
            });
        }

        // Show map modal
        function showMap(lat, lng, type, severity, alertId) {
            const modal = document.getElementById('mapModal');
            modal.style.display = 'block';
            
            document.getElementById('modalTitle').textContent = type + ' - Location';
            document.getElementById('alertLocation').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            document.getElementById('modalSeverity').textContent = severity;
            document.getElementById('modalSeverity').className = 'severity-badge ' + severity.toLowerCase();

            // Initialize or reset map
            if (map) {
                map.remove();
            }

            map = L.map('map').setView([lat, lng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            // Add alert marker
            const alertIcon = L.divIcon({
                className: 'custom-marker',
                html: '<div style="background: #dc3545; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                iconSize: [30, 30]
            });
            
            L.marker([lat, lng], {icon: alertIcon})
                .addTo(map)
                .bindPopup(`<b>${type}</b><br>Severity: ${severity}`)
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
                document.getElementById('modalDistance').textContent = formatDistance(distance);
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