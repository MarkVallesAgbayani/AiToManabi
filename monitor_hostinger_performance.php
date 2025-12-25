<?php
/**
 * Hostinger Performance Monitor
 * Access via: yourdomain.com/monitor_hostinger_performance.php
 * Password protect this file!
 */

// Simple password protection (add to .htaccess or use Hostinger's file protection)
$admin_password = 'your_secure_password_here';
if (!isset($_GET['password']) || $_GET['password'] !== $admin_password) {
    die('Access denied');
}

require_once 'config/database.php';
require_once 'config/hostinger_database_optimization.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hostinger Performance Monitor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .metric { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .good { border-left: 5px solid #28a745; }
        .warning { border-left: 5px solid #ffc107; }
        .danger { border-left: 5px solid #dc3545; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🏠 Hostinger Performance Monitor</h1>
    <p>Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <?php
    try {
        // Database Connection Health
        echo '<div class="metric good">';
        echo '<h3>🔗 Database Connection</h3>';
        $health = checkHostingerConnectionHealth($pdo);
        echo $health ? '✅ Healthy' : '❌ Issues detected';
        echo '</div>';
        
        // Connection Count
        echo '<div class="metric">';
        echo '<h3>📊 Database Connections</h3>';
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $connections = $stmt->fetch();
        $connectionCount = $connections['Value'];
        
        $connectionClass = 'good';
        if ($connectionCount > 50) $connectionClass = 'warning';
        if ($connectionCount > 100) $connectionClass = 'danger';
        
        echo "<div class='{$connectionClass}'>";
        echo "Current connections: <strong>{$connectionCount}</strong>";
        echo '</div>';
        echo '</div>';
        
        // User Count
        echo '<div class="metric">';
        echo '<h3>👥 User Statistics</h3>';
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
        $totalUsers = $stmt->fetch()['total_users'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as students FROM users WHERE role = 'student'");
        $totalStudents = $stmt->fetch()['students'];
        
        echo "Total users: <strong>{$totalUsers}</strong><br>";
        echo "Students: <strong>{$totalStudents}</strong>";
        echo '</div>';
        
        // OTP Statistics
        echo '<div class="metric">';
        echo '<h3>📧 OTP System</h3>';
        $stmt = $pdo->query("SELECT COUNT(*) as total_otps FROM otps");
        $totalOtps = $stmt->fetch()['total_otps'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as expired_otps FROM otps WHERE expires_at < NOW()");
        $expiredOtps = $stmt->fetch()['expired_otps'];
        
        $otpClass = 'good';
        if ($expiredOtps > 100) $otpClass = 'warning';
        if ($expiredOtps > 500) $otpClass = 'danger';
        
        echo "<div class='{$otpClass}'>";
        echo "Total OTPs: <strong>{$totalOtps}</strong><br>";
        echo "Expired OTPs: <strong>{$expiredOtps}</strong>";
        echo '</div>';
        echo '</div>';
        
        // Quiz Performance
        echo '<div class="metric">';
        echo '<h3>📝 Quiz System</h3>';
        $stmt = $pdo->query("SELECT COUNT(*) as total_quizzes FROM quizzes");
        $totalQuizzes = $stmt->fetch()['total_quizzes'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total_attempts FROM quiz_attempts");
        $totalAttempts = $stmt->fetch()['total_attempts'];
        
        echo "Total quizzes: <strong>{$totalQuizzes}</strong><br>";
        echo "Total attempts: <strong>{$totalAttempts}</strong>";
        echo '</div>';
        
        // Recent Activity
        echo '<div class="metric">';
        echo '<h3>📈 Recent Activity (Last 24 hours)</h3>';
        $stmt = $pdo->query("
            SELECT COUNT(*) as recent_users 
            FROM users 
            WHERE created_at >= NOW() - INTERVAL 1 DAY
        ");
        $recentUsers = $stmt->fetch()['recent_users'];
        
        $stmt = $pdo->query("
            SELECT COUNT(*) as recent_attempts 
            FROM quiz_attempts 
            WHERE completed_at >= NOW() - INTERVAL 1 DAY
        ");
        $recentAttempts = $stmt->fetch()['recent_attempts'];
        
        echo "New users: <strong>{$recentUsers}</strong><br>";
        echo "Quiz attempts: <strong>{$recentAttempts}</strong>";
        echo '</div>';
        
        // Performance Recommendations
        echo '<div class="metric">';
        echo '<h3>💡 Recommendations</h3>';
        
        if ($totalStudents > 200) {
            echo '<div class="warning">⚠️ You have 200+ students. Consider upgrading to Hostinger Premium for better performance.</div>';
        }
        
        if ($expiredOtps > 100) {
            echo '<div class="warning">⚠️ Many expired OTPs found. Run cleanup script.</div>';
        }
        
        if ($connectionCount > 50) {
            echo '<div class="warning">⚠️ High connection count. Monitor database usage.</div>';
        }
        
        if ($totalStudents < 100) {
            echo '<div class="good">✅ System performing well for current user load.</div>';
        }
        
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="metric danger">';
        echo '<h3>❌ Error</h3>';
        echo 'Error: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    ?>
    
    <hr>
    <p><small>🔒 Remember to password-protect this file in production!</small></p>
</body>
</html>
