<?php
// File: public/api/nearby-incidents.php
// Description: REST API endpoint to retrieve anonymized incidents near a given location.

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
// 1. Validate Request Method and Parameters
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(405, ['success' => false, 'message' => 'Method Not Allowed.']);
}

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
$radius_km = filter_input(INPUT_GET, 'radius_km', FILTER_VALIDATE_FLOAT);
$days_back = filter_input(INPUT_GET, 'days_back', FILTER_VALID_INT);

// Set defaults and enforce constraints
$radius_km = max(0.1, min(100.0, $radius_km ?: 5.0)); // Min 0.1, Max 100, Default 5km
$days_back = max(1, min(365, $days_back ?: 7));       // Min 1, Max 365, Default 7 days

if ($lat === false || $lng === false || $lat === null || $lng === null || 
    $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    sendResponse(BAD_REQUEST_CODE, [
        'success' => false, 
        'message' => 'Invalid or missing coordinates (lat, lng).'
    ]);
}

// --------------------------------------------------------------------------
// 2. Caching Implementation (Simulated)
// --------------------------------------------------------------------------
// In a production environment, this would use Redis/Memcached.
$cacheKey = "incidents:{$lat}:{$lng}:{$radius_km}:{$days_back}";
$cacheDuration = 300; // 5 minutes

// Placeholder for cache check:
// $cachedResult = get_from_cache($cacheKey);
// if ($cachedResult) {
//     sendResponse(SUCCESS_CODE, $cachedResult);
// }

$cachedResult = null; // Assume no cache hit for now

// --------------------------------------------------------------------------
// 3. Database Query with Haversine Formula
// --------------------------------------------------------------------------

if (!$cachedResult) {
    // Earth's radius in kilometers (approximate)
    $R = 6371;
    
    // Haversine formula within the SQL to calculate distance and filter efficiently
    $sql = "
        SELECT 
            r.report_id,
            t.type_name AS incident_type,
            r.severity,
            r.timestamp,
            l.latitude,
            l.longitude,
            
            -- Haversine Formula for Distance Calculation
            (
                {$R} * ACOS(
                    COS(RADIANS(?)) * COS(RADIANS(l.latitude)) * COS(RADIANS(l.longitude) - RADIANS(?)) 
                    + SIN(RADIANS(?)) * SIN(RADIANS(l.latitude))
                )
            ) AS distance_km
            
        FROM incident_reports r
        JOIN locations l ON r.location_id = l.location_id
        JOIN incident_types t ON r.incident_type_id = t.type_id
        
        WHERE 
            -- 1. Filter by status: Verified or Pending only (Anonymized data is okay to show)
            r.status IN ('Verified', 'Pending') 
            
            -- 2. Filter by time range (7 days back by default)
            AND r.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            
        HAVING 
            -- 3. Filter by distance (Radius constraint)
            distance_km <= ?
            
        ORDER BY distance_km ASC, r.timestamp DESC
        
        LIMIT 50; -- Limit results to 50 max
    ";

    $params = [
        $lat,           // Param 1 for COS(RADIANS(?)) * ...
        $lng,           // Param 2 for COS(RADIANS(l.longitude) - RADIANS(?))
        $lat,           // Param 3 for SIN(RADIANS(?)) * ...
        $days_back,     // Param 4 for DATE_SUB(NOW(), INTERVAL ? DAY)
        $radius_km      // Param 5 for distance_km <= ?
    ];

    try {
        $incidents = $db->query($sql, $params);
        $incidents = $incidents ?: []; // Ensure it's an array

    } catch (\Exception $e) {
        error_log("DB Query Error (Nearby Incidents): " . $e->getMessage());
        sendResponse(INTERNAL_SERVER_ERROR_CODE, [
            'success' => false,
            'message' => 'An internal error occurred while fetching data.',
            'error' => DEBUG_MODE ? $e->getMessage() : 'Database Error'
        ]);
    }

    // --------------------------------------------------------------------------
    // 4. Format Output and Cache Result
    // --------------------------------------------------------------------------
    $formattedIncidents = [];
    foreach ($incidents as $inc) {
        $formattedIncidents[] = [
            // Only return anonymized, necessary fields
            'report_id' => $inc['report_id'], 
            'incident_type' => $inc['incident_type'],
            'severity' => $inc['severity'],
            'distance_km' => round($inc['distance_km'], 2),
            'timestamp' => $inc['timestamp'],
            'location' => [
                // Return location as coordinates (no street address for privacy)
                'lat' => (float)$inc['latitude'],
                'lng' => (float)$inc['longitude']
            ]
        ];
    }
    
    $finalResult = [
        'success' => true,
        'incidents' => $formattedIncidents,
        'count' => count($formattedIncidents)
    ];

    // Placeholder for setting cache:
    // set_to_cache($cacheKey, $finalResult, $cacheDuration);

    sendResponse(SUCCESS_CODE, $finalResult);
}