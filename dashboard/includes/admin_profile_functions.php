<?php
/**
 * Admin Profile Functions
 * Functions to handle admin profile data and display
 */

/**
 * Get admin profile information including preferences
 * @param PDO $pdo Database connection
 * @param int $admin_id Admin ID
 * @return array Admin profile data
 */
function getAdminProfile($pdo, $admin_id) {
    try {
        // Get basic user information
        $stmt = $pdo->prepare("
            SELECT id, username, email, role, first_name, last_name, created_at 
            FROM users 
            WHERE id = ? AND role = 'admin'
        ");
        $stmt->execute([$admin_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        // Get admin preferences
        $stmt = $pdo->prepare("
            SELECT display_name, profile_picture, bio, phone, 
                   profile_visible, contact_visible 
            FROM admin_preferences 
            WHERE admin_id = ?
        ");
        $stmt->execute([$admin_id]);
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
            'display_name' => $preferences['display_name'] ?? '',
            'profile_picture' => $preferences['profile_picture'] ?? '',
            'bio' => $preferences['bio'] ?? '',
            'phone' => $preferences['phone'] ?? '',
            'profile_visible' => $preferences['profile_visible'] ?? true,
            'contact_visible' => $preferences['contact_visible'] ?? true
        ];
        
        return $profile;
        
    } catch (PDOException $e) {
        error_log("Error getting admin profile: " . $e->getMessage());
        return null;
    }
}

/**
 * Get admin display name (preferred display name or fallback to username)
 * @param array $profile Admin profile data
 * @return string Display name
 */
function getAdminDisplayName($profile) {
    if (!empty($profile['display_name'])) {
        return $profile['display_name'];
    }
    
    if (!empty($profile['first_name']) && !empty($profile['last_name'])) {
        return $profile['first_name'] . ' ' . $profile['last_name'];
    }
    
    if (!empty($profile['first_name'])) {
        return $profile['first_name'];
    }
    
    return $profile['username'];
}

/**
 * Get admin profile picture or generate initial
 * @param array $profile Admin profile data
 * @return array Profile picture data
 */
function getAdminProfilePicture($profile) {
    $picture = [
        'has_image' => false,
        'image_path' => '',
        'initial' => strtoupper(substr($profile['username'], 0, 1))
    ];
    
    if (!empty($profile['profile_picture'])) {
        // Construct the full file system path for existence check (uploads/ is at project root)
        $file_system_path = __DIR__ . '/../../uploads/profile_pictures/' . $profile['profile_picture'];
        
        error_log("Admin profile picture check - File: " . $profile['profile_picture']);
        error_log("Admin profile picture check - File system path: " . $file_system_path);
        error_log("Admin profile picture check - File exists: " . (file_exists($file_system_path) ? 'Yes' : 'No'));
        
        if (file_exists($file_system_path)) {
            $picture['has_image'] = true;
            // Construct web-accessible path from project root
            $picture['image_path'] = 'uploads/profile_pictures/' . $profile['profile_picture'];
            error_log("Admin profile picture check - Web path: " . $picture['image_path']);
        }
    }
    
    return $picture;
}

/**
 * Get admin role display text
 * @param array $profile Admin profile data
 * @param bool $is_hybrid Whether admin is hybrid
 * @return string Role display text
 */
function getAdminRoleDisplay($profile, $is_hybrid = false) {
    if ($is_hybrid) {
        return 'Hybrid Admin';
    }
    
    return 'Administrator';
}

/**
 * Render admin profile sidebar HTML
 * @param array $profile Admin profile data
 * @param bool $is_hybrid Optional hybrid status (for compatibility)
 * @return string HTML for sidebar profile
 */
function renderAdminSidebarProfile($profile, $is_hybrid = false) {
    $display_name = getAdminDisplayName($profile);
    $picture = getAdminProfilePicture($profile);
    $role_display = getAdminRoleDisplay($profile, $is_hybrid);
    
    $html = '<div class="p-4 border-b flex items-center space-x-3">';
    
    if ($picture['has_image']) {
        $html .= '<img src="' . htmlspecialchars($picture['image_path']) . '" 
                      alt="Profile Picture" 
                      class="w-12 h-12 rounded-full object-cover shadow-md sidebar-profile-picture">';
        $html .= '<div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-lg shadow-md sidebar-profile-placeholder" style="display: none;">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
    } else {
        $html .= '<div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-lg shadow-md sidebar-profile-placeholder">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
        $html .= '<img src="" alt="Profile Picture" class="w-12 h-12 rounded-full object-cover shadow-md sidebar-profile-picture" style="display: none;">';
    }
    
    $html .= '<div class="flex-1 min-w-0">';
    $html .= '<div class="font-medium sidebar-display-name truncate">' . htmlspecialchars($display_name) . '</div>';
    $html .= '<div class="text-sm text-gray-500 sidebar-role">' . htmlspecialchars($role_display) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Handle profile picture upload for admins
 * @param array $file $_FILES array for the uploaded file
 * @param int $admin_id Admin ID
 * @return string|false Filename on success, false on failure
 */
function handleAdminProfilePictureUpload($file, $admin_id) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    try {
        // Upload directory should point to the project's uploads folder (two levels up from includes/)
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
        $new_filename = 'admin_' . $admin_id . '_' . uniqid() . '.' . strtolower($file_info['extension']);
        $upload_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return $new_filename;
    } catch (Exception $e) {
        error_log("Admin profile picture upload error: " . $e->getMessage());
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        return false;
    }
}

/**
 * Update admin profile preferences
 * @param PDO $pdo Database connection
 * @param int $admin_id Admin ID
 * @param array $data Profile data to update
 * @return bool Success status
 */
function updateAdminProfile($pdo, $admin_id, $data) {
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
            
            $params[] = $admin_id;
            
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?");
            $stmt->execute($params);
        }
        
        // Update or insert admin preferences
        $stmt = $pdo->prepare("SELECT id FROM admin_preferences WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing preferences
            $update_fields = [];
            $params = [];
            
            $allowed_fields = ['display_name', 'profile_picture', 'bio', 'phone', 'profile_visible', 'contact_visible'];
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (!empty($update_fields)) {
                $params[] = $admin_id;
                $stmt = $pdo->prepare("UPDATE admin_preferences SET " . implode(', ', $update_fields) . " WHERE admin_id = ?");
                $stmt->execute($params);
            }
        } else {
            // Insert new preferences
            $fields = ['admin_id'];
            $placeholders = ['?'];
            $params = [$admin_id];
            
            $allowed_fields = ['display_name', 'profile_picture', 'bio', 'phone', 'profile_visible', 'contact_visible'];
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $fields[] = $field;
                    $placeholders[] = '?';
                    $params[] = $data[$field];
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO admin_preferences (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
            $stmt->execute($params);
        }
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating admin profile: " . $e->getMessage());
        return false;
    }
}
?>
