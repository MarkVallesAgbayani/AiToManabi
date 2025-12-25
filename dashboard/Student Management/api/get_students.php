<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../config/database.php';

try {
    $teacher_id = $_SESSION['user_id'];
    
    // Debug logging
    error_log("Student Profiles API - Teacher ID: " . $teacher_id);
    
    // Get parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $course_filter = isset($_GET['course']) ? $_GET['course'] : 'all';
    $progress_filter = isset($_GET['progress']) ? $_GET['progress'] : '';
    $enrollment_filter = isset($_GET['enrollment']) ? $_GET['enrollment'] : '';
    $activity_filter = isset($_GET['activity']) ? $_GET['activity'] : '';
    $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    
    $offset = ($page - 1) * $limit;
    
    // Build the base query
    $baseQuery = "
        SELECT DISTINCT u.id, u.username, u.email, 
               COALESCE(u.first_name, '') as first_name,
               COALESCE(u.last_name, '') as last_name,
               COALESCE(u.last_login_at, u.login_time) as last_login, 
               COALESCE(u.status, 'active') as status, 
               u.created_at,
               COALESCE(sp.profile_picture, '') as profile_picture,
               CASE 
                   WHEN COALESCE(u.last_login_at, u.login_time) IS NULL THEN 'Long Inactive'
                   WHEN COALESCE(u.last_login_at, u.login_time) < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Long Inactive'
                   WHEN COALESCE(u.last_login_at, u.login_time) < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Inactive'
                   ELSE 'Active'
               END as activity_status,
               CONCAT(
                   COALESCE(SUM(CASE WHEN cp.completion_status = 'completed' THEN 1 ELSE 0 END), 0),
                   ' of ',
                   COALESCE(COUNT(DISTINCT e.course_id), 0),
                   ' modules completed'
               ) as completion_rate,
               NULL as student_id,
               COALESCE(COUNT(DISTINCT e.course_id), 0) as enrolled_courses,
               COALESCE(AVG(cp.completion_percentage), 0) as overall_progress
        FROM users u
        LEFT JOIN student_preferences sp ON u.id = sp.student_id
        LEFT JOIN enrollments e ON u.id = e.student_id
        LEFT JOIN course_progress cp ON u.id = cp.student_id AND e.course_id = cp.course_id
        WHERE u.role = 'student'
    ";
    
    $params = [];
    
    // Add search filter
    if (!empty($search)) {
        $baseQuery .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Add status filter
    if ($filter !== 'all') {
        $baseQuery .= " AND u.status = ?";
        $params[] = $filter;
    }
    
    // Add course filter (if needed - you removed the dropdown but kept the logic)
    if ($course_filter !== 'all' && !empty($course_filter)) {
        $baseQuery .= " AND e.course_id = ?";
        $params[] = $course_filter;
    }
    
    // Add enrollment date filter
    if (!empty($enrollment_filter)) {
        switch ($enrollment_filter) {
            case 'last_week':
                $baseQuery .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'last_month':
                $baseQuery .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'last_3_months':
                $baseQuery .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                break;
        }
    }
    
    // Add activity filter
    if (!empty($activity_filter)) {
        switch ($activity_filter) {
            case 'today':
                $baseQuery .= " AND COALESCE(u.last_login_at, u.login_time) >= CURDATE()";
                break;
            case 'week':
                $baseQuery .= " AND COALESCE(u.last_login_at, u.login_time) >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $baseQuery .= " AND COALESCE(u.last_login_at, u.login_time) >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
    }
    
    $baseQuery .= " GROUP BY u.id, u.username, u.email, u.first_name, u.last_name, u.last_login_at, u.login_time, u.status, u.created_at, sp.profile_picture";
    
    // Add progress filter (HAVING clause because it's calculated)
    if (!empty($progress_filter)) {
        switch ($progress_filter) {
            case 'not_started':
                $baseQuery .= " HAVING overall_progress = 0";
                break;
            case 'in_progress':
                $baseQuery .= " HAVING overall_progress > 0 AND overall_progress < 100";
                break;
            case 'completed':
                $baseQuery .= " HAVING overall_progress = 100";
                break;
        }
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM ({$baseQuery}) as student_count";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Add sorting
    $orderBy = " ORDER BY ";
    switch ($sort_by) {
        case 'name':
            $orderBy .= "u.username ASC";
            break;
        case 'progress':
            $orderBy .= "overall_progress DESC";
            break;
        case 'last_activity':
            $orderBy .= "COALESCE(u.last_login_at, u.login_time) DESC";
            break;
        case 'enrollment_date':
            $orderBy .= "u.created_at DESC";
            break;
        default:
            $orderBy .= "u.username ASC";
    }
    
    // Get paginated results
    $query = $baseQuery . $orderBy . " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("Student Profiles API - Found " . count($students) . " students");
    
    // Format the data
    foreach ($students as &$student) {
        $student['overall_progress'] = $student['overall_progress'] ?: 0;
        // Format progress to 1 decimal place
        $student['overall_progress'] = number_format((float)$student['overall_progress'], 1);
        $student['enrolled_courses'] = intval($student['enrolled_courses']);
        
        // Format dates
        if ($student['last_login']) {
            $student['last_login_formatted'] = date('Y-m-d H:i:s', strtotime($student['last_login']));
        }
        
        if ($student['created_at']) {
            $student['joined_date'] = date('Y-m-d', strtotime($student['created_at']));
        }
    }
    
    $totalPages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'total' => intval($total),
        'page' => $page,
        'totalPages' => $totalPages,
        'limit' => $limit
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_students.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch students data'
    ]);
}
