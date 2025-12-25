<?php
/**
 * Completion Reports Functions
 * Handles all completion-related data processing and calculations
 */

class CompletionReportsAnalyzer {
    private $pdo;
    private $teacher_id;

    public function __construct($pdo, $teacher_id) {
        $this->pdo = $pdo;
        $this->teacher_id = $teacher_id;
    }

    /**
     * Get overall completion statistics
     */
    public function getOverallCompletionStats($date_from = null, $date_to = null, $course_id = '') {
        $date_from = $date_from ?? date('Y-m-01', strtotime('-3 months'));
        $date_to = $date_to ?? date('Y-m-d');
        $course_filter = $this->buildCourseFilter($course_id);
        $params = [$this->teacher_id, $date_from, $date_to];
        
        if (!empty($course_id)) {
            $params[] = $course_id;
        }

        // Use the same logic as the teacher dashboard
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT e.id) as total_enrollments,
                COUNT(DISTINCT CASE WHEN cp.completion_status = 'completed' THEN e.id END) as completed_enrollments,
                COUNT(DISTINCT CASE WHEN cp.completion_status = 'in_progress' THEN e.id END) as in_progress_enrollments,
                COUNT(DISTINCT CASE WHEN cp.completion_status = 'not_started' OR cp.completion_status IS NULL THEN e.id END) as not_started_enrollments
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
            WHERE c.teacher_id = ? 
            AND e.enrolled_at BETWEEN ? AND ?
            $course_filter
        ");
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        // Handle case where no data is returned
        if (!$result) {
            return [
                'total_enrollments' => 0,
                'completed_enrollments' => 0,
                'in_progress_enrollments' => 0,
                'not_started_enrollments' => 0,
                'completion_rate' => 0
            ];
        }
        
        $completion_rate = 0;
        if ($result['total_enrollments'] > 0) {
            $completion_rate = round(($result['completed_enrollments'] / $result['total_enrollments']) * 100, 1);
        }

        return [
            'total_enrollments' => (int)$result['total_enrollments'],
            'completed_enrollments' => (int)$result['completed_enrollments'],
            'in_progress_enrollments' => (int)$result['in_progress_enrollments'],
            'not_started_enrollments' => (int)$result['not_started_enrollments'],
            'completion_rate' => $completion_rate
        ];
    }

/**
 * Get average progress per student
 */
public function getAverageProgressPerStudent($date_from = null, $date_to = null, $course_id = '') {
    $date_from = $date_from ?? date('Y-m-01', strtotime('-3 months'));
    $date_to = $date_to ?? date('Y-m-d');
    $course_filter = $this->buildCourseFilter($course_id);
    $params = [$this->teacher_id, $date_from, $date_to];
    
    if (!empty($course_id)) {
        $params[] = $course_id;
    }

    // Simplified and accurate average progress calculation
    $stmt = $this->pdo->prepare("
        SELECT 
            COALESCE(ROUND(AVG(cp.completion_percentage), 1), 0) as avg_progress,
            COUNT(DISTINCT e.student_id) as total_students,
            MIN(cp.completion_percentage) as min_progress,
            MAX(cp.completion_percentage) as max_progress
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
        WHERE c.teacher_id = ? 
        AND e.enrolled_at BETWEEN ? AND ?
        $course_filter
    ");
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    // Handle case where no data is returned
    if (!$result) {
        return [
            'avg_progress' => 0,
            'total_students' => 0,
            'min_progress' => 0,
            'max_progress' => 0
        ];
    }
    
    return [
        'avg_progress' => round($result['avg_progress'] ?? 0, 1),
        'total_students' => (int)($result['total_students'] ?? 0),
        'min_progress' => round($result['min_progress'] ?? 0, 1),
        'max_progress' => round($result['max_progress'] ?? 0, 1)
    ];
}
    /**
     * Get module completion breakdown
     */
    public function getModuleCompletionBreakdown($date_from = null, $date_to = null, $course_id = '') {
        $date_from = $date_from ?? date('Y-m-01', strtotime('-3 months'));
        $date_to = $date_to ?? date('Y-m-d');
        $course_filter = $this->buildCourseFilter($course_id);
        $params = [$date_from, $date_to, $this->teacher_id];
        
        if (!empty($course_id)) {
            $params[] = $course_id;
        }

        // Use the same logic as the teacher dashboard for module breakdown
        $stmt = $this->pdo->prepare("
            SELECT 
                c.id,
                c.title,
                c.description,
                c.created_at,
                COUNT(DISTINCT e.id) as total_enrollments,
                COUNT(DISTINCT CASE WHEN cp.completion_status = 'completed' THEN e.id END) as completed_enrollments,
                COUNT(DISTINCT CASE WHEN cp.completion_status = 'in_progress' THEN e.id END) as in_progress_enrollments,
                ROUND(
                    (COUNT(DISTINCT CASE WHEN cp.completion_status = 'completed' THEN e.id END) / 
                     NULLIF(COUNT(DISTINCT e.id), 0)) * 100, 1
                ) as completion_rate,
                LEAST(100.0, ROUND(
                    (SUM(
                        CASE 
                            WHEN cp.completion_percentage IS NOT NULL 
                            THEN (cp.completion_percentage / 100.0) * (
                                SELECT COUNT(ch.id) 
                                FROM chapters ch 
                                JOIN sections s ON ch.section_id = s.id 
                                WHERE s.course_id = c.id
                            )
                            ELSE 0 
                        END
                    ) * 100.0) / 
                    NULLIF(
                        COUNT(DISTINCT e.student_id) * (
                            SELECT COUNT(ch.id) 
                            FROM chapters ch 
                            JOIN sections s ON ch.section_id = s.id 
                            WHERE s.course_id = c.id
                        ), 0
                    ), 1
                )) as avg_progress_percentage
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id AND e.enrolled_at BETWEEN ? AND ?
            LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
            WHERE c.teacher_id = ? AND c.is_archived = 0
            $course_filter
            GROUP BY c.id, c.title, c.description, c.created_at
            ORDER BY completion_rate DESC, total_enrollments DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

/**
 * Get on-time vs delayed completion data
 */
public function getTimelinessData($date_from = null, $date_to = null, $course_id = '') {
    $date_from = $date_from ?? date('Y-m-01', strtotime('-3 months'));
    $date_to = $date_to ?? date('Y-m-d');
    $course_filter = $this->buildCourseFilter($course_id);
    $params = [$this->teacher_id, $date_from, $date_to];
    
    if (!empty($course_id)) {
        $params[] = $course_id;
    }

    $stmt = $this->pdo->prepare("
        SELECT 
            COUNT(DISTINCT CASE 
                WHEN cp.completion_status = 'completed' 
                AND cp.completed_at IS NOT NULL
                AND DATEDIFF(cp.completed_at, e.enrolled_at) <= 30 
                THEN e.id 
            END) as on_time_completions,
            COUNT(DISTINCT CASE 
                WHEN cp.completion_status = 'completed' 
                AND cp.completed_at IS NOT NULL
                AND DATEDIFF(cp.completed_at, e.enrolled_at) > 30 
                AND DATEDIFF(cp.completed_at, e.enrolled_at) <= 60
                THEN e.id 
            END) as delayed_30_60_days,
            COUNT(DISTINCT CASE 
                WHEN cp.completion_status = 'completed' 
                AND cp.completed_at IS NOT NULL
                AND DATEDIFF(cp.completed_at, e.enrolled_at) > 60 
                THEN e.id 
            END) as delayed_over_60_days,
            COUNT(DISTINCT CASE 
                WHEN cp.completion_status != 'completed' OR cp.completion_status IS NULL
                THEN e.id 
            END) as not_completed,
            AVG(CASE 
                WHEN cp.completion_status = 'completed' 
                AND cp.completed_at IS NOT NULL
                THEN DATEDIFF(cp.completed_at, e.enrolled_at) 
            END) as avg_completion_days
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
        WHERE c.teacher_id = ? 
        AND e.enrolled_at BETWEEN ? AND ?
        $course_filter
    ");
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    // Handle case where no data is returned
    if (!$result) {
        return [
            'on_time_completions' => 0,
            'delayed_completions' => 0,
            'delayed_30_60_days' => 0,
            'delayed_over_60_days' => 0,
            'not_completed' => 0,
            'avg_completion_days' => 0
        ];
    }
    
    $delayed_completions = ($result['delayed_30_60_days'] ?? 0) + ($result['delayed_over_60_days'] ?? 0);
    
    return [
        'on_time_completions' => (int)($result['on_time_completions'] ?? 0),
        'delayed_completions' => $delayed_completions,
        'delayed_30_60_days' => (int)($result['delayed_30_60_days'] ?? 0),
        'delayed_over_60_days' => (int)($result['delayed_over_60_days'] ?? 0),
        'not_completed' => (int)($result['not_completed'] ?? 0),
        'avg_completion_days' => round($result['avg_completion_days'] ?? 0, 1)
    ];
}


    /**
     * Get completion trends over time
     */
    public function getCompletionTrends($date_from, $date_to, $course_id = '') {
        $course_filter = $this->buildCourseFilter($course_id);
        $params = [$this->teacher_id, $date_from, $date_to];
        
        if (!empty($course_id)) {
            $params[] = $course_id;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(e.enrolled_at) as enrollment_date,
                COUNT(DISTINCT e.id) as daily_enrollments,
                COUNT(DISTINCT CASE WHEN cp.completion_status = 'completed' THEN e.id END) as daily_completions
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
            WHERE c.teacher_id = ? 
            AND e.enrolled_at BETWEEN ? AND ?
            $course_filter
            GROUP BY DATE(e.enrolled_at)
            ORDER BY enrollment_date ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get top performing students
     */
    public function getTopPerformingStudents($date_from, $date_to, $course_id = '', $limit = 10) {
        $course_filter = $this->buildCourseFilter($course_id);
        $params = [$this->teacher_id, $date_from, $date_to, $limit];
        
        if (!empty($course_id)) {
            $params[] = $course_id;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                COUNT(DISTINCT e.course_id) as courses_enrolled,
                COUNT(DISTINCT CASE WHEN cp.completion_status = 'completed' THEN e.course_id END) as courses_completed,
                AVG(cp.completion_percentage) as avg_progress,
                MAX(cp.updated_at) as last_activity
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
            WHERE c.teacher_id = ? 
            AND e.enrolled_at BETWEEN ? AND ?
            $course_filter
            GROUP BY u.id, u.username, u.email
            HAVING courses_enrolled > 0
            ORDER BY avg_progress DESC, courses_completed DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get courses for filter dropdown
     */
    public function getCourses() {
        $stmt = $this->pdo->prepare("
            SELECT 
                id, 
                title, 
                description,
                created_at,
                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
            FROM courses c 
            WHERE teacher_id = ? AND is_archived = 0 
            ORDER BY title
        ");
        $stmt->execute([$this->teacher_id]);
        return $stmt->fetchAll();
    }

    /**
     * Build course filter clause for SQL queries
     */
    private function buildCourseFilter($course_id) {
        if (!empty($course_id)) {
            return ' AND c.id = ?';
        }
        return '';
    }

    /**
     * Get comprehensive completion report
     */
    public function getComprehensiveReport($date_from = null, $date_to = null, $course_id = '') {
        $date_from = $date_from ?? date('Y-m-01', strtotime('-3 months'));
        $date_to = $date_to ?? date('Y-m-d');
        return [
            'overall_stats' => $this->getOverallCompletionStats($date_from, $date_to, $course_id),
            'progress_stats' => $this->getAverageProgressPerStudent($date_from, $date_to, $course_id),
            'module_breakdown' => $this->getModuleCompletionBreakdown($date_from, $date_to, $course_id),
            'timeliness_data' => $this->getTimelinessData($date_from, $date_to, $course_id),
            'completion_trends' => $this->getCompletionTrends($date_from, $date_to, $course_id),
            'top_students' => $this->getTopPerformingStudents($date_from, $date_to, $course_id)
        ];
    }
}

/**
 * Utility functions for completion reports
 */
class CompletionReportsUtils {
    
    /**
     * Format completion rate for display
     */
    public static function formatCompletionRate($rate) {
        return number_format($rate, 1) . '%';
    }

    /**
     * Get completion status color
     */
    public static function getCompletionStatusColor($rate) {
        if ($rate >= 80) return 'text-green-600';
        if ($rate >= 60) return 'text-yellow-600';
        if ($rate >= 40) return 'text-orange-600';
        return 'text-red-600';
    }

    /**
     * Get completion status badge class
     */
    public static function getCompletionStatusBadge($rate) {
        if ($rate >= 80) return 'bg-green-100 text-green-800';
        if ($rate >= 60) return 'bg-yellow-100 text-yellow-800';
        if ($rate >= 40) return 'bg-orange-100 text-orange-800';
        return 'bg-red-100 text-red-800';
    }

    /**
     * Calculate completion trend
     */
    public static function calculateTrend($current, $previous) {
        if ($previous == 0) return 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Format time duration
     */
    public static function formatDuration($days) {
        if ($days < 1) return 'Less than 1 day';
        if ($days < 7) return $days . ' day' . ($days > 1 ? 's' : '');
        if ($days < 30) return round($days / 7, 1) . ' week' . (round($days / 7, 1) > 1 ? 's' : '');
        return round($days / 30, 1) . ' month' . (round($days / 30, 1) > 1 ? 's' : '');
    }
}
?>
