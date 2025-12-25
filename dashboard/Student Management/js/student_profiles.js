// Student Profiles specific JavaScript

// Data masking functions for privacy protection
function maskEmail(email) {
    if (!email || email.trim() === '') return 'Not provided';
    const parts = email.split('@');
    if (parts.length !== 2) return email;
    
    const username = parts[0];
    const domain = parts[1];
    
    if (username.length <= 2) {
        const maskedUsername = '*'.repeat(username.length);
        return maskedUsername + '@' + domain;
    } else {
        const maskedUsername = username.substring(0, 2) + '*'.repeat(username.length - 2);
        return maskedUsername + '@' + domain;
    }
}

// Phone removed from listing

function maskAge(age) {
    if (!age || age === '') return 'Not provided';
    const ageNum = parseInt(age);
    if (isNaN(ageNum)) return 'Not provided';
    
    // Show age range instead of exact age for privacy
    if (ageNum < 18) return 'Under 18';
    if (ageNum < 25) return '18-24';
    if (ageNum < 35) return '25-34';
    if (ageNum < 45) return '35-44';
    if (ageNum < 55) return '45-54';
    return '55+';
}

function maskLastLogin(lastLogin) {
    if (!lastLogin || lastLogin.trim() === '') return 'Never';
    
    const loginDate = new Date(lastLogin);
    const now = new Date();
    const diffInMs = now - loginDate;
    const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
    
    if (diffInDays === 0) return 'Today';
    if (diffInDays === 1) return 'Yesterday';
    if (diffInDays < 7) return diffInDays + ' days ago';
    if (diffInDays < 30) return Math.floor(diffInDays / 7) + ' weeks ago';
    if (diffInDays < 365) return Math.floor(diffInDays / 30) + ' months ago';
    return 'Over a year ago';
}

// Global variables
let currentPage = 1;
let currentSearch = '';
let currentFilter = 'all';
let currentCourseFilter = 'all';
let currentProgressFilter = 'all';
let currentEnrollmentFilter = 'all';
let currentActivityFilter = 'all';
let currentSortBy = 'name';

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    loadCourseFilter();
    loadStudents();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }
    
    // Course filter
    const courseFilter = document.getElementById('courseFilter');
    if (courseFilter) {
        courseFilter.addEventListener('change', function() {
            currentCourseFilter = this.value;
            currentPage = 1;
            loadStudents();
        });
    }
    
    // Progress filter
    const progressFilter = document.getElementById('progressFilter');
    if (progressFilter) {
        progressFilter.addEventListener('change', function() {
            currentProgressFilter = this.value;
            currentPage = 1;
            loadStudents();
        });
    }
    
    // Enrollment filter
    const enrollmentFilter = document.getElementById('enrollmentFilter');
    if (enrollmentFilter) {
        enrollmentFilter.addEventListener('change', function() {
            currentEnrollmentFilter = this.value;
            currentPage = 1;
            loadStudents();
        });
    }
    
    // Activity filter
    const activityFilter = document.getElementById('activityFilter');
    if (activityFilter) {
        activityFilter.addEventListener('change', function() {
            currentActivityFilter = this.value;
            currentPage = 1;
            loadStudents();
        });
    }
    
    // Sort by
    const sortBy = document.getElementById('sortBy');
    if (sortBy) {
        sortBy.addEventListener('change', function() {
            currentSortBy = this.value;
            currentPage = 1;
            loadStudents();
        });
    }
}

// Load course filter options
function loadCourseFilter() {
    fetch('api/get_teacher_courses.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateCourseFilter(data.courses);
            }
        })
        .catch(error => {
            console.error('Error loading courses:', error);
        });
}

// Populate course filter dropdown
function populateCourseFilter(courses) {
    const select = document.getElementById('courseFilter');
    if (!select) return;
    
    // Clear existing options except "All Courses"
    while (select.children.length > 1) {
        select.removeChild(select.lastChild);
    }
    
    courses.forEach(course => {
        const option = document.createElement('option');
        option.value = course.id;
        option.textContent = course.title;
        select.appendChild(option);
    });
}

// Load students data
function loadStudents() {
    showLoadingState();
    
    const params = new URLSearchParams({
        page: currentPage,
        limit: 12,
        filter: currentFilter,
        search: currentSearch,
        course: currentCourseFilter,
        progress: currentProgressFilter,
        enrollment: currentEnrollmentFilter,
        activity: currentActivityFilter,
        sort: currentSortBy
    });
    
    fetch(`api/get_students.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStudents(data.students);
                displayPagination(data.page, data.totalPages, data.total);
            } else {
                showErrorState(data.message);
            }
        })
        .catch(error => {
            console.error('Error loading students:', error);
            showErrorState('Failed to load students');
        });
}

// Display students in card layout
function displayStudents(students) {
    const container = document.getElementById('studentsContainer');
    const loadingState = document.getElementById('loadingState');
    const emptyState = document.getElementById('emptyState');
    
    if (!container) return;
    
    // Hide loading state
    if (loadingState) {
        loadingState.classList.add('hidden');
    }
    
    if (students.length === 0) {
        container.innerHTML = '';
        if (emptyState) {
            emptyState.classList.remove('hidden');
        }
        return;
    }
    
    // Hide empty state
    if (emptyState) {
        emptyState.classList.add('hidden');
    }
    
    let html = '';
    students.forEach(student => {
        // Determine activity status class based on activity_status
        let statusClass = 'bg-gray-100 text-gray-800'; // Default
        if (student.activity_status === 'Active') {
            statusClass = 'bg-green-100 text-green-800';
        } else if (student.activity_status === 'Inactive') {
            statusClass = 'bg-yellow-100 text-yellow-800';
        } else if (student.activity_status === 'Long Inactive') {
            statusClass = 'bg-red-100 text-red-800';
        }
        
        const lastLogin = student.last_login ? maskLastLogin(student.last_login) : 'Never';
        const progress = student.overall_progress || 0;
        const completionRate = student.completion_rate || '0 of 0 modules completed';
        
        // Extract completion numbers for styling
        const completionMatch = completionRate.match(/(\d+) of (\d+) modules completed/);
        const completed = completionMatch ? parseInt(completionMatch[1]) : 0;
        const total = completionMatch ? parseInt(completionMatch[2]) : 0;
        const completionPercentage = total > 0 ? (completed / total) * 100 : 0;
        
        // Determine badge color based on completion rate
        let badgeClass = 'bg-gray-100 text-gray-800'; // Default
        if (completionPercentage >= 80) {
            badgeClass = 'bg-green-100 text-green-800';
        } else if (completionPercentage >= 50) {
            badgeClass = 'bg-yellow-100 text-yellow-800';
        } else if (completionPercentage > 0) {
            badgeClass = 'bg-blue-100 text-blue-800';
        }
        const joinedDate = student.created_at ? new Date(student.created_at).toLocaleDateString() : 'Unknown';
        const fullName = student.first_name && student.last_name ? 
            `${student.first_name} ${student.last_name}` : 
            student.username;
        
        html += `
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 group">
                <div class="p-6">
                    <!-- Student Header -->
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="relative">
                            ${student.profile_picture ? 
                                `<img src="../../uploads/profile_pictures/${escapeHtml(student.profile_picture)}" 
                                     class="w-16 h-16 rounded-full object-cover shadow-lg group-hover:shadow-xl transition-shadow duration-300" 
                                     alt="Profile Picture"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                 <div class="w-16 h-16 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow duration-300" style="display: none;">
                                     <span class="text-white font-bold text-xl">${student.username.charAt(0).toUpperCase()}</span>
                                 </div>` :
                                `<div class="w-16 h-16 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow duration-300">
                                     <span class="text-white font-bold text-xl">${student.username.charAt(0).toUpperCase()}</span>
                                 </div>`
                            }
                            <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-gradient-to-br ${student.activity_status === 'Active' ? 'from-green-400 to-green-600' : student.activity_status === 'Inactive' ? 'from-yellow-400 to-yellow-600' : 'from-red-400 to-red-600'} rounded-full border-2 border-white shadow-sm"></div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-primary-700 transition-colors duration-200">${escapeHtml(fullName)}</h3>
                            <p class="text-sm text-gray-500 mb-2" title="Email address is masked for privacy">${escapeHtml(maskEmail(student.email))}</p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass} shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 ${student.activity_status === 'Active' ? 'bg-green-600' : student.activity_status === 'Inactive' ? 'bg-yellow-600' : 'bg-red-600'}"></span>
                                ${student.activity_status}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Student Info -->
                    <div class="space-y-3 mb-6">
                        
                        <div class="flex items-center justify-between text-sm bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-3">
                            <div class="flex items-center space-x-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-blue-700 font-medium">Completion:</span>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${badgeClass}">
                                ${escapeHtml(completionRate)}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm bg-gray-50 rounded-lg p-3">
                            <div class="flex items-center space-x-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3a4 4 0 118 0v4m-4 7a3 3 0 100-6 3 3 0 000 6z" />
                                </svg>
                                <span class="text-gray-600 font-medium">Joined:</span>
                            </div>
                            <span class="text-gray-900">${joinedDate}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm bg-gray-50 rounded-lg p-3">
                            <div class="flex items-center space-x-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-gray-600 font-medium">Last Login:</span>
                            </div>
                            <span class="text-gray-900" title="Login time is shown as relative for privacy">${lastLogin}</span>
                        </div>
                    </div>
                    
                    <!-- Progress Section -->
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm font-semibold text-gray-700">Overall Progress</span>
                            <span class="text-sm font-bold text-primary-600">${parseFloat(progress).toFixed(1)}%</span>
                        </div>
                        <div class="relative">
                            <div class="w-full bg-gray-200 rounded-full h-3 shadow-inner">
                                <div class="bg-gradient-to-r from-primary-500 to-primary-600 h-3 rounded-full transition-all duration-500 ease-out shadow-sm" style="width: ${progress}%"></div>
                            </div>
                            <div class="absolute inset-0 rounded-full bg-gradient-to-r from-transparent to-white opacity-30"></div>
                        </div>
                    </div>
                    
                    <!-- Course Count -->
                    <div class="mb-6">
                        <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800 border border-blue-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                            </svg>
                            ${student.enrolled_courses} enrolled modules   
                        </span>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-2">
                        ${(window.userPermissions?.canViewProfile || student.permissions?.canViewProfile) ? `
                        <button onclick="viewStudentDetail(${student.id})" class="flex-1 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 transform hover:scale-105 shadow-md hover:shadow-lg">
                            View Profile
                        </button>` : ''}
                        ${(window.userPermissions?.canViewProgress || student.permissions?.canViewProgress) ? `
                        <button onclick="viewProgress(${student.id})" class="flex-1 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 transform hover:scale-105 shadow-md hover:shadow-lg">
                            Progress
                        </button>` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Display pagination
function displayPagination(currentPage, totalPages, total) {
    const container = document.getElementById('paginationContainer');
    if (!container || totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<nav class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 rounded-lg">';
    
    // Previous button
    html += '<div class="flex flex-1 justify-between sm:hidden">';
    if (currentPage > 1) {
        html += `<button onclick="changePage(${currentPage - 1})" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="changePage(${currentPage + 1})" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</button>`;
    }
    html += '</div>';
    
    // Desktop pagination
    html += '<div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">';
    html += `<div><p class="text-sm text-gray-700">Showing <span class="font-medium">${((currentPage - 1) * 12) + 1}</span> to <span class="font-medium">${Math.min(currentPage * 12, total)}</span> of <span class="font-medium">${total}</span> results</p></div>`;
    
    html += '<div><nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">';
    
    // Previous page
    if (currentPage > 1) {
        html += `<button onclick="changePage(${currentPage - 1})" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">`;
        html += '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>';
        html += '</button>';
    }
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            html += `<button class="relative z-10 inline-flex items-center bg-primary-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">${i}</button>`;
        } else {
            html += `<button onclick="changePage(${i})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">${i}</button>`;
        }
    }
    
    // Next page
    if (currentPage < totalPages) {
        html += `<button onclick="changePage(${currentPage + 1})" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">`;
        html += '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>';
        html += '</button>';
    }
    
    html += '</nav></div></div>';
    html += '</nav>';
    
    container.innerHTML = html;
}

// Change page
function changePage(page) {
    currentPage = page;
    loadStudents();
}

// Show loading state
function showLoadingState() {
    const container = document.getElementById('studentsContainer');
    const loadingState = document.getElementById('loadingState');
    const emptyState = document.getElementById('emptyState');
    
    if (container) container.innerHTML = '';
    if (loadingState) loadingState.classList.remove('hidden');
    if (emptyState) emptyState.classList.add('hidden');
}

// Show error state
function showErrorState(message) {
    const container = document.getElementById('studentsContainer');
    const loadingState = document.getElementById('loadingState');
    const emptyState = document.getElementById('emptyState');
    
    if (container) container.innerHTML = '';
    if (loadingState) loadingState.classList.add('hidden');
    if (emptyState) {
        emptyState.classList.remove('hidden');
        emptyState.querySelector('h3').textContent = 'Error loading students';
        emptyState.querySelector('p').textContent = message;
    }
}

// View student detail
function viewStudentDetail(studentId) {
    // Check permission before proceeding
    if (!window.userPermissions?.canViewProfile) {
        alert('You do not have permission to view student profiles.');
        return;
    }
    // Redirect to the individual profile page
    window.location.href = `view_profile.php?student_id=${studentId}`;
}

// Send message to student
function sendMessage(studentId) {
    // For now, this could open an email client or a messaging system
    // This is a placeholder for future messaging functionality
    alert('Messaging feature coming soon!');
}

// View progress in modal
function viewProgress(studentId) {
    // Check permission before proceeding
    if (!window.userPermissions?.canViewProgress) {
        alert('You do not have permission to view student progress.');
        return;
    }
    
    // Show loading in modal first
    const modal = document.getElementById('progress-detail-modal');
    const content = document.getElementById('progress-detail-content');
    
    if (!modal || !content) {
        console.error('Modal elements not found');
        return;
    }
    
    // Show modal with loading state
    content.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-center items-center bg-gradient-to-r from-primary-50 to-blue-50 relative">
                <h3 class="text-lg font-semibold text-gray-900">Student Progress Details</h3>
                <button onclick="closeProgressModal()" class="text-gray-400 hover:text-gray-600 transition-colors absolute right-6">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mb-4"></div>
                <p class="text-gray-600">Loading student progress...</p>
            </div>
        </div>
    `;
    modal.classList.remove('hidden');
    lucide.createIcons();
    
    // Fetch student progress data
    fetch(`api/get_student_progress.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showProgressModalContent(data);
            } else {
                showProgressError(data.message || 'Failed to load student progress');
            }
        })
        .catch(error => {
            console.error('Error loading student progress:', error);
            showProgressError('Network error: Unable to load progress data');
        });
}

// Show progress modal content
function showProgressModalContent(data) {
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
    const displayName = (student.first_name && student.last_name) ? 
        `${student.first_name} ${student.last_name}` : student.username;
    
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
                            <p class="text-gray-600">${escapeHtml(maskEmail(student.email))}</p>
                            ${student.created_at ? `<p class="text-sm text-gray-500">Joined: ${formatDate(student.created_at)}</p>` : ''}
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
                        Module Progress
                    </h5>
                    ${progressHtml || '<p class="text-gray-500 text-center py-8">No course progress data available</p>'}
                </div>
            </div>
        </div>
    `;
    
    lucide.createIcons();
}

// Show progress error
function showProgressError(message) {
    const content = document.getElementById('progress-detail-content');
    if (!content) return;
    
    // Check if it's the "not enrolled" error
    const isNotEnrolled = message.includes('not enrolled') || message.includes('not found');
    
    content.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-center items-center bg-gradient-to-r from-primary-50 to-blue-50 relative">
                <h3 class="text-lg font-semibold text-gray-900">Student Progress Details</h3>
                <button onclick="closeProgressModal()" class="text-gray-400 hover:text-gray-600 transition-colors absolute right-6">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6 text-center">
                <div class="${isNotEnrolled ? 'text-blue-500' : 'text-red-500'} mb-4">
                    <i data-lucide="${isNotEnrolled ? 'info' : 'alert-circle'}" class="w-12 h-12 mx-auto"></i>
                </div>
                <h4 class="text-lg font-medium text-gray-900 mb-2">
                    ${isNotEnrolled ? 'No Enrollment Data' : 'Error Loading Progress'}
                </h4>
                <p class="text-gray-600 mb-4">
                    ${isNotEnrolled 
                        ? 'This student is not currently enrolled in any of your modules.' 
                        : message}
                </p>
                ${isNotEnrolled ? `
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left mb-4">
                        <p class="text-sm text-blue-700">
                            <strong>Note:</strong> You can only view progress for students enrolled in modules.
                        </p>
                    </div>
                ` : ''}
                <button onclick="closeProgressModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    Close
                </button>
            </div>
        </div>
    `;
    
    lucide.createIcons();
}

// Add helper functions if they don't exist
function getProgressClass(progress) {
    if (progress >= 100) return 'from-green-400 to-green-600';
    if (progress >= 75) return 'from-blue-400 to-blue-600';
    if (progress >= 50) return 'from-yellow-400 to-yellow-600';
    if (progress >= 25) return 'from-orange-400 to-orange-600';
    return 'from-red-400 to-red-600';
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}

function formatDate(dateString) {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

// Close progress modal function (if not already exists)
function closeProgressModal() {
    const modal = document.getElementById('progress-detail-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Utility function to format time ago
function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 2592000) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days} day${days > 1 ? 's' : ''} ago`;
    } else {
        return date.toLocaleDateString();
    }
}

// Enhanced student search with debouncing
let searchTimeout;
function handleSearch(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentSearch = e.target.value;
        currentPage = 1;
        loadStudents();
    }, 300);
}
