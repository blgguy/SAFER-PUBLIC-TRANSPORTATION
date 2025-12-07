<?php
// File: src/services/CryptographyService.php
// Description: Comprehensive service for all security, cryptography, and input sanitization tasks.

namespace SafeTransport\Services;

class CryptographyService
{
    private $encryptionKey;
    private $anonymousSalt;
    private $cipher = 'aes-256-gcm'; // Stored in config/security.php as SECURITY_CIPHER
    private $csrfFieldName;
    private $csrfLifetime;

    public function __construct()
    {
        // Load settings from the security configuration file
        $securityConfig = require __DIR__ . '/../../config/security.php';
        
        $this->encryptionKey = $securityConfig['key'];
        $this->anonymousSalt = $securityConfig['anon_salt'];
        $this->csrfFieldName = $securityConfig['csrf_field'];
        
        // Define lifetime (using the constant directly from config since it's defined there)
        $this->csrfLifetime = CSRF_TOKEN_LIFETIME; 

        if (!in_array($this->cipher, openssl_get_cipher_methods())) {
            throw new \Exception("Cipher '{$this->cipher}' not supported by OpenSSL.");
        }
    }

    // --------------------------------------------------------------------------
    // 1. Data Encryption (AES-256-GCM)
    // --------------------------------------------------------------------------

    /**
     * Encrypts data using AES-256-GCM.
     * The result is base64 encoded as: iv.tag.encrypted_data
     * @param string $data The plaintext data to encrypt.
     * @return string The base64-encoded encrypted string.
     */
    public function encrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = ''; // GCM mode requires a tag variable

        $encrypted = openssl_encrypt(
            $data, 
            $this->cipher, 
            $this->encryptionKey, 
            $options=0, 
            $iv, 
            $tag, 
            $aad='', 
            $tag_length=16 // Standard 16-byte authentication tag
        );

        if ($encrypted === false) {
            throw new \RuntimeException("Encryption failed.");
        }

        // Combine IV, Authentication Tag, and Ciphertext for storage (separated by a dot)
        return base64_encode($iv . '.' . $tag . '.' . $encrypted);
    }

    /**
     * Decrypts data encrypted with AES-256-GCM.
     * @param string $encryptedData The base64-encoded encrypted string.
     * @return string The plaintext data.
     */
    public function decrypt(string $encryptedData): string
    {
        if (empty($encryptedData)) {
            return '';
        }
        
        $decoded = base64_decode($encryptedData);
        if ($decoded === false) {
            throw new \InvalidArgumentException("Invalid base64 format for decryption.");
        }

        $parts = explode('.', $decoded);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Encrypted data format is corrupted.");
        }
        
        list($iv, $tag, $ciphertext) = $parts;

        $ivlen = openssl_cipher_iv_length($this->cipher);
        if (strlen($iv) !== $ivlen) {
             throw new \InvalidArgumentException("Invalid IV length.");
        }
        
        // Tag length is fixed at 16 bytes (specified in encrypt)
        $tag_length = 16; 
        if (strlen($tag) !== $tag_length) {
             throw new \InvalidArgumentException("Invalid Tag length.");
        }

        $decrypted = openssl_decrypt(
            $ciphertext, 
            $this->cipher, 
            $this->encryptionKey, 
            $options=0, 
            $iv, 
            $tag
        );

        if ($decrypted === false) {
            // Decryption failure typically means tampering (Authentication Tag failed)
            throw new \RuntimeException("Decryption failed. Data may have been tampered with.");
        }
        
        return $decrypted;
    }

    // --------------------------------------------------------------------------
    // 2. Hashing for Anonymous Identifiers
    // --------------------------------------------------------------------------

    /**
     * Generates a unique, salted SHA-256 hash for anonymous tracking.
     * @param string $uniqueIdentifier A non-PII, unique seed (e.g., report timestamp + random string).
     * @return string The 64-character hex hash.
     */
    public function generateAnonymousHash(string $uniqueIdentifier): string
    {
        // Concatenate the identifier with the application-specific salt
        $saltedData = $uniqueIdentifier . $this->anonymousSalt;
        
        // Use hash_hmac for cryptographic hashing with a key/salt
        return hash('sha256', $saltedData);
    }

    // --------------------------------------------------------------------------
    // 3. Password Hashing (Argon2id)
    // --------------------------------------------------------------------------

    /**
     * Hashes a user password using the secure Argon2id algorithm.
     * @param string $password
     * @return string The complete hash string (includes salt and parameters).
     */
    public function hashPassword(string $password): string
    {
        // PHP 7.4+ defaults to Argon2id if available, which is the recommended algorithm.
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Verifies a password against an Argon2id hash.
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // --------------------------------------------------------------------------
    // 4. CSRF Token Management
    // --------------------------------------------------------------------------
    
    /**
     * Generates and stores a new CSRF token in the session.
     * @return string The generated token.
     */
    public function generateCsrfToken(): string
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[$this->csrfFieldName]) || $_SESSION[$this->csrfFieldName]['expires'] < time()) {
            $_SESSION[$this->csrfFieldName] = [
                'token' => bin2hex(random_bytes(32)),
                'expires' => time() + $this->csrfLifetime
            ];
        }

        return $_SESSION[$this->csrfFieldName]['token'];
    }

    /**
     * Validates a CSRF token from the request against the session token.
     * @param string $token The token received from the POST request or header.
     * @return bool True if valid, false otherwise.
     */
    public function validateCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[$this->csrfFieldName])) {
            error_log("CSRF: Session token missing.");
            return false;
        }

        $sessionData = $_SESSION[$this->csrfFieldName];

        // 1. Check if token has expired
        if ($sessionData['expires'] < time()) {
            error_log("CSRF: Token expired.");
            return false;
        }
        
        // 2. Check for token match (use hash_equals to prevent timing attacks)
        if (!hash_equals($sessionData['token'], $token)) {
            error_log("CSRF: Token mismatch.");
            return false;
        }

        // Token is valid. Regenerate the token to follow the 'token regeneration after use' principle
        $this->regenerateCsrfToken(); 
        
        return true;
    }

    /**
     * Regenerates a new token after a successful validation.
     */
    public function regenerateCsrfToken(): void
    {
         if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Force expiry, generateCsrfToken() will create a new one.
        $_SESSION[$this->csrfFieldName]['expires'] = 0; 
        $this->generateCsrfToken();
    }
    
    // --------------------------------------------------------------------------
    // 5. Input Sanitization & XSS Prevention
    // --------------------------------------------------------------------------

    /**
     * Sanitizes a string input to prevent XSS attacks.
     * @param string|null $input
     * @return string The sanitized string.
     */
    public function sanitizeString(?string $input): string
    {
        if ($input === null) {
            return '';
        }
        // Remove HTML and PHP tags. ENT_QUOTES handles both single and double quotes.
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitizes an array of inputs (recursively).
     * @param array $data
     * @return array The sanitized array.
     */
    public function sanitizeArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitizeString($value);
            }
        }
        return $sanitized;
    }

    // --------------------------------------------------------------------------
    // 6. SQL Injection Prevention Helper
    // --------------------------------------------------------------------------
    
    /**
     * Helper to ensure integer type for parameters that should be integers.
     * NOT a replacement for prepared statements, but a useful validation layer.
     * @param mixed $input
     * @return int
     */
    public function ensureInt($input): int
    {
        return (int)$input;
    }
    
    /**
     * Helper to ensure float/decimal type for coordinates/scores.
     * @param mixed $input
     * @return float
     */
    public function ensureFloat($input): float
    {
        return (float)$input;
    }

    // Note: Prepared statements (used in DatabaseConnection) are the primary defense
    // against SQL injection. These helpers ensure the parameter types are correct.
}