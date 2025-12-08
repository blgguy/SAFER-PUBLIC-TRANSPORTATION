// File: public/assets/js/main.js
// Description: Core application logic, PWA registration, Alerts integration, and Incident Report form control.

import { initializeMap } from './map.js';
import { startAlertSystem, getAlertHistory } from './alerts.js'; // Import the new alert module

// Constants
const FORM_STEPS = 4;
const DEFAULT_CENTER = [40.730610, -73.935242]; // New York City

// --- Global State ---
let currentStep = 1;
let indexMap = null; // Map instance for the landing page/alerts

// --- PWA & Initialization ---

/** 1. Register Service Worker for PWA capabilities. */
function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('SW registered: ', registration.scope);
                })
                .catch(registrationError => {
                    console.error('SW registration failed: ', registrationError);
                });
        });
    }
}

/** 2. Initializes the map on the landing page and shows active alerts. */
function initializeLandingPageMap() {
    const mapElement = document.getElementById('alerts-map');
    if (!mapElement) return;

    indexMap = initializeMap('alerts-map', DEFAULT_CENTER, 12, true);

    // Placeholder: In a real system, alerts.js would expose a function
    // to retrieve the *current* active alerts with their coordinates 
    // from the public API, which we would then plot here.
    // For now, we plot a single sample point.
    
    if (indexMap) {
        L.marker([40.7580, -73.9855]).addTo(indexMap) // Times Square
            .bindPopup("Major Safety Alert: High Congestion Area.")
            .openPopup();
            
        console.log("Landing page map initialized with dummy alert marker.");
    }
}

// --- Report Form Logic (Retained and refactored from Layer 6.2) ---

const form = document.getElementById('incident-report-form');
if (form) {
    // Selectors and initial setup for the form (re-scoped globally)
    const navButtons = document.getElementById('form-navigation');
    const prevBtn = document.getElementById('prev-step-btn');
    const nextBtn = document.getElementById('next-step-btn');
    const submitBtn = document.getElementById('submit-report-btn');
    const stepsContent = document.querySelectorAll('.form-step-content');
    const progressSteps = document.querySelectorAll('.progress-steps .step');
    const confirmationScreen = document.getElementById('confirmation-screen');
    const formContainer = document.getElementById('report-form-container');
    const csrfTokenField = document.getElementById('csrf-token');
    const formErrorDiv = document.getElementById('form-submission-error');
    
    // Check if on report page and set up form logic
    setupFormEventListeners();
    showStep(1); // Initialize form to Step 1
}


/** Displays the specified step and updates navigation/progress. */
function showStep(step) {
    if (step < 1 || step > FORM_STEPS) return;

    stepsContent.forEach(section => {
        section.style.display = 'none';
    });
    document.getElementById(`step-${step}`).style.display = 'block';
    currentStep = step;

    // Update progress indicator
    progressSteps.forEach((pStep, index) => {
        pStep.classList.remove('active', 'completed');
        if (index + 1 < step) {
            pStep.classList.add('completed');
        } else if (index + 1 === step) {
            pStep.classList.add('active');
        }
    });

    // Update navigation buttons
    prevBtn.disabled = (step === 1);
    nextBtn.style.display = (step === FORM_STEPS) ? 'none' : 'inline-flex';
    submitBtn.style.display = (step === FORM_STEPS) ? 'inline-flex' : 'none';

    if (step === FORM_STEPS) {
        populateReview();
    }
}

/** 5. Resets the form state and returns to the first step. */
function resetForm() {
    form.reset();
    document.querySelectorAll('.selection-item, .severity-item').forEach(el => el.classList.remove('selected'));
    document.getElementById('incident-type-id').value = '';
    document.getElementById('severity-level').value = '';
    
    // Hide confirmation and show form container
    confirmationScreen.style.display = 'none';
    formContainer.style.display = 'block';
    navButtons.style.display = 'flex';
    formErrorDiv.style.display = 'none';
    
    showStep(1);
    console.log("Form reset complete. Ready for new report.");
}


/** Client-side validation for the current step (Retained from L6.2). */
function validateStep(step) {
    // ... (Validation logic from Layer 6.2 remains here) ...
    switch (step) {
        case 1:
            return document.getElementById('incident-type-id').value.length > 0;

        case 2:
            const mode = document.getElementById('transport-mode').value;
            const lat = parseFloat(document.getElementById('latitude').value);
            const lng = parseFloat(document.getElementById('longitude').value);
            
            if (!mode) {
                alert('Please select a transportation mode.');
                return false;
            }
            if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) {
                alert('Please auto-detect or manually enter valid coordinates.');
                return false;
            }
            return true;

        case 3:
            const description = document.getElementById('description').value.trim();
            const timestamp = document.getElementById('timestamp').value;
            const severity = document.getElementById('severity-level').value;

            if (description.length < 10) {
                alert('Description must be at least 10 characters long.');
                return false;
            }
            if (!timestamp) {
                alert('Please select the date and time of the incident.');
                return false;
            }
            if (!severity) {
                alert('Please select the incident severity level.');
                return false;
            }
            return true;
            
        case 4:
            return true;
        
        default:
            return false;
    }
}

/** Populates the final review screen data (Retained from L6.2). */
function populateReview() {
    document.getElementById('review-type').textContent = 
        document.querySelector('.selection-item.selected')?.dataset.name || 'N/A';
    document.getElementById('review-mode').textContent = 
        document.getElementById('transport-mode').value;
    document.getElementById('review-location').textContent = 
        `Lat: ${document.getElementById('latitude').value}, Lng: ${document.getElementById('longitude').value}`;
    document.getElementById('review-severity').textContent = 
        document.getElementById('severity-level').value;
    document.getElementById('review-timestamp').textContent = 
        document.getElementById('timestamp').value.replace('T', ' '); // Format for review
    document.getElementById('review-description').textContent = 
        document.getElementById('description').value.trim();
}

/** Tries to get the user's current geolocation (Retained from L6.2). */
function autoLocate() {
    const statusDiv = document.getElementById('location-status');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    statusDiv.textContent = '🧭 Detecting location...';
    statusDiv.style.color = 'var(--color-primary)';

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude.toFixed(6);
                const lng = position.coords.longitude.toFixed(6);
                
                latInput.value = lat;
                lngInput.value = lng;
                statusDiv.textContent = `✅ Location detected! Lat: ${lat}, Lng: ${lng}`;
                statusDiv.style.color = 'var(--color-success)';
            },
            (error) => {
                statusDiv.textContent = '❌ Failed to get location. Please enter manually.';
                statusDiv.style.color = 'var(--color-danger)';
                console.error('Geolocation Error:', error);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else {
        statusDiv.textContent = '❌ Geolocation is not supported by this browser.';
        statusDiv.style.color = 'var(--color-danger)';
    }
}


/** Sets up all event listeners for the multi-step form. */
function setupFormEventListeners() {
    if (!form) return;

    // Navigation Click Handlers
    nextBtn.addEventListener('click', () => {
        if (validateStep(currentStep)) {
            showStep(currentStep + 1);
        } else {
            alert('Please complete all required fields correctly before continuing.');
        }
    });

    prevBtn.addEventListener('click', () => {
        showStep(currentStep - 1);
    });

    // Geolocation Handler
    document.getElementById('auto-locate-btn')?.addEventListener('click', autoLocate);
    
    // Step 1: Incident Type Selection Handler
    document.getElementById('incident-type-selection')?.addEventListener('click', (e) => {
        const item = e.target.closest('.selection-item');
        if (item) {
            document.querySelectorAll('.selection-item').forEach(el => el.classList.remove('selected'));
            item.classList.add('selected');
            document.getElementById('incident-type-id').value = item.dataset.id;
        }
    });

    // Step 3: Severity Selection Handler
    document.getElementById('severity-options')?.addEventListener('click', (e) => {
        const item = e.target.closest('.severity-item');
        if (item) {
            document.querySelectorAll('.severity-item').forEach(el => el.classList.remove('selected'));
            item.classList.add('selected');
            document.getElementById('severity-level').value = item.dataset.severity;
        }
    });

    // Form Reset Button on confirmation screen
    document.getElementById('start-new-report-btn')?.addEventListener('click', resetForm);

    // Final Form Submission (Logic retained from L6.2)
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!validateStep(FORM_STEPS) || !validateStep(3) || !validateStep(2) || !validateStep(1)) {
            formErrorDiv.textContent = 'Please ensure all previous steps are completed correctly.';
            formErrorDiv.style.display = 'block';
            return;
        }

        const formData = {
            incident_type_id: document.getElementById('incident-type-id').value,
            severity: document.getElementById('severity-level').value,
            transportation_mode: document.getElementById('transport-mode-value').value,
            _csrf_token: csrfTokenField.value,

            latitude: document.getElementById('latitude').value,
            longitude: document.getElementById('longitude').value,
            route_identifier: document.getElementById('route-identifier').value || null,
            description: document.getElementById('description').value,
            timestamp: document.getElementById('timestamp').value.replace('T', ' ') + ':00',
            address_description: null 
        };

        submitBtn.textContent = 'Submitting...';
        submitBtn.disabled = true;
        navButtons.style.opacity = '0.5';
        formErrorDiv.style.display = 'none';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                document.getElementById('form-navigation').style.display = 'none';
                formContainer.style.display = 'none'; // Hide the whole form content
                confirmationScreen.style.display = 'block';
                document.getElementById('tracking-id').textContent = result.report_id;
            } else {
                const errorMessage = result.message || 'Submission failed due to an unknown error.';
                formErrorDiv.textContent = `Error: ${errorMessage}. Details: ${result.error || 'Check console.'}`;
                formErrorDiv.style.display = 'block';
            }

        } catch (error) {
            formErrorDiv.textContent = 'Network or Server Error. Please check your connection.';
            formErrorDiv.style.display = 'block';
        } finally {
            submitBtn.textContent = '🚨 Submit Report';
            submitBtn.disabled = false;
            navButtons.style.opacity = '1';
        }
    });
}

// --- General UI & Global Execution ---

document.addEventListener('DOMContentLoaded', () => {
    // 1. PWA Registration
    registerServiceWorker();

    // 2. Initialize Alerts System
    // We start the alert system for public pages to enable polling and notifications
    if (document.body.id !== 'admin-dashboard') {
        startAlertSystem();
    }
    
    // 3. Landing Page Map Initialization
    initializeLandingPageMap();
    
    // 4. Mobile Navigation Toggle (Simple example for the main header)
    const navToggle = document.getElementById('mobile-nav-toggle');
    const navMenu = document.getElementById('main-nav-menu');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active'); // CSS must define .active to show the menu
        });
    }

    // 6. Global Error Handler
    window.addEventListener('error', (event) => {
        console.error("Global Unhandled Exception:", event.message, event.filename, event.lineno);
        // Add logic here to send critical JS errors to a monitoring service (Sentry/LogRocket)
    });
});