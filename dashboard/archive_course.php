<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';

// Get course ID from URL
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify course belongs to this teacher
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: teacher_courses.php?error=unauthorized");
    exit();
}

try {
    $pdo->beginTransaction();

    // Archive the course
    $stmt = $pdo->prepare("UPDATE courses SET is_archived = 1, status = 'archived' WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);

    // Log in audit trail
    $stmt = $pdo->prepare("
        INSERT INTO audit_trail (course_id, user_id, action, details)
        VALUES (?, ?, 'Archived course', ?)
    ");
    $stmt->execute([
        $course_id,
        $_SESSION['user_id'],
        "Course archived: " . $course['title']
    ]);

    $pdo->commit();
    header("Location: teacher_courses.php?success=archived");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: teacher_courses.php?error=" . urlencode($e->getMessage()));
    exit();
}
?> 