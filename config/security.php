<?php
// File: config/security.php
// Description: Security settings for encryption, sessions, and CSRF protection.

// --- 1. Cryptography Settings ---
define('SECURITY_CIPHER', 'AES-256-GCM');
define('SECURITY_KEY_LENGTH', 32); // 256 bits for AES-256

// CRITICAL: Load from a secure environment variable. MUST be 32 bytes (256 bits).
// This key is used for the AES-256-GCM encryption of sensitive report data.
$encryption_key = getenv('ENCRYPTION_KEY');
if (empty($encryption_key) || strlen($encryption_key) !== 32) {
    // In a production environment, this should throw an exception or fatal error.
    error_log("CRITICAL: ENCRYPTION_KEY is missing or incorrect length. Using placeholder.");
    $encryption_key = str_repeat('K', 32); // Placeholder - DO NOT USE IN PRODUCTION
}
define('ENCRYPTION_KEY', $encryption_key);

// CRITICAL: Salt for hashing anonymous identifiers. Must be unique to the app.
define('ANONYMOUS_HASH_SALT', getenv('ANONYMOUS_HASH_SALT') ?: 'SafeTransportDefaultSalt');

// --- 2. Session Configuration ---
define('SESSION_NAME', 'STSID'); // Custom session name to hide PHP default
define('SESSION_LIFETIME', 7200); // 2 hours in seconds

// Secure session settings (best practices)
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1); // Prevents JavaScript access (XSS defense)
ini_set('session.cookie_secure', getenv('APP_ENV') === 'production' ? 1 : 0); // Only send cookie over HTTPS
ini_set('session.cookie_samesite', 'Lax'); // CSRF defense

// --- 3. CSRF Protection Settings ---
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour token lifespan
define('CSRF_FIELD_NAME', '_csrf_token'); // Default name for the hidden input field

// --- 4. Brute Force Protection (for Admin Login) ---
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 600); // 10 minutes lockout

// Export the configuration constants
return [
    'cipher' => SECURITY_CIPHER,
    'key' => ENCRYPTION_KEY,
    'anon_salt' => ANONYMOUS_HASH_SALT,
    'session_name' => SESSION_NAME,
    'csrf_field' => CSRF_FIELD_NAME,
    'login_max_attempts' => LOGIN_MAX_ATTEMPTS,
];