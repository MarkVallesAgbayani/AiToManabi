<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';

// Fetch teacher's courses
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM chapters ch JOIN sections s ON ch.section_id = s.id WHERE s.course_id = c.id) as chapter_count,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as student_count
    FROM courses c
    WHERE c.teacher_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Teacher Dashboard</title>
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
                            <a href="teacher.php" class="text-2xl font-bold text-red-600">← Back to Dashboard</a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <a href="teacher_course_editor.php" 
                           class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700">
                            Create New Module
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Modules</h1>

                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        Course updated successfully!
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                            <?php if ($course['image_path']): ?>
                                <img src="../uploads/course_images/<?php echo htmlspecialchars($course['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     onerror="this.src='../uploads/course_images/default-course.jpg'"
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
                                        <?php echo $course['chapter_count']; ?> chapters
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        <?php echo $course['student_count']; ?> students enrolled
                                    </span>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="px-3 py-1 rounded-full text-sm <?php echo $course['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                    <span class="text-lg font-bold text-red-600">
                                        <?php echo $course['price'] > 0 ? '₱' . number_format($course['price'], 2) : 'Free'; ?>
                                    </span>
                                </div>

                                <div class="mt-4 space-x-2 flex justify-end">
                                    <?php if ($course['status'] === 'published'): ?>
                                        <a href="teacher_course_editor.php?id=<?php echo $course['id']; ?>" 
                                           class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">
                                            Edit Course Content
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$course['is_archived']): ?>
                                        <a href="archive_course.php?id=<?php echo $course['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to archive this course? This will hide it from students.');"
                                           class="bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-700">
                                            Archive Course
                                        </a>
                                    <?php endif; ?>
                                    <!-- <a href="delete_course.php?id=<?php echo $course['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this course? This action cannot be undone and will remove all related data including enrollments and progress.');"
                                       class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700">
                                        Delete Course
                                    </a> -->
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 