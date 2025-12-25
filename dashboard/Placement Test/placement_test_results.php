<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');

require_once '../../../config/database.php';

// Get parameters
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$session_token = isset($_GET['session_token']) ? trim($_GET['session_token']) : '';

if (!$test_id || !$session_token) {
    die('Invalid parameters.');
}

// Get placement result
$stmt = $pdo->prepare("
    SELECT pr.*, pt.title as test_title 
    FROM placement_result pr 
    JOIN placement_test pt ON pr.test_id = pt.id 
    WHERE pr.test_id = ? AND pr.session_token = ?
");
$stmt->execute([$test_id, $session_token]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die('Results not found.');
}

// Decode JSON data
$result['answers'] = json_decode($result['answers'], true) ?? [];
$result['difficulty_scores'] = json_decode($result['difficulty_scores'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Test Results - Japanese LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f5f5;
        }
        
        .result-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin: 2rem auto;
            max-width: 800px;
        }
        
        .level-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .level-beginner {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: white;
        }
        
        .level-intermediate {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .level-advanced {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .score-high {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .score-medium {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .score-low {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
    </style>
</head>
<body>
    <div class="min-h-screen py-8">
        <div class="result-card">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Placement Test Results</h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($result['test_title']); ?></p>
            </div>
            
            <div class="text-center mb-8">
                <div class="score-circle <?php 
                    echo $result['percentage_score'] >= 80 ? 'score-high' : 
                         ($result['percentage_score'] >= 50 ? 'score-medium' : 'score-low'); 
                ?>">
                    <?php echo round($result['percentage_score'], 1); ?>%
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Your Score</h2>
                <p class="text-gray-600">
                    <?php echo $result['correct_answers']; ?> out of <?php echo $result['total_questions']; ?> questions correct
                </p>
            </div>
            
            <div class="text-center mb-8">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Recommended Level</h3>
                <div class="level-badge level-<?php echo $result['recommended_level']; ?>">
                    <?php echo ucfirst($result['recommended_level']); ?>
                </div>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Feedback</h3>
                <p class="text-gray-700"><?php echo htmlspecialchars($result['detailed_feedback']); ?></p>
            </div>
            
            <div class="text-center">
                <a href="../../student/dashboard.php" 
                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Go to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
