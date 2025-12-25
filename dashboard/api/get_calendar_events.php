<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $studentId = $_SESSION['user_id'];
    $events = [];
    
    // Get all enrolled modules with their current status (completed, in_progress, not_started)
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.title,
            c.description,
            c.created_at as published_date,
            cat.name as category_name,
            u.username as teacher_name,
            e.enrolled_at as enrollment_date,
            -- Get total chapters count for this course
            (SELECT COUNT(ch.id) 
             FROM chapters ch
             INNER JOIN sections s ON ch.section_id = s.id
             WHERE s.course_id = c.id) as total_chapters,
            -- Get completed chapters count (both video and text)
            (SELECT COUNT(DISTINCT ch.id)
             FROM chapters ch
             INNER JOIN sections s ON ch.section_id = s.id
             LEFT JOIN video_progress vp ON ch.id = vp.chapter_id AND vp.student_id = ? AND vp.completed = 1
             LEFT JOIN text_progress tp ON ch.id = tp.chapter_id AND tp.student_id = ? AND tp.completed = 1
             WHERE s.course_id = c.id 
             AND (
                 (ch.content_type = 'video' AND vp.completed = 1) OR 
                 (ch.content_type = 'text' AND tp.completed = 1)
             )) as completed_chapters,
            -- Get the last completed_at from text_progress for this course
            (SELECT tp.completed_at 
             FROM text_progress tp 
             JOIN chapters ch ON tp.chapter_id = ch.id 
             JOIN sections s ON ch.section_id = s.id 
             WHERE s.course_id = c.id AND tp.student_id = ? AND tp.completed = 1
             ORDER BY tp.completed_at DESC 
             LIMIT 1) as completion_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE e.student_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$studentId, $studentId, $studentId, $studentId]);
    $enrolledModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log what we found
    error_log("Calendar API - Found " . count($enrolledModules) . " enrolled modules for student $studentId");
    if (!empty($enrolledModules)) {
        error_log("Calendar API - First enrolled module: " . print_r($enrolledModules[0], true));
    } else {
        error_log("Calendar API - No enrolled modules found. Let's check what's in the database...");
        
        // Debug: Check if student has any enrollments
        $debug_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM enrollments WHERE student_id = ?");
        $debug_stmt->execute([$studentId]);
        $enrollmentCount = $debug_stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Calendar API - Enrollments for student $studentId: " . $enrollmentCount['total']);
    }
    
    // Get recent new modules - using actual database structure
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.title,
            c.description,
            c.created_at as upload_date,
            u.username as teacher_name,
            cat.name as category_name,
            c.price
        FROM courses c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.status = 'published'
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $newModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log what we found
    error_log("Calendar API - Found " . count($newModules) . " new modules");
    if (!empty($newModules)) {
        error_log("Calendar API - First new module: " . print_r($newModules[0], true));
    }
    
    // Process enrolled modules and create events based on their status
    foreach ($enrolledModules as $module) {
        // Calculate completion percentage and determine status
        $totalChapters = (int)$module['total_chapters'];
        $completedChapters = (int)$module['completed_chapters'];
        $completionPercentage = $totalChapters > 0 ? round(($completedChapters / $totalChapters) * 100, 2) : 0;
        
        // Determine status based on percentage
        $status = 'not_started';
        $eventDate = $module['enrollment_date']; // Default to enrollment date
        $eventType = 'enrolled';
        
        if ($completionPercentage >= 100) {
            $status = 'completed';
            $eventType = 'completed';
            $eventDate = $module['completion_date'] ?: $module['enrollment_date'];
        } elseif ($completionPercentage > 0) {
            $status = 'in_progress';
            $eventType = 'in_progress';
            $eventDate = $module['enrollment_date'];
        }
        
        // Set colors based on status
        $colors = [
            'completed' => ['bg' => '#10b981', 'border' => '#059669', 'text' => '#ffffff'],
            'in_progress' => ['bg' => '#3b82f6', 'border' => '#2563eb', 'text' => '#ffffff'],
            'not_started' => ['bg' => '#6b7280', 'border' => '#4b5563', 'text' => '#ffffff']
        ];
        
        $color = $colors[$status];
        
        // Handle timezone properly - convert from UTC to Asia/Manila (Philippine timezone)
        $eventDateTime = new DateTime($eventDate, new DateTimeZone('UTC'));
        $eventDateTime->setTimezone(new DateTimeZone('Asia/Manila'));
        
        // Convert published date to Asia/Manila timezone (Philippine timezone)
        $publishedDate = new DateTime($module['published_date'], new DateTimeZone('UTC'));
        $publishedDate->setTimezone(new DateTimeZone('Asia/Manila'));
        
        // For calendar display, use the original UTC date to avoid timezone shift issues
        $originalEventDate = new DateTime($eventDate, new DateTimeZone('UTC'));
        $originalPublishedDate = new DateTime($module['published_date'], new DateTimeZone('UTC'));
        
        // Determine button text and action based on status
        $buttonText = 'Start Module';
        $buttonAction = 'continue_learning.php?id=' . $module['id'];
        
        if ($status === 'completed') {
            $buttonText = 'Review Module';
        } elseif ($status === 'in_progress') {
            $buttonText = 'Continue Learning';
        }
        
        $events[] = [
            'id' => $eventType . '_' . $module['id'],
            'title' => $module['title'],
            'start' => $originalEventDate->format('Y-m-d'),
            'backgroundColor' => $color['bg'],
            'borderColor' => $color['border'],
            'textColor' => $color['text'],
            'extendedProps' => [
                'type' => $eventType,
                'status' => $status,
                'course_id' => $module['id'],
                'course_title' => $module['title'],
                'category' => $module['category_name'],
                'teacher_name' => $module['teacher_name'],
                'completion_percentage' => $completionPercentage,
                'completed_chapters' => $completedChapters,
                'total_chapters' => $totalChapters,
                'module' => $module['title'],
                'course_description' => $module['description'] ?: 'Learning module',
                'button_text' => $buttonText,
                'button_action' => $buttonAction,
                'is_enrolled' => true,
                'published_date' => $originalPublishedDate->format('Y-m-d H:i:s'),
                'enrollment_date' => $originalEventDate->format('Y-m-d H:i:s')
            ]
        ];
        
        // Debug: Log the event data being created
        error_log("Calendar API - Created event for course {$module['id']}:");
        error_log("  Status: $status");
        error_log("  Progress: $completedChapters/$totalChapters ($completionPercentage%)");
        error_log("  Event date: " . $originalEventDate->format('Y-m-d'));
    }
    
    // Add new modules as events (no icons)
    foreach ($newModules as $module) {
        // Handle timezone properly - convert from UTC to Asia/Manila (Philippine timezone)
        $uploadDate = new DateTime($module['upload_date'], new DateTimeZone('UTC'));
        $uploadDate->setTimezone(new DateTimeZone('Asia/Manila'));
        
        // For calendar display, use the original UTC date to avoid timezone shift issues
        $originalUploadDate = new DateTime($module['upload_date'], new DateTimeZone('UTC'));
        
        // Determine button text and action for new modules (like dashboard.php)
        $buttonText = 'View Module';
        $buttonAction = 'student_courses.php'; // Redirect to student courses page
        
        $events[] = [
            'id' => 'new_' . $module['id'],
            'title' => $module['title'],
            'start' => $originalUploadDate->format('Y-m-d'),
            'backgroundColor' => '#8b5cf6',
            'borderColor' => '#7c3aed',
            'textColor' => '#ffffff',
            'extendedProps' => [
                'type' => 'new_module',
                'course_id' => $module['id'],
                'course_title' => $module['title'],
                'category_name' => $module['category_name'],
                'teacher_name' => $module['teacher_name'],
                'price' => $module['price'],
                'upload_date' => $originalUploadDate->format('Y-m-d H:i:s'),
                'upload_time' => $originalUploadDate->format('H:i:s'),
                'course_description' => $module['description'] ?: 'New module available for enrollment',
                'is_enrolled' => false,
                'button_text' => $buttonText,
                'button_action' => $buttonAction
            ]
        ];
    }
    
    // Debug: Log final events
    error_log("Calendar API - Created " . count($events) . " total events");
    if (!empty($events)) {
        error_log("Calendar API - First event: " . print_r($events[0], true));
    }
    
    echo json_encode($events);
    
} catch (Exception $e) {
    error_log("Calendar events error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load events']);
}
?>
