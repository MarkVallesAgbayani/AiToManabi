<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
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
$placement_title = 'Beginner';
$placement_description = '';
$learning_focus = [];

// Determine placement based on percentage thresholds (3-level system)
if ($beginner_percentage >= 85) {
    // Mastered beginner level - check intermediate performance
    if ($intermediate_percentage >= 70) {
        // Mastered beginner, proficient in intermediate - Advanced Beginner (highest level)
        $placement_level = 'advanced_beginner';
        $placement_title = 'Advanced Beginner';
        $placement_description = "You've already built a strong foundation in Japanese — you can hold basic conversations, describe events, and use different verb forms with ease. Now it's time to refine your grammar, expand your vocabulary, and get ready for more natural, flowing conversations.";
        $learning_focus = [
            "Polite and humble language (keigo basics)",
            "Advanced particle usage and nuanced expressions",
            "Conditional forms (〜ば, 〜たら)",
            "Expressing intentions, plans, and suppositions",
            "Passive and causative forms",
            "〜ように / 〜ために (expressing purpose)",
            "〜ても / 〜のに (contrasts and concessions)",
            "Formal requests and respectful expressions"
        ];
    } else {
        // Mastered beginner, needs work on intermediate - Intermediate Beginner
        $placement_level = 'intermediate_beginner';
        $placement_title = 'Intermediate Beginner';
        $placement_description = "You already have a good grasp of Japanese basics — you can introduce yourself, talk about everyday activities, and understand many common words and phrases. Now it's time to level up with more sentence patterns, verb forms, and ways to express complex ideas.";
        $learning_focus = [
            "Past tense forms of verbs and adjectives",
            "Counters for objects, people, and events",
            "Talking about experiences and preferences",
            "More complex sentence structures",
            "Time expressions and scheduling",
            "Describing locations and directions"
        ];
    }
} else {
    // Needs to work on beginner level - True Beginner
    $placement_level = 'beginner';
    $placement_title = 'Beginner';
    $placement_description = "Perfect if you're just starting out or still getting comfortable with basic greetings, particles, and sentence patterns.";
    $learning_focus = [
        "Basic greetings and introductions",
        "Hiragana and Katakana reading",
        "Essential particles ([translate:は, が, を, に, で])",
        "Basic sentence patterns",
        "Numbers and counting",
        "Time and dates",
        "Family and personal information"
    ];
}

// Get assigned modules for the placement level
$assigned_modules = $result['module_assignments'][$placement_level] ?? [];

// Get module details from database
$module_details = [];
if (!empty($assigned_modules)) {
    $module_ids = array_column($assigned_modules, 'id');
    $placeholders = str_repeat('?,', count($module_ids) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT id, title, description, status, created_at 
        FROM courses 
        WHERE id IN ($placeholders) AND status = 'published'
        ORDER BY created_at ASC
    ");
    $stmt->execute($module_ids);
    $module_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Learning Path - Japanese Placement Test</title>
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
        
        .suggestion-container {
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .suggestion-container.with-preview {
            padding-top: 6rem;
        }
        
        .suggestion-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        
        .suggestion-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #f43f5e, #e11d48, #be123c);
        }
        
        .level-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .level-badge {
            display: inline-block;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        
        .level-badge.beginner {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: white;
        }
        
        .level-badge.intermediate_beginner {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .level-badge.advanced_beginner {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .level-title {
            font-size: 2rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .level-description {
            font-size: 1.1rem;
            color: #374151;
            line-height: 1.5;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .learning-focus {
            background: linear-gradient(135deg, #fef2f2, #fce7f3);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid #fecdd3;
        }
        
        .learning-focus h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #be123c;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .focus-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.75rem;
        }
        
        .focus-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #fecdd3;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .focus-item i {
            color: #f43f5e;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .modules-section {
            margin: 2rem 0;
        }
        
        .modules-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.75rem;
        }
        
        .module-card {
            background: white;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f43f5e, #e11d48);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }
        
        .module-card:hover::before {
            opacity: 1;
        }
        
        .module-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        
        .module-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            font-weight: 700;
        }
        
        .module-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1f2937;
            flex: 1;
        }
        
        .module-description {
            color: #6b7280;
            line-height: 1.4;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
        }
        
        .module-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #9ca3af;
        }
        
        .start-learning-btn {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 1.5rem auto;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(244, 63, 94, 0.3);
            text-align: center;
            width: fit-content;
        }
        
        .start-learning-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(244, 63, 94, 0.4);
        }
        
        .back-btn {
            background: white;
            color: #6b7280;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-right: 1rem;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        
        .back-btn:hover {
            background: #f9fafb;
            transform: translateY(-1px);
        }
        
        .empty-modules {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .empty-modules i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        @media (max-width: 1200px) {
            .suggestion-card {
                max-width: 95vw;
            }
        }
        
        @media (max-width: 768px) {
            .suggestion-container {
                padding: 1rem 0;
            }
            
            .suggestion-container.with-preview {
                padding-top: 5rem;
            }
            
            .suggestion-card {
                padding: 1.5rem;
                margin: 0.5rem;
                max-width: 95vw;
            }
            
            .level-title {
                font-size: 1.75rem;
            }
            
            .level-description {
                font-size: 1rem;
            }
            
            .learning-focus {
                padding: 1rem;
            }
            
            .learning-focus h3 {
                font-size: 1.1rem;
            }
            
            .focus-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .focus-item {
                padding: 0.75rem;
            }
            
            .modules-title {
                font-size: 1.5rem;
            }
            
            .modules-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .module-card {
                padding: 0.875rem 1rem;
                min-height: 60px;
            }
            
            .module-title {
                font-size: 0.95rem;
            }
            
            .module-description {
                font-size: 0.8rem;
            }
            
            .module-icon {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .suggestion-card {
                padding: 1rem;
                margin: 0.25rem;
            }
            
            .level-title {
                font-size: 1.5rem;
            }
            
            .level-description {
                font-size: 0.9rem;
            }
            
            .modules-title {
                font-size: 1.25rem;
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
                        <span class="font-semibold">Preview Mode - Module Suggestions</span>
                    </div>
                    <div class="text-sm opacity-90">
                        You are viewing the module suggestions page as a student would see it
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
            .suggestions-container {
                margin-top: 80px; /* Account for fixed banner */
            }
        </style>
    <?php endif; ?>
    <div class="suggestion-container<?php echo $preview_mode ? ' with-preview' : ''; ?>">
        <div class="suggestion-card">
            <!-- Level Header -->
            <div class="level-header">
                <div class="level-badge <?php echo $placement_level; ?>">
                    <?php echo $placement_title; ?>
                </div>
                <h1 class="level-title">Your Learning Path</h1>
                <p class="level-description"><?php echo $placement_description; ?></p>
            </div>
            
            <!-- Learning Focus -->
            <div class="learning-focus">
                <h3>📚 What You'll Learn</h3>
                <div class="focus-grid">
                    <?php foreach ($learning_focus as $focus): ?>
                        <div class="focus-item">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($focus); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Assigned Modules -->
            <div class="modules-section">
                <h2 class="modules-title">📘 Your Recommended Modules</h2>
                
                <?php if (empty($module_details)): ?>
                    <div class="empty-modules">
                        <i class="fas fa-book-open"></i>
                        <h3>No modules assigned yet</h3>
                        <p>Your instructor will assign specific modules to your level soon. Check back later!</p>
                    </div>
                <?php else: ?>
                    <div class="modules-grid">
                        <?php foreach ($module_details as $index => $module): ?>
                            <div class="module-card">
                                <div class="module-header">
                                    <div class="module-icon">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="module-title"><?php echo htmlspecialchars($module['title']); ?></div>
                                </div>
                                <div class="module-description">
                                    <?php echo htmlspecialchars($module['description'] ?: 'No description available'); ?>
                                </div>
                                <div class="module-meta">
                                    <span>Module <?php echo $index + 1; ?></span>
                                    <span><?php echo date('M j, Y', strtotime($module['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div style="text-align: center; margin-top: 2rem;">
                <a href="placement_congratulations.php?test_id=<?php echo $test_id; ?>&session_token=<?php echo $session_token; ?><?php echo $preview_mode ? '&preview=1' : ''; ?>" 
                   class="back-btn">
                    ← Back to Results
                </a>
                <a href="<?php echo $preview_mode ? '#' : '../../dashboard/dashboard.php'; ?>" 
                   class="start-learning-btn">
                    <?php echo $preview_mode ? 'Proceed to Dashboard' : 'Proceed to Dashboard'; ?> →
                </a>
            </div>
        </div>
    </div>
</body>
</html>
