<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify enrollment
$stmt = $pdo->prepare("
    SELECT e.*, c.title, c.description, c.teacher_id, u.username as teacher_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    WHERE e.course_id = ? AND e.user_id = ?
");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    header("Location: courses.php");
    exit();
}

// Fetch chapters and sections
$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(c.id) as chapter_count
    FROM sections s 
    LEFT JOIN chapters c ON c.section_id = s.id
    WHERE s.course_id = ? 
    GROUP BY s.id
    ORDER BY s.order_index
");
$stmt->execute([$course_id]);
$sections = $stmt->fetchAll();

// Track course progress
if (isset($_POST['mark_complete']) && isset($_POST['section_id'])) {
    try {
        $pdo->beginTransaction();

        $section_id = (int)$_POST['section_id'];
        
        // Mark section as completed
        $stmt = $pdo->prepare("
            INSERT INTO progress (user_id, section_id, completed, completion_date)
            VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE completed = 1, completion_date = NOW()
        ");
        $stmt->execute([$_SESSION['user_id'], $section_id]);

        // Check if all sections are completed
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM sections s 
                 WHERE s.course_id = ?) as total_sections,
                (SELECT COUNT(*) FROM progress p 
                 JOIN sections s ON p.section_id = s.id 
                 WHERE s.course_id = ? AND p.user_id = ? AND p.completed = 1) as completed_sections
        ");
        $stmt->execute([$course_id, $course_id, $_SESSION['user_id']]);
        $progress = $stmt->fetch();

        if ($progress['total_sections'] > 0 && $progress['total_sections'] == $progress['completed_sections']) {
            // Update enrollment status to completed
            $stmt = $pdo->prepare("
                UPDATE enrollments 
                SET status = 'completed', completion_date = NOW()
                WHERE course_id = ? AND user_id = ?
            ");
            $stmt->execute([$course_id, $_SESSION['user_id']]);

            // Log completion in audit trail
            $stmt = $pdo->prepare("
                INSERT INTO audit_trail (course_id, user_id, action, details)
                VALUES (?, ?, 'Completed course', 'User completed all sections')
            ");
            $stmt->execute([$course_id, $_SESSION['user_id']]);
        }

        $pdo->commit();
        header("Location: course_view.php?id=" . $course_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($enrollment['title']); ?> - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="courses.php" class="text-2xl font-bold text-red-600">← Back to Courses</a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-700 mr-4">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="auth/logout.php" class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($enrollment['title']); ?></h1>
                    <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($enrollment['description'])); ?></p>
                    <p class="text-sm text-gray-500">Instructor: <?php echo htmlspecialchars($enrollment['teacher_name']); ?></p>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Course Content -->
                <div class="space-y-6">
                    <?php foreach ($sections as $section): ?>
                        <div class="bg-white shadow rounded-lg p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                                <?php echo htmlspecialchars($section['title']); ?>
                            </h2>
                            
                            <?php if ($section['description']): ?>
                                <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($section['description'])); ?></p>
                            <?php endif; ?>

                            <!-- Show chapters in this section -->
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT c.*, 
                                       COALESCE(p.completed, 0) as is_completed,
                                       p.completion_date
                                FROM chapters c
                                LEFT JOIN progress p ON c.section_id = p.section_id AND p.user_id = ?
                                WHERE c.section_id = ?
                                ORDER BY c.order_index
                            ");
                            $stmt->execute([$_SESSION['user_id'], $section['id']]);
                            $chapters = $stmt->fetchAll();
                            ?>

                            <div class="mt-4 space-y-4">
                                <?php foreach ($chapters as $chapter): ?>
                                    <div class="border-t border-gray-200 pt-4">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h3 class="text-lg font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($chapter['title']); ?>
                                                    <span class="ml-2 text-sm text-gray-500">(<?php echo $chapter['content_type']; ?>)</span>
                                                </h3>
                                                <?php if ($chapter['is_completed']): ?>
                                                    <p class="text-sm text-green-600">
                                                        Completed on <?php echo date('M d, Y', strtotime($chapter['completion_date'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center space-x-4">
                                                <a href="chapter_view.php?id=<?php echo $chapter['id']; ?>" 
                                                   class="text-red-600 hover:text-red-700">View Content</a>
                                                <?php if (!$chapter['is_completed']): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                                        <button type="submit" name="mark_complete"
                                                            class="bg-green-600 text-white px-3 py-1 rounded-md text-sm hover:bg-green-700">
                                                            Mark Complete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 