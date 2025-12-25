<?php
// SAFE DATABASE OPTIMIZATIONS
// Copy these settings to your existing database.php file

// Add these options to your existing $config['options'] array:
$safe_optimization_options = [
    // Enable persistent connections (reuses connections)
    PDO::ATTR_PERSISTENT => true,
    
    // Set statement caching (improves repeated queries)
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    
    // Increase timeout for large queries
    PDO::ATTR_TIMEOUT => 30,
    
    // Your existing options (keep these)
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

// MONITORING FUNCTION - Add this to track performance
function logDatabasePerformance($query, $executionTime, $pdo) {
    if ($executionTime > 1.0) { // Log slow queries (>1 second)
        error_log("SLOW QUERY DETECTED: {$executionTime}s - {$query}");
    }
}

// CONNECTION MONITORING - Add this to track connection usage
function getConnectionStats($pdo) {
    try {
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $connected = $stmt->fetch();
        
        $stmt = $pdo->query("SHOW STATUS LIKE 'Max_used_connections'");
        $maxUsed = $stmt->fetch();
        
        error_log("DB CONNECTIONS - Current: {$connected['Value']}, Max Used: {$maxUsed['Value']}");
        
        return [
            'current_connections' => $connected['Value'],
            'max_used_connections' => $maxUsed['Value']
        ];
    } catch (Exception $e) {
        error_log("Connection monitoring error: " . $e->getMessage());
        return null;
    }
}

// QUERY OPTIMIZATION HELPERS
function optimizeUserQuery($pdo, $userId) {
    // Use prepared statements with proper indexing
    $stmt = $pdo->prepare("
        SELECT u.*, sp.display_name, sp.profile_picture 
        FROM users u 
        LEFT JOIN student_preferences sp ON u.id = sp.student_id 
        WHERE u.id = ? 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function optimizeQuizQuery($pdo, $quizId, $userId) {
    // Optimized quiz query with proper joins
    $stmt = $pdo->prepare("
        SELECT 
            q.id as quiz_id,
            q.title as quiz_title,
            q.description as quiz_description,
            qq.id as question_id,
            qq.question_text,
            qq.question_type,
            qc.id as choice_id,
            qc.choice_text,
            qc.is_correct,
            COALESCE(qa.score, 0) as attempt_score,
            COALESCE(qa.total_points, 0) as attempt_total_points,
            qa.completed_at as last_attempt_date
        FROM quizzes q
        INNER JOIN quiz_questions qq ON q.id = qq.quiz_id
        LEFT JOIN quiz_choices qc ON qq.id = qc.question_id
        LEFT JOIN (
            SELECT quiz_id, score, total_points, completed_at
            FROM quiz_attempts
            WHERE student_id = ?
            ORDER BY completed_at DESC
            LIMIT 1
        ) qa ON q.id = qa.quiz_id
        WHERE q.id = ?
        ORDER BY qq.order_index, qc.order_index
    ");
    $stmt->execute([$userId, $quizId]);
    return $stmt->fetchAll();
}
?>
