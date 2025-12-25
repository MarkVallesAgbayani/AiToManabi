// Video Progress Tracking System
class VideoProgressTracker {
    constructor() {
        this.videos = new Map();
        this.iframes = new Map();
        this.progressUpdateDelay = 2000; // 2 seconds delay for progress updates
        this.completionThreshold = 0.95; // 95% watched = completed
        this.init();
    }

    init() {
        this.setupVideoListeners();
        this.setupIframeListeners();
        this.setupTextProgressButtons();
        console.log('Video Progress Tracker initialized');
    }

    // Setup listeners for HTML5 video elements (direct video files)
    setupVideoListeners() {
        const videoElements = document.querySelectorAll('video[data-chapter-id]');
        
        videoElements.forEach(video => {
            const chapterId = parseInt(video.dataset.chapterId);
            const sectionId = parseInt(video.dataset.sectionId);
            const courseId = parseInt(video.dataset.courseId);
            
            if (!chapterId || !sectionId || !courseId) {
                console.warn('Missing data attributes on video element', video);
                return;
            }

            // Initialize video tracking data
            this.videos.set(chapterId, {
                element: video,
                chapterId,
                sectionId,
                courseId,
                duration: 0,
                currentTime: 0,
                completed: false,
                lastProgressUpdate: 0
            });

            // Video event listeners
            video.addEventListener('loadedmetadata', () => {
                const videoData = this.videos.get(chapterId);
                videoData.duration = video.duration;
                console.log(`Video ${chapterId} loaded, duration: ${video.duration}s`);
            });

            video.addEventListener('timeupdate', () => {
                this.handleVideoTimeUpdate(chapterId);
            });

            video.addEventListener('ended', () => {
                this.handleVideoCompleted(chapterId);
            });

            // Handle seeking to near the end
            video.addEventListener('seeked', () => {
                const videoData = this.videos.get(chapterId);
                if (video.currentTime / video.duration >= this.completionThreshold) {
                    this.handleVideoCompleted(chapterId);
                }
            });
        });
    }

    // Setup listeners for iframe videos (YouTube, Vimeo, etc.)
    setupIframeListeners() {
        const iframeElements = document.querySelectorAll('iframe[data-chapter-id]');
        
        iframeElements.forEach(iframe => {
            const chapterId = parseInt(iframe.dataset.chapterId);
            const sectionId = parseInt(iframe.dataset.sectionId);
            const courseId = parseInt(iframe.dataset.courseId);
            
            if (!chapterId || !sectionId || !courseId) {
                console.warn('Missing data attributes on iframe element', iframe);
                return;
            }

            // Store iframe data for later reference
            this.iframes.set(chapterId, {
                element: iframe,
                chapterId,
                sectionId,
                courseId,
                completed: false
            });

            // For iframe videos, we'll use a different approach
            // Since we can't directly access video events from iframe, 
            // we'll add a "Mark as Complete" button for iframe videos
            this.setupIframeCompletionButton(iframe, chapterId, sectionId, courseId);
            
            // Try to detect YouTube/Vimeo completion if possible
            this.setupIframeVideoAPI(iframe, chapterId, sectionId, courseId);
        });
    }

    setupIframeCompletionButton(iframe, chapterId, sectionId, courseId) {
        // Check if button already exists
        const existingButton = iframe.parentElement.querySelector('.video-complete-btn');
        if (existingButton) return;

        // Create completion button for iframe videos
        const button = document.createElement('button');
        button.className = 'video-complete-btn mt-3 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors';
        button.innerHTML = '✓ Mark Video as Complete';
        button.onclick = () => this.markIframeVideoComplete(chapterId, sectionId, courseId, button);

        // Check if already completed
        this.checkVideoCompletionStatus(chapterId).then(completed => {
            if (completed) {
                button.innerHTML = '✅ Video Completed';
                button.disabled = true;
                button.className = 'video-complete-btn mt-3 px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded-lg cursor-not-allowed';
                
                // Mark as completed in our tracker too
                const iframeData = this.iframes.get(chapterId);
                if (iframeData) {
                    iframeData.completed = true;
                }
            }
        });

        // Add helpful text for YouTube videos
        const helpText = document.createElement('p');
        helpText.className = 'text-xs text-gray-500 mt-1';
        helpText.innerHTML = '💡 <strong>Tip:</strong> Watch the video to the end, or click the button above when you\'re done.';

        iframe.parentElement.appendChild(button);
        iframe.parentElement.appendChild(helpText);
    }

    setupIframeVideoAPI(iframe, chapterId, sectionId, courseId) {
        const src = iframe.src;
        
        // Enhanced YouTube API integration
        if (src.includes('youtube.com') || src.includes('youtu.be')) {
            // Add YouTube API parameter if not present
            if (!src.includes('enablejsapi=1')) {
                const separator = src.includes('?') ? '&' : '?';
                iframe.src = src + separator + 'enablejsapi=1&origin=' + encodeURIComponent(window.location.origin);
            }
            
            console.log(`🎥 Setting up YouTube API for chapter ${chapterId}`);
            
            // Store iframe reference for this chapter
            this.iframes.set(chapterId, {
                element: iframe,
                chapterId,
                sectionId,
                courseId,
                completed: false
            });
            
            // Listen for YouTube API messages
            const messageHandler = (event) => {
                if (event.origin !== 'https://www.youtube.com') return;
                
                try {
                    let data;
                    if (typeof event.data === 'string') {
                        data = JSON.parse(event.data);
                    } else {
                        data = event.data;
                    }
                    
                    console.log('📺 YouTube message received:', data);
                    
                    // YouTube Player State Changes
                    if (data.event === 'onStateChange') {
                        const playerState = data.info;
                        console.log(`📺 YouTube player state changed: ${playerState} for chapter ${chapterId}`);
                        
                        // State 0 = ended
                        if (playerState === 0) {
                            console.log(`🎬 YouTube video ended for chapter ${chapterId}`);
                            this.handleIframeVideoCompleted(chapterId, sectionId, courseId);
                        }
                    }
                    
                    // YouTube Time Updates
                    if (data.event === 'onTimeUpdate' || data.event === 'video-progress') {
                        const info = data.info;
                        if (info && info.currentTime && info.duration) {
                            const currentTime = info.currentTime;
                            const duration = info.duration;
                            const completionPercentage = currentTime / duration;
                            
                            console.log(`📺 YouTube progress: ${Math.round(completionPercentage * 100)}% for chapter ${chapterId}`);
                            
                            // Mark as complete when video reaches 95% or ends
                            if (completionPercentage >= 0.95) {
                                console.log(`🎬 YouTube video reached 95% for chapter ${chapterId}`);
                                this.handleIframeVideoCompleted(chapterId, sectionId, courseId);
                            }
                        }
                    }
                } catch (e) {
                    // Ignore non-JSON messages or parsing errors
                    console.log('📺 YouTube message (ignored):', event.data);
                }
            };
            
            // Add the message listener
            window.addEventListener('message', messageHandler);
            
            // Store the handler so we can remove it later if needed
            iframe.messageHandler = messageHandler;
            
            // Also set up periodic checking as a fallback
            this.setupPeriodicVideoCheck(chapterId, sectionId, courseId);
        }
        
        // Enhanced Vimeo integration
        if (src.includes('vimeo.com')) {
            console.log(`🎥 Setting up Vimeo API for chapter ${chapterId}`);
            
            // Listen for Vimeo Player API messages
            const vimeoHandler = (event) => {
                if (event.origin !== 'https://player.vimeo.com') return;
                
                try {
                    const data = JSON.parse(event.data);
                    console.log('📺 Vimeo message received:', data);
                    
                    // Vimeo ended event
                    if (data.event === 'ended') {
                        console.log(`🎬 Vimeo video ended for chapter ${chapterId}`);
                        this.handleIframeVideoCompleted(chapterId, sectionId, courseId);
                    }
                    
                    // Vimeo progress tracking
                    if (data.event === 'timeupdate' && data.data) {
                        const currentTime = data.data.seconds;
                        const duration = data.data.duration;
                        
                        if (duration > 0) {
                            const completionPercentage = currentTime / duration;
                            console.log(`📺 Vimeo progress: ${Math.round(completionPercentage * 100)}%`);
                            
                            // Mark complete at 95%
                            if (completionPercentage >= 0.95) {
                                console.log(`🎬 Vimeo video reached 95% for chapter ${chapterId}`);
                                this.handleIframeVideoCompleted(chapterId, sectionId, courseId);
                            }
                        }
                    }
                } catch (e) {
                    // Ignore non-JSON messages
                }
            };
            
            window.addEventListener('message', vimeoHandler);
            iframe.vimeoHandler = vimeoHandler;
        }
    }

    // New method to periodically check video completion as fallback
    setupPeriodicVideoCheck(chapterId, sectionId, courseId) {
        // This is a fallback method for videos that don't send proper events
        const checkInterval = setInterval(() => {
            const iframeData = this.iframes.get(chapterId);
            if (!iframeData || iframeData.completed) {
                clearInterval(checkInterval);
                return;
            }
            
            // Check if the iframe is still visible (user hasn't navigated away)
            if (!document.contains(iframeData.element)) {
                clearInterval(checkInterval);
                return;
            }
            
            console.log(`⏰ Periodic check for video completion: chapter ${chapterId}`);
        }, 10000); // Check every 10 seconds
        
        // Store interval ID to clear it later
        const iframeData = this.iframes.get(chapterId);
        if (iframeData) {
            iframeData.checkInterval = checkInterval;
        }
    }

    handleIframeVideoCompleted(chapterId, sectionId, courseId) {
        const iframeData = this.iframes.get(chapterId);
        if (!iframeData || iframeData.completed) {
            console.log(`⚠️ Video ${chapterId} already completed or not found`);
            return;
        }

        iframeData.completed = true;
        console.log(`🎬 Iframe video ${chapterId} completed automatically!`);
        
        // Clear any periodic check interval
        if (iframeData.checkInterval) {
            clearInterval(iframeData.checkInterval);
        }
        
        // Update the completion button if it exists
        const button = iframeData.element.parentElement.querySelector('.video-complete-btn');
        if (button && !button.disabled) {
            this.markIframeVideoComplete(chapterId, sectionId, courseId, button);
        } else {
            // If no button exists, directly mark as complete
            this.markIframeVideoComplete(chapterId, sectionId, courseId, null);
        }
    }

    setupTextProgressButtons() {
        // Remove text completion buttons - text chapters will be marked complete automatically
        // when user navigates to next chapter via "Next" button
        console.log('Text content will be marked complete automatically on navigation');
    }

    handleVideoTimeUpdate(chapterId) {
        const videoData = this.videos.get(chapterId);
        if (!videoData) return;

        const video = videoData.element;
        videoData.currentTime = video.currentTime;

        // Check for completion based on percentage watched
        const completionPercentage = videoData.duration > 0 ? video.currentTime / videoData.duration : 0;
        
        // More reliable completion detection
        if ((completionPercentage >= this.completionThreshold || video.ended) && !videoData.completed) {
            this.handleVideoCompleted(chapterId);
            return;
        }

        // Throttle progress updates (but don't save to database until completed)
        const now = Date.now();
        if (now - videoData.lastProgressUpdate > this.progressUpdateDelay) {
            // Only log progress, don't update database until completion
            console.log(`Video ${chapterId} progress: ${Math.round(completionPercentage * 100)}%`);
            videoData.lastProgressUpdate = now;
        }
    }

    handleVideoCompleted(chapterId) {
        const videoData = this.videos.get(chapterId);
        if (!videoData || videoData.completed) return;

        videoData.completed = true;
        console.log(`🎥 Video ${chapterId} completed! Progress will be saved.`);
        
        // Now save the completion to database
        this.updateVideoProgress(chapterId, true, 1.0);
        this.showCompletionNotification(`🎉 Video completed! Progress saved.`);
        
        // Disable any manual completion buttons for this video
        const video = videoData.element;
        if (video && video.parentElement) {
            const button = video.parentElement.querySelector('.video-complete-btn');
            if (button) {
                button.innerHTML = '✅ Video Completed';
                button.disabled = true;
                button.className = 'video-complete-btn mt-3 px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded-lg cursor-not-allowed';
            }
        }
    }

    async markIframeVideoComplete(chapterId, sectionId, courseId, button) {
        try {
            console.log(`🎥 Manually marking iframe video ${chapterId} as complete`);
            
            const result = await this.updateProgress({
                chapter_id: chapterId,
                section_id: sectionId,
                course_id: courseId,
                content_type: 'video',
                completed: true,
                completion_percentage: 100
            });

            if (result.success) {
                button.innerHTML = '✅ Video Completed';
                button.disabled = true;
                button.className = 'video-complete-btn mt-3 px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded-lg cursor-not-allowed';
                this.showCompletionNotification('🎉 Video marked as complete!');
                this.updateProgressUIWithData(result.section_progress, result.course_progress);
                
                // Update iframe data
                if (this.iframes.has(chapterId)) {
                    this.iframes.get(chapterId).completed = true;
                }
            }
        } catch (error) {
            console.error('Error marking iframe video complete:', error);
            this.showErrorNotification('Failed to mark video as complete');
        }
    }

    async markTextComplete(chapterId, sectionId, courseId, button) {
        try {
            console.log(`📤 Sending text completion request for chapter ${chapterId}`);
            
            const result = await this.updateProgress({
                chapter_id: chapterId,
                section_id: sectionId,
                course_id: courseId,
                content_type: 'text',
                completed: true
            });

            console.log('📤 Text completion response:', result);

            if (result.success) {
                if (button) {
                    button.innerHTML = '✅ Completed';
                    button.disabled = true;
                    button.className = 'text-complete-btn mt-4 px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded-lg cursor-not-allowed';
                }
                this.showCompletionNotification('📖 Text content completed!');
                // Update UI immediately with the new progress data
                this.updateProgressUIWithData(result.section_progress, result.course_progress);
                return result;
            } else {
                console.error('❌ Text completion failed:', result.message || 'Unknown error');
                throw new Error(result.message || 'Failed to mark text as complete');
            }
        } catch (error) {
            console.error('❌ Error marking text complete:', error);
            this.showErrorNotification('Failed to mark content as complete: ' + error.message);
            throw error;
        }
    }

    // Enhanced method to automatically mark current text chapter as complete
    // This is called ONLY when navigating to next chapter via Next button
    async markCurrentTextChapterComplete() {
        console.log('🔍 Checking for active text content to mark complete...');
        
        // Find the currently visible/active text content
        const allTextContainers = document.querySelectorAll('[data-content-type="text"]');
        console.log(`📋 Found ${allTextContainers.length} text containers`);
        
        let activeTextContainer = null;
        
        // Find the visible text container (Alpine.js may hide others)
        for (const container of allTextContainers) {
            const isVisible = container.offsetParent !== null && 
                             !container.hidden && 
                             getComputedStyle(container).display !== 'none' &&
                             getComputedStyle(container).visibility !== 'hidden';
            
            console.log(`🔍 Container ${container.dataset.chapterId}: visible=${isVisible}, display=${getComputedStyle(container).display}`);
            
            if (isVisible) {
                activeTextContainer = container;
                console.log(`✅ Found active text container: chapter ${container.dataset.chapterId}`);
                break;
            }
        }
        
        // Fallback: if no visible container found, try to get from URL parameters
        if (!activeTextContainer && allTextContainers.length > 0) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentChapterId = urlParams.get('chapter');
            console.log(`🔍 Fallback: looking for chapter ID ${currentChapterId} from URL`);
            
            if (currentChapterId) {
                activeTextContainer = document.querySelector(`[data-content-type="text"][data-chapter-id="${currentChapterId}"]`);
                if (activeTextContainer) {
                    console.log(`✅ Found text container via URL fallback: chapter ${currentChapterId}`);
                }
            }
        }
        
        if (activeTextContainer) {
            const chapterId = parseInt(activeTextContainer.dataset.chapterId);
            const sectionId = parseInt(activeTextContainer.dataset.sectionId);
            const courseId = parseInt(activeTextContainer.dataset.courseId);
            
            console.log(`📊 Text chapter data: chapterId=${chapterId}, sectionId=${sectionId}, courseId=${courseId}`);
            
            if (chapterId && sectionId && courseId) {
                try {
                    // Check if already completed to avoid duplicate calls
                    const isAlreadyCompleted = await this.checkTextCompletionStatus(chapterId);
                    
                    if (!isAlreadyCompleted) {
                        console.log(`📖 Auto-marking text chapter ${chapterId} as complete via navigation`);
                        const result = await this.markTextComplete(chapterId, sectionId, courseId);
                        return result;
                    } else {
                        console.log(`📖 Text chapter ${chapterId} already completed`);
                        return { success: true, message: 'Already completed' };
                    }
                } catch (error) {
                    console.error('❌ Error in text completion process:', error);
                    throw error;
                }
            } else {
                console.warn('⚠️ Missing required data attributes on text container:', {
                    chapterId, sectionId, courseId,
                    element: activeTextContainer
                });
                return { success: false, message: 'Missing data attributes' };
            }
        } else {
            console.log('ℹ️ No active text content found (possibly viewing video or other content)');
            return { success: true, message: 'No text content to complete' };
        }
    }

    async updateVideoProgress(chapterId, completed, completionPercentage) {
        const videoData = this.videos.get(chapterId);
        if (!videoData) return;

        try {
            // Only update database when video is actually completed
            // This ensures videos are only marked complete when they finish playing
            if (completed) {
                console.log(`🎥 Saving video completion for chapter ${chapterId}`);
                
                const result = await this.updateProgress({
                    chapter_id: chapterId,
                    section_id: videoData.sectionId,
                    course_id: videoData.courseId,
                    content_type: 'video',
                    completed: true,
                    completion_percentage: 100, // Always 100% when completed
                    watch_time: Math.round(videoData.currentTime),
                    total_duration: Math.round(videoData.duration)
                });

                // Update UI immediately if video is completed
                if (result.success) {
                    this.updateProgressUIWithData(result.section_progress, result.course_progress);
                    console.log(`✅ Video ${chapterId} completion saved successfully`);
                }
            } else {
                // For incomplete videos, just log progress but don't save to database
                console.log(`📊 Video ${chapterId} progress: ${Math.round(completionPercentage * 100)}% (not saved until completion)`);
            }
        } catch (error) {
            console.error('Error updating video progress:', error);
        }
    }

    async updateProgress(progressData) {
        try {
            console.log('📤 Sending progress update:', progressData);
            
            // Correct API path from dashboard folder
            const apiPath = '../api/update_progress.php';
            
            console.log(`📡 Using API path: ${apiPath}`);
            const response = await fetch(apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(progressData)
            });

            console.log('📥 Progress update response status:', response.status);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('❌ HTTP error response:', errorText);
                throw new Error(`HTTP error! status: ${response.status}, response: ${errorText}`);
            }

            const result = await response.json();
            console.log('📥 Progress update result:', result);
            
            if (result.success) {
                console.log('✅ Progress updated successfully:', result.section_progress);
                return result; // Return the full result object with progress data
            } else {
                console.error('❌ Progress update failed:', result.message || 'Unknown error');
                throw new Error(result.message || 'Server returned failure status');
            }
        } catch (error) {
            console.error('❌ Progress update failed:', error);
            throw error;
        }
    }

    async checkVideoCompletionStatus(chapterId) {
        try {
            const response = await fetch(`../api/get_progress.php?chapter_id=${chapterId}&type=video`);
            if (response.ok) {
                const result = await response.json();
                return result.completed || false;
            }
        } catch (error) {
            console.error('Error checking video completion status:', error);
        }
        return false;
    }

    async checkTextCompletionStatus(chapterId) {
        try {
            const response = await fetch(`../api/get_progress.php?chapter_id=${chapterId}&type=text`);
            if (response.ok) {
                const result = await response.json();
                return result.completed || false;
            }
        } catch (error) {
            console.error('Error checking text completion status:', error);
        }
        return false;
    }

    updateProgressUI() {
        // For navigation-triggered completions, don't reload immediately
        // The page will reload anyway when navigating to next chapter
        console.log('Progress updated - UI will refresh on navigation');
    }

    // New method to update UI with specific progress data
    updateProgressUIWithData(sectionProgress, courseProgress) {
        try {
            // Update overall progress percentage
            if (courseProgress) {
                const overallProgressElement = document.querySelector('.text-sm.font-medium.rubik-medium.text-blue-600');
                if (overallProgressElement) {
                    overallProgressElement.textContent = `${courseProgress.percentage}%`;
                }

                // Update overall progress bar
                const overallProgressBar = document.querySelector('.bg-blue-600.h-2.rounded-full.transition-all.duration-300');
                if (overallProgressBar) {
                    overallProgressBar.style.width = `${courseProgress.percentage}%`;
                }
            }

            // Update section progress indicators
            if (sectionProgress) {
                // Find all section progress indicators and update the relevant one
                const progressSpans = document.querySelectorAll('span.text-xs.text-gray-500');
                progressSpans.forEach(span => {
                    // Look for pattern like "0/3" or "1/2"
                    if (span.textContent.includes('/')) {
                        // Get the parent section to identify which one to update
                        const sectionContainer = span.closest('.section-card');
                        if (sectionContainer) {
                            // Update the progress text
                            span.textContent = `${sectionProgress.completed_chapters}/${sectionProgress.total_chapters}`;
                            
                            // Update the progress bar
                            const progressBar = sectionContainer.querySelector('.h-1\\.5.rounded-full.transition-all.duration-300');
                            if (progressBar) {
                                const percentage = sectionProgress.total_chapters > 0 ? 
                                    (sectionProgress.completed_chapters / sectionProgress.total_chapters) * 100 : 0;
                                progressBar.style.width = `${percentage}%`;
                                
                                // Update color based on completion
                                if (sectionProgress.section_completed) {
                                    progressBar.className = progressBar.className.replace(/bg-\w+-\d+/, 'bg-green-500');
                                } else if (sectionProgress.completed_chapters > 0) {
                                    progressBar.className = progressBar.className.replace(/bg-\w+-\d+/, 'bg-blue-500');
                                }
                            }
                        }
                    }
                });
            }

            console.log('UI updated with new progress data');
        } catch (error) {
            console.error('Error updating progress UI:', error);
        }
    }

    showCompletionNotification(message) {
        // Create a modern notification with better styling
        const notification = document.createElement('div');
        notification.className = 'fixed top-20 right-4 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-lg shadow-xl z-50 transform transition-all duration-300 max-w-sm';
        notification.innerHTML = `
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-sm">${message}</p>
                </div>
            </div>
        `;
        
        // Add animation
        notification.style.transform = 'translateX(100%)';
        document.body.appendChild(notification);
        
        // Slide in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Remove after 4 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 4000);
    }

    showErrorNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-20 right-4 bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-4 rounded-lg shadow-xl z-50 transform transition-all duration-300 max-w-sm';
        notification.innerHTML = `
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-sm">${message}</p>
                </div>
            </div>
        `;
        
        notification.style.transform = 'translateX(100%)';
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 4000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.videoProgressTracker = new VideoProgressTracker();
});

// Also initialize if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.videoProgressTracker = new VideoProgressTracker();
    });
} else {
    window.videoProgressTracker = new VideoProgressTracker();
}
