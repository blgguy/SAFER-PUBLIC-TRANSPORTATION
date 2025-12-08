<?php
// File: public/api/submit-report.php
// Description: REST API endpoint for anonymous incident report submission.

// --------------------------------------------------------------------------
// 0. Setup and Dependencies
// --------------------------------------------------------------------------
use SafeTransport\Core\DatabaseConnection;
use SafeTransport\Services\CryptographyService;
use SafeTransport\Services\AnonymousReportingService;

// Configuration and class autoloading (Simulated)
// In a real project, use Composer autoloading. For this guide, we require files.
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../src/core/DatabaseConnection.php';
require_once __DIR__ . '/../../src/services/CryptographyService.php';
require_once __DIR__ . '/../../src/services/AnonymousReportingService.php';

// Initialization of core services
$db = DatabaseConnection::getInstance();
$cryptoService = new CryptographyService();
$reportingService = new AnonymousReportingService($db, $cryptoService);

// Set headers for JSON response
header('Content-Type: application/json');
define('SUCCESS_CODE', 200);
define('BAD_REQUEST_CODE', 400);
define('FORBIDDEN_CODE', 403);
define('TOO_MANY_REQUESTS_CODE', 429);
define('INTERNAL_SERVER_ERROR_CODE', 500);

// Helper function to send JSON response and terminate
function sendResponse(int $httpCode, array $data) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

// --------------------------------------------------------------------------
// 1. Validate Request Method
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, [
        'success' => false,
        'report_id' => null,
        'message' => 'Method Not Allowed. This endpoint only accepts POST requests.',
        'error' => 'Invalid Request Method'
    ]);
}

// --------------------------------------------------------------------------
// 2. Validate Content-Type
// --------------------------------------------------------------------------
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
     sendResponse(BAD_REQUEST_CODE, [
        'success' => false,
        'report_id' => null,
        'message' => 'Unsupported Content-Type. Please send data as application/json.',
        'error' => 'Invalid Content-Type'
    ]);
}

// --------------------------------------------------------------------------
// 3. Rate Limiting (5 reports per IP per hour)
// --------------------------------------------------------------------------
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$rateLimitKey = "rate_limit_report:{$ipAddress}";
$maxReports = API_RATE_LIMIT_REPORTS;
$timeWindow = API_RATE_LIMIT_TIME; // 3600 seconds (1 hour)

// --- Pseudo-Rate Limiting Implementation (Requires Redis/Memcached in production) ---
// For this example, we'll use a placeholder file/DB table to demonstrate the logic.
// In a real application, you'd use a robust solution.

// Check rate limit (Placeholder logic)
$reportCount = 0; // Replace with actual check against cache/database
// $reportCount = check_rate_limit($rateLimitKey, $timeWindow); 

if ($reportCount >= $maxReports) {
    error_log("RATE LIMIT: IP {$ipAddress} blocked from submitting report.");
    sendResponse(TOO_MANY_REQUESTS_CODE, [
        'success' => false,
        'report_id' => null,
        'message' => 'Too many reports submitted from this address. Please wait an hour.',
        'error' => 'Rate Limit Exceeded'
    ]);
}
// --- End Pseudo-Rate Limiting Implementation ---


// --------------------------------------------------------------------------
// 4. Input Parsing and Sanitization
// --------------------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if ($data === null) {
    sendResponse(BAD_REQUEST_CODE, [
        'success' => false,
        'report_id' => null,
        'message' => 'Invalid JSON payload received.',
        'error' => 'JSON Decode Error'
    ]);
}

// --------------------------------------------------------------------------
// 5. CSRF Protection (for web submissions)
// --------------------------------------------------------------------------
// Note: API calls from mobile apps or non-browser sources typically skip CSRF, 
// but web forms sending to this endpoint must include the token.
$csrfToken = $data[CSRF_FIELD_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

// Check if a CSRF token exists and is valid
if (!empty($csrfToken) && !$cryptoService->validateCsrfToken($csrfToken)) {
    sendResponse(FORBIDDEN_CODE, [
        'success' => false,
        'report_id' => null,
        'message' => 'Security check failed. Invalid or missing CSRF token.',
        'error' => 'CSRF Protection Failed'
    ]);
}

// --------------------------------------------------------------------------
// 6. Process Report Submission
// --------------------------------------------------------------------------
try {
    // The AnonymousReportingService handles validation, encryption, and DB insertion
    $result = $reportingService->submitReport($data);

    // Update rate limit counter (Placeholder logic)
    // increment_rate_limit($rateLimitKey, $timeWindow);

    if ($result['success']) {
        sendResponse(SUCCESS_CODE, [
            'success' => true,
            'report_id' => $result['report_id'],
            'message' => $result['message'],
            'error' => null
        ]);
    } else {
        // If service returns false, it's typically a validation or known error
        $httpCode = strpos($result['error'], 'Validation Error') !== false ? BAD_REQUEST_CODE : INTERNAL_SERVER_ERROR_CODE;
        sendResponse($httpCode, [
            'success' => false,
            'report_id' => null,
            'message' => $result['error'],
            'error' => $result['details'] ?? 'Submission failed.'
        ]);
    }

} catch (Exception $e) {
    error_log("FATAL API ERROR: " . $e->getMessage());
    sendResponse(INTERNAL_SERVER_ERROR_CODE, [
        'success' => false,
        'report_id' => null,
        'message' => 'An unexpected server error occurred.',
        'error' => DEBUG_MODE ? $e->getMessage() : 'Internal Server Error'
    ]);
}