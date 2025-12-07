<?php
/**
 * Incident Report Submission Handler
 * Processes incident reports, file uploads, and saves to database.
 * File: submit_report.php
 */

// Define upload constants (matching client-side validation)
const MAX_FILES = 3;
const MAX_FILE_SIZE = 2621440; // 2.5 MB in bytes
const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
// UPLOAD_DIR assumes the script is in 'public/' and assets are in 'public/uploads/'
const UPLOAD_DIR = 'uploads/'; 

// Include database connection and helper functions
// NOTE: You may need to adjust these paths if submit_report.php is not in the root public directory.
require_once 'config/db.php';
require_once 'config/functions.php';

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method. Please submit the form properly.');
}

// --- 1. INITIALIZE & VALIDATE CORE DATA ---
$errors = [];
$incident_id = null;

// Sanitize and validate input data
$incident_type = isset($_POST['incident_type']) ? sanitize_input($_POST['incident_type']) : '';
$transport_mode = isset($_POST['transport_mode']) ? sanitize_input($_POST['transport_mode']) : '';
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;
$description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';
$severity = isset($_POST['severity']) ? sanitize_input($_POST['severity']) : '';

// Validate required fields
if (empty($incident_type)) $errors[] = 'Incident type is required';
if (empty($transport_mode)) $errors[] = 'Transport mode is required';
if ($latitude == 0 || $longitude == 0) $errors[] = 'Valid location is required';
if (strlen($description) < 10) $errors[] = 'Description must be at least 10 characters';
if (strlen($description) > 500) $errors[] = 'Description cannot exceed 500 characters';
if (empty($severity)) $errors[] = 'Severity level is required';

// Validate severity values
$valid_severities = ['Low', 'Medium', 'High', 'Critical'];
if (!in_array($severity, $valid_severities)) $errors[] = 'Invalid severity level';

// --- 2. VALIDATE AND PRE-PROCESS PICTURES ---
$uploaded_files_data = [];

if (isset($_FILES['pictures'])) {
    $file_array = rearray_files($_FILES['pictures']); // Flattens the $_FILES array structure
    
    if (count($file_array) > MAX_FILES) {
        $errors[] = "Maximum of " . MAX_FILES . " pictures allowed.";
    }

    foreach ($file_array as $file) {
        // Skip if there's an upload error or if no file was actually selected for this slot
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) {
            continue; 
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = "File '{$file['name']}' exceeds the 2.5MB limit.";
            continue;
        }

        if (!in_array($file['type'], ALLOWED_MIME_TYPES)) {
            $errors[] = "File '{$file['name']}' is not a valid JPG or PNG image.";
            continue;
        }
        
        // Add to the list of files ready for processing
        $uploaded_files_data[] = $file;
    }
}

// --- 3. HANDLE ERRORS OR PROCEED ---
if (!empty($errors)) {
    echo '<h2>Submission Error</h2>';
    echo '<ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul>';
    echo '<br><a href="report.html">Go Back</a>';
    exit;
}

// Ensure the uploads directory is ready
$absolute_upload_dir = __DIR__ . '/' . UPLOAD_DIR; // __DIR__ . '/../' . UPLOAD_DIR; // e.g., /path/to/public/uploads/pictures/
if (!is_dir($absolute_upload_dir)) {
    if (!mkdir($absolute_upload_dir, 0755, true)) {
        $errors[] = "Server error: Cannot create upload directory. Please inform the administrator.";
        // Exit if directory creation fails
        echo '<h2>Submission Error</h2><p>' . $errors[0] . '</p>';
        exit;
    }
}


// --- 4. DATABASE TRANSACTION ---
// Use transactions to ensure incident and pictures are saved atomically.
mysqli_begin_transaction($conn);
$success = false;

try {
    // 4.1. Insert Main Incident Report
    $anonymous_id = generate_anonymous_id();
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO incidents (type, latitude, longitude, transport_mode, description, severity, anonymous_id, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new')"
    );

    mysqli_stmt_bind_param($stmt, "sddssss", 
        $incident_type, 
        $latitude, 
        $longitude, 
        $transport_mode, 
        $description, 
        $severity, 
        $anonymous_id
    );

    if (mysqli_stmt_execute($stmt)) {
        $incident_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // 4.2. Process and Insert Pictures (if any)
        if (!empty($uploaded_files_data)) {
            $stmt_picture = mysqli_prepare($conn, 
                "INSERT INTO incident_pictures (incident_id, file_path) VALUES (?, ?)"
            );

            foreach ($uploaded_files_data as $file) {
                // Generate unique filename and define paths
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $unique_name = $incident_id . '_' . uniqid() . '.' . $file_extension;
                
                // Destination is the full path on the server
                $destination = $absolute_upload_dir . $unique_name;
                // Relative path is stored in the DB for web access
                $relative_path = UPLOAD_DIR . $unique_name; 

                // Move file from temp location
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Insert file metadata into incident_pictures table
                    mysqli_stmt_bind_param($stmt_picture, "is", $incident_id, $relative_path);
                    mysqli_stmt_execute($stmt_picture);
                } else {
                    // If file move fails, trigger rollback
                    throw new Exception("Failed to move uploaded file: " . $file['name']);
                }
            }
            mysqli_stmt_close($stmt_picture);
        }

        // 4.3. If severity is Critical, create an alert automatically
        if ($severity === 'Critical') {
            $alert_message = "Critical " . strtolower($incident_type) . " incident reported on " . $transport_mode . ". Stay vigilant in this area.";
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $alert_stmt = mysqli_prepare($conn, 
                "INSERT INTO alerts (incident_id, severity, message, expires_at) VALUES (?, ?, ?, ?)"
            );
            
            if ($alert_stmt) {
                mysqli_stmt_bind_param($alert_stmt, "isss", $incident_id, $severity, $alert_message, $expires_at);
                mysqli_stmt_execute($alert_stmt);
                mysqli_stmt_close($alert_stmt);
            }
        }
        
        // 4.4. Commit the transaction
        mysqli_commit($conn);
        $success = true;

    } else {
        throw new Exception("Database error on incident insert: " . mysqli_stmt_error($stmt));
    }

} catch (Exception $e) {
    // Rollback if any error occurred during the transaction
    mysqli_rollback($conn);
    $errors[] = 'A system error occurred during submission. Incident not recorded. Error: ' . $e->getMessage();
    
    echo '<h2>Submission Failed</h2>';
    echo '<ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul>';
    echo '<br><a href="report.html">Go Back</a>';
    close_connection();
    exit;
}

// --- 5. FINAL REDIRECT ---
if ($success) {
    redirect("success.php?id=" . $incident_id);
}

// Close database connection
close_connection();


/**
 * Helper function to reorganize the $_FILES array structure for easier iteration.
 * @param array $file_post Array of files from $_FILES
 * @return array The re-organized array
 */
function rearray_files($file_post) {
    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);

    for ($i=0; $i<$file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }
    return $file_ary;
}
?>