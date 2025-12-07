<?php
// Get incident ID from URL
$incident_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Submitted Successfully - SAFER PUBLIC TRANSPORTATION IN INDIA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .success-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #28a745;
            margin-bottom: 20px;
            font-size: 2rem;
        }
        .tracking-box {
            background: #e7f3ff;
            border: 2px solid #0066cc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .tracking-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        .tracking-id {
            font-size: 24px;
            font-weight: bold;
            color: #0066cc;
            font-family: monospace;
        }
        .anonymous-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            border-radius: 4px;
        }
        .anonymous-note strong {
            color: #856404;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-primary {
            background: #0066cc;
            color: white;
        }
        .btn-primary:hover {
            background: #0052a3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .buttons {
            margin-top: 30px;
        }
        @media (max-width: 600px) {
            .success-container {
                padding: 30px 20px;
            }
            h1 {
                font-size: 1.5rem;
            }
            .tracking-id {
                font-size: 18px;
            }
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✅</div>
        <h1>Thank You!</h1>
        <p style="font-size: 18px; color: #333;">Your report has been submitted successfully</p>
        
        <?php if ($incident_id > 0): ?>
        <div class="tracking-box">
            <div class="tracking-label">Your Tracking ID</div>
            <div class="tracking-id">#<?php echo str_pad($incident_id, 6, '0', STR_PAD_LEFT); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="anonymous-note">
            <strong>🔒 Your Privacy is Protected</strong><br>
            Your report is completely anonymous. No personal information has been collected or stored. 
            This helps protect your identity while making public transport safer for everyone.
        </div>
        
        <p style="color: #666; margin: 20px 0;">
            The Authority, will review your report. If it's a critical incident, 
            an alert will be created to warn other travelers in your area.
        </p>
        
        <div class="buttons">
            <a href="report.php" class="btn btn-primary">Report Another Incident</a>
            <a href="alerts.php" class="btn btn-secondary">View Safety Alerts</a>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="index.php" style="color: #0066cc; text-decoration: none;">← Back to Home</a>
        </div>
    </div>
</body>
</html>