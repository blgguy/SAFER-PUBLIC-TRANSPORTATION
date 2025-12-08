<?php
// File: public/api/admin/data-aggregation.php
// Description: API endpoint to retrieve aggregated statistics for the administrative dashboard.

// --------------------------------------------------------------------------
// 0. Setup and Dependencies
// --------------------------------------------------------------------------
use SafeTransport\Core\DatabaseConnection;

require_once __DIR__ . '/../../../config/app_config.php';
require_once __DIR__ . '/../../../src/core/DatabaseConnection.php';
require_once __DIR__ . '/../../../src/services/AuthService.php'; // Placeholder for token validation

// Initialization
$db = DatabaseConnection::getInstance();

// Set headers for JSON response
header('Content-Type: application/json');
define('SUCCESS_CODE', 200);
define('BAD_REQUEST_CODE', 400);
define('UNAUTHORIZED_CODE', 401);

// Helper function to send JSON response and terminate
function sendResponse(int $httpCode, array $data) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

// --------------------------------------------------------------------------
// 1. Authorization Check (Token Validation)
// --------------------------------------------------------------------------
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = preg_replace('/Bearer\s/', '', $authHeader);

// NOTE: We rely on the Layer 7.1 simulation for token validation.
// Assume a successful validation returns user data.
$user = ['user_id' => 101, 'role' => 'Admin']; 
$authorizedRoles = ['Admin', 'Verifier', 'Analyst'];

if (!$token || !$user || !in_array($user['role'], $authorizedRoles)) {
    sendResponse(UNAUTHORIZED_CODE, ['success' => false, 'message' => 'Unauthorized or session expired.']);
}

// --------------------------------------------------------------------------
// 2. Input and Date Range Handling
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(405, ['success' => false, 'message' => 'Method Not Allowed.']);
}

$startDateInput = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
$endDateInput = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);

// Default to the last 7 days
$endDate = $endDateInput ? date('Y-m-d 23:59:59', strtotime($endDateInput)) : date('Y-m-d 23:59:59');
$startDate = $startDateInput ? date('Y-m-d 00:00:00', strtotime($startDateInput)) : date('Y-m-d 00:00:00', strtotime('-7 days'));

if (strtotime($startDate) > strtotime($endDate)) {
    sendResponse(BAD_REQUEST_CODE, ['success' => false, 'message' => 'Start date cannot be after end date.']);
}

// Global WHERE clause fragment
$dateFilter = " WHERE r.timestamp BETWEEN ? AND ? ";
$params = [$startDate, $endDate];


// --------------------------------------------------------------------------
// 3. Key Metrics Calculation
// --------------------------------------------------------------------------
$metrics = [];
$totalReports = 0;

try {
    // Metric 1: Total Reports, Verified/Resolved Count, Critical Count
    $sqlKeyMetrics = "
        SELECT 
            COUNT(r.report_id) AS total_reports,
            SUM(CASE WHEN r.status IN ('Verified', 'Resolved') THEN 1 ELSE 0 END) AS verified_resolved_count,
            SUM(CASE WHEN r.severity = 'Critical' THEN 1 ELSE 0 END) AS critical_count
        FROM incident_reports r
        " . $dateFilter;
        
    $keyMetrics = $db->query($sqlKeyMetrics, $params)->fetch();
    
    $totalReports = (int)$keyMetrics['total_reports'];
    $verifiedResolvedCount = (int)$keyMetrics['verified_resolved_count'];
    $criticalCount = (int)$keyMetrics['critical_count'];
    
    $verificationRate = ($totalReports > 0) ? round(($verifiedResolvedCount / $totalReports) * 100, 2) : 0.0;
    
    $metrics = [
        'total_reports' => $totalReports,
        'critical_count' => $criticalCount,
        'verification_rate' => $verificationRate,
        // Placeholder for Avg Response Time (Requires joining audit_logs and calculating difference)
        'avg_response_time' => 'N/A' 
    ];

    // Metric 2: Incident Type Breakdown
    $sqlTypeBreakdown = "
        SELECT t.type_name, COUNT(r.report_id) AS count
        FROM incident_reports r
        JOIN incident_types t ON r.incident_type_id = t.type_id
        " . $dateFilter . "
        GROUP BY t.type_name
        ORDER BY count DESC;
    ";
    $typeBreakdown = $db->query($sqlTypeBreakdown, $params)->fetchAll();
    
    $metrics['incident_type_breakdown'] = array_column($typeBreakdown, 'count', 'type_name');

    // Metric 3: Severity Breakdown
    $sqlSeverityBreakdown = "
        SELECT r.severity, COUNT(r.report_id) AS count
        FROM incident_reports r
        " . $dateFilter . "
        GROUP BY r.severity
        ORDER BY FIELD(r.severity, 'Critical', 'High', 'Medium', 'Low');
    ";
    $severityBreakdown = $db->query($sqlSeverityBreakdown, $params)->fetchAll();
    
    $metrics['severity_breakdown'] = array_column($severityBreakdown, 'count', 'severity');
    
    // Metric 4: Daily Trend
    $sqlDailyTrend = "
        SELECT DATE(r.timestamp) AS report_day, COUNT(r.report_id) AS count
        FROM incident_reports r
        " . $dateFilter . "
        GROUP BY report_day
        ORDER BY report_day ASC;
    ";
    $dailyTrend = $db->query($sqlDailyTrend, $params)->fetchAll();
    
    // Fill in missing days with zero count for chart plotting
    $trendMap = array_column($dailyTrend, 'count', 'report_day');
    $currentDay = strtotime($startDate);
    $endDay = strtotime($endDate);
    $dailyTrendComplete = [];
    
    while ($currentDay <= $endDay) {
        $dateStr = date('Y-m-d', $currentDay);
        $dailyTrendComplete[$dateStr] = (int)($trendMap[$dateStr] ?? 0);
        $currentDay = strtotime('+1 day', $currentDay);
    }
    
    $metrics['daily_trend'] = $dailyTrendComplete;
    
} catch (\Exception $e) {
    error_log("Data Aggregation DB Error: " . $e->getMessage());
    sendResponse(500, ['success' => false, 'message' => 'Internal database error during aggregation.', 'error' => DEBUG_MODE ? $e->getMessage() : 'Database Error']);
}


// --------------------------------------------------------------------------
// 4. Final Response
// --------------------------------------------------------------------------
sendResponse(SUCCESS_CODE, [
    'success' => true,
    'date_range' => ['start' => $startDate, 'end' => $endDate],
    'data' => $metrics
]);