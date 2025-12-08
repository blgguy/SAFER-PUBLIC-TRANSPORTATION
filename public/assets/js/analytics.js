// File: public/assets/js/analytics.js
// Description: Fetches aggregated data and renders dashboard charts using Chart.js.

// Check if Chart.js is loaded
if (typeof Chart === 'undefined') {
    console.error('Chart.js library is required for analytics.js.');
}

const API_ENDPOINT = '/api/admin/data-aggregation.php';

/**
 * Gets the auth token from local storage.
 * NOTE: This relies on Layer 7.1 login implementation storing the token.
 */
function getAuthToken() {
    // Replace with actual storage key if different
    return localStorage.getItem('admin_token'); 
}

/**
 * Fetches data from the aggregation API.
 * @param {string} startDate - YYYY-MM-DD
 * @param {string} endDate - YYYY-MM-DD
 */
async function fetchAnalyticsData(startDate, endDate) {
    const token = getAuthToken();
    if (!token) {
        console.error('Authentication token missing. Cannot fetch analytics data.');
        // Redirect to login or show error
        return null;
    }

    const url = `${API_ENDPOINT}?start_date=${startDate}&end_date=${endDate}`;

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (response.status === 401 || response.status === 403) {
            console.error('Session expired or unauthorized.');
            // Force logout and redirect to login
            // document.getElementById('login-overlay').style.display = 'flex'; 
            return null;
        }

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        if (result.success) {
            return result.data;
        } else {
            console.error('API Error:', result.message);
            return null;
        }

    } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
        return null;
    }
}

/**
 * Updates the four main metric cards on the dashboard.
 * @param {object} data - The aggregated data object.
 */
function updateKeyMetrics(data) {
    document.getElementById('metric-reports-total').textContent = data.total_reports.toLocaleString();
    document.getElementById('metric-critical-count').textContent = data.critical_count.toLocaleString();
    document.getElementById('metric-verification-rate').textContent = `${data.verification_rate}%`;
    
    // Avg Response Time is N/A in the current API, use placeholder
    document.getElementById('metric-response-time').textContent = data.avg_response_time === 'N/A' ? 'N/A' : `${data.avg_response_time} min`; 
}

/**
 * Renders the daily incident trend line chart.
 * @param {object} trendData - The daily_trend object {date: count}.
 */
function renderDailyTrendChart(trendData) {
    const ctx = document.getElementById('incidents-line-chart').getContext('2d');
    
    // Prepare data arrays
    const labels = Object.keys(trendData).map(date => new Date(date).toLocaleDateString());
    const data = Object.values(trendData);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Incidents Reported',
                data: data,
                borderColor: '#2563eb', // --color-primary
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Reports'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: false
                }
            }
        }
    });
}

/**
 * Renders the severity breakdown doughnut chart.
 * @param {object} severityData - The severity_breakdown object {severity: count}.
 */
function renderSeverityChart(severityData) {
    const ctx = document.getElementById('severity-pie-chart').getContext('2d');
    
    const labels = Object.keys(severityData);
    const data = Object.values(severityData);
    
    // Define colors based on severity (using CSS variables)
    const severityColors = {
        'Critical': '#dc2626',
        'High': '#ea580c',
        'Medium': '#facc15',
        'Low': '#16a34a'
    };

    const backgroundColors = labels.map(label => severityColors[label] || '#94a3b8');

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: false
                }
            }
        }
    });
}

/**
 * Main function to initialize and refresh dashboard analytics.
 * @param {string} startDate - Start date for data filter.
 * @param {string} endDate - End date for data filter.
 */
export async function initializeAnalytics(startDate = null, endDate = null) {
    // Default dates for the last 7 days if not provided
    const today = new Date();
    const defaultEndDate = today.toISOString().split('T')[0];
    const defaultStartDate = new Date(today.setDate(today.getDate() - 7)).toISOString().split('T')[0];
    
    startDate = startDate || defaultStartDate;
    endDate = endDate || defaultEndDate;
    
    const data = await fetchAnalyticsData(startDate, endDate);
    
    if (data) {
        updateKeyMetrics(data);
        renderDailyTrendChart(data.daily_trend);
        renderSeverityChart(data.severity_breakdown);
        console.log('Analytics dashboard successfully rendered.');
    }
}

// Global initialization when script loads (to be called by main.js after auth)
// initializeAnalytics(); 
// This will be called from main.js/dashboard.js once authentication is confirmed.