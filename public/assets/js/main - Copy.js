// File: public/assets/js/main.js
// Description: Core application logic, PWA registration, and Incident Report form control.

document.addEventListener('DOMContentLoaded', () => {
    // 1. PWA Service Worker Registration
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

    // 2. Geolocation/CSRF Placeholder Setup (Applicable to report.html)
    // --- IMPORTANT: In a real environment, the CSRF token value must be inserted 
    // --- server-side into the hidden field when the page is served.
    const csrfTokenField = document.getElementById('csrf-token');
    if (csrfTokenField) {
        // Placeholder for initial load (replace with server-generated value)
        if (csrfTokenField.value === '[CSRF_TOKEN_PLACEHOLDER]') {
            console.warn("CSRF Token Placeholder detected. In production, this must be securely generated server-side.");
            // Example of fetching a token if using a CSRF API endpoint (Layer 3 lacks this)
            // fetch('/api/security/csrf-token').then(r => r.json()).then(data => csrfTokenField.value = data.token);
            // For now, let's just make up a temporary one to avoid errors
             csrfTokenField.value = 'TEMP_CSRF_TOKEN_12345';
        }
    }


    // 3. Incident Reporting Form Logic (Specific to report.html)
    const form = document.getElementById('incident-report-form');
    if (!form) return; // Stop if not on the report page

    let currentStep = 1;
    const totalSteps = 4;

    const navButtons = document.getElementById('form-navigation');
    const prevBtn = document.getElementById('prev-step-btn');
    const nextBtn = document.getElementById('next-step-btn');
    const submitBtn = document.getElementById('submit-report-btn');
    const stepsContent = document.querySelectorAll('.form-step-content');
    const progressSteps = document.querySelectorAll('.progress-steps .step');
    const formErrorDiv = document.getElementById('form-submission-error');

    // --- Core Functions ---

    /** Displays the specified step and updates navigation/progress. */
    const showStep = (step) => {
        if (step < 1 || step > totalSteps) return;

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
        nextBtn.style.display = (step === totalSteps) ? 'none' : 'inline-flex';
        submitBtn.style.display = (step === totalSteps) ? 'inline-flex' : 'none';

        if (step === totalSteps) {
            populateReview();
        }
    };

    /** Client-side validation for the current step. */
    const validateStep = (step) => {
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
                return true; // Review screen validation is done on previous steps
            
            default:
                return false;
        }
    };

    /** Populates the final review screen data. */
    const populateReview = () => {
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
    };

    /** Tries to get the user's current geolocation. */
    const autoLocate = () => {
        const statusDiv = document.getElementById('location-status');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');

        statusDiv.textContent = '🧭 Detecting location...';
        statusDiv.style.color = '#2563eb';

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude.toFixed(6);
                    const lng = position.coords.longitude.toFixed(6);
                    
                    latInput.value = lat;
                    lngInput.value = lng;
                    statusDiv.textContent = `✅ Location detected! Lat: ${lat}, Lng: ${lng}`;
                    statusDiv.style.color = '#16a34a';
                },
                (error) => {
                    statusDiv.textContent = '❌ Failed to get location. Please enter manually.';
                    statusDiv.style.color = '#dc2626';
                    console.error('Geolocation Error:', error);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        } else {
            statusDiv.textContent = '❌ Geolocation is not supported by this browser.';
            statusDiv.style.color = '#dc2626';
        }
    };


    // --- Event Listeners ---

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
    document.getElementById('auto-locate-btn').addEventListener('click', autoLocate);
    
    // Step 1: Incident Type Selection Handler
    document.getElementById('incident-type-selection').addEventListener('click', (e) => {
        const item = e.target.closest('.selection-item');
        if (item) {
            document.querySelectorAll('.selection-item').forEach(el => el.classList.remove('selected'));
            item.classList.add('selected');
            document.getElementById('incident-type-id').value = item.dataset.id;
        }
    });

    // Step 3: Severity Selection Handler
    document.getElementById('severity-options').addEventListener('click', (e) => {
        const item = e.target.closest('.severity-item');
        if (item) {
            document.querySelectorAll('.severity-item').forEach(el => el.classList.remove('selected'));
            item.classList.add('selected');
            document.getElementById('severity-level').value = item.dataset.severity;
        }
    });
    
    // Step 2: Transportation Mode Handler (to set hidden field)
    document.getElementById('transport-mode').addEventListener('change', (e) => {
        document.getElementById('transport-mode-value').value = e.target.value;
    });

    // Step 3: Character Count for Description
    const descriptionTextarea = document.getElementById('description');
    const charCount = document.getElementById('char-count');
    if (descriptionTextarea) {
        descriptionTextarea.addEventListener('input', () => {
            const remaining = 500 - descriptionTextarea.value.length;
            charCount.textContent = `${remaining} characters remaining`;
        });
    }

    // Final Form Submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!validateStep(totalSteps) || !validateStep(3) || !validateStep(2) || !validateStep(1)) {
            formErrorDiv.textContent = 'Please ensure all previous steps are completed correctly.';
            formErrorDiv.style.display = 'block';
            return;
        }

        const formData = {
            // Data from hidden inputs
            incident_type_id: document.getElementById('incident-type-id').value,
            severity: document.getElementById('severity-level').value,
            transportation_mode: document.getElementById('transport-mode-value').value,
            _csrf_token: csrfTokenField.value,

            // Data from visible inputs
            latitude: document.getElementById('latitude').value,
            longitude: document.getElementById('longitude').value,
            route_identifier: document.getElementById('route-identifier').value || null,
            description: document.getElementById('description').value,
            timestamp: document.getElementById('timestamp').value.replace('T', ' ') + ':00', // API needs YYYY-MM-DD HH:MM:SS
            address_description: null // We don't collect street address for anonymity
        };

        // Show loading state and disable buttons
        submitBtn.textContent = 'Submitting...';
        submitBtn.disabled = true;
        navButtons.style.opacity = '0.5';
        formErrorDiv.style.display = 'none';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // The CSRF token is in the payload, but could also be here:
                    // 'X-CSRF-Token': formData._csrf_token, 
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Success: Show confirmation screen
                document.getElementById('form-navigation').style.display = 'none';
                document.getElementById('step-4').style.display = 'none';
                document.getElementById('confirmation-screen').style.display = 'block';
                document.getElementById('tracking-id').textContent = result.report_id;
            } else {
                // Failure: Show error message
                const errorMessage = result.message || 'Submission failed due to an unknown error.';
                formErrorDiv.textContent = `Error: ${errorMessage}. Details: ${result.error || 'Check console.'}`;
                formErrorDiv.style.display = 'block';
                console.error('Submission Failed:', result);
            }

        } catch (error) {
            formErrorDiv.textContent = 'Network or Server Error. Please check your connection.';
            formErrorDiv.style.display = 'block';
            console.error('Fetch Error:', error);
        } finally {
            submitBtn.textContent = '🚨 Submit Report';
            submitBtn.disabled = false;
            navButtons.style.opacity = '1';
        }
    });

    // Initialize form to Step 1
    showStep(1);
});