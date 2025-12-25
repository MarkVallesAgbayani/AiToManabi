<?php
/**
 * Teacher Profile Functions
 * Functions to handle teacher profile data and display
 */

/**
 * Get teacher profile information including preferences
 * @param PDO $pdo Database connection
 * @param int $teacher_id Teacher ID
 * @return array Teacher profile data
 */
function getTeacherProfile($pdo, $teacher_id) {
    try {
        // Get basic user information
        $stmt = $pdo->prepare("
            SELECT id, username, email, role, first_name, last_name, created_at 
            FROM users 
            WHERE id = ? AND role = 'teacher'
        ");
        $stmt->execute([$teacher_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        // Get teacher preferences
        $stmt = $pdo->prepare("
            SELECT display_name, profile_picture, bio, phone, languages, 
                   profile_visible, contact_visible 
            FROM teacher_preferences 
            WHERE teacher_id = ?
        ");
        $stmt->execute([$teacher_id]);
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
            'languages' => $preferences['languages'] ?? '',
            'profile_visible' => $preferences['profile_visible'] ?? true,
            'contact_visible' => $preferences['contact_visible'] ?? true
        ];
        
        return $profile;
        
    } catch (PDOException $e) {
        error_log("Error getting teacher profile: " . $e->getMessage());
        return null;
    }
}

/**
 * Get teacher display name (preferred display name or fallback to username)
 * @param array $profile Teacher profile data
 * @return string Display name
 */
function getTeacherDisplayName($profile) {
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
 * Get teacher profile picture or generate initial
 * @param array $profile Teacher profile data
 * @return array Profile picture data
 */
function getTeacherProfilePicture($profile) {
    $picture = [
        'has_image' => false,
        'image_path' => '',
        'initial' => strtoupper(substr($profile['username'], 0, 1))
    ];
    
    if (!empty($profile['profile_picture'])) {
        // Construct the full file system path for existence check
        $file_system_path = __DIR__ . '/../uploads/profile_pictures/' . $profile['profile_picture'];
        
        error_log("Profile picture check - File: " . $profile['profile_picture']);
        error_log("Profile picture check - File system path: " . $file_system_path);
        error_log("Profile picture check - File exists: " . (file_exists($file_system_path) ? 'Yes' : 'No'));
        
        if (file_exists($file_system_path)) {
            $picture['has_image'] = true;
            // Construct web-accessible path from project root
            $picture['image_path'] = 'uploads/profile_pictures/' . $profile['profile_picture'];
            error_log("Profile picture check - Web path: " . $picture['image_path']);
        }
    }
    
    return $picture;
}

/**
 * Get teacher role display text
 * @param array $profile Teacher profile data
 * @param bool $is_hybrid Whether teacher is hybrid
 * @return string Role display text
 */
function getTeacherRoleDisplay($profile, $is_hybrid = false) {
    if ($is_hybrid) {
        return 'Hybrid Teacher';
    }
    
    return ucfirst($profile['role']);
}

/**
 * Render teacher profile sidebar HTML
 * @param array $profile Teacher profile data
 * @param bool $is_hybrid Whether teacher is hybrid
 * @return string HTML for sidebar profile
 */
function renderTeacherSidebarProfile($profile, $is_hybrid = false) {
    $display_name = getTeacherDisplayName($profile);
    $picture = getTeacherProfilePicture($profile);
    $role_display = getTeacherRoleDisplay($profile, $is_hybrid);
    
    $html = '<div class="p-3 border-b flex items-center space-x-3">';
    
    if ($picture['has_image']) {
        $html .= '<img src="' . htmlspecialchars($picture['image_path']) . '" 
                      alt="Profile Picture" 
                      class="w-10 h-10 rounded-full object-cover shadow-sm sidebar-profile-picture">';
        $html .= '<div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm sidebar-profile-placeholder" style="display: none;">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
    } else {
        $html .= '<div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm sidebar-profile-placeholder">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
        $html .= '<img src="" alt="Profile Picture" class="w-10 h-10 rounded-full object-cover shadow-sm sidebar-profile-picture" style="display: none;">';
    }
    
    $html .= '<div class="flex-1 min-w-0">';
    $html .= '<div class="font-medium text-sm sidebar-display-name truncate">' . htmlspecialchars($display_name) . '</div>';
    $html .= '<div class="text-xs font-bold text-red-600 sidebar-role">' . htmlspecialchars($role_display) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>
