<?php
// File: src/services/AnonymousReportingService.php
// Description: Service class to handle the secure and anonymous submission of incident reports.

namespace SafeTransport\Services;

use SafeTransport\Core\DatabaseConnection;
use PDOException;
use Exception;

class AnonymousReportingService
{
    private $db;
    private $cryptoService;
    private $validSeverities = ['Low', 'Medium', 'High', 'Critical'];
    private $validTransportModes = ['Bus', 'Train', 'Subway', 'Taxi/RideShare', 'Walking', 'Cycling', 'Other'];

    public function __construct(DatabaseConnection $db, CryptographyService $cryptoService)
    {
        $this->db = $db;
        $this->cryptoService = $cryptoService;
    }

    /**
     * Validates the structure and content of the incoming report data.
     * @param array $data The raw report data.
     * @return bool True if validation passes.
     * @throws Exception with specific error message on failure.
     */
    private function validateReport(array $data): bool
    {
        // 1. Check for required fields
        $requiredFields = ['incident_type_id', 'latitude', 'longitude', 'description', 'severity', 'transportation_mode', 'timestamp'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Validation Error: Missing required field '{$field}'.");
            }
        }
        
        // 2. Validate data types and formats
        $incidentTypeId = $this->cryptoService->ensureInt($data['incident_type_id']);
        if ($incidentTypeId <= 0) {
            throw new Exception("Validation Error: Invalid incident type ID.");
        }
        
        $latitude = $this->cryptoService->ensureFloat($data['latitude']);
        $longitude = $this->cryptoService->ensureFloat($data['longitude']);
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new Exception("Validation Error: Invalid coordinates.");
        }

        // 3. Validate severity and transport mode against defined ENUMs
        if (!in_array($data['severity'], $this->validSeverities)) {
            throw new Exception("Validation Error: Invalid severity level.");
        }
        if (!in_array($data['transportation_mode'], $this->validTransportModes)) {
            throw new Exception("Validation Error: Invalid transportation mode.");
        }
        
        // 4. Validate description length
        $description = $this->cryptoService->sanitizeString($data['description']);
        if (strlen($description) < 10) {
            throw new Exception("Validation Error: Description is too short.");
        }

        // 5. Validate timestamp format (simple check)
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $data['timestamp']);
        if (!$date || $date->format('Y-m-d H:i:s') !== $data['timestamp']) {
            throw new Exception("Validation Error: Invalid timestamp format. Use 'YYYY-MM-DD HH:MM:SS'.");
        }

        return true;
    }

    /**
     * Triggers a safety alert for critical incidents.
     * This method would typically call an external queue/service, but here it's simple DB insertion.
     * @param string $reportId The ID of the report.
     * @param array $location The location data.
     */
    private function triggerCriticalAlert(string $reportId, array $location): void
    {
        try {
            // Generate a UUID for the alert
            $alertId = $this->generateUuid(); 
            
            // Critical alert details
            $alertData = [
                'alert_id' => $alertId,
                'report_id' => $reportId,
                'alert_type' => 'Emergency Broadcast',
                'severity' => 'Critical',
                'location_radius' => 1.0, // 1 km radius for critical
                'message' => 'CRITICAL SAFETY INCIDENT: Authorities are dispatched to the area.',
                // Alert expires 2 hours from now
                'expires_at' => date('Y-m-d H:i:s', time() + 7200) 
            ];

            $this->db->insert('safety_alerts', $alertData);
            error_log("AUDIT: Critical safety alert ({$alertId}) triggered by report {$reportId}.");
            
        } catch (PDOException $e) {
            error_log("ERROR: Failed to trigger safety alert for report {$reportId}. Reason: " . $e->getMessage());
        }
    }

    /**
     * Generates a unique UUID (v4) for IDs like report_id and alert_id.
     * @return string
     */
    private function generateUuid(): string
    {
        // Use PHP's built-in random_bytes for a cryptographically secure UUID
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100 (UUID v4)
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }


    /**
     * Submits a new anonymous report.
     * @param array $data The raw report data (must be sanitized/validated before this is called).
     * @return array The result containing success status and report_id.
     */
    public function submitReport(array $data): array
    {
        // Start database transaction
        $this->db->beginTransaction();

        try {
            // 1. Thorough Validation
            $this->validateReport($data);
            
            // Sanitize all string inputs
            $sanitizedData = $this->cryptoService->sanitizeArray($data);
            
            // 2. Prepare Unique IDs and Hash
            $reportId = $this->generateUuid();
            // The anonymous hash is generated from a non-PII seed (e.g., report ID + random string + salt)
            $anonymousHash = $this->cryptoService->generateAnonymousHash($reportId . time() . rand(0, 99999));
            
            // 3. Store Location Data Separately
            $locationData = [
                'latitude' => $sanitizedData['latitude'],
                'longitude' => $sanitizedData['longitude'],
                'address_description' => $sanitizedData['address_description'] ?? null,
                'transportation_mode' => $sanitizedData['transportation_mode'],
                'route_identifier' => $sanitizedData['route_identifier'] ?? null,
            ];
            $locationId = $this->db->insert('locations', $locationData);

            // 4. Encrypt Sensitive Data
            $encryptedDescription = $this->cryptoService->encrypt($sanitizedData['description']);
            
            // 5. Store Incident Report
            $reportData = [
                'report_id' => $reportId,
                'incident_type_id' => $this->cryptoService->ensureInt($sanitizedData['incident_type_id']),
                'location_id' => $locationId,
                'severity' => $sanitizedData['severity'],
                'description' => $encryptedDescription, // Encrypted!
                'timestamp' => $sanitizedData['timestamp'],
                'verification_score' => 0.00, // Default to unverified
                'status' => 'Pending',
                'anonymous_hash' => $anonymousHash,
            ];
            
            // Use query() for UUID insert since insert() assumes auto-increment integer ID
            $sql = "INSERT INTO incident_reports (report_id, incident_type_id, location_id, severity, description, timestamp, verification_score, status, anonymous_hash) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array_values($reportData);
            $this->db->query($sql, $params);


            // 6. Trigger Alerts for Critical Incidents
            if ($sanitizedData['severity'] === 'Critical') {
                $this->triggerCriticalAlert($reportId, $locationData);
            }
            
            // Commit transaction
            $this->db->commit();
            
            // 7. Log Operation
            error_log("AUDIT: New anonymous report submitted successfully. Report ID: {$reportId}, Hash: {$anonymousHash}.");

            return [
                'success' => true,
                'report_id' => $reportId, // Returned for user tracking
                'message' => 'Incident reported successfully. Thank you for making transport safer.'
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            $error = "DB Error during submission: " . $e->getMessage();
            error_log("ERROR: " . $error);
            return [
                'success' => false,
                'report_id' => null,
                'error' => 'A database error occurred during submission. Please try again.',
                'details' => DEBUG_MODE ? $e->getMessage() : 'Internal Server Error'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            $error = "Report Validation/Service Error: " . $e->getMessage();
            error_log("WARNING: " . $error);
            return [
                'success' => false,
                'report_id' => null,
                'error' => $e->getMessage(),
                'details' => 'Invalid or missing input data.'
            ];
        }
    }
}

// IMPORTANT: In a real app, inject dependencies like this:
/*
$db = DatabaseConnection::getInstance();
$crypto = new CryptographyService();
$reportingService = new AnonymousReportingService($db, $crypto);
*/