<?php
// File: config/app_config.php
// Description: General application and environment settings.

// --- 1. Environment and Base Settings ---
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // e.g., 'production', 'staging', 'development'
define('APP_BASE_URL', getenv('APP_BASE_URL') ?: 'http:yor-domain.com'); // http://localhost:8080/');
define('APP_NAME', 'Safe Transport System');
define('APP_TIMEZONE', 'Africa/Lagos');
date_default_timezone_set(APP_TIMEZONE);

// --- 2. Logging and Debugging ---
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'warning'); // e.g., 'debug', 'info', 'warning', 'error'
define('DEBUG_MODE', APP_ENV === 'development');
define('LOG_FILE_PATH', __DIR__ . '/../logs/app.log');

// --- 3. External API Keys (Load from ENV) ---
// Key for Geocoding/Mapping services (e.g., Google Maps, OpenCage)
define('MAPS_API_KEY', getenv('MAPS_API_KEY') ?: 'API Key'); 
// Key for external Email sending service (e.g., SendGrid, Mailgun)
define('EMAIL_API_KEY', getenv('EMAIL_API_KEY') ?: 'API Key');

// --- 4. Email Settings ---
define('EMAIL_FROM_ADDRESS', 'no-reply@example.com');
define('EMAIL_FROM_NAME', APP_NAME . ' Alerts');
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.example.com');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_SECURE', 'tls'); // Use 'ssl' or 'tls'

// --- 5. Reporting Limits ---
define('API_RATE_LIMIT_REPORTS', 5); // Reports per hour per IP
define('API_RATE_LIMIT_TIME', 3600); // 1 hour

// Export the configuration array
return [
    'env' => APP_ENV,
    'url' => APP_BASE_URL,
    'name' => APP_NAME,
    'timezone' => APP_TIMEZONE,
    'maps_key' => MAPS_API_KEY,
    'log_level' => LOG_LEVEL,
    'rate_limit' => ['reports' => API_RATE_LIMIT_REPORTS, 'time' => API_RATE_LIMIT_TIME]

];

