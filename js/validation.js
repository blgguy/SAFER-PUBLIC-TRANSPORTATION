/**
 * Form Validation JavaScript
 * Client-side validation for incident report form
 * File: js/validation.js
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    // Get form elements
    const reportForm = document.getElementById('reportForm');
    const descriptionField = document.getElementById('description');
    const charCountSpan = document.getElementById('charCount');
    const submitBtn = document.getElementById('submitBtn');
    
    // Character counter for description
    if (descriptionField && charCountSpan) {
        descriptionField.addEventListener('input', function() {
            const currentLength = this.value.length;
            charCountSpan.textContent = currentLength;
            
            // Change color based on length
            if (currentLength < 10) {
                charCountSpan.style.color = '#dc3545'; // Red
            } else if (currentLength > 450) {
                charCountSpan.style.color = '#ffc107'; // Yellow warning
            } else {
                charCountSpan.style.color = '#28a745'; // Green
            }
        });
    }
    
    // Form validation on submit
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Clear all previous error messages
            document.querySelectorAll('.error').forEach(el => el.textContent = '');
            
            // Validate incident type
            const incidentType = document.getElementById('incident_type');
            if (!incidentType.value) {
                showError('error_type', 'Please select an incident type');
                isValid = false;
            }
            
            // Validate transport mode
            const transportMode = document.getElementById('transport_mode');
            if (!transportMode.value) {
                showError('error_transport', 'Please select a transport mode');
                isValid = false;
            }
            
            // Validate location
            const latitude = document.getElementById('latitude');
            const longitude = document.getElementById('longitude');
            if (!latitude.value || !longitude.value) {
                showError('error_location', 'Please detect your location before submitting');
                isValid = false;
            }
            
            // Validate description
            const description = document.getElementById('description');
            if (description.value.trim().length < 10) {
                showError('error_description', 'Description must be at least 10 characters');
                isValid = false;
            }
            if (description.value.length > 500) {
                showError('error_description', 'Description cannot exceed 500 characters');
                isValid = false;
            }
            
            // Validate severity
            const severityChecked = document.querySelector('input[name="severity"]:checked');
            if (!severityChecked) {
                showError('error_severity', 'Please select a severity level');
                isValid = false;
            }
            
            // Prevent form submission if validation fails
            if (!isValid) {
                e.preventDefault();
                
                // Scroll to first error
                const firstError = document.querySelector('.error:not(:empty)');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                return false;
            }
            
            // Disable submit button to prevent double submission
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
            }
        });
    }
    
    // Real-time validation for description
    if (descriptionField) {
        descriptionField.addEventListener('blur', function() {
            const length = this.value.trim().length;
            if (length > 0 && length < 10) {
                showError('error_description', 'Description must be at least 10 characters');
            } else {
                clearError('error_description');
            }
        });
    }
    
    // Clear error on field focus
    const allInputs = document.querySelectorAll('input, select, textarea');
    allInputs.forEach(input => {
        input.addEventListener('focus', function() {
            const fieldName = this.id;
            clearError('error_' + fieldName.replace('incident_', '').replace('transport_', ''));
        });
    });
    
});

// Helper function to show error message
function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

// Helper function to clear error message
function clearError(elementId) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

// Validate required fields in real-time
function validateField(fieldId, errorId, errorMessage) {
    const field = document.getElementById(fieldId);
    const errorDiv = document.getElementById(errorId);
    
    if (field && errorDiv) {
        if (!field.value || field.value.trim() === '') {
            errorDiv.textContent = errorMessage;
            return false;
        } else {
            errorDiv.textContent = '';
            return true;
        }
    }
    return true;
}