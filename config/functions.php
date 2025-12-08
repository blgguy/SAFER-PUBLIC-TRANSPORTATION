<?php
/**
 * Helper Functions
 * File: config/functions.php
 */

/**
 * Sanitize user input to prevent XSS attacks
 * @param string $data - Raw input data
 * @return string - Cleaned data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate random anonymous ID for incident reports
 * @return string - 32 character unique ID
 */
function generate_anonymous_id() {
    return md5(uniqid(rand(), true) . time());
}

/**
 * Fetches picture file paths for a given incident ID.
 * Assumes the existence of the 'incident_pictures' table.
 * * @param mysqli $conn Database connection object.
 * @param int $incident_id The ID of the incident.
 * @return array An array of picture file paths (relative paths).
 */
function get_incident_pictures($conn, $incident_id) {
    $pictures = [];
    // Use prepared statements for safety
    $stmt = mysqli_prepare($conn, "SELECT file_path FROM incident_pictures WHERE incident_id = ?");
    
    if (!$stmt) {
        // Log the error if the statement fails to prepare
        error_log("Failed to prepare picture query: " . mysqli_error($conn));
        return $pictures;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $incident_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Sanitize the output path for HTML display
        $pictures[] = htmlspecialchars($row['file_path']);
    }
    
    mysqli_stmt_close($stmt);
    return $pictures;
}

/**
 * Calculate distance between two coordinates (Haversine formula)
 * @param float $lat1 - Latitude of point 1
 * @param float $lng1 - Longitude of point 1
 * @param float $lat2 - Latitude of point 2
 * @param float $lng2 - Longitude of point 2
 * @return float - Distance in kilometers
 */
function calculate_distance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // Earth's radius in kilometers
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

/**
 * Check if admin is logged in
 * @return bool - True if logged in, false otherwise
 */
function is_admin_logged_in() {
    session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Redirect to another page
 * @param string $url - URL to redirect to
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Calculate time ago from timestamp
 * @param string $timestamp - MySQL timestamp
 * @return string - Human readable time ago
 */
// function time_ago($timestamp) {
//     $time = strtotime($timestamp);
//     $diff = time() - $time;
    
//     if ($diff < 60) return $diff . " seconds ago";
//     if ($diff < 3600) return floor($diff / 60) . " minutes ago";
//     if ($diff < 86400) return floor($diff / 3600) . " hours ago";
//     if ($diff < 604800) return floor($diff / 86400) . " days ago";
//     return date('M d, Y', $time);
// }

// function time_ago($timestamp) {
//     // 1. Get the UNIX timestamp of the past event
//     $time = strtotime($timestamp);
//     // 2. Calculate the difference in seconds from the current time
//     $diff = time() - $time;
    
//     // Define time intervals in seconds
//     $second = 1;
//     $minute = 60 * $second;
//     $hour   = 60 * $minute;
//     $day    = 24 * $hour;
//     $week   = 7 * $day;
//     $month  = 30 * $day;
//     $year   = 365 * $day;
    
//     // Check against the intervals, from smallest to largest
//     if ($diff < $minute) {
//         $count = floor($diff / $second);
//         return $count . " second" . ($count != 1 ? "s" : "") . " ago";
//     }
    
//     // If it reaches here, $diff is 1 minute (60 seconds) or more
//     if ($diff < $hour) {
//         $count = floor($diff / $minute);
//         return $count . " minute" . ($count != 1 ? "s" : "") . " ago";
//     }
    
//     // If it reaches here, $diff is 1 hour (3600 seconds) or more
//     if ($diff < $day) {
//         $count = floor($diff / $hour);
//         return $count . " hour" . ($count != 1 ? "s" : "") . " ago";
//     }
    
//     // If it reaches here, $diff is 1 day (86400 seconds) or more
//     if ($diff < $week) {
//         $count = floor($diff / $day);
//         return $count . " day" . ($count != 1 ? "s" : "") . " ago";
//     }
    
//     // For anything longer, use the date format
//     return date('M d, Y H:i a', $time);
// }

function time_ago($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;

    // Handle future dates or exact match
    if ($diff < 1) {
        return "just now";
    }

    // Array of time units and their seconds equivalent
    $units = [
        31536000 => 'year', // 365 days
        2592000  => 'month', // 30 days
        604800   => 'week', // 7 days
        86400    => 'day', // 24 hours
        3600     => 'hour', // 60 minutes
        60       => 'minute', // 60 seconds
        1        => 'second' // 1 second
    ];

    // Iterate through units from largest to smallest
    foreach ($units as $seconds => $unit) {
        if ($diff >= $seconds) {
            $count = floor($diff / $seconds);
            
            // For longer periods (month, year), just return the date
            if ($seconds >= 2592000) { 
                 return date('M d, Y', $time);
            }
            
            // Return the calculated unit (e.g., 5 minutes, 2 hours)
            return $count . ' ' . $unit . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    
    // Fallback (shouldn't happen if $diff > 0)
    return date('M d, Y H:i a', $time);
}

/**
 * Get severity badge HTML
 * @param string $severity - Severity level
 * @return string - HTML badge
 */
function get_severity_badge($severity) {
    $colors = [
        'Low' => '#28a745',
        'Medium' => '#ffc107',
        'High' => '#fd7e14',
        'Critical' => '#dc3545'
    ];
    $color = $colors[$severity] ?? '#6c757d';
    return "<span style='background: $color; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold;'>$severity</span>";
}
?>