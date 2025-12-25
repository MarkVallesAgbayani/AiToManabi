function quizComponent(quizId) {
    return {
        quizId: quizId,
        loading: false,
        submitted: false,
        questions: [],
        answers: {},
        results: null,
        error: null,
        currentPage: 1,
        pagination: null,
        showSubmitDialog: false,
        

        get isComplete() {
            // Use the same logic as getAnsweredCount() for consistency
            const totalQuestions = this.pagination?.total_questions || 0;
            const answeredCount = this.getAnsweredCount();
            return totalQuestions > 0 && answeredCount >= totalQuestions;
        },

        init() {
            // Initialize showSubmitDialog
            this.showSubmitDialog = false;
            this.showRetakeConfirmation = false;
            this.showRetakeLimitModal = false;
            this.quizCompleted = false;
            this.canRetake = true;
            this.maxRetakes = 3;
            this.attemptCount = 0;
            
            
            console.log('Quiz component initialized, showSubmitDialog:', this.showSubmitDialog);
            console.log('Quiz component quizId:', this.quizId);
            
            // Wait a bit for Alpine store to be fully initialized
            this.$nextTick(() => {
                console.log('Alpine store check:', {
                    hasStore: !!window.Alpine?.store('content'),
                    activeContent: window.Alpine?.store('content')?.activeContent,
                    quizData: window.Alpine?.store('content')?.quizData
                });
                
                // Try to load from store first, fallback to API
                this.loadFromStore();
            });
            
        // Watch for changes in store data
        this.$watch('$store.content.quizData', (newData) => {
            console.log('Store quiz data changed:', newData);
            if (newData && !this.submitted) {
                console.log('Loading quiz data from store change...');
                this.loadFromStore();
            }
        });
        
        // Clean up when page is unloaded
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
        },

        loadLastAttempt() {
            // Check if there's a last attempt in the quiz data
            const storeData = Alpine.store('content').quizData;
            console.log('Checking for last attempt in store data:', storeData);
            
            if (storeData && storeData.last_attempt) {
                console.log('Loading last attempt:', storeData.last_attempt);
                
                // Use the basic attempt data directly
                this.results = {
                    score: parseInt(storeData.last_attempt.score) || 0,
                    total: parseInt(storeData.last_attempt.total_points) || 0,
                    questions: [],
                    attempt_number: storeData.attempt_count || 1
                };
                
                this.submitted = true;
                this.quizCompleted = true;
                this.canRetake = storeData.can_retake || false;
                this.maxRetakes = storeData.max_retakes !== undefined ? storeData.max_retakes : 3;
                this.attemptCount = storeData.attempt_count || 0;
                
                // Update store with quiz completion status
                if (window.Alpine && Alpine.store('content') && Alpine.store('content').quizData) {
                    Alpine.store('content').quizData.quizCompleted = true;
                }
                
                console.log('Quiz results loaded from last attempt:', this.results);
                console.log('Retake info:', {
                    canRetake: this.canRetake,
                    maxRetakes: this.maxRetakes,
                    attemptCount: this.attemptCount
                });
            } else {
                console.log('No last attempt found in store data');
                this.quizCompleted = false;
                this.canRetake = true;
                this.maxRetakes = 3;
                this.attemptCount = 0;
            }
        },

        loadFromStore() {
            // Check if Alpine store is available
            if (!window.Alpine || !window.Alpine.store('content')) {
                console.log('Alpine store not available, loading from API directly');
                this.loadQuiz();
                return;
            }
            
            const storeData = Alpine.store('content').quizData;
            console.log('loadFromStore called with data:', storeData);
            
            // Check if store data exists and has questions
            if (storeData && !storeData.error && storeData.questions && storeData.questions.length > 0) {
                console.log('Loading quiz data from store...');
                this.questions = storeData.questions || [];
                this.pagination = storeData.pagination || null;
                this.currentPage = storeData.pagination?.current_page || 1;
                this.loading = false;
                this.error = null;
                
                // Set retake information
                this.maxRetakes = storeData.max_retakes !== undefined ? storeData.max_retakes : 3;
                this.attemptCount = storeData.attempt_count || 0;
                this.canRetake = storeData.can_retake || false;
                
                // Check for last attempt immediately after loading store data
                if (storeData.last_attempt) {
                    console.log('Found last attempt in store data, loading it...');
                    this.loadLastAttempt();
                } else {
                    // No last attempt, quiz not completed yet
                    console.log('No last attempt found, quiz not completed');
                    this.submitted = false;
                    this.quizCompleted = false;
                    this.results = null;
                    
                }
            } else {
                // No store data or missing questions, load from API immediately
                console.log('No valid store data found, loading from API...');
                this.loadQuiz();
            }
        },

        async loadQuiz(page = 1) {
            this.loading = true;
            this.error = null;
            this.questions = [];
            this.results = null;
            this.submitted = false;
            this.currentPage = page;

            try {
                // Get the section ID from the Alpine store (activeContent)
                let sectionId = null;
                
                if (window.Alpine && window.Alpine.store('content')) {
                    sectionId = Alpine.store('content').activeContent;
                } else {
                    console.error('Alpine store not available');
                    this.error = 'System not ready. Please refresh the page.';
                    this.loading = false;
                    return;
                }
                
                console.log('loadQuiz called with sectionId:', sectionId, 'page:', page);
                
                if (!sectionId) {
                    console.error('No section selected - activeContent is null or undefined');
                    this.error = 'No section selected';
                    this.loading = false;
                    return;
                }
                
                console.log('Fetching quiz data from API...');
                const response = await fetch(`api/get_quiz.php?section_id=${sectionId}&page=${page}`);
                
                // Check if response is ok
                if (!response.ok) {
                    console.error('API response not ok:', response.status, response.statusText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Get response text first to debug
                const responseText = await response.text();
                console.log('Raw API response:', responseText);
                
                // Try to parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                    console.log('Parsed API data:', data);
                } catch (jsonError) {
                    console.error('JSON parsing error:', jsonError);
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (data.error) {
                    console.error('API returned error:', data.error);
                    this.error = data.error;
                    this.loading = false;
                    return;
                }

                this.questions = data.questions || [];
                this.pagination = data.pagination || null;
                
                // Set quiz completion status from API data
                this.quizCompleted = data.quizCompleted || false;
                this.submitted = data.quizCompleted || false;
                
                // Update store with quiz completion status
                if (window.Alpine && Alpine.store('content') && Alpine.store('content').quizData) {
                    Alpine.store('content').quizData.quizCompleted = this.quizCompleted;
                }
                
                // Set retake information but don't load last attempt for retakes
                this.maxRetakes = data.max_retakes !== undefined ? data.max_retakes : 3;
                this.attemptCount = data.attempt_count || 0;
                this.canRetake = data.can_retake || false;
                
                // If quiz is completed, load the last attempt results
                if (this.quizCompleted && data.last_attempt) {
                    this.results = {
                        score: parseInt(data.last_attempt.score) || 0,
                        total: parseInt(data.last_attempt.total_points) || 0,
                        questions: [],
                        attempt_number: data.attempt_count || 1
                    };
                    console.log('Loaded last attempt results:', this.results);
                }
                
                console.log('loadQuiz completed:', {
                    questionsCount: this.questions.length,
                    submitted: this.submitted,
                    quizCompleted: this.quizCompleted,
                    canRetake: this.canRetake
                });
                
                // Load saved answers from localStorage
                this.loadSavedAnswers();
                
                this.loading = false;
            } catch (error) {
                console.error('Error loading quiz:', error);
                console.error('Error details:', {
                    name: error.name,
                    message: error.message,
                    stack: error.stack
                });
                this.error = 'Failed to load quiz. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        async nextPage() {
            if (this.pagination && this.pagination.has_next) {
                // Save current answers before navigating
                this.saveCurrentAnswers();
                await this.loadQuiz(this.pagination.next_page);
            }
        },

        async previousPage() {
            if (this.pagination && this.pagination.has_previous) {
                // Save current answers before navigating
                this.saveCurrentAnswers();
                await this.loadQuiz(this.pagination.previous_page);
            }
        },

        saveCurrentAnswers() {
            // Save answers from current page to localStorage for persistence
            const currentAnswers = {};
            this.questions.forEach(question => {
                // Handle different question types
                if (question.type === 'word_definition' && question.word_definition_pairs) {
                    // For word definition questions, save all pair answers
                    question.word_definition_pairs.forEach((pair, index) => {
                        const answerKey = question.id + '_' + index;
                        if (this.answers[answerKey] !== undefined) {
                            currentAnswers[answerKey] = this.answers[answerKey];
                        }
                    });
                } else if (question.type === 'sentence_translation' && question.translation_pairs) {
                    // For sentence translation questions, save all pair answers
                    question.translation_pairs.forEach((pair, index) => {
                        const answerKey = question.id + '_' + index;
                        if (this.answers[answerKey] !== undefined) {
                            currentAnswers[answerKey] = this.answers[answerKey];
                        }
                    });
                } else {
                    // For other question types, save the main answer
                    if (this.answers[question.id] !== undefined) {
                        currentAnswers[question.id] = this.answers[question.id];
                    }
                }
            });
            
            // Merge with existing saved answers
            const savedAnswers = JSON.parse(localStorage.getItem(`quiz_answers_${this.quizId}`) || '{}');
            const allAnswers = { ...savedAnswers, ...currentAnswers };
            localStorage.setItem(`quiz_answers_${this.quizId}`, JSON.stringify(allAnswers));
        },

        loadSavedAnswers() {
            // Load previously saved answers from localStorage
            const savedAnswers = JSON.parse(localStorage.getItem(`quiz_answers_${this.quizId}`) || '{}');
            this.answers = { ...this.answers, ...savedAnswers };
        },

        onAnswerChange() {
            // Save current answer immediately when changed
            this.saveCurrentAnswers();
            // Force reactivity update for progress display
            this.$nextTick(() => {
                // Trigger reactivity by accessing the computed properties
                this.getAnsweredCount();
                this.getProgressPercentage();
            });
        },

        getAnsweredCount() {
            // Get count of answered questions from localStorage
            const savedAnswers = JSON.parse(localStorage.getItem(`quiz_answers_${this.quizId}`) || '{}');
            
            // Count unique question IDs that are answered (not individual pairs)
            const answeredQuestionIds = new Set();
            
            // Check saved answers
            Object.keys(savedAnswers).forEach(key => {
                // Extract question ID from key (handle both direct question.id and question.id_index formats)
                const questionId = key.split('_')[0];
                answeredQuestionIds.add(questionId);
            });
            
            // Also check current page answers in case they haven't been saved yet
            this.questions.forEach(question => {
                let isQuestionAnswered = false;
                
                // Handle different question types
                if (question.type === 'word_definition' && question.word_definition_pairs) {
                    // For word definition questions, check if all pairs are answered
                    const allPairsAnswered = question.word_definition_pairs.every((pair, index) => {
                        const answer = this.answers[question.id + '_' + index];
                        return answer !== undefined && answer !== null && answer !== '' && 
                               (typeof answer !== 'string' || answer.trim() !== '');
                    });
                    isQuestionAnswered = allPairsAnswered;
                } else if (question.type === 'sentence_translation' && question.translation_pairs) {
                    // For sentence translation questions, check if all pairs are answered
                    const allPairsAnswered = question.translation_pairs.every((pair, index) => {
                        const answer = this.answers[question.id + '_' + index];
                        return answer !== undefined && answer !== null && answer !== '' && 
                               (typeof answer !== 'string' || answer.trim() !== '');
                    });
                    isQuestionAnswered = allPairsAnswered;
                } else {
                    // For other question types, check the main answer
                    const answer = this.answers[question.id];
                    if (answer !== undefined && answer !== null && answer !== '') {
                        // For text-based answers, check if it's not just whitespace
                        if (typeof answer === 'string' && answer.trim() !== '') {
                            isQuestionAnswered = true;
                        } else if (typeof answer !== 'string') {
                            // For non-string answers (like audio URLs, numbers, etc.)
                            isQuestionAnswered = true;
                        }
                    }
                }
                
                if (isQuestionAnswered) {
                    answeredQuestionIds.add(question.id.toString());
                }
            });
            
            const totalAnswered = answeredQuestionIds.size;
            
            console.log('Answered count:', totalAnswered, 'Answered questions:', Array.from(answeredQuestionIds));
            return totalAnswered;
        },

        getProgressPercentage() {
            // Get progress percentage
            const totalQuestions = this.pagination?.total_questions || 0;
            const answeredCount = this.getAnsweredCount();
            return totalQuestions > 0 ? (answeredCount / totalQuestions) * 100 : 0;
        },

        testDialog() {
            console.log('Testing dialog, current showSubmitDialog:', this.showSubmitDialog);
            this.showSubmitDialog = true;
            console.log('Dialog should now be visible, showSubmitDialog:', this.showSubmitDialog);
        },


        async submitQuiz() {
            
            // Save current answers before submitting
            this.saveCurrentAnswers();
            
            // Get all answers from localStorage
            const allAnswers = JSON.parse(localStorage.getItem(`quiz_answers_${this.quizId}`) || '{}');
            
            // Process pronunciation answers to send base64 data to server
            const processedAnswers = this.processPronunciationAnswers(allAnswers);
            
            // Check if all questions are answered
            const totalQuestions = this.pagination?.total_questions || 0;
            const answeredQuestions = this.getAnsweredCount();
            
            console.log('Submit quiz debug:', {
                quizId: this.quizId,
                totalQuestions: totalQuestions,
                answeredQuestions: answeredQuestions,
                allAnswers: allAnswers,
                processedAnswers: processedAnswers
            });
            
            // Enforce "answer all questions" before submitting
            if (answeredQuestions < totalQuestions) {
                // Show user-friendly notification instead of alert
                if (typeof showNotification === 'function') {
                    showNotification(`Please answer all questions before submitting. You have answered ${answeredQuestions} out of ${totalQuestions} questions.`, 'warning');
                } else {
                    console.warn(`Please answer all questions before submitting. You have answered ${answeredQuestions} out of ${totalQuestions} questions.`);
                }
                return;
            }

            try {
                const requestData = {
                    quiz_id: this.quizId,
                    answers: processedAnswers
                };
                
                console.log('Sending quiz submission:', requestData);
                
                const response = await fetch('submit_quiz.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('JSON parsing error:', jsonError);
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (data.error) {
                    this.error = data.error;
                    return;
                }
                
                if (!data.success) {
                    console.error('Quiz submission failed:', data.message);
                    if (data.debug) {
                        console.error('Debug info:', data.debug);
                    }
                    
                // If submission failed due to retake limits
                if (data.message && data.message.includes('maximum number of retakes')) {
                    console.log('Retake limit reached');
                    this.showNotification('Maximum retakes reached.', 'warning');
                    return;
                }
                    
                    this.error = data.message || 'Failed to submit quiz. Please try again.';
                    return;
                }

                this.results = {
                    score: data.score || 0,
                    total: data.total_points || 0,
                    questions: data.questions || [],
                    attempt_number: data.attempt_number || 1
                };
                
                // Update retake information from API response
                this.maxRetakes = data.max_retakes !== undefined ? data.max_retakes : (this.maxRetakes !== undefined ? this.maxRetakes : 3);
                this.attemptCount = data.attempt_number || this.attemptCount || 0;
                this.canRetake = data.can_retake !== undefined ? data.can_retake : this.canRetake;
                
                console.log('Quiz results received:', this.results);
                console.log('Retake info updated:', {
                    maxRetakes: this.maxRetakes,
                    attemptCount: this.attemptCount,
                    canRetake: this.canRetake,
                    retakesExhausted: data.retakes_exhausted
                });
                
                this.submitted = true;
                this.quizCompleted = true;
                
                // Update store with quiz completion status
                if (window.Alpine && Alpine.store('content') && Alpine.store('content').quizData) {
                    Alpine.store('content').quizData.quizCompleted = true;
                }
                
                // Check if retakes are exhausted and redirect to dashboard
                this.checkRetakeStatusAndRedirect();
                
                // Update progress after successful quiz submission
                this.updateProgressAfterQuizSubmission();
                
                // Clear saved answers after successful submission
                localStorage.removeItem(`quiz_answers_${this.quizId}`);
                
            } catch (error) {
                console.error('Error submitting quiz:', error);
                console.error('Error details:', {
                    name: error.name,
                    message: error.message,
                    stack: error.stack
                });
                this.error = 'Failed to submit quiz. Please try again.';
            }
        },

        retryQuiz() {
            console.log('retryQuiz called');
            this.submitted = false;
            this.answers = {};
            this.results = null;
            this.quizCompleted = false;
            this.loading = true;
            
            
            // Clear saved answers when retrying
            localStorage.removeItem(`quiz_answers_${this.quizId}`);
            localStorage.removeItem(`quiz_progress_${this.quizId}`);
            
            // Re-enable quiz interactions
            this.enableQuizInteractions();
            
            // Force a fresh quiz load from API instead of using store data
            this.loadQuiz();
        },

        showRetakeDialog() {
            console.log('showRetakeDialog called', {
                canRetake: this.canRetake,
                maxRetakes: this.maxRetakes,
                attemptCount: this.attemptCount,
                quizCompleted: this.quizCompleted,
                submitted: this.submitted
            });
            
            if (!this.canRetake) {
                console.log('Retake limit reached, showing limit modal');
                this.showRetakeLimitModal = true;
                return;
            }
            console.log('Retake allowed, showing confirmation modal');
            this.showRetakeConfirmation = true;
        },

        confirmRetake() {
            console.log('confirmRetake called');
            this.showRetakeConfirmation = false;
            this.retryQuiz();
        },

        cancelRetake() {
            this.showRetakeConfirmation = false;
        },

        getRetakeMessage() {
            if (this.maxRetakes === -1) {
                return `This quiz is already complete. You want to retake? (Unlimited retakes allowed)`;
            } else if (this.maxRetakes === 0) {
                return `This quiz is already complete. No retakes allowed.`;
            } else {
                // Total allowed attempts = maxRetakes + 1 (initial attempt + retakes)
                // Remaining attempts = total_allowed - current_attempts
                const totalAllowedAttempts = this.maxRetakes + 1;
                const remainingAttempts = totalAllowedAttempts - this.attemptCount;
                const remainingRetakes = remainingAttempts - 1; // Subtract 1 because we're showing retakes, not total attempts
                
                if (remainingRetakes <= 0) {
                    return `This quiz is already complete. No retakes remaining.`;
                } else {
                    return `This quiz is already complete. You want to retake? (${remainingRetakes} retake${remainingRetakes !== 1 ? 's' : ''} remaining)`;
                }
            }
        },

        updateProgressAfterQuizSubmission() {
            // Update the Next button after quiz submission
            if (window.Alpine && Alpine.store('content')) {
                setTimeout(() => {
                    Alpine.store('content').updateNextButton();
                }, 200);
            }
            
            // Update progress display in real-time
            this.updateProgressDisplay();
            
            // Check if this is the last quiz in the course
            this.checkCourseCompletion();
            
            // Update any progress indicators
            console.log('📊 Quiz completed - progress updated');
        },
        
        checkRetakeStatusAndRedirect() {
            // Check if retakes are exhausted (0 retakes or max retakes reached)
            const maxRetakes = this.maxRetakes !== undefined ? this.maxRetakes : 3;
            const attemptCount = this.attemptCount || 0;
            
            console.log('Checking retake status:', {
                maxRetakes: maxRetakes,
                attemptCount: attemptCount,
                canRetake: this.canRetake
            });
            
            // If no retakes allowed (0) or max retakes reached
            if (maxRetakes === 0 || (maxRetakes > 0 && attemptCount >= (maxRetakes + 1)) || !this.canRetake) {
                console.log('Retakes exhausted');
                
                // Show notification
                this.showNotification('No retakes remaining.', 'warning');
            }
        },

        checkCourseCompletion() {
            // Check if this quiz completion means the course is complete
            fetch(`../api/get_course_progress.php?course_id=${this.getCourseId()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.progress >= 100) {
                        console.log('🎉 Course is now 100% complete!');
                        // Optionally show a completion message
                        if (window.videoProgressTracker && window.videoProgressTracker.showErrorNotification) {
                            window.videoProgressTracker.showErrorNotification('🎉 Congratulations! You have completed the entire course!');
                        } else {
                            alert('🎉 Congratulations! You have completed the entire course!');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking course completion:', error);
                });
        },

        updateProgressDisplay() {
            // Fetch updated progress from server
            fetch(`../api/get_course_progress.php?course_id=${this.getCourseId()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.progress !== undefined) {
                        // Update the progress display
                        const progressElements = document.querySelectorAll('[class*="text-blue-600"]');
                        const progressBars = document.querySelectorAll('.bg-blue-600.h-2.rounded-full');
                        
                        progressElements.forEach(element => {
                            if (element.textContent.includes('%')) {
                                element.textContent = `${data.progress}%`;
                            }
                        });
                        
                        progressBars.forEach(bar => {
                            bar.style.width = `${data.progress}%`;
                        });
                        
                        // Update section progress display
                        if (data.sections && data.sections.length > 0) {
                            data.sections.forEach(section => {
                                const sectionElement = document.querySelector(`[data-section-id="${section.section_id}"]`);
                                if (sectionElement) {
                                    const progressText = sectionElement.querySelector('[class*="text-blue-600"]');
                                    const progressBar = sectionElement.querySelector('.bg-blue-600.h-2.rounded-full');
                                    
                                    if (progressText && progressText.textContent.includes('/')) {
                                        progressText.textContent = `${section.completed_items}/${section.total_items}`;
                                    }
                                    
                                    if (progressBar) {
                                        progressBar.style.width = `${section.completion_percentage}%`;
                                    }
                                }
                            });
                        }
                        
                        console.log('📊 Progress updated to:', data.progress + '%');
                        console.log('📊 Section details:', data.sections);
                    }
                })
                .catch(error => {
                    console.error('Error updating progress:', error);
                });
        },

        getCourseId() {
            // Extract course ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('id') || '<?php echo $course_id; ?>';
        },

        recordPronunciation(questionId) {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.showNotification('Audio recording is not supported in this browser.', 'error');
                return;
            }

            // Set recording state
            this.isRecording = true;
            
            // Show recording status
            this.showNotification('Requesting microphone access...', 'info');
            
            // Request microphone permission
            navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 44100
                }
            })
                .then(stream => {
                    this.showNotification('Microphone access granted! Starting recording...', 'success');
                    
                    // Check for supported audio formats
                    let mimeType = 'audio/webm';
                    if (MediaRecorder.isTypeSupported('audio/webm')) {
                        mimeType = 'audio/webm';
                    } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                        mimeType = 'audio/mp4';
                    } else if (MediaRecorder.isTypeSupported('audio/wav')) {
                        mimeType = 'audio/wav';
                    }
                    
                    const mediaRecorder = new MediaRecorder(stream, { mimeType: mimeType });
                    const audioChunks = [];

                    mediaRecorder.ondataavailable = event => {
                        audioChunks.push(event.data);
                    };

                    mediaRecorder.onstop = async () => {
                        this.isRecording = false; // Stop recording state
                        const audioBlob = new Blob(audioChunks, { type: mimeType });
                        
                        // Convert audio to base64 for server processing
                        const audioBase64 = await this.convertBlobToBase64(audioBlob);
                        
                        // Perform client-side speech recognition for immediate feedback
                        const recognitionResult = await this.performClientSideRecognition(audioBlob, questionId);
                        
                        // Store both the audio URL and base64 data
                        this.answers[questionId] = {
                            audioUrl: URL.createObjectURL(audioBlob),
                            audioData: audioBase64,
                            recognitionResult: recognitionResult,
                            mimeType: mimeType
                        };
                        
                        this.showNotification('Recording completed! Your pronunciation has been saved.', 'success');
                        this.onAnswerChange(); // Trigger answer change event
                    };

                    // Start recording
                    mediaRecorder.start();
                    this.showNotification('🎤 Recording... Speak now for 5 seconds...', 'info');
                    
                    // Stop recording after 5 seconds
                    setTimeout(() => {
                        if (mediaRecorder.state === 'recording') {
                            mediaRecorder.stop();
                            stream.getTracks().forEach(track => track.stop());
                        }
                    }, 5000);
                })
                .catch(error => {
                    this.isRecording = false; // Stop recording state on error
                    console.error('Error accessing microphone:', error);
                    
                    let errorMessage = '';
                    let helpMessage = '';
                    
                    if (error.name === 'NotAllowedError') {
                        errorMessage = 'Microphone access denied.';
                        helpMessage = 'Please click the microphone icon in your browser\'s address bar and allow microphone access, then try again.';
                    } else if (error.name === 'NotFoundError') {
                        errorMessage = 'No microphone found.';
                        helpMessage = 'Please connect a microphone to your device and ensure it\'s working in other applications.';
                    } else if (error.name === 'NotSupportedError') {
                        errorMessage = 'Microphone access not supported.';
                        helpMessage = 'Your browser doesn\'t support microphone recording. Please use Chrome, Firefox, or Edge.';
                    } else if (error.name === 'NotReadableError') {
                        errorMessage = 'Microphone is being used by another application.';
                        helpMessage = 'Please close other applications that might be using your microphone and try again.';
                    } else {
                        errorMessage = 'Microphone access error.';
                        helpMessage = 'Please check your microphone connection and browser permissions.';
                    }
                    
                    this.showNotification(errorMessage, 'error');
                    
                    // Show detailed help after a short delay
                    setTimeout(() => {
                        this.showNotification(helpMessage, 'info');
                    }, 2000);
                });
        },

        // Convert blob to base64 for server processing
        async convertBlobToBase64(blob) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    const base64 = reader.result.split(',')[1]; // Remove data:audio/webm;base64, prefix
                    resolve(base64);
                };
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        },

        // Perform client-side speech recognition for immediate feedback
        async performClientSideRecognition(audioBlob, questionId) {
            try {
                // Check if Web Speech API is available
                if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                    console.log('Web Speech API not available, skipping client-side recognition');
                    return null;
                }

                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                const recognition = new SpeechRecognition();
                
                recognition.lang = 'ja-JP'; // Japanese
                recognition.continuous = false;
                recognition.interimResults = false;
                recognition.maxAlternatives = 1;

                return new Promise((resolve) => {
                    recognition.onresult = (event) => {
                        const result = event.results[0][0].transcript;
                        console.log('Client-side recognition result:', result);
                        resolve({
                            recognizedText: result,
                            confidence: event.results[0][0].confidence || 0.8
                        });
                    };

                    recognition.onerror = (event) => {
                        console.log('Client-side recognition error:', event.error);
                        resolve(null);
                    };

                    recognition.onend = () => {
                        console.log('Client-side recognition ended');
                    };

                    // Start recognition
                    recognition.start();
                });
            } catch (error) {
                console.error('Error in client-side recognition:', error);
                return null;
            }
        },

        // Process pronunciation answers to send base64 data and recognition results to server
        processPronunciationAnswers(answers) {
            const processedAnswers = { ...answers };
            
            // Find pronunciation questions and process their answers
            this.questions.forEach(question => {
                if (question.type === 'pronunciation' && processedAnswers[question.id]) {
                    const answer = processedAnswers[question.id];
                    
                    // If it's an object with audioData and recognition result
                    if (typeof answer === 'object' && answer.audioData) {
                        // Send both audio data and recognition result
                        processedAnswers[question.id] = {
                            audioData: `data:audio/webm;base64,${answer.audioData}`,
                            recognizedText: answer.recognitionResult?.recognizedText || '',
                            confidence: answer.recognitionResult?.confidence || 0
                        };
                    }
                    // If it's just a blob URL, we can't process it server-side
                    else if (typeof answer === 'string' && answer.startsWith('blob:')) {
                        // For blob URLs, we'll let the server handle it with fallback
                        processedAnswers[question.id] = answer;
                    }
                }
            });
            
            return processedAnswers;
        },

        // Helper function to show notifications
        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
            
            // Set colors based on type
            switch(type) {
                case 'success':
                    notification.className += ' bg-green-500 text-white';
                    break;
                case 'error':
                    notification.className += ' bg-red-500 text-white';
                    break;
                case 'info':
                    notification.className += ' bg-blue-500 text-white';
                    break;
                default:
                    notification.className += ' bg-gray-500 text-white';
            }
            
            notification.textContent = message;
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            // Remove after 4 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        },

        
        
        
        
        
        
        
        
        
        saveProgressToLocalStorage() {
            // Save quiz answers to localStorage
            const progressData = {
                quizId: this.quizId,
                timestamp: Date.now(),
                answers: this.answers
            };
            localStorage.setItem(`quiz_progress_${this.quizId}`, JSON.stringify(progressData));
        },
        
        loadProgressFromLocalStorage() {
            const progressData = localStorage.getItem(`quiz_progress_${this.quizId}`);
            if (progressData) {
                try {
                    const data = JSON.parse(progressData);
                    this.answers = data.answers || {};
                    console.log(`Restored quiz progress for quiz ${this.quizId}`);
                    return true;
                } catch (error) {
                    console.error('Error loading quiz progress:', error);
                    localStorage.removeItem(`quiz_progress_${this.quizId}`);
                }
            }
            return false;
        },
        
        disableQuizInteractions() {
            // Disable all quiz inputs and buttons
            const quizContainer = document.querySelector('.quiz-container');
            if (quizContainer) {
                // Disable all form inputs
                const inputs = quizContainer.querySelectorAll('input, select, textarea, button');
                inputs.forEach(input => {
                    input.disabled = true;
                    input.classList.add('opacity-50', 'cursor-not-allowed');
                });
                
                // Add overlay to prevent interactions
                const overlay = document.createElement('div');
                overlay.id = 'quiz-disabled-overlay';
                overlay.className = 'absolute inset-0 bg-black bg-opacity-20 z-10 flex items-center justify-center';
                overlay.innerHTML = `
                    <div class="bg-red-600 text-white px-6 py-4 rounded-lg shadow-lg text-center">
                        <div class="text-2xl mb-2">⏰</div>
                        <div class="font-bold">Time's Up!</div>
                        <div class="text-sm">Quiz is being submitted automatically...</div>
                    </div>
                `;
                quizContainer.style.position = 'relative';
                quizContainer.appendChild(overlay);
            }
        },
        
        enableQuizInteractions() {
            // Re-enable quiz interactions (for retakes)
            const quizContainer = document.querySelector('.quiz-container');
            if (quizContainer) {
                // Remove overlay
                const overlay = document.getElementById('quiz-disabled-overlay');
                if (overlay) {
                    overlay.remove();
                }
                
                // Re-enable all form inputs
                const inputs = quizContainer.querySelectorAll('input, select, textarea, button');
                inputs.forEach(input => {
                    input.disabled = false;
                    input.classList.remove('opacity-50', 'cursor-not-allowed');
                });
            }
        },
        
        // Cleanup method
        cleanup() {
            console.log('Cleaning up quiz component');
            // Clean up localStorage data
            localStorage.removeItem(`quiz_progress_${this.quizId}`);
        }
    };
}

