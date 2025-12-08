// File: public/assets/js/alerts.js
// Description: Real-time safety alerts system using API polling and Web Notifications.

const ALERTS_API = '/api/get-public-alerts.php';
const POLLING_INTERVAL = 30000; // 30 seconds
const HISTORY_KEY = 'st_alert_history';
const PREFS_KEY = 'st_alert_prefs';

let lastAlertCheck = 0;
let userLocation = { lat: null, lng: null };
let preferences = {
    sound: true,
    desktop_notifs: 'default' // 'default', 'granted', 'denied'
};

// --- Helper Functions ---

/** Loads user preferences and notification permission state. */
function loadPreferences() {
    const storedPrefs = localStorage.getItem(PREFS_KEY);
    if (storedPrefs) {
        preferences = { ...preferences, ...JSON.parse(storedPrefs) };
    }
    // Check actual permission state
    if ('Notification' in window) {
        preferences.desktop_notifs = Notification.permission;
    }
}

/** Saves user preferences. */
function savePreferences() {
    localStorage.setItem(PREFS_KEY, JSON.stringify(preferences));
}

/** Gets the last 24 hours of alert IDs from history. */
function getAlertHistory() {
    const history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
    const twentyFourHoursAgo = Date.now() - 24 * 60 * 60 * 1000;
    // Filter out old alerts and return only unique IDs
    return history.filter(item => item.timestamp > twentyFourHoursAgo);
}

/** Marks a new alert as seen and adds it to history. */
function markAlertAsRead(alertId) {
    let history = getAlertHistory();
    // Remove if already exists (in case of re-sent alert)
    history = history.filter(item => item.id !== alertId);
    history.push({ id: alertId, timestamp: Date.now() });
    
    // Limit history size to prevent overflow
    if (history.length > 100) {
        history.sort((a, b) => b.timestamp - a.timestamp); // Sort newest first
        history = history.slice(0, 100);
    }
    
    localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
}

/** Requests permission for desktop notifications if needed. */
function requestDesktopNotificationPermission() {
    if ('Notification' in window && preferences.desktop_notifs === 'default') {
        Notification.requestPermission().then(permission => {
            preferences.desktop_notifs = permission;
            savePreferences();
            console.log(`Desktop Notification Permission: ${permission}`);
        });
    }
}

/** Triggers a sound notification. */
function playSound() {
    if (preferences.sound) {
        try {
            // Placeholder: Use a brief, non-intrusive sound file
            const audio = new Audio('/assets/audio/alert.mp3'); 
            audio.play().catch(e => console.warn('Audio playback blocked:', e));
        } catch (e) {
            // Fail silently if browser doesn't support Audio or file is missing
        }
    }
}

// --- Display Logic ---

/** Displays an alert as a temporary toast notification. */
function showToast(alert) {
    const toast = document.createElement('div');
    const severityClass = `alert-${alert.severity.toLowerCase()}`;
    const icon = alert.severity === 'Critical' ? '🚨' : (alert.severity === 'Warning' ? '⚠️' : '🔔');
    
    toast.className = `alert-toast ${severityClass}`;
    toast.innerHTML = `
        <span class="icon">${icon}</span>
        <div class="message-content">
            <strong>${alert.severity} Alert:</strong> ${alert.message}
            <div class="distance-info">${alert.distance_km ? `— ${alert.distance_km} km away` : ''}</div>
        </div>
        <button class="close-btn" onclick="this.parentElement.remove()">×</button>
    `;
    
    // Styling for the toast (should be in components.css, but added here for immediate demo)
    toast.style.cssText = `
        position: fixed; top: 10px; right: 10px; z-index: 1000;
        background-color: white; padding: 10px 15px; border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); transition: all 0.5s;
        min-width: 300px; display: flex; align-items: center; gap: 10px;
        border-left: 5px solid ${alert.severity === 'Critical' ? '#dc2626' : (alert.severity === 'Warning' ? '#ea580c' : '#2563eb')};
    `;

    document.body.appendChild(toast);
    
    if (alert.severity !== 'Critical') {
        // Auto-remove non-critical alerts after 8 seconds
        setTimeout(() => toast.remove(), 8000);
    }
}

/** Displays an alert using the Web Notifications API. */
function showDesktopNotification(alert) {
    if (preferences.desktop_notifs === 'granted') {
        new Notification(`Safe Transport Alert: ${alert.severity}`, {
            body: alert.message,
            icon: '/assets/icons/icon-192x192.png',
            tag: alert.alert_id // Prevents duplicate critical notifications
        });
    }
}


// --- Core Logic ---

/** Determines current user location via Geolocation API. */
function getUserLocation() {
    return new Promise((resolve) => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLocation.lat = position.coords.latitude.toFixed(6);
                    userLocation.lng = position.coords.longitude.toFixed(6);
                    resolve(userLocation);
                },
                (error) => {
                    console.log('Geolocation unavailable or denied.', error.message);
                    userLocation = { lat: null, lng: null }; // Reset on error
                    resolve(userLocation);
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 60000 }
            );
        } else {
            userLocation = { lat: null, lng: null };
            resolve(userLocation);
        }
    });
}

/** Main polling function to fetch and display new alerts. */
async function checkNewAlerts() {
    await getUserLocation();
    
    const token = getAuthToken(); // Assuming token is null for public page

    // 1. Build API URL with location parameters
    let url = ALERTS_API + '?radius_km=5';
    if (userLocation.lat && userLocation.lng) {
        url += `&lat=${userLocation.lat}&lng=${userLocation.lng}`;
    }

    try {
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.alerts) {
            const history = getAlertHistory().map(item => item.id);
            let newAlertsFound = false;

            result.alerts.forEach(alert => {
                // 2. Filter for truly new alerts
                if (!history.includes(alert.alert_id)) {
                    // This is a new alert!
                    newAlertsFound = true;
                    
                    // 3. Display and log
                    showToast(alert);
                    showDesktopNotification(alert);
                    playSound();
                    markAlertAsRead(alert.alert_id);
                }
            });
            
            if (newAlertsFound) {
                console.log(`Found ${result.alerts.length - history.length} new safety alerts.`);
            } else {
                console.log('No new safety alerts in the area.');
            }
        }
    } catch (error) {
        console.error('Failed to fetch public alerts:', error);
    }

    // 4. Update last check time
    lastAlertCheck = Date.now();
}

/** Starts the alert system and the polling mechanism. */
export function startAlertSystem() {
    loadPreferences();
    requestDesktopNotificationPermission();

    // Initial check
    checkNewAlerts();

    // Set up polling interval
    setInterval(checkNewAlerts, POLLING_INTERVAL);
}

// Global initialization of the alert system
document.addEventListener('DOMContentLoaded', () => {
    // Only start the alert system if we are on a public-facing page
    if (document.body.id !== 'admin-dashboard') {
        startAlertSystem();
    }
});