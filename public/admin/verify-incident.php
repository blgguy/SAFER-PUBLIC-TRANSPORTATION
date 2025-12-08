<?php
// File: public/api/admin/verify-incident.php
// Description: API endpoint for Verifiers/Admins to update incident status and trigger alerts.

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
define('FORBIDDEN_CODE', 403);
define('NOT_FOUND_CODE', 404);

// Helper function to send JSON response and terminate
function sendResponse(int $httpCode, array $data) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

/**
 * Maps report severity to alert type.
 * @param string $severity
 * @return string
 */
function mapSeverityToAlertType(string $severity): string {
    return match ($severity) {
        'Critical' => 'IMMEDIATE_DANGER',
        'High'     => 'SEVERE_WARNING',
        'Medium'   => 'SAFETY_ADVISORY',
        default    => 'INFORMATIONAL'
    };
}

/**
 * Logs the administrative action to the audit table.
 * @param int $userId
 * @param string $action
 * @param string $reportId
 * @param string $details
 */
function logAdminAction(int $userId, string $action, string $reportId, string $details): void {
    global $db;
    try {
        $db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'details' => json_encode(['report_id' => $reportId, 'admin_details' => $details]),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (\PDOException $e) {
        error_log("Failed to log audit event: " . $e->getMessage());
    }
}

// --------------------------------------------------------------------------
// 1. Authorization Check (Token Validation)
// --------------------------------------------------------------------------
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = preg_replace('/Bearer\s/', '', $authHeader);

// NOTE: AuthService is assumed to exist from an earlier layer/project scope.
// We simulate the validation and return data for simplicity.
// In reality, this would decode and verify the JWT signature.
// $auth = new AuthService();
// $user = $auth->validateToken($token); 
$user = [
    'user_id' => 101, // Placeholder Admin ID
    'role' => 'Admin'  // Must be 'Admin' or 'Verifier'
]; 
$authorizedRoles = ['Admin', 'Verifier'];

if (!$token || !$user || !in_array($user['role'], $authorizedRoles)) {
    sendResponse(UNAUTHORIZED_CODE, ['success' => false, 'message' => 'Unauthorized or session expired.']);
}
$userId = $user['user_id'];
$userRole = $user['role'];

// --------------------------------------------------------------------------
// 2. Validate Request Method and Input
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['success' => false, 'message' => 'Method Not Allowed.']);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$reportId = $data['report_id'] ?? null;
$action = $data['action'] ?? null;
$adminNotes = trim($data['admin_notes'] ?? '');

if (empty($reportId) || !in_array($action, ['Verify', 'Reject', 'Resolve'])) {
    sendResponse(BAD_REQUEST_CODE, ['success' => false, 'message' => 'Missing or invalid report_id or action.']);
}

// --------------------------------------------------------------------------
// 3. Retrieve Report Data
// --------------------------------------------------------------------------
try {
    $reportSql = "SELECT report_id, status, severity, description_encrypted, location_id, timestamp 
                  FROM incident_reports WHERE report_id = ?";
    $report = $db->query($reportSql, [$reportId])->fetch();

    if (!$report) {
        sendResponse(NOT_FOUND_CODE, ['success' => false, 'message' => 'Report not found.']);
    }

    $currentStatus = $report['status'];
    $newStatus = $action; // Map action directly to new status
    $reportSeverity = $report['severity'];
    $locationId = $report['location_id'];

} catch (\Exception $e) {
    error_log("Verification API DB read error: " . $e->getMessage());
    sendResponse(500, ['success' => false, 'message' => 'Internal database error during read.']);
}


// --------------------------------------------------------------------------
// 4. Update Report Status and Notes
// --------------------------------------------------------------------------
try {
    $updateSql = "UPDATE incident_reports SET status = ?, admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n', ?), updated_at = NOW() WHERE report_id = ?";
    $db->execute($updateSql, [$newStatus, "[$userRole - $userId] " . date('Y-m-d H:i') . ": $adminNotes", $reportId]);
    
    // Log the successful action
    logAdminAction($userId, "INCIDENT_STATUS_CHANGE_{$action}", $reportId, $adminNotes);

} catch (\Exception $e) {
    error_log("Verification API DB write error: " . $e->getMessage());
    sendResponse(500, ['success' => false, 'message' => 'Internal database error during update.']);
}


// --------------------------------------------------------------------------
// 5. Trigger Alert System (Safety_Alerts Logic)
// --------------------------------------------------------------------------

// --- Action: VERIFY (Create a new alert) ---
if ($action === 'Verify') {
    if ($currentStatus !== 'Verified') { // Prevent re-alerting if already verified
        try {
            // Get location radius (from locations table, assumed to be there or default 2km)
            $locationSql = "SELECT radius_km FROM locations WHERE location_id = ?";
            $location = $db->query($locationSql, [$locationId])->fetch();
            $radiusKm = $location['radius_km'] ?? 2.0;

            // Insert new safety alert
            $db->insert('safety_alerts', [
                'report_id' => $reportId,
                'alert_type' => mapSeverityToAlertType($reportSeverity),
                'severity' => $reportSeverity,
                'message' => "Verified report of a **{$reportSeverity}** incident in the area.",
                'location_radius' => $radiusKm,
                'sent_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+4 hours')) // Alert expires in 4 hours
            ]);
            logAdminAction($userId, "SAFETY_ALERT_CREATED", $reportId, "Alert triggered for 4 hours.");

        } catch (\Exception $e) {
            error_log("Alert Trigger Error on Verify: " . $e->getMessage());
            // Log error but don't fail the primary status update
        }
    }
}

// --- Action: RESOLVE (Expire existing alert) ---
if ($action === 'Resolve') {
    try {
        // Find and expire any existing safety alert linked to this report
        $expireSql = "UPDATE safety_alerts SET expires_at = NOW(), message = CONCAT(message, ' (RESOLVED)') WHERE report_id = ? AND expires_at > NOW()";
        $db->execute($expireSql, [$reportId]);
        
        if ($db->rowCount() > 0) {
            logAdminAction($userId, "SAFETY_ALERT_EXPIRED", $reportId, "Active alert expired due to resolution.");
        }
    } catch (\Exception $e) {
        error_log("Alert Expiration Error on Resolve: " . $e->getMessage());
    }
}


// --------------------------------------------------------------------------
// 6. Final Success Response
// --------------------------------------------------------------------------
sendResponse(SUCCESS_CODE, [
    'success' => true,
    'message' => "Report {$reportId} successfully updated to status: {$newStatus}.",
    'new_status' => $newStatus
]);