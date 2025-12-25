// Learning Calendar JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize FullCalendar
    const calendarEl = document.getElementById('learning-calendar');
    
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            // Basic Configuration
            initialView: 'dayGridMonth',
            height: 'auto',
            aspectRatio: 1.2,
            
            
            // Header Configuration
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'phTime'
            },
            
            // Custom Buttons
            customButtons: {
                phTime: {
                    text: 'PH Time',
                    click: function() {
                        // Toggle between 12/24 hour format
                        toggleTimeFormat();
                    }
                }
            },
            
            // Display Configuration
            dayMaxEvents: 2,
            moreLinkClick: 'popover',
            showNonCurrentDates: true,
            
            // Date Configuration
            firstDay: 0, // Start week on Sunday
            
            // Event Configuration
            events: function(fetchInfo, successCallback, failureCallback) {
                // Fetch real events from your backend
                fetch('api/get_calendar_events.php?t=' + Date.now())
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Calendar events loaded:', data);
                        // Let FullCalendar handle the date filtering
                        successCallback(data);
                    })
                    .catch(error => {
                        console.error('Error fetching calendar events:', error);
                        // Return empty array if API fails
                        successCallback([]);
                        failureCallback(error);
                    });
            },
            
            // Event Handlers
            eventClick: function(info) {
                // Handle event click - show module details
                console.log('Event clicked:', info.event.title);
                showEventDetails(info.event);
            },
            
            dateClick: function(info) {
                // Handle date click - could add new learning session
                console.log('Date clicked:', info.dateStr);
                
                // You can add logic to create new learning sessions here
                showDateOptions(info.dateStr);
            },
            
            // Responsive Configuration
            responsive: true,
            viewDidMount: function(view) {
                // Adjust calendar height based on container
                adjustCalendarHeight();
            },
            
            // Theme Configuration
            themeSystem: 'bootstrap5',
            
            // Localization (Optional - you can add Japanese localization)
            locale: 'en',
            
            
            // Event Rendering
            eventDidMount: function(info) {
                // Add custom styling or tooltips to events
                info.el.title = info.event.title + ' - Click to view details';
                
                // Ensure event colors are preserved
                if (info.event.backgroundColor) {
                    info.el.style.backgroundColor = info.event.backgroundColor;
                }
                if (info.event.borderColor) {
                    info.el.style.borderColor = info.event.borderColor;
                }
                if (info.event.textColor) {
                    info.el.style.color = info.event.textColor;
                }
            },
            
            // View Change Handler
            viewDidMount: function(view) {
                // Adjust calendar height based on container
                adjustCalendarHeight();
            }
        });
        
        // Render the calendar
        calendar.render();
        
        // Store calendar instance globally for potential future use
        window.learningCalendar = calendar;
        
        // Load and display notes as calendar events
        loadNotesAsEvents();
        
        // Initialize live PH time display
        updatePHTime();
        setInterval(updatePHTime, 1000);
        
        // Adjust calendar on window resize
        window.addEventListener('resize', function() {
            calendar.updateSize();
            adjustCalendarHeight();
        });
        
        // Listen for dark mode changes
        document.addEventListener('alpine:updated', function() {
            // Re-render calendar when dark mode changes
            setTimeout(() => {
                calendar.render();
            }, 100);
        });
    }
    
    // Function to adjust calendar height
    function adjustCalendarHeight() {
        const calendarEl = document.getElementById('learning-calendar');
        const container = document.querySelector('.calendar-section .calendar-container');
        
        if (calendarEl && container) {
            const containerHeight = container.offsetHeight;
            const headerHeight = 50; // Adjusted for standalone calendar header
            const availableHeight = containerHeight - headerHeight;
            
            if (availableHeight > 0) {
                calendarEl.style.height = availableHeight + 'px';
            }
        }
    }
    
    // Function to show event details
    function showEventDetails(event) {
        const eventProps = event.extendedProps;
        const eventDate = event.start;
        
        // Format date in Philippine timezone
        const formattedDate = eventDate.toLocaleDateString('en-PH', {
            timeZone: 'Asia/Manila',
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        let eventDetails = '';
        let actionButton = '';
        
        // Different content based on event type
        if (eventProps.type === 'completed') {
            // Format completion time in PH timezone (12-hour format)
            const completionTime = eventProps.completion_date ? 
                new Date(eventProps.completion_date).toLocaleTimeString('en-PH', {
                    timeZone: 'Asia/Manila',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                }) : 'Unknown time';
            
            eventDetails = `
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Module Name:</strong> ${eventProps.course_title}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Status:</strong> <span class="text-green-600 dark:text-green-400 font-semibold">Completed</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Progress:</strong> ${eventProps.completed_chapters || 0} / ${eventProps.total_chapters || 0} chapters (${eventProps.completion_percentage || 0}%)
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Teacher:</strong> ${eventProps.teacher_name || 'N/A'}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Enrolled:</strong> ${eventProps.enrollment_date ? new Date(eventProps.enrollment_date).toLocaleDateString('en-PH', { 
                            timeZone: 'Asia/Manila',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        }) : 'N/A'}
                    </p>
                </div>
            `;
            actionButton = `
                <button onclick="window.location.href='${eventProps.button_action || 'continue_learning.php?id=' + eventProps.course_id}'" 
                        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors">
                    ${eventProps.button_text || 'Review Module'}
                </button>
            `;
        } else if (eventProps.type === 'in_progress') {
            eventDetails = `
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Module Name:</strong> ${eventProps.course_title}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Status:</strong> <span class="text-blue-600 dark:text-blue-400 font-semibold">In Progress</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Progress:</strong> ${eventProps.completed_chapters || 0} / ${eventProps.total_chapters || 0} chapters (${eventProps.completion_percentage || 0}%)
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Teacher:</strong> ${eventProps.teacher_name || 'N/A'}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Enrolled:</strong> ${eventProps.enrollment_date ? new Date(eventProps.enrollment_date).toLocaleDateString('en-PH', { 
                            timeZone: 'Asia/Manila',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        }) : 'N/A'}
                    </p>
                </div>
            `;
            actionButton = `
                <button onclick="window.location.href='${eventProps.button_action || 'continue_learning.php?id=' + eventProps.course_id}'" 
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                    ${eventProps.button_text || 'Continue Learning'}
                </button>
            `;
        } else if (eventProps.type === 'enrolled') {
            eventDetails = `
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Module Name:</strong> ${eventProps.course_title}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Status:</strong> <span class="text-gray-600 dark:text-gray-400 font-semibold">Not Started</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Progress:</strong> ${eventProps.completed_chapters || 0} / ${eventProps.total_chapters || 0} chapters (${eventProps.completion_percentage || 0}%)
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Teacher:</strong> ${eventProps.teacher_name || 'N/A'}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Enrolled:</strong> ${eventProps.enrollment_date ? new Date(eventProps.enrollment_date).toLocaleDateString('en-PH', { 
                            timeZone: 'Asia/Manila',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        }) : 'N/A'}
                    </p>
                </div>
            `;
            actionButton = `
                <button onclick="window.location.href='${eventProps.button_action || 'continue_learning.php?id=' + eventProps.course_id}'" 
                        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                    ${eventProps.button_text || 'Start Module'}
                </button>
            `;
        } else if (eventProps.type === 'new_module') {
            // Format upload time in PH timezone (12-hour format)
            const uploadTime = eventProps.upload_date ? 
                new Date(eventProps.upload_date).toLocaleTimeString('en-PH', {
                    timeZone: 'Asia/Manila',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                }) : 'Unknown time';
            
            eventDetails = `
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Module Name:</strong> ${eventProps.course_title}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Status:</strong> <span class="text-purple-600 dark:text-purple-400 font-semibold">New Module</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Published:</strong> ${eventProps.upload_date ? new Date(eventProps.upload_date).toLocaleDateString('en-PH', { 
                            timeZone: 'Asia/Manila',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        }) : 'N/A'}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Teacher:</strong> ${eventProps.teacher_name || 'N/A'}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Description:</strong> ${eventProps.course_description || 'No description available'}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Time Published:</strong> ${uploadTime}
                    </p>
                </div>
            `;
            actionButton = `
                <button onclick="window.location.href='${eventProps.button_action || (eventProps.is_enrolled ? 'continue_learning.php?id=' + eventProps.course_id : 'student_courses.php')}'" 
                        class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 transition-colors">
                    ${eventProps.button_text || (eventProps.is_enrolled ? 'Continue Learning' : 'View Module')}
                </button>
            `;
        } else if (eventProps.type === 'note') {
            // Note event
            eventDetails = `
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Note:</strong>
                    </p>
                    <div class="bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg border border-amber-200 dark:border-amber-800">
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${eventProps.noteText}</p>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Created: ${new Date(eventProps.created).toLocaleDateString('en-PH', { 
                            timeZone: 'Asia/Manila',
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}
                    </p>
                </div>
            `;
            actionButton = `
                <button onclick="editNote('${event.startStr}')" 
                        class="px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600 transition-colors mr-2">
                    Edit Note
                </button>
                <button onclick="deleteNote('${event.startStr}')" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">
                    Delete Note
                </button>
            `;
        } else {
            // Default for other events
            eventDetails = `
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Module:</strong> ${eventProps.module || eventProps.course_title || 'Learning Session'}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Duration:</strong> ${eventProps.duration || 'Unknown duration'}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                        <strong>Type:</strong> ${eventProps.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                    </p>
                </div>
            `;
            actionButton = `
                <button onclick="window.location.href='my_learning.php'" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">
                    Start Learning
                </button>
            `;
        }
        
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-lg mx-4 shadow-xl">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">${event.title}</h3>
                <p class="text-gray-600 dark:text-gray-300 mb-4">
                    <strong>Date:</strong> ${formattedDate}
                </p>
                ${eventDetails}
                <div class="flex justify-end space-x-3">
                    <button onclick="this.closest('.fixed').remove()" 
                            class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors">
                        Close
                    </button>
                    ${actionButton}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeCalendarModal(modal);
            }
        });
    }
    
    // Function to show date options
    function showDateOptions(dateStr) {
        const date = new Date(dateStr);
        const formattedDate = date.toLocaleDateString('en-PH', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const modal = document.createElement('div');
        modal.className = 'calendar-modal fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-[9999] backdrop-blur-sm';
        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 max-w-lg mx-4 shadow-2xl border border-gray-200 dark:border-gray-700 transform transition-all duration-300 scale-100">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Add Note</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            ${formattedDate}
                        </p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Note Content
                    </label>
                    <textarea id="noteText" 
                              placeholder="Enter your note here..." 
                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white resize-none transition-all duration-200"
                              rows="5"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeCalendarModal(this)" 
                            class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-200 font-medium">
                        Cancel
                    </button>
                    <button onclick="saveNote('${dateStr}')" 
                            class="px-6 py-3 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition-all duration-200 font-medium shadow-lg hover:shadow-xl">
                        Save Note
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Animate in
        setTimeout(() => {
            modal.querySelector('.bg-white, .dark\\:bg-gray-800').style.transform = 'scale(1)';
        }, 10);
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeCalendarModal(modal);
            }
        });
        
        // Focus on textarea
        setTimeout(() => {
            document.getElementById('noteText').focus();
        }, 100);
    }
    
    // Function to load notes from localStorage and display as calendar events
    function loadNotesAsEvents() {
        const notes = JSON.parse(localStorage.getItem('calendarNotes') || '{}');
        const noteEvents = [];
        
        Object.keys(notes).forEach(dateStr => {
            const note = notes[dateStr];
            noteEvents.push({
                id: 'note_' + dateStr,
                title: '📝 Note',
                start: dateStr,
                backgroundColor: '#fbbf24', // Amber color for notes
                borderColor: '#f59e0b',
                textColor: '#92400e',
                extendedProps: {
                    type: 'note',
                    noteText: note.text,
                    created: note.created
                }
            });
        });
        
        // Add note events to calendar
        if (noteEvents.length > 0) {
            noteEvents.forEach(event => {
                window.learningCalendar.addEvent(event);
            });
        }
    }
    
    // Function to save a note
    window.saveNote = function(dateStr) {
        const noteText = document.getElementById('noteText').value.trim();
        
        if (!noteText) {
            showNotification('Please enter a note before saving.', 'error');
            return;
        }
        
        // Save note to localStorage
        const notes = JSON.parse(localStorage.getItem('calendarNotes') || '{}');
        notes[dateStr] = {
            text: noteText,
            date: dateStr,
            created: new Date().toISOString()
        };
        localStorage.setItem('calendarNotes', JSON.stringify(notes));
        
        // Add note as calendar event immediately
        const noteEvent = {
            id: 'note_' + dateStr,
            title: '📝 Note',
            start: dateStr,
            backgroundColor: '#fbbf24', // Amber color for notes
            borderColor: '#f59e0b',
            textColor: '#92400e',
            extendedProps: {
                type: 'note',
                noteText: noteText,
                created: new Date().toISOString()
            }
        };
        
        // Remove existing note event for this date if it exists
        const existingEvent = window.learningCalendar.getEventById('note_' + dateStr);
        if (existingEvent) {
            existingEvent.remove();
        }
        
        // Add new note event
        window.learningCalendar.addEvent(noteEvent);
        
        // Close any open calendar modals
        document.querySelectorAll('.calendar-modal').forEach(modal => {
            closeCalendarModal(modal);
        });
        
        console.log(`Note saved for ${dateStr}:`, noteText);
        
        // Show success message
        showNotification(`Note saved for ${new Date(dateStr).toLocaleDateString('en-PH')}`, 'success');
    };
    
    // Function to show notifications
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
        
        const colors = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            info: 'bg-blue-500 text-white',
            warning: 'bg-yellow-500 text-black'
        };
        
        notification.className += ` ${colors[type] || colors.info}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
});




// Function to add a new learning event
function addLearningEvent(title, date, type = 'general') {
    if (window.learningCalendar) {
        const event = {
            title: title,
            start: date,
            backgroundColor: getEventColor(type),
            borderColor: getEventColor(type),
            textColor: '#ffffff',
            classNames: ['learning-event']
        };
        
        window.learningCalendar.addEvent(event);
    }
}

    // Function to get event color based on type
    function getEventColor(type) {
        const colors = {
            hiragana: '#ef4444',
            kanji: '#3b82f6',
            grammar: '#10b981',
            vocabulary: '#8b5cf6',
            listening: '#f59e0b',
            general: '#6b7280'
        };
        
        return colors[type] || colors.general;
    }
    
    // Global variable to track time format
    let use24HourFormat = false;
    
    // Function to update PH time display
    function updatePHTime() {
        const now = new Date();
        const phTime = new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: !use24HourFormat
        }).format(now);
        
        const timeDisplay = `${phTime} PH`;
        
        // Update the PH time button text
        const phTimeButton = document.querySelector('.fc-phTime-button');
        if (phTimeButton) {
            phTimeButton.innerHTML = timeDisplay;
        }
    }
    
    // Function to toggle time format
    function toggleTimeFormat() {
        use24HourFormat = !use24HourFormat;
        updatePHTime();
        const formatText = use24HourFormat ? '24-hour' : '12-hour';
        showNotification(`Time format changed to ${formatText}`, 'info');
    }
    
    // Function to edit a note
    window.editNote = function(dateStr) {
        const notes = JSON.parse(localStorage.getItem('calendarNotes') || '{}');
        const note = notes[dateStr];
        
        if (!note) {
            showNotification('Note not found.', 'error');
            return;
        }
        
        // Close any existing calendar modals only
        document.querySelectorAll('.calendar-modal').forEach(modal => modal.remove());
        
        // Show modern edit modal
        const modal = document.createElement('div');
        modal.className = 'calendar-modal fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-[9999] backdrop-blur-sm';
        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 max-w-lg mx-4 shadow-2xl border border-gray-200 dark:border-gray-700 transform transition-all duration-300 scale-100">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Edit Note</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            ${new Date(dateStr).toLocaleDateString('en-PH', { 
                                timeZone: 'Asia/Manila',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            })}
                        </p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Note Content
                    </label>
                    <textarea id="editNoteText" 
                              placeholder="Enter your note here..." 
                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent dark:bg-gray-700 dark:text-white resize-none transition-all duration-200"
                              rows="5">${note.text}</textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeCalendarModal(this)" 
                            class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-200 font-medium">
                        Cancel
                    </button>
                    <button onclick="updateNote('${dateStr}')" 
                            class="px-6 py-3 bg-amber-500 text-white rounded-xl hover:bg-amber-600 transition-all duration-200 font-medium shadow-lg hover:shadow-xl">
                        Update Note
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Animate in
        setTimeout(() => {
            modal.querySelector('.bg-white, .dark\\:bg-gray-800').style.transform = 'scale(1)';
        }, 10);
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeCalendarModal(modal);
            }
        });
        
        // Focus on textarea
        setTimeout(() => {
            document.getElementById('editNoteText').focus();
        }, 100);
    };
    
    // Function to update a note
    window.updateNote = function(dateStr) {
        const noteText = document.getElementById('editNoteText').value.trim();
        
        if (!noteText) {
            showNotification('Please enter a note before updating.', 'error');
            return;
        }
        
        // Update note in localStorage
        const notes = JSON.parse(localStorage.getItem('calendarNotes') || '{}');
        notes[dateStr] = {
            text: noteText,
            date: dateStr,
            created: notes[dateStr].created, // Keep original creation date
            updated: new Date().toISOString()
        };
        localStorage.setItem('calendarNotes', JSON.stringify(notes));
        
        // Update calendar event
        const existingEvent = window.learningCalendar.getEventById('note_' + dateStr);
        if (existingEvent) {
            existingEvent.setExtendedProp('noteText', noteText);
            existingEvent.setExtendedProp('updated', new Date().toISOString());
        }
        
        // Close ALL calendar modals (both edit and note details)
        document.querySelectorAll('.calendar-modal').forEach(modal => {
            closeCalendarModal(modal);
        });
        
        showNotification(`Note updated for ${new Date(dateStr).toLocaleDateString('en-PH')}`, 'success');
    };
    
    // Function to delete a note
    window.deleteNote = function(dateStr) {
        // Close any existing calendar modals only
        document.querySelectorAll('.calendar-modal').forEach(modal => modal.remove());
        
        // Show modern confirmation dialog
        const modal = document.createElement('div');
        modal.className = 'calendar-modal fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-[9999] backdrop-blur-sm';
        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 max-w-md mx-4 shadow-2xl border border-gray-200 dark:border-gray-700 transform transition-all duration-300 scale-100">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Delete Note</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            ${new Date(dateStr).toLocaleDateString('en-PH', { 
                                timeZone: 'Asia/Manila',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            })}
                        </p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <p class="text-gray-600 dark:text-gray-300">
                        Are you sure you want to delete this note? This action cannot be undone.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeCalendarModal(this)" 
                            class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-200 font-medium">
                        Cancel
                    </button>
                    <button onclick="confirmDeleteNote('${dateStr}')" 
                            class="px-6 py-3 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-all duration-200 font-medium shadow-lg hover:shadow-xl">
                        Delete Note
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Animate in
        setTimeout(() => {
            modal.querySelector('.bg-white, .dark\\:bg-gray-800').style.transform = 'scale(1)';
        }, 10);
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeCalendarModal(modal);
            }
        });
    };
    
    // Function to confirm note deletion
    window.confirmDeleteNote = function(dateStr) {
        // Remove from localStorage
        const notes = JSON.parse(localStorage.getItem('calendarNotes') || '{}');
        delete notes[dateStr];
        localStorage.setItem('calendarNotes', JSON.stringify(notes));
        
        // Remove from calendar
        const existingEvent = window.learningCalendar.getEventById('note_' + dateStr);
        if (existingEvent) {
            existingEvent.remove();
        }
        
        // Close ALL calendar modals (both delete confirmation and note details)
        document.querySelectorAll('.calendar-modal').forEach(modal => {
            closeCalendarModal(modal);
        });
        
        showNotification(`Note deleted for ${new Date(dateStr).toLocaleDateString('en-PH')}`, 'success');
    };
    
    // Function to close calendar modals safely
    window.closeCalendarModal = function(element) {
        let modal;
        if (element) {
            modal = element.closest('.calendar-modal');
        } else {
            modal = document.querySelector('.calendar-modal');
        }
        
        if (modal) {
            // Animate out
            const content = modal.querySelector('.bg-white, .dark\\:bg-gray-800');
            if (content) {
                content.style.transform = 'scale(0.95)';
                content.style.opacity = '0';
            }
            
            setTimeout(() => {
                modal.remove();
            }, 200);
        }
    };
    
