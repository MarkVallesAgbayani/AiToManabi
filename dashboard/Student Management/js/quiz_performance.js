/**
 * Quiz Performance Analytics JavaScript
 * Handles interactive charts, data loading, and user interactions
 */

class QuizPerformanceManager {
    constructor() {
        this.charts = {};
        this.currentFilters = {
            date_from: document.getElementById('date_from')?.value || '',
            date_to: document.getElementById('date_to')?.value || '',
            student_id: document.getElementById('student_filter')?.value || '',
            quiz_id: document.getElementById('quiz_filter')?.value || ''
        };
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.initializeCharts();
        // Load data after charts are initialized
        setTimeout(() => {
            this.loadInitialData();
        }, 100);
    }
    
    setupEventListeners() {
        // Filter change events
        const filterElements = ['date_from', 'date_to', 'student_filter', 'quiz_filter'];
        filterElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', () => this.updateFilters());
            }
        });
        
        // Export button
        const exportBtn = document.querySelector('[onclick="exportData()"]');
        if (exportBtn) {
            exportBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.exportData();
            });
        }
        
        // Apply filters button
        const applyBtn = document.querySelector('[onclick="applyFilters()"]');
        if (applyBtn) {
            applyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }
        
        // Reset filters button
        const resetBtn = document.querySelector('[onclick="resetFilters()"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.resetFilters();
            });
        }
    }
    
    updateFilters() {
        this.currentFilters = {
            date_from: document.getElementById('date_from')?.value || '',
            date_to: document.getElementById('date_to')?.value || '',
            student_id: document.getElementById('student_filter')?.value || '',
            quiz_id: document.getElementById('quiz_filter')?.value || ''
        };
    }
    
    async loadInitialData() {
        try {
            // First test the database connection
            await this.testDatabaseConnection();
            await this.loadChartData();
        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showError('Failed to load initial data');
            // Show empty states for charts
            this.updatePerformanceTrendChart([]);
            this.updateQuizDifficultyChart([]);
        }
    }
    
    async testDatabaseConnection() {
        try {
            const result = await this.fetchData('test_connection');
            console.log('Database connection test:', result);
            if (result.success) {
                console.log('Quiz attempts in database:', result.data.quiz_attempts_count);
            }
        } catch (error) {
            console.error('Database connection test failed:', error);
        }
    }
    
    async loadChartData() {
        try {
            // Set a timeout for API calls
            const timeoutPromise = new Promise((_, reject) => 
                setTimeout(() => reject(new Error('API call timeout')), 10000)
            );
            
            // Load performance trend data
            const trendPromise = this.fetchData('get_performance_trend', {
                days: 30,
                ...this.currentFilters
            });
            
            const trendData = await Promise.race([trendPromise, timeoutPromise]);
            
            if (trendData.success) {
                this.updatePerformanceTrendChart(trendData.data || []);
            } else {
                console.error('Failed to load performance trend data:', trendData.error);
                this.updatePerformanceTrendChart([]);
            }
            
            // Load quiz difficulty data
            const difficultyPromise = this.fetchData('get_quiz_difficulty', this.currentFilters);
            const difficultyData = await Promise.race([difficultyPromise, timeoutPromise]);
            
            if (difficultyData.success) {
                this.updateQuizDifficultyChart(difficultyData.data || []);
            } else {
                console.error('Failed to load quiz difficulty data:', difficultyData.error);
                this.updateQuizDifficultyChart([]);
            }
            
        } catch (error) {
            console.error('Error loading chart data:', error);
            // Show empty states for charts
            this.updatePerformanceTrendChart([]);
            this.updateQuizDifficultyChart([]);
        }
    }
    
    async fetchData(action, params = {}) {
        // Get the current directory path
        const currentPath = window.location.pathname;
        const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
        const url = new URL('api/quiz_performance_api.php', window.location.origin + basePath + '/');
        url.searchParams.append('action', action);
        
        Object.keys(params).forEach(key => {
            if (params[key]) {
                url.searchParams.append(key, params[key]);
            }
        });
        
        console.log('Fetching data from:', url.toString()); // Debug log
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('API Response Text:', text); // Debug log
        
        try {
            const data = JSON.parse(text);
            console.log('API Response Data:', data); // Debug log
            return data;
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response Text:', text);
            throw new Error('Invalid JSON response from server');
        }
    }
    
    initializeCharts() {
        // Initialize Performance Trend Chart
        const trendCtx = document.getElementById('performanceTrendChart');
        if (trendCtx) {
            this.charts.performanceTrend = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: ['Loading...'],
                    datasets: [{
                        label: 'Average Score (%)',
                        data: [0],
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Total Attempts',
                        data: [0],
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Average Score (%)'
                            },
                            min: 0,
                            max: 100
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Total Attempts'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            min: 0
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Performance Trend Over Time'
                        }
                    }
                }
            });
        }
        
        // Initialize Quiz Difficulty Chart
        const difficultyCtx = document.getElementById('quizDifficultyChart');
        if (difficultyCtx) {
            this.charts.quizDifficulty = new Chart(difficultyCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Easy (80%+)', 'Medium (60-79%)', 'Hard (<60%)'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(251, 191, 36, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgb(34, 197, 94)',
                            'rgb(251, 191, 36)',
                            'rgb(239, 68, 68)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: true,
                            text: 'Quiz Difficulty Distribution'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    return label + ': ' + value + ' quizzes';
                                }
                            }
                        }
                    }
                }
            });
        }
    }
    
    updatePerformanceTrendChart(data) {
        if (!this.charts.performanceTrend) return;
        
        console.log('Updating Performance Trend Chart with data:', data);
        
        if (!data || data.length === 0) {
            // Show empty state with informative message
            this.charts.performanceTrend.data.labels = ['No Quiz Attempts Found'];
            this.charts.performanceTrend.data.datasets[0].data = [0];
            this.charts.performanceTrend.data.datasets[1].data = [0];
            
            // Update chart title to indicate no data
            this.charts.performanceTrend.options.plugins.title.text = 'Performance Trend Over Time - No Data Available';
        } else {
            // Sort data by date (oldest first for proper line chart display)
            const sortedData = data.sort((a, b) => new Date(a.date) - new Date(b.date));
            
            const labels = sortedData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            
            const scores = sortedData.map(item => parseFloat(item.average_score) || 0);
            const attempts = sortedData.map(item => parseInt(item.total_attempts) || 0);
            
            console.log('Chart labels:', labels);
            console.log('Chart scores:', scores);
            console.log('Chart attempts:', attempts);
            
            this.charts.performanceTrend.data.labels = labels;
            this.charts.performanceTrend.data.datasets[0].data = scores;
            this.charts.performanceTrend.data.datasets[1].data = attempts;
            
            // Reset chart title
            this.charts.performanceTrend.options.plugins.title.text = 'Performance Trend Over Time';
        }
        
        this.charts.performanceTrend.update();
    }
    
    updateQuizDifficultyChart(data) {
        if (!this.charts.quizDifficulty) return;
        
        console.log('Updating Quiz Difficulty Chart with data:', data);
        
        if (!data || data.length === 0) {
            // Show empty state
            this.charts.quizDifficulty.data.datasets[0].data = [0, 0, 0];
            this.charts.quizDifficulty.options.plugins.title.text = 'Quiz Difficulty Distribution - No Data Available';
        } else {
            const difficultyCounts = {
                'Easy': 0,
                'Medium': 0,
                'Hard': 0
            };
            
            data.forEach(quiz => {
                if (quiz.difficulty_level && difficultyCounts.hasOwnProperty(quiz.difficulty_level)) {
                    difficultyCounts[quiz.difficulty_level]++;
                }
            });
            
            console.log('Difficulty counts:', difficultyCounts);
            
            this.charts.quizDifficulty.data.datasets[0].data = [
                difficultyCounts['Easy'],
                difficultyCounts['Medium'],
                difficultyCounts['Hard']
            ];
            
            // Reset chart title
            this.charts.quizDifficulty.options.plugins.title.text = 'Quiz Difficulty Distribution';
        }
        
        this.charts.quizDifficulty.update();
    }
    
    async applyFilters() {
        this.showLoading();
        this.updateFilters();
        
        try {
            await this.loadChartData();
            await this.refreshTables();
            this.hideLoading();
            this.showSuccess('Filters applied successfully');
        } catch (error) {
            console.error('Error applying filters:', error);
            this.hideLoading();
            this.showError('Failed to apply filters');
        }
    }
    
    async refreshTables() {
        try {
            // Refresh top performers
            const performersData = await this.fetchData('get_top_performers', {
                limit: 10,
                ...this.currentFilters
            });
            
            if (performersData.success) {
                this.updateTopPerformersTable(performersData.data);
            }
            
            // Refresh recent attempts
            const attemptsData = await this.fetchData('get_recent_attempts', {
                limit: 5,
                page: 1,
                ...this.currentFilters
            });
            
            if (attemptsData.success) {
                this.updateRecentAttemptsTable(attemptsData.data.data, attemptsData.data.pagination);
            }
            
        } catch (error) {
            console.error('Error refreshing tables:', error);
        }
    }
    
    updateTopPerformersTable(data) {
        const tbody = document.querySelector('.bg-white tbody');
        if (!tbody) return;
        
        tbody.innerHTML = data.map(performer => `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    ${performer.username}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getScoreBadgeClass(performer.average_score, performer.average_total_points)}">
                        ${this.formatScoreDisplay(performer.average_score, performer.average_total_points)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${performer.total_attempts}
                </td>
            </tr>
        `).join('');
    }
    
    updateRecentAttemptsTable(data, pagination = null) {
        const tables = document.querySelectorAll('.bg-white tbody');
        const attemptsTable = tables[tables.length - 1]; // Get the last table (recent attempts)
        
        if (!attemptsTable) return;
        
        attemptsTable.innerHTML = data.map(attempt => `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    ${attempt.username}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${attempt.quiz_title}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getScoreBadgeClass(attempt.score, attempt.total_points)}">
                        ${this.formatScoreDisplay(attempt.score, attempt.total_points)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${new Date(attempt.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                </td>
            </tr>
        `).join('');
        
        // Update pagination if provided
        if (pagination) {
            this.updatePaginationControls(pagination);
        }
    }
    
    updatePaginationControls(pagination) {
        // This function would update pagination controls if they exist
        // For now, we'll just log the pagination info
        console.log('Pagination info:', pagination);
    }
    
    getScoreBadgeClass(score, totalPoints = null) {
        let percentage = score;
        if (totalPoints && totalPoints > 0) {
            percentage = (score / totalPoints) * 100;
        }
        
        if (percentage >= 80) return 'bg-green-100 text-green-800';
        if (percentage >= 60) return 'bg-yellow-100 text-yellow-800';
        return 'bg-red-100 text-red-800';
    }
    
    formatScoreDisplay(score, totalPoints = null) {
        if (totalPoints && totalPoints > 0) {
            return `${Math.round(parseFloat(score))}/${Math.round(parseFloat(totalPoints))}`;
        }
        return Math.round(parseFloat(score));
    }
    
    resetFilters() {
        // Reset date inputs to current month
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        
        document.getElementById('date_from').value = firstDay.toISOString().split('T')[0];
        document.getElementById('date_to').value = today.toISOString().split('T')[0];
        document.getElementById('student_filter').value = '';
        document.getElementById('quiz_filter').value = '';
        
        this.applyFilters();
    }
    
    async exportData() {
        this.showLoading();
        
        try {
            // Build URL with current filters for PDF export
            const url = new URL(window.location.href);
            url.searchParams.set('export', 'pdf');
            
            // Add current filters to URL
            Object.keys(this.currentFilters).forEach(key => {
                if (this.currentFilters[key]) {
                    url.searchParams.set(key, this.currentFilters[key]);
                }
            });
            
            // Create a temporary link to download the PDF
            const link = document.createElement('a');
            link.href = url.toString();
            link.download = `quiz_performance_${new Date().toISOString().split('T')[0]}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            this.hideLoading();
            this.showSuccess('PDF export completed successfully');
            
        } catch (error) {
            console.error('Error exporting data:', error);
            this.hideLoading();
            this.showError('Failed to export PDF');
        }
    }
    
    showLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.remove('hidden');
        }
    }
    
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.add('hidden');
        }
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
}

// Global functions for onclick handlers
function applyFilters() {
    if (window.quizPerformanceManager) {
        window.quizPerformanceManager.applyFilters();
    }
}

function resetFilters() {
    if (window.quizPerformanceManager) {
        window.quizPerformanceManager.resetFilters();
    }
}

function exportData() {
    if (window.quizPerformanceManager) {
        window.quizPerformanceManager.exportData();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.quizPerformanceManager = new QuizPerformanceManager();
});

// Handle window resize for charts
window.addEventListener('resize', function() {
    if (window.quizPerformanceManager && window.quizPerformanceManager.charts) {
        Object.values(window.quizPerformanceManager.charts).forEach(chart => {
            if (chart && chart.resize) {
                chart.resize();
            }
        });
    }
});
