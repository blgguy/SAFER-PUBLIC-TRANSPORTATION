<?php
// File: public/api/safety-alerts.php
// Description: REST API endpoint to retrieve active safety alerts.

// --------------------------------------------------------------------------
// 0. Setup and Dependencies
// --------------------------------------------------------------------------
use SafeTransport\Core\DatabaseConnection;

// Configuration and class autoloading (Simulated)
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../src/core/DatabaseConnection.php';

$db = DatabaseConnection::getInstance();

// Set headers for JSON response
header('Content-Type: application/json');
define('SUCCESS_CODE', 200);
define('BAD_REQUEST_CODE', 400);

// Helper function to send JSON response and terminate
function sendResponse(int $httpCode, array $data) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

// --------------------------------------------------------------------------
// 1. Parameter Handling
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(405, ['success' => false, 'message' => 'Method Not Allowed.']);
}

$userLat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$userLng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
$maxRadius = filter_input(INPUT_GET, 'radius_km', FILTER_VALIDATE_FLOAT) ?: 5.0; // Default filter radius 5km

$hasLocation = ($userLat !== false && $userLng !== false && $userLat !== null && $userLng !== null);

// --------------------------------------------------------------------------
// 2. Caching Implementation (Simulated)
// --------------------------------------------------------------------------
$cacheKey = "alerts:" . ($hasLocation ? "{$userLat}:{$userLng}:{$maxRadius}" : "global");
$cacheDuration = 120; // 2 minutes

// Placeholder for cache check:
// $cachedResult = get_from_cache($cacheKey);
// if ($cachedResult) {
//     sendResponse(SUCCESS_CODE, $cachedResult);
// }

$cachedResult = null; // Assume no cache hit for now

// --------------------------------------------------------------------------
// 3. Dynamic SQL Query Construction
// --------------------------------------------------------------------------

if (!$cachedResult) {
    // Base query for active alerts (not expired)
    $sql = "
        SELECT 
            a.alert_id,
            a.alert_type,
            a.severity,
            a.message,
            a.location_radius,
            a.sent_at,
            a.expires_at,
            l.latitude,
            l.longitude
        FROM safety_alerts a
        LEFT JOIN incident_reports r ON a.report_id = r.report_id
        LEFT JOIN locations l ON r.location_id = l.location_id
        WHERE 
            a.expires_at > NOW() 
    ";

    $params = [];
    $whereClauses = [];
    $orderBy = [];
    
    // Severity mapping for sorting (Critical=1, Warning=2, Informational=3)
    $orderBy[] = "FIELD(a.severity, 'Critical', 'Warning', 'Informational')";
    $orderBy[] = "a.sent_at DESC";

    // ----------------------------------------------------------------------
    // Proximity Filtering (if location provided)
    // ----------------------------------------------------------------------
    if ($hasLocation && $userLat >= -90 && $userLat <= 90 && $userLng >= -180 && $userLng <= 180) {
        $R = 6371; // Earth's radius in km

        // Haversine formula for distance calculation between user and alert center (location of the report)
        $distanceSql = "(
            {$R} * ACOS(
                COS(RADIANS(?)) * COS(RADIANS(l.latitude)) * COS(RADIANS(l.longitude) - RADIANS(?)) 
                + SIN(RADIANS(?)) * SIN(RADIANS(l.latitude))
            )
        )";
        
        // Only include alerts where the user's distance to the alert center is less than 
        // the alert's radius (meaning the user is in the affected area) AND less than the max filter radius.
        $sql .= " HAVING ({$distanceSql} <= a.location_radius) AND ({$distanceSql} <= ?)";
        
        // Add Haversine parameters and user filter radius
        // Note: The distance calculation is complex to add in the HAVING clause without repeating.
        // We'll simplify the WHERE clause and use the location_radius constraint as the proximity filter.
        
        // We need to re-structure to ensure the location is relevant if location is provided:
        $sql = "
            SELECT 
                a.alert_id,
                a.alert_type,
                a.severity,
                a.message,
                a.location_radius,
                a.sent_at,
                a.expires_at,
                l.latitude,
                l.longitude,
                (
                    {$R} * ACOS(
                        COS(RADIANS({$userLat})) * COS(RADIANS(l.latitude)) * COS(RADIANS(l.longitude) - RADIANS({$userLng})) 
                        + SIN(RADIANS({$userLat})) * SIN(RADIANS(l.latitude))
                    )
                ) AS distance_to_user_km
            FROM safety_alerts a
            LEFT JOIN incident_reports r ON a.report_id = r.report_id
            LEFT JOIN locations l ON r.location_id = l.location_id
            WHERE a.expires_at > NOW()
            HAVING (
                -- Alert is global (no location_id) OR
                l.latitude IS NULL OR
                -- User is within the alert radius AND within the max filter radius
                (distance_to_user_km <= a.location_radius AND distance_to_user_km <= {$maxRadius})
            )
        ";
        // When location is provided, order by distance to user first
        array_unshift($orderBy, "distance_to_user_km ASC");
    }


    $sql .= " ORDER BY " . implode(', ', $orderBy) . " LIMIT 20";

    // --------------------------------------------------------------------------
    // 4. Execute Query
    // --------------------------------------------------------------------------
    try {
        $alerts = $db->query($sql, $params);
        $alerts = $alerts ?: [];

    } catch (\Exception $e) {
        error_log("DB Query Error (Safety Alerts): " . $e->getMessage());
        sendResponse(INTERNAL_SERVER_ERROR_CODE, [
            'success' => false,
            'message' => 'An internal error occurred while fetching alerts.',
            'error' => DEBUG_MODE ? $e->getMessage() : 'Database Error'
        ]);
    }

    // --------------------------------------------------------------------------
    // 5. Format Output and Cache Result
    // --------------------------------------------------------------------------
    $formattedAlerts = [];
    foreach ($alerts as $alert) {
        $formattedAlerts[] = [
            'alert_id' => $alert['alert_id'],
            'alert_type' => $alert['alert_type'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
            'location_radius_km' => (float)$alert['location_radius'],
            'sent_at' => $alert['sent_at'],
            'expires_at' => $alert['expires_at'],
            'location' => $alert['latitude'] ? [
                'lat' => (float)$alert['latitude'],
                'lng' => (float)$alert['longitude']
            ] : null,
            // Only include distance if location was provided in the request
            'distance_km' => $hasLocation && isset($alert['distance_to_user_km']) ? round((float)$alert['distance_to_user_km'], 2) : null
        ];
    }
    
    $finalResult = [
        'success' => true,
        'alerts' => $formattedAlerts,
        'count' => count($formattedAlerts)
    ];

    // Placeholder for setting cache:
    // set_to_cache($cacheKey, $finalResult, $cacheDuration);

    sendResponse(SUCCESS_CODE, $finalResult);
}