// File: public/assets/js/dashboard.js
// Description: Logic for the Authority Dashboard including authentication, map rendering, and real-time feed.

import { initializeMap } from './map.js';
import { initializeAnalytics } from './analytics.js';

const API_FEED_ENDPOINT = '/api/admin/incidents-feed.php';
const API_VERIFY_ENDPOINT = '/api/admin/verify-incident.php';

let dashboardMap = null;
let incidentMarkers = L.layerGroup();
const REFRESH_INTERVAL = 30000; // 30 seconds

/**
 * Gets the auth token from local storage.
 */
function getAuthToken() {
    return localStorage.getItem('admin_token'); 
}

/**
 * Simulates a successful login and saves a dummy token.
 * NOTE: This is for development; actual login is in main.js/login.php.
 */
function simulateLogin() {
    // Check if we are logged in by checking the token presence
    if (!getAuthToken()) {
        console.log("No token found. Simulating successful login...");
        // This token is a placeholder, usually returned by the API from Layer 7.1
        localStorage.setItem('admin_token', 'SIMULATED_JWT_ABC123XYZ456');
        localStorage.setItem('user_role', 'Admin');
    }
    document.getElementById('login-overlay').style.display = 'none';
    document.querySelector('.dashboard-container').style.display = 'grid'; // Show dashboard content
    
    // Start the dashboard logic
    startDashboard();
}

/**
 * Handles user logout.
 */
function handleLogout() {
    localStorage.removeItem('admin_token');
    localStorage.removeItem('user_role');
    window.location.href = '../index.html'; // Redirect to main page
}


/* -------------------------------------------------------------------------- */
/* INCIDENT & MAP LOGIC */
/* -------------------------------------------------------------------------- */

/**
 * Fetches the latest incidents from the API.
 * @returns {Array} List of incident objects.
 */
async function fetchIncidentsFeed() {
    const token = getAuthToken();
    if (!token) return [];

    try {
        const response = await fetch(API_FEED_ENDPOINT, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (response.status === 401) {
            handleLogout(); // Force logout on unauthorized
            return [];
        }

        if (!response.ok) {
            throw new Error(`Failed to fetch feed: ${response.statusText}`);
        }

        const result = await response.json();
        return result.data || [];

    } catch (error) {
        console.error('Error fetching incidents feed:', error);
        return [];
    }
}

/**
 * Maps severity to a marker color (Leaflet requires specific color names).
 */
function getMarkerColor(severity) {
    switch (severity) {
        case 'Critical':
            return 'red';
        case 'High':
            return 'orange';
        case 'Medium':
            return 'yellow';
        default:
            return 'blue';
    }
}

/**
 * Renders incident markers on the map and updates the incident list.
 * @param {Array} incidents - List of incident objects.
 */
function renderIncidents(incidents) {
    if (!dashboardMap) return;

    // 1. Clear existing markers
    incidentMarkers.clearLayers();

    const incidentListEl = document.getElementById('incident-list');
    incidentListEl.innerHTML = ''; // Clear feed list

    incidents.forEach(incident => {
        // --- Map Marker ---
        if (incident.latitude && incident.longitude) {
            const markerColor = getMarkerColor(incident.severity);
            
            // Define custom icon for color coding (using Leaflet's built-in DivIcon for color)
            const icon = L.divIcon({
                className: `custom-div-icon status-${incident.status}`,
                html: `<div style="background-color: ${markerColor}; width: 15px; height: 15px; border-radius: 50%; border: 2px solid white;"></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            const marker = L.marker([incident.latitude, incident.longitude], { icon: icon }).addTo(incidentMarkers);

            // Create Popup Content
            const popupContent = `
                <div>
                    <h4>Report #${incident.report_id} - ${incident.severity}</h4>
                    <p><strong>Type:</strong> ${incident.incident_type_name}</p>
                    <p><strong>Time:</strong> ${new Date(incident.timestamp).toLocaleString()}</p>
                    <p><strong>Status:</strong> <span class="status-${incident.status}">${incident.status}</span></p>
                    ${incident.status === 'Pending' ? 
                        `<button class="btn btn-sm btn-primary mt-sm quick-verify-btn" data-id="${incident.report_id}">Verify & Alert</button>` :
                        `<button class="btn btn-sm btn-success mt-sm quick-resolve-btn" data-id="${incident.report_id}">Resolve</button>`
                    }
                </div>
            `;
            marker.bindPopup(popupContent);
        }
        
        // --- Incident Feed List ---
        const listItem = document.createElement('li');
        listItem.innerHTML = `
            <div style="flex-grow: 1;">
                <span class="status-${incident.status}">${incident.status}</span> - 
                ${incident.incident_type_name} (${incident.transportation_mode}) 
                - ${new Date(incident.timestamp).toLocaleTimeString()}
            </div>
            ${incident.status === 'Pending' ? 
                `<button class="btn btn-sm btn-success quick-action-btn" data-action="Verify" data-id="${incident.report_id}">Verify</button>` :
                `<button class="btn btn-sm btn-secondary quick-action-btn" data-action="Resolve" data-id="${incident.report_id}">Resolve</button>`
            }
        `;
        incidentListEl.appendChild(listItem);
    });

    // Add all markers to the map
    dashboardMap.addLayer(incidentMarkers);
    
    // Add event listeners for quick action buttons in the list
    incidentListEl.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const reportId = btn.dataset.id;
            const action = btn.dataset.action;
            // Simple confirmation before API call
            if (confirm(`Are you sure you want to ${action} report #${reportId}?`)) {
                handleVerificationAction(reportId, action, `Quick action via dashboard list (${action}).`);
            }
        });
    });
}

/**
 * Handles the verification/rejection/resolution action via API.
 */
async function handleVerificationAction(reportId, action, notes) {
    const token = getAuthToken();
    if (!token) return handleLogout();

    try {
        const response = await fetch(API_VERIFY_ENDPOINT, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                report_id: reportId,
                action: action,
                admin_notes: notes
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            alert(`Report ${reportId} successfully updated to ${result.new_status}.`);
            refreshDashboardData(); // Re-fetch data to update map and feed
        } else {
            alert(`Action Failed: ${result.message || 'Unknown error'}`);
        }
    } catch (error) {
        console.error('Verification API error:', error);
        alert('A network error occurred. Please check server logs.');
    }
}

/**
 * Main dashboard data refresh function.
 */
async function refreshDashboardData() {
    document.getElementById('feed-refresh-status').textContent = '(Refreshing...)';

    // 1. Fetch and Render Incidents
    const incidents = await fetchIncidentsFeed();
    renderIncidents(incidents);

    // 2. Refresh Analytics (Layer 8.1)
    await initializeAnalytics();
    
    document.getElementById('feed-refresh-status').textContent = '(Auto-refreshing in 30s)';
}

/* -------------------------------------------------------------------------- */
/* INITIALIZATION */
/* -------------------------------------------------------------------------- */

/**
 * Starts the core dashboard services.
 */
function startDashboard() {
    // 1. Initialize Map
    const defaultCenter = [40.730610, -73.935242]; // Default to NYC
    const mapElement = document.getElementById('incident-map');
    if (mapElement) {
        mapElement.innerHTML = ''; // Clear placeholder
        dashboardMap = initializeMap('incident-map', defaultCenter, 12, false);
    }
    
    // 2. Initial Data Load
    refreshDashboardData();
    
    // 3. Set Auto-Refresh Interval
    setInterval(refreshDashboardData, REFRESH_INTERVAL);
    
    // 4. Attach Logout Handler
    document.getElementById('logout-btn').addEventListener('click', handleLogout);

    // 5. Global listener for quick verify buttons in map popups
    if (dashboardMap) {
        dashboardMap.on('popupopen', (e) => {
            const popup = e.popup.getElement();
            // Handle Verify button in map popup
            popup.querySelector('.quick-verify-btn')?.addEventListener('click', (btnE) => {
                const reportId = btnE.target.dataset.id;
                if (confirm(`VERIFY & ALERT: Are you sure you want to verify report #${reportId} and send an alert?`)) {
                    handleVerificationAction(reportId, 'Verify', 'Verified via Map Quick Action.');
                }
            });
            // Handle Resolve button in map popup
            popup.querySelector('.quick-resolve-btn')?.addEventListener('click', (btnE) => {
                const reportId = btnE.target.dataset.id;
                if (confirm(`RESOLVE: Are you sure you want to resolve report #${reportId}? This will expire any active alerts.`)) {
                    handleVerificationAction(reportId, 'Resolve', 'Resolved via Map Quick Action.');
                }
            });
        });
    }
}


// Check authentication status on page load
document.addEventListener('DOMContentLoaded', () => {
    // Check if on dashboard page
    if (!document.getElementById('incident-map')) return; 

    // NOTE: For the project flow, we assume a temporary successful login if running locally.
    // The actual authentication should be handled by main.js after login.
    const token = getAuthToken();
    if (token) {
        simulateLogin(); // Proceed directly to dashboard
    } else {
        // Show login form (Layer 4.3 HTML structure includes this overlay)
        document.getElementById('login-overlay').style.display = 'flex';
        // Add event listener to the login form
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            // Placeholder: Replace with actual Layer 7.1 login fetch call
            console.log("Simulating login API call...");
            setTimeout(() => { // Simulate API delay
                simulateLogin(); // On success, simulateLogin() is called
            }, 500);
        });
    }
});