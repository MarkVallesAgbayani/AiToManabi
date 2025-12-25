<?php
/**
 * Preview Mode Helper Functions
 * 
 * This file contains helper functions to detect and handle preview mode
 * to prevent database writes and ensure proper preview behavior.
 */

/**
 * Check if the current request is in preview mode
 * @return bool
 */
function isPreviewMode() {
    return isset($_GET['preview']) && ($_GET['preview'] === 'true' || $_GET['preview'] === '1');
}

/**
 * Check if the current user is a teacher in preview mode
 * @return bool
 */
function isTeacherPreviewMode() {
    return isPreviewMode() && 
           isset($_SESSION['user_id']) && 
           isset($_SESSION['role']) && 
           $_SESSION['role'] === 'teacher';
}

/**
 * Get the preview access mode (enrolled or all)
 * @return string
 */
function getPreviewAccessMode() {
    return isset($_GET['access']) ? $_GET['access'] : 'enrolled';
}

/**
 * Prevent database writes in preview mode
 * This function should be called before any database write operations
 * @param string $operation The operation being attempted
 * @return bool True if operation should proceed, false if blocked
 */
function allowDatabaseWrite($operation = '') {
    if (isPreviewMode()) {
        error_log("Preview Mode: Blocked database write operation: " . $operation);
        return false;
    }
    return true;
}

/**
 * Get preview mode banner HTML
 * @param string $courseId The course ID for the exit link
 * @return string HTML for the preview banner
 */
function getPreviewBanner($courseId = null) {
    if (!isPreviewMode()) {
        return '';
    }
    
    $exitUrl = $courseId ? "teacher_create_module.php?id=" . $courseId : "teacher_create_module.php";
    $accessMode = getPreviewAccessMode();
    
    ob_start();
    ?>
    <div class="preview-banner fixed top-0 left-0 right-0 z-50 text-white p-4" 
         x-data="{ previewAccessMode: '<?php echo $accessMode; ?>' }">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-eye text-lg"></i>
                    <span class="font-semibold">Preview Mode</span>
                </div>
                <div class="text-sm opacity-90">
                    You are viewing this course as a student would see it
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Preview Mode Toggle -->
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium">Access Mode:</span>
                    <button 
                        @click="previewAccessMode = 'enrolled'; updateUrl('enrolled')"
                        :class="previewAccessMode === 'enrolled' ? 'active' : ''"
                        class="toggle-btn px-3 py-1 rounded-full text-xs font-medium transition-all duration-200">
                        As Enrolled Student
                    </button>
                    <button 
                        @click="previewAccessMode = 'all'; updateUrl('all')"
                        :class="previewAccessMode === 'all' ? 'active' : ''"
                        class="toggle-btn px-3 py-1 rounded-full text-xs font-medium transition-all duration-200">
                        All Access
                    </button>
                </div>
                
                <!-- Exit Preview Button -->
                <a href="<?php echo $exitUrl; ?>" 
                   class="bg-white text-red-600 px-4 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors duration-200 flex items-center space-x-2">
                    <i class="fas fa-times"></i>
                    <span>Exit Preview</span>
                </a>
            </div>
        </div>
        
        <script>
            function updateUrl(mode) {
                const url = new URL(window.location);
                url.searchParams.set('access', mode);
                window.history.replaceState({}, '', url);
                location.reload();
            }
        </script>
    </div>
    
    <style>
        .preview-banner {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .preview-banner .toggle-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .preview-banner .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .preview-banner .toggle-btn.active {
            background: rgba(255, 255, 255, 0.9);
            color: #e11d48;
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Log preview mode actions for debugging
 * @param string $action The action being performed
 * @param array $data Additional data to log
 */
function logPreviewAction($action, $data = []) {
    if (isPreviewMode()) {
        error_log("Preview Mode Action: " . $action . " - " . json_encode($data));
    }
}

/**
 * Check if a course can be previewed by the current teacher
 * @param PDO $pdo Database connection
 * @param int $courseId Course ID to check
 * @param int $teacherId Teacher ID
 * @return bool|array False if not allowed, course data if allowed
 */
function canPreviewCourse($pdo, $courseId, $teacherId) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username as teacher_name, cat.name as category_name
        FROM courses c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ? AND (c.teacher_id = ? OR ? IN (SELECT user_id FROM user_permissions WHERE permission_name = 'preview_all_courses'))
    ");
    $stmt->execute([$courseId, $teacherId, $teacherId]);
    return $stmt->fetch();
}

/**
 * Get preview mode CSS styles
 * @return string CSS styles for preview mode
 */
function getPreviewModeStyles() {
    return '
        .preview-banner {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .preview-banner .toggle-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .preview-banner .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .preview-banner .toggle-btn.active {
            background: rgba(255, 255, 255, 0.9);
            color: #e11d48;
        }
        
        .preview-mode-content {
            margin-top: 80px; /* Account for fixed banner */
        }
        
        .preview-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            color: #92400e;
        }
        
        .preview-blocked {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
    ';
}
?>
