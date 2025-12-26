<?php

$hostinger_safe_options = [
    // Keep your existing options
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,  // Improves memory usage
    PDO::ATTR_TIMEOUT => 10,                     // Reasonable timeout for shared hosting

];


function executeQuerySafely($pdo, $query, $params = []) {
    try {
        $startTime = microtime(true);
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $executionTime = microtime(true) - $startTime;
        
        if ($executionTime > 2.0) {
            error_log("SLOW QUERY on Hostinger: {$executionTime}s - " . substr($query, 0, 100));
        }
        
        return $stmt;
    } catch (PDOException $e) {
        error_log("Hostinger DB Error: " . $e->getMessage());
        throw $e;
    }
}


function checkHostingerConnectionHealth($pdo) {
    try {
        // Simple health check
        $stmt = $pdo->query("SELECT 1 as health_check");
        $result = $stmt->fetch();
        
        // Check connection count (Hostinger limits)
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $connections = $stmt->fetch();
        
        error_log("Hostinger DB Health: " . ($result['health_check'] ? 'OK' : 'FAIL') . 
                 " | Connections: " . $connections['Value']);
        
        return $result['health_check'] === 1;
    } catch (Exception $e) {
        error_log("Hostinger connection health check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Optimized user query for Hostinger
 */
function getHostingerUserData($pdo, $userId) {
    $query = "
        SELECT u.id, u.username, u.email, u.role, u.first_name, u.last_name,
               sp.display_name, sp.profile_picture
        FROM users u 
        LEFT JOIN student_preferences sp ON u.id = sp.student_id 
        WHERE u.id = ? 
        LIMIT 1
    ";
    
    return executeQuerySafely($pdo, $query, [$userId])->fetch();
}

/**
 * Optimized quiz query for Hostinger (with pagination)
 */
function getHostingerQuizData($pdo, $quizId, $userId, $page = 1, $limit = 10) {
    $offset = ($page - 1) * $limit;
    
    $query = "
        SELECT 
            q.id as quiz_id,
            q.title as quiz_title,
            qq.id as question_id,
            qq.question_text,
            qq.question_type,
            qc.id as choice_id,
            qc.choice_text,
            qc.is_correct
        FROM quizzes q
        INNER JOIN quiz_questions qq ON q.id = qq.quiz_id
        LEFT JOIN quiz_choices qc ON qq.id = qc.question_id
        WHERE q.id = ?
        ORDER BY qq.order_index, qc.order_index
        LIMIT ? OFFSET ?
    ";
    
    return executeQuerySafely($pdo, $query, [$quizId, $limit, $offset])->fetchAll();
}

/**
 * Manual cleanup function for Hostinger (run via cron or manual)
 */
function cleanupHostingerDatabase($pdo) {
    try {
        // Clean expired OTPs (older than 1 day)
        $otpCleanup = executeQuerySafely($pdo, 
            "DELETE FROM otps WHERE expires_at < NOW() - INTERVAL 1 DAY"
        );
        $otpCount = $otpCleanup->rowCount();
        
        // Clean expired sessions (older than 7 days)
        $sessionCleanup = executeQuerySafely($pdo,
            "DELETE FROM user_sessions WHERE expires_at < NOW() - INTERVAL 7 DAY"
        );
        $sessionCount = $sessionCleanup->rowCount();
        
        error_log("Hostinger cleanup: Removed {$otpCount} expired OTPs, {$sessionCount} expired sessions");
        
        return ['otps_cleaned' => $otpCount, 'sessions_cleaned' => $sessionCount];
    } catch (Exception $e) {
        error_log("Hostinger cleanup failed: " . $e->getMessage());
        return false;
    }
}
?>
