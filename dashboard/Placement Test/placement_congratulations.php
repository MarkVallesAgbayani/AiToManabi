<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/php_errors.log');

require_once '../../config/database.php';
// Get parameters
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$session_token = isset($_GET['session_token']) ? trim($_GET['session_token']) : '';
$preview_mode = isset($_GET['preview']) && ($_GET['preview'] === '1' || $_GET['preview'] === 'true');

if (!$test_id) {
    die('Invalid test ID.');
}

// Check if user is logged in and has permission for preview
$user_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'student';
$is_teacher_preview = $preview_mode && isset($_SESSION['user_id']) && $_SESSION['role'] === 'teacher';

if (!$user_logged_in && !$is_teacher_preview) {
    die('Access denied. Please log in as a student or teacher.');
}

// Get placement result or create preview data
if ($preview_mode) {
    // Create preview data for teachers
    $stmt = $pdo->prepare("SELECT * FROM placement_test WHERE id = ?");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        die('Test not found.');
    }
    
    // Create mock result data for preview
    $result = [
        'test_title' => $test['title'],
        'module_assignments' => json_decode($test['module_assignments'], true) ?? [],
        'difficulty_scores' => [
            'beginner' => ['correct' => 6, 'total' => 7],
            'intermediate' => ['correct' => 2, 'total' => 6],
            'advanced' => ['correct' => 0, 'total' => 7]
        ],
        'percentage_score' => 40.0,
        'recommended_level' => 'intermediate_beginner',
        'detailed_feedback' => 'Preview Mode: Sample results for testing purposes'
    ];
} else {
    // Get real placement result
    $stmt = $pdo->prepare("
        SELECT pr.*, pt.title as test_title, pt.module_assignments
        FROM placement_result pr 
        JOIN placement_test pt ON pr.test_id = pt.id 
        WHERE pr.test_id = ? AND pr.session_token = ?
    ");
    $stmt->execute([$test_id, $session_token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        die('Results not found.');
    }
}

// Decode JSON data (only if not in preview mode)
if (!$preview_mode) {
    $result['answers'] = json_decode($result['answers'], true) ?? [];
    $result['difficulty_scores'] = json_decode($result['difficulty_scores'], true) ?? [];
    $result['module_assignments'] = json_decode($result['module_assignments'], true) ?? [];
} else {
    $result['answers'] = [];
}

// Get scores for each difficulty level
// Get scores for each difficulty level
$beginner_score = $result['difficulty_scores']['beginner']['correct'] ?? 0;
$beginner_total = $result['difficulty_scores']['beginner']['total'] ?? 0;
$intermediate_score = $result['difficulty_scores']['intermediate']['correct'] ?? 0;
$intermediate_total = $result['difficulty_scores']['intermediate']['total'] ?? 0;
$advanced_score = $result['difficulty_scores']['advanced']['correct'] ?? 0;
$advanced_total = $result['difficulty_scores']['advanced']['total'] ?? 0;

// Calculate percentages for each difficulty level
$beginner_percentage = ($beginner_total > 0) ? ($beginner_score / $beginner_total) * 100 : 0;
$intermediate_percentage = ($intermediate_total > 0) ? ($intermediate_score / $intermediate_total) * 100 : 0;
$advanced_percentage = ($advanced_total > 0) ? ($advanced_score / $advanced_total) * 100 : 0;

// Initialize placement variables
$placement_level = 'beginner';
$placement_message = '';
$recommended_module = '';

// Determine placement based on percentage thresholds (3-level system)
if ($beginner_percentage >= 85) {
    // Mastered beginner level - check intermediate performance
    if ($intermediate_percentage >= 70) {
        // Mastered beginner, proficient in intermediate - Advanced Beginner (highest level)
        $placement_level = 'advanced_beginner';
        $placement_message = "You're an Advanced Beginner!";
    } else {
        // Mastered beginner, needs work on intermediate - Intermediate Beginner
        $placement_level = 'intermediate_beginner';
        $placement_message = "You're an Intermediate Beginner!";
    }
} else {
    // Needs to work on beginner level - True Beginner
    $placement_level = 'beginner';
    $placement_message = "You're a Beginner!";
}

// Get recommended modules from the placement test configuration
$assigned_modules = $result['module_assignments'][$placement_level] ?? [];

// Format recommended module display (first and last module)
if (!empty($assigned_modules)) {
    $first_module = $assigned_modules[0];
    $last_module = end($assigned_modules);
    
    if (count($assigned_modules) == 1) {
        $recommended_module = $first_module['title'];
    } else {
        $recommended_module = $first_module['title'] . " - " . $last_module['title'];
    }
} else {
    // Fallback if no modules are assigned
    $recommended_module = "No modules assigned for this level";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Congratulations - Japanese Placement Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .celebration-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .celebration-container.with-preview {
            padding-top: 6rem;
            align-items: flex-start;
        }
        
        .congrats-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            max-width: 900px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin: 1rem auto;
        }
        
        .congrats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #f43f5e, #e11d48, #be123c);
        }
        
        .celebration-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .congrats-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .congrats-subtitle {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
        }
        
        .score-display {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid #e5e7eb;
        }
        
        .score-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .score-item {
            background: white;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .score-level {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .score-level.beginner {
            color: #059669;
        }
        
        .score-level.intermediate {
            color: #d97706;
        }
        
        .score-level.advanced {
            color: #dc2626;
        }
        
        .score-numbers {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .placement-badge {
            display: inline-block;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0.75rem 0;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        
        .placement-badge.beginner {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: white;
        }
        
        .placement-badge.intermediate_beginner {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .placement-badge.advanced_beginner {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        .placement-badge.advanced {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

        .recommendation {
            background: linear-gradient(135deg, #fef2f2, #fce7f3);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid #fecdd3;
        }
        
        .recommendation-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #be123c;
            margin-bottom: 0.75rem;
        }
        
        .recommendation-text {
            font-size: 1rem;
            color: #374151;
            line-height: 1.5;
        }
        
        .next-button {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
            padding: 0.875rem 2.5rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(244, 63, 94, 0.3);
        }
        
        .next-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(244, 63, 94, 0.4);
        }
        
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            overflow: hidden;
        }
        
        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            top: 20%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .floating-element:nth-child(3) {
            bottom: 20%;
            left: 15%;
            animation-delay: 4s;
        }
        
        .floating-element:nth-child(4) {
            bottom: 10%;
            right: 15%;
            animation-delay: 1s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }
        
        @media (max-width: 1200px) {
            .congrats-card {
                max-width: 90vw;
            }
        }
        
        @media (max-width: 768px) {
            .celebration-container {
                padding: 1rem 0.5rem;
            }
            
            .celebration-container.with-preview {
                padding-top: 5rem;
            }
            
            .congrats-card {
                padding: 1.5rem;
                margin: 0.5rem;
                max-width: 95vw;
            }
            
            .congrats-title {
                font-size: 2rem;
            }
            
            .congrats-subtitle {
                font-size: 1.1rem;
            }
            
            .score-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }
            
            .score-item {
                padding: 0.5rem 0.75rem;
                min-height: 50px;
            }
            
            .score-level {
                font-size: 0.7rem;
            }
            
            .score-numbers {
                font-size: 0.9rem;
            }
            
            .placement-badge {
                padding: 0.5rem 1.25rem;
                font-size: 1rem;
            }
            
            .recommendation {
                padding: 1rem;
            }
            
            .recommendation-title {
                font-size: 1.1rem;
            }
            
            .recommendation-text {
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .congrats-card {
                padding: 1rem;
                margin: 0.25rem;
            }
            
            .congrats-title {
                font-size: 1.75rem;
            }
            
            .congrats-subtitle {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php if ($preview_mode): ?>
        <!-- Preview Mode Banner -->
        <div class="preview-banner fixed top-0 left-0 right-0 z-50 text-white p-4">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-eye text-lg"></i>
                        <span class="font-semibold">Preview Mode - Congratulations Page</span>
                    </div>
                    <div class="text-sm opacity-90">
                        You are viewing the congratulations page as a student would see it
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Exit Preview Button -->
                    <a href="../placement_test.php" 
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
            .congratulations-container {
                margin-top: 80px; /* Account for fixed banner */
            }
        </style>
    <?php endif; ?>
    <div class="celebration-container<?php echo $preview_mode ? ' with-preview' : ''; ?>">
        <div class="congrats-card">
            <!-- Floating decorative elements -->
            <div class="floating-elements">
                <div class="floating-element">🎉</div>
                <div class="floating-element">✨</div>
                <div class="floating-element">🌟</div>
                <div class="floating-element">🎊</div>
            </div>
            
            <!-- Main Content -->
            <div class="celebration-icon">🎉</div>
            
            <h1 class="congrats-title">Congratulations!</h1>
            <p class="congrats-subtitle">Great job completing the AiToManabi Placement Test!</p>
            
            <div class="score-display">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Your Test Results</h3>
                <div class="score-grid">
                    <div class="score-item">
                        <div class="score-level beginner">Beginner</div>
                        <div class="score-numbers"><?php echo $beginner_score; ?>/<?php echo $beginner_total; ?></div>
                    </div>
                    <div class="score-item">
                        <div class="score-level intermediate">Intermediate</div>
                        <div class="score-numbers"><?php echo $intermediate_score; ?>/<?php echo $intermediate_total; ?></div>
                    </div>
                    <div class="score-item">
                        <div class="score-level advanced">Advanced</div>
                        <div class="score-numbers"><?php echo $advanced_score; ?>/<?php echo $advanced_total; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="placement-badge <?php echo $placement_level; ?>">
                <?php echo $placement_message; ?>
            </div>
            
            <div class="recommendation">
                <h3 class="recommendation-title">📘 Your Recommended Starting Point</h3>
                <p class="recommendation-text">
                    <?php if ($placement_level === 'beginner'): ?>
                        Perfect if you're just starting out or still getting comfortable with basic greetings, particles, and sentence patterns.
                        <br><strong>Recommended Start: <?php echo $recommended_module; ?> — learn the foundations step-by-step.</strong>
                    <?php elseif ($placement_level === 'intermediate_beginner'): ?>
                        You already have a good grasp of Japanese basics — you can introduce yourself, talk about everyday activities, and understand many common words and phrases. Now it's time to level up with more sentence patterns, verb forms, and ways to express complex ideas.
                        <br><strong>Recommended Starting Point: <?php echo $recommended_module; ?></strong>
                    <?php elseif ($placement_level === 'advanced_beginner'): ?>
                        You've already built a strong foundation in Japanese — you can hold basic conversations, describe events, and use different verb forms with ease. Now it's time to refine your grammar, expand your vocabulary, and get ready for more natural, flowing conversations.
                        <br><strong>Recommended Starting Point: <?php echo $recommended_module; ?></strong>
                    <?php else: ?>
                        Excellent work! You have strong command of Japanese fundamentals and intermediate concepts. You're ready to tackle advanced grammar, keigo (honorific language), and complex reading materials.
                        <br><strong>Recommended Starting Point: <?php echo $recommended_module; ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            
            <a href="placement_module_suggestion.php?test_id=<?php echo $test_id; ?>&session_token=<?php echo $session_token; ?><?php echo $preview_mode ? '&preview=1' : ''; ?>" 
               class="next-button">
                View Your Learning Path →
            </a>
        </div>
    </div>
</body>
</html>
