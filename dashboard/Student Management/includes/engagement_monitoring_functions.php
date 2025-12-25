<?php
/**
 * Engagement Monitoring Analysis Functions
 * Provides comprehensive analytics for student engagement tracking
 */

class EngagementMonitor {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database;
    }
    
    /**
     * Get overall engagement statistics
     */
    public function getOverallStatistics($date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(e.enrolled_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(e.enrolled_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                COUNT(DISTINCT e.student_id) as total_students,
                COUNT(DISTINCT e.course_id) as total_courses,
                AVG(TIMESTAMPDIFF(DAY, e.enrolled_at, NOW())) as avg_enrollment_days,
                COUNT(DISTINCT CASE WHEN e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN e.student_id END) as recent_enrollments
            FROM enrollments e
            $whereClause
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_students' => (int)$result['total_students'],
            'total_courses' => (int)$result['total_courses'],
            'avg_enrollment_days' => round($result['avg_enrollment_days'] ?? 0, 1),
            'recent_enrollments' => (int)$result['recent_enrollments'],
            'login_frequency' => $this->calculateLoginFrequency($date_from, $date_to),
            'dropoff_rate' => $this->calculateDropoffRate($date_from, $date_to)
        ];
    }
    
    /**
     * Get most engaged students
     */
    public function getMostEngagedStudents($limit = 10, $date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(e.enrolled_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(e.enrolled_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                u.id,
                u.username,
                COUNT(e.course_id) as enrolled_courses,
                AVG(TIMESTAMPDIFF(DAY, e.enrolled_at, NOW())) as avg_enrollment_days,
                MAX(e.enrolled_at) as last_enrollment,
                COALESCE(sp.profile_picture, '') as profile_picture
            FROM users u
            INNER JOIN enrollments e ON u.id = e.student_id
            LEFT JOIN student_preferences sp ON u.id = sp.student_id
            $whereClause
            GROUP BY u.id, u.username, sp.profile_picture
            HAVING enrolled_courses > 0
            ORDER BY enrolled_courses DESC, avg_enrollment_days ASC
            LIMIT :limit
        ";
        
        $params['limit'] = $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get course engagement statistics
     */
    public function getCourseEngagementStats($date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(e.enrolled_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(e.enrolled_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                c.id,
                c.title,
                COUNT(e.student_id) as enrollment_count,
                AVG(TIMESTAMPDIFF(DAY, e.enrolled_at, NOW())) as avg_enrollment_days,
                COUNT(DISTINCT CASE WHEN e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN e.student_id END) as recent_enrollments
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id
            $whereClause
            GROUP BY c.id, c.title
            ORDER BY enrollment_count DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Always return real data if available, only return empty array if no data exists
        if (empty($results)) {
            // Check if there are any courses at all
            $courseCheck = "SELECT COUNT(*) as course_count FROM courses";
            $stmt = $this->pdo->prepare($courseCheck);
            $stmt->execute();
            $courseCount = $stmt->fetch(PDO::FETCH_ASSOC)['course_count'];
            
            if ($courseCount > 0) {
                // There are courses but no enrollments, show courses with 0 enrollments
                $sql = "SELECT id, title, 0 as enrollment_count, 0 as avg_enrollment_days, 0 as recent_enrollments FROM courses ORDER BY title";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            // If no courses exist, return empty array
        }
        
        return $results;
    }
    
    /**
     * Get recent enrollment activities
     */
    public function getRecentEnrollments($limit = 20, $date_from = null, $date_to = null, $offset = 0, $pageLimit = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(e.enrolled_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(e.enrolled_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Use pageLimit if provided, otherwise use limit
        $actualLimit = $pageLimit ?? $limit;
        
        $sql = "
            SELECT 
                u.username,
                c.title as course_title,
                e.enrolled_at,
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, e.enrolled_at, NOW()) < 60 THEN CONCAT(TIMESTAMPDIFF(MINUTE, e.enrolled_at, NOW()), ' minutes ago')
                    WHEN TIMESTAMPDIFF(HOUR, e.enrolled_at, NOW()) < 24 THEN CONCAT(TIMESTAMPDIFF(HOUR, e.enrolled_at, NOW()), ' hours ago')
                    WHEN TIMESTAMPDIFF(DAY, e.enrolled_at, NOW()) = 1 THEN '1 day ago'
                    ELSE CONCAT(TIMESTAMPDIFF(DAY, e.enrolled_at, NOW()), ' days ago')
                END as time_ago,
                TIMESTAMPDIFF(DAY, e.enrolled_at, NOW()) as days_since_enrollment,
                COALESCE(sp.profile_picture, '') as profile_picture
            FROM enrollments e
            INNER JOIN users u ON e.student_id = u.id
            INNER JOIN courses c ON e.course_id = c.id
            LEFT JOIN student_preferences sp ON u.id = sp.student_id
            $whereClause
            ORDER BY e.enrolled_at DESC
            LIMIT :offset, :limit
        ";
        
        $params['offset'] = $offset;
        $params['limit'] = $actualLimit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total count of recent enrollments for pagination
     */
    public function getTotalRecentEnrollments($date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(e.enrolled_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(e.enrolled_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT COUNT(*) as total
            FROM enrollments e
            INNER JOIN users u ON e.student_id = u.id
            INNER JOIN courses c ON e.course_id = c.id
            $whereClause
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }
    
    /**
     * Get enrollment trend data
     */
    public function getEnrollmentTrend($days = 30, $date_from = null, $date_to = null) {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(e.enrolled_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(e.enrolled_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                DATE(e.enrolled_at) as date,
                COUNT(e.id) as enrollments
            FROM enrollments e
            $whereClause
            GROUP BY DATE(e.enrolled_at)
            ORDER BY date DESC
            LIMIT :days
        ";
        
        $params['days'] = $days;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all students for filters
     */
    public function getAllStudents() {
        $sql = "SELECT id, username FROM users WHERE role = 'student' ORDER BY username";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all courses for filters
     */
    public function getAllCourses() {
        $sql = "SELECT id, title FROM courses ORDER BY title";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get time spent learning data
     */
    public function getTimeSpentData($date_from = null, $date_to = null) {
        try {
            // Check if user_sessions table exists
            $checkTable = "SHOW TABLES LIKE 'user_sessions'";
            $stmt = $this->pdo->prepare($checkTable);
            $stmt->execute();
            $tableExists = $stmt->fetch();
            
            if (!$tableExists) {
                // Return empty array if table doesn't exist
                return [];
            }
            
            $conditions = [];
            $params = [];
            
            if ($date_from) {
                $conditions[] = "DATE(us.login_time) >= :date_from";
                $params['date_from'] = $date_from;
            }
            
            if ($date_to) {
                $conditions[] = "DATE(us.login_time) <= :date_to";
                $params['date_to'] = $date_to;
            }
            
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // Get daily time spent data for the last 7 days
            $sql = "
                SELECT 
                    DATE(us.login_time) as date,
                    DAYNAME(us.login_time) as day_name,
                    AVG(us.time_spent_minutes) as avg_time_minutes,
                    COUNT(DISTINCT us.user_id) as total_students,
                    SUM(us.time_spent_minutes) as total_time_minutes
                FROM user_sessions us
                $whereClause
                GROUP BY DATE(us.login_time), DAYNAME(us.login_time)
                ORDER BY us.login_time DESC
                LIMIT 7
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no real data, check if we have any sessions at all
            if (empty($results)) {
                $sessionCheck = "SELECT COUNT(*) as session_count FROM user_sessions";
                $stmt = $this->pdo->prepare($sessionCheck);
                $stmt->execute();
                $sessionCount = $stmt->fetch(PDO::FETCH_ASSOC)['session_count'];
                
                if ($sessionCount > 0) {
                    // There are sessions but no data for the date range, get all sessions
                    $sql = "
                        SELECT 
                            DATE(us.login_time) as date,
                            DAYNAME(us.login_time) as day_name,
                            AVG(us.time_spent_minutes) as avg_time_minutes,
                            COUNT(DISTINCT us.user_id) as total_students
                        FROM user_sessions us
                        GROUP BY DATE(us.login_time), DAYNAME(us.login_time)
                        ORDER BY us.login_time DESC
                        LIMIT 7
                    ";
                    
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute();
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($results)) {
                        $data = [];
                        foreach ($results as $result) {
                            $data[] = [
                                'period' => $result['day_name'],
                                'avg_time_minutes' => round($result['avg_time_minutes'] ?? 0),
                                'total_students' => (int)$result['total_students']
                            ];
                        }
                        return $data;
                    }
                }
                
                // Return empty array if no sessions exist
                return [];
            }
            
            // Format the results
            $data = [];
            foreach ($results as $result) {
                $data[] = [
                    'period' => $result['day_name'],
                    'avg_time_minutes' => round($result['avg_time_minutes'] ?? 0),
                    'total_students' => (int)$result['total_students']
                ];
            }
            
            return $data;
            
        } catch (Exception $e) {
            // Return empty array if there's any error
            return [];
        }
    }
    
    
/**
 * Calculate login frequency based on course access patterns
 */
private function calculateLoginFrequency($date_from = null, $date_to = null) {
    try {
        $conditions = [];
        $params = [];
        
        if ($date_from) {
            $conditions[] = "DATE(e.enrolled_at) >= :date_from";
            $params['date_from'] = $date_from;
        }
        
        if ($date_to) {
            $conditions[] = "DATE(e.enrolled_at) <= :date_to";
            $params['date_to'] = $date_to;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Calculate the period in days
        $start_date = $date_from ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = $date_to ?: date('Y-m-d');
        $days_period = max(1, (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24));
        $weeks_period = max(1, $days_period / 7);
        
        // Count distinct access days per student as a proxy for login frequency
        $sql = "
            SELECT 
                COUNT(DISTINCT e.student_id) as total_students,
                COUNT(DISTINCT DATE(cp.last_accessed_at)) as total_access_days,
                COUNT(DISTINCT CASE WHEN cp.last_accessed_at IS NOT NULL THEN e.student_id END) as active_students
            FROM enrollments e
            LEFT JOIN course_progress cp ON e.student_id = cp.student_id AND e.course_id = cp.course_id
            $whereClause
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If we have active students with access data
        if ($result['active_students'] > 0 && $result['total_access_days'] > 0) {
            // Average access days per active student
            $avgAccessDaysPerStudent = $result['total_access_days'] / $result['active_students'];
            // Convert to weekly frequency
            $loginsPerWeek = $avgAccessDaysPerStudent / $weeks_period;
            return round($loginsPerWeek, 1);
        }
        
        // Fallback: if no access data, estimate based on enrollment activity
        if ($result['total_students'] > 0) {
            // Assume students who enrolled recently logged in at least once
            $estimatedLogins = $result['total_students'];
            $loginsPerWeek = ($estimatedLogins / $result['total_students']) / $weeks_period;
            return round(max(0.1, $loginsPerWeek), 1); // Return at least 0.1 if there are students
        }
        
        return 0;
        
    } catch (Exception $e) {
        return 0;
    }
}
    
    private function calculateDropoffRate($date_from = null, $date_to = null) {
        try {
            $conditions = [];
            $params = [];
            
            if ($date_from) {
                $conditions[] = "DATE(e.enrolled_at) >= :date_from";
                $params['date_from'] = $date_from;
            }
            
            if ($date_to) {
                $conditions[] = "DATE(e.enrolled_at) <= :date_to";
                $params['date_to'] = $date_to;
            }
            
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // Get all enrolled students and their last activity
            $sql = "
                SELECT 
                    COUNT(DISTINCT e.student_id) as total_students,
                    COUNT(DISTINCT CASE 
                        WHEN cp.last_accessed_at IS NULL OR cp.last_accessed_at < DATE_SUB(NOW(), INTERVAL 14 DAY) 
                        THEN e.student_id 
                    END) as inactive_students
                FROM enrollments e
                LEFT JOIN course_progress cp ON e.student_id = cp.student_id AND e.course_id = cp.course_id
                $whereClause
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total_students'] > 0) {
                $dropoffRate = ($result['inactive_students'] / $result['total_students']) * 100;
                return round($dropoffRate, 1);
            }
            
            return 0;
            
        } catch (Exception $e) {
            return 0;
        }
    }    
}
?>
