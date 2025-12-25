<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once '../includes/admin_notifications.php';
require_once 'audit_logger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}


// Check if user has permission to view login activity or is admin
if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_view', 'audit_trails_view', 'security_warnings_view']) && $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'pdf' && isset($_GET['type'])) {
    // Check export permission (support both specific export permissions and admin)
    $can_export_any = hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_export_pdf', 'broken_links_export_pdf', 'login_activity_view_report']) || ($_SESSION['role'] ?? '') === 'admin';
    if (!$can_export_any) {
        header('Location: ../index.php');
        exit();
    }
    
    // Extract filters based on export type
    $filters = [];
    if ($_GET['type'] === 'login') {
        $filters = [
            'search' => $_GET['login_search'] ?? '',
            'role_filter' => $_GET['login_role_filter'] ?? '',
            'status_filter' => $_GET['login_status_filter'] ?? '',
            'date_from' => $_GET['login_date_from'] ?? '',
            'date_to' => $_GET['login_date_to'] ?? ''
        ];
    } elseif ($_GET['type'] === 'broken_links') {
        $filters = [
            'severity_filter' => $_GET['broken_severity_filter'] ?? '',
            'broken_status_filter' => $_GET['broken_status_filter'] ?? ''
        ];
    }
    
    // Use centralized report generator
    require_once 'reports.php';
    $reportGenerator = new ReportGenerator($pdo);
    $reportGenerator->generateLoginActivityReport($filters, $_GET['type'], 'pdf');
    exit;
}

// Get filters from URL parameters - separate login and broken links filters
$loginFilters = [
    'search' => $_GET['login_search'] ?? '',
    'role_filter' => $_GET['login_role_filter'] ?? '',
    'status_filter' => $_GET['login_status_filter'] ?? '',
    'date_from' => $_GET['login_date_from'] ?? date('Y-m-d', strtotime('-30 days')),
    'date_to' => $_GET['login_date_to'] ?? date('Y-m-d')
];

$brokenLinksFilters = [
    'severity_filter' => $_GET['broken_severity_filter'] ?? '',
    'broken_status_filter' => $_GET['broken_status_filter'] ?? ''
];

// Keep backward compatibility - merge all filters for legacy code
$filters = array_merge($loginFilters, $brokenLinksFilters);

// Pagination for login activity
$loginPage = isset($_GET['login_page']) ? max(1, intval($_GET['login_page'])) : 1;
$loginLimit = 20;
$loginOffset = ($loginPage - 1) * $loginLimit;

// Pagination for broken links
$brokenPage = isset($_GET['broken_page']) ? max(1, intval($_GET['broken_page'])) : 1;
$brokenLimit = 20;
$brokenOffset = ($brokenPage - 1) * $brokenLimit;

// Login Activity Functions
function getLoginActivity($pdo, $filters, $offset, $limit) {
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ? OR ll.ip_address LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Role filter
        if (!empty($filters['role_filter'])) {
            $whereClause .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        // Status filter
        if (!empty($filters['status_filter'])) {
            $whereClause .= " AND ll.status = ?";
            $params[] = $filters['status_filter'];
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(ll.login_time) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(ll.login_time) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql = "SELECT 
                    ll.id,
                    ll.user_id,
                    u.username,
                    COALESCE(u.first_name, '') as first_name,
                    COALESCE(u.last_name, '') as last_name,
                    u.role,
                    ll.login_time,
                    ll.ip_address,
                    ll.user_agent,
                    ll.status,
                    ll.location,
                    ll.device_type,
                    ll.browser_name,
                    ll.operating_system,
                    ll.session_id
                FROM login_logs ll
                JOIN users u ON ll.user_id = u.id
                $whereClause
                ORDER BY ll.login_time DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Login activity error: " . $e->getMessage());
        return [];
    }
}

function getTotalLoginRecords($pdo, $filters) {
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ? OR ll.ip_address LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['role_filter'])) {
            $whereClause .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        if (!empty($filters['status_filter'])) {
            $whereClause .= " AND ll.status = ?";
            $params[] = $filters['status_filter'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(ll.login_time) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(ll.login_time) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql = "SELECT COUNT(*) FROM login_logs ll JOIN users u ON ll.user_id = u.id $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Total login records error: " . $e->getMessage());
        return 0;
    }
}

// Broken Links Functions
function getBrokenLinks($pdo, $filters, $offset, $limit) {
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Severity filter
        if (!empty($filters['severity_filter'])) {
            $whereClause .= " AND bl.severity = ?";
            $params[] = $filters['severity_filter'];
        }
        
        // Status filter for broken links
        if (!empty($filters['broken_status_filter'])) {
            if ($filters['broken_status_filter'] == 'broken') {
                $whereClause .= " AND bl.status_code >= 400";
            } elseif ($filters['broken_status_filter'] == 'working') {
                $whereClause .= " AND bl.status_code < 400";
            }
        }
        
        $sql = "SELECT 
                    bl.id,
                    bl.url,
                    bl.reference_page,
                    bl.reference_module,
                    bl.first_detected,
                    bl.last_checked,
                    bl.status_code,
                    bl.severity
                FROM broken_links bl
                $whereClause
                ORDER BY bl.last_checked DESC, bl.severity DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Broken links error: " . $e->getMessage());
        return [];
    }
}

function getTotalBrokenLinksRecords($pdo, $filters) {
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['severity_filter'])) {
            $whereClause .= " AND severity = ?";
            $params[] = $filters['severity_filter'];
        }
        
        if (!empty($filters['broken_status_filter'])) {
            if ($filters['broken_status_filter'] == 'broken') {
                $whereClause .= " AND status_code >= 400";
            } elseif ($filters['broken_status_filter'] == 'working') {
                $whereClause .= " AND status_code < 400";
            }
        }
        
        $sql = "SELECT COUNT(*) FROM broken_links $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Total broken links records error: " . $e->getMessage());
        return 0;
    }
}

// Summary Statistics Functions
function getSummaryStats($pdo) {
    try {
        $stats = [];
        
        // Total logins today
        $sql = "SELECT COUNT(*) FROM login_logs WHERE DATE(login_time) = CURDATE() AND status = 'success'";
        $stmt = $pdo->query($sql);
        $stats['logins_today'] = $stmt->fetchColumn();
        
        // Failed login attempts today
        $sql = "SELECT COUNT(*) FROM login_logs WHERE DATE(login_time) = CURDATE() AND status = 'failed'";
        $stmt = $pdo->query($sql);
        $stats['failed_logins_today'] = $stmt->fetchColumn();
        
        // Total broken links - Updated to include status_code 0 (unreachable)
        $sql = "SELECT COUNT(*) FROM broken_links WHERE status_code >= 400 OR status_code = 0";
        $stmt = $pdo->query($sql);
        $stats['total_broken_links'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Summary stats error: " . $e->getMessage());
        return ['logins_today' => 0, 'failed_logins_today' => 0, 'total_broken_links' => 0];
    }
}
// Export Functions


// Create tables if they don't exist
function createTablesIfNotExist($pdo) {
    try {
        // First, check if login_logs table needs to be updated
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'status'");
        if ($stmt->rowCount() == 0) {
            // Add missing columns to existing login_logs table
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN status ENUM('success', 'failed') NOT NULL DEFAULT 'success'");
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'location'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN location VARCHAR(255) NULL");
        }
        
        // Check if login_time column is datetime (need to ensure compatibility)
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs WHERE Field = 'login_time'");
        $loginTimeColumn = $stmt->fetch();
        if ($loginTimeColumn && strpos($loginTimeColumn['Type'], 'datetime') !== false) {
            // Column exists as datetime, which is fine - our queries will work with both
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'created_at'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        
        // Create login_logs table if it doesn't exist (fallback)
        $sql = "CREATE TABLE IF NOT EXISTS login_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            status ENUM('success', 'failed') NOT NULL DEFAULT 'success',
            location VARCHAR(255) NULL,
            device_type VARCHAR(50) NULL,
            browser_name VARCHAR(100) NULL,
            operating_system VARCHAR(100) NULL,
            session_id VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_login_time (login_time),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_ip_address (ip_address)
        )";
        $pdo->exec($sql);
        
        // Add new columns to existing table if they don't exist
        $columns_to_add = [
            'device_type' => 'VARCHAR(50) NULL',
            'browser_name' => 'VARCHAR(100) NULL',
            'operating_system' => 'VARCHAR(100) NULL',
            'session_id' => 'VARCHAR(255) NULL'
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE '$column'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE login_logs ADD COLUMN $column $definition");
            }
        }
        
        // Create broken_links table
        $sql = "CREATE TABLE IF NOT EXISTS broken_links (
            id INT PRIMARY KEY AUTO_INCREMENT,
            url TEXT NOT NULL,
            reference_page VARCHAR(255),
            reference_module VARCHAR(255),
            first_detected TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status_code INT NOT NULL,
            severity ENUM('critical', 'warning') NOT NULL DEFAULT 'warning',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status_code (status_code),
            INDEX idx_severity (severity),
            INDEX idx_last_checked (last_checked)
        )";
        $pdo->exec($sql);
        
        // Tables created successfully
        
    } catch (PDOException $e) {
        error_log("Create tables error: " . $e->getMessage());
    }
}

function insertSampleData($pdo) {
    try {
        // Check if login_logs has data
        $stmt = $pdo->query("SELECT COUNT(*) FROM login_logs");
        $loginCount = $stmt->fetchColumn();
        
        if ($loginCount == 0) {
            // Insert sample login data using actual users
            $stmt = $pdo->query("SELECT id, role, username FROM users ORDER BY id ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($users)) {
                // If no users found, create some basic sample users first
                $sampleUsers = [
                    ['username' => 'admin_sample', 'email' => 'admin@example.com', 'role' => 'admin'],
                    ['username' => 'teacher_sample', 'email' => 'teacher@example.com', 'role' => 'teacher'],
                    ['username' => 'student_sample', 'email' => 'student@example.com', 'role' => 'student']
                ];
                
                foreach ($sampleUsers as $userData) {
                    $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$userData['username'], $userData['email'], password_hash('password123', PASSWORD_DEFAULT), $userData['role']]);
                }
                
                // Re-fetch users
                $stmt = $pdo->query("SELECT id, role, username FROM users ORDER BY id ASC");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            foreach ($users as $user) {
                // Generate more realistic login patterns
                $loginCount = rand(5, 15); // Each user gets 5-15 login records
                
                for ($i = 0; $i < $loginCount; $i++) {
                    // 90% success, 10% failed
                    $status = rand(1, 10) > 1 ? 'success' : 'failed';
                    
                    // Generate login times over the past 30 days
                    $daysAgo = rand(0, 30);
                    $hoursAgo = rand(0, 23);
                    $minutesAgo = rand(0, 59);
                    $loginTime = date('Y-m-d H:i:s', strtotime("-$daysAgo days -$hoursAgo hours -$minutesAgo minutes"));
                    
                    // More realistic IP addresses
                    $ipTypes = [
                        '192.168.' . rand(1, 255) . '.' . rand(1, 254), // Local network
                        '10.0.' . rand(1, 255) . '.' . rand(1, 254), // Private network
                        rand(203, 210) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 254), // Public IP
                        '::1', // IPv6 localhost
                        '2001:db8::' . rand(1, 9999) // IPv6 sample
                    ];
                    $ipAddress = $ipTypes[array_rand($ipTypes)];
                    
                    // Realistic user agents
                    $userAgents = [
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                        'Mozilla/5.0 (Linux; Android 13; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
                        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0'
                    ];
                    $userAgent = $userAgents[array_rand($userAgents)];
                    
                    // Philippine locations
                    $locations = [
                        'Manila, Philippines', 
                        'Quezon City, Philippines', 
                        'Cebu City, Philippines', 
                        'Davao City, Philippines',
                        'Makati, Philippines',
                        'Pasig, Philippines',
                        'Taguig, Philippines',
                        'Iloilo City, Philippines',
                        'Cagayan de Oro, Philippines',
                        'Bacolod, Philippines'
                    ];
                    $location = $locations[array_rand($locations)];
                    
                    $sql = "INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, status, location) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user['id'], $loginTime, $ipAddress, $userAgent, $status, $location]);
                }
            }
        }
        
        // Check if broken_links has data
        $stmt = $pdo->query("SELECT COUNT(*) FROM broken_links");
        $brokenCount = $stmt->fetchColumn();
        
        if ($brokenCount == 0) {
            // Insert sample broken links data
            $sampleLinks = [
                ['https://example.com/missing-image.jpg', 'Course Introduction', 'Japanese Basics', 404, 'warning'],
                ['https://old-cdn.example.com/video.mp4', 'Lesson 5', 'Hiragana Module', 404, 'critical'],
                ['https://api.translate.com/broken-endpoint', 'Translation Tool', 'Grammar Module', 500, 'critical'],
                ['https://fonts.googleapis.com/old-font.css', 'Main Layout', 'Site Theme', 404, 'warning'],
                ['https://example.com/quiz-results.php', 'Quiz Page', 'Assessment Module', 403, 'warning'],
                ['https://cdn.example.com/audio/pronunciation.mp3', 'Pronunciation Guide', 'Speaking Module', 404, 'critical']
            ];
            
            foreach ($sampleLinks as $link) {
                $firstDetected = date('Y-m-d H:i:s', strtotime('-' . rand(1, 15) . ' days'));
                $lastChecked = date('Y-m-d H:i:s', strtotime('-' . rand(0, 2) . ' days'));
                
                $sql = "INSERT INTO broken_links (url, reference_page, reference_module, first_detected, last_checked, status_code, severity) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$link[0], $link[1], $link[2], $firstDetected, $lastChecked, $link[3], $link[4]]);
            }
        }
        
    } catch (PDOException $e) {
        error_log("Insert sample data error: " . $e->getMessage());
    }
}

// Initialize tables and data
createTablesIfNotExist($pdo);
// Note: Sample data generation removed - only real data will be shown

// Get data for the page
$summaryStats = getSummaryStats($pdo);
$loginActivity = getLoginActivity($pdo, $loginFilters, $loginOffset, $loginLimit);
$totalLoginRecords = getTotalLoginRecords($pdo, $loginFilters);
$totalLoginPages = ceil($totalLoginRecords / $loginLimit);

$brokenLinks = getBrokenLinks($pdo, $brokenLinksFilters, $brokenOffset, $brokenLimit);
$totalBrokenRecords = getTotalBrokenLinksRecords($pdo, $brokenLinksFilters);
$totalBrokenPages = ceil($totalBrokenRecords / $brokenLimit);

// Log login activity report access
$auditLogger = new AuditLogger($pdo);
$auditLogger->logEntry([
    'action_type' => 'READ',
    'action_description' => 'Accessed login activity report',
    'resource_type' => 'Dashboard',
    'resource_id' => 'Login Activity Report',
    'resource_name' => 'Login Activity Report',
    'outcome' => 'Success',
    'context' => [
        'login_date_from' => $loginFilters['date_from'] ?? '',
        'login_date_to' => $loginFilters['date_to'] ?? '',
        'login_search' => $loginFilters['search'] ?? '',
        'login_role_filter' => $loginFilters['role_filter'] ?? '',
        'login_status_filter' => $loginFilters['status_filter'] ?? '',
        'broken_severity_filter' => $brokenLinksFilters['severity_filter'] ?? '',
        'broken_status_filter' => $brokenLinksFilters['broken_status_filter'] ?? '',
        'total_login_records' => $totalLoginRecords,
        'total_broken_records' => $totalBrokenRecords,
        'login_page' => $loginPage,
        'broken_page' => $brokenPage
    ]
]);

// Get admin info for header
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Get user permissions for dynamic navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Activity & Broken Links Report - Japanese Learning Platform</title>
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
    <link href="css/login-activity.css" rel="stylesheet">
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
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['dashboard_view_course_completion', 'dashboard_view_metrics', 'dashboard_view_sales_report', 'dashboard_view_user_retention'])): ?>
                    <a href="admin.php?view=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'])): ?>
                    <a href="course_management_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['user_add_new', 'user_reset_password', 'user_change_password', 'user_ban_user', 'user_move_to_deleted', 'user_change_role'])): ?>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus-icon lucide-user-round-plus"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>
                    <!-- Reports Dropdown -->
                    <?php 
                    $reports_permissions = ['nav_reports', 'nav_usage_analytics', 'nav_user_roles_report', 'nav_login_activity', 'nav_security_warnings', 'nav_audit_trails', 'nav_performance_logs', 'nav_error_logs', 'analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data', 'view_filter_analytics', 'user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details', 'login_activity_view', 'login_activity_view_metrics', 'login_activity_view_report', 'login_activity_export_pdf', 'broken_links_view_report', 'broken_links_export_pdf', 'audit_trails_view', 'security_warnings_view', 'performance_logs_view', 'error_logs_view'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $reports_permissions)): ?>
                    <div x-data="{ open: true }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full bg-primary-50 text-primary-700 font-medium focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>                            <span class="flex-1 text-left">Reports</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 ml-auto transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="mt-1 ml-4 space-y-1" x-cloak>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data'])): ?>
                            <a href="usage-analytics.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
      
                            Usage Analytics
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'])): ?>
                            <a href="user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>

                            User Roles Breakdown
                            </a>
                            <?php endif; ?>
                             <?php 
                            // Login Activity permissions check
                            $login_activity_permissions = ['login_activity_view_metrics', 'login_activity_view_report', 'login_activity_view', 'login_activity_export_pdf'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $login_activity_permissions)): ?>        
                            <a href="login-activity.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
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
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $performance_permissions)): ?>                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                            System Performance Logs
                            </a>
                            <?php endif; ?>
                            
                            <?php 
                            // Error Logs permissions check
                            $error_permissions = ['error_logs_view', 'error_logs_export', 'error_view_recent_errors', 'error_view_frequent_errors', 'error_view_severity_levels'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $error_permissions)): ?>
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
                    <!-- Settings Menu - Available to all logged-in users -->
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
                        <h1 class="text-2xl font-semibold text-gray-900">Login Activity & Broken Links Report</h1>
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
                <!-- Summary Cards -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'login_activity_view_metrics')): ?>
                <div class="summary-cards-container">
                    <div class="summary-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock-keyhole-open-icon lucide-lock-keyhole-open"><circle cx="12" cy="16" r="1"/><rect width="18" height="12" x="3" y="10" rx="2"/><path d="M7 10V7a5 5 0 0 1 9.33-2.5"/></svg>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Logins Today</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900" data-metric="logins_today">
                                            <?php echo number_format($summaryStats['logins_today']); ?>
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                            successful
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-banknote-x-icon lucide-banknote-x"><path d="M13 18H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5"/><path d="m17 17 5 5"/><path d="M18 12h.01"/><path d="m22 17-5 5"/><path d="M6 12h.01"/><circle cx="12" cy="12" r="2"/></svg>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Failed Login Attempts</dt>
                                    <dd class="flex items-baseline">
<div class="text-2xl font-semibold text-gray-900" data-metric="failed_logins_today">
    <?php echo number_format($summaryStats['failed_logins_today']); ?>
</div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-red-600">
                                            today
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link-icon lucide-link"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Broken Links Detected</dt>
                                    <dd class="flex items-baseline">
<div class="text-2xl font-semibold text-gray-900" data-metric="total_broken_links">
    <?php echo number_format($summaryStats['total_broken_links']); ?>
</div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-orange-600">
                                            total
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Login Activity Section -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'login_activity_view_report')): ?>
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-8">
                    <!-- Login Activity Filters -->
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <h4 class="text-lg font-medium text-gray-900">🔍 Login Activity Report</h4>
                            <!-- Clear Filters Button -->
                            <div class="flex space-x-2">
                                <a href="?<?php 
                                    // Preserve broken links filters, clear only login filters
                                    $clearParams = [];
                                    if (!empty($_GET['broken_severity_filter'])) $clearParams['broken_severity_filter'] = $_GET['broken_severity_filter'];
                                    if (!empty($_GET['broken_status_filter'])) $clearParams['broken_status_filter'] = $_GET['broken_status_filter'];
                                    if (!empty($_GET['broken_page'])) $clearParams['broken_page'] = $_GET['broken_page'];
                                    echo http_build_query($clearParams);
                                ?>" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Clear Login Filters
                                </a>
                            </div>
                        </div>
                        <!-- Export Buttons - Right corner -->
                        <div class="export-buttons">
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_export_pdf']) || $_SESSION['role'] === 'admin'): ?>
                            <button type="button" onclick="exportData('login', 'pdf')" class="inline-flex items-center px-3 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                               
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text-icon lucide-file-text"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                                Export PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="mb-4 flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Filters are applied automatically as you type or change selections</span>
                            <?php if (!empty(array_filter($loginFilters))): ?>
                            <?php endif; ?>
                        </div>
                        <form method="GET" id="loginFilterForm" class="space-y-4">
                            <!-- Preserve broken links filters when submitting login form -->
                            <?php if (!empty($_GET['broken_severity_filter'])): ?>
                                <input type="hidden" name="broken_severity_filter" value="<?php echo htmlspecialchars($_GET['broken_severity_filter']); ?>">
                            <?php endif; ?>
                            <?php if (!empty($_GET['broken_status_filter'])): ?>
                                <input type="hidden" name="broken_status_filter" value="<?php echo htmlspecialchars($_GET['broken_status_filter']); ?>">
                            <?php endif; ?>
                            <?php if (!empty($_GET['broken_page'])): ?>
                                <input type="hidden" name="broken_page" value="<?php echo htmlspecialchars($_GET['broken_page']); ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                <!-- Search -->
                                <div>
                                    <label for="login_search" class="block text-sm font-medium text-gray-700">Search</label>
                                    <input type="text" name="login_search" id="login_search" value="<?php echo htmlspecialchars($loginFilters['search']); ?>" 
                                           placeholder="Search username, email, name, or IP address..." 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                
                                <!-- Role Filter -->
                                <div>
                                    <label for="login_role_filter" class="block text-sm font-medium text-gray-700">Role</label>
                                    <select name="login_role_filter" id="login_role_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">All Roles</option>
                                        <option value="student" <?php echo $loginFilters['role_filter'] === 'student' ? 'selected' : ''; ?>>Students</option>
                                        <option value="teacher" <?php echo $loginFilters['role_filter'] === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                                        <option value="admin" <?php echo $loginFilters['role_filter'] === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                    </select>
                                </div>
                                
                                <!-- Status Filter -->
                                <div>
                                    <label for="login_status_filter" class="block text-sm font-medium text-gray-700">Status</label>
                                    <select name="login_status_filter" id="login_status_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">All Status</option>
                                        <option value="success" <?php echo $loginFilters['status_filter'] === 'success' ? 'selected' : ''; ?>>Success</option>
                                        <option value="failed" <?php echo $loginFilters['status_filter'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                </div>
                                
                                <!-- Date From -->
                                <div>
                                    <label for="login_date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                                    <input type="date" name="login_date_from" id="login_date_from" value="<?php echo htmlspecialchars($loginFilters['date_from']); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                
                                <!-- Date To -->
                                <div>
                                    <label for="login_date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                                    <input type="date" name="login_date_to" id="login_date_to" value="<?php echo htmlspecialchars($loginFilters['date_to']); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Login Activity Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Login Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device/Browser</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($loginActivity)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                                <p class="text-lg">No login activity found</p>
                                                <p class="text-sm">Try adjusting your filters or date range</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($loginActivity as $index => $activity): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> hover:bg-blue-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #<?php echo $activity['user_id']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars(trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')) ?: $activity['username']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
    <?php 
    echo match($activity['role']) {
        'student' => 'bg-blue-100 text-blue-800',
        'teacher' => 'bg-green-100 text-green-800',
        'admin'   => 'bg-purple-100 text-purple-800',
        default   => 'bg-gray-100 text-gray-800'
    };
    ?>">
    <?php 
    echo match($activity['role']) {
        'student' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M22 10V6L12 3 2 6v4"/>
                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                <path d="M6 12c3 2 9 2 12 0"/>
            </svg> Student',
        'teacher' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 12h6"/>
                <path d="M12 18h6"/>
                <path d="M2 4h6v16H2z"/>
                <path d="M22 4h-6v16h6z"/>
            </svg> Teacher',
        'admin' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <path d="m9 12 2 2 4-4"/>
            </svg> Admin',
        default => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12z"/>
                <path d="M4.8 21.6c0-3.9 3.3-7.2 7.2-7.2s7.2 3.3 7.2 7.2"/>
            </svg> ' . ucfirst($activity['role'])
    };
    ?>
</span>

                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y H:i', strtotime($activity['login_time'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($activity['ip_address']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($activity['location'] ?? 'Unknown'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500" title="<?php echo htmlspecialchars($activity['user_agent']); ?>">
                                                <?php 
                                                $browser = $activity['browser_name'] ?? 'Unknown';
                                                $os = $activity['operating_system'] ?? 'Unknown';
                                                $device = $activity['device_type'] ?? 'Unknown';
                                                
                                                // Browser icons
                                                $browserIcon = match($browser) {
                                                    'Chrome' => '🌐',
                                                    'Firefox' => '🦊',
                                                    'Safari' => '🧭',
                                                    'Edge' => '🌊',
                                                    'Opera' => '🎭',
                                                    default => '🖥️'
                                                };
                                                
                                                // Device icons
                                                $deviceIcon = match($device) {
                                                    'Mobile' => '📱',
                                                    'Tablet' => '📱',
                                                    'Desktop' => '💻',
                                                    default => '🖥️'
                                                };
                                                
                                                echo $browserIcon . ' ' . $browser . ' on ' . $os . ' ' . $deviceIcon;
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php echo $activity['status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $activity['status'] === 'success' ? '✅ Success' : '❌ Failed'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Login Activity Pagination -->
                    <?php if ($totalLoginPages > 1): ?>
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($loginPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['login_page' => $loginPage - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                                <?php endif; ?>
                                <?php if ($loginPage < $totalLoginPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['login_page' => $loginPage + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo $loginOffset + 1; ?></span> to <span class="font-medium"><?php echo min($loginOffset + $loginLimit, $totalLoginRecords); ?></span> of <span class="font-medium"><?php echo $totalLoginRecords; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($loginPage > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['login_page' => $loginPage - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Previous</span>
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start = max(1, $loginPage - 2);
                                        $end = min($totalLoginPages, $loginPage + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['login_page' => $i])); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $loginPage ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($loginPage < $totalLoginPages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['login_page' => $loginPage + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Broken Links Section -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'broken_links_view_report')): ?>
                <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                    
                    <!-- Broken Links Filters -->
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <h4 class="text-lg font-medium text-gray-900">🔗 Broken Links Report</h4>
                            <!-- Clear Filters Button -->
                            <div class="flex space-x-2">
                                <a href="?<?php 
                                    // Preserve login activity filters, clear only broken links filters
                                    $clearParams = [];
                                    if (!empty($_GET['login_search'])) $clearParams['login_search'] = $_GET['login_search'];
                                    if (!empty($_GET['login_role_filter'])) $clearParams['login_role_filter'] = $_GET['login_role_filter'];
                                    if (!empty($_GET['login_status_filter'])) $clearParams['login_status_filter'] = $_GET['login_status_filter'];
                                    if (!empty($_GET['login_date_from'])) $clearParams['login_date_from'] = $_GET['login_date_from'];
                                    if (!empty($_GET['login_date_to'])) $clearParams['login_date_to'] = $_GET['login_date_to'];
                                    if (!empty($_GET['login_page'])) $clearParams['login_page'] = $_GET['login_page'];
                                    echo http_build_query($clearParams);
                                ?>" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Clear Broken Links Filters
                                </a>
                            </div>
                        </div>
                        <!-- Export Buttons - Right corner -->
                        <div class="export-buttons">
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['broken_links_export_pdf']) || $_SESSION['role'] === 'admin'): ?>
                            <button type="button" onclick="exportData('broken_links', 'pdf')" class="inline-flex items-center px-3 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                               
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text-icon lucide-file-text"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                            Export PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="mb-4 flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Filters are applied automatically when you change selections</span>
                            <?php if (!empty(array_filter($brokenLinksFilters))): ?>
                            <?php endif; ?>
                        </div>
                        <form method="GET" id="brokenLinksFilterForm" class="space-y-4">
                            <!-- Preserve login activity filters when submitting broken links form -->
                            <?php if (!empty($_GET['login_search'])): ?>
                                <input type="hidden" name="login_search" value="<?php echo htmlspecialchars($_GET['login_search']); ?>">
                            <?php endif; ?>
                            <?php if (!empty($_GET['login_role_filter'])): ?>
                                <input type="hidden" name="login_role_filter" value="<?php echo htmlspecialchars($_GET['login_role_filter']); ?>">
                            <?php endif; ?>
                            <?php if (!empty($_GET['login_status_filter'])): ?>
                                <input type="hidden" name="login_status_filter" value="<?php echo htmlspecialchars($_GET['login_status_filter']); ?>">
                            <?php endif; ?>
                            <?php if (!empty($_GET['login_date_from'])): ?>
                                <input type="hidden" name="login_date_from" value="<?php echo htmlspecialchars($_GET['login_date_from']); ?>">
                            <?php endif; ?>
                            <?php if (!empty($_GET['login_date_to'])): ?>
                                <input type="hidden" name="login_date_to" value="<?php echo htmlspecialchars($_GET['login_date_to']); ?>">
                            <?php endif; ?>
                            <?php if (!empty($_GET['login_page'])): ?>
                                <input type="hidden" name="login_page" value="<?php echo htmlspecialchars($_GET['login_page']); ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Severity Filter -->
                                <div>
                                    <label for="broken_severity_filter" class="block text-sm font-medium text-gray-700">Severity</label>
                                    <select name="broken_severity_filter" id="broken_severity_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">All Severities</option>
                                        <option value="critical" <?php echo $brokenLinksFilters['severity_filter'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                        <option value="warning" <?php echo $brokenLinksFilters['severity_filter'] === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                    </select>
                                </div>
                                
                                <!-- Broken Status Filter -->
                                <div>
                                    <label for="broken_status_filter" class="block text-sm font-medium text-gray-700">Link Status</label>
                                    <select name="broken_status_filter" id="broken_status_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">All Links</option>
                                        <option value="broken" <?php echo $brokenLinksFilters['broken_status_filter'] === 'broken' ? 'selected' : ''; ?>>Broken Only</option>
                                        <option value="working" <?php echo $brokenLinksFilters['broken_status_filter'] === 'working' ? 'selected' : ''; ?>>Working Only</option>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Broken Links Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link/URL</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference Page</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference Module</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">First Detected</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Checked</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($brokenLinks)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                </svg>
                                                <p class="text-lg">No broken links found</p>
                                                <p class="text-sm">All links appear to be working correctly</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($brokenLinks as $index => $link): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> hover:bg-blue-50 transition-colors">
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($link['url']); ?>">
                                                    <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                        <?php echo htmlspecialchars($link['url']); ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($link['reference_page'] ?? 'Unknown'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($link['reference_module'] ?? 'Unknown'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($link['first_detected'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y H:i', strtotime($link['last_checked'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php 
                                                    if ($link['status_code'] >= 500) {
                                                        echo 'bg-red-100 text-red-800';
                                                    } elseif ($link['status_code'] >= 400) {
                                                        echo 'bg-orange-100 text-orange-800';
                                                    } else {
                                                        echo 'bg-green-100 text-green-800';
                                                    }
                                                    ?>">
                                                    <?php echo $link['status_code']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php echo $link['severity'] === 'critical' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                    <?php echo $link['severity'] === 'critical' ? '🔴 Critical' : '⚠️ Warning'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Broken Links Pagination -->
                    <?php if ($totalBrokenPages > 1): ?>
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($brokenPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['broken_page' => $brokenPage - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                                <?php endif; ?>
                                <?php if ($brokenPage < $totalBrokenPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['broken_page' => $brokenPage + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo $brokenOffset + 1; ?></span> to <span class="font-medium"><?php echo min($brokenOffset + $brokenLimit, $totalBrokenRecords); ?></span> of <span class="font-medium"><?php echo $totalBrokenRecords; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($brokenPage > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['broken_page' => $brokenPage - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Previous</span>
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start = max(1, $brokenPage - 2);
                                        $end = min($totalBrokenPages, $brokenPage + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['broken_page' => $i])); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $brokenPage ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($brokenPage < $totalBrokenPages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['broken_page' => $brokenPage + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const currentLoginFilters = <?php echo json_encode($loginFilters); ?>;
        const currentBrokenLinksFilters = <?php echo json_encode($brokenLinksFilters); ?>;
        
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
    <script src="js/login-activity.js"></script>
</body>
</html>
