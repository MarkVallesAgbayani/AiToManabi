// Usage Analytics JavaScript - Charts & Interactive Features
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeEventListeners();
    initializeAutoRefresh();
});

// Chart instances
let trendChart = null;
let roleChart = null;

// Initialize all charts
function initializeCharts() {
    initializeTrendChart();
    initializeRoleChart();
}

// Initialize Active Users Trend Chart
function initializeTrendChart() {
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;

    // Process data for trend chart
    const processedData = processActiveUsersData(activeUsersData);
    
    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: processedData.labels,
            datasets: [
                {
                    label: 'Total Active Users',
                    data: processedData.totalUsers,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#0ea5e9',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                },
                {
                    label: 'Students',
                    data: processedData.students,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                },
                {
                    label: 'Teachers',
                    data: processedData.teachers,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleColor: '#f9fafb',
                    bodyColor: '#f9fafb',
                    borderColor: '#374151',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        title: function(context) {
                            return formatDateLabel(context[0].label, viewType);
                        },
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y.toLocaleString()} users`;
                        },
                        afterBody: function(context) {
                            const total = context.reduce((sum, item) => sum + item.parsed.y, 0);
                            return [`Total: ${total.toLocaleString()} users`];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 11
                        },
                        maxRotation: 45,
                        callback: function(value, index, values) {
                            return formatDateLabel(this.getLabelForValue(value), viewType);
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return value.toLocaleString() + ' users';
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart'
            },
            elements: {
                line: {
                    borderWidth: 3
                }
            }
        }
    });
}

// Initialize Role Breakdown Chart
function initializeRoleChart() {
    const ctx = document.getElementById('roleChart');
    if (!ctx) return;

    const labels = roleBreakdownData.map(item => capitalizeFirst(item.role));
    const data = roleBreakdownData.map(item => parseInt(item.active_users));
    const colors = generateRoleColors(roleBreakdownData.length);

    roleChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.background,
                borderColor: colors.border,
                borderWidth: 2,
                hoverOffset: 10,
                hoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: '500'
                        },
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    const dataset = data.datasets[0];
                                    const value = dataset.data[i];
                                    const total = dataset.data.reduce((sum, val) => sum + val, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                    
                                    return {
                                        text: `${label}: ${value.toLocaleString()} (${percentage}%)`,
                                        fillStyle: dataset.backgroundColor[i],
                                        strokeStyle: dataset.borderColor[i],
                                        lineWidth: dataset.borderWidth,
                                        pointStyle: 'circle',
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                            return [];
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleColor: '#f9fafb',
                    bodyColor: '#f9fafb',
                    borderColor: '#374151',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((sum, val) => sum + val, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : '0.0';
                            return `${context.label}: ${context.parsed.toLocaleString()} users (${percentage}%)`;
                        }
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart',
                animateRotate: true,
                animateScale: true
            },
            cutout: '60%'
        }
    });
}

// Process active users data for trend chart
function processActiveUsersData(rawData) {
    const periodMap = new Map();
    
    // Helper: normalize server period to a display label while preserving a sortable key
    function normalizePeriod(period) {
        if (viewType === 'weekly') {
            // Backend returns YEARWEEK(...,0) which is numeric like 202542
            let year = null, week = null, key = null, label = null;
            if (typeof period === 'number') {
                year = Math.floor(period / 100);
                week = period % 100;
                key = period; // numeric sortable key
            } else if (typeof period === 'string') {
                if (/^\d{6}$/.test(period)) {
                    year = parseInt(period.slice(0, 4), 10);
                    week = parseInt(period.slice(4), 10);
                    key = parseInt(period, 10);
                } else if (period.includes('-W')) { // already like YYYY-Www
                    const parts = period.split('-W');
                    year = parseInt(parts[0], 10);
                    week = parseInt(parts[1], 10);
                    key = year * 100 + week;
                }
            }
            if (year !== null && week !== null) {
                label = `${year}-W${String(week).padStart(2, '0')}`;
                return { key, label };
            }
            return { key: String(period), label: String(period) };
        }
        // monthly: 'YYYY-MM', yearly: 'YYYY', daily: 'YYYY-MM-DD'
        return { key: String(period), label: String(period) };
    }
    
    // Group data by normalized period
    rawData.forEach(item => {
        const normalized = normalizePeriod(item.period);
        const mapKey = normalized.key;
        if (!periodMap.has(mapKey)) {
            periodMap.set(mapKey, {
                label: normalized.label,
                total: 0,
                students: 0,
                teachers: 0,
                admins: 0
            });
        }
        
        const data = periodMap.get(mapKey);
        const activeUsers = parseInt(item.active_users) || 0;
        
        data.total += activeUsers;
        if (item.role === 'student') data.students = activeUsers;
        else if (item.role === 'teacher') data.teachers = activeUsers;
        else if (item.role === 'admin') data.admins = activeUsers;
    });
    
    // Sort keys according to view type semantics
    const sortedKeys = Array.from(periodMap.keys()).sort((a, b) => {
        if (viewType === 'daily') {
            return new Date(periodMap.get(a).label) - new Date(periodMap.get(b).label);
        }
        if (viewType === 'monthly') {
            return String(a).localeCompare(String(b)); // 'YYYY-MM'
        }
        if (viewType === 'yearly' || viewType === 'weekly') {
            return Number(a) - Number(b);
        }
        return String(a).localeCompare(String(b));
    });
    
    const labels = sortedKeys.map(key => periodMap.get(key).label);
    
    return {
        labels,
        totalUsers: sortedKeys.map(key => periodMap.get(key).total),
        students: sortedKeys.map(key => periodMap.get(key).students),
        teachers: sortedKeys.map(key => periodMap.get(key).teachers),
        admins: sortedKeys.map(key => periodMap.get(key).admins)
    };
}

// Generate colors for role chart
function generateRoleColors(count) {
    const baseColors = [
        { bg: 'rgba(59, 130, 246, 0.8)', border: '#3b82f6' },     // Blue - Students
        { bg: 'rgba(16, 185, 129, 0.8)', border: '#10b981' },     // Green - Teachers
        { bg: 'rgba(139, 92, 246, 0.8)', border: '#8b5cf6' },     // Purple - Admins
        { bg: 'rgba(245, 158, 11, 0.8)', border: '#f59e0b' },     // Amber
        { bg: 'rgba(239, 68, 68, 0.8)', border: '#ef4444' },      // Red
        { bg: 'rgba(6, 182, 212, 0.8)', border: '#06b6d4' }       // Cyan
    ];
    
    const background = [];
    const border = [];
    
    for (let i = 0; i < count; i++) {
        const colorIndex = i % baseColors.length;
        background.push(baseColors[colorIndex].bg);
        border.push(baseColors[colorIndex].border);
    }
    
    return { background, border };
}

// Format date labels based on view type
function formatDateLabel(label, viewType) {
    if (!label) return '';
    
    switch (viewType) {
        case 'daily':
            return new Date(label).toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric',
                timeZone: 'Asia/Manila'
            });
        case 'weekly':
            if (typeof label === 'number' || /^\d{6}$/.test(String(label))) {
                const year = Math.floor(Number(label) / 100);
                const week = Number(label) % 100;
                return `Week ${String(week).padStart(2,'0')} (${year})`;
            }
            if (typeof label === 'string' && label.includes('-W')) {
                return `Week ${label.split('-W')[1]}`;
            }
            return `Week ${label}`;
        case 'monthly':
            return new Date(label + '-01').toLocaleDateString('en-US', { 
                month: 'short', 
                year: 'numeric',
                timeZone: 'Asia/Manila'
            });
        case 'yearly':
            return label;
        default:
            return label;
    }
}

// Initialize Event Listeners
function initializeEventListeners() {
    // Mobile sidebar toggle
    const mobileToggle = document.querySelector('[onclick="toggleMobileSidebar()"]');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleMobileSidebar);
    }

    // Notification bell already has onclick handler from notification system
    // No need to add another event listener here
    // Click-outside handling is also done by the notification system

    // Auto-submit form when filters change
    const filterForm = document.querySelector('form');
    const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Add loading state
            showLoadingState();
            // Submit form after short delay to allow user to see loading state
            setTimeout(() => {
                filterForm.submit();
            }, 300);
        });
    });

    // Auto adjust date range when view type changes
    const viewSelect = document.getElementById('view_type');
    if (viewSelect) {
        viewSelect.addEventListener('change', function() {
            const fromInput = document.getElementById('date_from');
            const toInput = document.getElementById('date_to');
            const now = new Date();
            const phtNow = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
            const toDate = phtNow;
            let fromDate = new Date(toDate);
            switch (this.value) {
                case 'daily':
                    fromDate.setDate(toDate.getDate() - 30);
                    break;
                case 'weekly':
                    fromDate.setDate(toDate.getDate() - 7 * 12);
                    break;
                case 'monthly':
                    fromDate.setMonth(toDate.getMonth() - 11);
                    fromDate.setDate(1);
                    break;
                case 'yearly':
                    fromDate.setFullYear(toDate.getFullYear() - 4, 0, 1);
                    break;
            }
            const fmt = d => d.toISOString().slice(0,10);
            if (fromInput) fromInput.value = fmt(fromDate);
            if (toInput) toInput.value = fmt(toDate);
        });
    }

    // Table row hover effects
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(2px)';
        });
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
}

// Auto-refresh functionality
function initializeAutoRefresh() {
    // Refresh every 5 minutes
    setInterval(() => {
        if (!document.hidden) {
            refreshData();
        }
    }, 300000);
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            refreshData();
        }
    });
}

// Refresh data without full page reload
function refreshData() {
    const currentParams = new URLSearchParams(window.location.search);
    
    fetch(`usage-analytics.php?ajax=1&${currentParams.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardCards(data.stats);
                updateCharts(data.chartData);
                showNotification('Data refreshed successfully', 'success', 2000);
            }
        })
        .catch(error => {
            console.error('Auto-refresh error:', error);
        });
}

// Update dashboard cards
function updateDashboardCards(stats) {
    const cards = {
        'total_active': stats.total_active,
        'daily_average': stats.daily_average,
        'peak_count': stats.peak_count,
        'growth_rate': stats.growth_rate
    };
    
    Object.keys(cards).forEach(key => {
        const element = document.querySelector(`[data-metric="${key}"]`);
        if (element) {
            element.textContent = formatMetricValue(key, cards[key]);
        }
    });
}

// Update charts with new data
function updateCharts(chartData) {
    if (trendChart && chartData.activeUsers) {
        const processedData = processActiveUsersData(chartData.activeUsers);
        trendChart.data.labels = processedData.labels;
        trendChart.data.datasets[0].data = processedData.totalUsers;
        trendChart.data.datasets[1].data = processedData.students;
        trendChart.data.datasets[2].data = processedData.teachers;
        trendChart.update('active');
    }
    
    if (roleChart && chartData.roleBreakdown) {
        const labels = chartData.roleBreakdown.map(item => capitalizeFirst(item.role));
        const data = chartData.roleBreakdown.map(item => parseInt(item.active_users));
        
        roleChart.data.labels = labels;
        roleChart.data.datasets[0].data = data;
        roleChart.update('active');
    }
}

// Export functionality
function exportData(format) {
    // Validate export columns before proceeding
    if (!validateExportColumns()) {
        showValidationModal();
        return;
    }
    
    // Collect all form data (Filter Analytics Data + Export Configuration)
    const formData = collectAllFormData();
    
    // Create URL parameters with all form data
    const currentParams = new URLSearchParams();
    currentParams.set('export', format);
    
    // Add all form data to parameters
    Object.keys(formData).forEach(key => {
        if (Array.isArray(formData[key])) {
            formData[key].forEach(value => {
                currentParams.append(key + '[]', value);
            });
        } else if (formData[key] !== null && formData[key] !== '') {
            currentParams.set(key, formData[key]);
        }
    });
    
    // Show loading state
    const exportBtn = event.target;
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Exporting...';
    exportBtn.disabled = true;
    
    // Create temporary link for download
    const link = document.createElement('a');
    link.href = `usage-analytics.php?${currentParams.toString()}`;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset button after delay
    setTimeout(() => {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        showNotification(`${format.toUpperCase()} export completed`, 'success', 3000);
    }, 2000);
}

// Validate that at least one export column is selected
function validateExportColumns() {
    const exportColumns = document.querySelectorAll('input[name="export_columns[]"]:checked');
    return exportColumns.length > 0;
}

// Collect all form data from both Filter Analytics Data and Export Configuration
function collectAllFormData() {
    const formData = {};
    
    // Get all form inputs
    const form = document.getElementById('filterForm');
    if (!form) return formData;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        // Normalize names like "export_columns[]" -> "export_columns"
        const baseName = input.name ? input.name.replace(/\[\]$/, '') : '';
        if (input.type === 'checkbox') {
            if (input.checked) {
                if (!formData[baseName]) {
                    formData[baseName] = [];
                }
                formData[baseName].push(input.value);
            }
        } else if (input.type === 'radio') {
            if (input.checked) {
                formData[baseName] = input.value;
            }
        } else if (baseName && input.value) {
            formData[baseName] = input.value;
        }
    });
    
    return formData;
}

// Show validation modal
function showValidationModal() {
    // Remove existing modal if any
    const existingModal = document.getElementById('validationModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'validationModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-gray-900">Export Validation Required</h3>
                    </div>
                </div>
                <div class="mb-6">
                    <p class="text-sm text-gray-600">Please select at least one export column to generate the PDF report.</p>
                </div>
                <div class="flex justify-end">
                    <button onclick="closeValidationModal()" class="bg-primary-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        OK
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeValidationModal();
        }
    });
}

// Close validation modal
function closeValidationModal() {
    const modal = document.getElementById('validationModal');
    if (modal) {
        modal.remove();
    }
}

// Mobile Sidebar Toggle
function toggleMobileSidebar() {
    const sidebar = document.querySelector('.bg-white.w-64');
    if (sidebar) {
        sidebar.classList.toggle('mobile-open');
    }
}

// Notification System - Only define if not already defined
if (typeof toggleNotifications !== 'function') {
    window.toggleNotifications = function() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }
}

// Loading States
function showLoadingState() {
    // Add loading overlay
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'fixed inset-0 bg-black bg-opacity-25 flex items-center justify-center z-50';
    overlay.innerHTML = `
        <div class="bg-white rounded-lg p-6 flex items-center gap-3 shadow-lg">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
            <span class="text-gray-700 font-medium">Loading analytics data...</span>
        </div>
    `;
    document.body.appendChild(overlay);
}

function hideLoadingState() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

// Notification System
function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} fixed top-4 right-4 z-50 min-w-80 transform transition-all duration-300 translate-x-full`;
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg hover:opacity-70">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Slide in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, duration);
}

// Utility Functions
function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function formatMetricValue(key, value) {
    switch (key) {
        case 'total_active':
        case 'peak_count':
            return value.toLocaleString();
        case 'daily_average':
            return value.toFixed(1);
        case 'growth_rate':
            return (value >= 0 ? '+' : '') + value + '%';
        default:
            return value;
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

function formatNumber(number) {
    return new Intl.NumberFormat().format(number);
}

function formatPercentage(value, decimals = 1) {
    return (value).toFixed(decimals) + '%';
}

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'r':
                e.preventDefault();
                location.reload();
                break;
            case 'e':
                e.preventDefault();
                exportData('csv');
                break;
            case 'n':
                e.preventDefault();
                toggleNotifications();
                break;
            case 'f':
                e.preventDefault();
                const firstFilter = document.querySelector('input[type="date"], select');
                if (firstFilter) firstFilter.focus();
                break;
        }
    }
    
    // ESC to close notifications
    if (e.key === 'Escape') {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
        hideLoadingState();
    }
});

// Chart resize handler
window.addEventListener('resize', function() {
    if (trendChart) trendChart.resize();
    if (roleChart) roleChart.resize();
});

// Performance monitoring
const performanceObserver = new PerformanceObserver((list) => {
    for (const entry of list.getEntries()) {
        if (entry.entryType === 'navigation') {
            console.log(`Page load time: ${entry.loadEventEnd - entry.loadEventStart}ms`);
        }
    }
});

if (typeof PerformanceObserver !== 'undefined') {
    performanceObserver.observe({ entryTypes: ['navigation'] });
}

// Export functions for potential external use
window.usageAnalyticsFunctions = {
    exportData,
    toggleMobileSidebar,
    // toggleNotifications is provided by the global notification system
    showNotification,
    refreshData,
    formatCurrency,
    formatNumber,
    formatPercentage
};

// Make modal functions globally accessible
window.closeValidationModal = closeValidationModal;

// Initialize tooltips (if any)
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const text = this.getAttribute('data-tooltip');
            const tooltipElement = document.createElement('div');
            tooltipElement.className = 'tooltip-popup';
            tooltipElement.textContent = text;
            document.body.appendChild(tooltipElement);
            
            // Position tooltip
            const rect = this.getBoundingClientRect();
            tooltipElement.style.position = 'absolute';
            tooltipElement.style.top = (rect.top - tooltipElement.offsetHeight - 5) + 'px';
            tooltipElement.style.left = (rect.left + rect.width / 2 - tooltipElement.offsetWidth / 2) + 'px';
        });
        
        tooltip.addEventListener('mouseleave', function() {
            const tooltipElement = document.querySelector('.tooltip-popup');
            if (tooltipElement) {
                tooltipElement.remove();
            }
        });
    });
});

// Analytics tracking (optional)
function trackEvent(category, action, label = '') {
    // Implement analytics tracking here if needed
    console.log(`Analytics: ${category} - ${action} - ${label}`);
}

// Track user interactions
document.addEventListener('click', function(e) {
    if (e.target.matches('.export-buttons button')) {
        trackEvent('Export', 'Click', e.target.textContent.trim());
    }
    
    if (e.target.matches('select')) {
        trackEvent('Filter', 'Change', e.target.name);
    }
});

console.log('Usage Analytics Dashboard initialized successfully!');
