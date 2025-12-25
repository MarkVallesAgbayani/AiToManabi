<?php
session_start();
require_once '../config/database.php';

// Check if there's an error parameter
$error = $_GET['error'] ?? null;

// Check if payment was successful
if (!isset($_SESSION['payment_success']) || !$_SESSION['payment_success']) {
    if ($error === 'payment_verification_failed') {
        // Get the latest payment attempt for this user
        $stmt = $pdo->prepare("
            SELECT p.*, c.title as course_title 
            FROM payments p 
            JOIN courses c ON p.course_id = c.id 
            WHERE p.user_id = ? 
            ORDER BY p.payment_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $payment = $stmt->fetch();

        if ($payment && $payment['payment_status'] === 'completed') {
            // Payment actually succeeded but session was lost
            $_SESSION['payment_success'] = true;
            $_SESSION['course_id'] = $payment['course_id'];
        } else {
            // Redirect to courses page with error message
            header('Location: student_courses.php?error=payment_failed');
            exit();
        }
    } else {
        // No success flag and no error, redirect to courses
        header('Location: student_courses.php');
        exit();
    }
}

// Clear the success flag
$payment_success = $_SESSION['payment_success'];
$course_id = $_SESSION['course_id'];
unset($_SESSION['payment_success']);
unset($_SESSION['course_id']);

// Get course details
$stmt = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Reggae+One&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .loading-animation {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #FF0000;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .success-checkmark {
            animation: scale-up 0.5s ease-in-out;
        }
        
        @keyframes scale-up {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center font-japanese">
    <div class="max-w-md w-full mx-4">
        <!-- Loading State -->
        <div id="loadingState" class="bg-white p-8 rounded-lg shadow-lg text-center">
            <div class="flex justify-center mb-6">
                <div class="loading-animation"></div>
            </div>
            <h2 class="text-2xl font-medium text-gray-900 mb-2">Preparing Your Learning Journey</h2>
            <p class="text-gray-600">Please wait while we set up your course...</p>
        </div>

        <!-- Success State (Initially Hidden) -->
        <div id="successState" class="bg-white p-8 rounded-lg shadow-lg text-center hidden">
            <div class="flex justify-center mb-6">
                <div class="success-checkmark text-green-500">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>
            <h2 class="text-2xl font-medium text-gray-900 mb-2">Congratulations!</h2>
            <p class="text-gray-600 mb-2">You are now enrolled in:</p>
            <p class="text-xl font-medium text-primary mb-6"><?php echo htmlspecialchars($course['title'] ?? 'your course'); ?></p>
            <p class="text-gray-600 mb-6">Your language learning journey begins here!</p>
            <div class="space-y-3">
                <a href="my_learning.php" class="block w-full bg-primary text-white py-2 px-4 rounded hover:bg-opacity-90 transition-all">
                    Go to My Learning
                </a>
                <a href="view_course.php?id=<?php echo htmlspecialchars($course_id); ?>" class="block w-full bg-gray-100 text-gray-700 py-2 px-4 rounded hover:bg-gray-200 transition-all">
                    View Course
                </a>
            </div>
        </div>
    </div>

    <script>
        // Show success state after 2 seconds
        setTimeout(() => {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('successState').classList.remove('hidden');
        }, 2000);
    </script>
</body>
</html> 