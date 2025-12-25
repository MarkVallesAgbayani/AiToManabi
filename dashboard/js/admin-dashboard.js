// Dashboard JavaScript - Analytics & Charts
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeEventListeners();
    initializeTableSorting();
});

// Chart instances
let retentionChart = null;
let salesChart = null;

// Initialize all charts
function initializeCharts() {
    initializeRetentionChart();
    initializeSalesChart();
}

// Initialize Retention Chart
function initializeRetentionChart() {
    const ctx = document.getElementById('retentionChart');
    if (!ctx) return;

    const data = retentionData.map(item => ({
        period: item.period,
        active: parseInt(item.active_users) || 0,
        churned: parseInt(item.churned_users) || 0,
        new: parseInt(item.new_users) || 0
    }));

    retentionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.period).reverse(),
            datasets: [
                {
                    label: 'Active Users',
                    data: data.map(item => item.active).reverse(),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Churned Users',
                    data: data.map(item => item.churned).reverse(),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
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
                        padding: 20
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
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y} users`;
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
                            size: 12
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
                            size: 12
                        },
                        callback: function(value) {
                            return value + ' users';
                        }
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

// Initialize Sales Chart
function initializeSalesChart() {
    const ctx = document.getElementById('salesChart');
    if (!ctx) return;

    const data = salesData.map(item => ({
        period: item.period,
        revenue: parseFloat(item.total_revenue) || 0,
        sales: parseInt(item.total_sales) || 0,
        customers: parseInt(item.unique_customers) || 0
    }));

    salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.period).reverse(),
            datasets: [
                {
                    label: 'Revenue (₱)',
                    data: data.map(item => item.revenue).reverse(),
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: '#22c55e',
                    borderWidth: 1,
                    yAxisID: 'y',
                    order: 2
                },
                {
                    label: 'Sales Count',
                    data: data.map(item => item.sales).reverse(),
                    type: 'line',
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    yAxisID: 'y1',
                    order: 1
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
                        padding: 20
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
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return `Revenue: ₱${context.parsed.y.toLocaleString()}`;
                            } else {
                                return `Sales: ${context.parsed.y} transactions`;
                            }
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
                            size: 12
                        }
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 12
                        },
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 12
                        }
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

// Initialize Event Listeners
function initializeEventListeners() {
    // Completion time range filter
    const completionTimeRange = document.getElementById('completionTimeRange');
    if (completionTimeRange) {
        console.log('Completion filter found and initialized');
        completionTimeRange.addEventListener('change', function() {
            console.log('Completion filter changed to:', this.value);
            updateCompletionData(this.value);
        });
    } else {
        console.warn('Completion filter not found');
    }

    // Retention time range filter
    const retentionTimeRange = document.getElementById('retentionTimeRange');
    if (retentionTimeRange) {
        console.log('Retention filter found and initialized');
        retentionTimeRange.addEventListener('change', function() {
            console.log('Retention filter changed to:', this.value);
            updateRetentionData(this.value);
        });
    } else {
        console.warn('Retention filter not found');
    }

    // Sales time range filter
    const salesTimeRange = document.getElementById('salesTimeRange');
    if (salesTimeRange) {
        console.log('Sales filter found and initialized');
        salesTimeRange.addEventListener('change', function() {
            console.log('Sales filter changed to:', this.value);
            updateSalesData(this.value);
        });
    } else {
        console.warn('Sales filter not found');
    }

    // Mobile sidebar toggle
    const mobileToggle = document.querySelector('[onclick="toggleMobileSidebar()"]');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleMobileSidebar);
    }

    // Notification bell already has onclick handler from notification system
    // No need to add another event listener here
    // Click-outside handling is also done by the notification system
}

// Update completion data based on time range
function updateCompletionData(timeRange) {
    console.log('Updating completion data for range:', timeRange);
    showLoadingState('completionTable');
    
    fetch(`get_completion_data.php?range=${timeRange}`)
        .then(response => {
            console.log('Completion data response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Completion data received:', data);
            if (data.success) {
                updateCompletionTable(data.data);
            } else {
                showNotification(data.message || 'Failed to load completion data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading completion data: ' + error.message, 'error');
        })
        .finally(() => {
            hideLoadingState('completionTable');
        });
}

// Update retention data and chart
function updateRetentionData(timeRange) {
    console.log('Updating retention data for range:', timeRange);
    showLoadingState('retentionChart');
    
    fetch(`get_retention_data.php?range=${timeRange}`)
        .then(response => {
            console.log('Retention data response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Retention data received:', data);
            if (data.success) {
                updateRetentionChart(data.data);
            } else {
                showNotification(data.message || 'Failed to load retention data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading retention data: ' + error.message, 'error');
        })
        .finally(() => {
            hideLoadingState('retentionChart');
        });
}

// Update sales data and chart
function updateSalesData(timeRange) {
    console.log('Updating sales data for range:', timeRange);
    showLoadingState('salesChart');
    
    fetch(`get_sales_data.php?range=${timeRange}`)
        .then(response => {
            console.log('Sales data response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Sales data received:', data);
            if (data.success) {
                updateSalesChart(data.data);
            } else {
                showNotification(data.message || 'Failed to load sales data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading sales data: ' + error.message, 'error');
        })
        .finally(() => {
            hideLoadingState('salesChart');
        });
}

// Update completion table
function updateCompletionTable(data) {
    const tbody = document.getElementById('completionTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';
    
    data.forEach(course => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                ${escapeHtml(course.course_name)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${course.total_enrolled}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${course.completed_count}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: ${course.completion_rate}%"></div>
                    </div>
                    <span class="text-sm text-gray-900">${course.completion_rate}%</span>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${course.avg_score ? Math.round(course.avg_score) + '%' : 'N/A'}
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Update retention chart
function updateRetentionChart(data) {
    if (!retentionChart) return;

    const processedData = data.map(item => ({
        period: item.period,
        active: parseInt(item.active_users) || 0,
        churned: parseInt(item.churned_users) || 0
    }));

    retentionChart.data.labels = processedData.map(item => item.period).reverse();
    retentionChart.data.datasets[0].data = processedData.map(item => item.active).reverse();
    retentionChart.data.datasets[1].data = processedData.map(item => item.churned).reverse();
    
    retentionChart.update('active');
}

// Update sales chart
function updateSalesChart(data) {
    if (!salesChart) return;

    const processedData = data.map(item => ({
        period: item.period,
        revenue: parseFloat(item.total_revenue) || 0,
        sales: parseInt(item.total_sales) || 0
    }));

    salesChart.data.labels = processedData.map(item => item.period).reverse();
    salesChart.data.datasets[0].data = processedData.map(item => item.revenue).reverse();
    salesChart.data.datasets[1].data = processedData.map(item => item.sales).reverse();
    
    salesChart.update('active');
}

// Table sorting functionality
function initializeTableSorting() {
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        const headers = table.querySelectorAll('th[onclick]');
        headers.forEach(header => {
            header.classList.add('sortable-header');
        });
    });
}

// Sort table function
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const header = table.querySelectorAll('th')[columnIndex];
    
    // Determine sort direction
    const isAscending = !header.classList.contains('asc');
    
    // Remove existing sort classes
    table.querySelectorAll('th').forEach(th => {
        th.classList.remove('asc', 'desc');
    });
    
    // Add new sort class
    header.classList.add(isAscending ? 'asc' : 'desc');
    
    // Sort rows
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Check if values are numeric
        const aNum = parseFloat(aValue.replace(/[^\d.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? aNum - bNum : bNum - aNum;
        } else {
            return isAscending ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
        }
    });
    
    // Reorder table rows
    rows.forEach(row => tbody.appendChild(row));
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
function showLoadingState(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.classList.add('loading');
    }
}

function hideLoadingState(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.classList.remove('loading');
    }
}

// Notification System
function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} fixed top-4 right-4 z-50 min-w-80`;
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

// Utility Functions
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
            case 'd':
                e.preventDefault();
                window.location.href = '?view=dashboard';
                break;
            case 'p':
                e.preventDefault();
                window.location.href = '?view=payments';
                break;
            case 'r':
                e.preventDefault();
                location.reload();
                break;
            case 'n':
                e.preventDefault();
                // Use centralized notification system
                if (typeof toggleNotifications === 'function') {
                    toggleNotifications();
                }
                break;
        }
    }
});

// Auto-refresh functionality (optional)
let autoRefreshInterval;

function startAutoRefresh(intervalMs = 300000) { // 5 minutes default
    autoRefreshInterval = setInterval(() => {
        if (!document.hidden) {
            refreshDashboardData();
        }
    }, intervalMs);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

function refreshDashboardData() {
    // Refresh summary cards
    fetch('get_dashboard_summary.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSummaryCards(data.data);
            }
        })
        .catch(error => console.error('Error refreshing dashboard data:', error));
}

function updateSummaryCards(data) {
    // Update summary card values
    const cards = {
        students: data.total_students,
        teachers: data.total_teachers,
        courses: data.total_courses,
        revenue: data.total_revenue
    };
    
    Object.keys(cards).forEach(key => {
        const element = document.querySelector(`[data-metric="${key}"]`);
        if (element) {
            element.textContent = key === 'revenue' ? formatCurrency(cards[key]) : formatNumber(cards[key]);
        }
    });
}

// Page visibility handling
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

// Initialize auto-refresh on page load (optional)
// startAutoRefresh();

// Export functions for potential external use
window.dashboardFunctions = {
    sortTable,
    toggleMobileSidebar,
    showNotification,
    updateCompletionData,
    updateRetentionData,
    updateSalesData,
    formatCurrency,
    formatNumber,
    formatPercentage
};
