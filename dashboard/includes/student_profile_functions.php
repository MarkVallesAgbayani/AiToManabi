<?php
/**
 * Student Profile Functions
 * Functions to handle student profile data and display
 */

/**
 * Get student profile information including preferences
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @return array Student profile data
 */
function getStudentProfile($pdo, $student_id) {
    try {
        // Get basic user information
        $stmt = $pdo->prepare("
            SELECT id, username, email, role, first_name, last_name, created_at, student_id, learning_level
            FROM users 
            WHERE id = ? AND role = 'student'
        ");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        // Get student preferences (create table if needed)
        $stmt = $pdo->prepare("
            SELECT display_name, profile_picture, bio, phone, 
                   profile_visible, contact_visible, notification_preferences
            FROM student_preferences 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Combine user data with preferences
        $profile = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'created_at' => $user['created_at'],
            'student_id' => $user['student_id'],
            'learning_level' => $user['learning_level'],
            'display_name' => $preferences['display_name'] ?? '',
            'profile_picture' => $preferences['profile_picture'] ?? '',
            'bio' => $preferences['bio'] ?? '',
            'phone' => $preferences['phone'] ?? '',
            'profile_visible' => $preferences['profile_visible'] ?? true,
            'contact_visible' => $preferences['contact_visible'] ?? true,
            'notification_preferences' => $preferences['notification_preferences'] ?? '{}'
        ];
        
        return $profile;
        
    } catch (PDOException $e) {
        error_log("Error getting student profile: " . $e->getMessage());
        return null;
    }
}

/**
 * Get student display name (preferred display name or fallback to username)
 * @param array $profile Student profile data
 * @return string Display name
 */
function getStudentDisplayName($profile) {
    if (!empty($profile['display_name'])) {
        return $profile['display_name'];
    }
    
    if (!empty($profile['first_name']) && !empty($profile['last_name'])) {
        return $profile['first_name'] . ' ' . $profile['last_name'];
    }
    
    if (!empty($profile['first_name'])) {
        return $profile['first_name'];
    }
    
    return $profile['username'] ?? 'Student';
}

/**
 * Get student profile picture or generate initial
 * @param array $profile Student profile data
 * @return array Profile picture data
 */
function getStudentProfilePicture($profile) {
    $username = $profile['username'] ?? 'Student';
    $picture = [
        'has_image' => false,
        'image_path' => '',
        'initial' => strtoupper(substr($username, 0, 1))
    ];
    
    if (!empty($profile['profile_picture'])) {
        // Construct the full file system path for existence check
        $file_system_path = __DIR__ . '/../../uploads/profile_pictures/' . $profile['profile_picture'];
        
        error_log("Student profile picture check - File: " . $profile['profile_picture']);
        error_log("Student profile picture check - File system path: " . $file_system_path);
        error_log("Student profile picture check - File exists: " . (file_exists($file_system_path) ? 'Yes' : 'No'));
        
        if (file_exists($file_system_path)) {
            $picture['has_image'] = true;
            // Construct web-accessible path from project root
            $picture['image_path'] = 'uploads/profile_pictures/' . $profile['profile_picture'];
            error_log("Student profile picture check - Web path: " . $picture['image_path']);
        }
    }
    
    return $picture;
}

/**
 * Get student role display text
 * @param array $profile Student profile data
 * @return string Role display text
 */
function getStudentRoleDisplay($profile) {
    $level = $profile['learning_level'] ?? 'Beginner';
    return 'Student - ' . $level;
}

/**
 * Render student profile sidebar HTML
 * @param array $profile Student profile data
 * @return string HTML for sidebar profile
 */
function renderStudentSidebarProfile($profile) {
    $display_name = getStudentDisplayName($profile);
    $picture = getStudentProfilePicture($profile);
    $role_display = getStudentRoleDisplay($profile);
    
    $html = '<div class="p-3 border-b flex items-center space-x-3">';
    
    if ($picture['has_image']) {
        $html .= '<img src="' . htmlspecialchars($picture['image_path']) . '" 
                      alt="Profile Picture" 
                      class="w-10 h-10 rounded-full object-cover shadow-sm sidebar-profile-picture">';
        $html .= '<div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm sidebar-profile-placeholder" style="display: none;">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
    } else {
        $html .= '<div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm sidebar-profile-placeholder">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
        $html .= '<img src="" alt="Profile Picture" class="w-10 h-10 rounded-full object-cover shadow-sm sidebar-profile-picture" style="display: none;">';
    }
    
    $html .= '<div class="flex-1 min-w-0">';
    $html .= '<div class="font-medium text-sm sidebar-display-name truncate">' . htmlspecialchars($display_name ?? 'Student') . '</div>';
    $html .= '<div class="text-xs font-bold text-blue-600 sidebar-role">' . htmlspecialchars($role_display ?? 'Student') . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Handle profile picture upload for students
 * @param array $file $_FILES array for the uploaded file
 * @param int $student_id Student ID
 * @return string|false Filename on success, false on failure
 */
function handleStudentProfilePictureUpload($file, $student_id) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    try {
        $upload_dir = __DIR__ . '/../../uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        // File validation
        $file_info = pathinfo($file['name']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!isset($file_info['extension']) || !in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            throw new Exception('Invalid file extension. Only JPG, PNG and GIF are allowed.');
        }
        
        // Check file size (2MB limit)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('File is too large. Maximum size is 2MB.');
        }
        
        // Generate unique filename
        $new_filename = 'student_' . $student_id . '_' . uniqid() . '.' . strtolower($file_info['extension']);
        $upload_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return $new_filename;
    } catch (Exception $e) {
        error_log("Student profile picture upload error: " . $e->getMessage());
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        return false;
    }
}

/**
 * Update student profile preferences
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @param array $data Profile data to update
 * @return bool Success status
 */
function updateStudentProfile($pdo, $student_id, $data) {
    try {
        $pdo->beginTransaction();
        
        // Update basic user info
        if (isset($data['username']) || isset($data['email'])) {
            $update_fields = [];
            $params = [];
            
            if (isset($data['username'])) {
                $update_fields[] = "username = ?";
                $params[] = $data['username'];
            }
            
            if (isset($data['email'])) {
                $update_fields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            $params[] = $student_id;
            
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?");
            $stmt->execute($params);
        }
        
        // Update or insert student preferences
        $stmt = $pdo->prepare("SELECT id FROM student_preferences WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing preferences
            $update_fields = [];
            $params = [];
            
            $allowed_fields = ['display_name', 'profile_picture', 'bio', 'phone', 'profile_visible', 'contact_visible', 'notification_preferences'];
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (!empty($update_fields)) {
                $params[] = $student_id;
                $stmt = $pdo->prepare("UPDATE student_preferences SET " . implode(', ', $update_fields) . " WHERE student_id = ?");
                $stmt->execute($params);
            }
        } else {
            // Insert new preferences
            $fields = ['student_id'];
            $placeholders = ['?'];
            $params = [$student_id];
            
            $allowed_fields = ['display_name', 'profile_picture', 'bio', 'phone', 'profile_visible', 'contact_visible', 'notification_preferences'];
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $fields[] = $field;
                    $placeholders[] = '?';
                    $params[] = $data[$field];
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO student_preferences (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
            $stmt->execute($params);
        }
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating student profile: " . $e->getMessage());
        return false;
    }
}
?>
