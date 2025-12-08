// File: public/assets/js/map.js
// Description: Initializes the Leaflet map and includes basic controls.

/**
 * Initializes a Leaflet map in the specified HTML element.
 * @param {string} elementId The ID of the div element to contain the map.
 * @param {Array<number>} centerCoords [latitude, longitude] for the initial center.
 * @param {number} zoomLevel Initial zoom level (e.g., 13).
 * @param {boolean} addLocationControl If true, adds a geolocation control.
 * @returns {L.Map} The initialized Leaflet Map instance.
 */
export function initializeMap(elementId, centerCoords, zoomLevel = 13, addLocationControl = false) {
    if (typeof L === 'undefined') {
        console.error('Leaflet.js is not loaded. Ensure CDN links are present in the HTML.');
        return null;
    }

    // Attempt to load map in the given element
    const map = L.map(elementId).setView(centerCoords, zoomLevel);

    // Set up the Tile Layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    // Add Geolocation Control
    if (addLocationControl) {
        L.control.locate({
            strings: {
                title: "Show me where I am"
            },
            locateOptions: {
                maxZoom: 16,
                enableHighAccuracy: true
            }
        }).addTo(map);
    }

    console.log(`Map initialized for element: ${elementId}`);
    return map;
}

// Example usage placeholder (to be executed via main.js or dashboard.js)
/*
document.addEventListener('DOMContentLoaded', () => {
    // Check if the element for the main map exists
    const mainMapElement = document.getElementById('alerts-map');
    if (mainMapElement) {
        // Default center (e.g., a major city)
        const defaultCenter = [40.730610, -73.935242]; // New York City
        const map = initializeMap('alerts-map', defaultCenter, 13, true);
        window.alertsMap = map; // Store map instance globally or in a scope
    }
});
*/