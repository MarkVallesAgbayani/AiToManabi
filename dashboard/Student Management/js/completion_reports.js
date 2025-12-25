class CompletionReportsManager {
    constructor() {
        this.charts = {};
        this.currentFilters = {
            date_from: '',
            date_to: '',
            course_id: ''
        };
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadInitialData();
    }

    setupEventListeners() {
        // Filter form submission
        document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
            this.applyFilters();
        });

        document.getElementById('resetFiltersBtn')?.addEventListener('click', () => {
            this.resetFilters();
        });

        // Export button
        document.getElementById('exportDataBtn')?.addEventListener('click', () => {
            this.exportData();
        });

        // Real-time filter updates
        document.getElementById('date_from')?.addEventListener('change', () => {
            this.debounce(() => this.loadData(), 500);
        });

        document.getElementById('date_to')?.addEventListener('change', () => {
            this.debounce(() => this.loadData(), 500);
        });

        document.getElementById('course_filter')?.addEventListener('change', () => {
            this.debounce(() => this.loadData(), 500);
        });
    }

    async loadInitialData() {
        // Use initial data from server-side rendering if available
        if (window.initialCompletionData) {
            this.moduleData = window.initialCompletionData.module_breakdown || [];
            this.timelinessData = window.initialCompletionData.timeliness_data || {};
            
            // Update cards with initial data
            this.updateCompletionStatsCards(window.initialCompletionData.overall_stats || {});
            this.updateTimelinessCards(window.initialCompletionData.timeliness_data || {});
            
            // Also update the average progress card with progress stats
            this.updateAverageProgressCard(window.initialCompletionData.progress_stats || {});
            
            // Initialize charts with initial data
            this.initializeCharts();
            
            // Load courses for filter dropdown
            await this.loadCourses();
        } else {
            // Fallback to API calls if no initial data
            this.showLoading(true);
            try {
                await Promise.all([
                    this.loadCompletionStats(),
                    this.loadModuleBreakdown(),
                    this.loadTimelinessData(),
                    this.loadCourses()
                ]);
                this.initializeCharts();
            } catch (error) {
                console.error('Error loading initial data:', error);
                this.showError('Failed to load completion data');
            } finally {
                this.showLoading(false);
            }
        }
    }

    async loadData() {
        this.updateFilters();
        this.showLoading(true);
        try {
            await Promise.all([
                this.loadCompletionStats(),
                this.loadModuleBreakdown(),
                this.loadTimelinessData()
            ]);
            this.updateCharts();
            this.showSuccess('Data refreshed successfully!');
        } catch (error) {
            console.error('Error loading data:', error);
            this.showError('Failed to load completion data: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }

    async loadCompletionStats() {
        try {
            const response = await this.fetchData('get_completion_stats');
            this.updateCompletionStatsCards(response);
        } catch (error) {
            console.error('Error loading completion stats:', error);
            throw error;
        }
    }

    async loadModuleBreakdown() {
        try {
            const response = await this.fetchData('get_module_breakdown');
            this.moduleData = response;
        } catch (error) {
            console.error('Error loading module breakdown:', error);
            throw error;
        }
    }

    async loadTimelinessData() {
        try {
            const response = await this.fetchData('get_timeliness_data');
            this.timelinessData = response;
        } catch (error) {
            console.error('Error loading timeliness data:', error);
            throw error;
        }
    }

    async loadCourses() {
        try {
            const response = await this.fetchData('get_courses');
            this.updateCourseFilter(response);
        } catch (error) {
            console.error('Error loading courses:', error);
            throw error;
        }
    }

    async fetchData(action) {
        const params = new URLSearchParams({
            action: action,
            ...this.currentFilters
        });

        try {
            const response = await fetch(`api/completion_reports_api.php?${params}`);
            
            if (!response.ok) {
                if (response.status === 401) {
                    throw new Error('Session expired. Please log in again.');
                } else if (response.status === 403) {
                    throw new Error('Access denied. You do not have permission to view this data.');
                } else if (response.status === 500) {
                    throw new Error('Server error. Please try again later.');
                } else {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
            }

            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }

            return data;
        } catch (error) {
            console.error(`Error fetching ${action}:`, error);
            throw error;
        }
    }

    updateCompletionStatsCards(data) {
        // Update Overall Completion Rate
        const completionRateElement = document.querySelector('[data-metric="completion-rate"] .metric-value');
        if (completionRateElement) {
            const completionRate = data.completion_rate || data.overall_completion_rate || 0;
            completionRateElement.textContent = `${completionRate}%`;
        }

        // Update Average Progress
        const avgProgressElement = document.querySelector('[data-metric="avg-progress"] .metric-value');
        if (avgProgressElement) {
            const avgProgress = data.avg_progress || data.average_progress || 0;
            avgProgressElement.textContent = `${avgProgress}%`;
        }

        // Update enrollment counts
        const totalEnrollmentsElement = document.querySelector('[data-metric="total-enrollments"] .metric-value');
        if (totalEnrollmentsElement) {
            totalEnrollmentsElement.textContent = data.total_enrollments || 0;
        }

        const completedEnrollmentsElement = document.querySelector('[data-metric="completed-enrollments"] .metric-value');
        if (completedEnrollmentsElement) {
            completedEnrollmentsElement.textContent = data.completed_enrollments || 0;
        }
    }

    updateTimelinessCards(data) {
        // Update On-time Completions
        const onTimeElement = document.querySelector('[data-metric="on-time"] .metric-value');
        if (onTimeElement) {
            const onTimeCompletions = data.on_time_completions || 0;
            onTimeElement.textContent = onTimeCompletions;
        }

        // Update Delayed Completions
        const delayedElement = document.querySelector('[data-metric="delayed"] .metric-value');
        if (delayedElement) {
            const delayedCompletions = data.delayed_completions || 0;
            delayedElement.textContent = delayedCompletions;
        }

        // Update Not Completed
        const notCompletedElement = document.querySelector('[data-metric="not-completed"] .metric-value');
        if (notCompletedElement) {
            notCompletedElement.textContent = data.not_completed || 0;
        }
    }

    updateAverageProgressCard(data) {
        // Update Average Progress
        const avgProgressElement = document.querySelector('[data-metric="avg-progress"] .metric-value');
        if (avgProgressElement) {
            const avgProgress = data.avg_progress || 0;
            avgProgressElement.textContent = `${avgProgress}%`;
        }
    }

    updateCourseFilter(courses) {
        const courseSelect = document.getElementById('course_filter');
        if (!courseSelect) return;

        // Clear existing options except "All Courses"
        courseSelect.innerHTML = '<option value="">All Courses</option>';
        
        courses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.id;
            option.textContent = course.title;
            courseSelect.appendChild(option);
        });
    }

    updateModuleBreakdownTable(moduleData) {
        const tableBody = document.getElementById('moduleBreakdownTable');
        
        if (!tableBody || !moduleData || moduleData.length === 0) {
            return;
        }

        // Clear existing rows
        tableBody.innerHTML = '';

        moduleData.forEach((module, index) => {
            const row = document.createElement('tr');
            row.className = `border-b border-gray-100 hover:bg-gradient-to-r hover:from-gray-50 hover:to-blue-50 transition-all duration-200 ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}`;
            
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-600 rounded-lg flex items-center justify-center text-white font-semibold text-sm shadow-md">
                            ${module.title.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">${module.title}</div>
                            <div class="text-xs text-gray-500">Module ID: ${module.id}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-semibold text-gray-900">${module.total_enrollments || 0}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-semibold text-gray-900">${module.completed_enrollments || 0}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center space-x-3">
                        <div class="text-sm font-semibold text-gray-900">${module.completion_rate || 0}%</div>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2.5 shadow-inner">
                                <div class="bg-gradient-to-r from-purple-500 to-purple-600 h-2.5 rounded-full transition-all duration-500 ease-out" style="width: ${module.completion_rate || 0}%"></div>
                            </div>
                        </div>
                    </div>
                </td>
            `;
            
            tableBody.appendChild(row);
        });
    }

    initializeCharts() {
        this.createModuleCompletionChart();
        this.createTimelineChart();
        this.updateTimelinessCards(this.timelinessData);
        this.updateModuleBreakdownTable(this.moduleData);
        
        // Update average progress card if we have progress stats
        if (window.initialCompletionData && window.initialCompletionData.progress_stats) {
            this.updateAverageProgressCard(window.initialCompletionData.progress_stats);
        }
    }

    updateCharts() {
        if (this.charts.moduleCompletion) {
            this.charts.moduleCompletion.destroy();
        }
        if (this.charts.timeline) {
            this.charts.timeline.destroy();
        }
        this.initializeCharts();
    }

    createModuleCompletionChart() {
        const ctx = document.getElementById('moduleCompletionChart');
        if (!ctx) {
            return;
        }

        if (!this.moduleData || this.moduleData.length === 0) {
            // Show empty state
            ctx.style.display = 'none';
            const chartContainer = ctx.parentElement;
            if (chartContainer) {
                chartContainer.innerHTML = `
                    <div class="flex items-center justify-center h-64 text-gray-500">
                        <div class="text-center">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <p class="text-sm">No module data available</p>
                        </div>
                    </div>
                `;
            }
            return;
        }

        // Destroy existing chart if it exists
        if (this.charts.moduleCompletion) {
            this.charts.moduleCompletion.destroy();
        }

        this.charts.moduleCompletion = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: this.moduleData.map(m => m.title),
                datasets: [{
                    data: this.moduleData.map(m => parseFloat(m.completion_rate) || 0),
                    backgroundColor: [
                        '#f43f5e', '#e11d48', '#be123c', '#9f1239', '#881337',
                        '#3b82f6', '#2563eb', '#1d4ed8', '#1e40af', '#1e3a8a',
                        '#10b981', '#059669', '#047857', '#065f46', '#064e3b'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.parsed}%`;
                            }
                        }
                    }
                }
            }
        });
    }

    createTimelineChart() {
        const ctx = document.getElementById('completionTimelineChart');
        if (!ctx) {
            return;
        }

        if (!this.timelinessData) {
            // Show empty state
            ctx.style.display = 'none';
            const chartContainer = ctx.parentElement;
            if (chartContainer) {
                chartContainer.innerHTML = `
                    <div class="flex items-center justify-center h-64 text-gray-500">
                        <div class="text-center">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <p class="text-sm">No timeline data available</p>
                        </div>
                    </div>
                `;
            }
            return;
        }

        // Destroy existing chart if it exists
        if (this.charts.timeline) {
            this.charts.timeline.destroy();
        }

        this.charts.timeline = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['On-time', 'Delayed', 'Not Completed'],
                datasets: [{
                    label: 'Completions',
                    data: [
                        parseInt(this.timelinessData.on_time_completions) || 0,
                        parseInt(this.timelinessData.delayed_completions) || 0,
                        parseInt(this.timelinessData.not_completed) || 0
                    ],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b',
                        '#ef4444'
                    ],
                    borderWidth: 0,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.parsed.y} students`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f3f4f6'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    updateFilters() {
        this.currentFilters = {
            date_from: document.getElementById('date_from')?.value || '',
            date_to: document.getElementById('date_to')?.value || '',
            course_id: document.getElementById('course_filter')?.value || ''
        };
    }

    applyFilters() {
        this.loadData();
    }

    resetFilters() {
        // Reset to last 3 months
        const today = new Date();
        const threeMonthsAgo = new Date(today.getFullYear(), today.getMonth() - 3, 1);
        
        document.getElementById('date_from').value = threeMonthsAgo.toISOString().split('T')[0];
        document.getElementById('date_to').value = today.toISOString().split('T')[0];
        document.getElementById('course_filter').value = '';
        
        this.loadData();
    }

    async exportData() {
        this.showLoading(true);
        try {
            const [stats, modules, timeliness] = await Promise.all([
                this.fetchData('get_completion_stats'),
                this.fetchData('get_module_breakdown'),
                this.fetchData('get_timeliness_data')
            ]);

            const exportData = {
                export_date: new Date().toISOString(),
                filters: this.currentFilters,
                statistics: stats,
                module_breakdown: modules,
                timeliness_data: timeliness
            };

            // Create and download CSV
            this.downloadCSV(exportData);
            this.showSuccess('Data exported successfully!');
        } catch (error) {
            console.error('Error exporting data:', error);
            this.showError('Failed to export data: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }

    downloadCSV(data) {
        const csvContent = this.convertToCSV(data);
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `completion_reports_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    convertToCSV(data) {
        let csv = 'Completion Reports Export\n';
        csv += `Export Date: ${data.export_date}\n\n`;
        
        csv += 'Statistics\n';
        csv += `Overall Completion Rate,${data.statistics.overall_completion_rate}%\n`;
        csv += `Average Progress,${data.statistics.average_progress}%\n`;
        csv += `Total Enrollments,${data.statistics.total_enrollments}\n`;
        csv += `Completed Enrollments,${data.statistics.completed_enrollments}\n\n`;
        
        csv += 'Module Breakdown\n';
        csv += 'Module,Total Enrollments,Completed,Completion Rate\n';
        data.module_breakdown.forEach(module => {
            csv += `"${module.title}",${module.total_enrollments},${module.completed_enrollments},${module.completion_rate}%\n`;
        });
        
        csv += '\nTimeliness Data\n';
        csv += `On-time Completions,${data.timeliness_data.on_time_completions}\n`;
        csv += `Delayed Completions,${data.timeliness_data.delayed_completions}\n`;
        csv += `Not Completed,${data.timeliness_data.not_completed}\n`;
        
        return csv;
    }

    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.toggle('hidden', !show);
        }
    }

    showError(message) {
        // Create a more detailed error notification
        const errorDiv = document.createElement('div');
        errorDiv.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 max-w-md';
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <div class="font-semibold">Error</div>
                    <div class="text-sm opacity-90">${message}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        `;
        document.body.appendChild(errorDiv);
        
        // Auto-remove after 8 seconds
        setTimeout(() => {
            if (errorDiv.parentElement) {
                document.body.removeChild(errorDiv);
            }
        }, 8000);
    }

    showSuccess(message) {
        // Create a success notification
        const successDiv = document.createElement('div');
        successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 max-w-md';
        successDiv.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <div class="font-semibold">Success</div>
                    <div class="text-sm opacity-90">${message}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        `;
        document.body.appendChild(successDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (successDiv.parentElement) {
                document.body.removeChild(successDiv);
            }
        }, 5000);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.completionReportsManager = new CompletionReportsManager();
});
