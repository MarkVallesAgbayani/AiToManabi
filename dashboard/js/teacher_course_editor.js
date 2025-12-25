// Global variables
let currentStep = 1;
let sections = [];
let chapters = [];
let quizzes = [];
let currentCourse = null;
let courseId = null;
let mode = 'create';

// Modern Confirmation Dialog System
function showModernConfirm(title, message, confirmText = 'Confirm', cancelText = 'Cancel', onConfirm = null, onCancel = null) {
    // Remove any existing confirm dialogs
    const existingDialogs = document.querySelectorAll('.modern-confirm-dialog');
    existingDialogs.forEach(dialog => dialog.remove());
    
    // Create the modal backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70] modern-confirm-dialog';
    
    // Create the dialog content
    backdrop.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4" id="confirmDialog">
            <div class="p-6">
                <!-- Header with icon -->
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-red-600 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">${title}</h3>
                </div>
                
                <!-- Message -->
                <p class="text-gray-600 mb-6 leading-relaxed">${message}</p>
                
                <!-- Action buttons -->
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelBtn" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium">
                        ${cancelText}
                    </button>
                    <button type="button" id="confirmBtn" class="px-6 py-2.5 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:from-red-600 hover:to-red-700 font-medium shadow-lg">
                        ${confirmText}
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(backdrop);
    
    // Event handlers
    document.getElementById('cancelBtn').onclick = () => {
        backdrop.remove();
        if (onCancel) onCancel();
    };
    
    document.getElementById('confirmBtn').onclick = () => {
        backdrop.remove();
        if (onConfirm) onConfirm();
    };
    
    // Close on backdrop click
    backdrop.onclick = (e) => {
        if (e.target === backdrop) {
            backdrop.remove();
            if (onCancel) onCancel();
        }
    };
    
    // Close on Escape key
    const handleEscape = (e) => {
        if (e.key === 'Escape') {
            backdrop.remove();
            if (onCancel) onCancel();
            document.removeEventListener('keydown', handleEscape);
        }
    };
    document.addEventListener('keydown', handleEscape);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Get values from global variables set in the HTML page
    if (typeof window.courseId !== 'undefined') {
        courseId = window.courseId;
    }
    if (typeof window.mode !== 'undefined') {
        mode = window.mode;
    }
    
    console.log('Course Editor: courseId =', courseId, 'mode =', mode);
    
    // Initialize Question Type Manager
    if (typeof QuestionTypeManager !== 'undefined') {
        window.questionTypeManager = new QuestionTypeManager();
        console.log('QuestionTypeManager initialized for course editor');
        
        // Refresh dropdown after initialization
        setTimeout(() => {
            if (window.questionTypeManager) {
                window.questionTypeManager.refreshDropdown();
            }
        }, 1000);
    } else {
        console.warn('QuestionTypeManager not available, using fallback question types');
    }
    
    if (mode === 'edit' && courseId > 0) {
        loadCourseData();
    } else {
        showStep(1);
    }
    
    // Add event listeners
    attachEventListeners();
});

// Load existing course data
function loadCourseData() {
    const fetchUrl = '../api/courses.php?id=' + courseId;
    console.log('Fetching course data from:', fetchUrl);
    
    fetch(fetchUrl)
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw err; });
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                showNotification(data.message || 'Failed to load course data', 'error');
                console.error('Course data error:', data);
                return;
            }
            
            // Store the loaded data
            currentCourse = data.data.course;
            sections = data.data.sections || [];
            chapters = data.data.chapters || [];
            quizzes = data.data.quizzes || [];
            
            // Debug log the loaded quiz data
            console.log('Loaded quizzes:', quizzes);
            quizzes.forEach((quiz, index) => {
                console.log(`Quiz ${index + 1}:`, quiz);
                if (quiz.questions) {
                    console.log(`Quiz ${index + 1} questions:`, quiz.questions);
                    quiz.questions.forEach((question, qIndex) => {
                        console.log(`Question ${qIndex + 1}:`, question);
                    });
                }
            });
            
            // Populate form fields
            populateFormFields(currentCourse);
            
            // Show first step
            showStep(1);
        })
        .catch(err => {
            showNotification('Error loading course data: ' + (err.message || JSON.stringify(err)), 'error');
            console.error('AJAX error loading course data:', err);
        });
}

// Populate form fields with course data
function populateFormFields(course) {
    if (!course) return;
    
    document.getElementById('moduleTitle').value = course.title || '';
    document.getElementById('moduleDescription').value = course.description || '';
    document.getElementById('modulePrice').value = course.price || '';
    document.getElementById('categorySelect').value = course.category_id || '';
    document.getElementById('courseCategorySelect').value = course.course_category_id || '';
}

// Show specific step
function showStep(step) {
    // Hide all step contents
    document.querySelectorAll('.step-content').forEach(content => {
        content.classList.remove('active');
        content.style.display = 'none';
    });
    
    // Remove active/completed classes from all steps
    document.querySelectorAll('.step').forEach(stepEl => {
        stepEl.classList.remove('active', 'completed');
        const stepCircle = stepEl.querySelector('.step-circle');
        if (stepCircle) {
            stepCircle.classList.remove('bg-gradient-to-r', 'from-red-500', 'to-red-600', 'text-white', 'shadow-lg');
            stepCircle.classList.remove('bg-gray-200', 'text-gray-400', 'shadow-md');
            stepCircle.classList.remove('bg-green-500', 'text-white', 'shadow-lg');
        }
    });
    
    // Show current step content
    const currentStepContent = document.getElementById(`step${step}-content`);
    if (currentStepContent) {
        currentStepContent.style.display = 'block';
        currentStepContent.classList.add('active');
    }
    
    // Update step indicator with proper styling
    for (let i = 1; i <= 4; i++) {
        const stepEl = document.getElementById(`step${i}`);
        const stepCircle = stepEl.querySelector('.step-circle');
        
        if (i < step) {
            // Completed steps - green
            stepEl.classList.add('completed');
            if (stepCircle) {
                stepCircle.classList.add('bg-green-500', 'text-white', 'shadow-lg');
                stepCircle.classList.remove('bg-gray-200', 'text-gray-400', 'shadow-md');
                stepCircle.classList.remove('bg-gradient-to-r', 'from-red-500', 'to-red-600');
            }
        } else if (i === step) {
            // Current step - red gradient
            stepEl.classList.add('active');
            if (stepCircle) {
                stepCircle.classList.add('bg-gradient-to-r', 'from-red-500', 'to-red-600', 'text-white', 'shadow-lg');
                stepCircle.classList.remove('bg-gray-200', 'text-gray-400', 'shadow-md');
                stepCircle.classList.remove('bg-green-500');
            }
        } else {
            // Future steps - gray
            if (stepCircle) {
                stepCircle.classList.add('bg-gray-200', 'text-gray-400', 'shadow-md');
                stepCircle.classList.remove('bg-gradient-to-r', 'from-red-500', 'to-red-600', 'text-white', 'shadow-lg');
                stepCircle.classList.remove('bg-green-500', 'text-white', 'shadow-lg');
            }
        }
    }
    
    currentStep = step;
    
    // Load content for specific steps
    if (step === 2) {
        renderSections();
    } else if (step === 3) {
        renderChapters();
    } else if (step === 4) {
        renderQuizzes();
    }
}

// Render sections for step 2
function renderSections() {
    const container = document.getElementById('sectionsContainer');
    
    if (sections.length === 0) {
        container.innerHTML = `
            <div class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
                <i class="fas fa-folder-plus text-4xl mb-4"></i>
                <p class="text-lg">No sections added yet</p>
                <p class="text-sm">Click "Add Section" to create your first section</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    sections.forEach((section, index) => {
        html += `
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-gray-800">${escapeHtml(section.title)}</h3>
                        <p class="text-gray-600 text-sm mt-1">${escapeHtml(section.description || '')}</p>
                    </div>
                    <div class="flex space-x-2 ml-4">
                        <button type="button" class="text-blue-500 hover:text-blue-700 p-2 rounded transition" 
                                onclick="editSection(${index})" title="Edit Section">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="text-red-500 hover:text-red-700 p-2 rounded transition" 
                                onclick="deleteSection(${index})" title="Delete Section">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Render chapters for step 3
function renderChapters() {
    const container = document.getElementById('chaptersContainer');
    
    if (sections.length === 0) {
        container.innerHTML = `
            <div class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
                <i class="fas fa-exclamation-triangle text-4xl mb-4 text-orange-400"></i>
                <p class="text-lg">No sections available</p>
                <p class="text-sm">Please go back to Step 2 and add sections first</p>
                <button type="button" class="btn btn-primary mt-4" onclick="showStep(2)">
                    <i class="fas fa-arrow-left mr-1"></i> Add Sections
                </button>
            </div>
        `;
        return;
    }
    
    let html = '';
    sections.forEach(section => {
        const sectionChapters = chapters.filter(c => c.section_id == section.id);
        
        html += `
            <div class="bg-gray-50 rounded-lg border border-gray-200 mb-6">
                <div class="bg-blue-50 px-4 py-3 border-b border-gray-200 rounded-t-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-folder text-blue-600 mr-2"></i>
                            <h3 class="font-semibold text-lg text-gray-800">${escapeHtml(section.title)}</h3>
                        </div>
                        <button type="button" class="btn bg-red-600 text-white hover:bg-red-700 text-sm" 
                                onclick="addChapterToSection('${section.id}')">
                            <i class="fas fa-plus mr-1"></i> Add Chapter
                        </button>
                    </div>
                    ${section.description ? `<p class="text-gray-600 text-sm mt-1">${escapeHtml(section.description)}</p>` : ''}
                </div>
                
                <div class="p-4">
                    ${sectionChapters.length === 0 ? `
                        <div class="text-gray-500 text-center py-8">
                            <i class="fas fa-book-open text-3xl mb-3 text-gray-400"></i>
                            <p class="text-sm">No chapters in this section yet</p>
                            <p class="text-xs text-gray-400">Click "Add Chapter" to create the first chapter</p>
                        </div>
                    ` : `
                        <div class="space-y-3">
                            ${sectionChapters.map((chapter, index) => `
                                <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-sm transition-shadow">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-800">${escapeHtml(chapter.title)}</h4>
                                            ${chapter.description ? `<p class="text-gray-600 text-sm mt-1">${escapeHtml(chapter.description)}</p>` : ''}
                                            ${chapter.content ? `
                                                <div class="flex items-center mt-2 ${chapter.content_type === 'video' ? 'text-blue-600' : 'text-gray-500'} text-sm">
                                                    <i class="fas ${chapter.content_type === 'video' ? 'fa-play-circle' : 'fa-align-left'} mr-1"></i>
                                                    <span>${chapter.content_type === 'video' ? 'Video' : 'Content'}: ${escapeHtml(chapter.content.length > 100 ? chapter.content.substring(0, 100) + '...' : chapter.content)}</span>
                                                </div>
                                            ` : ''}
                                            ${chapter.content_type === 'video' && chapter.video_copyright ? `
                                                <div class="mt-2 p-2 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                                                    <div class="flex items-start">
                                                        <i class="fas fa-copyright text-yellow-600 mr-2 mt-0.5 text-xs"></i>
                                                        <div class="text-xs text-yellow-800">
                                                            <strong>Copyright Notice:</strong> ${escapeHtml(chapter.video_copyright.length > 150 ? chapter.video_copyright.substring(0, 150) + '...' : chapter.video_copyright)}
                                                        </div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                            <div class="flex items-center mt-1 text-purple-600 text-xs">
                                                <i class="fas fa-tag mr-1"></i>
                                                <span>${chapter.content_type === 'video' ? 'Video Content' : 'Text Content'}</span>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2 ml-4">
                                            <button type="button" class="text-blue-500 hover:text-blue-700 p-2 rounded transition" 
                                                    onclick="editChapter('${chapter.id}')" title="Edit Chapter">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="text-red-500 hover:text-red-700 p-2 rounded transition" 
                                                    onclick="deleteChapter('${chapter.id}')" title="Delete Chapter">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Render quizzes for step 4
function renderQuizzes() {
    const container = document.getElementById('quizzesContainer');
    
    if (sections.length === 0) {
        container.innerHTML = `
            <div class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
                <i class="fas fa-exclamation-triangle text-4xl mb-4 text-orange-400"></i>
                <p class="text-lg">No sections available</p>
                <p class="text-sm">Please go back to Step 2 and add sections first</p>
                <button type="button" class="btn btn-primary mt-4" onclick="showStep(2)">
                    <i class="fas fa-arrow-left mr-1"></i> Add Sections
                </button>
            </div>
        `;
        return;
    }
    
    if (quizzes.length === 0) {
        container.innerHTML = `
            <div class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg">
                <i class="fas fa-question-circle text-4xl mb-4"></i>
                <p class="text-lg">No quizzes added yet</p>
                <p class="text-sm">Click "Add Quiz" to create your first quiz</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    quizzes.forEach((quiz, index) => {
        html += `
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mb-4">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-gray-800">${escapeHtml(quiz.title)}</h3>
                        <p class="text-gray-600 text-sm mt-1">${escapeHtml(quiz.description || quiz.instructions || '')}</p>
                        <div class="flex items-center mt-2 space-x-4">
                            <span class="text-sm text-blue-600">
                                <i class="fas fa-clock mr-1"></i>
                            </span>
                            <span class="text-sm text-purple-600">
                                <i class="fas fa-question mr-1"></i>
                                ${quiz.questions ? quiz.questions.length : 0} questions
                            </span>
                            <span class="text-sm text-orange-600">
                                <i class="fas fa-redo mr-1"></i>
                                ${quiz.max_retakes === -1 ? 'Unlimited retakes' : 
                                  quiz.max_retakes === 0 ? 'No retakes' : 
                                  `${quiz.max_retakes || 3} retake${(quiz.max_retakes || 3) !== 1 ? 's' : ''}`}
                            </span>
                        </div>
                    </div>
                    <div class="flex space-x-2 ml-4">
                        <button type="button" class="text-blue-500 hover:text-blue-700 p-2 rounded transition" 
                                onclick="editQuiz('${quiz.id}')" title="Edit Quiz">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="text-red-500 hover:text-red-700 p-2 rounded transition" 
                                onclick="deleteQuiz('${quiz.id}')" title="Delete Quiz">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                            ${quiz.questions && quiz.questions.length > 0 ? `
                    <div class="border-t pt-4">
                        <h4 class="font-medium text-gray-700 mb-3">Questions Preview:</h4>
                        <div class="max-h-80 overflow-y-auto space-y-3 pr-2 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                            ${quiz.questions.map((question, qIndex) => `
                                <div class="bg-white p-3 rounded border">
                                    <p class="font-medium text-sm">${qIndex + 1}. ${escapeHtml(question.question_text || question.question || question.text || question.word || 'No question text')}</p>
                                    <div class="flex items-center mt-1">
                                        <span class="text-xs px-2 py-1 rounded ${question.type === 'pronunciation' ? 'bg-purple-100 text-purple-700' : 
                                            question.type === 'true_false' ? 'bg-blue-100 text-blue-700' : 
                                            question.type === 'word_definition' ? 'bg-amber-100 text-amber-700' :
                                            question.type === 'sentence_translation' ? 'bg-green-100 text-green-700' :
                                            'bg-gray-100 text-gray-700'}">
                                            ${question.type === 'pronunciation' ? 'Pronunciation' : 
                                              question.type === 'true_false' ? 'True/False' : 
                                              question.type === 'word_definition' ? 'Word Definition' :
                                              question.type === 'sentence_translation' ? 'Translation' :
                                              question.type === 'fill_blank' ? 'Fill Blank' :
                                              question.type === 'multiple_choice' ? 'Multiple Choice' : question.type}
                                        </span>
                                        <span class="text-xs text-blue-600 ml-2">Points: ${question.points || question.score || 1}</span>
                                    </div>
                                    ${question.type === 'pronunciation' ? `
                                        <div class="mt-2 text-xs bg-purple-50 p-2 rounded border border-purple-200">
                                            <div class="grid grid-cols-3 gap-2 text-purple-700">
                                                <div><strong>Japanese:</strong> ${escapeHtml(question.word || '')}</div>
                                                <div><strong>Romaji:</strong> ${escapeHtml(question.romaji || '')}</div>
                                                <div><strong>Meaning:</strong> ${escapeHtml(question.meaning || '')}</div>
                                            </div>
                                        </div>
                                    ` : question.type === 'word_definition' && question.word_definition_pairs ? `
                                        <div class="mt-2 text-xs bg-amber-50 p-2 rounded border border-amber-200">
                                            <div class="mb-1"><strong>Word-Definition Pairs:</strong></div>
                                            ${question.word_definition_pairs.map(pair => `
                                                <div class="text-gray-700">${escapeHtml(pair.word)} → ${escapeHtml(pair.definition)}</div>
                                            `).join('')}
                                        </div>
                                    ` : question.type === 'sentence_translation' && question.translation_pairs ? `
                                        <div class="mt-2 text-xs bg-green-50 p-2 rounded border border-green-200">
                                            <div class="mb-1"><strong>Translation Pairs:</strong></div>
                                            ${question.translation_pairs.map(pair => `
                                                <div class="text-gray-700">${escapeHtml(pair.japanese)} → ${escapeHtml(pair.english)}</div>
                                            `).join('')}
                                        </div>
                                    ` : question.type === 'fill_blank' && (question.answers || question.correct_answers) ? `
                                        <div class="mt-2 space-y-1">
                                            <div class="text-xs text-green-600 font-medium">
                                                <strong>Correct Answer(s):</strong> ${escapeHtml((question.answers || question.correct_answers).join(', '))}
                                            </div>
                                        </div>
                                    ` : question.choices && question.choices.length > 0 ? `
                                        <div class="mt-2 space-y-1">
                                            ${question.choices.map((choice, cIndex) => `
                                                <div class="text-xs ${choice.is_correct ? 'text-green-600 font-medium' : 'text-gray-500'}">
                                                    ${String.fromCharCode(65 + cIndex)}. ${escapeHtml(choice.text)}
                                                    ${choice.is_correct ? ' ✓' : ''}
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Attach event listeners
function attachEventListeners() {
    // Add Section button
    document.getElementById('addSectionBtn').addEventListener('click', function() {
        showSectionModal();
    });
    
    // Add Quiz button
    document.getElementById('addQuizBtn').addEventListener('click', function() {
        showQuizModal();
    });
    
    // Category management buttons
    document.getElementById('addCategoryBtn').addEventListener('click', function() {
        showCategoryModal('add');
    });
    
    document.getElementById('editCategoryBtn').addEventListener('click', function() {
        const select = document.getElementById('categorySelect');
        if (!select.value) {
            showNotification('Please select a level to edit', 'error');
            return;
        }
        
        // Get the selected category name for the confirmation dialog
        const selectedOption = select.selectedOptions[0];
        const categoryName = selectedOption ? selectedOption.textContent : 'this level';
        
        showModernConfirm(
            'Edit Module Level',
            `Are you sure you want to edit "${categoryName}"? This will open the level editor.`,
            'Edit',
            'Cancel',
            () => {
                // Proceed with editing
                showCategoryModal('edit', select.value, select.options[select.selectedIndex].text);
            }
        );
    });
    
    document.getElementById('deleteCategoryBtn').addEventListener('click', async function() {
        const select = document.getElementById('categorySelect');
        if (!select.value) {
            showNotification('Please select a level to delete', 'error');
            return;
        }
        
        const levelName = select.options[select.selectedIndex].text;
        
        showModernConfirm(
            'Delete Level',
            `Are you sure you want to delete the level "${levelName}"? This action cannot be undone.`,
            'Delete',
            'Cancel',
            async () => {
                // Proceed with deletion
                try {
                            try { window.__suppressBeforeUnload = true; } catch (e) {}
                    const response = await fetch('../api/categories.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', id: select.value })
                    });

                    const data = await response.json();
                    if (data.success) {
                        // Remove the deleted category option without reloading
                        try {
                            const idx = select.selectedIndex;
                            select.remove(idx);
                            select.selectedIndex = Math.max(0, Math.min(idx, select.options.length - 1));
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        } catch (err) {
                            console.error('Failed to remove category option:', err);
                        }

                        showNotification('Category deleted successfully', 'success');
                    } else {
                        throw new Error(data.message || 'Failed to delete category');
                    }
                } catch (error) {
                    showNotification(error.message, 'error');
                } finally {
                    try { window.__suppressBeforeUnload = false; } catch (e) {}
                }
            }
        );
    });
    
    // Step navigation buttons
    const nextButtons = ['nextToStep2', 'nextToStep3', 'nextToStep4'];
    const backButtons = ['backToStep1', 'backToStep2', 'backToStep3'];
    
    nextButtons.forEach(buttonId => {
        const button = document.getElementById(buttonId);
        if (button) {
            button.addEventListener('click', function() {
                const currentStepNum = parseInt(buttonId.replace('nextToStep', ''));
                const nextStep = currentStepNum + 1;
                
                // Validate current step before proceeding
                if (validateCurrentStep(currentStepNum)) {
                    showStep(nextStep);
                }
            });
        }
    });
    
    backButtons.forEach(buttonId => {
        const button = document.getElementById(buttonId);
        if (button) {
            button.addEventListener('click', function() {
                const currentStepNum = parseInt(buttonId.replace('backToStep', ''));
                const prevStep = currentStepNum - 1;
                showStep(prevStep);
            });
        }
    });
    
    // Save and publish buttons
    const saveDraftBtn = document.getElementById('saveDraft');
    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', function() {
            const title = document.getElementById('moduleTitle').value.trim();
            const moduleTitle = title || 'this module';
            
            showModernConfirm(
                'Save as Draft',
                `Are you sure you want to save "${moduleTitle}" as a draft? You can continue editing and publish it later when ready.`,
                'Save Draft',
                'Continue Editing',
                () => {
                    // Proceed with saving draft
                    saveCourse('draft');
                }
            );
        });
    }
    
    const publishBtn = document.getElementById('publishModule');
    if (publishBtn) {
        publishBtn.addEventListener('click', function() {
            const title = document.getElementById('moduleTitle').value.trim();
            const moduleTitle = title || 'this module';
            
            showModernConfirm(
                'Publish Module',
                `Are you ready to publish "${moduleTitle}"? Once published, this module will be available to students. Please ensure all content is complete and accurate.`,
                'Publish Now',
                'Review First',
                () => {
                    // Proceed with publishing
                    saveCourse('published');
                }
            );
        });
    }
}

// Validate current step before allowing navigation
function validateCurrentStep(step) {
    switch(step) {
        case 1:
            const title = document.getElementById('moduleTitle')?.value?.trim();
            const category = document.getElementById('categorySelect')?.value;
            const courseCategory = document.getElementById('courseCategorySelect')?.value;
            
            // Debug logging
            console.log('Validation - Title:', title);
            console.log('Validation - Category:', category);
            console.log('Validation - Course Category:', courseCategory);
            
            if (!title) {
                showNotification('Module title is required', 'error');
                return false;
            }
            if (!category) {
                showNotification('Module level is required', 'error');
                return false;
            }
            if (!courseCategory) {
                showNotification('Course category is required', 'error');
                return false;
            }
            return true;
            
        case 2:
            if (sections.length === 0) {
                showNotification('Please add at least one section', 'error');
                return false;
            }
            return true;
            
        case 3:
            if (chapters.length === 0) {
                showNotification('Please add at least one chapter', 'error');
                return false;
            }
            return true;
            
        default:
            return true;
    }
}

// Show section modal
function showSectionModal(sectionIndex = null) {
    const isEdit = sectionIndex !== null;
    const section = isEdit ? sections[sectionIndex] : null;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.id = 'sectionModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">${isEdit ? 'Edit' : 'Add'} Section</h3>
            <form id="sectionForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Section Title *</label>
                    <input type="text" id="sectionTitle" class="form-input" value="${isEdit ? escapeHtml(section.title) : ''}" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea id="sectionDescription" class="form-textarea" rows="3">${isEdit ? escapeHtml(section.description || '') : ''}</textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelSection" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Add'} Section</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Cancel button
    document.getElementById('cancelSection').onclick = () => modal.remove();
    
    // Form submit
    document.getElementById('sectionForm').onsubmit = function(e) {
        e.preventDefault();
        
        const title = document.getElementById('sectionTitle').value.trim();
        const description = document.getElementById('sectionDescription').value.trim();
        
        if (!title) {
            showNotification('Section title is required', 'error');
            return;
        }
        
        const sectionData = {
            id: isEdit ? section.id : `new_${Date.now()}`,
            title: title,
            description: description,
            order_index: isEdit ? section.order_index : sections.length
        };
        
        if (isEdit) {
            // Show confirmation dialog for update
            const originalTitle = section.title;
            showModernConfirm(
                'Update Section',
                `Are you sure you want to update "${originalTitle}" to "${title}"? This will save your changes.`,
                'Update',
                'Cancel',
                () => {
                    // Proceed with update
                    sections[sectionIndex] = sectionData;
                    renderSections();
                    modal.remove();
                    showNotification('Section updated successfully', 'success');
                    notifyUnsavedChange();
                }
            );
        } else {
            // Add new section (no confirmation needed)
            sections.push(sectionData);
            renderSections();
            modal.remove();
            showNotification('Section added successfully', 'success');
            notifyUnsavedChange();
        }
    };
}

// Show category modal
function showCategoryModal(mode, id = '', name = '') {
    const existing = document.getElementById('categoryModal');
    if (existing) existing.remove();
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.id = 'categoryModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">${mode === 'add' ? 'Add' : 'Edit'} Module Level</h3>
            <form id="categoryForm" data-id="${id}">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Level Name</label>
                    <input type="text" id="categoryName" class="form-input" value="${name}" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelCategory" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">${mode === 'add' ? 'Save' : 'Update'} Level</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Cancel button
    document.getElementById('cancelCategory').onclick = () => modal.remove();
    
    // Form submit
    document.getElementById('categoryForm').onsubmit = async function(e) {
        e.preventDefault();
        
        const categoryName = document.getElementById('categoryName').value.trim();
        if (!categoryName) {
            showNotification('Level name is required', 'error');
            return;
        }
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';
        submitBtn.disabled = true;
        
        if (mode === 'edit') {
            // Show confirmation dialog for update
            const originalName = name || 'this level';
            showModernConfirm(
                'Update Module Level',
                `Are you sure you want to update "${originalName}" to "${categoryName}"? This will save your changes.`,
                'Update',
                'Cancel',
                async () => {
                    // Proceed with update
                    await proceedWithCategoryUpdate(categoryName, id, submitBtn, originalText, modal);
                }
            );
        } else {
            // Add new category (no confirmation needed)
            await proceedWithCategoryUpdate(categoryName, null, submitBtn, originalText, modal);
        }
    };
    
    async function proceedWithCategoryUpdate(categoryName, id, submitBtn, originalText, modal) {
        try {
            const payload = id 
                ? { action: 'update', id: id, name: categoryName }
                : { action: 'add', name: categoryName };
            
            const response = await fetch('../api/categories.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const data = await response.json();
            if (data.success) {
                const select = document.getElementById('categorySelect');
                
                if (!id) {
                    // Adding new category
                    console.log('Adding new category with data:', data);
                    console.log('Category ID from response:', data.data ? data.data.category_id : 'no data');
                    console.log('Category name:', categoryName);
                    
                    // Get the category ID from the correct location in the response
                    const categoryId = data.data ? data.data.category_id : null;
                    
                    if (!categoryId) {
                        throw new Error('No category ID returned from server');
                    }
                    
                    const option = document.createElement('option');
                    option.value = categoryId;
                    option.textContent = categoryName;
                    select.appendChild(option);
                    
                    console.log('Option created:', option);
                    console.log('Option value:', option.value);
                    console.log('Option text:', option.textContent);
                    
                    // Set the value and ensure it's properly applied
                    select.value = categoryId;
                    select.selectedIndex = select.options.length - 1;
                    
                    // Force the select to recognize the new value
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    
                    // Verify the value is set correctly
                    console.log('Category select value immediately after setting:', select.value);
                    console.log('Category select selectedIndex:', select.selectedIndex);
                    console.log('Selected option after setting:', select.options[select.selectedIndex]);
                } else {
                    // Updating existing category
                    select.options[select.selectedIndex].text = categoryName;
                }
                
                modal.remove();
                showNotification(`Category ${id ? 'updated' : 'added'} successfully`, 'success');
                
                // Additional safety: ensure the select value is set after modal is removed
                if (!id) {
                    setTimeout(() => {
                        const select = document.getElementById('categorySelect');
                        const categoryId = data.data ? data.data.category_id : null;
                        if (select && categoryId) {
                            select.value = categoryId;
                            select.selectedIndex = Array.from(select.options).findIndex(opt => opt.value === categoryId);
                            console.log('Category select value after timeout:', select.value);
                            console.log('Category select selectedIndex after timeout:', select.selectedIndex);
                            
                            // Trigger change event again to ensure all listeners are notified
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }, 100);
                }
            } else {
                throw new Error(data.message || `Failed to ${id ? 'update' : 'add'} category`);
            }
        } catch (error) {
            showNotification(error.message, 'error');
        } finally {
            if (document.getElementById('categoryModal')) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }
    };
}

// Helper function to notify unsaved changes
function notifyUnsavedChange() {
    if (typeof markAsChanged === 'function') {
        markAsChanged();
    }
}

// Section management functions
function editSection(index) {
    showSectionModal(index);
}

function deleteSection(index) {
    const section = sections[index];
    const sectionTitle = section ? section.title : 'this section';
    
    showModernConfirm(
        'Delete Section',
        `Are you sure you want to delete "${sectionTitle}"? This action cannot be undone and will also remove all chapters in this section.`,
        'Delete',
        'Cancel',
        () => {
            // Proceed with deletion
            sections.splice(index, 1);
            renderSections();
            showNotification('Section deleted successfully', 'success');
            notifyUnsavedChange();
        }
    );
}

// Chapter management functions
function addChapterToSection(sectionId) {
    showChapterModal(null, sectionId);
}

function editChapter(chapterId) {
    const chapterIndex = chapters.findIndex(c => c.id == chapterId);
    if (chapterIndex !== -1) {
        showChapterModal(chapterIndex);
    }
}

function deleteChapter(chapterId) {
    const chapterIndex = chapters.findIndex(c => c.id == chapterId);
    if (chapterIndex !== -1) {
        const chapter = chapters[chapterIndex];
        const chapterTitle = chapter ? chapter.title : 'this chapter';
        
        showModernConfirm(
            'Delete Chapter',
            `Are you sure you want to delete "${chapterTitle}"? This action cannot be undone.`,
            'Delete',
            'Cancel',
            () => {
                // Proceed with deletion
                chapters.splice(chapterIndex, 1);
                renderChapters();
                showNotification('Chapter deleted successfully', 'success');
                notifyUnsavedChange();
            }
        );
    }
}

// Show chapter modal
function showChapterModal(chapterIndex = null, sectionId = null) {
    const isEdit = chapterIndex !== null;
    const chapter = isEdit ? chapters[chapterIndex] : null;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto';
    modal.id = 'chapterModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-8 w-full max-w-6xl my-6 mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold mb-6">${isEdit ? 'Edit' : 'Add'} Chapter</h3>
            <form id="chapterForm">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Chapter Title *</label>
                        <input type="text" id="chapterTitle" class="form-input" value="${isEdit ? escapeHtml(chapter.title) : ''}" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Section *</label>
                        <select id="chapterSection" class="form-select" required ${isEdit ? 'disabled' : ''}>
                            <option value="">Select Section</option>
                            ${sections.map(section => `
                                <option value="${section.id}" ${(isEdit && chapter.section_id === section.id) || (!isEdit && sectionId === section.id) ? 'selected' : ''}>
                                    ${escapeHtml(section.title)}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Description</label>
                        <textarea id="chapterDescription" class="form-textarea" rows="3">${isEdit ? escapeHtml(chapter.description || '') : ''}</textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Content Type *</label>
                        <select id="chapterContentType" class="form-select" required>
                            <option value="text" ${!isEdit || chapter.content_type === 'text' ? 'selected' : ''}>Text Content</option>
                            <option value="video" ${isEdit && chapter.content_type === 'video' ? 'selected' : ''}>Video Content</option>
                        </select>
                    </div>
                </div>
                    
                <div class="mb-6">
                    <div id="contentContainer">
                        <!-- Content field will be dynamically rendered here -->
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                    <button type="button" id="cancelChapter" class="btn btn-secondary px-6 py-2">Cancel</button>
                    <button type="submit" class="btn btn-primary px-6 py-2">${isEdit ? 'Update' : 'Add'} Chapter</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
        // Function to render content field based on type
        function renderContentField() {
            const contentType = document.getElementById('chapterContentType').value;
            const container = document.getElementById('contentContainer');
            
            // Remove any existing hidden textarea that might be causing validation issues
            const existingTextarea = document.getElementById('chapterContent');
            if (existingTextarea) {
                existingTextarea.removeAttribute('required');
                existingTextarea.style.display = 'none';
            }
            
            if (contentType === 'video') {
                container.innerHTML = `
                    <div>
                        <label class="block text-sm font-medium mb-2">Video Type *</label>
                        <div class="mb-4 space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="videoType" value="url" class="mr-2" ${(isEdit && chapter.video_type === 'url') || !isEdit ? 'checked' : ''}>
                                <span>Video URL (YouTube, Vimeo, etc.)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="videoType" value="upload" class="mr-2" ${isEdit && chapter.video_type === 'upload' ? 'checked' : ''}>
                                <span>Upload Video File</span>
                            </label>
                        </div>
                        
                        <div id="videoUrlContainer" class="mb-4">
                            <label class="block text-sm font-medium mb-1">Video URL *</label>
                            <input type="url" id="videoUrl" class="form-input" 
                                   placeholder="https://www.youtube.com/watch?v=..." 
                                   value="${isEdit && chapter.video_type === 'url' ? escapeHtml(chapter.video_url || '') : ''}" required>
                            <p class="text-xs text-gray-500 mt-1">Supports YouTube, Vimeo, and direct video links</p>
                        </div>
                        
                        <div id="videoUploadContainer" class="mb-4" style="display: none;">
                            <label class="block text-sm font-medium mb-1">Upload Video File *</label>
                            <input type="file" id="videoFile" class="form-input" accept="video/*">
                            <p class="text-xs text-gray-500 mt-1">Supports MP4, WebM, AVI, MOV, WMV (Max: 50MB)</p>
                            ${isEdit && chapter.video_type === 'upload' && chapter.video_file_path ? 
                                "<p class=\"text-xs text-green-600 mt-1\">Current file: " + chapter.video_file_path + "</p>" : ''
                            }
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Copyright Notice / Disclaimer</label>
                            <textarea id="videoCopyright" class="form-textarea" rows="3" 
                                      placeholder="Enter copyright notice, attribution, or disclaimer for this video content...">${isEdit && chapter.video_copyright ? escapeHtml(chapter.video_copyright) : ''}</textarea>
                            <p class="text-xs text-gray-500 mt-1">Optional: Add copyright information, attribution, or any disclaimers for the video content</p>
                        </div>
                        
                        <!-- Hidden textarea for video content (no validation) -->
                        <textarea id="chapterContent" class="form-textarea" rows="6" style="display: none;">${isEdit && chapter.content_type === 'video' ? escapeHtml(chapter.content || '') : ''}</textarea>
                    </div>
                `;
            } else {
                container.innerHTML = `
                    <div>
                        <label class="block text-sm font-medium mb-1">Chapter Content *</label>
                                <textarea id="chapterContent" class="form-textarea" rows="6" 
                                  placeholder="Enter the chapter content, learning materials, instructions, etc.">${isEdit && chapter.content_type === 'text' ? escapeHtml(chapter.content || '') : ''}</textarea>
                        <p class="text-xs text-gray-500 mt-1">Use the rich text editor to format your content with headings, lists, links, and more.</p>
                    </div>
                `;
                
                // Initialize TinyMCE for text content
                if (window.tinyMCEChapterEditor) {
                    const currentContent = isEdit && chapter.content_type === 'text' ? (chapter.content || '') : '';
                    window.tinyMCEChapterEditor.initEditor(currentContent);
                }
            }
        
        // Add event listeners for video type radio buttons if video content type
        if (contentType === 'video') {
            const videoTypeRadios = document.querySelectorAll('input[name="videoType"]');
            const videoUrlContainer = document.getElementById('videoUrlContainer');
            const videoUploadContainer = document.getElementById('videoUploadContainer');
            
            function updateVideoInputs() {
                const selectedType = document.querySelector('input[name="videoType"]:checked').value;
                if (selectedType === 'url') {
                    videoUrlContainer.style.display = 'block';
                    videoUploadContainer.style.display = 'none';
                    document.getElementById('videoUrl').required = true;
                    document.getElementById('videoFile').required = false;
                } else {
                    videoUrlContainer.style.display = 'none';
                    videoUploadContainer.style.display = 'block';
                    document.getElementById('videoUrl').required = false;
                    document.getElementById('videoFile').required = !isEdit; // Not required if editing and file already exists
                }
            }
            
            videoTypeRadios.forEach(radio => {
                radio.addEventListener('change', updateVideoInputs);
            });
            
            // Initial update
            updateVideoInputs();
        }
    }
    
        // Content type change handler
        document.getElementById('chapterContentType').addEventListener('change', function() {
            // Destroy existing TinyMCE editor before re-rendering
            if (window.tinyMCEChapterEditor) {
                window.tinyMCEChapterEditor.destroyEditor();
            }
            renderContentField();
        });
    
    // Initial render
    renderContentField();
    
    // Cancel button
    document.getElementById('cancelChapter').onclick = () => {
        // Destroy TinyMCE editor if active
        if (window.tinyMCEChapterEditor && window.tinyMCEChapterEditor.isActive()) {
            window.tinyMCEChapterEditor.destroyEditor();
        }
        modal.remove();
    };
    
    // Form submit
    document.getElementById('chapterForm').onsubmit = function(e) {
        e.preventDefault();
        
        const title = document.getElementById('chapterTitle').value.trim();
        const selectedSectionId = document.getElementById('chapterSection').value;
        const description = document.getElementById('chapterDescription').value.trim();
        const contentType = document.getElementById('chapterContentType').value;
        
        let content = '';
        let videoType = null;
        let videoUrl = null;
        let videoFile = null;
        let videoCopyright = null;
        
        if (contentType === 'video') {
            videoType = document.querySelector('input[name="videoType"]:checked').value;
            videoCopyright = document.getElementById('videoCopyright').value.trim();
            
            if (videoType === 'url') {
                content = document.getElementById('videoUrl').value.trim();
                videoUrl = content;
                
                if (!content) {
                    showNotification('Please enter a video URL', 'error');
                    return;
                }
                
                if (!isValidUrl(content)) {
                    showNotification('Please enter a valid video URL', 'error');
                    return;
                }
            } else {
                videoFile = document.getElementById('videoFile').files[0];
                
                if (!isEdit && !videoFile) {
                    showNotification('Please select a video file to upload', 'error');
                    return;
                }
                
                if (videoFile) {
                    // Validate file size (50MB)
                    if (videoFile.size > 50 * 1024 * 1024) {
                        showNotification('Video file is too large. Maximum size is 50MB.', 'error');
                        return;
                    }
                    
                    // Validate file type
                    const allowedTypes = ['video/mp4', 'video/webm', 'video/avi', 'video/mov', 'video/wmv'];
                    if (!allowedTypes.includes(videoFile.type)) {
                        showNotification('Invalid video file type. Please use MP4, WebM, AVI, MOV, or WMV.', 'error');
                        return;
                    }
                }
            }
        } else {
            // Get content from TinyMCE if available, otherwise from textarea
            if (window.tinyMCEChapterEditor && window.tinyMCEChapterEditor.isActive()) {
                content = window.tinyMCEChapterEditor.getContent();
            } else {
                const textarea = document.getElementById('chapterContent');
                content = textarea ? textarea.value.trim() : '';
            }
            
            if (!content) {
                showNotification('Please enter chapter content', 'error');
                return;
            }
        }
        
        if (!title) {
            showNotification('Chapter title is required', 'error');
            return;
        }
        
        if (!selectedSectionId) {
            showNotification('Please select a section for this chapter', 'error');
            return;
        }
        
        const chapterData = {
            id: isEdit ? chapter.id : "new_chapter_" + Date.now(),
            title: title,
            description: description,
            section_id: selectedSectionId,
            content_type: contentType,
            content: content,
            video_type: videoType,
            video_url: videoUrl,
            video_file: videoFile,
            video_copyright: videoCopyright,
            order_index: isEdit ? chapter.order_index : chapters.filter(c => c.section_id === selectedSectionId).length
        };
        
        if (isEdit) {
            // Show confirmation dialog for update
            const originalTitle = chapter.title;
            showModernConfirm(
                'Update Chapter',
                `Are you sure you want to update "${originalTitle}" to "${title}"? This will save your changes.`,
                'Update',
                'Cancel',
                () => {
                    // Proceed with update
                    chapters[chapterIndex] = chapterData;
                    renderChapters();
                    
                    // Destroy TinyMCE editor if active
                    if (window.tinyMCEChapterEditor && window.tinyMCEChapterEditor.isActive()) {
                        window.tinyMCEChapterEditor.destroyEditor();
                    }
                    
                    modal.remove();
                    showNotification('Chapter updated successfully', 'success');
                    notifyUnsavedChange();
                }
            );
        } else {
            // Add new chapter (no confirmation needed)
            chapters.push(chapterData);
            renderChapters();
            
            // Destroy TinyMCE editor if active
            if (window.tinyMCEChapterEditor && window.tinyMCEChapterEditor.isActive()) {
                window.tinyMCEChapterEditor.destroyEditor();
            }
            
            modal.remove();
            showNotification('Chapter added successfully', 'success');
            notifyUnsavedChange();
        }
    };
}

// Quiz management functions
function showQuizModal(quizIndex = null, sectionId = null) {
    const isEdit = quizIndex !== null;
    const quiz = isEdit ? quizzes[quizIndex] : null;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto';
    modal.id = 'quizModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-8 w-full max-w-6xl my-6 mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold mb-6">${isEdit ? 'Edit' : 'Add'} Quiz</h3>
            <form id="quizForm">
                <!-- Quiz Basic Info - Landscape Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Quiz Title *</label>
                        <input type="text" id="quizTitle" class="form-input" value="${isEdit ? escapeHtml(quiz.title) : ''}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Section *</label>
                        <select id="quizSection" class="form-select" required>
                            <option value="">Select Section</option>
                            ${sections.map(section => `
                                <option value="${section.id}" ${(isEdit && quiz.section_id == section.id) || (!isEdit && sectionId == section.id) ? 'selected' : ''}>
                                    ${escapeHtml(section.title)}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            <i class="fas fa-redo text-blue-500 mr-1"></i>
                            Maximum Retakes Allowed
                        </label>
                        <div class="flex space-x-3 items-center">
                            <input type="number" id="quizMaxRetakes" class="form-input w-32" min="0" max="99" value="${isEdit ? (quiz.max_retakes === -1 ? '' : (quiz.max_retakes || '')) : '3'}" placeholder="Enter number">
                            <label class="flex items-center">
                                <input type="checkbox" id="unlimitedRetakes" class="mr-2" ${isEdit && quiz.max_retakes === -1 ? 'checked' : ''}>
                                <span class="text-sm text-blue-600 font-medium">Unlimited</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Set how many times students can retake this quiz (0 = no retakes)</p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <textarea id="quizDescription" class="form-textarea" rows="2">${isEdit ? escapeHtml(quiz.instructions || quiz.description || '') : ''}</textarea>
                </div>
                
                <!-- Questions Section -->
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-lg font-semibold">Questions</h4>
                        <button type="button" id="addQuestionBtn" class="btn bg-green-600 text-white hover:bg-green-700 px-4 py-2 rounded-lg">
                            <i class="fas fa-plus mr-1"></i> Add Question
                        </button>
                    </div>
                    <div id="questionsContainer" class="max-h-96 overflow-y-auto space-y-4 pr-2 quiz-container">
                        <!-- Questions will be rendered here -->
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                    <button type="button" id="cancelQuiz" class="btn btn-secondary px-6 py-2">Cancel</button>
                    <button type="submit" class="btn btn-primary px-6 py-2">${isEdit ? 'Update' : 'Add'} Quiz</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Handle unlimited retakes checkbox
    const unlimitedCheckbox = document.getElementById('unlimitedRetakes');
    const maxRetakesInput = document.getElementById('quizMaxRetakes');
    
    unlimitedCheckbox.addEventListener('change', function() {
        if (this.checked) {
            maxRetakesInput.value = '';
            maxRetakesInput.disabled = true;
            maxRetakesInput.placeholder = 'Unlimited retakes enabled';
        } else {
            maxRetakesInput.disabled = false;
            maxRetakesInput.placeholder = 'Enter number';
            maxRetakesInput.value = '3'; // Reset to default
        }
    });
    
    // Initialize the state if editing and unlimited is already set
    if (isEdit && quiz.max_retakes === -1) {
        maxRetakesInput.disabled = true;
        maxRetakesInput.placeholder = 'Unlimited retakes enabled';
    }
    
    
    // Initialize questions
    let questions = isEdit && quiz.questions ? [...quiz.questions] : [];
    
    // Render questions
    function renderQuestions() {
        const container = document.getElementById('questionsContainer');
        if (questions.length === 0) {
            container.innerHTML = `
                <div class="text-gray-500 text-center py-4 border border-dashed border-gray-300 rounded">
                    <p>No questions added yet. Click "Add Question" to start.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = questions.map((question, qIndex) => `
            <div class="bg-white p-4 rounded-lg border border-gray-200 hover:shadow-sm transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center space-x-2">
                        <h5 class="font-medium">Question ${qIndex + 1}</h5>
                        <span class="text-xs px-2 py-1 rounded ${question.type === 'pronunciation' ? 'bg-purple-100 text-purple-700' : 
                            question.type === 'true_false' ? 'bg-blue-100 text-blue-700' : 
                            'bg-green-100 text-green-700'}">
                            ${question.type === 'pronunciation' ? 'Pronunciation' : 
                              question.type === 'true_false' ? 'True/False' : 
                              question.type === 'multiple_choice' ? 'Multiple Choice' : question.type}
                        </span>
                    </div>
                    <div class="flex space-x-2">
                        <button type="button" class="text-blue-500 hover:text-blue-700 p-1 rounded transition" onclick="editQuestion(${qIndex})" title="Edit Question">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="text-red-500 hover:text-red-700 p-1 rounded transition" onclick="deleteQuestion(${qIndex})" title="Delete Question">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <p class="text-sm mb-2"><strong>Question:</strong> ${escapeHtml(question.question_text || question.question || question.word || 'No question text')}</p>
                <p class="text-sm mb-2"><strong>Points:</strong> ${question.points || question.score || 1}</p>
                
                ${question.type === 'pronunciation' ? `
                    <div class="text-sm bg-purple-50 p-3 rounded border border-purple-200">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-2">
                            <div><strong class="text-purple-700">Japanese:</strong> ${escapeHtml(question.word || '')}</div>
                            <div><strong class="text-purple-700">Romaji:</strong> ${escapeHtml(question.romaji || '')}</div>
                            <div><strong class="text-purple-700">Meaning:</strong> ${escapeHtml(question.meaning || '')}</div>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <div><strong class="text-purple-700">Accuracy:</strong> ${(question.evaluation?.accuracy_threshold || 0.85) * 100}%</div>
                            ${question.audio_url ? '<div class="text-green-600"><i class="fas fa-volume-up mr-1"></i>Audio uploaded</div>' : '<div class="text-gray-500">No audio</div>'}
                        </div>
                    </div>
                ` : question.type === 'word_definition' && question.word_definition_pairs ? `
                    <div class="text-sm bg-amber-50 p-3 rounded border border-amber-200">
                        <div class="mb-2"><strong class="text-amber-700">Word-Definition Pairs:</strong></div>
                        <ul class="ml-4 text-xs">
                            ${question.word_definition_pairs.map((pair, pIndex) => `
                                <li class="text-gray-700">
                                    ${pIndex + 1}. ${escapeHtml(pair.word)} → ${escapeHtml(pair.definition)}
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                ` : question.type === 'sentence_translation' && question.translation_pairs ? `
                    <div class="text-sm bg-green-50 p-3 rounded border border-green-200">
                        <div class="mb-2"><strong class="text-green-700">Translation Pairs:</strong></div>
                        <ul class="ml-4 text-xs">
                            ${question.translation_pairs.map((pair, pIndex) => `
                                <li class="text-gray-700">
                                    ${pIndex + 1}. ${escapeHtml(pair.japanese)} → ${escapeHtml(pair.english)}
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                ` : question.type === 'fill_blank' && (question.answers || question.correct_answers) ? `
                    <div class="text-sm">
                        <strong>Correct Answer(s):</strong>
                        <div class="ml-4 mt-1 text-green-600 font-medium">
                            ${escapeHtml((question.answers || question.correct_answers).join(', '))}
                        </div>
                    </div>
                ` : question.choices ? `
                    <div class="text-sm">
                        <strong>Choices:</strong>
                        <ul class="ml-4 mt-1">
                            ${question.choices.map((choice, cIndex) => `
                                <li class="${choice.is_correct ? 'text-green-600 font-medium' : 'text-gray-600'}">
                                    ${String.fromCharCode(65 + cIndex)}. ${escapeHtml(choice.text)}
                                    ${choice.is_correct ? ' ✓' : ''}
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                ` : ''}
            </div>
        `).join('');
    }
    
    // Add question function
    window.addQuestion = function() {
        // Show question type picker first, then open question modal with selected type
        showQuestionTypePicker(function(selectedType) {
            // Add the question type to the manager
            if (window.questionTypeManager && typeof window.questionTypeManager.addQuestionType === 'function') {
                window.questionTypeManager.addQuestionType(selectedType);
            }
            
            // Update the question type dropdown if it exists
            const questionTypeSelect = document.getElementById('questionType');
            if (questionTypeSelect) {
                questionTypeSelect.value = selectedType;
                // Trigger change event to update the form
                questionTypeSelect.dispatchEvent(new Event('change'));
            }
            
            showNotification('Question type updated to: ' + getQuestionTypeDisplayName(selectedType), 'success');
            
            // Open the question modal with the selected type
            setTimeout(() => {
                showQuestionModal();
            }, 300);
        });
    };
    
    // Edit question function
    window.editQuestion = function(qIndex) {
        showQuestionModal(qIndex);
    };
    
    // Delete question function
    window.deleteQuestion = function(qIndex) {
        const question = questions[qIndex];
        const questionText = question ? (question.question_text || question.word || 'this question') : 'this question';
        
        showModernConfirm(
            'Delete Question',
            `Are you sure you want to delete "${questionText}"? This action cannot be undone.`,
            'Delete',
            'Cancel',
            () => {
                // Proceed with deletion
                questions.splice(qIndex, 1);
                renderQuestions();
                showNotification('Question deleted successfully', 'success');
            }
        );
    };
    
    // Show question modal
    function showQuestionModal(questionIndex = null) {
        const isEditQuestion = questionIndex !== null;
        const question = isEditQuestion ? questions[questionIndex] : null;
        
        const questionModal = document.createElement('div');
        questionModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]';
        questionModal.id = 'questionModal';
        
        questionModal.innerHTML = `
            <div class="bg-white rounded-lg p-6 w-full max-w-6xl mx-4">
                <h4 class="text-lg font-bold mb-4">${isEditQuestion ? 'Edit' : 'Add'} Question</h4>
                <form id="questionForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Question Type</label>
                            <div class="flex gap-2 items-end border border-gray-200 rounded-md p-2 bg-gray-50/50 question-type-container">
                                <select id="questionType" class="form-select flex-1 border-0 bg-transparent focus:ring-0">
                                    <option value="multiple_choice" ${isEditQuestion && question.type === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                                    <option value="true_false" ${isEditQuestion && question.type === 'true_false' ? 'selected' : ''}>True/False</option>
                                    <option value="fill_blank" ${isEditQuestion && question.type === 'fill_blank' ? 'selected' : ''}>Fill in the Blank</option>
                                    <option value="pronunciation" ${isEditQuestion && question.type === 'pronunciation' ? 'selected' : ''}>Pronunciation Check</option>
                                    <option value="word_definition" ${isEditQuestion && question.type === 'word_definition' ? 'selected' : ''}>Word Definition</option>
                                    <option value="sentence_translation" ${isEditQuestion && question.type === 'sentence_translation' ? 'selected' : ''}>Sentence Translation</option>
                                    <!-- Additional options will be populated by QuestionTypeManager -->
                                </select>
                                <button type="button" id="manageQuestionTypesBtn" class="btn bg-red-500 text-white hover:bg-red-600 text-sm px-3 py-2 whitespace-nowrap border-0">
                                    <i class="fas fa-cog mr-1"></i> Manage
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Points</label>
                            <input type="number" id="questionPoints" class="form-input" min="1" value="${isEditQuestion ? (question.points || question.score || 1) : '1'}" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Question Text *</label>
                        <textarea id="questionText" class="form-textarea" rows="3">${isEditQuestion ? escapeHtml(question.question_text || question.question || '') : ''}</textarea>
                    </div>
                    
                    <!-- Pronunciation Container -->
                    <div id="pronunciationContainer" style="display: none;" class="mb-4 bg-purple-50 border-2 border-dashed border-purple-200 rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium mb-1 text-purple-700">Japanese Word *</label>
                                <input type="text" id="japaneseWord" class="form-input border-purple-300 focus:border-purple-500" placeholder="こんにちは" value="${isEditQuestion && question.type === 'pronunciation' ? escapeHtml(question.word || '') : ''}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-purple-700">Romaji *</label>
                                <input type="text" id="romaji" class="form-input border-purple-300 focus:border-purple-500" placeholder="konnichiwa" value="${isEditQuestion && question.type === 'pronunciation' ? escapeHtml(question.romaji || '') : ''}">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1 text-purple-700">Meaning *</label>
                            <input type="text" id="meaning" class="form-input border-purple-300 focus:border-purple-500" placeholder="Hello" value="${isEditQuestion && question.type === 'pronunciation' ? escapeHtml(question.meaning || '') : ''}">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1 text-purple-700">Reference Audio</label>
                                <input type="file" id="referenceAudio" class="form-input border-2 border-dashed border-purple-300 bg-purple-25" accept=".mp3,.wav">
                                <p class="text-xs text-purple-600 mt-1">Upload MP3 or WAV file for pronunciation reference</p>
                                ${isEditQuestion && question.type === 'pronunciation' && question.audio_url ? 
                                    `<p class="text-xs text-green-600 mt-1">Current audio: ${question.audio_url}</p>` : ''}
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-purple-700">Accuracy Threshold</label>
                                <input type="number" id="accuracyThreshold" class="form-input border-purple-300 focus:border-purple-500" min="0" max="1" step="0.01" value="${isEditQuestion && question.type === 'pronunciation' ? (question.evaluation?.accuracy_threshold || 0.85) : '0.85'}">
                                <p class="text-xs text-purple-600 mt-1">Minimum accuracy score (0.0 - 1.0)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="choicesContainer">
                        <!-- Choices will be rendered here -->
                    </div>
                    
                    <div class="flex justify-between items-center mt-4">
                        <button type="button" id="addMoreQuestionTypeBtn" class="btn btn-outline text-blue-600 border-blue-300 hover:bg-blue-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add More Question Type
                        </button>
                        <div class="flex space-x-3">
                            <button type="button" id="cancelQuestion" class="btn btn-secondary">Cancel</button>
                            <button type="submit" class="btn btn-primary">${isEditQuestion ? 'Update' : 'Add'} Question</button>
                        </div>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(questionModal);
        
        // Initialize "Manage" button functionality
        const manageBtn = document.getElementById('manageQuestionTypesBtn');
        if (manageBtn) {
            manageBtn.onclick = function() {
                if (window.questionTypeManager && typeof window.questionTypeManager.showManagementUI === 'function') {
                    window.questionTypeManager.showManagementUI();
                } else {
                    showNotification('Question Type Manager not available', 'error');
                    console.warn('QuestionTypeManager.showManagementUI not available');
                }
            };
        }
        
        // Initialize "Add More Question Type" button functionality
        const addMoreQuestionTypeBtn = document.getElementById('addMoreQuestionTypeBtn');
        if (addMoreQuestionTypeBtn) {
            addMoreQuestionTypeBtn.onclick = function() {
                showQuestionTypePicker(function(selectedType) {
                    // Add the question type to the manager
                    if (window.questionTypeManager && typeof window.questionTypeManager.addQuestionType === 'function') {
                        window.questionTypeManager.addQuestionType(selectedType);
                    }
                    
                    // Update the question type dropdown
                    const questionTypeSelect = document.getElementById('questionType');
                    if (questionTypeSelect) {
                        // Add the new option if it doesn't exist
                        let optionExists = false;
                        for (let i = 0; i < questionTypeSelect.options.length; i++) {
                            if (questionTypeSelect.options[i].value === selectedType) {
                                optionExists = true;
                                break;
                            }
                        }
                        
                        if (!optionExists) {
                            const newOption = document.createElement('option');
                            newOption.value = selectedType;
                            newOption.textContent = getQuestionTypeDisplayName(selectedType);
                            questionTypeSelect.appendChild(newOption);
                        }
                        
                        questionTypeSelect.value = selectedType;
                        questionTypeSelect.dispatchEvent(new Event('change'));
                    }
                    
                    showNotification('Question type added: ' + getQuestionTypeDisplayName(selectedType), 'success');
                });
            };
        }
        
        // Initialize Question Type Manager dropdown if available
        if (window.questionTypeManager && typeof window.questionTypeManager.populateDropdown === 'function') {
            const questionTypeSelect = document.getElementById('questionType');
            if (questionTypeSelect) {
                window.questionTypeManager.populateDropdown(questionTypeSelect);
            }
        }
        
        // Initialize choices
        let choices = isEditQuestion && question.choices ? [...question.choices] : [
            { text: '', is_correct: false },
            { text: '', is_correct: false },
            { text: '', is_correct: false },
            { text: '', is_correct: false }
        ];
        
        // Initialize question type specific data
        let comprehensionQuestions = [];
        let matchingOptions = [];
        let definitionPairs = [];
        let translationPairs = [];
        
        // Initialize data based on question type when editing
        if (isEditQuestion) {
            if (question.type === 'word_definition' && question.word_definition_pairs) {
                definitionPairs = question.word_definition_pairs.map(pair => ({
                    word: pair.word || '',
                    definition: pair.definition || ''
                }));
            } else if (question.type === 'sentence_translation' && question.translation_pairs) {
                translationPairs = question.translation_pairs.map(pair => ({
                    japanese: pair.japanese || '',
                    english: pair.english || ''
                }));
            } else if (question.type === 'fill_blank' && (question.answers || question.correct_answers)) {
                // Initialize fill_blank answers for editing
                window.fillBlankAnswers = (question.answers || question.correct_answers || []).join(', ');
            }
        }
        
        // Render choices based on question type
        function renderChoices() {
            const container = document.getElementById('choicesContainer');
            const pronunciationContainer = document.getElementById('pronunciationContainer');
            const questionType = document.getElementById('questionType').value;
            const questionTextContainer = document.querySelector('textarea#questionText').parentElement;
            
            // Hide both containers first
            container.style.display = 'none';
            pronunciationContainer.style.display = 'none';
            
            // Try to use QuestionTypeManager for rendering if available
            if (window.questionTypeManager && typeof window.questionTypeManager.renderQuestionForm === 'function') {
                const formData = {
                    questionType: questionType,
                    container: container,
                    pronunciationContainer: pronunciationContainer,
                    questionTextContainer: questionTextContainer,
                    choices: choices,
                    isEditQuestion: isEditQuestion,
                    question: isEditQuestion ? question : null
                };
                
                if (window.questionTypeManager.renderQuestionForm(formData)) {
                    return; // Successfully rendered by QuestionTypeManager
                }
            }
            
            // Fallback rendering for basic question types
            if (questionType === 'pronunciation') {
                // Show pronunciation container and hide choices
                pronunciationContainer.style.display = 'block';
                container.style.display = 'none';
                
                // Update question text label for pronunciation
                questionTextContainer.querySelector('label').textContent = 'Instructions (Optional)';
                document.getElementById('questionText').placeholder = 'e.g., "Please pronounce the following Japanese word"';
                document.getElementById('questionText').required = false;
                
                
                
            } else if (questionType === 'word_definition') {
                // Show choices container for word definition
                container.style.display = 'block';
                pronunciationContainer.style.display = 'none';
                
                questionTextContainer.querySelector('label').textContent = 'Instructions (optional)';
                document.getElementById('questionText').placeholder = 'e.g., "Match each Japanese word with its correct definition"';
                document.getElementById('questionText').required = false;
                
                // Use pre-initialized data or create default
                if (definitionPairs.length === 0) {
                    definitionPairs = [{ word: '', definition: '' }];
                }
                window.definitionPairs = definitionPairs;
                
                // Generate definition pairs HTML
                const definitionPairsHtml = definitionPairs.map((pair, index) => `
                    <div class="definition-pair">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">Japanese Word</label>
                                <input type="text" class="form-input" placeholder="e.g., こんにちは" value="${escapeHtml(pair.word)}" data-pair-index="${index}" data-field="word">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Definition</label>
                                <input type="text" class="form-input" placeholder="e.g., Hello" value="${escapeHtml(pair.definition)}" data-pair-index="${index}" data-field="definition">
                            </div>
                        </div>
                        <button type="button" class="btn btn-danger mt-2" onclick="removeDefinitionPair(${index})" ${window.definitionPairs.length <= 1 ? 'style="display: none;"' : ''}>
                            <i class="fas fa-trash mr-1"></i> Remove
                        </button>
                    </div>
                `).join('');
                
                container.innerHTML = `
                    <div class="word-definition-container fade-in">
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-lg font-semibold text-amber-800">Word-Definition Pairs</h3>
                                <button type="button" id="addDefinitionPair" class="btn btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Add Pair
                                </button>
                            </div>
                            <div id="definitionPairs">
                                ${definitionPairsHtml}
                            </div>
                        </div>
                    </div>
                `;
                
                // Add pair button handler
                document.getElementById('addDefinitionPair').onclick = function() {
                    if (window.definitionPairs.length < 8) {
                        window.definitionPairs.push({ word: '', definition: '' });
                        renderDefinitionPairs();
                    }
                };
                
            } else if (questionType === 'sentence_translation') {
                // Show choices container for sentence translation
                container.style.display = 'block';
                pronunciationContainer.style.display = 'none';
                
                questionTextContainer.querySelector('label').textContent = 'Instructions *';
                document.getElementById('questionText').placeholder = 'e.g., "Translate the following sentences"';
                document.getElementById('questionText').required = true;
                
                // Use pre-initialized data or create default
                if (translationPairs.length === 0) {
                    translationPairs = [{ japanese: '', english: '' }];
                }
                window.translationPairs = translationPairs;
                
                
                // Generate translation pairs HTML
                const translationPairsHtml = translationPairs.map((pair, index) => `
                    <div class="translation-pair">
                        <div>
                            <label class="block text-sm font-medium mb-1">Japanese Sentence</label>
                            <input type="text" class="form-input" placeholder="e.g., 私は学生です。" value="${escapeHtml(pair.japanese)}" data-translation-index="${index}" data-field="japanese">
                        </div>
                        <div class="translation-arrow">→</div>
                        <div>
                            <label class="block text-sm font-medium mb-1">English Translation</label>
                            <input type="text" class="form-input" placeholder="e.g., I am a student." value="${escapeHtml(pair.english)}" data-translation-index="${index}" data-field="english">
                        </div>
                        ${window.translationPairs.length > 1 ? `
                            <button type="button" class="btn btn-danger" onclick="removeTranslationPair(${index})" style="grid-column: 1 / -1; justify-self: start; margin-top: 0.5rem;">
                                <i class="fas fa-trash mr-1"></i> Remove
                            </button>
                        ` : ''}
                    </div>
                `).join('');
                
                container.innerHTML = `
                    <div class="translation-container fade-in">
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-green-800 mb-3">Translation Pairs</h3>
                            <div id="translationPairs">
                                ${translationPairsHtml}
                            </div>
                            <button type="button" id="addTranslationPair" class="btn btn-primary mt-3">
                                <i class="fas fa-plus mr-1"></i> Add Translation
                            </button>
                        </div>
                    </div>
                `;
                
                setupSentenceTranslation();
                
            } else if (questionType === 'fill_blank') {
                // Show choices container for fill in the blank
                container.style.display = 'block';
                pronunciationContainer.style.display = 'none';
                
                // Update question text label for fill in the blank
                questionTextContainer.querySelector('label').textContent = 'Question Text (use _____ for blanks) *';
                document.getElementById('questionText').placeholder = 'e.g., "The cat is _____ the table."';
                document.getElementById('questionText').required = true;
                
                container.innerHTML = `
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Correct Answer(s)</label>
                        <input type="text" id="fillBlankAnswer" class="form-input" placeholder="Enter the correct word/phrase to fill the blank" value="${isEditQuestion && question.type === 'fill_blank' ? escapeHtml(window.fillBlankAnswers || '') : ''}">
                        <p class="text-xs text-gray-500 mt-1">For multiple acceptable answers, separate them with commas (e.g., "on, upon, at")</p>
                    </div>
                `;
                
                
            } else {
                // Standard question types (multiple choice, true/false)
                container.style.display = 'block';
                pronunciationContainer.style.display = 'none';
                
                // Reset question text label
                questionTextContainer.querySelector('label').textContent = 'Question Text *';
                document.getElementById('questionText').placeholder = '';
                document.getElementById('questionText').required = true;
                
                if (questionType === 'true_false') {
                    choices = [
                        { text: 'True', is_correct: isEditQuestion && question.choices ? question.choices[0]?.is_correct || false : false },
                        { text: 'False', is_correct: isEditQuestion && question.choices ? question.choices[1]?.is_correct || false : false }
                    ];
                    
                    container.innerHTML = `
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Correct Answer</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="correctAnswer" value="0" class="mr-2" ${choices[0].is_correct ? 'checked' : ''}>
                                    True
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="correctAnswer" value="1" class="mr-2" ${choices[1].is_correct ? 'checked' : ''}>
                                    False
                                </label>
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-sm font-medium">Answer Choices *</label>
                                <button type="button" id="addChoiceBtn" class="btn bg-blue-600 text-white hover:bg-blue-700 text-xs">
                                    <i class="fas fa-plus mr-1"></i> Add Choice
                                </button>
                            </div>
                            <div id="choicesList">
                                ${choices.map((choice, cIndex) => `
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="text-sm font-medium w-6">${String.fromCharCode(65 + cIndex)}.</span>
                                        <input type="text" class="form-input flex-1" placeholder="Choice text" value="${escapeHtml(choice.text)}" data-choice-index="${cIndex}">
                                        <label class="flex items-center">
                                            <input type="radio" name="correctChoice" value="${cIndex}" class="mr-1" ${choice.is_correct ? 'checked' : ''}>
                                            <span class="text-xs">Correct</span>
                                        </label>
                                        ${choices.length > 2 ? `
                                            <button type="button" class="text-red-500 hover:text-red-700" onclick="removeChoice(${cIndex})">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                    
                    // Add choice button handler
                    document.getElementById('addChoiceBtn').onclick = function() {
                        if (choices.length < 6) {
                            choices.push({ text: '', is_correct: false });
                            renderChoices();
                        }
                    };
                }
            }
        }
        
        // Remove choice function
        window.removeChoice = function(cIndex) {
            if (choices.length > 2) {
                choices.splice(cIndex, 1);
                renderChoices();
            }
        };
        
        
        window.setupSentenceTranslation = function() {
            const addPairBtn = document.getElementById('addTranslationPair');
            
            // Only initialize with empty array if translationPairs doesn't exist or is empty
            if (!window.translationPairs || window.translationPairs.length === 0) {
                window.translationPairs = [{ japanese: '', english: '' }];
            }
            
            addPairBtn.onclick = function() {
                if (window.translationPairs.length < 8) {
                    window.translationPairs.push({ japanese: '', english: '' });
                    renderTranslationPairs();
                }
            };
            
            function renderTranslationPairs() {
                const container = document.getElementById('translationPairs');
                container.innerHTML = window.translationPairs.map((pair, index) => `
                    <div class="translation-pair">
                        <div>
                            <label class="block text-sm font-medium mb-1">Japanese Sentence</label>
                            <input type="text" class="form-input" placeholder="e.g., 私は学生です。" value="${escapeHtml(pair.japanese)}" data-translation-index="${index}" data-field="japanese">
                        </div>
                        <div class="translation-arrow">→</div>
                        <div>
                            <label class="block text-sm font-medium mb-1">English Translation</label>
                            <input type="text" class="form-input" placeholder="e.g., I am a student." value="${escapeHtml(pair.english)}" data-translation-index="${index}" data-field="english">
                        </div>
                        ${window.translationPairs.length > 1 ? `
                            <button type="button" class="btn btn-danger" onclick="removeTranslationPair(${index})" style="grid-column: 1 / -1; justify-self: start; margin-top: 0.5rem;">
                                <i class="fas fa-trash mr-1"></i> Remove
                            </button>
                        ` : ''}
                    </div>
                `).join('');
            }
            
            window.removeTranslationPair = function(index) {
                if (window.translationPairs.length > 1) {
                    window.translationPairs.splice(index, 1);
                    renderTranslationPairs();
                }
            };
            
            renderTranslationPairs();
        };
        
        
        window.renderDefinitionPairs = function() {
            const container = document.getElementById('definitionPairs');
            container.innerHTML = window.definitionPairs.map((pair, index) => `
                <div class="definition-pair">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Japanese Word</label>
                            <input type="text" class="form-input" placeholder="e.g., こんにちは" value="${pair.word}" data-pair-index="${index}" data-field="word">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Definition</label>
                            <input type="text" class="form-input" placeholder="e.g., Hello" value="${pair.definition}" data-pair-index="${index}" data-field="definition">
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger mt-2" onclick="removeDefinitionPair(${index})" ${window.definitionPairs.length <= 1 ? 'style="display: none;"' : ''}>
                        <i class="fas fa-trash mr-1"></i> Remove
                    </button>
                </div>
            `).join('');
        };
        
        window.removeDefinitionPair = function(index) {
            if (window.definitionPairs.length > 1) {
                window.definitionPairs.splice(index, 1);
                renderDefinitionPairs();
            }
        };
        
        // Question type change handler
        document.getElementById('questionType').onchange = function() {
            // Save selected question type to localStorage
            localStorage.setItem('selectedQuestionType', this.value);
            renderChoices();
        };
        
        // Set question type dropdown value
        if (isEditQuestion && question.type) {
            // When editing, set to the question's actual type
            document.getElementById('questionType').value = question.type;
            renderChoices();
        } else {
            // When creating new, restore from localStorage
            const savedQuestionType = localStorage.getItem('selectedQuestionType');
            if (savedQuestionType) {
                document.getElementById('questionType').value = savedQuestionType;
                renderChoices();
            }
        }
        
        // Refresh dropdown with latest saved question types
        if (window.questionTypeManager) {
            window.questionTypeManager.refreshDropdown();
        }
        
        // Initial render of choices
        renderChoices();
        
        // Cancel button
        document.getElementById('cancelQuestion').onclick = () => questionModal.remove();
        
        // Form submit
        document.getElementById('questionForm').onsubmit = function(e) {
            e.preventDefault();
            
            const questionType = document.getElementById('questionType').value;
            const pointsValue = document.getElementById('questionPoints').value;
            const points = pointsValue ? parseInt(pointsValue) : 1;
            
            // Validate points
            if (isNaN(points) || points < 1) {
                showNotification('Please enter a valid number of points (minimum 1)', 'error');
                return;
            }
            
            if (!questionType) {
                showNotification('Please select a question type', 'error');
                return;
            }
            
            let questionData = {
                type: questionType,
                points: points
            };
            
            if (questionType === 'pronunciation') {
                const japaneseWord = document.getElementById('japaneseWord').value.trim();
                const romaji = document.getElementById('romaji').value.trim();
                const meaning = document.getElementById('meaning').value.trim();
                const accuracyThreshold = parseFloat(document.getElementById('accuracyThreshold').value);
                const questionText = document.getElementById('questionText').value.trim();
                
                if (!japaneseWord || !romaji || !meaning) {
                    showNotification('Japanese word, romaji, and meaning are required for pronunciation questions', 'error');
                    return;
                }
                
                // Generate unique ID for pronunciation question
                const pronunciationId = isEditQuestion && question?.id ? question.id : `pronunciation_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
                
                questionData = {
                    type: 'pronunciation',
                    id: pronunciationId,
                    word: japaneseWord,
                    romaji: romaji,
                    meaning: meaning,
                    question: questionText || `Please pronounce the Japanese word: ${japaneseWord}`,
                    points: points,
                    audio_url: null, // Will be handled by file upload in backend
                    evaluation: {
                        expected: japaneseWord,
                        accuracy_threshold: accuracyThreshold
                    },
                    feedback: {
                        correct: "✅ Great job! Your pronunciation was correct.",
                        incorrect: "❌ Try again. Listen carefully and repeat."
                    }
                };
                
                // Handle file upload if present
                const audioFile = document.getElementById('referenceAudio').files[0];
                if (audioFile) {
                    // Store the audio file for later upload
                    questionData.audio_file = audioFile;
                    questionData.audio_url = null; // Will be set after upload
                    console.log('Audio file selected:', audioFile.name);
                }
                
                
                
            } else if (questionType === 'word_definition') {
                const questionText = document.getElementById('questionText').value.trim();
                
                // Collect word-definition pairs
                const wordDefinitionPairs = [];
                const pairElements = document.querySelectorAll('.definition-pair');
                
                pairElements.forEach((pairElement, index) => {
                    const wordInput = pairElement.querySelector('input[data-field="word"]');
                    const definitionInput = pairElement.querySelector('input[data-field="definition"]');
                    
                    if (wordInput && definitionInput) {
                        const word = wordInput.value.trim();
                        const definition = definitionInput.value.trim();
                        
                        if (word && definition) {
                            wordDefinitionPairs.push({
                                word: word,
                                definition: definition
                            });
                        }
                    }
                });
                
                if (wordDefinitionPairs.length === 0) {
                    showNotification('Please add at least one word-definition pair', 'error');
                    return;
                }
                
                questionData = {
                    question: questionText || 'Match the Japanese words with their definitions',
                    type: questionType,
                    points: points,
                    word_definition_pairs: wordDefinitionPairs
                };
                
            } else if (questionType === 'sentence_translation') {
                const questionText = document.getElementById('questionText').value.trim();
                
                if (!questionText) {
                    showNotification('Instructions are required', 'error');
                    return;
                }
                
                // Collect translation pairs
                const translationPairs = [];
                const pairElements = document.querySelectorAll('.translation-pair');
                
                pairElements.forEach((pairElement, index) => {
                    const japaneseInput = pairElement.querySelector('input[data-field="japanese"]');
                    const englishInput = pairElement.querySelector('input[data-field="english"]');
                    
                    if (japaneseInput && englishInput) {
                        const japanese = japaneseInput.value.trim();
                        const english = englishInput.value.trim();
                        
                        if (japanese && english) {
                            translationPairs.push({
                                japanese: japanese,
                                english: english
                            });
                        }
                    }
                });
                
                if (translationPairs.length === 0) {
                    showNotification('Please add at least one translation pair', 'error');
                    return;
                }
                
                questionData = {
                    question: questionText,
                    type: questionType,
                    points: points,
                    translation_pairs: translationPairs
                };
                
            } else {
                const questionText = document.getElementById('questionText').value.trim();
                
                if (!questionText) {
                    showNotification('Question text is required', 'error');
                    return;
                }
                
                // Update choices based on type
                if (questionType === 'fill_blank') {
                    const fillBlankAnswer = document.getElementById('fillBlankAnswer').value.trim();
                    
                    if (!fillBlankAnswer) {
                        showNotification('Correct answer is required for fill in the blank questions', 'error');
                        return;
                    }
                    
                    if (!questionText.includes('_____')) {
                        showNotification('Question text must include _____ to indicate where the blank should be', 'error');
                        return;
                    }
                    
                    // Parse multiple acceptable answers
                    const acceptableAnswers = fillBlankAnswer.split(',').map(answer => answer.trim());
                    
                    questionData = {
                        question: questionText,
                        type: questionType,
                        points: points,
                        answers: acceptableAnswers,
                        correct_answers: acceptableAnswers // For backward compatibility
                    };
                    
                } else if (questionType === 'true_false') {
                    const correctAnswer = document.querySelector('input[name="correctAnswer"]:checked');
                    if (!correctAnswer) {
                        showNotification('Please select the correct answer', 'error');
                        return;
                    }
                    choices[0].is_correct = correctAnswer.value === '0';
                    choices[1].is_correct = correctAnswer.value === '1';
                } else {
                    // Update choice texts
                    document.querySelectorAll('[data-choice-index]').forEach(input => {
                        const index = parseInt(input.dataset.choiceIndex);
                        choices[index].text = input.value.trim();
                    });
                    
                    // Update correct choice
                    const correctChoice = document.querySelector('input[name="correctChoice"]:checked');
                    if (!correctChoice) {
                        showNotification('Please select the correct answer', 'error');
                        return;
                    }
                    
                    choices.forEach((choice, index) => {
                        choice.is_correct = index === parseInt(correctChoice.value);
                    });
                    
                    // Validate at least one choice has text
                    if (!choices.some(choice => choice.text.trim())) {
                        showNotification('Please add at least one choice', 'error');
                        return;
                    }
                }
                
                questionData = {
                    question: questionText,
                    type: questionType,
                    points: points,
                    choices: choices.filter(choice => choice.text.trim() || questionType === 'true_false')
                };
            }
            
            if (isEditQuestion) {
                // Show confirmation dialog for update
                const originalQuestion = question.question_text || question.word || 'this question';
                const newQuestion = questionData.question_text || questionData.word || 'the updated question';
                
                showModernConfirm(
                    'Update Question',
                    `Are you sure you want to update this question? Please review your changes before proceeding.`,
                    'Update',
                    'Cancel',
                    () => {
                        // Proceed with update
                        questions[questionIndex] = questionData;
                        renderQuestions();
                        questionModal.remove();
                        showNotification('Question updated successfully', 'success');
                    }
                );
            } else {
                // Add new question (no confirmation needed)
                questions.push(questionData);
                renderQuestions();
                questionModal.remove();
                showNotification('Question added successfully', 'success');
            }
        };
    }
    
    // Add question button
    document.getElementById('addQuestionBtn').onclick = () => showQuestionModal();
    
    // Initial render
    renderQuestions();
    
    // Cancel button
    document.getElementById('cancelQuiz').onclick = () => modal.remove();
    
    // Form submit
    document.getElementById('quizForm').onsubmit = function(e) {
        e.preventDefault();
        
        const title = document.getElementById('quizTitle').value.trim();
        const sectionId = document.getElementById('quizSection').value;
        const description = document.getElementById('quizDescription').value.trim();
        const unlimitedRetakes = document.getElementById('unlimitedRetakes').checked;
        const maxRetakesValue = document.getElementById('quizMaxRetakes').value;
        
        // Determine max_retakes value
        let maxRetakes;
        if (unlimitedRetakes) {
            maxRetakes = -1; // -1 represents unlimited
        } else if (maxRetakesValue && maxRetakesValue.trim() !== '') {
            maxRetakes = parseInt(maxRetakesValue);
            if (maxRetakes < 0) maxRetakes = 0; // Ensure non-negative
        } else {
            maxRetakes = 3; // Default value
        }
        
        
        if (!title) {
            showNotification('Quiz title is required', 'error');
            return;
        }
        
        if (!sectionId) {
            showNotification('Please select a section', 'error');
            return;
        }
        
        const quizData = {
            id: isEdit ? quiz.id : `new_${Date.now()}`,
            title: title,
            section_id: sectionId,
            description: description,
            max_retakes: maxRetakes,
            questions: questions,
            order_index: isEdit ? quiz.order_index : quizzes.filter(q => q.section_id == sectionId).length
        };
        
        if (isEdit) {
            // Show confirmation dialog for update
            const originalTitle = quiz.title;
            showModernConfirm(
                'Update Quiz',
                `Are you sure you want to update "${originalTitle}" to "${title}"? This will save your changes and update all quiz settings.`,
                'Update',
                'Cancel',
                () => {
                    // Proceed with update
                    const quizIndex = quizzes.findIndex(q => q.id === quiz.id);
                    if (quizIndex !== -1) {
                        quizzes[quizIndex] = quizData;
                    }
                    renderQuizzes();
                    modal.remove();
                    showNotification('Quiz updated successfully', 'success');
                    notifyUnsavedChange();
                }
            );
        } else {
            // Add new quiz (no confirmation needed)
            quizzes.push(quizData);
            renderQuizzes();
            modal.remove();
            showNotification('Quiz added successfully', 'success');
            notifyUnsavedChange();
        }
    };
}

function addQuizToSection(sectionId) {
    showQuizModal(null, sectionId);
}

function editQuiz(quizId) {
    const quizIndex = quizzes.findIndex(q => q.id == quizId);
    if (quizIndex !== -1) {
        showQuizModal(quizIndex);
    }
}

function deleteQuiz(quizId) {
    const quizIndex = quizzes.findIndex(q => q.id == quizId);
    if (quizIndex !== -1) {
        const quiz = quizzes[quizIndex];
        const quizTitle = quiz ? quiz.title : 'this quiz';
        
        showModernConfirm(
            'Delete Quiz',
            `Are you sure you want to delete "${quizTitle}"? This action cannot be undone and will remove all questions in this quiz.`,
            'Delete',
            'Cancel',
            () => {
                // Proceed with deletion
                quizzes.splice(quizIndex, 1);
                renderQuizzes();
                showNotification('Quiz deleted successfully', 'success');
                notifyUnsavedChange();
            }
        );
    }
}


// Save course function
function saveCourse(status) {
    // Mark as saved before submission
    if (typeof markAsSaved === 'function') {
        markAsSaved();
    }
    
    // Validate basic info
    const title = document.getElementById('moduleTitle').value.trim();
    const categorySelect = document.getElementById('categorySelect');
    let categoryId = categorySelect ? categorySelect.value : '';
    
    // If value is undefined or empty, try to get it from selected option
    if ((!categoryId || categoryId === '') && categorySelect && categorySelect.selectedIndex >= 0) {
        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        categoryId = selectedOption ? selectedOption.value : '';
    }
    
    const courseCategoryId = document.getElementById('courseCategorySelect').value;
    const price = document.getElementById('modulePrice').value;
    
    // Debug logging
    console.log('Save Course - Title:', title);
    console.log('Save Course - Category ID:', categoryId);
    console.log('Save Course - Course Category ID:', courseCategoryId);
    console.log('Save Course - Price:', price);
    
    // Additional debugging for category select
    // categorySelect already declared above
    console.log('Category select element:', categorySelect);
    console.log('Category select value:', categorySelect ? categorySelect.value : 'element not found');
    console.log('Category select selectedIndex:', categorySelect ? categorySelect.selectedIndex : 'element not found');
    console.log('Category select options length:', categorySelect ? categorySelect.options.length : 'element not found');
    
    // Debug all options
    if (categorySelect) {
        console.log('All category options:');
        for (let i = 0; i < categorySelect.options.length; i++) {
            const option = categorySelect.options[i];
            console.log(`Option ${i}: value="${option.value}", text="${option.text}", selected=${option.selected}`);
        }
        
        // Try to get value from selected option directly
        if (categorySelect.selectedIndex >= 0) {
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            console.log('Selected option directly:', selectedOption);
            console.log('Selected option value:', selectedOption ? selectedOption.value : 'no option');
            console.log('Selected option text:', selectedOption ? selectedOption.text : 'no option');
        }
    }
    
    if (!title) {
        showNotification('Module title is required', 'error');
        showStep(1);
        return;
    }
    
    if (!categoryId || categoryId === '') {
        // Try to re-read the category value in case of timing issues
        // categorySelect already declared above
        let retryCategoryId = categorySelect ? categorySelect.value : '';
        
        console.log('Category validation failed, retrying...');
        console.log('Original categoryId:', categoryId);
        console.log('Retry categoryId from value:', retryCategoryId);
        
        // If value is still undefined/empty, try to get it from the selected option
        if ((!retryCategoryId || retryCategoryId === '') && categorySelect && categorySelect.selectedIndex >= 0) {
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            retryCategoryId = selectedOption ? selectedOption.value : '';
            console.log('Retry categoryId from selected option:', retryCategoryId);
            
            // If still empty, try to force the select to update its value
            if ((!retryCategoryId || retryCategoryId === '') && selectedOption) {
                // Force the select to recognize the selected option
                categorySelect.selectedIndex = categorySelect.selectedIndex;
                retryCategoryId = categorySelect.value;
                console.log('Force updated categoryId:', retryCategoryId);
            }
        }
        
        if (!retryCategoryId || retryCategoryId === '') {
            showNotification('Module level is required', 'error');
            showStep(1);
            return;
        } else {
            // Use the retry value
            categoryId = retryCategoryId;
            console.log('Using retry categoryId:', categoryId);
        }
    }
    
    if (!courseCategoryId) {
        showNotification('Course category is required', 'error');
        showStep(1);
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('title', title);
    formData.append('description', document.getElementById('moduleDescription').value);
    formData.append('category', categoryId);
    formData.append('course_category_id', courseCategoryId);
    formData.append('price', price);
    formData.append('status', status);
    formData.append('sections', JSON.stringify(sections));
    
    // Process chapters with video files
    const chaptersForSubmission = chapters.map((chapter, index) => {
        const chapterCopy = {...chapter};
        
        // If chapter has a video file, append it to formData with unique name
        if (chapter.video_file && chapter.video_file instanceof File) {
            const fieldName = `chapter_video_${index}_${Date.now()}`;
            formData.append(fieldName, chapter.video_file);
            chapterCopy.video_file_field = fieldName; // Reference to form field
            delete chapterCopy.video_file; // Remove file object from JSON
        }
        
        return chapterCopy;
    });
    
    formData.append('chapters', JSON.stringify(chaptersForSubmission));
    
    // Process quizzes with audio files for pronunciation questions
    const quizzesForSubmission = quizzes.map((quiz, quizIndex) => {
        const quizCopy = { ...quiz };
        if (quizCopy.questions) {
            quizCopy.questions = quizCopy.questions.map((question, questionIndex) => {
                const questionCopy = { ...question };
                
                // If question has an audio file, append it to formData with unique name
                if (question.type === 'pronunciation' && question.audio_file) {
                    const fieldName = "pronunciation_audio_" + quizIndex + "_" + questionIndex;
                    formData.append(fieldName, question.audio_file);
                    questionCopy.audio_file_field = fieldName; // Reference to the file field
                    delete questionCopy.audio_file; // Remove file object from JSON
                }
                
                return questionCopy;
            });
        }
        return quizCopy;
    });
    
    formData.append('quizzes', JSON.stringify(quizzesForSubmission));
    
    // Add image if selected
    const imageFile = document.getElementById('moduleImage').files[0];
    if (imageFile) {
        formData.append('course_image', imageFile);
    }
    
    // Debug logging
    console.log('Form data being sent:');
    console.log('Title:', title);
    console.log('Category ID:', categoryId);
    console.log('Course Category ID:', courseCategoryId);
    console.log('Price:', price);
    console.log('Status:', status);
    console.log('Sections:', sections);
    console.log('Chapters:', chaptersForSubmission);
    console.log('Quizzes:', quizzesForSubmission);
    
    // Validate variables
    if (typeof sections === 'undefined') {
        console.error('Sections variable is undefined');
        sections = [];
    }
    if (typeof chapters === 'undefined') {
        console.error('Chapters variable is undefined');
        chapters = [];
    }
    if (typeof quizzes === 'undefined') {
        console.error('Quizzes variable is undefined');
        quizzes = [];
    }
    
    // Submit
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            // Try to get the response text for debugging
            return response.text().then(text => {
                console.log('Error response body:', text);
                throw new Error(`HTTP error! status: ${response.status} - ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => {
                window.location.href = 'courses_available.php';
            }, 1500);
        } else {
            showNotification(data.message || 'An error occurred while saving the module', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while saving the module: ' + error.message, 'error');
    });
}

// Question Type Picker - Comprehensive N5 Japanese question types
function showQuestionTypePicker(onSelect) {
    // Try to use QuestionTypeManager if available
    if (window.questionTypeManager && typeof window.questionTypeManager.showQuestionTypePicker === 'function') {
        window.questionTypeManager.showQuestionTypePicker(onSelect);
        return;
    }
    
    // Fallback to default picker with EXACT UI matching teacher_create_module.php
    console.warn('Using fallback question type picker');
    
    // Use the question type manager's data
    const questionTypes = window.questionTypeManager ? 
        window.questionTypeManager.getAllQuestionTypes() : 
        getDefaultQuestionTypes();
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70] overflow-y-auto';
    modal.id = 'questionTypePickerModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg w-full max-w-4xl mx-4 my-8 shadow-2xl">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-t-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-2xl font-bold">Choose Question Type</h3>
                    <button type="button" id="closeQuestionTypePicker" class="text-white hover:text-gray-200 text-2xl">×</button>
                </div>
                
                <!-- Search and Filter Controls -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <input type="text" id="questionTypeSearch" placeholder="Search question types..." 
                               class="w-full px-4 py-2 rounded-lg border-0 text-gray-800 placeholder-gray-500">
                    </div>
                    <div class="flex space-x-2">
                        <select id="categoryFilter" class="flex-1 px-3 py-2 rounded-lg border-0 text-gray-800">
                            <option value="">All Categories</option>
                            <option value="Basic">Basic</option>
                            <option value="Vocabulary">Vocabulary</option>
                            <option value="Grammar">Grammar</option>
                            <option value="Audio">Audio</option>
                            <option value="Reading">Reading</option>
                            <option value="Writing">Writing</option>
                        </select>
                        <select id="difficultyFilter" class="flex-1 px-3 py-2 rounded-lg border-0 text-gray-800">
                            <option value="">All Levels</option>
                            <option value="Easy">Easy</option>
                            <option value="Medium">Medium</option>
                            <option value="Hard">Hard</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="p-6">
                <div id="questionTypeGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto">
                    <!-- Question type cards will be rendered here -->
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <span id="questionTypeCount">${questionTypes.length}</span> question types available
                </div>
                <button type="button" id="cancelQuestionTypePicker" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Filter and render functions
    function filterAndRenderQuestionTypes() {
        const searchTerm = document.getElementById('questionTypeSearch').value.toLowerCase();
        const categoryFilter = document.getElementById('categoryFilter').value;
        const difficultyFilter = document.getElementById('difficultyFilter').value;
        
        const filteredTypes = questionTypes.filter(type => {
            const matchesSearch = type.name.toLowerCase().includes(searchTerm) || 
                                type.description.toLowerCase().includes(searchTerm) ||
                                type.category.toLowerCase().includes(searchTerm);
            const matchesCategory = !categoryFilter || type.category === categoryFilter;
            const matchesDifficulty = !difficultyFilter || type.difficulty === difficultyFilter;
            
            return matchesSearch && matchesCategory && matchesDifficulty;
        });
        
        renderQuestionTypes(filteredTypes);
        document.getElementById('questionTypeCount').textContent = filteredTypes.length;
    }
    
    function renderQuestionTypes(types) {
        const grid = document.getElementById('questionTypeGrid');
        
        if (types.length === 0) {
            grid.innerHTML = `
                <div class="col-span-full text-center py-8 text-gray-500">
                    <div class="text-4xl mb-2">🔍</div>
                    <p>No question types found matching your criteria</p>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = types.map(type => `
            <div class="question-type-card bg-white border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 hover:shadow-lg transition-all duration-200"
                 data-type-id="${type.id}"
                 tabindex="0"
                 role="button"
                 aria-describedby="type-${type.id}-description">
                <div class="flex items-start justify-between mb-3">
                    <div class="text-2xl" aria-hidden="true">${type.icon}</div>
                    <span class="text-xs px-2 py-1 rounded-full ${getDifficultyBadgeClass(type.difficulty)}">${type.difficulty}</span>
                </div>
                
                <h4 class="font-semibold text-gray-800 mb-2">${type.name}</h4>
                <p id="type-${type.id}-description" class="text-sm text-gray-600 mb-3 line-clamp-2">${type.description}</p>
                
                <div class="flex flex-wrap gap-1 mb-3" aria-label="Required capabilities">
                    ${type.capabilities.map(cap => `
                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded">${cap}</span>
                    `).join('')}
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-xs text-purple-600 font-medium">${type.category}</span>
                    <span class="text-blue-600 hover:text-blue-800 text-sm font-medium">Select →</span>
                </div>
            </div>
        `).join('');
        
        // Add click handlers
        grid.querySelectorAll('.question-type-card').forEach(card => {
            card.addEventListener('click', function() {
                const typeId = this.dataset.typeId;
                onSelect(typeId);
                modal.remove();
            });
            
            // Add keyboard navigation
            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const typeId = this.dataset.typeId;
                    onSelect(typeId);
                    modal.remove();
                }
            });
        });
    }
    
    function getDifficultyBadgeClass(difficulty) {
        switch(difficulty) {
            case 'Easy': return 'bg-green-100 text-green-700';
            case 'Medium': return 'bg-yellow-100 text-yellow-700';
            case 'Hard': return 'bg-red-100 text-red-700';
            default: return 'bg-gray-100 text-gray-700';
        }
    }
    
    // Event listeners
    document.getElementById('questionTypeSearch').addEventListener('input', filterAndRenderQuestionTypes);
    document.getElementById('categoryFilter').addEventListener('change', filterAndRenderQuestionTypes);
    document.getElementById('difficultyFilter').addEventListener('change', filterAndRenderQuestionTypes);
    
    document.getElementById('closeQuestionTypePicker').addEventListener('click', () => modal.remove());
    document.getElementById('cancelQuestionTypePicker').addEventListener('click', () => modal.remove());
    
    // Initial render
    filterAndRenderQuestionTypes();
    
    // Focus search input
    setTimeout(() => {
        document.getElementById('questionTypeSearch').focus();
    }, 100);
}

// Helper function to get display name for question types
function getQuestionTypeDisplayName(typeId) {
    // Try QuestionTypeManager first
    if (window.questionTypeManager && typeof window.questionTypeManager.getQuestionTypeDisplayName === 'function') {
        return window.questionTypeManager.getQuestionTypeDisplayName(typeId);
    }
    
    // Fallback mapping
    const displayNames = {
        'multiple_choice': 'Multiple Choice',
        'true_false': 'True/False',
        'pronunciation': 'Pronunciation Check',
        'fill_blank': 'Fill in the Blank',
        'grammar_particle': 'Grammar Particle',
        'listening_comprehension': 'Listening Comprehension',
        'sentence_ordering': 'Sentence Ordering',
        'translation': 'Translation',
        'conversation_response': 'Conversation Response'
    };
    
    return displayNames[typeId] || typeId.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Fallback question types for when manager isn't loaded
function getDefaultQuestionTypes() {
    return [
        // Basic Question Types
        {
            id: 'multiple_choice',
            name: 'Multiple Choice',
            category: 'Basic',
            description: 'Traditional multiple choice with 2-6 options',
            capabilities: ['Text'],
            difficulty: 'Easy',
            icon: '📝'
        },
        {
            id: 'true_false',
            name: 'True/False',
            category: 'Basic',
            description: 'Simple true or false questions',
            capabilities: ['Text'],
            difficulty: 'Easy',
            icon: '✓'
        },
        {
            id: 'fill_blank',
            name: 'Fill in the Blank',
            category: 'Basic',
            description: 'Students fill in missing words or characters',
            capabilities: ['Text'],
            difficulty: 'Medium',
            icon: '📄'
        },
        
        // Vocabulary Question Types
        {
            id: 'word_definition',
            name: 'Word Definition',
            category: 'Vocabulary',
            description: 'Match words with their correct definitions',
            capabilities: ['Text'],
            difficulty: 'Medium',
            icon: '📚'
        },
        
        // Audio & Pronunciation
        {
            id: 'pronunciation',
            name: 'Pronunciation Check',
            category: 'Audio',
            description: 'Students pronounce Japanese words for accuracy check',
            capabilities: ['Audio', 'Speech Recognition'],
            difficulty: 'Medium',
            icon: '🎤'
        },
        
        
        // Writing & Translation
        {
            id: 'sentence_translation',
            name: 'Sentence Translation',
            category: 'Writing',
            description: 'Translate between Japanese and English',
            capabilities: ['Text'],
            difficulty: 'Hard',
            icon: '🌐'
        }
    ];
}

// Utility functions
function escapeHtml(text) {
    if (typeof text !== 'string') return text === undefined || text === null ? '' : String(text);
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (e) {
        return false;
    }
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Hide notification after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}