<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();
require_once 'config/database.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Handle course enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll']) && $is_logged_in) {
    try {
        $pdo->beginTransaction();

        $course_id = (int)$_POST['course_id'];
        
        // Check if already enrolled
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?");
        $stmt->execute([$course_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            // Check if course is free
            $stmt = $pdo->prepare("SELECT price FROM courses WHERE id = ? AND status = 'published'");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();

            if ($course && $course['price'] == 0) {
                // Auto-enroll for free courses
                $stmt = $pdo->prepare("
                    INSERT INTO enrollments (course_id, user_id, status)
                    VALUES (?, ?, 'active')
                ");
                $stmt->execute([$course_id, $_SESSION['user_id']]);

                // Log in audit trail
                $stmt = $pdo->prepare("
                    INSERT INTO audit_trail (course_id, user_id, action, details)
                    VALUES (?, ?, 'Enrolled', 'User enrolled in free course')
                ");
                $stmt->execute([$course_id, $_SESSION['user_id']]);

                $success_message = "You have been successfully enrolled in this course!";
            } else {
                // For paid courses, redirect to payment page (to be implemented)
                header("Location: course_payment.php?course_id=" . $course_id);
                exit();
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Fetch published courses with enrollment status for logged-in users
$query = "
    SELECT c.*, u.username as teacher_name,
    " . ($is_logged_in ? "(SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.user_id = ?) as is_enrolled" : "0 as is_enrolled") . "
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.status = 'published' AND c.is_archived = 0
    ORDER BY c.created_at DESC
";

$stmt = $pdo->prepare($query);
if ($is_logged_in) {
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $stmt->execute();
}
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Courses - Japanese Learning Platform</title>
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
                            <a href="index.php" class="text-2xl font-bold text-red-600">Japanese Learning Platform</a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <?php if ($is_logged_in): ?>
                            <span class="text-gray-700 mr-4">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <a href="auth/logout.php" class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700">Logout</a>
                        <?php else: ?>
                            <a href="login.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Login</a>
                            <a href="register.php" class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Available Courses</h1>

                <?php if (isset($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                            <?php if ($course['image_path']): ?>
                                <img src="uploads/course_images/<?php echo htmlspecialchars($course['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     onerror="this.src='uploads/course_images/default-course.jpg'"
                                     class="w-full h-48 object-cover">
                            <?php else: ?>
                                <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                    <span class="text-gray-500">No image available</span>
                                </div>
                            <?php endif; ?>

                            <div class="p-6">
                                <h2 class="text-xl font-bold text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </h2>
                                <p class="text-gray-600 mb-4">
                                    <?php echo htmlspecialchars(substr(strip_tags($course['description']), 0, 150)) . '...'; ?>
                                </p>
                                <div class="flex justify-between items-center mb-4">
                                    <span class="text-sm text-gray-500">
                                        By <?php echo htmlspecialchars($course['teacher_name']); ?>
                                    </span>
                                    <span class="text-lg font-bold text-red-600">
                                        <?php echo $course['price'] > 0 ? '$' . number_format($course['price'], 2) : 'Free'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($is_logged_in): ?>
                                    <?php if ($course['is_enrolled']): ?>
                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" 
                                           class="block w-full text-center bg-green-600 text-white px-4 py-2 rounded-md font-medium hover:bg-green-700">
                                            Go to Course
                                        </a>
                                    <?php else: ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" name="enroll" 
                                                class="w-full bg-red-600 text-white px-4 py-2 rounded-md font-medium hover:bg-red-700">
                                                <?php echo $course['price'] > 0 ? 'Enroll ($' . number_format($course['price'], 2) . ')' : 'Enroll (Free)'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="login.php" class="block w-full text-center bg-gray-600 text-white px-4 py-2 rounded-md font-medium hover:bg-gray-700">
                                        Login to Enroll
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 