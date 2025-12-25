<?php
/**
 * Session Validator
 * Checks if user's session should be invalidated (banned, deleted, etc.)
 */

class SessionValidator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if user session is still valid
     * Returns true if valid, false if should be logged out
     */
    public function isSessionValid($user_id) {
        try {
            // Check if user exists and is not banned/deleted
            $stmt = $this->pdo->prepare("
                SELECT status, deleted_at 
                FROM users 
                WHERE id = ? 
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // User not found
            if (!$user) {
                return false;
            }
            
            // User is deleted
            if ($user['deleted_at'] !== null) {
                return false;
            }
            
            // User is banned
            if ($user['status'] === 'banned') {
                return false;
            }
            
            // NEW: If user is active (not banned and not deleted), 
            // automatically clear any old ban/delete invalidation records
            if ($user['status'] !== 'banned' && $user['deleted_at'] === null) {
                $stmt = $this->pdo->prepare("
                    DELETE FROM invalidated_sessions 
                    WHERE user_id = ? 
                    AND reason IN ('banned', 'deleted')
                ");
                $stmt->execute([$user_id]);
            }
            
            // Check if there's a recent session invalidation for OTHER reasons
            // (like password reset or manual admin action)
            $stmt = $this->pdo->prepare("
                SELECT id, reason
                FROM invalidated_sessions 
                WHERE user_id = ? 
                AND reason NOT IN ('banned', 'deleted')
                AND invalidated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            
            if ($stmt->fetch()) {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            // On error, allow session to continue (fail-safe)
            return true;
        }
    }
    
    /**
     * Invalidate all sessions for a user
     */
    public function invalidateUserSessions($user_id, $reason = 'admin_action', $invalidated_by = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO invalidated_sessions (user_id, reason, invalidated_by) 
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$user_id, $reason, $invalidated_by]);
        } catch (Exception $e) {
            error_log("Session invalidation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear invalidated sessions for a user (used when unbanning/restoring)
     */
    public function clearInvalidatedSessions($user_id, $reasons = ['banned', 'deleted']) {
        try {
            $placeholders = str_repeat('?,', count($reasons) - 1) . '?';
            $stmt = $this->pdo->prepare("
                DELETE FROM invalidated_sessions 
                WHERE user_id = ? 
                AND reason IN ($placeholders)
            ");
            $params = array_merge([$user_id], $reasons);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Clear invalidated sessions error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Force logout by destroying session and redirecting
     */
    public function forceLogout($reason = 'Your session has been terminated') {
        // Clear all session data
        $_SESSION = array();
        
        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy session
        session_destroy();
        
        // Set alert for login page
        session_start();
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => $reason,
            'timestamp' => time()
        ];
        
        // Redirect to login
        header("Location: /AIToManabi_Updated/dashboard/login.php");
        exit();
    }
}
?>
