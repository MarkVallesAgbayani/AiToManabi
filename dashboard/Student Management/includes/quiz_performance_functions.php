<?php
/**
 * Quiz Performance Analysis Functions
 * Provides comprehensive analytics for quiz performance tracking
 */

class QuizPerformanceAnalyzer {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database;
    }
    
    /**
     * Get overall quiz performance statistics
     */
    public function getOverallStatistics($date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                COUNT(DISTINCT qa.id) as total_attempts,
                COUNT(DISTINCT qa.student_id) as active_students,
                COUNT(DISTINCT qa.quiz_id) as total_quizzes,
                ROUND(AVG(qa.score), 2) as average_score,
                MIN(qa.score) as min_score,
                MAX(qa.score) as max_score,
                (SELECT COUNT(DISTINCT id) FROM quizzes) as total_quiz_count
            FROM quiz_attempts qa
            LEFT JOIN quizzes q ON qa.quiz_id = q.id
            $whereClause
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_attempts' => (int)$result['total_attempts'],
            'active_students' => (int)$result['active_students'],
            'total_quizzes' => (int)$result['total_quiz_count'],
            'average_score' => (float)$result['average_score'],
            'min_score' => (float)$result['min_score'],
            'max_score' => (float)$result['max_score']
        ];
    }
    
    /**
     * Get top performing students
     */
    public function getTopPerformers($limit = 10, $date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                u.id,
                u.username,
                ROUND(AVG(qa.score), 2) as average_score,
                ROUND(AVG(qa.total_points), 2) as average_total_points,
                COUNT(qa.id) as total_attempts,
                MAX(qa.score) as best_score,
                MIN(qa.score) as worst_score,
                COALESCE(sp.profile_picture, '') as profile_picture
            FROM users u
            INNER JOIN quiz_attempts qa ON u.id = qa.student_id
            LEFT JOIN student_preferences sp ON u.id = sp.student_id
            $whereClause
            GROUP BY u.id, u.username, sp.profile_picture
            HAVING total_attempts > 0
            ORDER BY average_score DESC, total_attempts DESC
            LIMIT :limit
        ";
        
        $params['limit'] = $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get quiz-specific statistics
     */
    public function getQuizStatistics($date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                q.id,
                q.title,
                COUNT(qa.id) as total_attempts,
                ROUND(AVG(qa.score), 2) as average_score,
                MIN(qa.score) as min_score,
                MAX(qa.score) as max_score,
                COUNT(DISTINCT qa.student_id) as unique_students
            FROM quizzes q
            LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
            $whereClause
            GROUP BY q.id, q.title
            ORDER BY total_attempts DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent quiz attempts with pagination
     */
    public function getRecentAttempts($limit = 20, $page = 1, $date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Get total count for pagination
        $countSql = "
            SELECT COUNT(*) as total
            FROM quiz_attempts qa
            INNER JOIN users u ON qa.student_id = u.id
            INNER JOIN quizzes q ON qa.quiz_id = q.id
            $whereClause
        ";
        
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Calculate pagination
        $offset = ($page - 1) * $limit;
        $totalPages = ceil($totalRecords / $limit);
        
        $sql = "
            SELECT 
                qa.id,
                qa.score,
                qa.total_points,
                qa.completed_at,
                u.username,
                q.title as quiz_title,
                COALESCE(sp.profile_picture, '') as profile_picture
            FROM quiz_attempts qa
            INNER JOIN users u ON qa.student_id = u.id
            INNER JOIN quizzes q ON qa.quiz_id = q.id
            LEFT JOIN student_preferences sp ON u.id = sp.student_id
            $whereClause
            ORDER BY qa.completed_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'limit' => $limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Get performance trend data for charts
     */
    public function getPerformanceTrendData($days = 30, $date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                DATE(qa.completed_at) as date,
                ROUND(AVG(qa.score), 2) as average_score,
                COUNT(qa.id) as total_attempts
            FROM quiz_attempts qa
            $whereClause
            GROUP BY DATE(qa.completed_at)
            ORDER BY date DESC
            LIMIT :days
        ";
        
        $params['days'] = $days;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $result;
    }
    
    /**
     * Get quiz difficulty analysis
     */
    public function getQuizDifficultyData($date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                q.id,
                q.title,
                ROUND(AVG(qa.score), 2) as average_score,
                COUNT(qa.id) as total_attempts,
                CASE 
                    WHEN AVG(qa.score) >= 80 THEN 'Easy'
                    WHEN AVG(qa.score) >= 60 THEN 'Medium'
                    ELSE 'Hard'
                END as difficulty_level
            FROM quizzes q
            LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
            $whereClause
            GROUP BY q.id, q.title
            HAVING total_attempts > 0
            ORDER BY average_score ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $result;
    }
    
    /**
     * Get student performance details
     */
    public function getStudentPerformance($student_id, $date_from = null, $date_to = null) {
        $conditions = ["qa.student_id = :student_id"];
        $params = ['student_id' => $student_id];
        
        if ($date_from) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = "WHERE " . implode(" AND ", $conditions);
        
        $sql = "
            SELECT 
                qa.id,
                qa.score,
                qa.completed_at,
                q.title as quiz_title,
                q.description as quiz_description
            FROM quiz_attempts qa
            INNER JOIN quizzes q ON qa.quiz_id = q.id
            $whereClause
            ORDER BY qa.completed_at DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get quiz performance details
     */
    public function getQuizPerformance($quiz_id, $date_from = null, $date_to = null) {
        $conditions = ["qa.quiz_id = :quiz_id"];
        $params = ['quiz_id' => $quiz_id];
        
        if ($date_from) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = "WHERE " . implode(" AND ", $conditions);
        
        $sql = "
            SELECT 
                qa.id,
                qa.score,
                qa.completed_at,
                u.username,
                u.email
            FROM quiz_attempts qa
            INNER JOIN users u ON qa.student_id = u.id
            $whereClause
            ORDER BY qa.completed_at DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all students for filter dropdown
     */
    public function getAllStudents() {
        $sql = "
            SELECT DISTINCT u.id, u.username, u.email
            FROM users u
            INNER JOIN quiz_attempts qa ON u.id = qa.student_id
            WHERE u.role = 'student'
            ORDER BY u.username
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all quizzes for filter dropdown
     */
    public function getAllQuizzes() {
        $sql = "
            SELECT DISTINCT q.id, q.title, q.description
            FROM quizzes q
            INNER JOIN quiz_attempts qa ON q.id = qa.quiz_id
            ORDER BY q.title
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Export quiz performance data
     */
    public function exportQuizPerformance($format = 'csv', $filters = []) {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(qa.completed_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(qa.completed_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['student_id'])) {
            $conditions[] = "qa.student_id = :student_id";
            $params['student_id'] = $filters['student_id'];
        }
        
        if (!empty($filters['quiz_id'])) {
            $conditions[] = "qa.quiz_id = :quiz_id";
            $params['quiz_id'] = $filters['quiz_id'];
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                u.username as student_name,
                u.email as student_email,
                q.title as quiz_title,
                qa.score,
                qa.completed_at,
                qa.total_points
            FROM quiz_attempts qa
            INNER JOIN users u ON qa.student_id = u.id
            INNER JOIN quizzes q ON qa.quiz_id = q.id
            $whereClause
            ORDER BY qa.completed_at DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        switch ($format) {
            case 'csv':
                $this->exportToCSV($data);
                break;
            case 'excel':
                $this->exportToExcel($data);
                break;
            case 'pdf':
                $this->exportToPDF($data);
                break;
        }
    }
    
    /**
     * Export data to CSV
     */
    private function exportToCSV($data) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="quiz_performance_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, ['Student Name', 'Student Email', 'Quiz Title', 'Score', 'Completed At', 'Total Points']);
        
        // CSV data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['student_name'],
                $row['student_email'],
                $row['quiz_title'],
                $row['score'],
                $row['completed_at'],
                $row['total_points']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data to Excel (simplified CSV with Excel headers)
     */
    private function exportToExcel($data) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="quiz_performance_' . date('Y-m-d') . '.xls"');
        
        echo "Student Name\tStudent Email\tQuiz Title\tScore\tCompleted At\tTotal Points\n";
        
        foreach ($data as $row) {
            echo $row['student_name'] . "\t";
            echo $row['student_email'] . "\t";
            echo $row['quiz_title'] . "\t";
            echo $row['score'] . "\t";
            echo $row['completed_at'] . "\t";
            echo $row['total_points'] . "\n";
        }
        
        exit;
    }
    
    /**
     * Export data to PDF (basic implementation)
     */
    private function exportToPDF($data) {
        // This is a simplified PDF export - in a real application, you'd use a proper PDF library
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="quiz_performance_' . date('Y-m-d') . '.txt"');
        
        echo "QUIZ PERFORMANCE REPORT\n";
        echo "Generated on: " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat("=", 50) . "\n\n";
        
        foreach ($data as $row) {
            echo "Student: " . $row['student_name'] . "\n";
            echo "Email: " . $row['student_email'] . "\n";
            echo "Quiz: " . $row['quiz_title'] . "\n";
            echo "Score: " . $row['score'] . "\n";
            echo "Completed: " . $row['completed_at'] . "\n";
            echo "Total Points: " . $row['total_points'] . "\n";
            echo str_repeat("-", 30) . "\n";
        }
        
        exit;
    }
}
?>
