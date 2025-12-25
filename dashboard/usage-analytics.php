<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include required files
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'real_time_activity_logger.php';
require_once '../includes/admin_notifications.php';
require_once 'audit_logger.php';
require_once 'reports.php';
require_once '../components/collapsible_section.php';


// Check analytics access - either by permission or admin role
$has_analytics_access = false;

// First check if user has analytics permissions
if (function_exists('hasAnyPermission')) {
    $analytics_permissions = ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data'];
    $has_analytics_access = hasAnyPermission($pdo, $_SESSION['user_id'], $analytics_permissions);
}

// Fallback: Check if user has admin role
if (!$has_analytics_access && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $has_analytics_access = true;
}

// Final fallback: Check user role directly from database
if (!$has_analytics_access) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        $has_analytics_access = true;
    }
}

if (!$has_analytics_access) {
    header('Location: ../index.php');
    exit();
}

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Check export permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'analytics_export_pdf')) {
        header('Location: ../index.php');
        exit();
    }
    
    // Validate that at least one export column is selected
    $export_columns = $_GET['export_columns'] ?? [];
    if (empty($export_columns)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Please select at least one export column to generate the PDF report.'
        ]);
        exit;
    }
    
    $filters = [
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'view_type' => $_GET['view_type'] ?? 'daily',
        'role_filter' => $_GET['role_filter'] ?? '',
        // Support both export_columns and export_columns[] param styles
        'export_columns' => isset($_GET['export_columns']) ? (is_array($_GET['export_columns']) ? $_GET['export_columns'] : (isset($_GET['export_columns'][0]) ? $_GET['export_columns'] : $export_columns)) : $export_columns,
        'data_source' => $_GET['data_source'] ?? 'auto',
        'export_detail' => $_GET['export_detail'] ?? 'summary',
        'report_purpose' => $_GET['report_purpose'] ?? 'general',
        'confidentiality' => $_GET['confidentiality'] ?? 'internal',
        // Support summary_metrics[] from form
        'summary_metrics' => isset($_GET['summary_metrics']) ? (is_array($_GET['summary_metrics']) ? $_GET['summary_metrics'] : (isset($_GET['summary_metrics'][0]) ? $_GET['summary_metrics'] : ['totals','averages'])) : ['totals','averages'],
        'data_grouping' => $_GET['data_grouping'] ?? 'chronological',
        'admin_info_level' => $_GET['admin_info_level'] ?? 'name_only'
    ];
    
    // Use centralized report generator
    $reportGenerator = new ReportGenerator($pdo);
    $reportGenerator->generateUsageAnalyticsReport($filters, $_GET['export']);
    exit;
}

// Get filters from URL parameters
$filters = [
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'view_type' => $_GET['view_type'] ?? 'daily',
    'role_filter' => $_GET['role_filter'] ?? '',
    'search' => $_GET['search'] ?? '',
    'export_columns' => $_GET['export_columns'] ?? ['date', 'total_active', 'students', 'teachers', 'admins'],
    'data_source' => $_GET['data_source'] ?? 'auto',
    'export_detail' => $_GET['export_detail'] ?? 'summary',
    'report_purpose' => $_GET['report_purpose'] ?? 'general',
    'confidentiality' => $_GET['confidentiality'] ?? 'internal',
    'summary_metrics' => $_GET['summary_metrics'] ?? ['totals', 'averages'],
    'data_grouping' => $_GET['data_grouping'] ?? 'chronological',
    'admin_info_level' => $_GET['admin_info_level'] ?? 'name_only'
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Analytics Functions
function getActiveUsersData($pdo, $filters) {
    try {
        // Try to get data from user_activity_log first
        $data = getActiveUsersFromActivityLog($pdo, $filters);
        
        // If no data, try fallback sources
        if (empty($data)) {
            $data = getActiveUsersFromFallback($pdo, $filters);
        }
        
        return $data;
    } catch (PDOException $e) {
        error_log("Active users data error: " . $e->getMessage());
        return getActiveUsersFromFallback($pdo, $filters);
    }
}

function getActiveUsersFromActivityLog($pdo, $filters) {
    $whereClause = "WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
    $params = [$filters['date_from'], $filters['date_to']];
    
    if (!empty($filters['role_filter'])) {
        $whereClause .= " AND u.role = ?";
        $params[] = $filters['role_filter'];
    }
    
    $groupBy = '';
    $selectDate = '';
    
    switch ($filters['view_type']) {
        case 'daily':
            $selectDate = "DATE(ual.created_at) as period";
            $groupBy = "GROUP BY DATE(ual.created_at), u.role";
            break;
        case 'weekly':
            // Use Sunday–Saturday week grouping (mode 0)
            $selectDate = "YEARWEEK(ual.created_at, 0) as period";
            $groupBy = "GROUP BY YEARWEEK(ual.created_at, 0), u.role";
            break;
        case 'monthly':
            $selectDate = "DATE_FORMAT(ual.created_at, '%Y-%m') as period";
            $groupBy = "GROUP BY DATE_FORMAT(ual.created_at, '%Y-%m'), u.role";
            break;
        case 'yearly':
            $selectDate = "YEAR(ual.created_at) as period";
            $groupBy = "GROUP BY YEAR(ual.created_at), u.role";
            break;
    }
    
    $sql = "SELECT 
                $selectDate,
                u.role,
                COUNT(DISTINCT ual.user_id) as active_users
            FROM user_activity_log ual
            JOIN users u ON ual.user_id = u.id
            $whereClause
            $groupBy
            ORDER BY period DESC, u.role";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActiveUsersFromFallback($pdo, $filters) {
    // Try comprehensive_audit_trail first
    $catWhereClause = "WHERE 1=1";
    $catParams = [];
    
    if (!empty($filters['role_filter'])) {
        $catWhereClause .= " AND u.role = ?";
        $catParams[] = $filters['role_filter'];
    }
    
    // Add date filters for comprehensive_audit_trail
    if (!empty($filters['date_from'])) {
        $catWhereClause .= " AND DATE(cat.timestamp) >= ?";
        $catParams[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $catWhereClause .= " AND DATE(cat.timestamp) <= ?";
        $catParams[] = $filters['date_to'];
    }
    
    $catGroupBy = '';
    $catSelectDate = '';
    
    switch ($filters['view_type']) {
        case 'daily':
            $catSelectDate = "DATE(cat.timestamp) as period";
            $catGroupBy = "GROUP BY DATE(cat.timestamp), u.role";
            break;
        case 'weekly':
            // Use Sunday–Saturday week grouping (mode 0)
            $catSelectDate = "YEARWEEK(cat.timestamp, 0) as period";
            $catGroupBy = "GROUP BY YEARWEEK(cat.timestamp, 0), u.role";
            break;
        case 'monthly':
            $catSelectDate = "DATE_FORMAT(cat.timestamp, '%Y-%m') as period";
            $catGroupBy = "GROUP BY DATE_FORMAT(cat.timestamp, '%Y-%m'), u.role";
            break;
        case 'yearly':
            $catSelectDate = "YEAR(cat.timestamp) as period";
            $catGroupBy = "GROUP BY YEAR(cat.timestamp), u.role";
            break;
    }
    
    $catSql = "SELECT 
                $catSelectDate,
                u.role,
                COUNT(DISTINCT cat.user_id) as active_users
            FROM comprehensive_audit_trail cat
            JOIN users u ON cat.user_id = u.id
            $catWhereClause
            $catGroupBy
            ORDER BY period DESC, u.role";
    
    try {
        $stmt = $pdo->prepare($catSql);
        $stmt->execute($catParams);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($data)) {
            return $data;
        }
    } catch (PDOException $e) {
        // Continue to next fallback
    }
    
    // Fallback to login_logs
    $llWhereClause = "WHERE 1=1";
    $llParams = [];
    
    if (!empty($filters['role_filter'])) {
        $llWhereClause .= " AND u.role = ?";
        $llParams[] = $filters['role_filter'];
    }
    
    // Add date filters for login_logs
    if (!empty($filters['date_from'])) {
        $llWhereClause .= " AND DATE(ll.login_time) >= ?";
        $llParams[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $llWhereClause .= " AND DATE(ll.login_time) <= ?";
        $llParams[] = $filters['date_to'];
    }
    
    $llGroupBy = '';
    $llSelectDate = '';
    
    switch ($filters['view_type']) {
        case 'daily':
            $llSelectDate = "DATE(ll.login_time) as period";
            $llGroupBy = "GROUP BY DATE(ll.login_time), u.role";
            break;
        case 'weekly':
            // Use Sunday–Saturday week grouping (mode 0)
            $llSelectDate = "YEARWEEK(ll.login_time, 0) as period";
            $llGroupBy = "GROUP BY YEARWEEK(ll.login_time, 0), u.role";
            break;
        case 'monthly':
            $llSelectDate = "DATE_FORMAT(ll.login_time, '%Y-%m') as period";
            $llGroupBy = "GROUP BY DATE_FORMAT(ll.login_time, '%Y-%m'), u.role";
            break;
        case 'yearly':
            $llSelectDate = "YEAR(ll.login_time) as period";
            $llGroupBy = "GROUP BY YEAR(ll.login_time), u.role";
            break;
    }
    
    $llSql = "SELECT 
                $llSelectDate,
                u.role,
                COUNT(DISTINCT ll.user_id) as active_users
            FROM login_logs ll
            JOIN users u ON ll.user_id = u.id
            $llWhereClause
            $llGroupBy
            ORDER BY period DESC, u.role";
    
    $stmt = $pdo->prepare($llSql);
    $stmt->execute($llParams);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDashboardStats($pdo, $filters) {
    try {
        $stats = [];
        
        // Try to get stats from user_activity_log first
        $stats = getDashboardStatsFromActivityLog($pdo, $filters);
        
        // If no data, try fallback sources
        if ($stats['total_active'] == 0) {
            $stats = getDashboardStatsFromFallback($pdo, $filters);
        }
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        return getDashboardStatsFromFallback($pdo, $filters);
    }
}

// Helper function for calculating growth rates based on view type
function calculateGrowthRate($pdo, $filters, $current_active) {
    try {
        $view_type = $filters['view_type'] ?? 'daily';
        $date_to = $filters['date_to'];
        
        // Calculate previous period based on view_type
        switch ($view_type) {
            case 'daily':
                $prev_start = date('Y-m-d', strtotime($date_to . ' -1 day'));
                $prev_end = $prev_start;
                break;
            case 'weekly':
                // Previous week
                $prev_start = date('Y-m-d', strtotime($date_to . ' -1 week'));
                $prev_end = date('Y-m-d', strtotime($prev_start . ' +6 days'));
                break;
            case 'monthly':
                // Previous month
                $prev_start = date('Y-m-d', strtotime($date_to . ' -1 month'));
                $prev_end = date('Y-m-t', strtotime($prev_start)); // Last day of previous month
                break;
            case 'yearly':
                // Previous year
                $prev_start = date('Y-m-d', strtotime($date_to . ' -1 year'));
                $prev_end = date('Y-12-31', strtotime($prev_start)); // Last day of previous year
                break;
            default:
                // For custom ranges, use equivalent previous period
                $period_length = (strtotime($filters['date_to']) - strtotime($filters['date_from']));
                $prev_start = date('Y-m-d', strtotime($filters['date_from']) - $period_length);
                $prev_end = date('Y-m-d', strtotime($filters['date_from']) - 1);
        }
        
        // Get previous period active users
        $sql = "SELECT COUNT(DISTINCT ual.user_id) as prev_active
                FROM user_activity_log ual
                JOIN users u ON ual.user_id = u.id
                WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
        
        $prev_params = [$prev_start, $prev_end];
        if (!empty($filters['role_filter'])) {
            $sql .= " AND u.role = ?";
            $prev_params[] = $filters['role_filter'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($prev_params);
        $prev_active = $stmt->fetchColumn();
        
        // Calculate growth rate
        if ($prev_active > 0) {
            $growth_rate = round((($current_active - $prev_active) / $prev_active) * 100, 1);
            return min($growth_rate, 100); // Cap at 100%
        } else {
            return $current_active > 0 ? 100 : 0;
        }
        
    } catch (PDOException $e) {
        error_log("Growth rate calculation error: " . $e->getMessage());
        return 0;
    }
}

// Helper function for calculating growth rates from fallback data sources
function calculateGrowthRateFromFallback($pdo, $filters, $current_active) {
    try {
        $view_type = $filters['view_type'] ?? 'daily';
        $date_to = $filters['date_to'];
        
        // Calculate previous period based on view_type
        switch ($view_type) {
            case 'daily':
                $prev_start = date('Y-m-d', strtotime($date_to . ' -1 day'));
                $prev_end = $prev_start;
                break;
            case 'weekly':
                $prev_start = date('Y-m-d', strtotime($date_to . ' -1 week'));
                $prev_end = date('Y-m-d', strtotime($prev_start . ' +6 days'));
                break;
            case 'monthly':
                $prev_start = date('Y-m-d', strtotime($date_to . ' -1 month'));
                $prev_end = date('Y-m-t', strtotime($prev_start));
                break;
            case 'yearly':
                $prev_start = date('Y-m-d', strtotime($date_to . ' -1 year'));
                $prev_end = date('Y-12-31', strtotime($prev_start));
                break;
            default:
                $period_length = (strtotime($filters['date_to']) - strtotime($filters['date_from']));
                $prev_start = date('Y-m-d', strtotime($filters['date_from']) - $period_length);
                $prev_end = date('Y-m-d', strtotime($filters['date_from']) - 1);
        }
        
        // Try comprehensive_audit_trail first
        $sql = "SELECT COUNT(DISTINCT cat.user_id) as prev_active
                FROM comprehensive_audit_trail cat
                JOIN users u ON cat.user_id = u.id
                WHERE DATE(cat.timestamp) >= ? AND DATE(cat.timestamp) <= ?";
        
        $params = [$prev_start, $prev_end];
        if (!empty($filters['role_filter'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $prev_active = $stmt->fetchColumn();
        } catch (PDOException $e) {
            // Fallback to login_logs
            $sql = "SELECT COUNT(DISTINCT ll.user_id) as prev_active
                    FROM login_logs ll
                    JOIN users u ON ll.user_id = u.id
                    WHERE DATE(ll.login_time) >= ? AND DATE(ll.login_time) <= ?";
            
            if (!empty($filters['role_filter'])) {
                $sql .= " AND u.role = ?";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $prev_active = $stmt->fetchColumn();
        }
        
        // Calculate growth rate
        if ($prev_active > 0) {
            $growth_rate = round((($current_active - $prev_active) / $prev_active) * 100, 1);
            return min($growth_rate, 100); // Cap at 100%
        } else {
            return $current_active > 0 ? 100 : 0;
        }
        
    } catch (PDOException $e) {
        error_log("Fallback growth rate calculation error: " . $e->getMessage());
        return 0;
    }
}

function getDashboardStatsFromActivityLog($pdo, $filters) {
    $stats = [];
    
    // Total active users in selected period
    $sql = "SELECT COUNT(DISTINCT ual.user_id) as total_active
            FROM user_activity_log ual
            JOIN users u ON ual.user_id = u.id
            WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
    
    $params = [$filters['date_from'], $filters['date_to']];
    if (!empty($filters['role_filter'])) {
        $sql .= " AND u.role = ?";
        $params[] = $filters['role_filter'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['total_active'] = $stmt->fetchColumn();
    
    // Daily average
    $days = max(1, (strtotime($filters['date_to']) - strtotime($filters['date_from'])) / (24 * 60 * 60));
    $stats['daily_average'] = round($stats['total_active'] / $days, 1);
    
    // Peak active day
    $sql = "SELECT DATE(ual.created_at) as peak_date, COUNT(DISTINCT ual.user_id) as peak_count
            FROM user_activity_log ual
            JOIN users u ON ual.user_id = u.id
            WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
    
    if (!empty($filters['role_filter'])) {
        $sql .= " AND u.role = ?";
    }
    
    $sql .= " GROUP BY DATE(ual.created_at)
              ORDER BY peak_count DESC
              LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $peak = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['peak_date'] = $peak['peak_date'] ?? 'N/A';
    $stats['peak_count'] = $peak['peak_count'] ?? 0;
    
    // Growth rate calculation based on view_type
    $stats['growth_rate'] = calculateGrowthRate($pdo, $filters, $stats['total_active']);
    
    return $stats;
}

function getDashboardStatsFromFallback($pdo, $filters) {
    $stats = [];
    
    // Try comprehensive_audit_trail first
    $sql = "SELECT COUNT(DISTINCT cat.user_id) as total_active
            FROM comprehensive_audit_trail cat
            JOIN users u ON cat.user_id = u.id
            WHERE DATE(cat.timestamp) >= ? AND DATE(cat.timestamp) <= ?";
    
    $params = [$filters['date_from'], $filters['date_to']];
    if (!empty($filters['role_filter'])) {
        $sql .= " AND u.role = ?";
        $params[] = $filters['role_filter'];
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stats['total_active'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Fallback to login_logs
        $sql = "SELECT COUNT(DISTINCT ll.user_id) as total_active
                FROM login_logs ll
                JOIN users u ON ll.user_id = u.id
                WHERE DATE(ll.login_time) >= ? AND DATE(ll.login_time) <= ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stats['total_active'] = $stmt->fetchColumn();
    }
    
    // Calculate other stats
    $days = max(1, (strtotime($filters['date_to']) - strtotime($filters['date_from'])) / (24 * 60 * 60));
    $stats['daily_average'] = round($stats['total_active'] / $days, 1);
    $stats['peak_date'] = 'N/A';
    $stats['peak_count'] = 0;
    $stats['growth_rate'] = calculateGrowthRateFromFallback($pdo, $filters, $stats['total_active']);
    
    return $stats;
}

function getRoleBreakdownData($pdo, $filters) {
    try {
        // Try user_activity_log first
        $data = getRoleBreakdownFromActivityLog($pdo, $filters);
        
        // If no data, try fallback sources
        if (empty($data)) {
            $data = getRoleBreakdownFromFallback($pdo, $filters);
        }
        
        return $data;
    } catch (PDOException $e) {
        error_log("Role breakdown error: " . $e->getMessage());
        return getRoleBreakdownFromFallback($pdo, $filters);
    }
}

function getRoleBreakdownFromActivityLog($pdo, $filters) {
    $sql = "SELECT 
                u.role,
                COUNT(DISTINCT ual.user_id) as active_users
            FROM user_activity_log ual
            JOIN users u ON ual.user_id = u.id
            WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
    
    $params = [$filters['date_from'], $filters['date_to']];
    if (!empty($filters['role_filter'])) {
        $sql .= " AND u.role = ?";
        $params[] = $filters['role_filter'];
    }
    
    $sql .= " GROUP BY u.role ORDER BY active_users DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRoleBreakdownFromFallback($pdo, $filters) {
    // Try comprehensive_audit_trail first
    $sql = "SELECT 
                u.role,
                COUNT(DISTINCT cat.user_id) as active_users
            FROM comprehensive_audit_trail cat
            JOIN users u ON cat.user_id = u.id
            WHERE DATE(cat.timestamp) >= ? AND DATE(cat.timestamp) <= ?";
    
    $params = [$filters['date_from'], $filters['date_to']];
    if (!empty($filters['role_filter'])) {
        $sql .= " AND u.role = ?";
        $params[] = $filters['role_filter'];
    }
    
    $sql .= " GROUP BY u.role ORDER BY active_users DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($data)) {
            return $data;
        }
    } catch (PDOException $e) {
        // Continue to next fallback
    }
    
    // Fallback to login_logs
    $sql = "SELECT 
                u.role,
                COUNT(DISTINCT ll.user_id) as active_users
            FROM login_logs ll
            JOIN users u ON ll.user_id = u.id
            WHERE DATE(ll.login_time) >= ? AND DATE(ll.login_time) <= ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDetailedActivityData($pdo, $filters, $offset, $limit) {
    try {
        // Try user_activity_log first
        $data = getDetailedActivityFromActivityLog($pdo, $filters, $offset, $limit);
        
        // If no data, try fallback sources
        if (empty($data)) {
            $data = getDetailedActivityFromFallback($pdo, $filters, $offset, $limit);
        }
        
        return $data;
    } catch (PDOException $e) {
        error_log("Detailed activity data error: " . $e->getMessage());
        return getDetailedActivityFromFallback($pdo, $filters, $offset, $limit);
    }
}

function getDetailedActivityFromActivityLog($pdo, $filters, $offset, $limit) {
    $whereClause = "WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
    $params = [$filters['date_from'], $filters['date_to']];
    
    if (!empty($filters['role_filter'])) {
        $whereClause .= " AND u.role = ?";
        $params[] = $filters['role_filter'];
    }
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ?)";
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }
    
    $groupBy = '';
    $selectDate = '';
    
    switch ($filters['view_type']) {
        case 'daily':
            $selectDate = "DATE(ual.created_at) as activity_date";
            $groupBy = "GROUP BY DATE(ual.created_at)";
            break;
        case 'weekly':
            // Use Sunday–Saturday week grouping (mode 0)
            $selectDate = "CONCAT(YEAR(ual.created_at), '-W', LPAD(WEEK(ual.created_at, 0), 2, '0')) as activity_date";
            $groupBy = "GROUP BY YEAR(ual.created_at), WEEK(ual.created_at, 0)";
            break;
        case 'monthly':
            $selectDate = "DATE_FORMAT(ual.created_at, '%Y-%m') as activity_date";
            $groupBy = "GROUP BY DATE_FORMAT(ual.created_at, '%Y-%m')";
            break;
        case 'yearly':
            $selectDate = "YEAR(ual.created_at) as activity_date";
            $groupBy = "GROUP BY YEAR(ual.created_at)";
            break;
    }
    
    $sql = "SELECT 
                $selectDate,
                COUNT(DISTINCT ual.user_id) as total_active,
                COUNT(DISTINCT CASE WHEN u.role = 'student' THEN ual.user_id END) as unique_students,
                COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN ual.user_id END) as unique_teachers,
                COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN ual.user_id END) as unique_admins
            FROM user_activity_log ual
            JOIN users u ON ual.user_id = u.id
            $whereClause
            $groupBy
            ORDER BY activity_date DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDetailedActivityFromFallback($pdo, $filters, $offset, $limit) {
    // First try comprehensive_audit_trail
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['role_filter'])) {
            $whereClause .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        if (!empty($filters['search'])) {
            $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        
        // Add date filters for comprehensive_audit_trail (using timestamp column)
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(cat.timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(cat.timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $groupBy = '';
        $selectDate = '';
        
        switch ($filters['view_type']) {
            case 'daily':
                $selectDate = "DATE(cat.timestamp) as activity_date";
                $groupBy = "GROUP BY DATE(cat.timestamp)";
                break;
            case 'weekly':
                // Use Sunday–Saturday week grouping (mode 0)
                $selectDate = "CONCAT(YEAR(cat.timestamp), '-W', LPAD(WEEK(cat.timestamp, 0), 2, '0')) as activity_date";
                $groupBy = "GROUP BY YEAR(cat.timestamp), WEEK(cat.timestamp, 0)";
                break;
            case 'monthly':
                $selectDate = "DATE_FORMAT(cat.timestamp, '%Y-%m') as activity_date";
                $groupBy = "GROUP BY DATE_FORMAT(cat.timestamp, '%Y-%m')";
                break;
            case 'yearly':
                $selectDate = "YEAR(cat.timestamp) as activity_date";
                $groupBy = "GROUP BY YEAR(cat.timestamp)";
                break;
        }
        
        $sql = "SELECT 
                    $selectDate,
                    COUNT(DISTINCT cat.user_id) as total_active,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' THEN cat.user_id END) as unique_students,
                    COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN cat.user_id END) as unique_teachers,
                    COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN cat.user_id END) as unique_admins
                FROM comprehensive_audit_trail cat
                JOIN users u ON cat.user_id = u.id
                $whereClause
                $groupBy
                ORDER BY activity_date DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($data)) {
            return $data;
        }
    } catch (PDOException $e) {
        // Continue to login_logs fallback
    }
    
    // Fallback to login_logs
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['role_filter'])) {
        $whereClause .= " AND u.role = ?";
        $params[] = $filters['role_filter'];
    }
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ?)";
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }
    
    // Add date filters for login_logs (using login_time column)
    if (!empty($filters['date_from'])) {
        $whereClause .= " AND DATE(ll.login_time) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereClause .= " AND DATE(ll.login_time) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $groupBy = '';
    $selectDate = '';
    
    switch ($filters['view_type']) {
        case 'daily':
            $selectDate = "DATE(ll.login_time) as activity_date";
            $groupBy = "GROUP BY DATE(ll.login_time)";
            break;
        case 'weekly':
            // Use Sunday–Saturday week grouping (mode 0)
            $selectDate = "CONCAT(YEAR(ll.login_time), '-W', LPAD(WEEK(ll.login_time, 0), 2, '0')) as activity_date";
            $groupBy = "GROUP BY YEAR(ll.login_time), WEEK(ll.login_time, 0)";
            break;
        case 'monthly':
            $selectDate = "DATE_FORMAT(ll.login_time, '%Y-%m') as activity_date";
            $groupBy = "GROUP BY DATE_FORMAT(ll.login_time, '%Y-%m')";
            break;
        case 'yearly':
            $selectDate = "YEAR(ll.login_time) as activity_date";
            $groupBy = "GROUP BY YEAR(ll.login_time)";
            break;
    }
    
    $sql = "SELECT 
                $selectDate,
                COUNT(DISTINCT ll.user_id) as total_active,
                COUNT(DISTINCT CASE WHEN u.role = 'student' THEN ll.user_id END) as unique_students,
                COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN ll.user_id END) as unique_teachers,
                COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN ll.user_id END) as unique_admins
            FROM login_logs ll
            JOIN users u ON ll.user_id = u.id
            $whereClause
            $groupBy
            ORDER BY activity_date DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalRecords($pdo, $filters) {
    try {
        $whereClause = "WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
        $params = [$filters['date_from'], $filters['date_to']];
        
        if (!empty($filters['role_filter'])) {
            $whereClause .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        $groupBy = '';
        switch ($filters['view_type']) {
            case 'daily':
                $groupBy = "GROUP BY DATE(ual.created_at)";
                break;
            case 'weekly':
                // Use Sunday–Saturday week grouping (mode 0)
                $groupBy = "GROUP BY YEAR(ual.created_at), WEEK(ual.created_at, 0)";
                break;
            case 'monthly':
                $groupBy = "GROUP BY DATE_FORMAT(ual.created_at, '%Y-%m')";
                break;
            case 'yearly':
                $groupBy = "GROUP BY YEAR(ual.created_at)";
                break;
        }
        
        $sql = "SELECT COUNT(*) as total FROM (
                    SELECT 1
                    FROM user_activity_log ual
                    JOIN users u ON ual.user_id = u.id
                    $whereClause
                    $groupBy
                ) as grouped_data";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Total records error: " . $e->getMessage());
        return 0;
    }
}

// Get data for the page
$dashboardStats = getDashboardStats($pdo, $filters);
$activeUsersData = getActiveUsersData($pdo, $filters);
$roleBreakdownData = getRoleBreakdownData($pdo, $filters);
$detailedData = getDetailedActivityData($pdo, $filters, $offset, $limit);
$totalRecords = getTotalRecords($pdo, $filters);
$totalPages = ceil($totalRecords / $limit);

// Log analytics access
$auditLogger = new AuditLogger($pdo);
$auditLogger->logEntry([
    'action_type' => 'READ',
    'action_description' => 'Accessed usage analytics report',
    'resource_type' => 'Dashboard',
    'resource_id' => 'Usage Analytics',
    'resource_name' => 'Usage Analytics Report',
    'outcome' => 'Success',
    'context' => [
        'view_type' => $filters['view_type'],
        'date_from' => $filters['date_from'],
        'date_to' => $filters['date_to'],
        'role_filter' => $filters['role_filter'] ?? 'all',
        'total_active_users' => $dashboardStats['total_active'] ?? 0,
        'total_records' => $totalRecords
    ]
]);

// Get admin info for header
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Get current user's permissions for navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);

// Get all permissions from database for dynamic navigation
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usage Analytics Report - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="css/usage-analytics.css" rel="stylesheet">
    
    <!-- Collapsible Section Styles and Scripts -->
    <style>
        <?php echo getCollapsibleCSS(); ?>
    </style>
    <script src="js/collapsible_section.js"></script>
</head>
<body class="bg-gray-50">
    <?php echo $notificationSystem->renderNotificationAssets(); ?>
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-primary-600 to-primary-800 text-white">
                <span class="text-2xl font-bold">Admin Portal</span>
            </div>
            
            <!-- Admin Profile -->
            <?php require_once __DIR__ . '/includes/sidebar_profile.php'; ?>

            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]">
                <div class="space-y-1">
                    <?php 
                    // Dashboard permissions check
                    $dashboard_permissions = ['dashboard_view_metrics', 'dashboard_view_course_completion', 'dashboard_view_sales_report', 'dashboard_view_user_retention'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $dashboard_permissions)): ?>
                    <a href="admin.php?view=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    <?php 
                    // Course Management permissions check
                    $course_permissions = ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $course_permissions)): ?>
                    <a href="course_management_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>
                    <?php 
                    // User Management permissions check
                    $user_permissions = ['user_add_new', 'user_reset_password', 'user_change_password', 'user_ban_user', 'user_move_to_deleted', 'user_change_role'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $user_permissions)): ?>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus-icon lucide-user-round-plus"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>
                    <!-- Reports Dropdown -->
                    <?php 
                    // Reports permissions check
                    $reports_permissions = [
                        'analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'view_filter_analytics',
                        'analytics_view_role_breakdown', 'analytics_view_activity_data', 
                        'user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 
                        'user_roles_view_details', 'login_activity_view_metrics', 'login_activity_view_report', 'analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data', 'view_filter_analytics'
                    ];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $reports_permissions)): ?>
                    <div x-data="{ open: true }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full bg-primary-50 text-primary-700 font-medium focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg><span class="flex-1 text-left">Reports</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 ml-auto transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="mt-1 ml-4 space-y-1" x-cloak>
                            <?php 
                            // Usage Analytics permissions check
                            $analytics_permissions = ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data', 'view_filter_analytics'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $analytics_permissions)): ?>
                            <a href="usage-analytics.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
                            Usage Analytics
                            </a>
                            <?php endif; ?>
                            <?php 
                            // User Roles Report permissions check
                            $user_roles_permissions = ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $user_roles_permissions)): ?>
                            <a href="user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                            User Roles Breakdown
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Login Activity permissions check
                            $login_activity_permissions = ['login_activity_view_metrics', 'login_activity_view_report', 'login_activity_view', 'login_activity_export_pdf'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $login_activity_permissions)): ?>
                            <a href="login-activity.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>      
                            Login Activity
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Security Warnings permissions check
                            $security_permissions = ['security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $security_permissions)): ?>
                            <a href="security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-alert-icon lucide-shield-alert"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                            Security Warnings
                            </a>
                            <?php endif; ?>

                            <?php 
                            // Audit Trails permissions check
                            $audit_permissions = ['audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $audit_permissions)): ?>
                            <a href="audit-trails.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
                            Audit Trails
                            </a>
                            <?php endif; ?>

                            <?php 
                            // Performance Logs permissions check
                            $performance_permissions = ['performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $performance_permissions)): ?>
                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                            System Performance Logs
                            </a>
                            <?php endif; ?>

                            <?php 
                            $errorlogs_permissions = ['error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $errorlogs_permissions)): ?>
                            <a href="error-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            Error Logs
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Payment History permissions check
                    $payment_permissions = ['payment_view_history', 'payment_export_invoice_pdf', 'payment_export_pdf'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $payment_permissions)): ?>
                    <a href="payment_history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins-icon lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>

                    <?php
                    $content_permission = ['content_manage_announcement', 'content_manage_terms', 'content_manage_privacy'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $content_permission)): ?>
                    <a href="../dashboard/contentmanagement/content_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        Content Management
                    </a>
                    <?php endif; ?>
                    <!-- Settings Menu - Available to all admins -->
                    <a href="admin_settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                <!-- Push logout to bottom -->
                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="flex items-center justify-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out-icon lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="px-6 py-4 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <!-- Mobile Menu Toggle -->
                        <button onclick="toggleMobileSidebar()" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <h1 class="text-2xl font-semibold text-gray-900">Usage Analytics Report</h1>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <?php echo $notificationSystem->renderNotificationBell('System Notifications'); ?>
                        
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="p-6">
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'view_filter_analytics')): ?>
                <!-- Filter Controls -->
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-medium text-gray-900">Filter Analytics Data</h3>
                            <!-- Filter Action Buttons - Near the title -->
                            <div class="flex space-x-2">
                                <button type="submit" form="filterForm" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    Apply Filters
                                </button>
                                <a href="usage-analytics.php" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Clear Filters
                                </a>
                            </div>
                        </div>

                        
                        <!-- Export Buttons - Right corner -->
                        <div class="export-buttons">
                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'analytics_export_pdf')): ?>
                            <button type="button" onclick="exportData('pdf')" class="inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text-icon lucide-file-text"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>                                Export PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <form method="GET" id="filterForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- Date Range -->
                                <div>
                                    <label for="date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label for="date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                
                                <!-- View Type -->
                                <div>
                                    <label for="view_type" class="block text-sm font-medium text-gray-700">View By</label>
                                    <select name="view_type" id="view_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="daily" <?php echo $filters['view_type'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekly" <?php echo $filters['view_type'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo $filters['view_type'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="yearly" <?php echo $filters['view_type'] === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                    </select>
                                </div>
                                
                                <!-- Role Filter -->
                                <div>
                                    <label for="role_filter" class="block text-sm font-medium text-gray-700">User Role</label>
                                    <select name="role_filter" id="role_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">All Roles</option>
                                        <option value="student" <?php echo $filters['role_filter'] === 'student' ? 'selected' : ''; ?>>Students</option>
                                        <option value="teacher" <?php echo $filters['role_filter'] === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                                        <option value="admin" <?php echo $filters['role_filter'] === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Professional Export Filters - Collapsible -->
                            <div class="border-t border-gray-200 pt-4">
                                <?php
                                // Prepare the Export Configuration content
                                $exportConfigContent = '
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Report Purpose -->
                                    <div>
                                        <label for="report_purpose" class="block text-sm font-medium text-gray-700">Report Purpose</label>
                                        <select name="report_purpose" id="report_purpose" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <option value="general" ' . (($filters['report_purpose'] ?? 'general') === 'general' ? 'selected' : '') . '>General Analytics</option>
                                            <option value="monthly_review" ' . (($filters['report_purpose'] ?? '') === 'monthly_review' ? 'selected' : '') . '>Monthly Review</option>
                                            <option value="performance_audit" ' . (($filters['report_purpose'] ?? '') === 'performance_audit' ? 'selected' : '') . '>Performance Audit</option>
                                            <option value="stakeholder_report" ' . (($filters['report_purpose'] ?? '') === 'stakeholder_report' ? 'selected' : '') . '>Stakeholder Report</option>
                                            <option value="compliance_check" ' . (($filters['report_purpose'] ?? '') === 'compliance_check' ? 'selected' : '') . '>Compliance Check</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Confidentiality Level -->
                                    <div>
                                        <label for="confidentiality" class="block text-sm font-medium text-gray-700">Confidentiality Level</label>
                                        <select name="confidentiality" id="confidentiality" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <option value="internal" ' . (($filters['confidentiality'] ?? 'internal') === 'internal' ? 'selected' : '') . '>Internal Use Only</option>
                                            <option value="confidential" ' . (($filters['confidentiality'] ?? '') === 'confidential' ? 'selected' : '') . '>Confidential</option>
                                            <option value="restricted" ' . (($filters['confidentiality'] ?? '') === 'restricted' ? 'selected' : '') . '>Restricted Access</option>
                                            <option value="public" ' . (($filters['confidentiality'] ?? '') === 'public' ? 'selected' : '') . '>Public (Anonymized)</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Admin Information Display -->
                                    <div>
                                        <label for="admin_info_level" class="block text-sm font-medium text-gray-700">Admin Information</label>
                                        <select name="admin_info_level" id="admin_info_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <option value="name_only" ' . (($filters['admin_info_level'] ?? 'name_only') === 'name_only' ? 'selected' : '') . '>Name Only</option>
                                            <option value="name_role" ' . (($filters['admin_info_level'] ?? '') === 'name_role' ? 'selected' : '') . '>Name + Role</option>
                                            <option value="full_details" ' . (($filters['admin_info_level'] ?? '') === 'full_details' ? 'selected' : '') . '>Full Details + Contact</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Export Column Selection -->
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Export Columns</label>
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="date" ' . (in_array('date', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Date</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="total_active" ' . (in_array('total_active', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Total Active</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="students" ' . (in_array('students', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Students</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="teachers" ' . (in_array('teachers', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Teachers</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="admins" ' . (in_array('admins', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Admins</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Summary Metrics Selection -->
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Include Summary Metrics</label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="summary_metrics[]" value="totals" ' . (in_array('totals', $filters['summary_metrics']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Totals</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="summary_metrics[]" value="averages" ' . (in_array('averages', $filters['summary_metrics']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Averages</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="summary_metrics[]" value="growth_rates" ' . (in_array('growth_rates', $filters['summary_metrics']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Growth Rates</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="summary_metrics[]" value="peak_times" ' . (in_array('peak_times', $filters['summary_metrics']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Peak Activity</span>
                                        </label>
                                    </div>
                                </div>';

                                // Generate the collapsible Export Configuration section
                                echo generateCollapsibleSection(
                                    'export-configuration',        // Unique ID
                                    'Export Configuration',        // Title
                                    $exportConfigContent,         // Content
                                    true,                         // Default COLLAPSED (starts closed)
                                    '',                          // Header additional classes
                                    'bg-white p-4 border border-gray-200 rounded-lg mt-2', // Content styling
                                    'chevron'                    // Chevron icon
                                );
                                ?>
                            </div>
                            

                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dashboard Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Active Users -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'analytics_view_metrics')): ?>
                    <div class="analytics-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-check-icon lucide-user-round-check"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="m16 19 2 2 4-4"/></svg>                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Active Users</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900"><?php echo number_format($dashboardStats['total_active']); ?></div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-gray-600">
                                            <?php echo ucfirst($filters['view_type']); ?>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Daily Average -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'analytics_view_metrics')): ?>
                    <div class="analytics-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ferris-wheel-icon lucide-ferris-wheel"><circle cx="12" cy="12" r="2"/><path d="M12 2v4"/><path d="m6.8 15-3.5 2"/><path d="m20.7 7-3.5 2"/><path d="M6.8 9 3.3 7"/><path d="m20.7 17-3.5-2"/><path d="m9 22 3-8 3 8"/><path d="M8 22h8"/><path d="M18 18.7a9 9 0 1 0-12 0"/></svg>                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Daily Average</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900"><?php echo number_format($dashboardStats['daily_average'], 1); ?></div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-blue-600">
                                            users/day
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Peak Active Day -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'analytics_view_metrics')): ?>
                    <div class="analytics-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-monitor-check-icon lucide-monitor-check"><path d="m9 10 2 2 4-4"/><rect width="20" height="14" x="2" y="3" rx="2"/><path d="M12 17v4"/><path d="M8 21h8"/></svg>                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Peak Active Day</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900"><?php echo $dashboardStats['peak_count']; ?></div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                            <?php echo $dashboardStats['peak_date'] !== 'N/A' ? date('M j', strtotime($dashboardStats['peak_date'])) : 'N/A'; ?>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Growth Rate -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'analytics_view_metrics')): ?>
                    <div class="analytics-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-<?php echo $dashboardStats['growth_rate'] >= 0 ? 'green' : 'red'; ?>-100 rounded-full flex items-center justify-center">
                                    <span class="text-<?php echo $dashboardStats['growth_rate'] >= 0 ? 'green' : 'red'; ?>-600">
                                        <?php echo $dashboardStats['growth_rate'] >= 0 ? '📈' : '📉'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Growth Rate</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo $dashboardStats['growth_rate'] >= 0 ? '+' : ''; ?><?php echo $dashboardStats['growth_rate']; ?>%
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-<?php echo $dashboardStats['growth_rate'] >= 0 ? 'green' : 'red'; ?>-600">
                                            vs prev period
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Active Users Trend Chart -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'analytics_view_active_trends')): ?>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
             viewBox="0 0 24 24" fill="none" stroke="currentColor" 
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
             class="lucide lucide-trending-up text-green-600 mr-2">
            <path d="M16 7h6v6"/>
            <path d="m22 7-8.5 8.5-5-5L2 17"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-900">Active Users Trend</h3>
    </div>
    <div class="p-6">
        <div class="chart-container">
            <canvas id="trendChart" width="400" height="200"></canvas>
        </div>
    </div>
</div>
                    <?php endif; ?>

<!-- Role Breakdown Chart -->
<?php if (hasPermission($pdo, $_SESSION['user_id'], 'analytics_view_role_breakdown')): ?>
<div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
             viewBox="0 0 24 24" fill="none" stroke="currentColor" 
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
             class="lucide lucide-users-round text-blue-600 mr-2">
            <path d="M18 21a8 8 0 0 0-16 0"/>
            <circle cx="10" cy="8" r="5"/>
            <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-900">User Role Breakdown</h3>
    </div>
    <div class="p-6">
        <div class="chart-container">
            <canvas id="roleChart" width="400" height="200"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

                </div>

                                    <!-- Data Table -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'analytics_view_activity_data')): ?>
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
    <!-- Left: title + icon -->
    <div class="flex items-center">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             class="lucide lucide-database text-indigo-600 mr-2">
            <ellipse cx="12" cy="5" rx="9" ry="3"/>
            <path d="M3 5V19A9 3 0 0 0 21 19V5"/>
            <path d="M3 12A9 3 0 0 0 21 12"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-900">Detailed Activity Data</h3>
    </div>
</div>

                    
                    <div class="overflow-x-auto max-w-full">
                        <!-- Pagination Controls - Top of Table -->
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-b border-gray-200 sm:px-6">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                                    <?php endif; ?>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                                    <?php endif; ?>
                                </div>
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $totalRecords); ?></span> of <span class="font-medium"><?php echo $totalRecords; ?></span> results
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <?php if ($page > 1): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                    <span class="sr-only">Previous</span>
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php
                                            $start = max(1, $page - 2);
                                            $end = min($totalPages, $page + 2);
                                            
                                            for ($i = $start; $i <= $end; $i++):
                                            ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                                   class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $totalPages): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                    <span class="sr-only">Next</span>
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        
                        <table class="w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Active</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teachers</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admins</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($detailedData)): ?>
                                    <tr>
                                        <td colspan="5" class="px-3 py-12 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <p class="text-lg">No activity data found</p>
                                                <p class="text-sm">Try adjusting your filters or date range</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($detailedData as $index => $record): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> hover:bg-blue-50 transition-colors">
                                            <td class="px-3 py-4 text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($record['activity_date']); ?>
                                            </td>
                                            <td class="px-3 py-4 text-sm text-gray-900">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                                    <?php echo $record['total_active']; ?> users
                                                </span>
                                            </td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo $record['unique_students']; ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <?php echo $record['unique_teachers']; ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    <?php echo $record['unique_admins']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                    <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const activeUsersData = <?php echo json_encode($activeUsersData); ?>;
        const roleBreakdownData = <?php echo json_encode($roleBreakdownData); ?>;
        const viewType = '<?php echo $filters['view_type']; ?>';
        
        // Real-time clock for Philippines timezone
        function updateRealTimeClock() {
            const now = new Date();
            const philippinesTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
            
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: 'Asia/Manila'
            };
            
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: 'Asia/Manila'
            };
            
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            if (timeElement) {
                timeElement.textContent = philippinesTime.toLocaleTimeString('en-US', timeOptions) + ' (PHT)';
            }
            
            if (dateElement) {
                dateElement.textContent = philippinesTime.toLocaleDateString('en-US', dateOptions);
            }
        }

        // Initialize real-time clock
        document.addEventListener('DOMContentLoaded', function() {
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000); // Update every second
        });
    </script>
    <script src="js/usage-analytics.js"></script>
</body>
</html>
