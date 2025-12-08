/**
 * Geolocation JavaScript
 * Handles HTML5 Geolocation API for incident reporting
 * File: js/geolocation.js
 */

// Main function to get user's current location
function getLocation() {
    const statusDiv = document.getElementById('locationStatus');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    
    // Show loading message
    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusDiv.innerHTML = '📡 Detecting your location...';
        statusDiv.style.background = '#e7f3ff';
        statusDiv.style.color = '#004085';
    }
    
    // Check if geolocation is supported
    if (!navigator.geolocation) {
        showError({code: 0, message: 'Geolocation not supported'});
        return;
    }
    
    // Get current position with high accuracy
    navigator.geolocation.getCurrentPosition(
        showPosition, 
        showError,
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Success callback - display the position
function showPosition(position) {
    const lat = position.coords.latitude;
    const lng = position.coords.longitude;
    const accuracy = position.coords.accuracy;
    
    // Set hidden form fields
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    
    if (latInput && lngInput) {
        latInput.value = lat;
        lngInput.value = lng;
    }
    
    // Display success message
    const statusDiv = document.getElementById('locationStatus');
    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusDiv.style.background = '#d4edda';
        statusDiv.style.color = '#155724';
        statusDiv.innerHTML = `
            ✅ <strong>Location detected successfully!</strong><br>
            <small>Latitude: ${lat.toFixed(6)}, Longitude: ${lng.toFixed(6)}</small><br>
            <small>Accuracy: ±${Math.round(accuracy)} meters</small>
        `;
    }
    
    // Clear any previous error messages
    const errorDiv = document.getElementById('error_location');
    if (errorDiv) {
        errorDiv.textContent = '';
    }
}

// Error callback - handle geolocation errors
function showError(error) {
    const statusDiv = document.getElementById('locationStatus');
    let errorMessage = '';
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            errorMessage = '❌ Location access denied. Please enable location permissions in your browser settings.';
            break;
        case error.POSITION_UNAVAILABLE:
            errorMessage = '❌ Location information unavailable. Please check your device settings.';
            break;
        case error.TIMEOUT:
            errorMessage = '❌ Location request timed out. Please try again.';
            break;
        case 0:
            errorMessage = '❌ Geolocation is not supported by your browser.';
            break;
        default:
            errorMessage = '❌ An unknown error occurred while detecting location.';
    }
    
    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusDiv.style.background = '#f8d7da';
        statusDiv.style.color = '#721c24';
        statusDiv.innerHTML = errorMessage;
    }
}

// Optional: Watch position continuously (for real-time tracking)
let watchId = null;

function startWatchingPosition() {
    if (navigator.geolocation) {
        watchId = navigator.geolocation.watchPosition(
            showPosition,
            showError,
            {
                enableHighAccuracy: true,
                maximumAge: 30000,
                timeout: 27000
            }
        );
    }
}

function stopWatchingPosition() {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
}