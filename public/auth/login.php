<?php
// File: public/api/auth/login.php
// Description: API endpoint for administrator authentication.

// --------------------------------------------------------------------------
// 0. Setup and Dependencies
// --------------------------------------------------------------------------
use SafeTransport\Core\DatabaseConnection;

require_once __DIR__ . '/../../../config/app_config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../src/core/DatabaseConnection.php';

// Initialization
$db = DatabaseConnection::getInstance();

// Set headers for JSON response
header('Content-Type: application/json');
define('SUCCESS_CODE', 200);
define('BAD_REQUEST_CODE', 400);
define('UNAUTHORIZED_CODE', 401);
define('TOO_MANY_REQUESTS_CODE', 429);

// Helper function to send JSON response and terminate
function sendResponse(int $httpCode, array $data) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

/**
 * Generates a JSON Web Token (JWT) placeholder for the session.
 * In a full system, this would involve hashing the header/payload with a secret key.
 * @param int $userId
 * @param string $role
 * @return string The generated token string.
 */
function generateSessionToken(int $userId, string $role): string {
    // Basic JWT structure placeholder: Header.Payload.Signature
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'user_id' => $userId,
        'role' => $role,
        'iat' => time(), // Issued At
        'exp' => time() + (3600 * 8) // Expires in 8 hours
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    // For this demonstration, we'll use a simple static signature placeholder.
    // REALITY: Use hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true)
    $signaturePlaceholder = 'A_SECURE_HASHED_SIGNATURE_PLACEHOLDER'; 

    return "{$base64UrlHeader}.{$base64UrlPayload}.{$signaturePlaceholder}";
}

/**
 * Logs the login attempt to the audit table.
 * @param int|null $userId
 * @param string $ip
 * @param bool $success
 */
function logLoginAttempt(?int $userId, string $ip, bool $success): void {
    global $db;
    try {
        $db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILURE',
            'details' => json_encode(['ip_address' => $ip, 'username_attempt' => $_POST['username'] ?? 'N/A']),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (\PDOException $e) {
        error_log("Failed to log audit event: " . $e->getMessage());
    }
}

// --------------------------------------------------------------------------
// 1. Validate Request Method and Content
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['success' => false, 'message' => 'Method Not Allowed.']);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data['username']) || empty($data['password'])) {
    sendResponse(BAD_REQUEST_CODE, ['success' => false, 'message' => 'Username and password are required.']);
}

$username = filter_var($data['username'], FILTER_SANITIZE_EMAIL);
$password = $data['password'];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// --------------------------------------------------------------------------
// 2. Rate Limiting Check (Placeholder)
// --------------------------------------------------------------------------
// NOTE: This requires a cache (Redis/Memcached) or dedicated DB table to track attempts by IP/username.
// For this example, we skip the logic but include the check.

$attempts = 0; // Replace with actual rate limit check against IP address
if ($attempts >= 5) {
    logLoginAttempt(null, $ipAddress, false);
    sendResponse(TOO_MANY_REQUESTS_CODE, ['success' => false, 'message' => 'Too many failed login attempts. Try again in 10 minutes.']);
}

// --------------------------------------------------------------------------
// 3. Authentication
// --------------------------------------------------------------------------
try {
    $sql = "SELECT user_id, password_hash, role, status FROM users WHERE username = ? AND role IN ('Admin', 'Verifier', 'Analyst')";
    $user = $db->query($sql, [$username])->fetch();

    if ($user && $user['status'] === 'Active' && password_verify($password, $user['password_hash'])) {
        
        // --- Authentication SUCCESS ---
        
        $token = generateSessionToken((int)$user['user_id'], $user['role']);

        logLoginAttempt((int)$user['user_id'], $ipAddress, true);

        sendResponse(SUCCESS_CODE, [
            'success' => true,
            'token' => $token,
            'user_id' => (int)$user['user_id'],
            'role' => $user['role'],
            'message' => 'Login successful.'
        ]);

    } else {
        
        // --- Authentication FAILURE ---
        
        // Placeholder: increment_login_attempt($ipAddress);
        logLoginAttempt($user ? (int)$user['user_id'] : null, $ipAddress, false);

        if ($user && $user['status'] !== 'Active') {
            sendResponse(UNAUTHORIZED_CODE, ['success' => false, 'message' => 'Account is inactive or locked.']);
        } else {
            // Generic message to prevent username enumeration
            sendResponse(UNAUTHORIZED_CODE, ['success' => false, 'message' => 'Invalid username or password.']);
        }
    }

} catch (\Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
    sendResponse(500, ['success' => false, 'message' => 'An internal server error occurred.']);
}