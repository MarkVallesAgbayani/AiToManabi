// Progress Tracking JavaScript

// Global variables
let progressChart = null;
let moduleChart = null;
let progressData = [];
let filteredData = [];

// Pagination variables
let progressCurrentPage = 1;
let progressItemsPerPage = 5;

// Search Filter sa Detailed progress tracking
let currentSearchQuery = '';

// Initialize progress tracking
function initializeProgressTracking() {
    initializeProgressCharts();
    loadProgressData();
    loadCourseFilter();
    initializeProgressEventListeners();
}

// Initialize event listeners for progress tracking
function initializeProgressEventListeners() {
    // Filter event listeners
    const courseFilter = document.getElementById('course-filter');
    const progressFilter = document.getElementById('progress-filter');
    const sortFilter = document.getElementById('sort-filter');
    const refreshBtn = document.getElementById('refresh-btn');
    const exportBtn = document.getElementById('export-progress-btn');
    
    // ADD SEARCH INPUT LISTENER:
    const searchInput = document.getElementById('progress-search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearchQuery = e.target.value.trim().toLowerCase();
                filterAndDisplayProgress();
            }, 300); // Debounce for 300ms
        });
    }

    if (courseFilter) {
        courseFilter.addEventListener('change', handleProgressFilter);
    }
    
    if (progressFilter) {
        progressFilter.addEventListener('change', handleProgressFilter);
    }
    
    if (sortFilter) {
        sortFilter.addEventListener('change', handleProgressFilter);
    }
    
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            loadProgressData();
            loadCourseFilter();
        });
    }
    
    if (exportBtn) {
        exportBtn.addEventListener('click', exportProgressData);
    }
}

// Load progress data
function loadProgressData() {
    console.log('Loading progress data...');
    
    fetch('api/get_all_student_progress.php')
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get as text first to see raw response
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    console.log('Progress data loaded successfully:', data);
                    progressData = data.progress || [];
                    updateProgressOverview(data.overview || {});
                    filterAndDisplayProgress();
                    updateProgressCharts();
                } else {
                    console.error('Failed to load progress data:', data.message);
                    displayProgressError(data.message || 'Unknown error');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                displayProgressError('Invalid response from server');
            }
        })
        .catch(error => {
            console.error('Error loading progress data:', error);
            displayProgressError('Network error: ' + error.message);
        });
}

// Load course filter options
function loadCourseFilter() {
    console.log('Loading course filter options...');
    
    fetch('api/get_teacher_courses.php')
        .then(response => {
            console.log('Course filter response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Course filter raw response:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    console.log('Courses loaded successfully:', data);
                    populateCourseFilter(data.courses || []);
                } else {
                    console.error('Failed to load courses for filter:', data.message);
                }
            } catch (e) {
                console.error('JSON parse error for courses:', e);
                console.error('Response text:', text);
            }
        })
        .catch(error => {
            console.error('Error loading courses for filter:', error);
        });
}

// Populate course filter dropdown
function populateCourseFilter(courses) {
    const courseFilter = document.getElementById('course-filter');
    if (!courseFilter) return;
    
    // Keep the "All Courses" option and add courses
    let options = '<option value="all">All Modules</option>';
    courses.forEach(course => {
        options += `<option value="${course.id}">${escapeHtml(course.title)}</option>`;
    });
    
    courseFilter.innerHTML = options;
}

// Update progress overview cards
function updateProgressOverview(overview) {
    const completedModules = document.getElementById('completed-modules');
    const inProgressModules = document.getElementById('in-progress-modules');
    const avgProgress = document.getElementById('avg-progress');
    const activeStudents = document.getElementById('active-students');
    
    if (completedModules) completedModules.textContent = overview.completed_modules || 0;
    if (inProgressModules) inProgressModules.textContent = overview.in_progress_modules || 0;
    if (avgProgress) avgProgress.textContent = (overview.average_progress || 0) + '%';
    if (activeStudents) activeStudents.textContent = overview.active_students || 0;
}

// Handle progress filter changes
function handleProgressFilter() {
    filterAndDisplayProgress();
}

// Filter and display progress data
function filterAndDisplayProgress() {
    const courseFilter = document.getElementById('course-filter');
    const progressFilter = document.getElementById('progress-filter');
    const sortFilter = document.getElementById('sort-filter');
    
    let filtered = [...progressData];
    
    // ADD SEARCH FILTER:
    if (currentSearchQuery) {
        filtered = filtered.filter(item => {
            const studentName = (item.student_name || '').toLowerCase();
            const studentDisplayName = (item.student_display_name || '').toLowerCase();
            const courseTitle = (item.course_title || '').toLowerCase();
            const username = (item.username || '').toLowerCase();
            
            return studentName.includes(currentSearchQuery) ||
                   studentDisplayName.includes(currentSearchQuery) ||
                   courseTitle.includes(currentSearchQuery) ||
                   username.includes(currentSearchQuery);
        });
    }
    
    // Apply course filter
    if (courseFilter && courseFilter.value !== 'all') {
        const courseId = parseInt(courseFilter.value);
        filtered = filtered.filter(item => item.course_id === courseId);
    }
    
    // Apply progress filter
    if (progressFilter && progressFilter.value !== 'all') {
        const progressType = progressFilter.value;
        filtered = filtered.filter(item => {
            const progress = parseFloat(item.progress_percentage) || 0;
            switch (progressType) {
                case 'completed':
                    return progress >= 100;
                case 'in_progress':
                    return progress > 0 && progress < 100;
                case 'not_started':
                    return progress === 0;
                default:
                    return true;
            }
        });
    }
    
    // Apply sorting
    if (sortFilter && sortFilter.value) {
        const sortType = sortFilter.value;
        filtered.sort((a, b) => {
            switch (sortType) {
                case 'progress_desc':
                    return (parseFloat(b.progress_percentage) || 0) - (parseFloat(a.progress_percentage) || 0);
                case 'progress_asc':
                    return (parseFloat(a.progress_percentage) || 0) - (parseFloat(b.progress_percentage) || 0);
                case 'name_asc':
                    return (a.student_name || '').localeCompare(b.student_name || '');
                case 'activity_desc':
                    return new Date(b.last_activity || 0) - new Date(a.last_activity || 0);
                default:
                    return 0;
            }
        });
    }
    
    // Reset pagination to first page when filters change
    progressCurrentPage = 1;
    filteredData = filtered;
    displayProgressTable(filtered);
}


// Display progress table with pagination
function displayProgressTable(data) {
    const tableBody = document.getElementById('progress-table-body');
    if (!tableBody) return;
    
    if (data.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8">
                    <div class="text-gray-500">
                        <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-4 text-gray-300"></i>
                        <p>No progress data found</p>
                    </div>
                </td>
            </tr>
        `;
        lucide.createIcons();
        updatePaginationControls(0);
        return;
    }
    
    // Calculate pagination
    progressTotalPages = Math.ceil(data.length / progressItemsPerPage);
    if (progressCurrentPage > progressTotalPages) {
        progressCurrentPage = 1;
    }
    
    // Get current page data
    const startIndex = (progressCurrentPage - 1) * progressItemsPerPage;
    const endIndex = startIndex + progressItemsPerPage;
    const currentPageData = data.slice(startIndex, endIndex);
    
    let html = '';
    currentPageData.forEach(item => {
        const progress = parseFloat(item.progress_percentage) || 0;
        const progressClass = getProgressClass(progress);
        const lastActivity = formatDate(item.last_activity);
        const displayName = item.student_display_name || item.student_name;
        
        // Status badge
        let statusBadge = '';
        switch (item.status) {
            case 'completed':
                statusBadge = '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Completed</span>';
                break;
            case 'in_progress':
                statusBadge = '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">In Progress</span>';
                break;
            default:
                statusBadge = '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">Not Started</span>';
        }
        
        html += `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        ${item.profile_picture ? 
                            `<img src="../../uploads/profile_pictures/${escapeHtml(item.profile_picture)}" 
                                 class="w-10 h-10 rounded-full object-cover mr-3 shadow-md" 
                                 alt="Profile Picture"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                             <div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-medium text-sm mr-3 shadow-md" style="display: none;">
                                 ${getInitials(displayName)}
                             </div>` :
                            `<div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-medium text-sm mr-3 shadow-md">
                                 ${getInitials(displayName)}
                             </div>`
                        }
                        <div>
                            <div class="font-medium text-gray-900">${escapeHtml(displayName)}</div>
                            <div class="text-sm text-gray-500">${maskEmail(item.student_email)}</div>
                            ${statusBadge}
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900">${escapeHtml(item.course_title)}</div>
                    ${item.level ? `<div class="text-sm text-gray-500 capitalize">${escapeHtml(item.level)}</div>` : ''}
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900" id="current-section-${item.student_id}-${item.course_id}">${escapeHtml(item.current_section || item.current_module || 'Not started')}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="w-full bg-gray-200 rounded-full h-2 mr-3">
                            <div class="bg-gradient-to-r ${progressClass} h-2 rounded-full transition-all duration-300" style="width: ${progress}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900 min-w-12">${progress.toFixed(1)}%</span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="text-sm text-gray-900">${item.completed_modules || 0}/${item.total_modules || 0}</span>
                    ${item.quiz_attempts ? `<div class="text-xs text-gray-500">${item.quiz_attempts} quiz attempts</div>` : ''}
                </td>
                <td class="px-6 py-4">
                    <span class="text-sm text-gray-500">${lastActivity}</span>
                </td>
                <td class="px-6 py-4">
                    <button onclick="viewStudentProgress(${item.student_id})" 
                            class="text-primary-600 hover:text-primary-900 text-sm font-medium hover:underline transition-colors">
                        View Details
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    lucide.createIcons();
    updatePaginationControls(data.length);
}

// Update pagination controls
function updatePaginationControls(totalItems) {
    const paginationContainer = document.getElementById('progress-pagination-container');
    if (!paginationContainer) return;
    
    if (totalItems === 0 || progressTotalPages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    const startItem = (progressCurrentPage - 1) * progressItemsPerPage + 1;
    const endItem = Math.min(progressCurrentPage * progressItemsPerPage, totalItems);
    
    let paginationHtml = `
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Showing <span class="font-medium">${startItem}</span> to <span class="font-medium">${endItem}</span> of <span class="font-medium">${totalItems}</span> results
            </div>
            <div class="flex items-center space-x-2">
    `;
    
    // Previous button
    if (progressCurrentPage > 1) {
        paginationHtml += `
            <button onclick="goToProgressPage(${progressCurrentPage - 1})" 
                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 transition-colors">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
        `;
    } else {
        paginationHtml += `
            <button disabled 
                    class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
        `;
    }
    
    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, progressCurrentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(progressTotalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    // First page and ellipsis
    if (startPage > 1) {
        paginationHtml += `
            <button onclick="goToProgressPage(1)" 
                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 transition-colors">
                1
            </button>
        `;
        if (startPage > 2) {
            paginationHtml += `
                <span class="px-3 py-2 text-sm font-medium text-gray-500">...</span>
            `;
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        if (i === progressCurrentPage) {
            paginationHtml += `
                <button class="px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-primary-600 rounded-md">
                    ${i}
                </button>
            `;
        } else {
            paginationHtml += `
                <button onclick="goToProgressPage(${i})" 
                        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 transition-colors">
                    ${i}
                </button>
            `;
        }
    }
    
    // Last page and ellipsis
    if (endPage < progressTotalPages) {
        if (endPage < progressTotalPages - 1) {
            paginationHtml += `
                <span class="px-3 py-2 text-sm font-medium text-gray-500">...</span>
            `;
        }
        paginationHtml += `
            <button onclick="goToProgressPage(${progressTotalPages})" 
                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 transition-colors">
                ${progressTotalPages}
            </button>
        `;
    }
    
    // Next button
    if (progressCurrentPage < progressTotalPages) {
        paginationHtml += `
            <button onclick="goToProgressPage(${progressCurrentPage + 1})" 
                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 transition-colors">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        `;
    } else {
        paginationHtml += `
            <button disabled 
                    class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        `;
    }
    
    paginationHtml += `
            </div>
        </div>
    `;
    
    paginationContainer.innerHTML = paginationHtml;
    lucide.createIcons();
}

// Navigate to specific page
function goToProgressPage(page) {
    if (page >= 1 && page <= progressTotalPages && page !== progressCurrentPage) {
        progressCurrentPage = page;
        displayProgressTable(filteredData);
    }
}

// Get progress class for styling
function getProgressClass(progress) {
    if (progress >= 100) return 'from-green-400 to-green-600';
    if (progress >= 75) return 'from-blue-400 to-blue-600';
    if (progress >= 50) return 'from-yellow-400 to-yellow-600';
    if (progress >= 25) return 'from-orange-400 to-orange-600';
    return 'from-red-400 to-red-600';
}

// Initialize progress charts
function initializeProgressCharts() {
    initializeProgressDistributionChart();
    initializeModuleCompletionChart();
}

// Initialize progress distribution chart
function initializeProgressDistributionChart() {
    const ctx = document.getElementById('progressChart');
    if (!ctx) return;
    
    if (progressChart) {
        progressChart.destroy();
    }
    
    progressChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['0-25%', '26-50%', '51-75%', '76-99%', '100%'],
            datasets: [{
                label: 'Number of Students',
                data: [0, 0, 0, 0, 0],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(34, 197, 94, 0.8)'
                ],
                borderColor: [
                    'rgb(239, 68, 68)',
                    'rgb(245, 158, 11)',
                    'rgb(59, 130, 246)',
                    'rgb(16, 185, 129)',
                    'rgb(34, 197, 94)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Initialize module completion chart
function initializeModuleCompletionChart() {
    const ctx = document.getElementById('moduleChart');
    if (!ctx) return;
    
    if (moduleChart) {
        moduleChart.destroy();
    }
    
    moduleChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'In Progress', 'Not Started'],
            datasets: [{
                data: [0, 0, 0],
                backgroundColor: [
                    'rgba(34, 197, 94, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(156, 163, 175, 0.8)'
                ],
                borderColor: [
                    'rgb(34, 197, 94)',
                    'rgb(59, 130, 246)',
                    'rgb(156, 163, 175)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Update progress charts with data
function updateProgressCharts() {
    updateProgressDistributionChart();
    updateModuleCompletionChart();
}

// Update progress distribution chart
function updateProgressDistributionChart() {
    if (!progressChart || !progressData) return;
    
    const distribution = [0, 0, 0, 0, 0];
    
    progressData.forEach(item => {
        const progress = parseFloat(item.progress_percentage) || 0;
        if (progress === 100) {
            distribution[4]++;
        } else if (progress >= 76) {
            distribution[3]++;
        } else if (progress >= 51) {
            distribution[2]++;
        } else if (progress >= 26) {
            distribution[1]++;
        } else {
            distribution[0]++;
        }
    });
    
    progressChart.data.datasets[0].data = distribution;
    progressChart.update();
}

// Update module completion chart
function updateModuleCompletionChart() {
    if (!moduleChart || !progressData) return;
    
    let completed = 0;
    let inProgress = 0;
    let notStarted = 0;
    
    progressData.forEach(item => {
        const progress = parseFloat(item.progress_percentage) || 0;
        if (progress === 100) {
            completed++;
        } else if (progress > 0) {
            inProgress++;
        } else {
            notStarted++;
        }
    });
    
    moduleChart.data.datasets[0].data = [completed, inProgress, notStarted];
    moduleChart.update();
}

// View individual student progress
function viewStudentProgress(studentId) {
    fetch(`api/get_student_progress.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showProgressDetailModal(data);
            } else {
                alert('Failed to load student progress: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading student progress:', error);
            alert('Error loading student progress');
        });
}

// Show progress detail modal
function showProgressDetailModal(data) {
    const modal = document.getElementById('progress-detail-modal');
    const content = document.getElementById('progress-detail-content');
    
    if (!modal || !content) return;
    
    const student = data.student;
    const progress = data.progress || [];
    
    let progressHtml = '';
    let totalProgress = 0;
    let totalQuizAttempts = 0;
    
    progress.forEach(course => {
        const progressPercent = parseFloat(course.completion_percentage) || 0;
        const progressClass = getProgressClass(progressPercent);
        totalProgress += progressPercent;
        totalQuizAttempts += course.quiz_attempts || 0;
        
        // Status icon
        let statusIcon = 'clock';
        let statusColor = 'text-yellow-500';
        if (course.status === 'completed') {
            statusIcon = 'check-circle';
            statusColor = 'text-green-500';
        } else if (course.status === 'not_started') {
            statusIcon = 'circle';
            statusColor = 'text-gray-400';
        }
        
        progressHtml += `
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="${statusIcon}" class="w-4 h-4 ${statusColor}"></i>
                        <h4 class="font-medium text-gray-900">${escapeHtml(course.course_title)}</h4>
                        ${course.level ? `<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full capitalize">${course.level}</span>` : ''}
                    </div>
                    <span class="text-sm font-medium text-gray-600">${progressPercent.toFixed(1)}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 mb-3">
                    <div class="bg-gradient-to-r ${progressClass} h-2 rounded-full transition-all duration-300" style="width: ${progressPercent}%"></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Modules:</span>
                        <span class="font-medium ml-1">${course.completed_modules}/${course.total_modules}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Quiz Avg:</span>
                        <span class="font-medium ml-1">${course.quiz_average === 'N/A' ? 'N/A' : course.quiz_average + '%'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Attempts:</span>
                        <span class="font-medium ml-1">${course.quiz_attempts || 0}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Enrolled:</span>
                        <span class="font-medium ml-1">${formatDate(course.enrollment_date)}</span>
                    </div>
                </div>
                ${course.last_activity ? `
                    <div class="mt-2 text-xs text-gray-500">
                        Last activity: ${formatDate(course.last_activity)}
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    const averageProgress = progress.length > 0 ? (totalProgress / progress.length).toFixed(1) : 0;
    const displayName = student.display_name || student.username;
    
    content.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-center items-center bg-gradient-to-r from-primary-50 to-blue-50 relative">
                <h3 class="text-lg font-semibold text-gray-900">Student Progress Details</h3>
                <button onclick="closeProgressModal()" class="text-gray-400 hover:text-gray-600 transition-colors absolute right-6">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6">
                <!-- Student Info Header -->
                <div class="mb-6 bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg p-4">
                    <div class="flex items-center mb-4">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-xl mr-4 shadow-lg">
                            ${getInitials(displayName)}
                        </div>
                        <div class="flex-1">
                            <h4 class="text-xl font-semibold text-gray-900">${escapeHtml(displayName)}</h4>
                            <p class="text-gray-600">${escapeHtml(student.email)}</p>
                            ${student.joined_date ? `<p class="text-sm text-gray-500">Joined: ${formatDate(student.joined_date)}</p>` : ''}
                        </div>
                    </div>
                    
                    <!-- Summary Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary-600">${progress.length}</div>
                            <div class="text-sm text-gray-500">Enrolled Courses</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">${averageProgress}%</div>
                            <div class="text-sm text-gray-500">Avg Progress</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">${totalQuizAttempts}</div>
                            <div class="text-sm text-gray-500">Quiz Attempts</div>
                        </div>
                    </div>
                </div>
                
                <!-- Course Progress -->
                <div class="space-y-4">
                    <h5 class="font-medium text-gray-900 mb-3 flex items-center">
                        <i data-lucide="book-open" class="w-5 h-5 mr-2 text-primary-600"></i>
                        Course Progress
                    </h5>
                    ${progressHtml || '<p class="text-gray-500 text-center py-8">No course progress data available</p>'}
                </div>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    lucide.createIcons();
}

// Close progress modal
function closeProgressModal() {
    const modal = document.getElementById('progress-detail-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Export progress data
function exportProgressData() {
    if (!filteredData || filteredData.length === 0) {
        alert('No data to export');
        return;
    }

    // Get current filter values
    const courseFilter = document.getElementById('course-filter')?.value || 'all';
    const progressFilter = document.getElementById('progress-filter')?.value || 'all';
    const sortFilter = document.getElementById('sort-filter')?.value || 'progress_desc';
    const searchQuery = document.getElementById('progress-search')?.value.trim() || '';

    // Show loading indicator
    const exportBtn = document.getElementById('export-progress-btn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Generating PDF...';
    exportBtn.disabled = true;

    // Prepare export URL with filters
    const params = new URLSearchParams({
        export: 'pdf',
        course_filter: courseFilter,
        progress_filter: progressFilter,
        sort_filter: sortFilter,
        search: searchQuery
    });

    // Create download link
    const downloadUrl = `progress_tracking.php?${params.toString()}`;

    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Reset button after a short delay
    setTimeout(() => {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        lucide.createIcons(); // Reinitialize icons
    }, 2000);
}

// Display error state
function displayProgressError(message = 'Failed to load progress data') {
    const tableBody = document.getElementById('progress-table-body');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8">
                    <div class="text-red-500">
                        <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4"></i>
                        <p class="font-medium">${message}</p>
                        <button onclick="loadProgressData()" class="mt-2 text-primary-600 hover:text-primary-800 underline">
                            Try Again
                        </button>
                        <br>
                        <button onclick="debugSession()" class="mt-2 text-blue-600 hover:text-blue-800 underline text-sm">
                            Debug Session
                        </button>
                    </div>
                </td>
            </tr>
        `;
        lucide.createIcons();
    }
    
    // Also update the overview cards to show error state
    updateProgressOverview({
        total_students: 0,
        completed_modules: 0,
        in_progress_modules: 0,
        active_students: 0,
        average_progress: 0
    });
}

// Debug session function
function debugSession() {
    fetch('api/debug_session.php')
        .then(response => response.text())
        .then(text => {
            console.log('Debug session response:', text);
            try {
                const data = JSON.parse(text);
                alert('Debug info: ' + data.message);
                if (data.success) {
                    // Try loading data again
                    loadProgressData();
                    loadCourseFilter();
                }
            } catch (e) {
                console.error('Debug session parse error:', e);
                alert('Debug response: ' + text);
            }
        })
        .catch(error => {
            console.error('Debug session error:', error);
            alert('Debug error: ' + error.message);
        });
}

// Utility functions
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}

function formatDate(dateString) {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Mask email address for privacy
function maskEmail(email) {
    if (!email) return '';
    const [localPart, domain] = email.split('@');
    if (!localPart || !domain) return email;
    
    // Keep first 2 characters and last character of local part, mask the rest
    const maskedLocal = localPart.length <= 3 
        ? localPart.charAt(0) + '*'.repeat(localPart.length - 1)
        : localPart.charAt(0) + localPart.charAt(1) + '*'.repeat(localPart.length - 3) + localPart.charAt(localPart.length - 1);
    
    // Keep first part of domain and mask the rest
    const domainParts = domain.split('.');
    const maskedDomain = domainParts[0].charAt(0) + '*'.repeat(domainParts[0].length - 1) + '.' + domainParts.slice(1).join('.');
    
    return maskedLocal + '@' + maskedDomain;
}

// Update current section in real-time
function updateCurrentSections() {
    if (!filteredData || filteredData.length === 0) return;
    
    // Get unique student-course combinations
    const studentCourses = [...new Set(filteredData.map(item => `${item.student_id}-${item.course_id}`))];
    
    studentCourses.forEach(studentCourse => {
        const [studentId, courseId] = studentCourse.split('-');
        
        // Fetch current section for this student-course combination
        fetch(`api/get_current_section.php?student_id=${studentId}&course_id=${courseId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.current_section) {
                    const sectionElement = document.getElementById(`current-section-${studentId}-${courseId}`);
                    if (sectionElement) {
                        sectionElement.textContent = data.current_section;
                    }
                }
            })
            .catch(error => {
                console.log('Error updating current section:', error);
            });
    });
}

// Functions are initialized from the PHP file to avoid conflicts
