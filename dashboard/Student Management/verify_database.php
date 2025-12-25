<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../index.php");
    exit();
}

require_once '../../config/database.php';

echo "<h1>🔍 Database Schema & Data Verification</h1>";
echo "<h2>Teacher ID: " . $_SESSION['user_id'] . "</h2>";

// 1. Check actual table schemas
echo "<h3>📋 1. ACTUAL TABLE SCHEMAS</h3>";

echo "<h4>🔸 course_progress table structure:</h4>";
$stmt = $pdo->query("DESCRIBE course_progress");
$columns = $stmt->fetchAll();
echo "<pre>" . print_r($columns, true) . "</pre>";

echo "<h4>🔸 enrollments table structure:</h4>";
$stmt = $pdo->query("DESCRIBE enrollments");
$columns = $stmt->fetchAll();
echo "<pre>" . print_r($columns, true) . "</pre>";

// 2. Check actual data in course_progress
echo "<h3>📊 2. ACTUAL course_progress DATA</h3>";
$stmt = $pdo->prepare("
    SELECT cp.*, c.title as course_title 
    FROM course_progress cp 
    JOIN courses c ON cp.course_id = c.id 
    WHERE c.teacher_id = ?
    ORDER BY cp.updated_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$progress_data = $stmt->fetchAll();
echo "<pre>" . print_r($progress_data, true) . "</pre>";

// 3. Check actual enrollments data
echo "<h3>📊 3. ACTUAL enrollments DATA</h3>";
$stmt = $pdo->prepare("
    SELECT e.*, c.title as course_title 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE c.teacher_id = ?
    ORDER BY e.enrolled_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$enrollments_data = $stmt->fetchAll();
echo "<pre>" . print_r($enrollments_data, true) . "</pre>";

// 4. Check if there's a progress table (section-based progress)
echo "<h3>📊 4. CHECK FOR progress TABLE (section-based)</h3>";
try {
    $stmt = $pdo->query("DESCRIBE progress");
    $progress_columns = $stmt->fetchAll();
    echo "<h4>progress table structure:</h4>";
    echo "<pre>" . print_r($progress_columns, true) . "</pre>";
    
    // Get progress data
    $stmt = $pdo->prepare("
        SELECT p.*, s.title as section_title, c.title as course_title
        FROM progress p
        JOIN sections s ON p.section_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE c.teacher_id = ?
        ORDER BY p.completion_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $section_progress = $stmt->fetchAll();
    echo "<h4>progress table data:</h4>";
    echo "<pre>" . print_r($section_progress, true) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ progress table does not exist: " . $e->getMessage() . "</p>";
}

// 5. Check sections and chapters
echo "<h3>📊 5. SECTIONS & CHAPTERS DATA</h3>";
$stmt = $pdo->prepare("
    SELECT s.*, c.title as course_title, 
           (SELECT COUNT(*) FROM chapters ch WHERE ch.section_id = s.id) as chapter_count
    FROM sections s
    JOIN courses c ON s.course_id = c.id
    WHERE c.teacher_id = ?
    ORDER BY s.course_id, s.order_index
");
$stmt->execute([$_SESSION['user_id']]);
$sections_data = $stmt->fetchAll();
echo "<pre>" . print_r($sections_data, true) . "</pre>";

// 6. Test the actual query used in completion reports
echo "<h3>🧪 6. TEST COMPLETION REPORTS QUERY</h3>";
$date_from = date('Y-m-01', strtotime('-2 months'));
$date_to = date('Y-m-d'); // This should be September 6, 2025

echo "<p><strong>Date Range:</strong> $date_from to $date_to</p>";

$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.id) as total_enrollments,
        COUNT(DISTINCT CASE WHEN cp.completion_status = 'completed' THEN e.id END) as completed_enrollments
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
    WHERE c.teacher_id = ? 
    AND e.enrolled_at BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$completion_test = $stmt->fetch();
echo "<h4>Completion Stats Query Result:</h4>";
echo "<pre>" . print_r($completion_test, true) . "</pre>";

// 6.1. Detailed analysis of completions
echo "<h4>🔍 Detailed Completion Analysis:</h4>";
$stmt = $pdo->prepare("
    SELECT 
        e.id as enrollment_id,
        e.course_id,
        e.student_id,
        e.enrolled_at,
        c.title as course_title,
        cp.completion_status,
        cp.completion_percentage,
        cp.updated_at as progress_updated
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
    WHERE c.teacher_id = ? 
    AND e.enrolled_at BETWEEN ? AND ?
    ORDER BY cp.completion_status DESC, e.enrolled_at DESC
");
$stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$detailed_completions = $stmt->fetchAll();
echo "<pre>" . print_r($detailed_completions, true) . "</pre>";

// 6.2. Count completions by status
echo "<h4>📊 Completion Status Breakdown:</h4>";
$stmt = $pdo->prepare("
    SELECT 
        cp.completion_status,
        COUNT(*) as count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
    WHERE c.teacher_id = ? 
    AND e.enrolled_at BETWEEN ? AND ?
    GROUP BY cp.completion_status
");
$stmt->execute([$_SESSION['user_id'], $date_from, $date_to]);
$status_breakdown = $stmt->fetchAll();
echo "<pre>" . print_r($status_breakdown, true) . "</pre>";

// 7. Check for data inconsistencies
echo "<h3>⚠️ 7. DATA CONSISTENCY CHECK</h3>";

// Check if course_progress has student_id or user_id
$stmt = $pdo->query("SHOW COLUMNS FROM course_progress LIKE '%id'");
$id_columns = $stmt->fetchAll();
echo "<h4>course_progress ID columns:</h4>";
echo "<pre>" . print_r($id_columns, true) . "</pre>";

// Check if enrollments has student_id or user_id
$stmt = $pdo->query("SHOW COLUMNS FROM enrollments LIKE '%id'");
$enrollment_id_columns = $stmt->fetchAll();
echo "<h4>enrollments ID columns:</h4>";
echo "<pre>" . print_r($enrollment_id_columns, true) . "</pre>";

// 8. Check for completion_status vs status column
echo "<h3>🔍 8. STATUS COLUMN CHECK</h3>";
$stmt = $pdo->query("SHOW COLUMNS FROM course_progress LIKE '%status%'");
$status_columns = $stmt->fetchAll();
echo "<h4>course_progress status columns:</h4>";
echo "<pre>" . print_r($status_columns, true) . "</pre>";

// 9. Check for completion_percentage vs progress_percentage
echo "<h3>🔍 9. PERCENTAGE COLUMN CHECK</h3>";
$stmt = $pdo->query("SHOW COLUMNS FROM course_progress LIKE '%percentage%'");
$percentage_columns = $stmt->fetchAll();
echo "<h4>course_progress percentage columns:</h4>";
echo "<pre>" . print_r($percentage_columns, true) . "</pre>";

echo "<hr>";
echo "<h2>🎯 SUMMARY</h2>";
echo "<p>This verification will help identify the exact schema conflicts and data issues.</p>";
?>
