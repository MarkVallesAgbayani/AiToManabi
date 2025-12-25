<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/php_errors.log');

require_once '../../config/database.php';
require_once '../includes/preview_helpers.php';

// Get test ID from URL
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$preview_mode = isPreviewMode();


// Check if test exists and is published (unless in preview mode)
if ($test_id > 0) {
    $stmt = $pdo->prepare("
        SELECT * FROM placement_test 
        WHERE id = ? AND (is_published = 1 OR ? = 1)
    ");
    $stmt->execute([$test_id, $preview_mode ? 1 : 0]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        die('Placement test not found or not published.');
    }
    
    // Decode JSON data
    $test['questions'] = json_decode($test['questions'], true) ?? [];
    $test['page_content'] = json_decode($test['page_content'], true) ?? [];
    $test['module_assignments'] = json_decode($test['module_assignments'], true) ?? [];
    $test['design_settings'] = json_decode($test['design_settings'], true) ?? [];
    $test['images'] = json_decode($test['images'], true) ?? [];
} else {
    die('Invalid test ID.');
}

// Check if user is logged in (for skip functionality)
$user_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'student';
$user_id = $user_logged_in ? $_SESSION['user_id'] : null;

// For preview mode, check if user is a teacher
$is_teacher_preview = $preview_mode && isset($_SESSION['user_id']) && $_SESSION['role'] === 'teacher';

// Check if student has already taken this test
$has_taken_test = false;
if ($user_logged_in && !$preview_mode) {
    $stmt = $pdo->prepare("SELECT id FROM placement_result WHERE student_id = ? AND test_id = ?");
    $stmt->execute([$user_id, $test_id]);
    $has_taken_test = $stmt->fetch() !== false;
}

// If student has already taken the test and not in preview mode, redirect
if ($has_taken_test && !$preview_mode) {
    header("Location: ../dashboard.php?message=test_already_taken");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($test['title']); ?> - Japanese LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    'brand-red': '#FF0000',
                    'brand-light': '#FF4B4B',
                    'brand-dark': '#CC0000',
                    'dark-bg': '#1F2937',
                    'dark-surface': '#374151',
                    'dark-border': '#4B5563',
                    'dark-text-primary': '#F3F4F6',
                    'dark-text-secondary': '#D1D5DB',
                    'dark-text-muted': '#9CA3AF',
                    'dark-text-link': '#93C5FD'
                },
                fontFamily: {
                    'comfortaa': ['"Comfortaa"', 'sans-serif'],
                    'japanese': ['"Noto Sans JP"', 'sans-serif'],
                    'mincho': ['"Sawarabi Mincho"', 'serif'],
                    'rubik': ['"Rubik"', 'sans-serif']
                }
            }
        }
    }
    </script>
    
    <style>
        [x-cloak] { display: none !important; }
        
        body {
            font-family: 'Rubik', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Ensure body has proper stacking context */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            z-index: -2;
        }

        /* Abstract Background Shapes */
        .abstract-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }

        .abstract-shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.4), rgba(16, 185, 129, 0.25));
            animation: float 20s ease-in-out infinite;
            box-shadow: 0 0 60px rgba(34, 197, 94, 0.3);
            border: 4px solid rgba(34, 197, 94, 0.25);
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            top: 5%;
            left: 5%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 250px;
            height: 250px;
            top: 50%;
            right: 10%;
            animation-delay: 5s;
        }

        .shape-3 {
            width: 200px;
            height: 200px;
            top: 20%;
            right: 25%;
            animation-delay: 10s;
        }

        .shape-4 {
            width: 150px;
            height: 150px;
            bottom: 15%;
            left: 15%;
            animation-delay: 15s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.8;
            }
            25% {
                transform: translateY(-20px) rotate(90deg);
                opacity: 1;
            }
            50% {
                transform: translateY(-10px) rotate(180deg);
                opacity: 0.6;
            }
            75% {
                transform: translateY(-30px) rotate(270deg);
                opacity: 0.9;
            }
        }
        
        .test-container {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            background: transparent;
        }
        
        .test-header {
            background: linear-gradient(135deg, #FF0000, #CC0000);
            color: white;
            padding: 1.5rem 2rem;
            text-align: center;
            font-weight: 700;
            font-size: 1.5rem;
            box-shadow: 0 4px 20px rgba(255, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .test-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .test-footer {
            background: linear-gradient(135deg, #FF0000, #CC0000);
            color: white;
            padding: 1rem 2rem;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 -4px 20px rgba(255, 0, 0, 0.3);
        }
        
        .content-area {
            flex: 1;
            border: none;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            position: relative;
            width: 100%;
            backdrop-filter: blur(3px);
        }
        
        
        .page-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .content-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .question-layout {
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
        }
        
        .question-header {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .question-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            text-align: center;
            background: linear-gradient(135deg, #FF0000, #CC0000);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .question-difficulty {
            position: absolute;
            right: 0;
            top: 0;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .difficulty-beginner {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border: 2px solid #22c55e;
        }

        .difficulty-intermediate {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 2px solid #f59e0b;
        }

        .difficulty-advanced {
            background: linear-gradient(135deg, #fecaca, #fca5a5);
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        
        .question-text {
            font-size: 1.375rem;
            color: #374151;
            margin-bottom: 2.5rem;
            line-height: 1.6;
            text-align: center;
        }
        
        .answer-option {
            display: flex;
            align-items: center;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .answer-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 0, 0, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .answer-option:hover {
            border-color: #FF0000;
            background: linear-gradient(135deg, #fef2f2, #fce7f3);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.15);
        }
        
        .answer-option:hover::before {
            left: 100%;
        }
        
        .answer-option.selected {
            border-color: #FF0000;
            background: linear-gradient(135deg, #fef2f2, #fce7f3);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.2);
            transform: translateY(-2px);
        }
        
        .radio-button {
            width: 24px;
            height: 24px;
            border: 3px solid #6b7280;
            border-radius: 50%;
            margin-right: 1.25rem;
            position: relative;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .answer-option:hover .radio-button {
            border-color: #FF0000;
            transform: scale(1.1);
        }
        
        .radio-button.selected {
            border-color: #FF0000;
            background: #FF0000;
            transform: scale(1.1);
        }
        
        .radio-button.selected::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background-color: white;
            border-radius: 50%;
            animation: pulse 0.6s ease-out;
        }

        .choice-letter {
            font-weight: 600;
            color: #374151;
            margin-right: 0.75rem;
            min-width: 1.5rem;
            display: inline-block;
        }
        
        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(0); }
            50% { transform: translate(-50%, -50%) scale(1.2); }
            100% { transform: translate(-50%, -50%) scale(1); }
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #f1f5f9;
        }
        
        .btn {
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-skip {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        
        .btn-skip:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #4b5563, #374151);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.4);
        }
        
        .btn-next {
            background: linear-gradient(135deg, #FF0000, #CC0000);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.3);
        }
        
        .btn-next:hover {
            background: linear-gradient(135deg, #CC0000, #B91C1C);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.4);
        }
        
        .btn-next:disabled {
            background: linear-gradient(135deg, #d1d5db, #9ca3af);
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .progress-bar {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 2.5rem;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .progress-fill {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            height: 100%;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: progressShimmer 2s infinite;
        }
        
        @keyframes progressShimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .image-placeholder {
            width: 100%;
            height: 350px;
            background: linear-gradient(135deg, #87ceeb 0%, #98fb98 100%);
            border: none;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .custom-image-container {
            width: 100%;
            height: 350px;
            border: none;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .custom-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
        }
        
        .custom-image:hover {
            transform: scale(1.05);
        }
        
        .cloud {
            position: absolute;
            background: white;
            border-radius: 50px;
            opacity: 0.8;
            animation: float 6s ease-in-out infinite;
        }
        
        .cloud:before {
            content: '';
            position: absolute;
            background: white;
            border-radius: 50px;
        }
        
        .cloud:after {
            content: '';
            position: absolute;
            background: white;
            border-radius: 50px;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .cloud1 {
            width: 60px;
            height: 25px;
            top: 30px;
            left: 40px;
            animation-delay: 0s;
        }
        
        .cloud1:before {
            width: 35px;
            height: 35px;
            top: -18px;
            left: 12px;
        }
        
        .cloud1:after {
            width: 45px;
            height: 25px;
            top: -12px;
            right: 12px;
        }
        
        .cloud2 {
            width: 50px;
            height: 20px;
            top: 50px;
            right: 50px;
            animation-delay: 2s;
        }
        
        .cloud2:before {
            width: 30px;
            height: 30px;
            top: -15px;
            left: 10px;
        }
        
        .cloud2:after {
            width: 35px;
            height: 20px;
            top: -10px;
            right: 10px;
        }
        
        .hill {
            position: absolute;
            bottom: 0;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 50% 50% 0 0;
            box-shadow: 0 -5px 15px rgba(34, 197, 94, 0.3);
        }
        
        .hill1 {
            width: 220px;
            height: 90px;
            left: -60px;
            animation: hillFloat 8s ease-in-out infinite;
        }
        
        .hill2 {
            width: 170px;
            height: 70px;
            right: -40px;
            animation: hillFloat 8s ease-in-out infinite 2s;
        }
        
        .hill3 {
            width: 120px;
            height: 50px;
            left: 50%;
            transform: translateX(-50%);
            animation: hillFloat 8s ease-in-out infinite 4s;
        }
        
        @keyframes hillFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        
        .modern-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(5px);
        }
        
        .modern-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #FF0000, #FF4B4B, #FF0000);
        }
        
        .text-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .text-section p {
            font-size: 1.25rem;
            color: #374151;
            line-height: 1.7;
        }

        /* Simple content styling - no red colors */
        .tinymce-content {
            font-size: 1.25rem;
            color: #374151 !important;
            line-height: 1.7;
        }

        .tinymce-content * {
            color: #374151 !important;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            color: #9ca3af;
            margin-bottom: 1.5rem;
        }
        
        .empty-state h3 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            font-size: 1.125rem;
            color: #6b7280;
        }
        
        @media (max-width: 1024px) {
            .content-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
                max-width: 100%;
            }
            
            .content-area {
                padding: 1.5rem;
            }
            
            .question-layout {
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .content-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .test-header,
            .test-footer {
                padding: 1rem 1.5rem;
                font-size: 1.25rem;
            }
            
            .question-title {
                font-size: 2rem;
            }
            
            .question-text {
                font-size: 1.125rem;
            }
            
            .btn {
                padding: 0.875rem 2rem;
                font-size: 0.875rem;
            }
            
            .modern-card {
                padding: 2rem;
            }
            
            .text-section h1 {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .content-area {
                padding: 0.75rem;
            }
            
            .test-header,
            .test-footer {
                padding: 0.75rem 1rem;
                font-size: 1rem;
            }
            
            .question-title {
                font-size: 1.75rem;
            }
            
            .text-section h1 {
                font-size: 1.75rem;
            }
            
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.8rem;
            }
            
            .modern-card {
                padding: 1.5rem;
            }
        }
        
        /* Modal Enhancements */
        .modal-backdrop {
            backdrop-filter: blur(4px);
        }
        
        /* Smooth modal animations */
        .modal-enter {
            opacity: 0;
            transform: scale(0.95);
        }
        
        .modal-enter-active {
            opacity: 1;
            transform: scale(1);
            transition: opacity 200ms ease-out, transform 200ms ease-out;
        }
        
        .modal-exit {
            opacity: 1;
            transform: scale(1);
        }
        
        .modal-exit-active {
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 150ms ease-in, transform 150ms ease-in;
        }
    </style>
</head>
<body>
    <!-- Abstract Background Shapes -->
    <div class="abstract-bg">
        <div class="abstract-shape shape-1"></div>
        <div class="abstract-shape shape-2"></div>
        <div class="abstract-shape shape-3"></div>
        <div class="abstract-shape shape-4"></div>
    </div>

    <?php if ($preview_mode): ?>
        <!-- Preview Mode Banner -->
        <div class="preview-banner fixed top-0 left-0 right-0 z-50 text-white p-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-eye text-lg"></i>
                        <span class="font-semibold">Preview Mode</span>
                    </div>
                    <div class="text-sm opacity-90">
                        You are viewing this placement test as a student would see it
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Exit Preview Button -->
                    <a href="../Placement Test/placement_test.php" 
                       class="bg-white text-red-600 px-4 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors duration-200 flex items-center space-x-2">
                        <i class="fas fa-times"></i>
                        <span>Exit Preview</span>
                    </a>
                </div>
            </div>
        </div>
        
        <style>
            .preview-banner {
                background: linear-gradient(135deg, #FF0000, #CC0000);
                box-shadow: 0 4px 20px rgba(255, 0, 0, 0.3);
                backdrop-filter: blur(10px);
            }
            .test-container {
                margin-top: 80px; /* Account for fixed banner */
            }
        </style>
    <?php endif; ?>
    
    <div class="test-container" x-data="placementTest()">
        <!-- Header -->
        <div class="test-header">
            <?php echo htmlspecialchars($test['title']); ?>
        </div>
        
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill" :style="`width: ${progressPercentage}%`"></div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
            <div class="page-content">
                <!-- No Pages Message -->
                <div x-show="allPages.length === 0" class="empty-state">
                    <div class="modern-card max-w-2xl mx-auto">
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3>No Content Available</h3>
                        <p>This placement test doesn't have any pages or questions yet. Please contact your instructor for more information.</p>
                    </div>
                </div>
                
                <!-- Content Page Layout -->
                <div x-show="currentPageType === 'content' && allPages.length > 0" class="content-layout">
                    <div class="image-section">
                        <div x-show="currentPage.image" class="custom-image-container">
                            <img :src="getImageUrl(currentPage.image)" :alt="currentPage.title" class="custom-image" @error="console.log('Image failed to load:', currentPage.image)">
                        </div>
                        <div x-show="!currentPage.image" class="image-placeholder">
                            <div class="cloud cloud1"></div>
                            <div class="cloud cloud2"></div>
                            <div class="hill hill1"></div>
                            <div class="hill hill2"></div>
                            <div class="hill hill3"></div>
                            <div class="text-center text-white py-8 relative z-10">
                                <i class="fas fa-image text-6xl mb-4 opacity-80"></i>
                                <p class="text-lg font-medium">No image uploaded</p>
                                <p class="text-sm opacity-75 mt-2">This content page doesn't have an image</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-section modern-card">
                        <h1 class="text-3xl font-bold text-gray-900 mb-6" x-text="currentPage.title"></h1>
                        <div class="text-lg text-gray-700 leading-relaxed tinymce-content" x-html="currentPage.content"></div>
                    </div>
                </div>
                
                <!-- Question Page Layout -->
                <div x-show="currentPageType === 'question' && allPages.length > 0" class="question-layout">
                    <div class="modern-card">
                        <div class="question-header">
                            <div class="question-title" x-text="`Question ${currentQuestionIndex + 1}`"></div>
                            <div class="question-difficulty" :class="`difficulty-${currentQuestion.difficulty_level}`" x-text="currentQuestion.difficulty_level"></div>
                        </div>
                        <div class="question-text" x-text="currentQuestion.question_text"></div>
                        
                        <div class="answer-options mt-8">
                            <template x-for="(choice, index) in currentQuestion.choices" :key="index">
                                <div class="answer-option" 
                                     :class="{ 'selected': selectedAnswer === index }"
                                     @click="selectAnswer(index)">
                                    <div class="radio-button" :class="{ 'selected': selectedAnswer === index }"></div>
                                    <span class="choice-letter" x-text="String.fromCharCode(65 + index) + '.'"></span>
                                    <span class="text-lg font-medium" x-text="choice.text"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <div class="navigation-buttons" x-show="allPages.length > 0">
                <div>
                    <!-- Skip Button (only on first content page) -->
                    <button x-show="currentPageIndex === 0 && currentPageType === 'content'" 
                            class="btn btn-skip" 
                            @click="skipTest()">
                        Skip
                    </button>
                    
                    <!-- Back Button (not on first page) -->
                    <button x-show="currentPageIndex > 0" 
                            class="btn btn-back" 
                            @click="previousPage()">
                        Back
                    </button>
                </div>
                
                <div>
                    <!-- Next Button -->
                    <button class="btn btn-next" 
                            :disabled="currentPageType === 'question' && selectedAnswer === null"
                            @click="nextPage()">
                        <span x-text="isLastPage ? 'Finish' : 'Next'"></span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="test-footer">
            &copy; <?php echo date('Y'); ?> AiToManabi. All rights reserved.
        </div>
        
        <!-- Modern Skip Confirmation Modal -->
        <div x-show="showSkipModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto"
             style="display: none;">
            <div class="flex items-center justify-center min-h-screen p-4">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 modal-backdrop transition-opacity" 
                     @click="showSkipModal = false"></div>

                <!-- Modal panel -->
                <div class="relative bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-w-lg w-full"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95">
                    
                    <!-- Modal header -->
                    <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-3 sm:px-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-white">
                                    Skip Placement Test?
                                </h3>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal body -->
                    <div class="bg-white dark:bg-dark-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 dark:bg-amber-900 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-300">
                                        Are you sure you want to skip the placement test? 
                                    </p>
                                    <div class="mt-3 p-3 bg-gray-50 dark:bg-dark-border rounded-lg">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                                            What happens if you skip:
                                        </p>
                                        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                            <li class="flex items-center">
                                                <svg class="w-4 h-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                                </svg>
                                                You <span class="font-semibold text-red-600 dark:text-red-400 m-1">cannot retake</span> this test again.
                                            </li>
                                            <li class="flex items-center">
                                                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                You can still access all beginner modules
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal footer -->
                    <div class="bg-gray-50 dark:bg-dark-border px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" 
                                @click="confirmSkip()"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Skip Test
                        </button>
                        <button type="button" 
                                @click="showSkipModal = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-dark-border shadow-sm px-4 py-2 bg-white dark:bg-dark-surface text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-border focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple notification function for user-friendly messages
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transform transition-all duration-300 ${
                type === 'error' ? 'bg-red-500 text-white' : 
                type === 'warning' ? 'bg-yellow-500 text-black' : 
                type === 'success' ? 'bg-green-500 text-white' : 
                'bg-blue-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <span class="flex-1">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-current opacity-70 hover:opacity-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        function placementTest() {
            return {
                // Test data from PHP
                testData: <?php echo json_encode($test); ?>,
                previewMode: <?php echo $preview_mode ? 'true' : 'false'; ?>,
                userLoggedIn: <?php echo $user_logged_in ? 'true' : 'false'; ?>,
                userId: <?php echo $user_id ? $user_id : 'null'; ?>,
                
                // Current state
                currentPageIndex: 0,
                currentPageType: 'content',
                selectedAnswer: null,
                answers: {},
                sessionToken: null,
                showSkipModal: false,
                
                // Computed properties
                get allPages() {
                    const pages = [];
                    
                    // Add content pages from page_content
                    if (this.testData.page_content) {
                        let pageContent = this.testData.page_content;
                        
                        // Handle both array and object formats
                        if (Array.isArray(pageContent)) {
                            pageContent.forEach((page, index) => {
                                if (page && (page.title || page.name)) {
                                    // Find corresponding image from images array (new method)
                                    const pageImage = this.findImageForPage(page.id, this.testData.images);
                                    
                                    // Fallback to image stored in page_content (old method)
                                    const fallbackImage = page.image || page.image_url || null;
                                    
                                    pages.push({
                                        type: 'content',
                                        title: page.title || page.name || `Page ${index + 1}`,
                                        content: page.content || page.message || page.description || '',
                                        image: pageImage || fallbackImage,
                                        order: page.order || index
                                    });
                                }
                            });
                        } else if (typeof pageContent === 'object') {
                            // Handle object format (legacy)
                            Object.keys(pageContent).forEach((key, index) => {
                                const content = pageContent[key];
                                if (content) {
                                    // Find corresponding image from images array (new method)
                                    const pageImage = this.findImageForPage(content.id, this.testData.images);
                                    
                                    // Fallback to image stored in page_content (old method)
                                    const fallbackImage = content.image || content.image_url || null;
                                    
                                    pages.push({
                                        type: 'content',
                                        title: key.charAt(0).toUpperCase() + key.slice(1),
                                        content: typeof content === 'string' ? content : (content.content || content.message || ''),
                                        image: pageImage || fallbackImage,
                                        order: content.order || index
                                    });
                                }
                            });
                        }
                    }
                    
                    // Add question pages
                    if (this.testData.questions && Array.isArray(this.testData.questions)) {
                        this.testData.questions.forEach((question, index) => {
                            if (question && question.question_text) {
                                pages.push({
                                    type: 'question',
                                    question: question,
                                    questionIndex: index,
                                    order: question.order || (1000 + index) // Use high numbers for questions without explicit order
                                });
                            }
                        });
                    }
                    
                    // Sort pages by order if available
                    const sortedPages = pages.sort((a, b) => (a.order || 0) - (b.order || 0));
                    
                    // Debug logging to help troubleshoot ordering
                    console.log('Page ordering debug:');
                    sortedPages.forEach((page, index) => {
                        console.log(`Page ${index + 1}: ${page.type} - "${page.title || page.question?.question_text?.substring(0, 30) || 'Untitled'}" (order: ${page.order})`);
                    });
                    
                    return sortedPages;
                },
                
                get currentPage() {
                    return this.allPages[this.currentPageIndex] || {};
                },
                
                get currentQuestion() {
                    return this.currentPage.question || {};
                },
                
                get currentQuestionIndex() {
                    return this.currentPage.questionIndex || 0;
                },
                
                get isLastPage() {
                    return this.currentPageIndex === this.allPages.length - 1;
                },
                
                get progressPercentage() {
                    return ((this.currentPageIndex + 1) / this.allPages.length) * 100;
                },
                
                // Methods
                init() {
                    this.currentPageType = this.currentPage.type || 'content';
                    this.selectedAnswer = null;
                    
                    // Debug: Log current page data
                    console.log('Current Page:', this.currentPage);
                    console.log('Current Page Image:', this.currentPage.image);
                    console.log('All Pages:', this.allPages);
                    
                    // Generate session token
                    this.sessionToken = this.generateSessionToken();
                    
                    // Start session if user is logged in
                    if (this.userLoggedIn && !this.previewMode) {
                        this.startSession();
                    }
                    
                    // Add keyboard event listener for modal
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape' && this.showSkipModal) {
                            this.showSkipModal = false;
                        }
                    });
                },
                
                generateSessionToken() {
                    // Generate a more unique token to avoid conflicts
                    return 'pt_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2, 4);
                },
                
                
                findImageForPage(pageId, imagesArray) {
                    if (!imagesArray || !Array.isArray(imagesArray)) return null;
                    
                    const imageData = imagesArray.find(img => img.page_id == pageId);
                    return imageData ? imageData.image_path : null;
                },
                
                getImageUrl(imagePath) {
                    if (!imagePath) return '';
                    
                    // If it's already a full URL, return as is
                    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
                        return imagePath;
                    }
                    
                    // If it's a relative path, make it absolute
                    if (imagePath.startsWith('../') || imagePath.startsWith('./')) {
                        return imagePath;
                    }
                    
                    // If it's just a filename, use local uploads folder
                    if (!imagePath.includes('/')) {
                        // Use local uploads folder in Placement Test directory
                        const fullPath = `uploads/${imagePath}`;
                        console.log('Generated image path:', fullPath);
                        return fullPath;
                    }
                    
                    // Return as is for other cases
                    return imagePath;
                },
                
                selectAnswer(index) {
                    this.selectedAnswer = index;
                },
                
                nextPage() {
                    // Save current answer if it's a question
                    if (this.currentPageType === 'question') {
                        this.answers[this.currentQuestionIndex] = this.selectedAnswer;
                    }
                    
                    if (this.isLastPage) {
                        this.finishTest();
                    } else {
                        this.currentPageIndex++;
                        this.currentPageType = this.currentPage.type;
                        this.selectedAnswer = this.answers[this.currentQuestionIndex] || null;
                        
                    }
                },
                
                previousPage() {
                    if (this.currentPageIndex > 0) {
                        this.currentPageIndex--;
                        this.currentPageType = this.currentPage.type;
                        this.selectedAnswer = this.answers[this.currentQuestionIndex] || null;
                        
                    }
                },
                
                skipTest() {
                    this.showSkipModal = true;
                },
                
                confirmSkip() {
                    this.showSkipModal = false;
                    this.finishTest(true);
                },
                
                async startSession() {
                    try {
                        const response = await fetch('api/start_placement_session.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                test_id: this.testData.id,
                                session_token: this.sessionToken,
                                session_type: 'test_attempt'
                            })
                        });
                        
                        const data = await response.json();
                        if (!data.success) {
                            console.error('Failed to start session:', data.message);
                        }
                    } catch (error) {
                        console.error('Error starting session:', error);
                    }
                },
                
                async finishTest(skipped = false) {
                    if (this.previewMode) {
                        // In preview mode, redirect to congratulations page with preview parameters
                        if (skipped) {
                            window.location.href = `placement_congratulations.php?test_id=${this.testData.id}&session_token=${this.sessionToken}&preview=1`;
                        } else {
                            window.location.href = `placement_congratulations.php?test_id=${this.testData.id}&session_token=${this.sessionToken}&preview=1`;
                        }
                        return;
                    }
                    
                    try {
                        // Convert Alpine.js Proxy to plain array
                        let answersArray = [];
                        if (this.answers) {
                            if (Array.isArray(this.answers)) {
                                answersArray = [...this.answers];
                            } else if (typeof this.answers === 'object') {
                                // Convert object/Proxy to array
                                answersArray = Object.values(this.answers);
                            }
                        }
                        
                        console.log('Submitting test with data:', {
                            test_id: this.testData.id,
                            session_token: this.sessionToken,
                            answers: answersArray,
                            skipped: skipped
                        });
                        
                        const response = await fetch('api/submit_placement_test.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                test_id: this.testData.id,
                                session_token: this.sessionToken,
                                answers: answersArray,
                                skipped: skipped
                            })
                        });
                        
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers);
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        
                        // Get response text first to debug
                        const responseText = await response.text();
                        console.log('Raw response text:', responseText);
                        
                        let data;
                        try {
                            data = JSON.parse(responseText);
                            console.log('Parsed response data:', data);
                        } catch (parseError) {
                            console.error('JSON parse error:', parseError);
                            console.error('Response text that failed to parse:', responseText);
                            throw new Error('Invalid JSON response from server');
                        }
                        
                        if (data.success) {
                            // Redirect to results or dashboard
                            if (skipped) {
                                window.location.href = '../dashboard.php?message=test_skipped';
                            } else {
                                window.location.href = `placement_congratulations.php?test_id=${this.testData.id}&session_token=${this.sessionToken}`;
                            }
                        } else {
                            console.error('Error submitting test:', data.message);
                            // Show user-friendly error message instead of alert
                            showNotification('Error submitting test: ' + data.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error submitting test:', error);
                        // Show user-friendly error message instead of alert
                        showNotification('Error submitting test. Please try again. Check console for details.', 'error');
                    }
                }
            }
        }
    </script>
    
    <?php if ($preview_mode): ?>
        <!-- Preview Mode JavaScript Safeguards -->
        <script>
            // Preview Mode JavaScript Safeguards
            (function() {
                console.log("Preview Mode: JavaScript safeguards loaded");
                
                // Override form submissions
                document.addEventListener("submit", function(e) {
                    e.preventDefault();
                    console.log("Preview Mode: Form submissions are disabled. No data will be saved.");
                    // Show user-friendly message instead of alert
                    showNotification("Preview Mode: Form submissions are disabled. No data will be saved.", 'warning');
                    return false;
                });
                
                // Override fetch requests
                const originalFetch = window.fetch;
                window.fetch = function(...args) {
                    console.warn("Preview Mode: AJAX request blocked:", args[0]);
                    return Promise.reject(new Error("Preview Mode: Data saving is disabled"));
                };
                
                // Override XMLHttpRequest
                const originalXHR = window.XMLHttpRequest;
                window.XMLHttpRequest = function() {
                    const xhr = new originalXHR();
                    const originalOpen = xhr.open;
                    xhr.open = function(method, url, ...args) {
                        console.warn("Preview Mode: XHR request blocked:", method, url);
                        throw new Error("Preview Mode: Data saving is disabled");
                    };
                    return xhr;
                };
                
                // Add preview mode indicator to console
                console.log("%cPreview Mode Active", "color: #e11d48; font-weight: bold; font-size: 16px;");
                console.log("All data saving operations are disabled in preview mode.");
            })();
        </script>
    <?php endif; ?>
</body>
</html>
