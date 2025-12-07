<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Incident - SAFER PUBLIC TRANSPORTATION IN INDIA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f8f9fa; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        header { background: #0066cc; color: white; padding: 1rem; text-align: center; }
        .anonymous-badge { background: #28a745; color: white; padding: 12px 24px; border-radius: 8px; text-align: center; margin: 20px 0; font-weight: bold; }
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        input, select, textarea { width: 100%; padding: 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 6px; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #0066cc; }
        textarea { resize: vertical; min-height: 120px; font-family: Arial, sans-serif; }
        .radio-group { display: flex; gap: 15px; flex-wrap: wrap; }
        .radio-option { display: flex; align-items: center; gap: 5px; }
        .radio-option input { width: auto; }
        .btn { padding: 14px 28px; font-size: 16px; font-weight: bold; border: none; border-radius: 6px; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: #0066cc; color: white; width: 100%; }
        .btn-primary:hover { background: #0052a3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .location-status { padding: 12px; background: #e7f3ff; border-left: 4px solid #0066cc; margin: 10px 0; border-radius: 4px; display: none; }
        .error { color: #dc3545; font-size: 14px; margin-top: 5px; }
        .char-count { font-size: 14px; color: #666; text-align: right; }
        .required { color: #dc3545; }
        @media (max-width: 600px) { .container { padding: 10px; } .form-container { padding: 20px; } }
    </style>
</head>
<body>
    <header>
        <h1>🛡️ Report Safety Incident</h1>
    </header>

    <div class="container">
        <div class="anonymous-badge">
            🔒 Completely Anonymous - No Personal Data Collected
        </div>

        <div class="form-container">
            <form id="reportForm" method="POST" action="submit_report.php">
                
                <div class="form-group">
                    <label for="incident_type">Incident Type <span class="required">*</span></label>
                    <select id="incident_type" name="incident_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="Harassment">Harassment</option>
                        <option value="Theft">Theft</option>
                        <option value="Violence">Violence</option>
                        <option value="Suspicious Activity">Suspicious Activity</option>
                    </select>
                    <div class="error" id="error_type"></div>
                </div>

                <div class="form-group">
                    <label for="transport_mode">Transport Mode <span class="required">*</span></label>
                    <select id="transport_mode" name="transport_mode" required>
                        <option value="">-- Select Mode --</option>
                        <option value="Bus">Bus</option>
                        <option value="Metro">Metro</option>
                        <option value="Train">Train</option>
                        <option value="Auto">Auto-Rickshaw</option>
                        <option value="Shared">Shared Taxi</option>
                    </select>
                    <div class="error" id="error_transport"></div>
                </div>

                <div class="form-group">
                    <label>Location <span class="required">*</span></label>
                    <button type="button" class="btn btn-secondary" onclick="getLocation()">
                        📍 Get My Current Location
                    </button>
                    <div class="location-status" id="locationStatus"></div>
                    <input type="hidden" id="latitude" name="latitude" required>
                    <input type="hidden" id="longitude" name="longitude" required>
                    <div class="error" id="error_location"></div>
                </div>

                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <textarea id="description" name="description" placeholder="Describe what happened... (Min 10 characters, Max 500)" maxlength="500" required></textarea>
                    <div class="char-count"><span id="charCount">0</span> / 500 characters</div>
                    <div class="error" id="error_description"></div>
                </div>

                <div class="form-group">
                    <label>Severity Level <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="low" name="severity" value="Low" required>
                            <label for="low" style="font-weight: normal; margin: 0;">Low</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="medium" name="severity" value="Medium">
                            <label for="medium" style="font-weight: normal; margin: 0;">Medium</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="high" name="severity" value="High">
                            <label for="high" style="font-weight: normal; margin: 0;">High</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="critical" name="severity" value="Critical">
                            <label for="critical" style="font-weight: normal; margin: 0;">Critical</label>
                        </div>
                    </div>
                    <div class="error" id="error_severity"></div>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">Submit Report</button>
            </form>
        </div>
    </div>

    <script>
        // Character counter
        const description = document.getElementById('description');
        const charCount = document.getElementById('charCount');
        description.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });

        // Get location function
        function getLocation() {
            const statusDiv = document.getElementById('locationStatus');
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = '📡 Detecting your location...';

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError);
            } else {
                statusDiv.innerHTML = '❌ Geolocation is not supported by your browser.';
            }
        }

        function showPosition(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('locationStatus').innerHTML = 
                `✅ Location detected: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            document.getElementById('error_location').textContent = '';
        }

        function showError(error) {
            const statusDiv = document.getElementById('locationStatus');
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    statusDiv.innerHTML = '❌ Please allow location access to report incidents.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    statusDiv.innerHTML = '❌ Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    statusDiv.innerHTML = '❌ Location request timed out. Please try again.';
                    break;
                default:
                    statusDiv.innerHTML = '❌ An error occurred while detecting location.';
            }
        }

        // Form validation
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            let valid = true;

            // Clear previous errors
            document.querySelectorAll('.error').forEach(el => el.textContent = '');

            // Validate incident type
            if (!document.getElementById('incident_type').value) {
                document.getElementById('error_type').textContent = 'Please select incident type';
                valid = false;
            }

            // Validate transport mode
            if (!document.getElementById('transport_mode').value) {
                document.getElementById('error_transport').textContent = 'Please select transport mode';
                valid = false;
            }

            // Validate location
            if (!document.getElementById('latitude').value) {
                document.getElementById('error_location').textContent = 'Please detect your location first';
                valid = false;
            }

            // Validate description
            const desc = document.getElementById('description').value;
            if (desc.length < 10) {
                document.getElementById('error_description').textContent = 'Description must be at least 10 characters';
                valid = false;
            }

            // Validate severity
            if (!document.querySelector('input[name="severity"]:checked')) {
                document.getElementById('error_severity').textContent = 'Please select severity level';
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>