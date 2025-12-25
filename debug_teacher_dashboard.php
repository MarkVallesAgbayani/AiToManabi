<?php
// debug_teacher_dashboard.php
// Access: localhost/AIToManabi_Updated/debug_teacher_dashboard.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
session_start();

// Set the teacher IDs to compare
$teacher1_id = 35; // Maria Rica Ono (incomplete dashboard)
$teacher2_id = 37; // Teacher 2 (complete dashboard)

echo "<h1>Teacher Dashboard Debug Tool</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .section { margin: 30px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #4CAF50; }
    .error { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
</style>";

try {
    // Test database connection
    echo "<div class='section'>";
    echo "<h2>1. Database Connection Test</h2>";
    if ($pdo) {
        echo "<p class='success'>✓ Database connection successful!</p>";
    } else {
        echo "<p class='error'>✗ Database connection failed!</p>";
        die();
    }
    echo "</div>";

    // Teacher Basic Info
    echo "<div class='section'>";
    echo "<h2>2. Teacher Basic Information</h2>";
    $stmt = $pdo->prepare("
        SELECT id, username, email, role, status, created_at 
        FROM users 
        WHERE id IN (?, ?)
    ");
    $stmt->execute([$teacher1_id, $teacher2_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created At</th></tr>";
    foreach ($teachers as $teacher) {
        echo "<tr>";
        echo "<td>{$teacher['id']}</td>";
        echo "<td>{$teacher['username']}</td>";
        echo "<td>{$teacher['email']}</td>";
        echo "<td>{$teacher['role']}</td>";
        echo "<td>{$teacher['status']}</td>";
        echo "<td>{$teacher['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Permissions Comparison
    echo "<div class='section'>";
    echo "<h2>3. Permissions Comparison</h2>";
    $stmt = $pdo->prepare("
        SELECT user_id, COUNT(*) as permission_count
        FROM user_permissions
        WHERE user_id IN (?, ?)
        GROUP BY user_id
    ");
    $stmt->execute([$teacher1_id, $teacher2_id]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Teacher ID</th><th>Permission Count</th></tr>";
    foreach ($permissions as $perm) {
        echo "<tr>";
        echo "<td>Teacher {$perm['user_id']}</td>";
        echo "<td>{$perm['permission_count']}</td>";
        echo "</tr>";
    }
    if (empty($permissions)) {
        echo "<tr><td colspan='2' class='warning'>⚠ No user-specific permissions found (may be using role-based permissions)</td></tr>";
    }
    echo "</table>";
    echo "</div>";

    // User Roles
    echo "<div class='section'>";
    echo "<h2>4. User Roles</h2>";
    $stmt = $pdo->prepare("
        SELECT user_id, role_id
        FROM user_roles
        WHERE user_id IN (?, ?)
    ");
    $stmt->execute([$teacher1_id, $teacher2_id]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>User ID</th><th>Role ID</th></tr>";
    foreach ($roles as $role) {
        echo "<tr>";
        echo "<td>{$role['user_id']}</td>";
        echo "<td>{$role['role_id']}</td>";
        echo "</tr>";
    }
    if (empty($roles)) {
        echo "<tr><td colspan='2' class='warning'>⚠ No roles assigned in user_roles table</td></tr>";
    }
    echo "</table>";
    echo "</div>";

    // Courses Comparison
    echo "<div class='section'>";
    echo "<h2>5. Courses Statistics</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            teacher_id,
            COUNT(*) as total_courses,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_courses,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_courses,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_courses
        FROM courses
        WHERE teacher_id IN (?, ?)
        GROUP BY teacher_id
    ");
    $stmt->execute([$teacher1_id, $teacher2_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Teacher ID</th><th>Total Courses</th><th>Active</th><th>Published</th><th>Draft</th></tr>";
    foreach ($courses as $course) {
        echo "<tr>";
        echo "<td>Teacher {$course['teacher_id']}</td>";
        echo "<td>{$course['total_courses']}</td>";
        echo "<td>{$course['active_courses']}</td>";
        echo "<td>{$course['published_courses']}</td>";
        echo "<td>{$course['draft_courses']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Student Enrollments
    echo "<div class='section'>";
    echo "<h2>6. Student Enrollments</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            c.teacher_id,
            COUNT(DISTINCT e.student_id) as enrolled_students,
            COUNT(DISTINCT e.course_id) as courses_with_students
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        WHERE c.teacher_id IN (?, ?)
        GROUP BY c.teacher_id
    ");
    $stmt->execute([$teacher1_id, $teacher2_id]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Teacher ID</th><th>Enrolled Students</th><th>Courses with Students</th></tr>";
    foreach ($enrollments as $enrollment) {
        echo "<tr>";
        echo "<td>Teacher {$enrollment['teacher_id']}</td>";
        echo "<td>{$enrollment['enrolled_students']}</td>";
        echo "<td>{$enrollment['courses_with_students']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Course Progress / Completion Rate
    echo "<div class='section'>";
    echo "<h2>7. Course Progress & Completion Rate</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            c.teacher_id,
            COUNT(cp.id) as progress_records,
            ROUND(AVG(cp.progress_percentage), 2) as avg_completion_rate,
            MIN(cp.progress_percentage) as min_progress,
            MAX(cp.progress_percentage) as max_progress
        FROM courses c
        LEFT JOIN course_progress cp ON c.id = cp.course_id
        WHERE c.teacher_id IN (?, ?)
        GROUP BY c.teacher_id
    ");
    $stmt->execute([$teacher1_id, $teacher2_id]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Teacher ID</th><th>Progress Records</th><th>Avg Completion %</th><th>Min %</th><th>Max %</th></tr>";
    foreach ($progress as $prog) {
        echo "<tr>";
        echo "<td>Teacher {$prog['teacher_id']}</td>";
        echo "<td>{$prog['progress_records']}</td>";
        echo "<td>{$prog['avg_completion_rate']}%</td>";
        echo "<td>{$prog['min_progress']}%</td>";
        echo "<td>{$prog['max_progress']}%</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Sections/Chapters (potential "modules")
    echo "<div class='section'>";
    echo "<h2>8. Sections per Course (Potential Modules)</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            c.teacher_id,
            COUNT(s.id) as total_sections
        FROM courses c
        LEFT JOIN sections s ON c.id = s.course_id
        WHERE c.teacher_id IN (?, ?)
        GROUP BY c.teacher_id
    ");
    $stmt->execute([$teacher1_id, $teacher2_id]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Teacher ID</th><th>Total Sections</th></tr>";
    foreach ($sections as $section) {
        echo "<tr>";
        echo "<td>Teacher {$section['teacher_id']}</td>";
        echo "<td>{$section['total_sections']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    $stmt = $pdo->prepare("
        SELECT 
            c.teacher_id,
            COUNT(ch.id) as total_chapters
        FROM courses c
        LEFT JOIN chapters ch ON c.id = ch.course_id
        WHERE c.teacher_id IN (?, ?)
        GROUP BY c.teacher_id
    ");
    $stmt->execute([$teacher1_id, $teacher2_id]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Chapters per Course</h3>";
    echo "<table>";
    echo "<tr><th>Teacher ID</th><th>Total Chapters</th></tr>";
    foreach ($chapters as $chapter) {
        echo "<tr>";
        echo "<td>Teacher {$chapter['teacher_id']}</td>";
        echo "<td>{$chapter['total_chapters']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Full Dashboard Data Simulation
    echo "<div class='section'>";
    echo "<h2>9. COMPLETE DASHBOARD DATA (What Should Display)</h2>";
    
    foreach ([$teacher1_id, $teacher2_id] as $tid) {
        echo "<h3>Teacher ID: $tid</h3>";
        
        // Get all dashboard metrics
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM courses WHERE teacher_id = ? AND status = 'active') as active_courses,
                (SELECT COUNT(*) FROM courses WHERE teacher_id = ? AND status = 'published') as published_courses,
                (SELECT COUNT(DISTINCT e.student_id) 
                 FROM enrollments e 
                 JOIN courses c ON e.course_id = c.id 
                 WHERE c.teacher_id = ?) as enrolled_students,
                (SELECT COALESCE(ROUND(AVG(cp.progress_percentage), 1), 0)
                 FROM course_progress cp
                 JOIN courses c ON cp.course_id = c.id
                 WHERE c.teacher_id = ?) as completion_rate
        ");
        $stmt->execute([$tid, $tid, $tid, $tid]);
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Metric</th><th>Value</th><th>Status</th></tr>";
        
        $metrics = [
            'Active Modules/Courses' => $dashboard['active_courses'],
            'Published Modules/Courses' => $dashboard['published_courses'],
            'Enrolled Students' => $dashboard['enrolled_students'],
            'Completion Rate' => $dashboard['completion_rate'] . '%'
        ];
        
        foreach ($metrics as $name => $value) {
            $status = (intval($value) > 0) ? "<span class='success'>✓ Has Data</span>" : "<span class='warning'>⚠ No Data</span>";
            echo "<tr>";
            echo "<td><strong>$name</strong></td>";
            echo "<td>$value</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // Diagnosis Summary
    echo "<div class='section'>";
    echo "<h2>10. DIAGNOSIS & RECOMMENDATIONS</h2>";
    
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM courses WHERE teacher_id = ?) as courses,
            (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = ?) as students,
            (SELECT COUNT(*) FROM course_progress cp JOIN courses c ON cp.course_id = c.id WHERE c.teacher_id = ?) as progress_records
    ");
    $stmt->execute([$teacher1_id, $teacher1_id, $teacher1_id]);
    $diag = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0;'>";
    echo "<h3>For Teacher 1 (Maria Rica Ono - ID: 35):</h3>";
    
    if ($diag['courses'] == 0) {
        echo "<p class='error'>✗ ISSUE: No courses found. Teacher 1 needs courses assigned.</p>";
    } else {
        echo "<p class='success'>✓ Has {$diag['courses']} courses</p>";
    }
    
    if ($diag['students'] == 0) {
        echo "<p class='warning'>⚠ WARNING: No students enrolled. This will cause 'Active Students' card to not display.</p>";
    } else {
        echo "<p class='success'>✓ Has {$diag['students']} enrolled students</p>";
    }
    
    if ($diag['progress_records'] == 0) {
        echo "<p class='warning'>⚠ WARNING: No progress records. This will cause 'Completion Rate' card to not display.</p>";
    } else {
        echo "<p class='success'>✓ Has {$diag['progress_records']} progress records</p>";
    }
    
    echo "</div>";
    
    echo "<h3>Recommended Fixes:</h3>";
    echo "<ol>";
    echo "<li><strong>If dashboard cards are missing due to no data:</strong> Your dashboard PHP code needs to handle NULL/zero values and still display all cards with '0' values.</li>";
    echo "<li><strong>If data exists but cards don't show:</strong> Check your teacher.php file for conditional rendering that hides cards when values are 0 or NULL.</li>";
    echo "<li><strong>Check JavaScript errors:</strong> Open browser console (F12) on Teacher 1's dashboard and look for errors.</li>";
    echo "<li><strong>Check API endpoints:</strong> Verify all API calls in Network tab are returning data successfully.</li>";
    echo "</ol>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h2>Database Error</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Debug completed at: " . date('Y-m-d H:i:s') . "</strong></p>";
?>
