<?php
session_start();

            // Set current test ID for updating and mark edit mode
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../index.php");
    exit();
}

require_once '../../config/database.php';
require_once '../includes/teacher_profile_functions.php';
require_once '../../includes/rbac_helper.php';


// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Custom profile rendering function for subdirectory files
function renderTeacherSidebarProfileSubdir($profile, $is_hybrid = false) {
    $display_name = getTeacherDisplayName($profile);
    $picture = getTeacherProfilePicture($profile);
    $role_display = getTeacherRoleDisplay($profile, $is_hybrid);
    
    // Fix the image path for subdirectory files
    if ($picture['has_image']) {
        $picture['image_path'] = '../' . $picture['image_path'];
    }
    
    $html = '<div class="p-3 border-b flex items-center space-x-3">';
    
    if ($picture['has_image']) {
        $html .= '<img src="' . htmlspecialchars($picture['image_path']) . '" 
                      alt="Profile Picture" 
                      class="w-10 h-10 rounded-full object-cover shadow-sm sidebar-profile-picture">';
        $html .= '<div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm sidebar-profile-placeholder" style="display: none;">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
    } else {
        $html .= '<div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm sidebar-profile-placeholder">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
        $html .= '<img src="" alt="Profile Picture" class="w-10 h-10 rounded-full object-cover shadow-sm sidebar-profile-picture" style="display: none;">';
    }
    
    $html .= '<div class="flex-1 min-w-0">';
    $html .= '<div class="font-medium text-sm sidebar-display-name truncate">' . htmlspecialchars($display_name) . '</div>';
    $html .= '<div class="text-xs font-bold text-red-600 sidebar-role">' . htmlspecialchars($role_display) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Check if teacher has hybrid permissions
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
$stmt->execute([$_SESSION['user_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
$is_hybrid = !empty($permissions);

// Store permissions in session if hybrid
if ($is_hybrid) {
    $_SESSION['is_hybrid'] = true;
    $_SESSION['permissions'] = $permissions;
}

// Check placement test permissions for JavaScript
$canCreate = hasPermission($pdo, $_SESSION['user_id'], 'teacher_placement_test_create');
$canEdit = hasPermission($pdo, $_SESSION['user_id'], 'teacher_placement_test_edit');
$canDelete = hasPermission($pdo, $_SESSION['user_id'], 'teacher_placement_test_delete');
$canPublish = hasPermission($pdo, $_SESSION['user_id'], 'teacher_placement_test_publish');
$canPreview = hasPermission($pdo, $_SESSION['user_id'], 'preview_placement');

// Initialize placement test pages table if it doesn't exist
try {
    // Check if placement_test_pages table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'placement_test_pages'");
    if ($stmt->rowCount() == 0) {
        // Create the table without dependency on placement_tests
        $pdo->exec("
            CREATE TABLE placement_test_pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_type ENUM('welcome', 'instructions', 'questions', 'completion', 'custom') NOT NULL,
                page_key VARCHAR(100) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                image_path VARCHAR(255),
                page_order INT NOT NULL DEFAULT 0,
                question_count INT DEFAULT 0,
                is_required BOOLEAN DEFAULT TRUE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_page_order (page_order),
                INDEX idx_page_type (page_type),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert default pages if none exist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM placement_test_pages");
        $stmt->execute();
        $pageCount = $stmt->fetchColumn();
        
        if ($pageCount == 0) {
            $defaultPages = [
                [
                    'page_type' => 'welcome',
                    'page_key' => 'welcome',
                    'title' => 'Welcome Page',
                    'content' => '<h1 style="text-align: center; color: #2563eb; margin-bottom: 1rem;">🎯 Welcome to Japanese Language Placement Test</h1><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">This test will help us determine your current Japanese language level and recommend the best starting module for your learning journey.</p><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">Please answer all questions to the best of your ability. You can only take this test once.</p>',
                    'page_order' => 1
                ],
                [
                    'page_type' => 'instructions',
                    'page_key' => 'instructions',
                    'title' => 'Instructions',
                    'content' => '<h2 style="text-align: center; color: #059669; margin-bottom: 1.5rem;">📋 Are you ready?</h2><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">Before we begin, here are some important instructions:</p><ul style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1.5rem;"><li style="margin-bottom: 0.5rem;">This test contains multiple-choice questions</li><li style="margin-bottom: 0.5rem;">Select the answer that best represents your knowledge</li><li style="margin-bottom: 0.5rem;">You can navigate between questions using Previous/Next buttons</li><li style="margin-bottom: 0.5rem;">Once you complete the test, you cannot retake it</li><li style="margin-bottom: 0.5rem;">Take your time and answer honestly</li></ul>',
                    'page_order' => 2
                ],
                [
                    'page_type' => 'completion',
                    'page_key' => 'completion',
                    'title' => 'Test Complete',
                    'content' => '<div style="text-align: center; margin-bottom: 2rem;"><h2 style="color: #059669; font-size: 2.5em; margin-bottom: 1rem;">🎉 Test Completed!</h2><p style="font-size: 1.2em; line-height: 1.6; margin-bottom: 1rem;">Thank you for completing the Japanese Language Placement Test.</p><p style="font-size: 1.2em; line-height: 1.6; margin-bottom: 1rem;">Your results will be analyzed and you will receive a recommended starting module based on your performance.</p></div>',
                    'page_order' => 999
                ]
            ];
            
            $insertStmt = $pdo->prepare("
                INSERT INTO placement_test_pages (page_type, page_key, title, content, page_order, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            foreach ($defaultPages as $page) {
                $insertStmt->execute([
                    $page['page_type'],
                    $page['page_key'],
                    $page['title'],
                    $page['content'],
                    $page['page_order']
                ]);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error initializing placement test pages: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Test - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../js/session_timeout.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <!-- TinyMCE for Content Page Editor -->
    <script src="../../assets/tinymce/tinymce/js/tinymce/tinymce.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            200: '#fecdd3',
                            300: '#fda4af',
                            400: '#fb7185',
                            500: '#f43f5e',
                            600: '#e11d48',
                            700: '#be123c',
                            800: '#9f1239',
                            900: '#881337',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../css/settings-teacher.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        .main-content {
            height: calc(100vh - 64px);
            overflow-y: auto;
        }
        
        /* Add scrolling to content sections */
        .content-section {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            padding-right: 8px;
        }
        
        /* Custom scrollbar styling */
        .content-section::-webkit-scrollbar {
            width: 8px;
        }
        
        .content-section::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .content-section::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .content-section::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Ensure design content is properly spaced */
        #design-content-container {
            min-height: 600px;
        }
        
        /* Improve spacing for design sections */
        .design-section {
            margin-bottom: 2rem;
        }
        
        /* Ensure form inputs are properly sized */
        .form-input {
            min-height: 40px;
        }
        [x-cloak] { 
            display: none !important; 
        }
        .content-area {
            display: none;
        }
        .content-area.active {
            display: block;
        }
        .nav-link.active,
        .nav-link.bg-primary-50 {
            background-color: #fff1f2 !important;
            color: #be123c !important;
        }
        
        /* Dropdown transition styles */
        .dropdown-enter {
            transition: all 0.2s ease-in-out;
        }
        .dropdown-enter-start {
            opacity: 0;
            transform: translateY(-10px);
        }
        .dropdown-enter-end {
            opacity: 1;
            transform: translateY(0);
        }
        
        
        /* Modern Content Cards */
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .content-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        /* Modern Difficulty Level Cards */
        .difficulty-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .difficulty-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f43f5e, #e11d48);
        }
        
        .difficulty-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        /* Modern Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-beginner {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: white;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }
        
        .badge-intermediate {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 15px rgba(217, 119, 6, 0.3);
        }
        
        .badge-advanced {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }
        
        /* Modern Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(244, 63, 94, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(244, 63, 94, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #f9fafb;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }

        /* Fix icon alignment in buttons */
        .btn-primary svg,
        .btn-secondary svg {
            vertical-align: middle;
            display: inline-block;
            margin-top: -1px;
        }

        .btn-primary.flex,
        .btn-secondary.flex {
            align-items: center;
        }

        .btn-primary.flex svg,
        .btn-secondary.flex svg {
            margin-top: 0;
            flex-shrink: 0;
        }

        /* Ensure perfect icon alignment in navigation buttons */
        .btn-primary.flex.items-center,
        .btn-secondary.flex.items-center {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary.flex.items-center svg,
        .btn-secondary.flex.items-center svg {
            vertical-align: baseline;
            margin-top: 0;
            margin-bottom: 0;
            line-height: 1;
        }

        /* Specific fix for navigation buttons */
        #prevStepBtn svg,
        #saveDraftBtn svg,
        #nextStepBtn svg,
        #createTestFinalBtn svg {
            vertical-align: baseline;
            margin-top: 0;
            margin-bottom: 0;
        }
        
        /* Modern Form Elements */
        .form-input {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #f43f5e;
            box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1);
        }
        
        .search-input {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #f43f5e;
            box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1);
        }
        
        /* Modern Tables */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .data-table th {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
            padding: 1rem;
            font-weight: 600;
            text-align: left;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .data-table tr:hover {
            background: rgba(244, 63, 94, 0.05);
        }
        
        
        /* Modern Chart Containers */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        /* Modern Module Cards */
        .module-card {
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #f43f5e, #e11d48);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .module-card:hover::before {
            opacity: 1;
        }
        
        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        
        /* Modern Results Table */
        .results-table-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Step Indicator Styles */
        .step-indicator-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
        }

        .step-indicator-item.active .step-circle {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
            border-color: #e11d48;
        }

        .step-indicator-item.completed .step-circle {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-color: #059669;
        }

        .step-label {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
            text-align: center;
        }

        .step-indicator-item.active .step-label {
            color: #e11d48;
            font-weight: 600;
        }

        .step-line {
            width: 60px;
            height: 2px;
            background: #e5e7eb;
            margin: 0 10px;
            transition: all 0.3s ease;
        }

        .step-indicator-item.completed + .step-indicator-item .step-line {
            background: linear-gradient(90deg, #10b981, #e5e7eb);
        }

        /* Step Content */
        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        /* Question Card Styles */
        .question-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .question-card:hover {
            border-color: #f43f5e;
            box-shadow: 0 4px 15px rgba(244, 63, 94, 0.1);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .question-number {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .question-difficulty {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .difficulty-beginner {
            background: #dcfce7;
            color: #166534;
        }

        .difficulty-intermediate {
            background: #fef3c7;
            color: #92400e;
        }

        .difficulty-advanced {
            background: #fee2e2;
            color: #991b1b;
        }

        .question-text {
            font-size: 1rem;
            line-height: 1.6;
            color: #374151;
            margin-bottom: 1rem;
        }

        .question-choices {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .question-choice {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .question-choice.correct {
            background: #dcfce7;
            border-color: #22c55e;
            color: #166534;
        }

        .question-choice:hover {
            background: #f3f4f6;
        }

        .choice-letter {
            font-weight: 600;
            color: #374151;
            margin-right: 0.5rem;
        }

        .choice-letter-label {
            font-weight: 600;
            color: #374151;
            margin-right: 0.5rem;
            min-width: 1.5rem;
            display: inline-block;
        }

        /* Page Card Styles */
        .page-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .page-card:hover {
            border-color: #f43f5e;
            box-shadow: 0 4px 15px rgba(244, 63, 94, 0.1);
        }

        .page-type {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .page-type-welcome {
            background: #dbeafe;
            color: #1e40af;
        }

        .page-type-instructions {
            background: #dcfce7;
            color: #166534;
        }

        .page-type-questions {
            background: #fef3c7;
            color: #92400e;
        }

        .page-type-completion {
            background: #e0e7ff;
            color: #3730a3;
        }

        /* Choice Input Styles */
        .choice-input-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .choice-input-group input[type="text"] {
            flex: 1;
            border: none;
            background: transparent;
            padding: 0.5rem;
            font-size: 0.875rem;
        }

        .choice-input-group input[type="text"]:focus {
            outline: none;
        }

        .choice-radio {
            width: 16px;
            height: 16px;
        }

        .remove-choice-btn {
            color: #ef4444;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .remove-choice-btn:hover {
            background: #fee2e2;
        }

        /* Enhanced Module Assignment Styles */
        .module-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .module-item:hover:not(.opacity-60) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        /* Ownership styling for module cards */
        .module-item[data-ownership="other"] {
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            border-left: 4px solid #a855f7;
        }
        
        .module-item[data-ownership="own"] {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-left: 4px solid #3b82f6;
        }
        
        .module-item[data-ownership="other"]:hover:not(.opacity-60) {
            box-shadow: 0 12px 30px rgba(168, 85, 247, 0.2);
            border-color: #a855f7 !important;
        }
        
        .module-item[data-ownership="own"]:hover:not(.opacity-60) {
            box-shadow: 0 12px 30px rgba(59, 130, 246, 0.2);
            border-color: #3b82f6 !important;
        }
        
        /* Enhanced filter controls styling */
        .filter-control {
            transition: all 0.2s ease;
        }
        
        .filter-control:hover {
            border-color: #6b7280;
        }
        
        .filter-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Bulk action button improvements */
        .bulk-action-button {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .bulk-action-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        /* Statistics section improvements */
        .statistics-section {
            backdrop-filter: blur(10px);
            background: linear-gradient(135deg, rgba(239, 246, 255, 0.8) 0%, rgba(243, 232, 255, 0.8) 100%);
        }
        
        /* Selected count badge animation */
        #selectedCount {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
        }
        
        /* Filter section styling */
        .filter-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 231, 235, 0.8);
        }
        
        .module-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
        }
        
        .module-checkbox:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .module-statistics {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        
        .bulk-action-summary {
            font-size: 0.875rem;
            color: #64748b;
        }
        
        /* Teacher info styling */
        .teacher-info {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Ownership badges */
        .ownership-badge {
            font-size: 0.6875rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        
        .ownership-badge.own {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .ownership-badge.shared {
            background-color: #f3e8ff;
            color: #7c3aed;
        }
        
        /* Module Assignment Styles */
        .module-assignment {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .module-info {
            flex: 1;
        }

        .module-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }

        .module-description {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .module-checkbox {
            width: 20px;
            height: 20px;
            accent-color: #f43f5e;
        }

        /* Modern Test Cards */
        .test-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .test-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            border-color: rgba(0, 0, 0, 0.1);
        }

        .test-card::before {
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

        .test-card:hover::before {
            opacity: 1;
        }

        /* Action Button Styles */
        .action-btn {
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s ease, height 0.3s ease;
        }

        .action-btn:hover::before {
            width: 100%;
            height: 100%;
        }

        /* Status Indicator Animation */
        .status-indicator {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Metrics Card Hover Effect */
        .metric-card {
            transition: all 0.2s ease;
        }

        .metric-card:hover {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            transform: translateY(-1px);
        }

        /* TinyMCE Integration Styles */
        .tox-tinymce { 
            border: 1px solid #d1d5db !important; 
            border-radius: 0.375rem !important; 
            font-family: Inter, 'Noto Sans JP', sans-serif !important;
        }
        .tox-tinymce:focus-within { 
            border-color: #f43f5e !important; 
            box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1) !important; 
        }
        .tox .tox-toolbar { 
            background: #f9fafb !important; 
            border-bottom: 1px solid #e5e7eb !important; 
        }
        .tox .tox-toolbar__group { 
            border-color: #e5e7eb !important; 
        }
        .tox .tox-tbtn { 
            color: #374151 !important; 
        }
        .tox .tox-tbtn:hover { 
            background: #e5e7eb !important; 
        }
        .tox .tox-tbtn--enabled { 
            background: #fef2f2 !important; 
            color: #be123c !important; 
        }
        .tox .tox-statusbar { 
            border-top: 1px solid #e5e7eb !important; 
            background: #f9fafb !important; 
        }
        .tox .tox-edit-area { 
            border: none !important; 
        }
        
        /* Question Checkbox Styling */
        .question-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #f43f5e;
            cursor: pointer;
        }
        
        .question-checkbox:checked {
            background-color: #f43f5e;
            border-color: #f43f5e;
        }
        
        .question-checkbox:focus {
            outline: 2px solid #f43f5e;
            outline-offset: 2px;
        }
        
        #questionCheckboxes {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        
        #questionCheckboxes::-webkit-scrollbar {
            width: 6px;
        }
        
        #questionCheckboxes::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 3px;
        }
        
        #questionCheckboxes::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
        }
        
        #questionCheckboxes::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
        
        /* Responsive TinyMCE */
        @media (max-width: 768px) {
            .tox-tinymce { 
                font-size: 14px !important; 
            }
            .tox .tox-toolbar { 
                flex-wrap: wrap !important; 
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-primary-600 to-primary-800 text-white">
                <span class="text-2xl font-bold">Teacher Portal</span>
            </div>
            
            <!-- Teacher Profile -->
            <?php echo renderTeacherSidebarProfileSubdir($teacher_profile, $is_hybrid); ?>

            <!-- Sidebar Navigation -->
            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['module_performance_analytics', 'student_progress_overview', 'teacher_dashboard_view_active_modules', 'teacher_dashboard_view_active_students', 'teacher_dashboard_view_completion_rate', 'teacher_dashboard_view_published_modules', 'teacher_dashboard_view_learning_analytics', 'teacher_dashboard_view_quick_actions', 'teacher_dashboard_view_recent_activities'])): ?>
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ studentDropdownOpen: false }">
                <div class="space-y-1">
                    <a href="../teacher.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
            <?php endif; ?>

            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['unpublished_modules', 'edit_course_module', 'archived_course_module', 'courses'])): ?>           
                    <a href="../courses_available.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                       <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        Courses
                    </a>
            <?php endif; ?>

            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['create_new_module', 'delete_level', 'edit_level', 'add_level', 'add_quiz'])): ?>                      
                    <a href="../teacher_create_module.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Module
                    </a>
            <?php endif; ?>


            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['my_drafts', 'create_new_draft', 'archived_modules', 'published_modules', 'edit_modules'])): ?>
                    <a href="../teacher_drafts.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        My Drafts
                    </a>
            <?php endif; ?>


            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['archived', 'delete_permanently', 'restore_to_drafts'])): ?>
                    <a href="../teacher_archive.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-archive-restore-icon lucide-archive-restore"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/></svg>
                        Archived
                    </a>
            <?php endif; ?>

                    <!-- Student Management Dropdown -->
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['student_profiles', 'progress_tracking', 'quiz_performance', 'engagement_monitoring', 'completion_reports'])): ?>
                    <div class="relative">
                        <button @click="studentDropdownOpen = !studentDropdownOpen" 
                                class="nav-link w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                                :class="{ 'bg-primary-50 text-primary-700': studentDropdownOpen }">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Student Management
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" 
                                 :class="{ 'rotate-180': studentDropdownOpen }" 
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div x-show="studentDropdownOpen" 
                             x-transition:enter="dropdown-enter"
                             x-transition:enter-start="dropdown-enter-start"
                             x-transition:enter-end="dropdown-enter-end"
                             x-cloak
                             class="mt-1 ml-4 space-y-1">

                           <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['student_profiles', 'view_progress_button', 'view_profile_button', 'search_and_filter'])): ?>                            
                            <a href="../Student Management/student_profiles.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Student Profiles
                            </a>
                            <?php endif; ?>

                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['progress_tracking', 'export_progress', 'complete_modules', 'in_progress', 'average_progress', 'active_students', 'progress_distribution', 'module_completion', 'detailed_progress_tracking'])): ?>                           
                            <a href="../Student Management/progress_tracking.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Progress Tracking
                            </a>
                        <?php endif; ?>


                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['quiz_performance', 'filter_search', 'export_pdf_quiz', 'average_score', 'total_attempts', 'active_students_quiz', 'total_quiz_students', 'performance_trend', 'quiz_difficulty_analysis', 'top_performer', 'recent_quiz_attempt'])): ?>                               
                            <a href="../Student Management/quiz_performance.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Quiz Performance
                            </a>
                        <?php endif; ?>
                        
                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['engagement_monitoring', 'filter_engagement_monitoring', 'export_pdf_engagement', 'login_frequency', 'drop_off_rate', 'average_enrollment_days', 'recent_enrollments', 'time_spent_learning', 'module_engagement', 'most_engaged_students', 'recent_enrollments_card'])): ?>                         
                            <a href="../Student Management/engagement_monitoring.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Engagement Monitoring
                            </a>
                        <?php endif; ?>

                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['completion_reports', 'filter_completion_reports', 'export_completion_reports', 'overall_completion_rate', 'average_progress_completion_reports', 'on_time_completions', 'delayed_completions', 'module_completion_breakdown', 'completion_timeline', 'completion_breakdown'])): ?>                          
                            <a href="../Student Management/completion_reports.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Completion Reports
                            </a>
                        <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['preview_placement', 'placement_test', 'teacher_placement_test_create', 'teacher_placement_test_edit', 'teacher_placement_test_delete', 'teacher_placement_test_publish'])): ?>             
                    <a href="../Placement Test/placement_test.php" 
                       class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg fill="#000000" viewBox="0 0 64 64" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg">
                            <g id="SVGRepo_iconCarrier">
                                <g data-name="Layer 2" id="Layer_2">
                                    <path d="M51,5H41.21A2.93,2.93,0,0,0,39,4H34.21a2.94,2.94,0,0,0-4.42,0H25a2.93,2.93,0,0,0-2.21,1H13a2,2,0,0,0-2,2V59a2,2,0,0,0,2,2H51a2,2,0,0,0,2-2V7A2,2,0,0,0,51,5Zm-19.87.5A1,1,0,0,1,32,5a1,1,0,0,1,.87.51A1,1,0,0,1,33,6a1,1,0,0,1-2,0A1.09,1.09,0,0,1,31.13,5.5ZM32,9a3,3,0,0,0,3-3h4a1,1,0,0,1,.87.51A1,1,0,0,1,40,7V9a1,1,0,0,1-1,1H25a1,1,0,0,1-1-1V7a1.09,1.09,0,0,1,.13-.5A1,1,0,0,1,25,6h4A3,3,0,0,0,32,9ZM51,59H13V7h9V9a3,3,0,0,0,3,3H39a3,3,0,0,0,3-3V7h9Z"></path>
                                    <path d="M16,56H48V15H16Zm2-39H46V54H18Z"></path>
                                    <rect height="2" width="18" x="26" y="22"></rect>
                                    <rect height="2" width="4" x="20" y="22"></rect>
                                    <rect height="2" width="18" x="26" y="27"></rect>
                                    <rect height="2" width="4" x="20" y="27"></rect>
                                    <rect height="2" width="18" x="26" y="32"></rect>
                                    <rect height="2" width="4" x="20" y="32"></rect>
                                    <rect height="2" width="18" x="26" y="37"></rect>
                                    <rect height="2" width="4" x="20" y="37"></rect>
                                    <rect height="2" width="18" x="26" y="42"></rect>
                                    <rect height="2" width="4" x="20" y="42"></rect>
                                    <rect height="2" width="18" x="26" y="47"></rect>
                                    <rect height="2" width="4" x="20" y="47"></rect>
                                </g>
                            </g>
                        </svg>
                        Placement Test
                    </a>
            <?php endif; ?>
            
                    <a href="../settings.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                
                <div class="mt-auto pt-4">
                    <a href="../../auth/logout.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-0">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Placement Test Management</h1>
                    <div class="text-sm text-gray-500">
                        <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                        <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <div class="main-content p-6">

                <!-- Action Buttons -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex space-x-4">
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'teacher_placement_test_create')): ?>
                        <button id="createTestBtn" class="btn-primary flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Create New Test
                        </button>
                        <?php endif; ?>
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'preview_placement')): ?>
                        <div id="previewTestBtn" class="inline-flex items-center px-6 py-3 bg-gray-400 text-white rounded-lg shadow-lg cursor-not-allowed opacity-60">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Save Test First
                        </div>
                        <?php endif; ?>
                    </div>
                    
                </div>


                <!-- Tests List -->
                <div class="content-card">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-gray-900">Placement Tests</h2>
                    </div>
                    
                    <div id="testsList" class="space-y-4">
                        <!-- Tests will be loaded here -->
                        <div class="text-center py-12 text-gray-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-lg font-medium">No placement tests found</p>
                            <p class="text-sm">Create your first placement test to get started</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Test Modal -->
    <div id="createTestModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[95vh] overflow-y-auto">
            <div class="p-8 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-3xl font-bold text-gray-900">Create New Placement Test</h2>
                    <button id="closeCreateModal" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 rounded-lg hover:bg-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-8">
                <!-- Step Indicator -->
                <div class="flex items-center justify-center mb-12">
                    <div class="flex items-center space-x-2 sm:space-x-4 overflow-x-auto w-full justify-center">
                        <div class="step-indicator-item active flex-shrink-0" data-step="1">
                            <div class="step-circle">1</div>
                            <div class="step-label">Basic Info</div>
                        </div>
                        <div class="step-line flex-shrink-0"></div>
                        <div class="step-indicator-item flex-shrink-0" data-step="2">
                            <div class="step-circle">2</div>
                            <div class="step-label">Questions</div>
                        </div>
                        <div class="step-line flex-shrink-0"></div>
                        <div class="step-indicator-item flex-shrink-0" data-step="3">
                            <div class="step-circle">3</div>
                            <div class="step-label">Pages</div>
                        </div>
                        <div class="step-line flex-shrink-0"></div>
                        <div class="step-indicator-item flex-shrink-0" data-step="4">
                            <div class="step-circle">4</div>
                            <div class="step-label">Modules</div>
                        </div>
                    </div>
                </div>

                <!-- Step 1: Basic Information -->
                <div id="step1" class="step-content active">
                    <div class="max-w-2xl mx-auto">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Basic Information</h3>
                            <p class="text-gray-600">Let's start by giving your placement test a title</p>
                        </div>
                        
                        <form id="createTestForm">
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Test Title *</label>
                                    <input type="text" id="testTitle" name="title" class="form-input w-full text-lg py-3 px-4" placeholder="Enter your placement test title..." required>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Step 2: Questions -->
                <div id="step2" class="step-content">
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Test Questions</h3>
                        <p class="text-gray-600">Add multiple choice questions for your placement test</p>
                    </div>
                    
                    <div class="flex justify-center mb-8">
                        <button type="button" id="addQuestionBtn" class="btn-primary text-lg px-8 py-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Question
                        </button>
                    </div>
                    
                    <div id="questionsList" class="space-y-6 max-w-4xl mx-auto">
                        <!-- Questions will be added here -->
                    </div>
                </div>

                <!-- Step 3: Pages -->
                <div id="step3" class="step-content">
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Test Pages</h3>
                        <p class="text-gray-600">Create content pages and question pages for your placement test</p>
                    </div>
                    
                    <div class="flex justify-center mb-8 space-x-4">
                        <button type="button" id="addContentPageBtn" class="btn-primary text-lg px-6 py-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Content Page
                        </button>
                    </div>
                    
                    <div id="pagesList" class="space-y-6 max-w-4xl mx-auto">
                        <!-- Pages will be added here -->
                        <div class="text-center py-12 text-gray-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            <p class="text-lg">No pages added yet</p>
                            <p class="text-sm text-gray-400 mt-2">Click the buttons above to add content or question pages</p>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Module Assignments -->
                <div id="step4" class="step-content">
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Module Assignments</h3>
                        <p class="text-gray-600">Assign your courses to different difficulty levels based on placement test results</p>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                        <!-- Beginner Level -->
                        <div class="difficulty-card">
                            <div class="text-center mb-6">
                                <div class="badge badge-beginner text-lg px-4 py-2 mb-3">Beginner</div>
                                <p class="text-sm text-gray-600">Students who score low on beginner questions</p>
                            </div>
                            <div class="mb-4">
                                <button type="button" onclick="openModuleSelector('beginner')" class="btn-secondary w-full flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Add Module
                                </button>
                            </div>
                            <div id="beginnerModules" class="space-y-3">
                                <!-- Beginner modules will be listed here -->
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    <p class="text-sm">No modules assigned</p>
                                </div>
                            </div>
                        </div>

                        <!-- Intermediate Beginner Level -->
                        <div class="difficulty-card">
                            <div class="text-center mb-6">
                                <div class="badge badge-intermediate text-lg px-4 py-2 mb-3">Intermediate Beginner</div>
                                <p class="text-sm text-gray-600">Students who score high on beginner but low on intermediate</p>
                            </div>
                            <div class="mb-4">
                                <button type="button" onclick="openModuleSelector('intermediate')" class="btn-secondary w-full flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Add Module
                                </button>
                            </div>
                            <div id="intermediateModules" class="space-y-3">
                                <!-- Intermediate modules will be listed here -->
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    <p class="text-sm">No modules assigned</p>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Beginner Level -->
                        <div class="difficulty-card">
                            <div class="text-center mb-6">
                                <div class="badge badge-advanced text-lg px-4 py-2 mb-3">Advanced Beginner</div>
                                <p class="text-sm text-gray-600">Students who score high on beginner and intermediate</p>
                            </div>
                            <div class="mb-4">
                                <button type="button" onclick="openModuleSelector('advanced')" class="btn-secondary w-full flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Add Module
                                </button>
                            </div>
                            <div id="advancedModules" class="space-y-3">
                                <!-- Advanced modules will be listed here -->
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    <p class="text-sm">No modules assigned</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex justify-between items-center mt-12 pt-8 border-t border-gray-200">
                    <div class="flex">
                        <button type="button" id="prevStepBtn" class="btn-secondary text-lg px-6 py-3 flex items-center" style="display: none;">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Previous
                        </button>
                    </div>
                    <div class="flex space-x-4">
                        <button type="button" id="saveDraftBtn" class="btn-secondary text-lg px-6 py-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                            </svg>
                            Save Draft
                        </button>
                        <button type="button" id="nextStepBtn" class="btn-primary text-lg px-8 py-3 flex items-center">
                            Next
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                        <button type="button" id="createTestFinalBtn" class="btn-primary text-lg px-8 py-3 flex items-center" style="display: none;">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Publish Test
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Question Template Modal -->
<div id="questionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden" style="z-index: 9999;">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-900">Add Question</h2>
                    <button id="closeQuestionModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <form id="questionForm">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Question Text *</label>
                            <textarea id="questionText" name="question_text" class="form-input w-full" rows="3" required></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Difficulty Level *</label>
                                <select id="questionDifficulty" name="difficulty_level" class="form-input w-full" required>
                                    <option value="">Select Level</option>
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Points</label>
                                <input type="number" id="questionPoints" name="points" class="form-input w-full" value="1" min="1">
                            </div>
                        </div>
                        
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Answer Choices *</label>
                            <div id="choicesList" class="space-y-2">
                                <!-- Choices will be added here -->
                            </div>
                            <button type="button" id="addChoiceBtn" class="btn-secondary mt-2">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Choice
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-8">
                        <button type="button" id="cancelQuestionBtn" class="btn-secondary">Cancel</button>
                        <button type="button" id="saveQuestionBtn" class="btn-primary">Save Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Question Modal (Dedicated for Edit Test) -->
    <div id="editQuestionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden" style="z-index: 9999;">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 id="editQuestionModalTitle" class="text-xl font-bold text-gray-900">Add Question</h2>
                    <button id="closeEditQuestionModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <form id="editQuestionForm">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Question Text *</label>
                            <textarea id="editQuestionText" name="question_text" class="form-input w-full" rows="3" required></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Difficulty Level *</label>
                                <select id="editQuestionDifficulty" name="difficulty_level" class="form-input w-full" required>
                                    <option value="">Select Level</option>
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Points</label>
                                <input type="number" id="editQuestionPoints" name="points" class="form-input w-full" value="1" min="1">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Answer Choices *</label>
                            <div id="editChoicesContainer" class="space-y-2">
                                <!-- Choices will be added here -->
                            </div>
                            <button type="button" id="editAddChoiceBtn" class="btn-secondary mt-2">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Choice
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-8">
                        <button type="button" id="cancelEditQuestionBtn" class="btn-secondary">Cancel</button>
                        <button type="button" id="saveEditQuestionBtn" class="btn-primary">Save Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Test Modal -->
    <div id="editTestModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[95vh] overflow-y-auto">
            <div class="p-8 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-3xl font-bold text-gray-900">Edit Placement Test</h2>
                    <button id="closeEditModal" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 rounded-lg hover:bg-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-8">
                <!-- Step Indicator -->
                <div class="flex items-center justify-center mb-12">
                    <div class="flex items-center space-x-2 sm:space-x-4 overflow-x-auto w-full justify-center">
                        <div class="step-indicator-item active flex-shrink-0" data-step="1">
                            <div class="step-circle">1</div>
                            <div class="step-label">Basic Info</div>
                        </div>
                        <div class="step-line flex-shrink-0"></div>
                        <div class="step-indicator-item flex-shrink-0" data-step="2">
                            <div class="step-circle">2</div>
                            <div class="step-label">Questions</div>
                        </div>
                        <div class="step-line flex-shrink-0"></div>
                        <div class="step-indicator-item flex-shrink-0" data-step="3">
                            <div class="step-circle">3</div>
                            <div class="step-label">Pages</div>
                        </div>
                        <div class="step-line flex-shrink-0"></div>
                        <div class="step-indicator-item flex-shrink-0" data-step="4">
                            <div class="step-circle">4</div>
                            <div class="step-label">Modules</div>
                        </div>
                    </div>
                </div>

                <!-- Step Content -->
                <div class="step-content-container">
                    <!-- Step 1: Basic Information -->
                    <div id="editStep1" class="step-content active">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Basic Information</h3>
                            <p class="text-gray-600">Provide the basic details for your placement test</p>
                        </div>
                        
                        <div class="max-w-2xl mx-auto space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Test Title *</label>
                                <input type="text" id="editTestTitle" class="form-input w-full" placeholder="Enter test title" required>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Questions -->
                    <div id="editStep2" class="step-content">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Test Questions</h3>
                            <p class="text-gray-600">Add multiple choice questions for your placement test</p>
                        </div>
                        
                        <div class="flex justify-center mb-8">
                            <button type="button" id="editAddQuestionBtn" class="btn-primary text-lg px-8 py-3 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Question
                            </button>
                        </div>
                        
                        <div id="editQuestionsList" class="space-y-6 max-w-4xl mx-auto">
                            <!-- Questions will be rendered here -->
                        </div>
                    </div>

                    <!-- Step 3: Pages -->
                    <div id="editStep3" class="step-content">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Test Pages</h3>
                            <p class="text-gray-600">Create content pages and question pages for your placement test</p>
                        </div>
                        
                        <div class="flex justify-center mb-8 space-x-4">
                            <button type="button" id="editAddContentPageBtn" class="btn-primary text-lg px-6 py-3 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Content Page
                            </button>
                            <button type="button" id="editAddQuestionPageBtn" class="btn-primary text-lg px-6 py-3 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Question Page
                            </button>
                        </div>
                        
                        <div id="editPagesList" class="space-y-4">
                            <!-- Pages will be rendered here -->
                        </div>
                    </div>

                    <!-- Step 4: Module Assignments -->
                    <div id="editStep4" class="step-content">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Module Assignments</h3>
                            <p class="text-gray-600">Assign modules to different difficulty levels</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
                            <!-- Beginner Level -->
                            <div class="difficulty-card">
                                <div class="text-center mb-6">
                                    <div class="badge badge-beginner text-lg px-4 py-2 mb-3">Beginner Level</div>
                                    <p class="text-sm text-gray-600">Students who score low on beginner questions</p>
                                </div>
                                <div class="mb-4">
                                    <button type="button" onclick="openEditModuleSelector('beginner')" class="btn-secondary w-full flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Add Modules
                                    </button>
                                </div>
                                <div id="editBeginnerModules" class="min-h-[200px] border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <div class="text-center py-8 text-gray-500">
                                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        <p>No modules assigned</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Intermediate Beginner Level -->
                            <div class="difficulty-card">
                                <div class="text-center mb-6">
                                    <div class="badge badge-intermediate text-lg px-4 py-2 mb-3">Intermediate Beginner</div>
                                    <p class="text-sm text-gray-600">Students who score high on beginner but low on intermediate</p>
                                </div>
                                <div class="mb-4">
                                    <button type="button" onclick="openEditModuleSelector('intermediate')" class="btn-secondary w-full flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Add Modules
                                    </button>
                                </div>
                                <div id="editIntermediateModules" class="min-h-[200px] border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <div class="text-center py-8 text-gray-500">
                                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        <p>No modules assigned</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Advanced Beginner Level -->
                            <div class="difficulty-card">
                                <div class="text-center mb-6">
                                    <div class="badge badge-advanced text-lg px-4 py-2 mb-3">Advanced Beginner</div>
                                    <p class="text-sm text-gray-600">Students who score high on beginner and intermediate</p>
                                </div>
                                <div class="mb-4">
                                    <button type="button" onclick="openEditModuleSelector('advanced')" class="btn-secondary w-full flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Add Modules
                                    </button>
                                </div>
                                <div id="editAdvancedModules" class="min-h-[200px] border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <div class="text-center py-8 text-gray-500">
                                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        <p>No modules assigned</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex justify-between items-center mt-12 pt-8 border-t border-gray-200">
                    <div class="flex">
                        <button type="button" id="editPrevBtn" class="btn-secondary text-lg px-6 py-3 flex items-center" style="display: none;">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Previous
                        </button>
                    </div>
                    <div class="flex space-x-4" style="display: none;">
                        <button type="button" id="editSaveDraftBtn" class="btn-secondary text-lg px-6 py-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                            </svg>
                            Save as Draft
                        </button>
                        <button type="button" id="editPublishBtn" class="btn-primary text-lg px-8 py-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Publish Test
                        </button>
                    </div>
                    <button type="button" id="editNextBtn" class="btn-primary text-lg px-8 py-3 flex items-center">
                        Next
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Page Modal -->
    <div id="pageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-900" id="pageModalTitle">Add Page</h2>
                    <button id="closePageModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <form id="pageForm">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Page Type</label>
                            <select id="pageType" name="page_type" class="form-input w-full">
                                <option value="content">Content Page</option>
                                <option value="question">Question Page</option>
                            </select>
                        </div>
                        
                        <div id="pageTitleField">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Page Title *</label>
                            <input type="text" id="pageTitle" name="title" class="form-input w-full" required>
                        </div>
                        
                        <div id="contentFields">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Message/Content</label>
                                <div id="pageContentEditor" class="w-full"></div>
                                <textarea id="pageContent" name="content" style="display: none;"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Image (Optional)</label>
                                <input type="file" id="pageImage" name="image" class="form-input w-full" accept="image/*">
                            </div>
                        </div>
                        
                        <div id="questionFields" style="display: none;">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Questions</label>
                                <div class="relative">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-gray-600">Choose questions to include in this page</span>
                                        <button type="button" id="selectAllQuestions" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                            Select All
                                        </button>
                                    </div>
                                    <div id="questionCheckboxes" class="max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3 bg-gray-50">
                                        <!-- Question checkboxes will be populated here -->
                                    </div>
                                    <input type="hidden" id="pageQuestion" name="question_id" value="">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-8">
                        <button type="button" id="cancelPageBtn" class="btn-secondary">Cancel</button>
                        <button type="button" id="savePageBtn" class="btn-primary">Save Page</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Module Selector Modal -->
    <div id="moduleSelectorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Module Assignment</h2>
                        <p class="text-gray-600 mt-1">Assign modules to <span id="selectedLevel" class="font-semibold text-blue-600"></span> level</p>
                    </div>
                    <button id="closeModuleSelector" class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 rounded-lg hover:bg-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6 overflow-y-auto" style="max-height: calc(85vh - 200px);">


                <!-- Enhanced Statistics with Ownership -->
                <div class="mb-6 p-6 statistics-section rounded-xl border border-gray-200 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Module Statistics
                    </h3>
                    <div class="module-statistics-summary">
                        <!-- Will be populated by updateModuleStatistics() -->
                    </div>
                </div>

                <!-- Bulk Actions with Enhanced Filtering -->
                <div class="mb-6 p-4 filter-section rounded-lg shadow-sm">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-4 lg:space-y-0">
                        <!-- Bulk Action Buttons -->
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" id="selectAllModules" class="bulk-action-button inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Select All Available
                            </button>
                            <button type="button" id="deselectAllModules" class="bulk-action-button inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Deselect All
                            </button>
                            <div class="flex items-center px-3 py-2 bg-blue-50 border border-blue-200 rounded-md">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd"></path>
                                </svg>
                                <span class="text-sm font-medium text-blue-700" id="selectedCount">0 selected</span>
                            </div>
                        </div>
                        
                        <!-- Filter Controls -->
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="text-sm font-medium text-gray-700 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"></path>
                                </svg>
                                Filter by:
                            </label>
                            <select id="moduleStatusFilter" class="filter-control inline-flex items-center px-3 py-2 text-sm border border-gray-300 rounded-md bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <option value="all">All Modules</option>
                                <option value="available">Available Only</option>
                                <option value="assigned">Currently Assigned</option>
                                <option value="other-levels">Assigned to Other Levels</option>
                            </select>
                            <select id="moduleOwnershipFilter" class="filter-control inline-flex items-center px-3 py-2 text-sm border border-gray-300 rounded-md bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <option value="all">All Teachers</option>
                                <option value="own">Your Modules</option>
                                <option value="other">Shared Modules</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div id="availableCourses" class="space-y-3 max-h-96 overflow-y-auto border border-gray-200 rounded-xl p-4 bg-gray-50">
                    <!-- Available courses will be loaded here -->
                    <div class="text-center py-8 text-gray-500">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
                        <p class="text-sm font-medium">Loading modules...</p>
                    </div>
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200 bg-gray-50">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        <span class="font-medium" id="bulkActionSummary">Select modules to assign or remove</span>
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" id="cancelModuleSelection" class="btn-secondary">Cancel</button>
                        <button type="button" id="removeSelectedModules" class="btn-secondary text-red-600 hover:bg-red-50" disabled>Remove Selected</button>
                        <button type="button" id="confirmModuleSelection" class="btn-primary" disabled>Assign Selected</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- TinyMCE 6 for rich text editing -->
    <script src="../../assets/tinymce/tinymce/js/tinymce/tinymce.min.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <script src="../js/settings-teacher.js"></script>
    
    <script>
        // Pass PHP permissions to JavaScript
        window.placementTestPermissions = {
            canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
            canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
            canDelete: <?php echo $canDelete ? 'true' : 'false'; ?>,
            canPublish: <?php echo $canPublish ? 'true' : 'false'; ?>,
            canPreview: <?php echo $canPreview ? 'true' : 'false'; ?>
        };
        
        // Initialize Lucide Icons after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
        
        // Re-initialize icons when content changes (for dynamic content)
        function initializeLucideIcons() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
        
        // Make it globally available
        window.initializeLucideIcons = initializeLucideIcons;
        
        // Real-time clock for Philippines timezone
        function updateRealTimeClock() {
            const now = new Date();
            const philippinesTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
            
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: 'Asia/Manila'
            };
            
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: 'Asia/Manila'
            };
            
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            if (timeElement) {
                timeElement.textContent = philippinesTime.toLocaleTimeString('en-US', timeOptions) + ' (PHT)';
            }
            
            if (dateElement) {
                dateElement.textContent = philippinesTime.toLocaleDateString('en-US', dateOptions);
            }
        }

        // Initialize real-time clock
        document.addEventListener('DOMContentLoaded', function() {
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000); // Update every second
            
            // Initialize placement test functionality
            initializePlacementTest();
            
            // Initialize edit test modal functionality
            initializeEditTestModal();
            
            // Test if step elements exist
            console.log('Testing step elements existence:');
            console.log('editStep1 exists:', document.getElementById('editStep1'));
            console.log('editStep2 exists:', document.getElementById('editStep2'));
            console.log('editStep3 exists:', document.getElementById('editStep3'));
            console.log('editStep4 exists:', document.getElementById('editStep4'));
            
            // Initialize Lucide Icons
            setTimeout(() => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 100);
        });

        // Question Validation Functions
        function validateQuestionForm(questionText, choices, hasCorrectAnswer, isEditMode = false) {
            const errors = [];
            
            // Validate question text
            if (!questionText || questionText.trim().length === 0) {
                errors.push('Question text is required');
            } else if (questionText.trim().length < 10) {
                errors.push('Question text must be at least 10 characters long');
            }
            
            // Validate choices
            if (!choices || choices.length < 2) {
                errors.push('At least 2 answer choices are required');
            } else if (choices.length > 6) {
                errors.push('Maximum 6 answer choices allowed');
            }
            
            // Check for empty choices
            const nonEmptyChoices = choices.filter(choice => choice.text && choice.text.trim().length > 0);
            if (nonEmptyChoices.length < 2) {
                errors.push('At least 2 non-empty answer choices are required');
            }
            
            // Validate correct answer
            if (!hasCorrectAnswer) {
                errors.push('Please select one correct answer');
            }
            
            // Check for duplicate choices
            const choiceTexts = nonEmptyChoices.map(choice => choice.text.trim().toLowerCase());
            const uniqueChoiceTexts = [...new Set(choiceTexts)];
            if (choiceTexts.length !== uniqueChoiceTexts.length) {
                errors.push('Duplicate answer choices are not allowed');
            }
            
            return {
                isValid: errors.length === 0,
                errors: errors
            };
        }

        function validateEditQuestionForm() {
            const questionText = document.getElementById('editQuestionText').value.trim();
            const difficulty = document.getElementById('editQuestionDifficulty').value;
            const points = parseInt(document.getElementById('editQuestionPoints').value) || 1;
            
            // Get choices from edit modal
            const choices = [];
            let hasCorrectAnswer = false;
            const choiceInputs = document.querySelectorAll('#editChoicesContainer .choice-item');
            
            choiceInputs.forEach((choiceDiv, index) => {
                const textInput = choiceDiv.querySelector('input[type="text"]');
                const radioButton = choiceDiv.querySelector('input[type="radio"]');
                const text = textInput ? textInput.value.trim() : '';
                const isCorrect = radioButton ? radioButton.checked : false;
                
                if (text) {
                    choices.push({
                        text: text,
                        is_correct: isCorrect
                    });
                    
                    if (isCorrect) {
                        hasCorrectAnswer = true;
                    }
                }
            });
            
            // Additional validation for difficulty and points
            const errors = [];
            if (!difficulty) {
                errors.push('Please select a difficulty level');
            }
            if (points < 1 || points > 100) {
                errors.push('Points must be between 1 and 100');
            }
            
            // Additional quality checks
            if (questionText.length > 1000) {
                errors.push('Question text is too long (maximum 1000 characters)');
            }
            
            // Check for HTML/script injection attempts
            const htmlPattern = /<script|javascript:|on\w+=/i;
            if (htmlPattern.test(questionText)) {
                errors.push('Question text contains potentially unsafe content');
            }
            
            // Validate choice text quality
            choices.forEach((choice, index) => {
                if (choice.text.length > 200) {
                    errors.push(`Choice ${String.fromCharCode(65 + index)} is too long (maximum 200 characters)`);
                }
                if (htmlPattern.test(choice.text)) {
                    errors.push(`Choice ${String.fromCharCode(65 + index)} contains potentially unsafe content`);
                }
            });
            
            // Run main validation
            const validation = validateQuestionForm(questionText, choices, hasCorrectAnswer, true);
            
            return {
                isValid: validation.isValid && errors.length === 0,
                errors: [...validation.errors, ...errors],
                data: {
                    questionText,
                    difficulty,
                    points,
                    choices
                }
            };
        }

        function showValidationErrors(errors) {
            if (errors && errors.length > 0) {
                const errorMessage = errors.length === 1 
                    ? errors[0] 
                    : `Please fix the following issues:\n• ${errors.join('\n• ')}`;
                
                showNotification(errorMessage, 'error');
                return true;
            }
            return false;
        }

        function hasUnsavedQuestionChanges() {
            if (!window.editQuestionOriginalData) return false;
            
            // Check if currently editing a question
            if (window.currentEditingQuestionIndex === null || window.currentEditingQuestionIndex === undefined) {
                return false;
            }
            
            const currentData = {
                questionText: document.getElementById('editQuestionText').value.trim(),
                difficulty: document.getElementById('editQuestionDifficulty').value,
                points: parseInt(document.getElementById('editQuestionPoints').value) || 1,
                choices: []
            };
            
            // Get current choices
            const choiceInputs = document.querySelectorAll('#editChoicesContainer .choice-item');
            choiceInputs.forEach((choiceDiv) => {
                const textInput = choiceDiv.querySelector('input[type="text"]');
                const radioButton = choiceDiv.querySelector('input[type="radio"]');
                
                if (textInput) {
                    currentData.choices.push({
                        text: textInput.value.trim(),
                        is_correct: radioButton ? radioButton.checked : false
                    });
                }
            });
            
            const original = window.editQuestionOriginalData;
            
            // Compare basic data
            if (currentData.questionText !== original.questionText ||
                currentData.difficulty !== original.difficulty ||
                currentData.points !== original.points) {
                return true;
            }
            
            // Compare choices count
            if (currentData.choices.length !== original.choices.length) {
                return true;
            }
            
            // Compare choices content
            for (let i = 0; i < currentData.choices.length; i++) {
                const current = currentData.choices[i];
                const orig = original.choices[i];
                
                if (!orig || current.text !== orig.text || current.is_correct !== orig.is_correct) {
                    return true;
                }
            }
            
            // Check for meaningful changes (not just whitespace)
            const hasSubstantialChanges = 
                currentData.questionText.replace(/\s+/g, ' ') !== original.questionText.replace(/\s+/g, ' ') ||
                currentData.choices.some((choice, index) => {
                    const origChoice = original.choices[index];
                    return origChoice && choice.text.replace(/\s+/g, ' ') !== origChoice.text.replace(/\s+/g, ' ');
                });
            
            return hasSubstantialChanges;
        }

        function hasUnsavedMainQuestionChanges() {
            if (!window.questionOriginalData) return false;
            
            // Check if currently editing a question
            if (window.currentEditingQuestionIndex === null || window.currentEditingQuestionIndex === undefined) {
                return false;
            }
            
            const currentData = {
                questionText: document.getElementById('questionText').value.trim(),
                difficulty: document.getElementById('questionDifficulty').value,
                points: parseInt(document.getElementById('questionPoints').value) || 1,
                choices: []
            };
            
            // Get current choices from main modal
            const choiceInputs = document.querySelectorAll('#choicesList .choice-input-group');
            choiceInputs.forEach((choiceDiv) => {
                const textInput = choiceDiv.querySelector('input[name="choice_text"]');
                const radioButton = choiceDiv.querySelector('input[name="correct_choice"]');
                
                if (textInput) {
                    currentData.choices.push({
                        text: textInput.value.trim(),
                        is_correct: radioButton ? radioButton.checked : false
                    });
                }
            });
            
            const original = window.questionOriginalData;
            
            // Compare basic data
            if (currentData.questionText !== original.questionText ||
                currentData.difficulty !== original.difficulty ||
                currentData.points !== original.points) {
                return true;
            }
            
            // Compare choices count
            if (currentData.choices.length !== original.choices.length) {
                return true;
            }
            
            // Compare choices content
            for (let i = 0; i < currentData.choices.length; i++) {
                const current = currentData.choices[i];
                const orig = original.choices[i];
                
                if (!orig || current.text !== orig.text || current.is_correct !== orig.is_correct) {
                    return true;
                }
            }
            
            // Check for meaningful changes (not just whitespace)
            const hasSubstantialChanges = 
                currentData.questionText.replace(/\s+/g, ' ') !== original.questionText.replace(/\s+/g, ' ') ||
                currentData.choices.some((choice, index) => {
                    const origChoice = original.choices[index];
                    return origChoice && choice.text.replace(/\s+/g, ' ') !== origChoice.text.replace(/\s+/g, ' ');
                });
            
            return hasSubstantialChanges;
        }

        function resetMainQuestionForm() {
            // Clear form fields
            document.getElementById('questionText').value = '';
            document.getElementById('questionDifficulty').value = '';
            document.getElementById('questionPoints').value = '1';
            
            // Clear choices container
            const choicesList = document.getElementById('choicesList');
            choicesList.innerHTML = '';
            
            // Reset editing state variables
            window.currentEditingQuestionIndex = null;
            window.questionOriginalData = null;
            
            // Reset modal title
            document.querySelector('#questionModal h2').textContent = 'Add Question';
            
            // Clear any validation errors
            const errorElements = document.querySelectorAll('#questionModal .error-message');
            errorElements.forEach(element => element.remove());
            
            // Reset form validation state
            const formInputs = document.querySelectorAll('#questionModal input, #questionModal select, #questionModal textarea');
            formInputs.forEach(input => {
                input.classList.remove('error', 'invalid');
                input.removeAttribute('aria-invalid');
            });
            
            // Log reset for debugging
            console.log('Main question form reset completed');
        }

        // Page Validation Functions
        function validatePageForm(pageTitle, pageContent, pageType, selectedQuestions = [], isEditMode = false) {
            const errors = [];
            const pageTypeNorm = (pageType || '').toString().trim().toLowerCase();
            
            // Validate page title
            // For question pages title is optional (we allow question pages without a separate title)
            if (pageTypeNorm !== 'question') {
                if (!pageTitle || pageTitle.trim().length === 0) {
                    errors.push('Page title is required');
                } else if (pageTitle.trim().length < 3) {
                    errors.push('Page title must be at least 3 characters long');
                } else if (pageTitle.trim().length > 100) {
                    errors.push('Page title must be less than 100 characters');
                }
            }
            
            // Validate page type
            const validPageTypes = ['welcome', 'instructions', 'content', 'question', 'completion'];
            if (!pageTypeNorm || !validPageTypes.includes(pageTypeNorm)) {
                errors.push('Please select a valid page type');
            }
            
            // Type-specific validation
            switch (pageTypeNorm) {
                case 'content':
                case 'welcome':
                case 'instructions':
                case 'completion':
                    // Validate content for content-based pages
                    if (!pageContent || pageContent.trim().length === 0) {
                        errors.push('Page content is required for this page type');
                    } else if (pageContent.trim().length < 10) {
                        errors.push('Page content must be at least 10 characters long');
                    } else if (pageContent.trim().length > 5000) {
                        errors.push('Page content is too long (maximum 5000 characters)');
                    }
                    break;
                    
                case 'question':
                    // Validate questions for question pages
                    if (!selectedQuestions || selectedQuestions.length === 0) {
                        errors.push('Please select at least one question for question pages');
                    } else if (selectedQuestions.length > 20) {
                        errors.push('Too many questions selected (maximum 20 per page)');
                    }
                    break;
            }
            
            // Security validation - check for potentially unsafe content
            const htmlPattern = /<script|javascript:|on\w+=/i;
            if (htmlPattern.test(pageTitle)) {
                errors.push('Page title contains potentially unsafe content');
            }
            if (pageContent && htmlPattern.test(pageContent)) {
                errors.push('Page content contains potentially unsafe content');
            }
            
            return {
                isValid: errors.length === 0,
                errors: errors
            };
        }

        function validatePageFormInputs(isEditMode = false) {
            const pageTitle = document.getElementById('pageTitle').value.trim();
            const pageType = document.getElementById('pageType').value;
            const pageContent = document.getElementById('pageContent').value.trim();
            
            // Get selected questions for question pages
            let selectedQuestions = [];
            if (pageType === 'question') {
                const questionCheckboxes = document.querySelectorAll('#questionCheckboxes input[type="checkbox"]:checked');
                selectedQuestions = Array.from(questionCheckboxes).map(cb => cb.value);
            }
            
            // Run main validation
            const validation = validatePageForm(pageTitle, pageContent, pageType, selectedQuestions, isEditMode);
            
            return {
                isValid: validation.isValid,
                errors: validation.errors,
                data: {
                    pageTitle,
                    pageType,
                    pageContent,
                    selectedQuestions
                }
            };
        }

        function hasUnsavedPageChanges() {
            if (!window.editPageOriginalData) return false;
            
            // Check if currently editing a page
            if (window.currentEditingPageIndex === null || window.currentEditingPageIndex === undefined) {
                return false;
            }
            
            const currentData = {
                pageTitle: document.getElementById('pageTitle').value.trim(),
                pageType: document.getElementById('pageType').value,
                pageContent: document.getElementById('pageContent').value.trim(),
                selectedQuestions: []
            };
            
            // Get current selected questions
            const questionCheckboxes = document.querySelectorAll('#questionCheckboxes input[type="checkbox"]:checked');
            currentData.selectedQuestions = Array.from(questionCheckboxes).map(cb => cb.value);
            
            const original = window.editPageOriginalData;
            
            // Compare basic data
            if (currentData.pageTitle !== original.pageTitle ||
                currentData.pageType !== original.pageType) {
                return true;
            }
            
            // Compare content (ignore minor whitespace changes)
            const currentContentNormalized = currentData.pageContent.replace(/\s+/g, ' ');
            const originalContentNormalized = original.pageContent.replace(/\s+/g, ' ');
            if (currentContentNormalized !== originalContentNormalized) {
                return true;
            }
            
            // Compare selected questions
            if (currentData.selectedQuestions.length !== original.selectedQuestions.length) {
                return true;
            }
            
            const sortedCurrent = [...currentData.selectedQuestions].sort();
            const sortedOriginal = [...original.selectedQuestions].sort();
            
            for (let i = 0; i < sortedCurrent.length; i++) {
                if (sortedCurrent[i] !== sortedOriginal[i]) {
                    return true;
                }
            }
            
            return false;
        }

        function validatePageEditPermissions(pageIndex) {
            // Check if page index is valid
            if (pageIndex === null || pageIndex === undefined) {
                return {
                    valid: false,
                    message: 'No page selected for editing'
                };
            }

            // Check if the page exists in the appropriate array
            const pagesArray = window.currentTestId ? pages : editPages;
            if (!pagesArray[pageIndex]) {
                return {
                    valid: false,
                    message: 'Page not found'
                };
            }

            // Check if test is in editable state
            const testStatus = document.getElementById('editTestModal').dataset.testStatus;
            if (testStatus === 'published') {
                return {
                    valid: false,
                    message: 'Cannot edit pages in published tests without confirmation'
                };
            }

            return {
                valid: true,
                message: 'Permission granted'
            };
        }

        function validateQuestionEditPermissions() {
            // Check if user has permission to edit questions
            if (!window.currentEditingQuestionIndex && window.currentEditingQuestionIndex !== 0) {
                return {
                    valid: false,
                    message: 'No question selected for editing'
                };
            }

            // Check if the question exists
            if (!editQuestions[window.currentEditingQuestionIndex]) {
                return {
                    valid: false,
                    message: 'Question not found'
                };
            }

            // Check if test is in editable state
            const testStatus = document.getElementById('editTestModal').dataset.testStatus;
            if (testStatus === 'published' && !confirm('This test is published. Editing questions may affect student results. Continue?')) {
                return {
                    valid: false,
                    message: 'Edit cancelled by user'
                };
            }

            return {
                valid: true,
                message: 'Permission granted'
            };
        }

        // Placement Test Management JavaScript
        function initializePlacementTest() {
            let currentStep = 1;

            // Modal controls
            const createTestModal = document.getElementById('createTestModal');
            const questionModal = document.getElementById('questionModal');
            const createTestBtn = document.getElementById('createTestBtn');
            const closeCreateModal = document.getElementById('closeCreateModal');
            const closeQuestionModal = document.getElementById('closeQuestionModal');

            // Step navigation
            const nextStepBtn = document.getElementById('nextStepBtn');
            const prevStepBtn = document.getElementById('prevStepBtn');
            const saveDraftBtn = document.getElementById('saveDraftBtn');
            const createTestFinalBtn = document.getElementById('createTestFinalBtn');

            // Question management
            const addQuestionBtn = document.getElementById('addQuestionBtn');
            const addChoiceBtn = document.getElementById('addChoiceBtn');
            const saveQuestionBtn = document.getElementById('saveQuestionBtn');
            const cancelQuestionBtn = document.getElementById('cancelQuestionBtn');

            // Page management
            const addContentPageBtn = document.getElementById('addContentPageBtn');
            const pageModal = document.getElementById('pageModal');
            const closePageModal = document.getElementById('closePageModal');
            const savePageBtn = document.getElementById('savePageBtn');
            const cancelPageBtn = document.getElementById('cancelPageBtn');

            // Module selector management
            const moduleSelectorModal = document.getElementById('moduleSelectorModal');
            const closeModuleSelector = document.getElementById('closeModuleSelector');
            const cancelModuleSelection = document.getElementById('cancelModuleSelection');
            const confirmModuleSelection = document.getElementById('confirmModuleSelection');
            const availableCourses = document.getElementById('availableCourses');
            const selectedLevel = document.getElementById('selectedLevel');
            const pageTypeSelect = document.getElementById('pageType');
            const contentFields = document.getElementById('contentFields');
            const questionFields = document.getElementById('questionFields');

            // Event listeners
            createTestBtn.addEventListener('click', () => {
                createTestModal.classList.remove('hidden');
                resetCreateTestForm();
            });

            // Add change tracking for form inputs
            function setupChangeTracking() {
                // Track test title changes
                const testTitleInput = document.getElementById('testTitle');
                if (testTitleInput) {
                    testTitleInput.addEventListener('input', markAsChanged);
                }

                // Track question form changes
                const questionTextInput = document.getElementById('questionText');
                const questionDifficultySelect = document.getElementById('questionDifficulty');
                const questionPointsInput = document.getElementById('questionPoints');
                
                if (questionTextInput) questionTextInput.addEventListener('input', markAsChanged);
                if (questionDifficultySelect) questionDifficultySelect.addEventListener('change', markAsChanged);
                if (questionPointsInput) questionPointsInput.addEventListener('input', markAsChanged);

                // Track page form changes
                const pageTitleInput = document.getElementById('pageTitle');
                const pageContentEditor = document.getElementById('pageContentEditor');
                
                if (pageTitleInput) pageTitleInput.addEventListener('input', markAsChanged);
                
                // Track TinyMCE changes
                if (pageContentEditor) {
                    // This will be set up when TinyMCE is initialized
                }
            }

            closeCreateModal.addEventListener('click', async () => {
                const result = await checkUnsavedChanges('close_modal');
                if (result === 'proceed' || result === 'leave' || result === 'save') {
                createTestModal.classList.add('hidden');
                }
            });

            closeQuestionModal.addEventListener('click', async () => {
                if (hasUnsavedMainQuestionChanges()) {
                    const confirmed = await showConfirmationModal({
                        type: 'warning',
                        title: 'Unsaved Changes',
                        message: 'You have unsaved changes. Are you sure you want to close without saving?',
                        confirmText: 'Close Without Saving',
                        cancelText: 'Continue Editing'
                    });
                    
                    if (!confirmed) return;
                }
                
                // Reset form and close modal
                resetMainQuestionForm();
                questionModal.classList.add('hidden');
            });

            // Step navigation event listeners
            nextStepBtn.addEventListener('click', () => {
                if (validateCurrentStep()) {
                    currentStep++;
                    updateStepIndicator();
                    updateStepContent();
                    updateNavigationButtons();
                }
            });

            prevStepBtn.addEventListener('click', () => {
                currentStep--;
                updateStepIndicator();
                updateStepContent();
                updateNavigationButtons();
            });

            // Question management event listeners
            addQuestionBtn.addEventListener('click', () => {
                questionModal.classList.remove('hidden');
                resetQuestionForm();
            });

            addChoiceBtn.addEventListener('click', addChoice);
            saveQuestionBtn.addEventListener('click', async () => {
                const confirmed = await showConfirmationModal({
                    type: 'info',
                    title: 'Save Question',
                    message: 'Are you sure you want to save this question?',
                    confirmText: 'Save Question',
                    cancelText: 'Continue Editing'
                });
                
                if (confirmed) {
                    saveQuestion();
                }
            });
            cancelQuestionBtn.addEventListener('click', async () => {
                if (hasUnsavedMainQuestionChanges()) {
                    const confirmed = await showConfirmationModal({
                        type: 'warning',
                        title: 'Unsaved Changes',
                        message: 'You have unsaved changes. Are you sure you want to cancel without saving?',
                        confirmText: 'Cancel Without Saving',
                        cancelText: 'Continue Editing'
                    });
                    
                    if (!confirmed) return;
                }
                
                // Reset form and close modal
                resetMainQuestionForm();
                questionModal.classList.add('hidden');
            });

            // Page management event listeners
            addContentPageBtn.addEventListener('click', () => {
                openPageModal('content');
            });


            closePageModal.addEventListener('click', async () => {
                if (hasUnsavedPageChanges()) {
                    const confirmed = await showConfirmationModal({
                        type: 'warning',
                        title: 'Unsaved Changes',
                        message: 'You have unsaved changes. Are you sure you want to close without saving?',
                        confirmText: 'Close Without Saving',
                        cancelText: 'Continue Editing'
                    });
                    
                    if (!confirmed) return;
                }
                
                // Clean up TinyMCE before closing
                if (tinymce.get('pageContentEditor')) {
                    tinymce.get('pageContentEditor').remove();
                }
                resetPageForm();
                pageModal.classList.add('hidden');
            });

            cancelPageBtn.addEventListener('click', async () => {
                if (hasUnsavedPageChanges()) {
                    const confirmed = await showConfirmationModal({
                        type: 'warning',
                        title: 'Unsaved Changes',
                        message: 'You have unsaved changes. Are you sure you want to cancel without saving?',
                        confirmText: 'Cancel Without Saving',
                        cancelText: 'Continue Editing'
                    });
                    
                    if (!confirmed) return;
                }
                
                // Clean up TinyMCE before closing
                if (tinymce.get('pageContentEditor')) {
                    tinymce.get('pageContentEditor').remove();
                }
                resetPageForm();
                pageModal.classList.add('hidden');
            });

            // Module selector event listeners
            closeModuleSelector.addEventListener('click', () => {
                selectedModules.clear();
                moduleSelectorModal.classList.add('hidden');
            });

            cancelModuleSelection.addEventListener('click', () => {
                selectedModules.clear();
                moduleSelectorModal.classList.add('hidden');
            });

            confirmModuleSelection.addEventListener('click', async () => {
                if (selectedModules.size === 0) return;
                
                const confirmed = await showConfirmationModal({
                    type: 'info',
                    title: 'Assign Modules',
                    message: `Are you sure you want to assign ${selectedModules.size} module(s) to this level?`,
                    confirmText: 'Assign Modules',
                    cancelText: 'Cancel'
                });
                
                if (confirmed) {
                    await performBulkAssignment();
                    
                    // Refresh the modal display to reflect changes
                    renderEnhancedModuleList();
                    updateModuleStatistics();
                    updateBulkActionButtons();
                    
                    // Close modal and clear selection
                    moduleSelectorModal.classList.add('hidden');
                    selectedModules.clear();
                }
            });

            removeSelectedModules.addEventListener('click', async () => {
                if (selectedModules.size === 0) return;
                
                const confirmed = await showConfirmationModal({
                    type: 'danger',
                    title: 'Remove Modules',
                    message: `Are you sure you want to remove ${selectedModules.size} module(s) from this level?`,
                    confirmText: 'Remove Modules',
                    cancelText: 'Cancel'
                });
                
                if (confirmed) {
                    await performBulkRemoval();
                    renderEnhancedModuleList();
                    updateModuleStatistics();
                    autoSaveModuleAssignments();
                }
            });
            
            // Bulk selection buttons
            document.getElementById('selectAllModules').addEventListener('click', () => {
                const availableCheckboxes = document.querySelectorAll('.module-checkbox:not(:disabled)');
                const currentlyAssignedIds = getCurrentModulesForLevel().map(m => m.id);
                
                availableCheckboxes.forEach(checkbox => {
                    const moduleId = parseInt(checkbox.value);
                    if (!currentlyAssignedIds.includes(moduleId)) {
                        checkbox.checked = true;
                        selectedModules.add(moduleId);
                    }
                });
                updateBulkActionButtons();
            });
            
            document.getElementById('deselectAllModules').addEventListener('click', () => {
                document.querySelectorAll('.module-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                selectedModules.clear();
                updateBulkActionButtons();
            });
            
            // Module status filter
            document.getElementById('moduleStatusFilter').addEventListener('change', (e) => {
                renderEnhancedModuleList();
            });
            
            // Module ownership filter
            document.getElementById('moduleOwnershipFilter').addEventListener('change', (e) => {
                renderEnhancedModuleList();
            });

            pageTypeSelect.addEventListener('change', () => {
                togglePageFields();
            });

            savePageBtn.addEventListener('click', savePage);

            // Save and create buttons
            saveDraftBtn.addEventListener('click', async () => {
                const confirmed = await showConfirmationModal({
                    type: 'info',
                    title: 'Save Draft',
                    message: 'Are you sure you want to save this test as a draft?',
                    confirmText: 'Save Draft',
                    cancelText: 'Cancel'
                });
                
                if (confirmed) {
                    saveTest('draft');
                }
            });

            createTestFinalBtn.addEventListener('click', async () => {
                const confirmed = await showConfirmationModal({
                    type: 'success',
                    title: 'Create Test',
                    message: 'Are you sure you want to create and publish this test? Published tests will be available to students.',
                    confirmText: 'Create & Publish',
                    cancelText: 'Cancel'
                });
                
                if (confirmed) {
                    saveTest('published');
                }
            });

            // Step indicator functions
            function updateStepIndicator() {
                const stepItems = document.querySelectorAll('.step-indicator-item');
                stepItems.forEach((item, index) => {
                    const stepNumber = index + 1;
                    item.classList.remove('active', 'completed');
                    
                    if (stepNumber === currentStep) {
                        item.classList.add('active');
                    } else if (stepNumber < currentStep) {
                        item.classList.add('completed');
                    }
                });
            }

            function updateStepContent() {
                const stepContents = document.querySelectorAll('.step-content');
                stepContents.forEach((content, index) => {
                    content.classList.remove('active');
                    if (index + 1 === currentStep) {
                        content.classList.add('active');
                    }
                });
            }

            function updateNavigationButtons() {
                // Show/hide Previous button
                prevStepBtn.style.display = currentStep > 1 ? 'block' : 'none';
                
                // Show/hide Next button (only on steps 1-3)
                nextStepBtn.style.display = currentStep < 4 ? 'block' : 'none';
                
                // Show/hide Save Draft and Create Test buttons (only on step 4)
                saveDraftBtn.style.display = currentStep === 4 ? 'block' : 'none';
                createTestFinalBtn.style.display = currentStep === 4 ? 'block' : 'none';
            }

            function validateCurrentStep() {
                // No validation - allow proceeding through all steps
                return true;
            }

            function resetCreateTestForm() {
                currentStep = 1;
                questions = [];
                pages = [];
                modules = {};
                updateStepIndicator();
                updateStepContent();
                updateNavigationButtons();
                
                // Reset form fields
                document.getElementById('testTitle').value = '';
                
                // Clear lists
                document.getElementById('questionsList').innerHTML = '';
                document.getElementById('pagesList').innerHTML = '';
                
                // Reset unsaved changes state
                markAsSaved();
            }

            function resetQuestionForm() {
                document.getElementById('questionText').value = '';
                document.getElementById('questionDifficulty').value = '';
                document.getElementById('questionPoints').value = '1';
                
                // Reset choices
                const choicesList = document.getElementById('choicesList');
                choicesList.innerHTML = '';
                addChoice(); // Add one default choice
                addChoice(); // Add second choice
                
                // Reset editing state
                window.currentEditingQuestionIndex = null;
                
                // Reset modal title
                document.querySelector('#questionModal h2').textContent = 'Add Question';
            }

            function addChoice() {
                const choicesList = document.getElementById('choicesList');
                const choiceIndex = choicesList.children.length;
                const letter = String.fromCharCode(65 + choiceIndex); // A, B, C, D, etc.
                
                const choiceDiv = document.createElement('div');
                choiceDiv.className = 'choice-input-group';
                choiceDiv.innerHTML = `
                    <input type="radio" name="correct_choice" value="${choiceIndex}" class="choice-radio">
                    <span class="choice-letter-label">${letter}.</span>
                    <input type="text" name="choice_text" placeholder="Enter choice text..." class="flex-1">
                    <button type="button" class="remove-choice-btn" onclick="removeChoice(this)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;
                
                choicesList.appendChild(choiceDiv);
            }

            function removeChoice(button) {
                const choicesList = document.getElementById('choicesList');
                if (choicesList.children.length > 2) {
                    button.parentElement.remove();
                    // Update letter labels after removal
                    updateChoiceLabels();
                }
                // Allow removing even if only 2 choices remain
            }

            function updateChoiceLabels() {
                const choicesList = document.getElementById('choicesList');
                const choiceGroups = choicesList.children;
                
                for (let i = 0; i < choiceGroups.length; i++) {
                    const letterLabel = choiceGroups[i].querySelector('.choice-letter-label');
                    const radioButton = choiceGroups[i].querySelector('.choice-radio');
                    
                    if (letterLabel) {
                        const letter = String.fromCharCode(65 + i); // A, B, C, D, etc.
                        letterLabel.textContent = letter + '.';
                    }
                    
                    if (radioButton) {
                        radioButton.value = i;
                    }
                }
            }

            function saveQuestion() {
                const questionText = document.getElementById('questionText').value.trim();
                const difficulty = document.getElementById('questionDifficulty').value;
                const points = parseInt(document.getElementById('questionPoints').value) || 1;
                
                // Get choices
                const choices = [];
                const choiceInputs = document.querySelectorAll('#choicesList .choice-input-group');
                let correctChoiceIndex = -1;
                let hasCorrectAnswer = false;
                
                choiceInputs.forEach((choiceDiv, index) => {
                    const radio = choiceDiv.querySelector('input[type="radio"]');
                    const textInput = choiceDiv.querySelector('input[type="text"]');
                    const text = textInput.value.trim();
                    
                    if (text) {
                        const isCorrect = radio.checked;
                        choices.push({
                            id: index + 1,
                            text: text,
                            is_correct: isCorrect
                        });
                        
                        if (isCorrect) {
                            correctChoiceIndex = index;
                            hasCorrectAnswer = true;
                        }
                    }
                });

                // Validate form
                const validation = validateQuestionForm(questionText, choices, hasCorrectAnswer, false);
                
                // Additional validation for difficulty and points
                const errors = [...validation.errors];
                if (!difficulty) {
                    errors.push('Please select a difficulty level');
                }
                if (points < 1 || points > 100) {
                    errors.push('Points must be between 1 and 100');
                }
                
                if (errors.length > 0) {
                    showValidationErrors(errors);
                    return;
                }

                // Use default values if fields are empty (fallback)
                const finalQuestionText = questionText || 'Untitled Question';
                const finalDifficulty = difficulty || 'beginner';
                const finalChoices = choices.length > 0 ? choices : [
                    { id: 1, text: 'Option A', is_correct: true },
                    { id: 2, text: 'Option B', is_correct: false }
                ];

                // Check if we're editing an existing question
                if (window.currentEditingQuestionIndex !== undefined && window.currentEditingQuestionIndex !== null) {
                    // Update existing question
                    const questionIndex = window.currentEditingQuestionIndex;
                    questions[questionIndex] = {
                        id: questions[questionIndex].id, // Keep the original ID
                        question_text: finalQuestionText,
                        difficulty_level: finalDifficulty,
                        points: points,
                        choices: finalChoices
                    };
                    
                    // Clear the editing index
                    window.currentEditingQuestionIndex = null;
                    
                    // Reset modal title
                    document.querySelector('#questionModal h2').textContent = 'Add Question';
                    
                    showNotification('Question updated successfully!', 'success');
                } else {
                    // Create new question
                const question = {
                    id: questions.length + 1,
                    question_text: finalQuestionText,
                    difficulty_level: finalDifficulty,
                    points: points,
                    choices: finalChoices
                };
                questions.push(question);
                showNotification('Question added successfully!', 'success');
            }

                renderQuestions();
            markAsSaved(); // Mark as saved when question is added/updated
                questionModal.classList.add('hidden');
            }



            function saveTest(status) {
                // Check create permission
                if (!window.placementTestPermissions.canCreate) {
                    showNotification('You do not have permission to create placement tests.', 'error');
                    return;
                }
                
                const title = document.getElementById('testTitle').value.trim();
                
                // Basic validation
                if (!title) {
                    alert('Please enter a test title');
                    return;
                }

                // Create test data object
                const testData = {
                    title: title,
                    status: status,
                    questions: questions,
                    pages: pages,
                    modules: modules,
                    design_settings: {
                        header_color: '#1f2937',
                        header_text_color: '#ffffff',
                        background_color: '#f5f5f5',
                        accent_color: '#dc2626',
                        font_family: 'Inter',
                        button_color: '#dc2626'
                    }
                };
                
                // Debug logging for page ordering before saving
                console.log('Saving pages with order:');
                pages.forEach((page, index) => {
                    console.log(`Page ${index + 1}: ${page.type} - "${page.title}" (order: ${page.order})`);
                });
                
                // Debug logging for modules before saving
                console.log('Saving modules:', modules);
                
                // Add test ID if updating
                if (currentTestId) {
                    testData.test_id = currentTestId;
                    // Keep the original status (draft) for regular saves
                }

                console.log('Saving test:', testData);
                
                // Show loading state
                const saveBtn = status === 'published' ? createTestFinalBtn : saveDraftBtn;
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...';
                saveBtn.disabled = true;
                
                // Send data to server
                fetch('api/save_placement_test.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(testData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showNotification(data.message, 'success');
                        
                        // Mark as saved
                        markAsSaved();
                        
                        // Close modal
                        createTestModal.classList.add('hidden');
                        
                        // Update preview button to be clickable
                        updatePreviewButton(true, data.data.test_id);
                        
                        // Reset form
                        resetCreateTestForm();
                        
                        // Reload dashboard data
                        loadDashboardData();
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error saving test:', error);
                    showNotification('Error saving test: ' + error.message, 'error');
                })
                .finally(() => {
                    // Restore button state
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                });
            }

            // Function to update preview button state
            function updatePreviewButton(isEnabled, testId = null) {
                const previewBtn = document.getElementById('previewTestBtn');
                if (isEnabled && testId) {
                    // Make it clickable
                    previewBtn.className = 'btn-primary flex items-center';
                    previewBtn.innerHTML = `
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        Preview Placement Test
                    `;
                    previewBtn.onclick = function() {
                        // Check preview permission
                        if (!window.placementTestPermissions.canPreview) {
                            showNotification('You do not have permission to preview placement tests.', 'error');
                            return;
                        }
                        // Open preview in new window
                        window.open(`placement_test_student.php?test_id=${testId}&preview=true`, '_blank');
                    };
                } else {
                    // Make it disabled
                    previewBtn.className = 'inline-flex items-center px-6 py-3 bg-gray-400 text-white rounded-lg shadow-lg cursor-not-allowed opacity-60';
                    previewBtn.innerHTML = `
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        Save Test First
                    `;
                    previewBtn.onclick = null;
                }
            }

            // Page management functions
            function openPageModal(type) {
                pageModal.classList.remove('hidden');
                pageTypeSelect.value = type;
                togglePageFields();
                resetPageForm();
                
                // Initialize TinyMCE for content pages
                if (type === 'content') {
                    setTimeout(() => {
                        initializeTinyMCE();
                    }, 100);
                } else if (type === 'question') {
                    // Update question dropdown for question pages
                    setTimeout(() => {
                        updateQuestionDropdown();
                    }, 100);
                }
            }

            // (togglePageFields is implemented globally further down)

            // resetPageForm is defined globally further down so it's available
            // to both create and edit flows. See the global definition below.

            // TinyMCE initialization function
            function initializeTinyMCE() {
                // Remove existing TinyMCE instance if it exists
                if (tinymce.get('pageContentEditor')) {
                    tinymce.get('pageContentEditor').remove();
                }

                tinymce.init({
                    selector: '#pageContentEditor',
                    height: 300,
                    menubar: false,
                    base_url: '../../assets/tinymce/tinymce/js/tinymce',
                    suffix: '.min',
                    license_key: 'gpl',
                    promotion: false,
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                        'insertdatetime', 'media', 'table', 'help', 'wordcount'
                    ],
                    toolbar: 'undo redo | blocks | ' +
                        'bold italic underline strikethrough | ' +
                        'alignleft aligncenter alignright alignjustify | ' +
                        'bullist numlist outdent indent | link image | ' +
                        'removeformat | help',
                    textcolor_map: [
                        "000000", "Black",
                        "993300", "Burnt orange",
                        "333300", "Dark olive",
                        "003300", "Dark green",
                        "003366", "Dark azure",
                        "000080", "Navy Blue",
                        "333399", "Indigo",
                        "333333", "Very dark gray",
                        "800000", "Maroon",
                        "FF6600", "Orange",
                        "808080", "Gray",
                        "FF0000", "Red",
                        "FF9900", "Amber",
                        "99CC00", "Yellow green",
                        "339966", "Sea green",
                        "33CCCC", "Turquoise",
                        "3366FF", "Royal blue",
                        "800080", "Purple",
                        "999999", "Medium gray",
                        "FF00FF", "Magenta",
                        "FFCC00", "Gold",
                        "FFFF00", "Yellow",
                        "00FF00", "Lime",
                        "00FFFF", "Aqua",
                        "00CCFF", "Sky blue",
                        "9933FF", "Light purple",
                        "FFFFFF", "White",
                        "FF99CC", "Pink",
                        "FFCC99", "Peach",
                        "FFFF99", "Light yellow",
                        "CCFFCC", "Light green",
                        "CCFFFF", "Light cyan",
                        "99CCFF", "Light sky blue",
                        "CC99FF", "Light purple"
                    ],
                    content_style: 'body { font-family: Inter, "Noto Sans JP", sans-serif; font-size: 14px; }',
                    setup: function (editor) {
                        editor.on('change', function () {
                            // Sync content with hidden textarea
                            document.getElementById('pageContent').value = editor.getContent();
                            // Mark as changed
                            markAsChanged();
                        });
                    }
                    ,
                    // Ensure we set initial content once the editor instance is ready
                    init_instance_callback: function (editor) {
                        try {
                            const content = document.getElementById('pageContent') ? document.getElementById('pageContent').value : '';
                            if (content) {
                                editor.setContent(content);
                            } else {
                                // ensure empty editor is clean
                                editor.setContent('');
                            }
                        } catch (e) {
                            console.warn('init_instance_callback: failed to set initial content', e);
                        }
                    }
                });
            }

            // Sync TinyMCE content before form submission
            function syncTinyMCEContent() {
                if (tinymce.get('pageContentEditor')) {
                    const content = tinymce.get('pageContentEditor').getContent();
                    document.getElementById('pageContent').value = content;
                }
            }

            // Initialize TinyMCE specifically for the Page modal in edit mode
            // This wraps the generic initializeTinyMCE and ensures the editor
            // has the current page content loaded from the hidden textarea.
            function initializePageContentEditor() {
                // Remove any existing instance first
                try {
                    if (tinymce.get('pageContentEditor')) {
                        tinymce.get('pageContentEditor').remove();
                    }
                } catch (e) {
                    console.warn('Error removing existing TinyMCE instance:', e);
                }

                // Initialize the editor using the shared initializer. The
                // TinyMCE init callback (init_instance_callback) will set content
                // from the hidden textarea once the editor is ready.
                initializeTinyMCE();
            }

            // Ensure the TinyMCE instance exists; if not, attempt a re-init.
            function ensurePageEditorInitialized(retries = 4, delay = 200) {
                // Try to detect editor immediately and then retry a few times
                (function tryInit(remaining) {
                    try {
                        if (typeof tinymce !== 'undefined' && tinymce.get('pageContentEditor')) {
                            console.log('ensurePageEditorInitialized: editor ready');
                            return;
                        }
                    } catch (e) {
                        // ignore and retry
                    }

                    if (remaining <= 0) {
                        console.error('ensurePageEditorInitialized: failed to initialize editor');
                        return;
                    }

                    // Attempt to initialize and check again
                    try {
                        if (typeof initializeTinyMCE === 'function') initializeTinyMCE();
                    } catch (e) {
                        console.warn('ensurePageEditorInitialized: initializeTinyMCE threw', e);
                    }

                    setTimeout(() => tryInit(remaining - 1), delay);
                })(retries);
            }

            // Expose initialization helpers and provide a robust wrapper
            // so edit flows can call a single, safe entrypoint.
            window.ensurePageEditorInitialized = ensurePageEditorInitialized;

            // If initializePageContentEditor isn't defined elsewhere, provide
            // a fallback that removes any existing instance and calls the
            // generic initializer.
            if (typeof initializePageContentEditor !== 'function') {
                window.initializePageContentEditor = function () {
                    try {
                        if (typeof tinymce !== 'undefined' && tinymce.get('pageContentEditor')) {
                            tinymce.get('pageContentEditor').remove();
                        }
                    } catch (e) {
                        console.warn('initializePageContentEditor (fallback): remove failed', e);
                    }

                    if (typeof initializeTinyMCE === 'function') {
                        initializeTinyMCE();
                    }
                };
            } else {
                // also expose the existing implementation
                window.initializePageContentEditor = initializePageContentEditor;
            }

            // Ensure initializeTinyMCE is exposed as well
            if (typeof initializeTinyMCE === 'function') window.initializeTinyMCE = initializeTinyMCE;

            // A single safe wrapper that edit flows should call. It ensures
            // the hidden textarea is set (editPage already sets it) and
            // initializes TinyMCE defensively.
            window.initPageEditor = function () {
                try {
                    if (typeof window.initializePageContentEditor === 'function') {
                        window.initializePageContentEditor();
                    } else if (typeof window.initializeTinyMCE === 'function') {
                        window.initializeTinyMCE();
                    }
                } catch (e) {
                    console.error('initPageEditor failed', e);
                }
                // Start retries to ensure instance ready
                if (typeof window.ensurePageEditorInitialized === 'function') {
                    window.ensurePageEditorInitialized(6, 250);
                }
            };

            // Global resetPageForm used by both create and edit flows.
            function resetPageForm() {
                // Reset modal title and type defaults
                const pageModalTitle = document.getElementById('pageModalTitle');
                if (pageModalTitle) pageModalTitle.textContent = 'Add Page';

                const pageTypeEl = document.getElementById('pageType');
                if (pageTypeEl) pageTypeEl.value = 'content';

                const pageTitle = document.getElementById('pageTitle');
                if (pageTitle) pageTitle.value = '';

                const pageContent = document.getElementById('pageContent');
                if (pageContent) pageContent.value = '';

                const pageImage = document.getElementById('pageImage');
                if (pageImage) pageImage.value = '';

                const pageQuestion = document.getElementById('pageQuestion');
                if (pageQuestion) pageQuestion.value = '';

                // Remove any current image display
                const currentImageDiv = document.querySelector('.current-image');
                if (currentImageDiv) currentImageDiv.remove();

                // Reset visibility of fields
                const contentFieldsEl = document.getElementById('contentFields');
                const questionFieldsEl = document.getElementById('questionFields');
                if (contentFieldsEl) contentFieldsEl.style.display = 'block';
                if (questionFieldsEl) questionFieldsEl.style.display = 'none';

                // Clear question checkbox selections
                const questionCheckboxes = document.querySelectorAll('#questionCheckboxes input[type="checkbox"]');
                questionCheckboxes.forEach(cb => cb.checked = false);

                // Clear any validation errors inside the modal
                const errorElements = document.querySelectorAll('#pageModal .error-message');
                errorElements.forEach(el => el.remove());

                // Reset form validation state
                const formInputs = document.querySelectorAll('#pageModal input, #pageModal select, #pageModal textarea');
                formInputs.forEach(input => {
                    input.classList.remove('error', 'invalid');
                    input.removeAttribute('aria-invalid');
                });

                // Reset editing state
                window.currentEditingPageIndex = undefined;
                window.editPageOriginalData = null;

                // Reset TinyMCE content if present
                try {
                    if (tinymce.get('pageContentEditor')) {
                        tinymce.get('pageContentEditor').setContent('');
                    }
                } catch (e) {
                    console.warn('resetPageForm: TinyMCE instance not available', e);
                }

                console.log('Page form reset completed');
            }

            // Global togglePageFields to be used by both Create and Edit flows
            function togglePageFields() {
                const pageTypeEl = document.getElementById('pageType');
                const pageType = pageTypeEl ? pageTypeEl.value : 'content';
                const pageTitleField = document.getElementById('pageTitleField');
                const contentFieldsEl = document.getElementById('contentFields');
                const questionFieldsEl = document.getElementById('questionFields');

                if (pageType === 'content') {
                    if (pageTitleField) pageTitleField.style.display = 'block';
                    if (contentFieldsEl) contentFieldsEl.style.display = 'block';
                    if (questionFieldsEl) questionFieldsEl.style.display = 'none';

                    // Initialize appropriate TinyMCE instance depending on edit/create mode
                    setTimeout(() => {
                        // If editing an existing page, use the edit initializer which loads content
                        if (typeof window.currentEditingPageIndex !== 'undefined' && window.currentEditingPageIndex !== null) {
                            if (typeof initializePageContentEditor === 'function') {
                                initializePageContentEditor();
                            } else if (typeof initializeTinyMCE === 'function') {
                                initializeTinyMCE();
                            }
                        } else {
                            if (typeof initializeTinyMCE === 'function') {
                                initializeTinyMCE();
                            }
                        }
                    }, 100);
                } else {
                    if (pageTitleField) pageTitleField.style.display = 'none';
                    if (contentFieldsEl) contentFieldsEl.style.display = 'none';
                    if (questionFieldsEl) questionFieldsEl.style.display = 'block';

                    // Remove any existing TinyMCE instance when switching to question mode
                    try {
                        if (tinymce.get('pageContentEditor')) {
                            tinymce.get('pageContentEditor').remove();
                        }
                    } catch (e) {
                        console.warn('togglePageFields: TinyMCE remove failed', e);
                    }

                    setTimeout(() => {
                        if (typeof updateQuestionDropdown === 'function') updateQuestionDropdown();
                    }, 100);
                }
            }

            // Expose globally
            window.togglePageFields = togglePageFields;

            function updateQuestionDropdown() {
                const questionCheckboxes = document.getElementById('questionCheckboxes');
                questionCheckboxes.innerHTML = '';

                // In edit mode we should prefer the test's questions (editQuestions)
                // otherwise fall back to the global questions list.
                const sourceQuestions = (typeof editQuestions !== 'undefined' && Array.isArray(editQuestions) && editQuestions.length > 0)
                    ? editQuestions
                    : (typeof questions !== 'undefined' && Array.isArray(questions) ? questions : []);

                if (sourceQuestions.length === 0) {
                    questionCheckboxes.innerHTML = '<p class="text-gray-500 text-center py-4">No questions available</p>';
                    return;
                }

                sourceQuestions.forEach((question, index) => {
                    const checkboxContainer = document.createElement('div');
                    checkboxContainer.className = 'flex items-center space-x-3 py-2 hover:bg-gray-100 rounded px-2';
                    
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'question_checkbox';
                    checkbox.id = `question_${question.id}`;
                    checkbox.value = question.id;
                    checkbox.className = 'question-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500';
                    checkbox.addEventListener('change', updateSelectedQuestions);
                    
                    const label = document.createElement('label');
                    label.htmlFor = `question_${question.id}`;
                    label.className = 'flex-1 text-sm text-gray-700 cursor-pointer';
                    label.textContent = `Question ${index + 1}: ${question.question_text.substring(0, 60)}${question.question_text.length > 60 ? '...' : ''}`;
                    
                    checkboxContainer.appendChild(checkbox);
                    checkboxContainer.appendChild(label);
                    questionCheckboxes.appendChild(checkboxContainer);
                });
                
                // Add select all functionality
                setupSelectAllFunctionality();
            }

            // Backwards-compatible wrapper: some code calls populateQuestionCheckboxes
            // while the implementation is named updateQuestionDropdown. Provide the
            // wrapper so both call-sites work.
            function populateQuestionCheckboxes() {
                if (typeof updateQuestionDropdown === 'function') updateQuestionDropdown();
            }

            window.populateQuestionCheckboxes = populateQuestionCheckboxes;
            
            function setupSelectAllFunctionality() {
                const selectAllBtn = document.getElementById('selectAllQuestions');
                const checkboxes = document.querySelectorAll('.question-checkbox');
                
                selectAllBtn.addEventListener('click', function() {
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = !allChecked;
                    });
                    
                    selectAllBtn.textContent = allChecked ? 'Select All' : 'Deselect All';
                    updateSelectedQuestions();
                });
                
                // Update select all button text based on current state
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const checkedCount = document.querySelectorAll('.question-checkbox:checked').length;
                        const totalCount = checkboxes.length;
                        
                        if (checkedCount === 0) {
                            selectAllBtn.textContent = 'Select All';
                        } else if (checkedCount === totalCount) {
                            selectAllBtn.textContent = 'Deselect All';
                        } else {
                            selectAllBtn.textContent = 'Select All';
                        }
                    });
                });
            }
            
            function updateSelectedQuestions() {
                const selectedCheckboxes = document.querySelectorAll('.question-checkbox:checked');
                const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
                document.getElementById('pageQuestion').value = selectedIds.join(',');
            }

// Fix the savePage function (around line 1100)
async function savePage() {
    const confirmed = await showConfirmationModal({
        type: 'info',
        title: 'Save Page',
        message: 'Are you sure you want to save this page?',
        confirmText: 'Save Page',
        cancelText: 'Continue Editing'
    });
    
    if (!confirmed) return;

    // Sync TinyMCE content before saving
    syncTinyMCEContent();
    
    const pageType = (document.getElementById('pageType').value || '').toString().trim().toLowerCase();
    const title = document.getElementById('pageTitle').value.trim();
    const content = document.getElementById('pageContent').value.trim();
    const image = document.getElementById('pageImage').files[0];
    const questionIds = document.getElementById('pageQuestion').value;

    // Get selected questions for question pages
    let selectedQuestions = [];
    if (pageType === 'question') {
        const questionCheckboxes = document.querySelectorAll('#questionCheckboxes input[type="checkbox"]:checked');
        selectedQuestions = Array.from(questionCheckboxes).map(cb => parseInt(cb.value));
    }

    // Validate the form
    const validation = validatePageForm(title, content, pageType, selectedQuestions, window.currentEditingPageIndex !== undefined);
    
    if (!validation.isValid) {
        showValidationErrors(validation.errors);
        return;
    }

    let imageFilename = null;
    
    // Upload image if provided
    if (image) {
        try {
            const formData = new FormData();
            formData.append('image', image);
            
            const response = await fetch('api/upload_image.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                imageFilename = result.filename;
            } else {
                showNotification('Failed to upload image: ' + result.message, 'error');
                return;
            }
        } catch (error) {
            console.error('Error uploading image:', error);
            showNotification('Error uploading image. Please try again.', 'error');
            return;
        }
    }

    // **KEY FIX: Determine which array to use based on modal context**
    const targetArray = (window.editCurrentTestId || isEditMode) ? editPages : pages;
    const editingIndex = window.currentEditingPageIndex;

    console.log('Saving page:', {
        isEditMode: isEditMode,
        editCurrentTestId: window.editCurrentTestId,
        editingIndex: editingIndex,
        targetArray: targetArray === editPages ? 'editPages' : 'pages',
        arrayLength: targetArray.length
    });

    if (editingIndex !== undefined && editingIndex !== null && targetArray[editingIndex]) {
        // **Update existing page**
        const existingPage = targetArray[editingIndex];
        existingPage.type = pageType;
        existingPage.title = title || (pageType === 'content' ? 'Untitled Page' : '');
        existingPage.content = content;
        
        // Only update image if a new one was uploaded
        if (imageFilename) {
            existingPage.image = imageFilename;
        }
        
        existingPage.question_ids = selectedQuestions;
        existingPage.updated_at = new Date().toISOString();

        showNotification('Page updated successfully!', 'success');
        console.log('Updated page:', existingPage);
    } else {
        // **Create new page**
        const newPage = {
            id: targetArray.length + 1,
            type: pageType,
            title: title || (pageType === 'content' ? 'Untitled Page' : ''),
            content: content,
            image: imageFilename,
            question_ids: selectedQuestions,
            order: targetArray.length + 1,
            created_at: new Date().toISOString()
        };

        targetArray.push(newPage);
        showNotification('Page created successfully!', 'success');
        console.log('Created new page:', newPage);
    }

    // **Render using the correct renderer**
    if (targetArray === editPages) {
        renderEditPages();
    } else {
        renderPages();
    }
    
    markAsChanged();
    
    // Clean up TinyMCE before closing
    if (tinymce.get('pageContentEditor')) {
        tinymce.get('pageContentEditor').remove();
    }
    
    // Reset form and close modal
    resetPageForm();
    document.getElementById('pageModal').classList.add('hidden');
}

// Fix the editPage function (around line 3800)
async function editPage(index) {
    const page = pages[index];
    if (!page) {
        showNotification('Page not found', 'error');
        return;
    }

    const editConfirmed = await showConfirmationModal({
        type: 'info',
        title: 'Edit Page',
        message: `Are you sure you want to edit "${page.title || 'Untitled Page'}"?`,
        confirmText: 'Edit Page',
        cancelText: 'Cancel'
    });

    if (!editConfirmed) return;

    // **Store original data for change detection**
    window.editPageOriginalData = {
        pageTitle: page.title || '',
        pageType: page.type || 'content',
        pageContent: page.content || '',
        selectedQuestions: page.question_ids ? [...page.question_ids] : []
    };
    
    // **Store the editing index**
    window.currentEditingPageIndex = index;
    
    // Set the modal title
    document.getElementById('pageModalTitle').textContent = `Edit Page: ${window.editPageOriginalData.pageTitle}`;
    
    // Populate the form
    document.getElementById('pageType').value = window.editPageOriginalData.pageType;
    document.getElementById('pageTitle').value = window.editPageOriginalData.pageTitle;
    document.getElementById('pageContent').value = window.editPageOriginalData.pageContent;
    
    // Handle content vs question fields
    if (window.editPageOriginalData.pageType === 'content') {
        document.getElementById('contentFields').style.display = 'block';
        document.getElementById('questionFields').style.display = 'none';
        
        // Show current image if exists
        if (page.image) {
            const imageInput = document.getElementById('pageImage');
            if (imageInput && imageInput.parentNode) {
                const existing = imageInput.parentNode.querySelector('.current-image');
                if (existing) existing.remove();
                
                const currentImageDiv = document.createElement('div');
                currentImageDiv.className = 'current-image text-sm text-gray-600 mt-1';
                currentImageDiv.innerHTML = `Current image: ${page.image}`;
                imageInput.parentNode.appendChild(currentImageDiv);
            }
        }
        
        // **Initialize TinyMCE for content editing**
        setTimeout(() => {
            if (typeof window.initPageEditor === 'function') {
                window.initPageEditor();
            } else if (typeof initializePageContentEditor === 'function') {
                initializePageContentEditor();
            }
        }, 100);
    } else if (window.editPageOriginalData.pageType === 'question') {
        document.getElementById('contentFields').style.display = 'none';
        document.getElementById('questionFields').style.display = 'block';
        
        // Populate question checkboxes
        setTimeout(() => {
            populateQuestionCheckboxes();
            
            // Set selected questions after a delay
            setTimeout(() => {
                window.editPageOriginalData.selectedQuestions.forEach(questionId => {
                    const checkbox = document.querySelector(`input[name="question_checkbox"][value="${questionId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }, 200);
        }, 100);
    }
    
    // Show the modal
    document.getElementById('pageModal').classList.remove('hidden');
    showNotification('Page editor opened', 'success');
}

// Fix the editEditPage function (around line 3900)
async function editEditPage(index) {
    const page = editPages[index];
    if (!page) {
        showNotification('Page not found', 'error');
        return;
    }

    const editConfirmed = await showConfirmationModal({
        type: 'info',
        title: 'Edit Page',
        message: `Are you sure you want to edit "${page.title || 'Untitled Page'}"?`,
        confirmText: 'Edit Page',
        cancelText: 'Cancel'
    });

    if (!editConfirmed) return;

    // **Set edit mode flag**
    isEditMode = true;
    
    // **Store original data**
    window.editPageOriginalData = {
        pageTitle: page.title || '',
        pageType: page.type || 'content',
        pageContent: page.content || '',
        selectedQuestions: page.question_ids ? [...page.question_ids] : []
    };
    
    // **Store the editing index**
    window.currentEditingPageIndex = index;
    
    // Populate form
    document.getElementById('pageModalTitle').textContent = `Edit Page: ${window.editPageOriginalData.pageTitle}`;
    document.getElementById('pageType').value = window.editPageOriginalData.pageType;
    document.getElementById('pageTitle').value = window.editPageOriginalData.pageTitle;
    document.getElementById('pageContent').value = window.editPageOriginalData.pageContent;
    
    // Handle fields based on page type
    if (window.editPageOriginalData.pageType === 'content') {
        document.getElementById('contentFields').style.display = 'block';
        document.getElementById('questionFields').style.display = 'none';
        
        // Show current image
        if (page.image) {
            const imageInput = document.getElementById('pageImage');
            if (imageInput && imageInput.parentNode) {
                const existing = imageInput.parentNode.querySelector('.current-image');
                if (existing) existing.remove();
                
                const currentImageDiv = document.createElement('div');
                currentImageDiv.className = 'current-image text-sm text-gray-600 mt-1';
                currentImageDiv.innerHTML = `Current image: ${page.image}`;
                imageInput.parentNode.appendChild(currentImageDiv);
            }
        }
        
        // Initialize TinyMCE
        setTimeout(() => {
            if (typeof window.initPageEditor === 'function') {
                window.initPageEditor();
            } else if (typeof initializePageContentEditor === 'function') {
                initializePageContentEditor();
            }
        }, 100);
    } else {
        document.getElementById('contentFields').style.display = 'none';
        document.getElementById('questionFields').style.display = 'block';
        
        // Populate questions
        setTimeout(() => {
            populateQuestionCheckboxes();
            setTimeout(() => {
                window.editPageOriginalData.selectedQuestions.forEach(questionId => {
                    const checkbox = document.querySelector(`input[name="question_checkbox"][value="${questionId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }, 200);
        }, 100);
    }
    
    document.getElementById('pageModal').classList.remove('hidden');
    showNotification('Page editor opened', 'success');
}

// Add this helper function to improve TinyMCE sync
function syncTinyMCEContent() {
    try {
        const editor = tinymce.get('pageContentEditor');
        if (editor) {
            const content = editor.getContent();
            document.getElementById('pageContent').value = content;
            console.log('TinyMCE content synced:', content.substring(0, 100) + '...');
        }
    } catch (e) {
        console.warn('TinyMCE sync failed:', e);
    }
}

            function resetPageForm() {
                // Reset form fields
                document.getElementById('pageModalTitle').textContent = 'Add Page';
                document.getElementById('pageType').value = 'content';
                document.getElementById('pageTitle').value = '';
                document.getElementById('pageContent').value = '';
                document.getElementById('pageImage').value = '';
                document.getElementById('pageQuestion').value = '';
                
                // Clear current image display
                const currentImageDiv = document.querySelector('.current-image');
                if (currentImageDiv) {
                    currentImageDiv.remove();
                }
                
                // Reset field visibility
                document.getElementById('contentFields').style.display = 'block';
                document.getElementById('questionFields').style.display = 'none';
                
                // Clear question selections
                const questionCheckboxes = document.querySelectorAll('#questionCheckboxes input[type="checkbox"]');
                questionCheckboxes.forEach(cb => cb.checked = false);
                
                // Clear any validation errors
                const errorElements = document.querySelectorAll('#pageModal .error-message');
                errorElements.forEach(element => element.remove());
                
                // Reset form validation state
                const formInputs = document.querySelectorAll('#pageModal input, #pageModal select, #pageModal textarea');
                formInputs.forEach(input => {
                    input.classList.remove('error', 'invalid');
                    input.removeAttribute('aria-invalid');
                });
                
                // Clear editing state
                window.currentEditingPageIndex = undefined;
                window.editPageOriginalData = null;
                
                // Log reset for debugging
                console.log('Page form reset completed');
            }


            // Module assignment functions
            function openModuleSelector(level) {
                currentLevel = level;
                isEditMode = false;
                selectedModules.clear();
                
                // Map level names for display
                const levelNames = {
                    'beginner': 'Beginner',
                    'intermediate': 'Intermediate Beginner', 
                    'advanced': 'Advanced Beginner'
                };
                
                document.getElementById('selectedLevel').textContent = levelNames[level] || level.charAt(0).toUpperCase() + level.slice(1);
                
                loadAvailableCourses();
                document.getElementById('moduleSelectorModal').classList.remove('hidden');
                
                // Reset UI state
                updateBulkActionButtons();
                updateModuleStatistics();
            }









            


            // Initialize preview button as disabled
            updatePreviewButton(false);

            // Make functions globally available
            window.removeChoice = removeChoice;
            window.removeQuestion = removeQuestion;
            window.editQuestion = editQuestion;
            window.updateChoiceLabels = updateChoiceLabels;
            window.movePageUp = movePageUp;
            window.movePageDown = movePageDown;
            window.removePage = removePage;
            window.editPage = editPage;
            window.openModuleSelector = openModuleSelector;
            window.removeModuleFromLevel = removeModuleFromLevel;
            window.updatePreviewButton = updatePreviewButton;
            window.editTest = editTest;
            window.populateEditTestModal = populateEditTestModal;
            window.editEditQuestion = editEditQuestion;
            window.removeEditQuestion = removeEditQuestion;
            window.moveEditPageUp = moveEditPageUp;
            window.moveEditPageDown = moveEditPageDown;
            window.editEditPage = editEditPage;
            window.removeEditPage = removeEditPage;
            window.openEditModuleSelector = openEditModuleSelector;
            window.removeEditModuleFromLevel = removeEditModuleFromLevel;
            window.publishTest = publishTest;
            
            // Enhanced module functions
            window.handleModuleSelection = handleModuleSelection;
            window.renderEnhancedModuleList = renderEnhancedModuleList;
            window.updateBulkActionButtons = updateBulkActionButtons;
            window.updateModuleStatistics = updateModuleStatistics;
            window.renderAssignedModules = renderAssignedModules;
            window.loadAvailableCourses = loadAvailableCourses;
            window.getModuleStatus = getModuleStatus;
            window.getModuleItemClasses = getModuleItemClasses;
            window.getStatusBadge = getStatusBadge;
            window.getCurrentModulesForLevel = getCurrentModulesForLevel;
            window.getInternalLevelName = getInternalLevelName;
            window.filterModulesByStatus = filterModulesByStatus;
            window.updateSelectedModulesFromCheckboxes = updateSelectedModulesFromCheckboxes;
            window.autoSaveModuleAssignments = autoSaveModuleAssignments;
            window.saveModuleAssignments = saveModuleAssignments;
            window.performBulkAssignment = performBulkAssignment;
            window.performBulkRemoval = performBulkRemoval;
            window.unpublishTest = unpublishTest;
            window.archiveTest = archiveTest;
            window.restoreTest = restoreTest;
            window.deleteTest = deleteTest;
            window.showConfirmationModal = showConfirmationModal;
            window.hideConfirmationModal = hideConfirmationModal;
            window.showUnsavedChangesModal = showUnsavedChangesModal;
            window.hideUnsavedChangesModal = hideUnsavedChangesModal;
            window.markAsChanged = markAsChanged;
            window.markAsSaved = markAsSaved;
            window.checkUnsavedChanges = checkUnsavedChanges;
            window.showRefreshModal = showRefreshModal;
            window.hideRefreshModal = hideRefreshModal;
            window.addEditChoiceToModal = addEditChoiceToModal;
            window.removeEditChoiceFromModal = removeEditChoiceFromModal;
            window.updateEditChoiceLabelsInModal = updateEditChoiceLabelsInModal;
            window.resetEditQuestionForm = resetEditQuestionForm;
            window.saveEditQuestionFromModal = saveEditQuestionFromModal;
            window.validateQuestionEditPermissions = validateQuestionEditPermissions;
            window.validateEditQuestionState = validateEditQuestionState;
            window.calculateStringSimilarity = calculateStringSimilarity;
            window.levenshteinDistance = levenshteinDistance;
            window.validatePageForm = validatePageForm;
            window.validatePageFormInputs = validatePageFormInputs;
            window.hasUnsavedPageChanges = hasUnsavedPageChanges;
            window.validatePageEditPermissions = validatePageEditPermissions;
            window.hasUnsavedMainQuestionChanges = hasUnsavedMainQuestionChanges;
            window.resetMainQuestionForm = resetMainQuestionForm;
            window.resetPageForm = resetPageForm;

            // Load initial data
            loadDashboardData();

            // Setup change tracking
            setupChangeTracking();

            // Add browser beforeunload event listener with modern modal
            window.addEventListener('beforeunload', (e) => {
                if (hasUnsavedChanges) {
                    // Prevent the browser's default dialog
                    e.preventDefault();
                    e.returnValue = '';
                    
                    // Show our modern modal instead
                    showUnsavedChangesModal('browser_navigation', 'You are about to leave this page. Your unsaved changes will be lost.');
                    return '';
                }
            });

            // Intercept refresh keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Intercept F5, Ctrl+R, Ctrl+F5
                if ((e.key === 'F5') || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'R')) {
                    if (hasUnsavedChanges) {
                        e.preventDefault();
                        showRefreshModal();
                    }
                }
            });
        }

        // Edit Test Modal Management JavaScript
        function initializeEditTestModal() {
            console.log('initializeEditTestModal called');
            
            // Initialize global variables
            editCurrentStep = 1;
            editQuestions = [];
            editPages = [];
            editModules = {};
            editCurrentTestId = null;

            // Edit modal controls
            const editTestModal = document.getElementById('editTestModal');
            const closeEditModal = document.getElementById('closeEditModal');
            const editPrevBtn = document.getElementById('editPrevBtn');
            const editNextBtn = document.getElementById('editNextBtn');
            const editSaveDraftBtn = document.getElementById('editSaveDraftBtn');
            const editPublishBtn = document.getElementById('editPublishBtn');

            // Edit step elements - assign to global variables
            editStep1 = document.getElementById('editStep1');
            editStep2 = document.getElementById('editStep2');
            editStep3 = document.getElementById('editStep3');
            editStep4 = document.getElementById('editStep4');
            
            console.log('Step elements found:', {
                editStep1: editStep1,
                editStep2: editStep2,
                editStep3: editStep3,
                editStep4: editStep4
            });

            // Edit form elements
            const editTestTitle = document.getElementById('editTestTitle');

            // Edit buttons
            const editAddQuestionBtn = document.getElementById('editAddQuestionBtn');
            const editAddContentPageBtn = document.getElementById('editAddContentPageBtn');
            const editAddQuestionPageBtn = document.getElementById('editAddQuestionPageBtn');

            // Hide the separate "Add Question Page" button in edit modal to match Create modal's single button
            if (editAddQuestionPageBtn) {
                editAddQuestionPageBtn.style.display = 'none';
            }

            // Edit step indicator elements
            const editStepIndicators = document.querySelectorAll('#editTestModal .step-indicator-item');

            // Edit modal event listeners
            closeEditModal.addEventListener('click', () => {
                editTestModal.classList.add('hidden');
                resetEditTestForm();
            });

            // Edit question modal event listeners
            const editQuestionModal = document.getElementById('editQuestionModal');
            const closeEditQuestionModal = document.getElementById('closeEditQuestionModal');
            const editAddChoiceBtn = document.getElementById('editAddChoiceBtn');
            const saveEditQuestionBtn = document.getElementById('saveEditQuestionBtn');
            const cancelEditQuestionBtn = document.getElementById('cancelEditQuestionBtn');

            closeEditQuestionModal.addEventListener('click', async () => {
                if (hasUnsavedQuestionChanges()) {
                    const confirmed = await showConfirmationModal({
                        type: 'warning',
                        title: 'Unsaved Changes',
                        message: 'You have unsaved changes. Are you sure you want to close without saving?',
                        confirmText: 'Close Without Saving',
                        cancelText: 'Continue Editing'
                    });
                    
                    if (!confirmed) return;
                }
                
                editQuestionModal.classList.add('hidden');
                resetEditQuestionForm();
            });

            cancelEditQuestionBtn.addEventListener('click', async () => {
                if (hasUnsavedQuestionChanges()) {
                    const confirmed = await showConfirmationModal({
                        type: 'warning',
                        title: 'Unsaved Changes',
                        message: 'You have unsaved changes. Are you sure you want to cancel without saving?',
                        confirmText: 'Cancel Without Saving',
                        cancelText: 'Continue Editing'
                    });
                    
                    if (!confirmed) return;
                }
                
                editQuestionModal.classList.add('hidden');
                resetEditQuestionForm();
            });

            editAddChoiceBtn.addEventListener('click', () => {
                addEditChoiceToModal();
            });

            saveEditQuestionBtn.addEventListener('click', async () => {
                const confirmed = await showConfirmationModal({
                    type: 'info',
                    title: 'Save Question',
                    message: 'Are you sure you want to save this question?',
                    confirmText: 'Save Question',
                    cancelText: 'Continue Editing'
                });
                
                if (confirmed) {
                    saveEditQuestionFromModal();
                }
            });

            editPrevBtn.addEventListener('click', () => {
                if (editCurrentStep > 1) {
                    editCurrentStep--;
                    updateEditStepIndicator();
                    window.updateEditStepContent();
                    updateEditNavigationButtons();
                }
            });

            editNextBtn.addEventListener('click', () => {
                if (editCurrentStep < 4) {
                    editCurrentStep++;
                    updateEditStepIndicator();
                    window.updateEditStepContent();
                    updateEditNavigationButtons();
                }
            });

            editSaveDraftBtn.addEventListener('click', async () => {
                const confirmed = await showConfirmationModal({
                    type: 'info',
                    title: 'Save Draft',
                    message: 'Are you sure you want to save this test as a draft?',
                    confirmText: 'Save Draft',
                    cancelText: 'Cancel'
                });
                
                if (confirmed) {
                    saveEditTest('draft');
                }
            });

            editPublishBtn.addEventListener('click', async () => {
                const confirmed = await showConfirmationModal({
                    type: 'success',
                    title: 'Publish Test',
                    message: 'Are you sure you want to publish this test? Published tests will be available to students.',
                    confirmText: 'Publish Test',
                    cancelText: 'Cancel'
                });
                
                if (confirmed) {
                    saveEditTest('published');
                }
            });

            // Edit step indicator click handlers
            editStepIndicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    editCurrentStep = index + 1;
                    updateEditStepIndicator();
                    window.updateEditStepContent();
                    updateEditNavigationButtons();
                });
            });

            // Edit question and page buttons
            editAddQuestionBtn.addEventListener('click', () => {
                openEditQuestionModal();
            });

            editAddContentPageBtn.addEventListener('click', () => {
                openEditPageModal('content');
            });

            editAddQuestionPageBtn.addEventListener('click', () => {
                openEditPageModal('question');
            });

            // Edit modal functions
            function updateEditStepIndicator() {
                editStepIndicators.forEach((indicator, index) => {
                    if (index + 1 <= editCurrentStep) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
            }


            function updateEditNavigationButtons() {
                // Update Previous button
                if (editCurrentStep === 1) {
                    editPrevBtn.style.display = 'none';
                } else {
                    editPrevBtn.style.display = 'inline-flex';
                }

                // Update Next button
                if (editCurrentStep === 4) {
                    editNextBtn.style.display = 'none';
                } else {
                    editNextBtn.style.display = 'inline-flex';
                }

                // Update Save buttons - only show on last step (step 4)
                const editSaveDraftBtn = document.getElementById('editSaveDraftBtn');
                const editPublishBtn = document.getElementById('editPublishBtn');
                const saveButtonsContainer = editSaveDraftBtn ? editSaveDraftBtn.parentElement : null;
                
                console.log('Save buttons container found:', saveButtonsContainer);
                console.log('Current step:', editCurrentStep);
                
                if (saveButtonsContainer) {
                    if (editCurrentStep === 4) {
                        saveButtonsContainer.style.display = 'flex';
                        saveButtonsContainer.style.visibility = 'visible';
                        console.log('Showing save buttons');
                    } else {
                        saveButtonsContainer.style.display = 'none';
                        saveButtonsContainer.style.visibility = 'hidden';
                        console.log('Hiding save buttons');
                    }
                } else {
                    console.log('Save buttons container not found');
                }
            }

            function resetEditTestForm() {
                editCurrentStep = 1;
                editQuestions = [];
                editPages = [];
                editModules = {};
                editCurrentTestId = null;
                // Clear edit mode flag when resetting
                isEditMode = false;
                window.isEditMode = false;
                
                // Reset form fields
                editTestTitle.value = '';
                
                // Reset step indicator and content
                updateEditStepIndicator();
                window.updateEditStepContent();
                updateEditNavigationButtons();
                
                // Clear rendered content
                document.getElementById('editQuestionsList').innerHTML = '';
                document.getElementById('editPagesList').innerHTML = '';
                clearEditModuleSelections();
            }

            // Initialize the edit modal properly
            function initializeEditModal() {
                console.log('initializeEditModal called');
                console.log('Step elements before update:', editStep1, editStep2, editStep3, editStep4);
                
                // Re-assign step elements in case they weren't found earlier
                editStep1 = document.getElementById('editStep1');
                editStep2 = document.getElementById('editStep2');
                editStep3 = document.getElementById('editStep3');
                editStep4 = document.getElementById('editStep4');
                
                console.log('Step elements after re-assignment:', editStep1, editStep2, editStep3, editStep4);
                
                updateEditStepIndicator();
                window.updateEditStepContent();
                updateEditNavigationButtons();
            }

            function clearEditModuleSelections() {
                const containers = ['editBeginnerModules', 'editIntermediateModules', 'editAdvancedModules'];
                containers.forEach(containerId => {
                    const container = document.getElementById(containerId);
                    if (container) {
                        container.innerHTML = `
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                <p>No modules assigned</p>
                            </div>
                        `;
                    }
                });
            }

            function openEditQuestionModal() {
                // Reset form and prepare for new question
                resetEditQuestionForm();
                
                // Set to add mode (use -1 to indicate new question)
                window.currentEditingQuestionIndex = -1;
                
                // Add default choices
                addEditChoiceToModal('', false);
                addEditChoiceToModal('', false);
                
                // Set title for new question
                document.getElementById('editQuestionModalTitle').textContent = 'Add Question';
                
                // Show the edit question modal
                document.getElementById('editQuestionModal').classList.remove('hidden');
            }

function openEditPageModal(type) {
    // Reset the page form completely
    if (window.resetPageForm) window.resetPageForm();
    
    // Set editing mode to undefined (new page, not editing existing)
    window.currentEditingPageIndex = undefined;
    
    // Set the page type
    document.getElementById('pageType').value = type;
    
    // Set the modal title
    document.getElementById('pageModalTitle').textContent = 'Add Page';
    
    // Toggle fields based on type (show/hide content vs question fields)
    if (window.togglePageFields) window.togglePageFields();
    
    // Initialize TinyMCE for content pages
    if (type === 'content') {
        // Initialize TinyMCE editor and load existing content for edit modal
        if (typeof initializePageContentEditor === 'function') initializePageContentEditor();
        // ensure the editor initializes and loads content (retry if needed)
        if (typeof ensurePageEditorInitialized === 'function') ensurePageEditorInitialized(6, 250);
    } else if (type === 'question') {
        // Update question dropdown for question pages
        setTimeout(() => {
            // Populate question checkboxes with available questions
            updateQuestionDropdown();
        }, 100);
    }
    
    // Show the page modal
    document.getElementById('pageModal').classList.remove('hidden');
}

            async function saveEditTest(status) {
                // Check edit permission
                if (!window.placementTestPermissions.canEdit) {
                    showNotification('You do not have permission to edit placement tests.', 'error');
                    return;
                }
                
                const title = editTestTitle.value.trim();
                
                if (!title) {
                    showNotification('Please enter a test title', 'error');
                    return;
                }

                // Create test data object
                const testData = {
                    title: title,
                    status: status,
                    questions: editQuestions,
                    // Backend expects `page_content` when returning test data.
                    // Send the edited pages under that key so changes (content/image)
                    // are persisted. Also include `pages` for backward compatibility.
                    page_content: editPages,
                    pages: editPages,
                    modules: editModules,
                    design_settings: {
                        header_color: '#1f2937',
                        header_text_color: '#ffffff',
                        background_color: '#f5f5f5',
                        accent_color: '#dc2626',
                        font_family: 'Inter',
                        button_color: '#dc2626'
                    }
                };
                
                // Add test ID for update
                if (editCurrentTestId) {
                    testData.test_id = editCurrentTestId;
                }

                console.log('Saving edit test:', testData);
                
                try {
                    const response = await fetch('api/save_placement_test.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(testData)
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        
                        // Close modal and reset form
                        editTestModal.classList.add('hidden');
                        resetEditTestForm();
                        
                        // Reload dashboard data
                        loadDashboardData();
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error saving edit test:', error);
                    showNotification('Error saving test', 'error');
                }
            }

            // Make edit modal functions globally available
            window.initializeEditTestModal = initializeEditTestModal;
            window.initializeEditModal = initializeEditModal;
            window.resetEditTestForm = resetEditTestForm;
            window.saveEditTest = saveEditTest;
            window.openEditModuleSelector = openEditModuleSelector;
            window.updateEditStepContent = updateEditStepContent;
            window.testShowStep1 = testShowStep1;
        }

        // Global edit modal functions
        function updateEditStepContent() {
            console.log('updateEditStepContent called, editCurrentStep:', editCurrentStep);
            console.log('editStep1:', editStep1, 'editStep2:', editStep2, 'editStep3:', editStep3, 'editStep4:', editStep4);
            
            // Hide all steps
            [editStep1, editStep2, editStep3, editStep4].forEach((step, index) => {
                if (step) {
                    step.classList.remove('active');
                    console.log(`Hiding step ${index + 1}`);
                } else {
                    console.log(`Step ${index + 1} element not found`);
                }
            });

            // Show current step
            const currentStepElement = document.getElementById(`editStep${editCurrentStep}`);
            console.log('Current step element:', currentStepElement);
            if (currentStepElement) {
                currentStepElement.classList.add('active');
                console.log(`Showing step ${editCurrentStep}`);
            } else {
                console.log(`Step ${editCurrentStep} element not found`);
            }
        }

        // Test function to manually show step 1
        function testShowStep1() {
            console.log('testShowStep1 called');
            const step1 = document.getElementById('editStep1');
            console.log('Step 1 element:', step1);
            if (step1) {
                step1.classList.add('active');
                console.log('Step 1 shown');
            } else {
                console.log('Step 1 not found');
            }
        }

        function loadDashboardData() {
            // Fetch placement tests data
            fetch('api/get_placement_tests.php', {
                credentials: 'same-origin'
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Dashboard data received:', data);
                    if (data.success) {
                        // Update tests list
                        renderTestsList(data.data.tests);
                        
                        // Initialize Lucide icons for the newly rendered content
                        setTimeout(() => {
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        }, 50);
                        
                        // Update preview button if there are published tests
                        const publishedTests = data.data.tests.filter(test => test.status === 'published');
                        if (publishedTests.length > 0) {
                            const latestPublishedTest = publishedTests[0];
                            updatePreviewButton(true, latestPublishedTest.id);
                        }
                    } else {
                        console.error('Error loading dashboard data:', data.message);
                        showNotification('Error loading dashboard data: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching dashboard data:', error);
                    showNotification('Error loading dashboard data: ' + error.message, 'error');
                });
        }

        function renderTestsList(tests) {
            const testsContainer = document.getElementById('testsList');
            
            if (tests.length === 0) {
                testsContainer.innerHTML = `
                    <div class="text-center py-12">
                        <div class="bg-gray-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">No placement tests found</h3>
                        <p class="text-gray-500">Create your first placement test to get started</p>
                    </div>
                `;
                return;
            }

            const testsHtml = tests.map(test => `
                <div class="test-card group">
                    <!-- Gradient overlay for status -->
                    <div class="absolute top-0 left-0 right-0 h-1 ${
                        test.status === 'published' 
                            ? 'bg-gradient-to-r from-green-400 to-green-600' 
                            : test.status === 'archived'
                            ? 'bg-gradient-to-r from-red-400 to-red-600'
                            : 'bg-gradient-to-r from-yellow-400 to-yellow-600'
                    }"></div>
                    
                    <!-- Header -->
                    <div class="flex justify-between items-start mb-6">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-xl font-bold text-gray-900 mb-2 truncate">${test.title}</h3>
                            <p class="text-sm text-gray-500 mb-3">${test.description || 'No description provided'}</p>
                            
                            <!-- Status Badge -->
                            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${
                                test.status === 'published' 
                                    ? 'bg-green-50 text-green-700 border border-green-200' 
                                    : test.status === 'archived'
                                    ? 'bg-red-50 text-red-700 border border-red-200'
                                    : 'bg-yellow-50 text-yellow-700 border border-yellow-200'
                            }">
                                <div class="w-2 h-2 rounded-full mr-2 status-indicator ${
                                    test.status === 'published' 
                                        ? 'bg-green-500' 
                                        : test.status === 'archived'
                                        ? 'bg-red-500'
                                        : 'bg-yellow-500'
                                }"></div>
                                ${test.status === 'published' ? 'Published' : test.status === 'archived' ? 'Archived' : 'Draft'}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="metric-card text-center p-3 bg-gray-50 rounded-xl">
                            <div class="text-2xl font-bold text-gray-900 mb-1">${test.questions_count}</div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Questions</div>
                        </div>
                        <div class="metric-card text-center p-3 bg-gray-50 rounded-xl">
                            <div class="text-2xl font-bold text-gray-900 mb-1">${test.pages_count}</div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Pages</div>
                        </div>
                        <div class="metric-card text-center p-3 bg-gray-50 rounded-xl">
                            <div class="text-2xl font-bold text-gray-900 mb-1">${test.modules_count}</div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Modules</div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                        <div class="flex items-center text-sm text-gray-500">
                            <i data-lucide="calendar" class="w-4 h-4 mr-2"></i>
                            <span>Updated ${new Date(test.updated_at).toLocaleDateString()}</span>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex items-center space-x-1">
                            ${test.status === 'archived' 
                                ? (window.placementTestPermissions.canEdit ? `<button onclick="restoreTest(${test.id})" 
                                   class="action-btn p-2 text-green-600 hover:text-green-700 hover:bg-green-50 rounded-lg transition-colors duration-200"
                                   title="Restore Test">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                </button>` : '')
                                : test.status === 'published' 
                                    ? (window.placementTestPermissions.canPublish ? `<button onclick="unpublishTest(${test.id})" 
                                       class="action-btn p-2 text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded-lg transition-colors duration-200"
                                       title="Unpublish Test">
                                        <i data-lucide="eye-off" class="w-4 h-4"></i>
                                    </button>` : '')
                                    : (window.placementTestPermissions.canPublish ? `<button onclick="publishTest(${test.id})" 
                                       class="action-btn p-2 text-green-600 hover:text-green-700 hover:bg-green-50 rounded-lg transition-colors duration-200"
                                       title="Publish Test">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>` : '')
                            }
                            
                            ${test.status !== 'archived' && window.placementTestPermissions.canEdit
                                ? `<button onclick="editTest(${test.id})" 
                                   class="action-btn p-2 text-gray-600 hover:text-gray-700 hover:bg-gray-50 rounded-lg transition-colors duration-200"
                                   title="Edit Test">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </button>`
                                : ''
                            }
                            
                            ${test.status !== 'archived' && window.placementTestPermissions.canDelete
                                ? `<button onclick="archiveTest(${test.id}, '${test.status}')" 
                                   class="action-btn p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-200"
                                   title="Archive Test">
                                    <i data-lucide="archive" class="w-4 h-4"></i>
                                </button>`
                                : test.status === 'archived' && window.placementTestPermissions.canDelete
                                ? `<button onclick="deleteTest(${test.id})" 
                                   class="action-btn p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-200"
                                   title="Delete Test">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>`
                                : ''
                            }
                        </div>
                    </div>
                </div>
            `).join('');

            testsContainer.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    ${testsHtml}
                </div>
            `;
        }


        async function editTest(testId) {
            // Check edit permission
            if (!window.placementTestPermissions.canEdit) {
                showNotification('You do not have permission to edit placement tests.', 'error');
                return;
            }
            
            const confirmed = await showConfirmationModal({
                type: 'warning',
                title: 'Edit Test',
                message: 'Are you sure you want to edit this test? Any unsaved changes will be lost.',
                confirmText: 'Edit',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                // Fetch test data
                fetch(`api/get_test_data.php?test_id=${testId}`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Received data:', data);
                        if (data.success) {
                            // Populate the edit modal with existing data
                            populateEditTestModal(data.data);
                        } else {
                            console.error('API error:', data.message);
                            showNotification('Error loading test data: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading test data:', error);
                        showNotification('Error loading test data: ' + error.message, 'error');
                    });
            }
        }

        function populateEditTestModal(testData) {
            console.log('populateEditTestModal called with data:', testData);
            console.log('Current editStep1:', editStep1, 'editStep2:', editStep2, 'editStep3:', editStep3, 'editStep4:', editStep4);
            
            // Populate basic fields
            document.getElementById('editTestTitle').value = testData.title || '';
            
            // Populate questions
            if (testData.questions && Array.isArray(testData.questions)) {
                editQuestions = testData.questions;
                renderEditQuestions();
            }
            
            // Populate pages
            if (testData.page_content) {
                if (Array.isArray(testData.page_content)) {
                    editPages = testData.page_content;
                } else if (typeof testData.page_content === 'object') {
                    // Convert object format to array format if needed
                    editPages = Object.values(testData.page_content).filter(page => page && typeof page === 'object');
                }
                renderEditPages();
            }
            
            // Populate modules - handle different possible structures
            console.log('Full test data received:', testData);
            console.log('Module assignments in test data:', testData.module_assignments);
            console.log('Type of module_assignments:', typeof testData.module_assignments);
            
            if (testData.module_assignments) {
                console.log('Loading module assignments:', testData.module_assignments);
                
                // Handle if it's a JSON string instead of an object
                let assignments = testData.module_assignments;
                if (typeof assignments === 'string') {
                    try {
                        assignments = JSON.parse(assignments);
                        console.log('Parsed JSON assignments:', assignments);
                    } catch (e) {
                        console.error('Failed to parse module_assignments JSON:', e);
                        assignments = {};
                    }
                }
                
                editModules = assignments;
                
                // Handle legacy format: migrate intermediate/advanced to intermediate_beginner/advanced_beginner
                if (editModules.intermediate && !editModules.intermediate_beginner) {
                    console.log('Migrating intermediate modules to intermediate_beginner');
                    editModules.intermediate_beginner = editModules.intermediate;
                    delete editModules.intermediate;
                }
                if (editModules.advanced && !editModules.advanced_beginner) {
                    console.log('Migrating advanced modules to advanced_beginner');
                    editModules.advanced_beginner = editModules.advanced;
                    delete editModules.advanced;
                }
                
                // Handle orphaned modules with empty/invalid keys
                if (editModules[''] && Array.isArray(editModules[''])) {
                    console.log('Found orphaned modules with empty key, migrating to beginner:', editModules['']);
                    if (!editModules.beginner) editModules.beginner = [];
                    editModules.beginner = editModules.beginner.concat(editModules['']);
                    delete editModules[''];
                }
                
                // Clean up any other invalid keys and migrate to beginner
                const validKeys = ['beginner', 'intermediate_beginner', 'advanced_beginner'];
                Object.keys(editModules).forEach(key => {
                    if (!validKeys.includes(key) && Array.isArray(editModules[key])) {
                        console.log(`Found modules with invalid key '${key}', migrating to beginner:`, editModules[key]);
                        if (!editModules.beginner) editModules.beginner = [];
                        editModules.beginner = editModules.beginner.concat(editModules[key]);
                        delete editModules[key];
                    }
                });
                
                // Ensure we have the expected structure
                if (!editModules.beginner) editModules.beginner = [];
                if (!editModules.intermediate_beginner) editModules.intermediate_beginner = [];
                if (!editModules.advanced_beginner) editModules.advanced_beginner = [];
                
                console.log('Final modules structure:', editModules);
                
                renderEditAssignedModules('beginner');
                renderEditAssignedModules('intermediate_beginner');
                renderEditAssignedModules('advanced_beginner');
            } else {
                console.log('No module assignments found in test data, initializing empty structure');
                // Initialize empty structure for edit mode
                editModules = {
                    beginner: [],
                    intermediate_beginner: [],
                    advanced_beginner: []
                };
                
                renderEditAssignedModules('beginner');
                renderEditAssignedModules('intermediate_beginner');
                renderEditAssignedModules('advanced_beginner');
            }
            
            // Set current test ID for updating
            editCurrentTestId = testData.id;
            
            // Initialize the modal properly
            initializeEditModal();
            
            // Show the edit modal
            document.getElementById('editTestModal').classList.remove('hidden');
        }

        function renderEditQuestions() {
            const questionsList = document.getElementById('editQuestionsList');
            if (!questionsList) return;
            
            questionsList.innerHTML = '';
            
            if (editQuestions.length === 0) {
                questionsList.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p>No questions added yet</p>
                    </div>
                `;
                return;
            }
            
            editQuestions.forEach((question, index) => {
                const questionCard = document.createElement('div');
                questionCard.className = 'question-card';
                questionCard.innerHTML = `
                    <div class="question-header">
                        <div class="question-number">Question ${index + 1}</div>
                        <div class="question-difficulty difficulty-${question.difficulty_level || question.difficulty || 'beginner'}">${(question.difficulty_level || question.difficulty || 'beginner').toUpperCase()}</div>
                    </div>
                    <div class="question-text">${question.question_text || question.text || 'Untitled Question'}</div>
                    <ul class="question-choices">
                        ${(question.choices || []).map((choice, choiceIndex) => {
                            const letter = String.fromCharCode(65 + choiceIndex); // A, B, C, D, etc.
                            return `
                                <li class="question-choice ${choice.is_correct ? 'correct' : ''}">
                                    <span class="choice-letter">${letter}.</span> ${choice.text || choice.choice_text} ${choice.is_correct ? '✓' : ''}
                                </li>
                            `;
                        }).join('')}
                    </ul>
                    <div class="flex justify-between items-center mt-4">
                        <span class="text-sm text-gray-500">${question.points || 1} point${(question.points || 1) !== 1 ? 's' : ''}</span>
                        <div class="flex items-center space-x-2">
                            <button type="button" class="text-blue-600 hover:text-blue-800 p-1 rounded transition-colors" onclick="editEditQuestion(${index})" title="Edit Question">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-800 p-1 rounded transition-colors" onclick="removeEditQuestion(${index})" title="Remove Question">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                `;
                questionsList.appendChild(questionCard);
            });
            
            // Re-initialize Lucide icons for the new content
            setTimeout(() => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 50);
        }

        function renderEditPages() {
            const pagesList = document.getElementById('editPagesList');
            if (!pagesList) return;
            
            // Debug logging for page ordering
            console.log('Rendering edit pages with order:');
            editPages.forEach((page, index) => {
                console.log(`Page ${index + 1}: ${page.type} - "${page.title}" (order: ${page.order})`);
            });
            
            pagesList.innerHTML = '';

            if (editPages.length === 0) {
                pagesList.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                        <p>No pages added yet</p>
                    </div>
                `;
                return;
            }

            editPages.forEach((page, index) => {
                const pageCard = document.createElement('div');
                pageCard.className = 'page-card';
                pageCard.innerHTML = `
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="page-type page-type-${page.type}">${page.type}</div>
                            <div class="font-semibold text-gray-900">${page.title}</div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="moveEditPageUp(${index})" ${index === 0 ? 'disabled' : ''} title="Move Up">
                                <i data-lucide="chevron-up" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="moveEditPageDown(${index})" ${index === editPages.length - 1 ? 'disabled' : ''} title="Move Down">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="text-blue-600 hover:text-blue-800 p-1" onclick="editEditPage(${index})" title="Edit Page">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-800 p-1" onclick="removeEditPage(${index})" title="Delete Page">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-sm text-gray-600">
                        ${page.type === 'content' ? 
                            `<div>Content: ${page.content ? page.content.substring(0, 100) + (page.content.length > 100 ? '...' : '') : 'No content'}</div>
                             ${page.image ? `<div class="mt-1">Image: ${page.image}</div>` : ''}` :
                            `<div>Questions: ${page.question_ids && page.question_ids.length > 0 ? 
                                page.question_ids.map(id => `Q${id}`).join(', ') : 'No questions selected'}</div>`
                        }
                    </div>
                `;
                pagesList.appendChild(pageCard);
            });
            
            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function renderAssignedModules(level) {
            // Map internal level names to container IDs for Create New mode
            const containerMapping = {
                'beginner': 'beginnerModules',
                'intermediate_beginner': 'intermediateModules',
                'advanced_beginner': 'advancedModules'
            };
            
            const containerId = containerMapping[level];
            const container = document.getElementById(containerId);
            if (!container) return;
            
            if (!modules[level] || modules[level].length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <p class="text-sm">No modules assigned</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = modules[level].map((module, index) => `
                <div class="module-item bg-white border border-gray-200 rounded-lg p-3 flex items-center justify-between mb-2">
                    <div class="flex-1">
                        <h5 class="font-medium text-gray-900">${module.title}</h5>
                        <p class="text-xs text-gray-500">ID: ${module.id}</p>
                    </div>
                    <button type="button" class="text-red-600 hover:text-red-800 p-1" onclick="removeModuleFromLevel('${level}', ${index})" title="Remove Module">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            `).join('');
            
            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function renderEditAssignedModules(level) {
            // Map internal level names to container IDs for Edit mode
            const containerMapping = {
                'beginner': 'editBeginnerModules',
                'intermediate_beginner': 'editIntermediateModules',
                'advanced_beginner': 'editAdvancedModules'
            };
            
            const containerId = containerMapping[level];
            const container = document.getElementById(containerId);
            if (!container) return;
            
            if (!editModules[level] || editModules[level].length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <p>No modules assigned</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = editModules[level].map((module, index) => `
                <div class="module-item bg-white border border-gray-200 rounded-lg p-3 flex items-center justify-between mb-2">
                    <div class="flex-1">
                        <h5 class="font-medium text-gray-900">${module.title}</h5>
                        <p class="text-xs text-gray-500">ID: ${module.id}</p>
                    </div>
                    <button type="button" class="text-red-600 hover:text-red-800 p-1" onclick="removeEditModuleFromLevel('${level}', ${index})" title="Remove Module">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            `).join('');
            
            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Edit modal helper functions
        async function editEditQuestion(index) {
            // Use the dedicated edit question modal
            const question = editQuestions[index];
            if (!question) {
                showNotification('Question not found', 'error');
                return;
            }

            // Check if another question is currently being edited
            if (window.currentEditingQuestionIndex !== null && window.currentEditingQuestionIndex !== undefined) {
                const confirmed = await showConfirmationModal({
                    type: 'warning',
                    title: 'Another Question Being Edited',
                    message: 'You are currently editing another question. Do you want to discard those changes and edit this question instead?',
                    confirmText: 'Yes, Edit This Question',
                    cancelText: 'Cancel'
                });
                
                if (!confirmed) return;
            }

            // Show confirmation modal before opening edit form
            const editConfirmed = await showConfirmationModal({
                type: 'info',
                title: 'Edit Question',
                message: `Are you sure you want to edit Question ${index + 1}? This will open the question editor.`,
                confirmText: 'Edit Question',
                cancelText: 'Cancel'
            });

            if (!editConfirmed) return;

            // Validate question data before editing
            if (!question.question_text && !question.text) {
                showNotification('Cannot edit: Question text is missing', 'error');
                return;
            }

            // Store original data for change detection
            window.editQuestionOriginalData = {
                questionText: question.question_text || question.text || '',
                difficulty: question.difficulty_level || question.difficulty || 'beginner',
                points: question.points || 1,
                choices: question.choices ? question.choices.map(choice => ({
                    text: choice.text || '',
                    is_correct: choice.is_correct || false
                })) : []
            };
            
            // Populate the edit question modal with data
            document.getElementById('editQuestionText').value = window.editQuestionOriginalData.questionText;
            document.getElementById('editQuestionDifficulty').value = window.editQuestionOriginalData.difficulty;
            document.getElementById('editQuestionPoints').value = window.editQuestionOriginalData.points;
            
            // Clear existing choices
            const choicesContainer = document.getElementById('editChoicesContainer');
            choicesContainer.innerHTML = '';
            
            // Add existing choices
            if (window.editQuestionOriginalData.choices.length > 0) {
                window.editQuestionOriginalData.choices.forEach((choice) => {
                    addEditChoiceToModal(choice.text, choice.is_correct);
                });
            } else {
                // Add default choices if none exist
                addEditChoiceToModal('', false);
                addEditChoiceToModal('', false);
            }
            
            // Set editing mode
            window.currentEditingQuestionIndex = index;
            document.getElementById('editQuestionModalTitle').textContent = `Edit Question ${index + 1}`;
            
            // Show the edit question modal
            document.getElementById('editQuestionModal').classList.remove('hidden');
            
            // Show success notification
            showNotification('Question editor opened', 'success');
        }

        function addEditChoiceToModal(text = '', isCorrect = false) {
            const choicesContainer = document.getElementById('editChoicesContainer');
            const choiceIndex = choicesContainer.children.length;
            const letter = String.fromCharCode(65 + choiceIndex);
            
            const choiceDiv = document.createElement('div');
            choiceDiv.className = 'choice-item flex items-center space-x-3 mb-3';
            choiceDiv.innerHTML = `
                <span class="choice-letter-label font-medium text-gray-700 w-6">${letter}.</span>
                <input type="text" class="form-input flex-1" placeholder="Enter choice text" value="${text}">
                <label class="flex items-center">
                    <input type="radio" name="editCorrectChoice" value="${choiceIndex}" ${isCorrect ? 'checked' : ''} class="mr-2">
                    <span class="text-sm text-gray-600">Correct</span>
                </label>
                <button type="button" onclick="removeEditChoiceFromModal(this)" class="text-red-600 hover:text-red-800 p-1">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            `;
            
            choicesContainer.appendChild(choiceDiv);
            
            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function removeEditChoiceFromModal(button) {
            const choiceItem = button.closest('.choice-item');
            choiceItem.remove();
            updateEditChoiceLabelsInModal();
        }

        function updateEditChoiceLabelsInModal() {
            const choicesContainer = document.getElementById('editChoicesContainer');
            const choices = choicesContainer.children;
            
            for (let i = 0; i < choices.length; i++) {
                const choice = choices[i];
                const letter = String.fromCharCode(65 + i);
                const letterLabel = choice.querySelector('.choice-letter-label');
                const radioButton = choice.querySelector('input[type="radio"]');
                
                if (letterLabel) letterLabel.textContent = letter + '.';
                if (radioButton) radioButton.value = i;
            }
        }

        function resetEditQuestionForm() {
            // Clear form fields
            document.getElementById('editQuestionText').value = '';
            document.getElementById('editQuestionDifficulty').value = '';
            document.getElementById('editQuestionPoints').value = '1';
            
            // Clear choices container
            const choicesContainer = document.getElementById('editChoicesContainer');
            choicesContainer.innerHTML = '';
            
            // Reset editing state variables
            window.currentEditingQuestionIndex = null;
            window.editQuestionOriginalData = null;
            
            // Reset modal title
            document.getElementById('editQuestionModalTitle').textContent = 'Add Question';
            
            // Clear any validation errors
            const errorElements = document.querySelectorAll('#editQuestionModal .error-message');
            errorElements.forEach(element => element.remove());
            
            // Reset form validation state
            const formInputs = document.querySelectorAll('#editQuestionModal input, #editQuestionModal select, #editQuestionModal textarea');
            formInputs.forEach(input => {
                input.classList.remove('error', 'invalid');
                input.removeAttribute('aria-invalid');
            });
            
            // Log reset for debugging
            console.log('Edit question form reset completed');
        }

        function validateEditQuestionState() {
            const modal = document.getElementById('editQuestionModal');
            const isModalVisible = !modal.classList.contains('hidden');
            
            if (isModalVisible && hasUnsavedQuestionChanges()) {
                return {
                    hasUnsavedChanges: true,
                    message: 'You have unsaved changes in the question editor'
                };
            }
            
            return {
                hasUnsavedChanges: false,
                message: 'No unsaved changes detected'
            };
        }

        function saveEditQuestionFromModal() {
            // Pre-validation checks
            if (window.currentEditingQuestionIndex === null || window.currentEditingQuestionIndex === undefined) {
                showNotification('No question is currently being edited', 'error');
                return;
            }

            // Validate the form
            const validation = validateEditQuestionForm();
            
            if (!validation.isValid) {
                showValidationErrors(validation.errors);
                return;
            }
            
            const { questionText, difficulty, points, choices } = validation.data;

            // Additional business logic validation
            const errors = [];
            
            // Check for duplicate question text in the test
            const existingQuestions = editQuestions.filter((q, index) => index !== window.currentEditingQuestionIndex);
            const isDuplicate = existingQuestions.some(q => 
                (q.question_text || q.text || '').trim().toLowerCase() === questionText.trim().toLowerCase()
            );
            
            if (isDuplicate) {
                errors.push('A question with similar text already exists in this test');
            }

            // Validate choice quality
            const choiceTexts = choices.map(c => c.text.trim().toLowerCase());
            const averageChoiceLength = choiceTexts.reduce((sum, text) => sum + text.length, 0) / choiceTexts.length;
            
            if (averageChoiceLength < 3) {
                errors.push('Answer choices should be more descriptive (average minimum 3 characters)');
            }

            // Check for choices that are too similar
            for (let i = 0; i < choiceTexts.length; i++) {
                for (let j = i + 1; j < choiceTexts.length; j++) {
                    const similarity = calculateStringSimilarity(choiceTexts[i], choiceTexts[j]);
                    if (similarity > 0.8) {
                        errors.push('Some answer choices are too similar to each other');
                        break;
                    }
                }
                if (errors.length > 0) break;
            }

            // Show errors if any
            if (errors.length > 0) {
                showValidationErrors(errors);
                return;
            }
            
            // Check if we're editing an existing question or adding a new one
            if (window.currentEditingQuestionIndex !== undefined && window.currentEditingQuestionIndex !== null && window.currentEditingQuestionIndex >= 0) {
                // Update existing question
                const questionIndex = window.currentEditingQuestionIndex;
                const originalQuestion = editQuestions[questionIndex];
                
                if (originalQuestion) {
                    editQuestions[questionIndex] = {
                        id: originalQuestion.id || (editQuestions.length + 1),
                        question_text: questionText,
                        difficulty_level: difficulty,
                        points: points,
                        choices: choices.map((choice, index) => ({
                            id: index + 1,
                            text: choice.text,
                            is_correct: choice.is_correct
                        })),
                        updated_at: new Date().toISOString()
                    };
                    
                    showNotification(`Question ${questionIndex + 1} updated successfully!`, 'success');
                } else {
                    showNotification('Error: Question not found', 'error');
                    return;
                }
            } else {
                // Create new question (when currentEditingQuestionIndex is -1 or null)
                const newQuestion = {
                    id: editQuestions.length + 1,
                    question_text: questionText,
                    difficulty_level: difficulty,
                    points: points,
                    choices: choices.map((choice, index) => ({
                        id: index + 1,
                        text: choice.text,
                        is_correct: choice.is_correct
                    })),
                    created_at: new Date().toISOString()
                };
                
                editQuestions.push(newQuestion);
                showNotification('Question added successfully!', 'success');
            }
            
            // Re-render questions
            renderEditQuestions();
            
            // Close modal and reset form
            document.getElementById('editQuestionModal').classList.add('hidden');
            resetEditQuestionForm();
            
            // Mark as changed for unsaved changes tracking
            markAsChanged();
        }

        // Helper function to calculate string similarity
        function calculateStringSimilarity(str1, str2) {
            const longer = str1.length > str2.length ? str1 : str2;
            const shorter = str1.length > str2.length ? str2 : str1;
            
            if (longer.length === 0) return 1.0;
            
            const distance = levenshteinDistance(longer, shorter);
            return (longer.length - distance) / longer.length;
        }

        // Levenshtein distance calculation
        function levenshteinDistance(str1, str2) {
            const matrix = [];
            
            for (let i = 0; i <= str2.length; i++) {
                matrix[i] = [i];
            }
            
            for (let j = 0; j <= str1.length; j++) {
                matrix[0][j] = j;
            }
            
            for (let i = 1; i <= str2.length; i++) {
                for (let j = 1; j <= str1.length; j++) {
                    if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                        matrix[i][j] = matrix[i - 1][j - 1];
                    } else {
                        matrix[i][j] = Math.min(
                            matrix[i - 1][j - 1] + 1,
                            matrix[i][j - 1] + 1,
                            matrix[i - 1][j] + 1
                        );
                    }
                }
            }
            
            return matrix[str2.length][str1.length];
        }

        function removeEditQuestion(index) {
            showConfirmationModal({
                type: 'danger',
                title: 'Delete Question',
                message: 'Are you sure you want to delete this question? This action cannot be undone.',
                confirmText: 'Delete Question',
                cancelText: 'Cancel'
            }).then(confirmed => {
                if (confirmed) {
                    editQuestions.splice(index, 1);
                    renderEditQuestions();
                    showNotification('Question deleted successfully!', 'success');
                    markAsChanged();
                }
            });
        }

        function moveEditPageUp(index) {
            if (index > 0) {
                const temp = editPages[index];
                editPages[index] = editPages[index - 1];
                editPages[index - 1] = temp;
                
                // Update order properties
                editPages.forEach((page, i) => {
                    page.order = i + 1;
                });
                
                renderEditPages();
            }
        }

        function moveEditPageDown(index) {
            if (index < editPages.length - 1) {
                const temp = editPages[index];
                editPages[index] = editPages[index + 1];
                editPages[index + 1] = temp;
                
                // Update order properties
                editPages.forEach((page, i) => {
                    page.order = i + 1;
                });
                
                renderEditPages();
            }
        }

        async function editEditPage(index) {
            // Use the existing page modal for editing
            const page = editPages[index];
            if (!page) {
                showNotification('Page not found', 'error');
                return;
            }

            // Check if another page is currently being edited
            if (window.currentEditingPageIndex !== null && window.currentEditingPageIndex !== undefined) {
                const confirmed = await showConfirmationModal({
                    type: 'warning',
                    title: 'Another Page Being Edited',
                    message: 'You are currently editing another page. Do you want to discard those changes and edit this page instead?',
                    confirmText: 'Yes, Edit This Page',
                    cancelText: 'Cancel'
                });
                
                if (!confirmed) return;
            }

            // Show confirmation modal before opening edit form
            const editConfirmed = await showConfirmationModal({
                type: 'info',
                title: 'Edit Page',
                message: `Are you sure you want to edit "${page.title || 'Untitled Page'}"? This will open the page editor.`,
                confirmText: 'Edit Page',
                cancelText: 'Cancel'
            });

            if (!editConfirmed) return;

            // Validate page data before editing
            if (!page.title && page.title !== '') {
                showNotification('Cannot edit: Page title is missing', 'error');
                return;
            }

            // Validate permissions
            const permission = validatePageEditPermissions(index);
            if (!permission.valid) {
                showNotification(permission.message, 'error');
                return;
            }

            // Store original data for change detection
            window.editPageOriginalData = {
                pageTitle: page.title || '',
                pageType: page.type || 'content',
                pageContent: page.content || '',
                selectedQuestions: page.question_ids ? [...page.question_ids] : []
            };
            
            // Populate the page modal with edit data
            document.getElementById('pageType').value = window.editPageOriginalData.pageType;
            document.getElementById('pageTitle').value = window.editPageOriginalData.pageTitle;
            document.getElementById('pageContent').value = window.editPageOriginalData.pageContent;
            
            // Handle image display - guard against missing element
            const imageDisplay = document.getElementById('imageDisplay');
            if (page.image) {
                const currentImageHtml = `
                    <div class="mt-2 p-2 bg-gray-100 rounded current-image">
                        <p class="text-sm text-gray-600">Current image: ${page.image}</p>
                    </div>
                `;
                if (imageDisplay) {
                    imageDisplay.innerHTML = currentImageHtml;
                } else {
                    // fallback: append next to the file input if available
                    const imageInput = document.getElementById('pageImage');
                    if (imageInput && imageInput.parentNode) {
                        // remove any existing current-image sibling
                        const existing = imageInput.parentNode.querySelector('.current-image');
                        if (existing) existing.remove();
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = currentImageHtml;
                        imageInput.parentNode.appendChild(wrapper.firstElementChild);
                    }
                }
            } else {
                if (imageDisplay) imageDisplay.innerHTML = '';
            }
            
            // Handle question selection for question pages.
            // populateQuestionCheckboxes may render checkboxes asynchronously,
            // so defer setting the checked state slightly to avoid a race.
            if (window.editPageOriginalData.pageType === 'question') {
                if (typeof populateQuestionCheckboxes === 'function') {
                    populateQuestionCheckboxes();
                }

                // After population, set the selected checkboxes. Use a few
                // retries in case population is delayed.
                (function setSelectedQuestions(retries = 5, delay = 120) {
                    setTimeout(() => {
                        const questionCheckboxes = document.querySelectorAll('#questionFields input[type="checkbox"]');
                        if (questionCheckboxes && questionCheckboxes.length > 0) {
                            try {
                                questionCheckboxes.forEach(checkbox => {
                                    const questionId = parseInt(checkbox.value);
                                    checkbox.checked = window.editPageOriginalData.selectedQuestions.includes(questionId);
                                });
                                // done
                                return;
                            } catch (e) {
                                console.warn('setSelectedQuestions: error setting checkboxes', e);
                            }
                        }

                        if (retries > 0) {
                            setSelectedQuestions(retries - 1, delay);
                        }
                    }, delay);
                })();
            }
            
            // Toggle page fields based on type
            togglePageFields();
            
            // Set editing mode
            window.currentEditingPageIndex = index;
            document.getElementById('pageModalTitle').textContent = `Edit Page: ${window.editPageOriginalData.pageTitle}`;
            
            // Show the page modal
            document.getElementById('pageModal').classList.remove('hidden');
            
            // Show success notification
            showNotification('Page editor opened', 'success');
        }

        function removeEditPage(index) {
            showConfirmationModal({
                type: 'danger',
                title: 'Delete Page',
                message: `Are you sure you want to delete "${editPages[index].title}"? This action cannot be undone.`,
                confirmText: 'Delete',
                cancelText: 'Cancel'
            }).then(confirmed => {
                if (confirmed) {
                    editPages.splice(index, 1);
                    renderEditPages();
                }
            });
        }

        function openEditModuleSelector(level) {
            // Set global state for edit mode
            currentLevel = level;
            window.currentLevel = level;
            isEditMode = true;
            selectedModules.clear();
            
            // Map level names for display
            const levelNames = {
                'beginner': 'Beginner',
                'intermediate': 'Intermediate Beginner', 
                'advanced': 'Advanced Beginner'
            };
            
            document.getElementById('selectedLevel').textContent = levelNames[level] || level.charAt(0).toUpperCase() + level.slice(1);
            
            loadAvailableCourses(); // Use the enhanced loading function
            document.getElementById('moduleSelectorModal').classList.remove('hidden');
            
            // Reset UI state
            updateBulkActionButtons();
            updateModuleStatistics();
        }

        function loadEditModuleSuggestions() {
            const level = window.currentLevel;
            const suggestionsContainer = document.getElementById('moduleSuggestions');
            
            if (!level) return;
            
            // Show loading state
            suggestionsContainer.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-pink-500 mx-auto"></div>
                    <p class="mt-2 text-gray-600">Loading module suggestions...</p>
                </div>
            `;
            
            // Fetch module suggestions
            fetch(`api/get_module_suggestions.php?level=${level}`, {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.modules) {
                        displayEditModuleSuggestions(data.modules);
                    } else {
                        suggestionsContainer.innerHTML = `
                            <div class="text-center py-8 text-gray-500">
                                <p>No module suggestions available for ${level} level.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading module suggestions:', error);
                    suggestionsContainer.innerHTML = `
                        <div class="text-center py-8 text-red-500">
                            <p>Error loading module suggestions.</p>
                        </div>
                    `;
                });
        }

        function displayEditModuleSuggestions(modules) {
            const suggestionsContainer = document.getElementById('moduleSuggestions');
            
            if (!modules || modules.length === 0) {
                suggestionsContainer.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <p>No modules available for this level.</p>
                    </div>
                `;
                return;
            }
            
            suggestionsContainer.innerHTML = modules.map(module => `
                <div class="module-suggestion border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900">${module.title}</h4>
                            <p class="text-sm text-gray-600 mt-1">${module.description || 'No description available'}</p>
                            <div class="flex items-center mt-2 space-x-4 text-xs text-gray-500">
                                <span>ID: ${module.id}</span>
                                <span>Level: ${module.level || 'N/A'}</span>
                            </div>
                        </div>
                        <button type="button" onclick="assignEditModuleToLevel(${module.id}, '${module.title}')" 
                                class="btn-primary text-sm px-3 py-1">
                            Add
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function assignEditModuleToLevel(courseId, courseTitle) {
            const level = window.currentLevel;
            if (!level) return;
            
            // Map display level to internal level
            const levelMapping = {
                'beginner': 'beginner',
                'intermediate': 'intermediate_beginner',
                'advanced': 'advanced_beginner'
            };
            
            const internalLevel = levelMapping[level] || level;
            
            // Initialize the level array if it doesn't exist
            if (!editModules[internalLevel]) {
                editModules[internalLevel] = [];
            }
            
            // Check if module is already assigned
            const isAlreadyAssigned = editModules[internalLevel].some(module => module.id === courseId);
            if (isAlreadyAssigned) {
                showNotification('This module is already assigned to this level', 'warning');
                return;
            }
            
            // Add the module
            editModules[internalLevel].push({
                id: courseId,
                title: courseTitle
            });
            
            // Update the display
            renderEditAssignedModules(internalLevel);
            
            // Close the modal
            document.getElementById('moduleSelectorModal').classList.add('hidden');
            
            showNotification(`Module "${courseTitle}" added to ${level} level`, 'success');
        }

        function removeModuleFromLevel(level, index) {
            showConfirmationModal({
                type: 'danger',
                title: 'Remove Module',
                message: 'Are you sure you want to remove this module from this level?',
                confirmText: 'Remove',
                cancelText: 'Cancel'
            }).then(confirmed => {
                if (confirmed) {
                    if (modules[level] && modules[level][index]) {
                        modules[level].splice(index, 1);
                        renderAssignedModules(level);
                        markAsChanged(); // Mark as changed when module is removed
                    }
                }
            });
        }

        function removeEditModuleFromLevel(level, index) {
            showConfirmationModal({
                type: 'danger',
                title: 'Remove Module',
                message: 'Are you sure you want to remove this module from this level?',
                confirmText: 'Remove',
                cancelText: 'Cancel'
            }).then(confirmed => {
                if (confirmed) {
                    if (editModules[level] && editModules[level][index]) {
                        editModules[level].splice(index, 1);
                        renderEditAssignedModules(level);
                        autoSaveModuleAssignments(); // Auto-save changes in edit mode
                    }
                }
            });
        }
        
        // Global variables for placement test management
        let questions = [];
        let pages = [];
        let modules = {};
        let currentTestId = null;
        
        // Global variables for edit test modal management
        let editQuestions = [];
        let editPages = [];
        let editModules = {};
        let editCurrentTestId = null;
        let editCurrentStep = 1;
        
        // Global module management variables
        let allModules = []; // Cache of all available modules
        let currentLevel = '';
        let selectedModules = new Set(); // Track selected modules in modal
        let isEditMode = false; // Track if we're in edit mode or create mode
        
        // Global edit modal elements
        let editStep1, editStep2, editStep3, editStep4;
        
        // Global functions for question management
        function renderQuestions() {
            const questionsList = document.getElementById('questionsList');
            if (!questionsList) return;
            
            questionsList.innerHTML = '';

            if (questions.length === 0) {
                questionsList.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p>No questions added yet</p>
                    </div>
                `;
                return;
            }

            questions.forEach((question, index) => {
                const questionCard = document.createElement('div');
                questionCard.className = 'question-card';
                questionCard.innerHTML = `
                    <div class="question-header">
                        <div class="question-number">Question ${index + 1}</div>
                        <div class="question-difficulty difficulty-${question.difficulty_level}">${question.difficulty_level}</div>
                    </div>
                    <div class="question-text">${question.question_text}</div>
                    <ul class="question-choices">
                        ${question.choices.map((choice, choiceIndex) => {
                            const letter = String.fromCharCode(65 + choiceIndex); // A, B, C, D, etc.
                            return `
                                <li class="question-choice ${choice.is_correct ? 'correct' : ''}">
                                    <span class="choice-letter">${letter}.</span> ${choice.text} ${choice.is_correct ? '✓' : ''}
                                </li>
                            `;
                        }).join('')}
                    </ul>
                    <div class="flex justify-between items-center mt-4">
                        <span class="text-sm text-gray-500">${question.points} point${question.points !== 1 ? 's' : ''}</span>
                        <div class="flex items-center space-x-2">
                            <button type="button" class="text-blue-600 hover:text-blue-800 p-1 rounded transition-colors" onclick="editQuestion(${index})" title="Edit Question">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-800 p-1 rounded transition-colors" onclick="removeQuestion(${index})" title="Remove Question">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                        </div>
                    </div>
                `;
                questionsList.appendChild(questionCard);
            });
            
            // Re-initialize Lucide icons for the new content
            setTimeout(() => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 50);
        }

        async function removeQuestion(index) {
            const confirmed = await showConfirmationModal({
                type: 'danger',
                title: 'Remove Question',
                message: 'Are you sure you want to remove this question? This action cannot be undone.',
                confirmText: 'Remove',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                questions.splice(index, 1);
                renderQuestions();
            }
        }

        async function editQuestion(index) {
            const question = questions[index];
            if (!question) {
                showNotification('Question not found', 'error');
                return;
            }

            // Check if another question is currently being edited
            if (window.currentEditingQuestionIndex !== null && window.currentEditingQuestionIndex !== undefined && window.currentEditingQuestionIndex !== index) {
                const confirmed = await showConfirmationModal({
                    type: 'warning',
                    title: 'Another Question Being Edited',
                    message: 'You are currently editing another question. Do you want to discard those changes and edit this question instead?',
                    confirmText: 'Yes, Edit This Question',
                    cancelText: 'Cancel'
                });
                
                if (!confirmed) return;
            }

            // Show confirmation modal before opening edit form
            const editConfirmed = await showConfirmationModal({
                type: 'info',
                title: 'Edit Question',
                message: `Are you sure you want to edit Question ${index + 1}? This will open the question editor.`,
                confirmText: 'Edit Question',
                cancelText: 'Cancel'
            });

            if (!editConfirmed) return;

            // Validate question data before editing
            if (!question.question_text) {
                showNotification('Cannot edit: Question text is missing', 'error');
                return;
            }

            // Store original data for change detection (for main question modal)
            window.questionOriginalData = {
                questionText: question.question_text || '',
                difficulty: question.difficulty_level || 'beginner',
                points: question.points || 1,
                choices: question.choices ? question.choices.map(choice => ({
                    text: choice.text || '',
                    is_correct: choice.is_correct || false
                })) : []
            };
            
            // Store the current question index for updating
            window.currentEditingQuestionIndex = index;
            
            // Populate the question form with existing data
            document.getElementById('questionText').value = window.questionOriginalData.questionText;
            document.getElementById('questionDifficulty').value = window.questionOriginalData.difficulty;
            document.getElementById('questionPoints').value = window.questionOriginalData.points;
            
            // Clear existing choices
            const choicesList = document.getElementById('choicesList');
            choicesList.innerHTML = '';
            
            // Add choices from the existing question
            window.questionOriginalData.choices.forEach((choice, choiceIndex) => {
                const letter = String.fromCharCode(65 + choiceIndex); // A, B, C, D, etc.
                const choiceDiv = document.createElement('div');
                choiceDiv.className = 'choice-input-group';
                choiceDiv.innerHTML = `
                    <input type="radio" name="correct_choice" value="${choiceIndex}" class="choice-radio" ${choice.is_correct ? 'checked' : ''}>
                    <span class="choice-letter-label">${letter}.</span>
                    <input type="text" name="choice_text" placeholder="Enter choice text..." class="flex-1" value="${choice.text}">
                    <button type="button" class="remove-choice-btn" onclick="removeChoice(this)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;
                choicesList.appendChild(choiceDiv);
            });
            
            // Update the modal title to indicate editing
            document.querySelector('#questionModal h2').textContent = `Edit Question ${index + 1}`;
            
            // Show the modal
            document.getElementById('questionModal').classList.remove('hidden');
            
            // Show success notification
            showNotification('Question editor opened', 'success');
        }

        // Global functions for page management
        function renderPages() {
            const pagesList = document.getElementById('pagesList');
            if (!pagesList) return;
            
            // Debug logging for page ordering
            console.log('Rendering pages with order:');
            pages.forEach((page, index) => {
                console.log(`Page ${index + 1}: ${page.type} - "${page.title}" (order: ${page.order})`);
            });
            
            pagesList.innerHTML = '';

            if (pages.length === 0) {
                pagesList.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                        <p>No pages added yet</p>
                    </div>
                `;
                return;
            }

            pages.forEach((page, index) => {
                const pageCard = document.createElement('div');
                pageCard.className = 'page-card';
                pageCard.innerHTML = `
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="page-type page-type-${page.type}">${page.type}</div>
                            <div class="font-semibold text-gray-900">${page.title}</div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="movePageUp(${index})" ${index === 0 ? 'disabled' : ''} title="Move Up">
                                <i data-lucide="chevron-up" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="movePageDown(${index})" ${index === pages.length - 1 ? 'disabled' : ''} title="Move Down">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="text-blue-600 hover:text-blue-800 p-1" onclick="editPage(${index})" title="Edit Page">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-800 p-1" onclick="removePage(${index})" title="Delete Page">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-sm text-gray-600">
                        ${page.type === 'content' ? 
                            `<div>Content: ${page.content ? page.content.substring(0, 100) + (page.content.length > 100 ? '...' : '') : 'No content'}</div>
                             ${page.image ? `<div class="mt-1">Image: ${page.image}</div>` : ''}` :
                            `<div>Questions: ${page.question_ids && page.question_ids.length > 0 ? 
                                page.question_ids.map(id => `Q${id}`).join(', ') : 'No questions selected'}</div>`
                        }
                    </div>
                `;
                pagesList.appendChild(pageCard);
            });
            
            // Re-initialize Lucide icons after rendering
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function movePageUp(index) {
            if (index > 0) {
                // Swap the pages
                [pages[index], pages[index - 1]] = [pages[index - 1], pages[index]];
                
                // Update order values to reflect new positions
                pages.forEach((page, i) => {
                    page.order = i + 1;
                });
                
                renderPages();
                markAsChanged(); // Mark as changed when reordering
            }
        }

        function movePageDown(index) {
            if (index < pages.length - 1) {
                // Swap the pages
                [pages[index], pages[index + 1]] = [pages[index + 1], pages[index]];
                
                // Update order values to reflect new positions
                pages.forEach((page, i) => {
                    page.order = i + 1;
                });
                
                renderPages();
                markAsChanged(); // Mark as changed when reordering
            }
        }

        async function removePage(index) {
            const page = pages[index];
            if (!page) return;
            
            const confirmed = await showConfirmationModal({
                type: 'danger',
                title: 'Delete Page',
                message: `Are you sure you want to delete "${page.title}"? This action cannot be undone.`,
                confirmText: 'Delete',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                pages.splice(index, 1);
                renderPages();
                markAsChanged();
            }
        }

        async function editPage(index) {
            const page = pages[index];
            if (!page) {
                showNotification('Page not found', 'error');
                return;
            }

            // Check if another page is currently being edited
            if (window.currentEditingPageIndex !== null && window.currentEditingPageIndex !== undefined) {
                const confirmed = await showConfirmationModal({
                    type: 'warning',
                    title: 'Another Page Being Edited',
                    message: 'You are currently editing another page. Do you want to discard those changes and edit this page instead?',
                    confirmText: 'Yes, Edit This Page',
                    cancelText: 'Cancel'
                });
                
                if (!confirmed) return;
            }

            // Show confirmation modal before opening edit form
            const editConfirmed = await showConfirmationModal({
                type: 'info',
                title: 'Edit Page',
                message: `Are you sure you want to edit "${page.title || 'Untitled Page'}"? This will open the page editor.`,
                confirmText: 'Edit Page',
                cancelText: 'Cancel'
            });

            if (!editConfirmed) return;

            // Validate page data before editing
            if (!page.title && page.title !== '') {
                showNotification('Cannot edit: Page title is missing', 'error');
                return;
            }

            // Store original data for change detection
            window.editPageOriginalData = {
                pageTitle: page.title || '',
                pageType: page.type || 'content',
                pageContent: page.content || '',
                selectedQuestions: page.question_ids ? [...page.question_ids] : []
            };
            
            // Set the modal title
            document.getElementById('pageModalTitle').textContent = `Edit Page: ${window.editPageOriginalData.pageTitle}`;
            
            // Populate the form with existing data
            document.getElementById('pageType').value = window.editPageOriginalData.pageType;
            document.getElementById('pageTitle').value = window.editPageOriginalData.pageTitle;
            
            // Handle content fields
            if (window.editPageOriginalData.pageType === 'content') {
                document.getElementById('contentFields').style.display = 'block';
                document.getElementById('questionFields').style.display = 'none';
                
                // Set content
                document.getElementById('pageContent').value = window.editPageOriginalData.pageContent;
                
                // Set image (if any)
                if (page.image) {
                    // Note: File inputs can't be set programmatically for security reasons
                    // We'll show the current image name if available
                    const imageInput = document.getElementById('pageImage');
                    if (imageInput.nextElementSibling && imageInput.nextElementSibling.classList.contains('current-image')) {
                        imageInput.nextElementSibling.remove();
                    }
                    const currentImageDiv = document.createElement('div');
                    currentImageDiv.className = 'current-image text-sm text-gray-600 mt-1';
                    currentImageDiv.innerHTML = `Current image: ${page.image}`;
                    imageInput.parentNode.appendChild(currentImageDiv);
                }
            } else if (window.editPageOriginalData.pageType === 'question') {
                document.getElementById('contentFields').style.display = 'none';
                document.getElementById('questionFields').style.display = 'block';
                
                // Populate question selection
                populateQuestionCheckboxes();
                
                // Set selected questions
                if (window.editPageOriginalData.selectedQuestions.length > 0) {
                    window.editPageOriginalData.selectedQuestions.forEach(questionId => {
                        const checkbox = document.querySelector(`input[name="question_checkbox"][value="${questionId}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                }
            }
            
            // Store the current editing page index
            window.currentEditingPageIndex = index;
            
            // Show the modal
            document.getElementById('pageModal').classList.remove('hidden');
            
            // Initialize TinyMCE if it's a content page
            if (window.editPageOriginalData.pageType === 'content') {
                // Use the safe wrapper which handles missing functions and retries
                if (typeof window.initPageEditor === 'function') {
                    window.initPageEditor();
                } else {
                    try {
                        if (typeof initializePageContentEditor === 'function') initializePageContentEditor();
                    } catch (e) {
                        console.error('Editor init failed in editPage', e);
                    }
                    if (typeof ensurePageEditorInitialized === 'function') ensurePageEditorInitialized(6, 250);
                }
            }
            
            // Show success notification
            showNotification('Page editor opened', 'success');
        }

        // Global functions for module management
        
        // Global module loading function
        async function loadAvailableCourses() {
            try {
                // Show loading state
                document.getElementById('availableCourses').innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
                        <p>Loading modules...</p>
                    </div>
                `;
                
                // Load all published modules from all teachers for placement test assignment
                const response = await fetch('../../api/courses.php?include_all_published=true', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                
                if (data.success) {
                    allModules = data.data || [];
                    renderEnhancedModuleList();
                    updateModuleStatistics();
                } else {
                    console.error('Failed to load courses:', data.message);
                    document.getElementById('availableCourses').innerHTML = 
                        '<div class="text-center py-8 text-red-500"><p>Failed to load modules</p></div>';
                }
            } catch (error) {
                console.error('Error loading courses:', error);
                document.getElementById('availableCourses').innerHTML = 
                    '<div class="text-center py-8 text-red-500"><p>Error loading modules</p></div>';
            }
        }

        function renderEnhancedModuleList() {
            const container = document.getElementById('availableCourses');
            const filter = document.getElementById('moduleStatusFilter')?.value || 'all';
            
            if (!allModules || allModules.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-gray-500"><p>No modules available</p></div>';
                return;
            }

            const currentModulesForLevel = getCurrentModulesForLevel();
            const ownershipFilter = document.getElementById('moduleOwnershipFilter')?.value || 'all';
            const filteredModules = filterModulesByStatus(allModules, filter, ownershipFilter);

            if (filteredModules.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-gray-500"><p>No modules match the current filter</p></div>';
                return;
            }

            container.innerHTML = filteredModules.map(module => {
                const status = getModuleStatus(module.id);
                const isDisabled = status.assignedToOther;
                const isCurrentlyAssigned = status.assignedToCurrent;
                
                return `
                    <div class="module-item border rounded-lg p-3 transition-all duration-200 ${getModuleItemClasses(status, module)}" 
                         data-module-id="${module.id}" data-status="${status.type}" data-ownership="${module.ownership_type || 'own'}">
                        <label class="flex items-center space-x-3 ${isDisabled ? 'cursor-not-allowed' : 'cursor-pointer'}">
                            <input type="checkbox" 
                                   value="${module.id}" 
                                   data-title="${module.title}" 
                                   class="module-checkbox ${isDisabled ? 'cursor-not-allowed' : ''}"
                                   ${isDisabled ? 'disabled' : ''}
                                   ${isCurrentlyAssigned ? 'checked' : ''}
                                   onchange="handleModuleSelection(this)">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2 flex-1 min-w-0">
                                        <h4 class="font-medium text-gray-900 truncate ${isDisabled ? 'text-gray-400' : ''}">${module.title}</h4>
                                        ${getOwnershipBadge(module)}
                                    </div>
                                    ${getStatusBadge(status)}
                                </div>
                                <p class="text-sm text-gray-600 mt-1 ${isDisabled ? 'text-gray-400' : ''}">${module.description || 'No description available'}</p>
                                <div class="flex items-center justify-between mt-2">
                                    <div class="flex items-center space-x-4 text-xs ${isDisabled ? 'text-gray-300' : 'text-gray-500'}">
                                        <span>ID: ${module.id}</span>
                                        ${module.status ? `<span>Status: ${module.status}</span>` : ''}
                                        ${status.assignedLevel ? `<span class="text-blue-600 font-medium">Assigned to: ${status.assignedLevel}</span>` : ''}
                                    </div>
                                    ${getTeacherInfo(module)}
                                </div>
                            </div>
                        </label>
                    </div>
                `;
            }).join('');

            // Initialize checkbox states for currently assigned modules
            updateSelectedModulesFromCheckboxes();
        }

        // Enhanced helper functions for module management
        function getModuleStatus(moduleId) {
            const currentModules = getCurrentModulesForLevel();
            const assignedToCurrent = currentModules.some(m => m.id == moduleId);
            
            // Check if assigned to other levels
            let assignedToOther = false;
            let assignedLevel = null;
            
            const moduleData = isEditMode ? editModules : modules;
            const levelMapping = {
                'beginner': 'Beginner',
                'intermediate_beginner': 'Intermediate Beginner',
                'advanced_beginner': 'Advanced Beginner'
            };
            
            for (const [level, levelModules] of Object.entries(moduleData)) {
                if (level !== getInternalLevelName() && levelModules.some(m => m.id == moduleId)) {
                    assignedToOther = true;
                    assignedLevel = levelMapping[level] || level;
                    break;
                }
            }
            
            return {
                assignedToCurrent,
                assignedToOther,
                assignedLevel,
                type: assignedToCurrent ? 'current' : (assignedToOther ? 'other' : 'available')
            };
        }
        
        function getModuleItemClasses(status, module) {
            if (status.assignedToOther) {
                return 'border-gray-200 bg-gray-50 opacity-60';
            } else if (status.assignedToCurrent) {
                return 'border-green-300 bg-green-50';
            } else {
                return 'border-gray-200 hover:border-blue-300';
            }
        }
        
        function getStatusBadge(status) {
            if (status.assignedToCurrent) {
                return '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Currently Assigned</span>';
            } else if (status.assignedToOther) {
                return '<span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">Assigned to Other</span>';
            } else {
                return '<span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Available</span>';
            }
        }
        
        function getOwnershipBadge(module) {
            if (module.ownership_type === 'other') {
                return '<span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">' +
                       '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">' +
                       '<path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd"></path>' +
                       '</svg>Shared</span>';
            } else {
                return '<span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">' +
                       '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">' +
                       '<path fillRule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clipRule="evenodd"></path>' +
                       '</svg>Your Module</span>';
            }
        }
        
        function getTeacherInfo(module) {
            if (module.ownership_type === 'other') {
                return `<div class="text-xs text-purple-600 font-medium flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd"></path>
                    </svg>
                    by ${module.teacher_display_name || 'Other Teacher'}
                </div>`;
            }
            return '';
        }
        
        function getCurrentModulesForLevel() {
            const moduleData = isEditMode ? editModules : modules;
            const internalLevel = getInternalLevelName();
            return moduleData[internalLevel] || [];
        }
        
        function getInternalLevelName() {
            const levelMapping = {
                'beginner': 'beginner',
                'intermediate': 'intermediate_beginner',
                'advanced': 'advanced_beginner'
            };
            const result = levelMapping[currentLevel] || currentLevel;
            console.log('getInternalLevelName:', { currentLevel, result });
            return result;
        }
        
        function filterModulesByStatus(modulesList, statusFilter, ownershipFilter = 'all') {
            return modulesList.filter(module => {
                // Status filtering
                let passesStatusFilter = true;
                if (statusFilter !== 'all') {
                    const status = getModuleStatus(module.id);
                    switch (statusFilter) {
                        case 'available': 
                            passesStatusFilter = status.type === 'available';
                            break;
                        case 'assigned': 
                            passesStatusFilter = status.assignedToCurrent;
                            break;
                        case 'other-levels': 
                            passesStatusFilter = status.assignedToOther;
                            break;
                        default: 
                            passesStatusFilter = true;
                    }
                }
                
                // Ownership filtering
                let passesOwnershipFilter = true;
                if (ownershipFilter !== 'all') {
                    switch (ownershipFilter) {
                        case 'own':
                            passesOwnershipFilter = module.ownership_type === 'own';
                            break;
                        case 'other':
                            passesOwnershipFilter = module.ownership_type === 'other';
                            break;
                        default:
                            passesOwnershipFilter = true;
                    }
                }
                
                return passesStatusFilter && passesOwnershipFilter;
            });
        }

        // Module selection and bulk action handlers
        function handleModuleSelection(checkbox) {
            const moduleId = parseInt(checkbox.value);
            
            if (checkbox.checked) {
                selectedModules.add(moduleId);
            } else {
                selectedModules.delete(moduleId);
            }
            
            updateBulkActionButtons();
            updateModuleStatistics();
            autoSaveModuleAssignments();
        }
        
        function updateSelectedModulesFromCheckboxes() {
            selectedModules.clear();
            document.querySelectorAll('.module-checkbox:checked').forEach(checkbox => {
                if (!checkbox.disabled) {
                    selectedModules.add(parseInt(checkbox.value));
                }
            });
            updateBulkActionButtons();
        }
        
        function updateBulkActionButtons() {
            const selectedCount = selectedModules.size;
            const assignButton = document.getElementById('confirmModuleSelection');
            const removeButton = document.getElementById('removeSelectedModules');
            const selectedCountSpan = document.getElementById('selectedCount');
            const bulkSummary = document.getElementById('bulkActionSummary');
            
            if (!assignButton || !removeButton || !selectedCountSpan || !bulkSummary) return;
            
            selectedCountSpan.textContent = `${selectedCount} selected`;
            
            if (selectedCount > 0) {
                const currentModules = getCurrentModulesForLevel();
                const selectedAssigned = Array.from(selectedModules).filter(id => 
                    currentModules.some(m => m.id == id)
                );
                const selectedUnassigned = Array.from(selectedModules).filter(id => 
                    !currentModules.some(m => m.id == id)
                );
                
                assignButton.disabled = selectedUnassigned.length === 0;
                removeButton.disabled = selectedAssigned.length === 0;
                
                const parts = [];
                if (selectedUnassigned.length > 0) parts.push(`${selectedUnassigned.length} to assign`);
                if (selectedAssigned.length > 0) parts.push(`${selectedAssigned.length} to remove`);
                bulkSummary.textContent = parts.join(', ') || 'Select modules to assign or remove';
            } else {
                assignButton.disabled = true;
                removeButton.disabled = true;
                bulkSummary.textContent = 'Select modules to assign or remove';
            }
        }
        
        function updateModuleStatistics() {
            const total = allModules.length;
            const currentModules = getCurrentModulesForLevel();
            const assigned = currentModules.length;
            const available = total - Object.values(isEditMode ? editModules : modules)
                .flat().length;
            
            // Count ownership types
            const ownModules = allModules.filter(m => m.ownership_type === 'own').length;
            const sharedModules = allModules.filter(m => m.ownership_type === 'other').length;
            
            const totalEl = document.getElementById('totalModulesCount');
            const assignedEl = document.getElementById('assignedModulesCount');
            const availableEl = document.getElementById('availableModulesCount');
            const ownEl = document.getElementById('ownModulesCount');
            const sharedEl = document.getElementById('sharedModulesCount');
            
            if (totalEl) totalEl.textContent = total;
            if (assignedEl) assignedEl.textContent = assigned;
            if (availableEl) availableEl.textContent = Math.max(0, available);
            if (ownEl) ownEl.textContent = ownModules;
            if (sharedEl) sharedEl.textContent = sharedModules;
            
            // Update the statistics summary if it exists
            const summaryElement = document.querySelector('.module-statistics-summary');
            if (summaryElement) {
                summaryElement.innerHTML = `
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="flex items-center text-blue-600">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clipRule="evenodd"></path>
                            </svg>
                            <span class="font-semibold">${ownModules}</span> Your Modules
                        </div>
                        <div class="flex items-center text-purple-600">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clipRule="evenodd"></path>
                            </svg>
                            <span class="font-semibold">${sharedModules}</span> Shared Modules
                        </div>
                        <div class="flex items-center text-green-600">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M5 13l4 4L19 7l-1.5-1.5L9 14l-2.5-2.5L5 13z" clipRule="evenodd"></path>
                            </svg>
                            <span class="font-semibold">${assigned}</span> Assigned
                        </div>
                        <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd"></path>
                            </svg>
                            <span class="font-semibold">${Math.max(0, available)}</span> Available
                        </div>
                    </div>
                `;
            }
        }

        // Bulk operations
        async function performBulkAssignment() {
            try {
                const moduleData = isEditMode ? editModules : modules;
                const internalLevel = getInternalLevelName();
                
                console.log('Performing bulk assignment:', {
                    isEditMode: isEditMode,
                    internalLevel: internalLevel,
                    selectedModules: Array.from(selectedModules),
                    currentLevel: currentLevel
                });
                
                if (!moduleData[internalLevel]) {
                    moduleData[internalLevel] = [];
                }
                
                let assignedCount = 0;
                const currentModuleIds = getCurrentModulesForLevel().map(m => m.id);
                
                for (const moduleId of selectedModules) {
                    if (!currentModuleIds.includes(moduleId)) {
                        const module = allModules.find(m => m.id == moduleId);
                        if (module) {
                            moduleData[internalLevel].push({
                                id: moduleId,
                                title: module.title
                            });
                            assignedCount++;
                        }
                    }
                }
                
                console.log(`Assigned ${assignedCount} modules to ${internalLevel}`);
                
                // Update UI
                if (isEditMode) {
                    renderEditAssignedModules(internalLevel);
                } else {
                    renderAssignedModules(internalLevel);
                }
                
                // Mark as changed and auto-save
                markAsChanged();
                autoSaveModuleAssignments();
                
                showNotification(`${assignedCount} module(s) assigned successfully`, 'success');
                
            } catch (error) {
                console.error('Error in performBulkAssignment:', error);
                showNotification('Failed to assign modules', 'error');
            }
        }
        
        async function performBulkRemoval() {
            const moduleData = isEditMode ? editModules : modules;
            const internalLevel = getInternalLevelName();
            
            if (!moduleData[internalLevel]) return;
            
            let removedCount = 0;
            const selectedIds = Array.from(selectedModules);
            
            moduleData[internalLevel] = moduleData[internalLevel].filter(module => {
                const shouldRemove = selectedIds.includes(parseInt(module.id));
                if (shouldRemove) removedCount++;
                return !shouldRemove;
            });
            
            // Clear selection
            selectedModules.clear();
            
            // Update UI
            if (isEditMode) {
                renderEditAssignedModules(internalLevel);
            } else {
                renderAssignedModules(internalLevel);
            }
            
            // Mark as changed
            markAsChanged();
            
            showNotification(`${removedCount} module(s) removed successfully`, 'success');
        }

        // Auto-save functionality
        function autoSaveModuleAssignments() {
            // Only auto-save if we have changes and are in edit mode
            if (isEditMode && editCurrentTestId) {
                clearTimeout(window.autoSaveTimeout);
                window.autoSaveTimeout = setTimeout(() => {
                    saveModuleAssignments();
                }, 2000); // Auto-save after 2 seconds of inactivity
            }
        }
        
        async function saveModuleAssignments() {
            try {
                const testId = isEditMode ? editCurrentTestId : currentTestId;
                const assignments = isEditMode ? editModules : modules;
                
                if (!testId) {
                    console.log('No test ID available for auto-save');
                    return;
                }
                
                console.log('Saving module assignments:', {
                    testId: testId,
                    isEditMode: isEditMode,
                    assignments: assignments
                });
                
                const response = await fetch('api/save_module_assignments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        test_id: testId,
                        assignments: assignments
                    }),
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    // Try to get the error response even if status is not OK
                    let errorMessage = `HTTP error! status: ${response.status}`;
                    try {
                        const errorData = await response.json();
                        errorMessage = errorData.error || errorMessage;
                        console.error('API Error Response:', errorData);
                    } catch (e) {
                        console.error('Could not parse error response');
                    }
                    throw new Error(errorMessage);
                }
                
                const data = await response.json();
                if (data.success) {
                    console.log('Module assignments auto-saved successfully');
                    
                    // Update the UI to reflect the changes
                    const currentModules = isEditMode ? editModules : modules;
                    const totalCount = Object.values(currentModules).reduce((sum, level) => sum + (level ? level.length : 0), 0);
                    
                    // Show success notification with count
                    showNotification(`${totalCount} module(s) assigned successfully`, 'success');
                    
                    // Refresh the module display
                    if (isEditMode) {
                        renderAssignedModules('beginner');
                        renderAssignedModules('intermediate_beginner');
                        renderAssignedModules('advanced_beginner');
                    } else {
                        renderAssignedModules('beginner');
                        renderAssignedModules('intermediate_beginner');
                        renderAssignedModules('advanced_beginner');
                    }
                } else {
                    console.error('API Error:', data);
                    throw new Error(data.error || 'Failed to save module assignments');
                }
            } catch (error) {
                console.error('Error saving module assignments:', error);
                console.error('Full error details:', {
                    message: error.message,
                    testId: isEditMode ? editCurrentTestId : currentTestId,
                    isEditMode: isEditMode,
                    assignments: isEditMode ? editModules : modules
                });
                showNotification(`Failed to auto-save module assignments: ${error.message}`, 'warning');
            }
        }

        function renderAssignedModules(level) {
            // Map internal level names to container IDs
            const containerMapping = {
                'beginner': 'beginnerModules',
                'intermediate_beginner': 'intermediateModules',
                'advanced_beginner': 'advancedModules'
            };
            
            const containerId = containerMapping[level] || (level + 'Modules');
            const container = document.getElementById(containerId);
            if (!container) return;
            
            if (!modules[level] || modules[level].length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <p class="text-sm">No modules assigned</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = modules[level].map((module, index) => `
                <div class="module-item bg-white border border-gray-200 rounded-lg p-3 flex items-center justify-between">
                    <div class="flex-1">
                        <h5 class="font-medium text-gray-900">${module.title}</h5>
                        <p class="text-xs text-gray-500">ID: ${module.id}</p>
                    </div>
                    <button onclick="removeModuleFromLevel('${level}', ${index})" class="text-red-500 hover:text-red-700 p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            `).join('');
        }

        async function removeModuleFromLevel(level, index) {
            // Map display level names back to internal names
            const levelMapping = {
                'beginner': 'beginner',
                'intermediate': 'intermediate_beginner',
                'advanced': 'advanced_beginner',
                'intermediate_beginner': 'intermediate_beginner',
                'advanced_beginner': 'advanced_beginner'
            };
            
            const internalLevel = levelMapping[level] || level;
            console.log('Removing module from level:', level, 'mapped to:', internalLevel);
            
            const confirmed = await showConfirmationModal({
                type: 'warning',
                title: 'Remove Module',
                message: `Are you sure you want to remove this module from ${level} level?`,
                confirmText: 'Remove',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                modules[internalLevel].splice(index, 1);
                renderAssignedModules(internalLevel);
            }
        }
        
        
        function resetCreateTestForm() {
            // Reset form fields
            document.getElementById('testTitle').value = '';
            
            // Check if description and instructions fields exist before resetting
            const descriptionField = document.getElementById('testDescription');
            const instructionsField = document.getElementById('testInstructions');
            
            if (descriptionField) {
                descriptionField.value = '';
            }
            if (instructionsField) {
                instructionsField.value = '';
            }
            
            // Reset arrays
            questions = [];
            pages = [];
            modules = {
                'beginner': [],
                'intermediate_beginner': [],
                'advanced_beginner': []
            };
            images = [];
            currentTestId = null;
            
            // Reset UI - keep button text unchanged
            
            // Re-render empty sections
            renderQuestions();
            renderPages();
            renderAssignedModules('beginner');
            renderAssignedModules('intermediate_beginner');
            renderAssignedModules('advanced_beginner');
        }

        async function publishTest(testId) {
            // Check publish permission
            if (!window.placementTestPermissions.canPublish) {
                showNotification('You do not have permission to publish placement tests.', 'error');
                return;
            }
            
            const confirmed = await showConfirmationModal({
                type: 'success',
                title: 'Publish Test',
                message: 'Are you sure you want to publish this test? It will be visible to students.',
                confirmText: 'Publish',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                // If we're in edit mode, save the current test data first, then publish
                if (currentTestId === testId) {
                    console.log('Publishing test in edit mode - saving current data first');
                    // Save the current test data with published status
                    await saveTestWithStatus('published');
                } else {
                    // If we're not in edit mode, just update the status
                    updateTestStatus(testId, 'published');
                }
            }
        }

        async function saveTestWithStatus(status) {
            // Check if user has create or edit permission
            if (!window.placementTestPermissions.canCreate && !window.placementTestPermissions.canEdit) {
                showNotification('You do not have permission to save placement tests.', 'error');
                return;
            }
            
            const title = document.getElementById('testTitle').value.trim();
            
            // Basic validation
            if (!title) {
                showNotification('Please enter a test title', 'error');
                return;
            }

            // Create test data object
            const testData = {
                title: title,
                status: status,
                questions: questions,
                pages: pages,
                modules: modules,
                design_settings: {
                    header_color: '#1f2937',
                    header_text_color: '#ffffff',
                    background_color: '#f5f5f5',
                    accent_color: '#dc2626',
                    font_family: 'Inter',
                    button_color: '#dc2626'
                }
            };
            
            // Add test ID for update
            if (currentTestId) {
                testData.test_id = currentTestId;
                // Keep the original status (published/draft) instead of 'update'
                // This ensures the is_published field is set correctly
            }

            console.log('Saving test with status:', status, testData);
            
            try {
                const response = await fetch('api/save_placement_test.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(testData)
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    // Update preview button based on status
                    if (status === 'published') {
                        updatePreviewButton(true, currentTestId);
                    } else {
                        updatePreviewButton(false);
                    }
                    
                    // Mark as saved
                    markAsSaved();
                    
                    // Reload dashboard data to reflect changes
                    loadDashboardData();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error saving test:', error);
                showNotification('Error saving test', 'error');
            }
        }

        async function unpublishTest(testId) {
            // Check publish permission (same permission used for unpublish)
            if (!window.placementTestPermissions.canPublish) {
                showNotification('You do not have permission to unpublish placement tests.', 'error');
                return;
            }
            
            const confirmed = await showConfirmationModal({
                type: 'warning',
                title: 'Unpublish Test',
                message: 'Are you sure you want to unpublish this test? It will no longer be visible to students.',
                confirmText: 'Unpublish',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                updateTestStatus(testId, 'draft');
            }
        }

        function updateCurrentTestStatus(status) {
            // Update the current test's status in the edit form
            // This ensures that when we publish/unpublish a test while editing it,
            // the status is properly reflected in the UI
            
            console.log('Updating current test status to:', status);
            
            // Update the preview button based on the new status
            if (status === 'published') {
                updatePreviewButton(true, currentTestId);
            } else {
                updatePreviewButton(false);
            }
            
            // Mark as saved since the status change is now reflected
            markAsSaved();
        }

        async function archiveTest(testId, currentStatus) {
            // Check delete permission (archive uses same permission)
            if (!window.placementTestPermissions.canDelete) {
                showNotification('You do not have permission to archive placement tests.', 'error');
                return;
            }
            
            if (currentStatus === 'published') {
                showNotification('Please unpublish the test first before archiving it.', 'warning');
                return;
            }
            
            const confirmed = await showConfirmationModal({
                type: 'danger',
                title: 'Archive Test',
                message: 'Are you sure you want to archive this test? This action cannot be undone.',
                confirmText: 'Archive',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                updateTestStatus(testId, 'archived');
            }
        }

        function updateTestStatus(testId, status) {
            fetch('api/update_test_status.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    test_id: testId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    // Update preview button in real-time based on status
                    if (status === 'draft' || status === 'archived') {
                        // If test is unpublished or archived, disable preview button
                        updatePreviewButton(false);
                    } else if (status === 'published') {
                        // If test is published, enable preview button
                        updatePreviewButton(true, testId);
                    }
                    
                    // If we're currently editing this test, update the current test status
                    if (currentTestId === testId) {
                        // Update the current test's status in the edit form
                        updateCurrentTestStatus(status);
                    }
                    
                    loadDashboardData(); // Reload the tests list
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error updating test status:', error);
                showNotification('Error updating test status', 'error');
            });
        }

        async function restoreTest(testId) {
            // Check edit permission (restore requires edit capabilities)
            if (!window.placementTestPermissions.canEdit) {
                showNotification('You do not have permission to restore placement tests.', 'error');
                return;
            }
            
            const confirmed = await showConfirmationModal({
                type: 'success',
                title: 'Restore Test',
                message: 'Are you sure you want to restore this test from archive?',
                confirmText: 'Restore',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                updateTestStatus(testId, 'draft');
            }
        }

        async function deleteTest(testId) {
            // Check delete permission
            if (!window.placementTestPermissions.canDelete) {
                showNotification('You do not have permission to delete placement tests.', 'error');
                return;
            }
            
            const confirmed = await showConfirmationModal({
                type: 'danger',
                title: 'Delete Test',
                message: 'Are you sure you want to permanently delete this test? This action cannot be undone.',
                confirmText: 'Delete',
                cancelText: 'Cancel'
            });
            
            if (confirmed) {
                console.log('Deleting test with ID:', testId);
                
                fetch('api/delete_test.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        test_id: testId
                    })
                })
                .then(response => {
                    console.log('Delete response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Delete response data:', data);
                    if (data.success) {
                        showNotification(data.message, 'success');
                        loadDashboardData(); // Reload the tests list
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting test:', error);
                    showNotification('Error deleting test: ' + error.message, 'error');
                });
            }
        }


        // Modern Confirmation Modal System (Global Functions)
        function showConfirmationModal(options) {
            
            const modal = document.getElementById('confirmationModal');
            const modalContent = document.getElementById('confirmationModalContent');
            const icon = document.getElementById('confirmationIcon');
            const title = document.getElementById('confirmationTitle');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmationConfirm');
            const cancelBtn = document.getElementById('confirmationCancel');

            // Debug: Check if elements exist
            if (!modal) {
                console.error('confirmationModal element not found');
                return Promise.resolve(false);
            }
            if (!modalContent) {
                console.error('confirmationModalContent element not found');
                return Promise.resolve(false);
            }
            if (!icon) {
                console.error('confirmationIcon element not found');
            }
            if (!title) {
                console.error('confirmationTitle element not found');
            }
            if (!message) {
                console.error('confirmationMessage element not found');
            }
            if (!confirmBtn) {
                console.error('confirmationConfirm element not found');
            }
            if (!cancelBtn) {
                console.error('confirmationCancel element not found');
            }

            // Set content
            if (title) title.textContent = options.title || 'Confirm Action';
            if (message) message.textContent = options.message || 'Are you sure you want to proceed?';
            if (confirmBtn) confirmBtn.textContent = options.confirmText || 'Confirm';
            if (cancelBtn) cancelBtn.textContent = options.cancelText || 'Cancel';

            // Set icon and colors based on type
            const iconElement = icon ? icon.querySelector('i') : null;
            if (iconElement) iconElement.className = 'w-6 h-6';
            
            switch (options.type) {
                case 'warning':
                    if (icon) icon.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-yellow-100';
                    if (iconElement) iconElement.setAttribute('data-lucide', 'alert-triangle');
                    if (confirmBtn) confirmBtn.className = 'flex-1 px-4 py-2 text-white bg-yellow-600 hover:bg-yellow-700 rounded-lg font-medium transition-colors duration-200';
                    break;
                case 'danger':
                    if (icon) icon.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100';
                    if (iconElement) iconElement.setAttribute('data-lucide', 'alert-circle');
                    if (confirmBtn) confirmBtn.className = 'flex-1 px-4 py-2 text-white bg-red-600 hover:bg-red-700 rounded-lg font-medium transition-colors duration-200';
                    break;
                case 'success':
                    if (icon) icon.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-green-100';
                    if (iconElement) iconElement.setAttribute('data-lucide', 'check-circle');
                    if (confirmBtn) confirmBtn.className = 'flex-1 px-4 py-2 text-white bg-green-600 hover:bg-green-700 rounded-lg font-medium transition-colors duration-200';
                    break;
                default:
                    if (icon) icon.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-blue-100';
                    if (iconElement) iconElement.setAttribute('data-lucide', 'help-circle');
                    if (confirmBtn) confirmBtn.className = 'flex-1 px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition-colors duration-200';
            }

            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
                
                // Re-initialize Lucide icons for the modal
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 10);

            // Set up event listeners
            return new Promise((resolve) => {
                const handleConfirm = () => {
                    hideConfirmationModal();
                    resolve(true);
                };

                const handleCancel = () => {
                    hideConfirmationModal();
                    resolve(false);
                };

                if (confirmBtn) confirmBtn.onclick = handleConfirm;
                if (cancelBtn) cancelBtn.onclick = handleCancel;

                // Handle escape key
                const handleEscape = (e) => {
                    if (e.key === 'Escape') {
                        handleCancel();
                        document.removeEventListener('keydown', handleEscape);
                    }
                };
                document.addEventListener('keydown', handleEscape);

                // Handle backdrop click
                const handleBackdrop = (e) => {
                    if (e.target === modal) {
                        handleCancel();
                    }
                };
                modal.onclick = handleBackdrop;
            });
        }

        function hideConfirmationModal() {
            const modal = document.getElementById('confirmationModal');
            const modalContent = document.getElementById('confirmationModalContent');
            
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // Unsaved Changes Management System
        let hasUnsavedChanges = false;
        let pendingAction = null;

        function showUnsavedChangesModal(action, customMessage = null) {
            const modal = document.getElementById('unsavedChangesModal');
            const modalContent = document.getElementById('unsavedChangesModalContent');
            const message = document.getElementById('unsavedChangesMessage');
            const leaveBtn = document.getElementById('unsavedChangesLeave');
            const saveBtn = document.getElementById('unsavedChangesSave');
            const cancelBtn = document.getElementById('unsavedChangesCancel');

            if (!modal || !modalContent) {
                console.error('Unsaved changes modal elements not found');
                return Promise.resolve(false);
            }

            // Set custom message if provided
            if (customMessage && message) {
                message.textContent = customMessage;
            }

            // Store the pending action
            pendingAction = action;

            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
                
                // Re-initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 10);

            return new Promise((resolve) => {
                const handleLeave = () => {
                    hideUnsavedChangesModal();
                    hasUnsavedChanges = false;
                    
                    // Handle browser navigation case
                    if (action === 'browser_navigation') {
                        // Allow the browser to proceed with navigation
                        window.location.reload();
                    }
                    
                    resolve('leave');
                };

                const handleSave = () => {
                    hideUnsavedChangesModal();
                    // Save as draft and then proceed
                    saveTest('draft').then(() => {
                        hasUnsavedChanges = false;
                        
                        // Handle browser navigation case
                        if (action === 'browser_navigation') {
                            window.location.reload();
                        }
                        
                        resolve('save');
                    });
                };

                const handleCancel = () => {
                    hideUnsavedChangesModal();
                    resolve('cancel');
                };

                if (leaveBtn) leaveBtn.onclick = handleLeave;
                if (saveBtn) saveBtn.onclick = handleSave;
                if (cancelBtn) cancelBtn.onclick = handleCancel;

                // Handle escape key
                const handleEscape = (e) => {
                    if (e.key === 'Escape') {
                        handleCancel();
                        document.removeEventListener('keydown', handleEscape);
                    }
                };
                document.addEventListener('keydown', handleEscape);

                // Handle backdrop click
                const handleBackdrop = (e) => {
                    if (e.target === modal) {
                        handleCancel();
                    }
                };
                modal.onclick = handleBackdrop;
            });
        }

        function hideUnsavedChangesModal() {
            const modal = document.getElementById('unsavedChangesModal');
            const modalContent = document.getElementById('unsavedChangesModalContent');
            
            if (modalContent) {
                modalContent.classList.remove('scale-100', 'opacity-100');
                modalContent.classList.add('scale-95', 'opacity-0');
            }
            
            setTimeout(() => {
                if (modal) modal.classList.add('hidden');
            }, 300);
        }

        function markAsChanged() {
            hasUnsavedChanges = true;
        }

        function markAsSaved() {
            hasUnsavedChanges = false;
        }

        function checkUnsavedChanges(action) {
            if (hasUnsavedChanges) {
                return showUnsavedChangesModal(action);
            }
            return Promise.resolve('proceed');
        }

        // Refresh Modal Functions
        function showRefreshModal() {
            const modal = document.getElementById('refreshModal');
            const modalContent = document.getElementById('refreshModalContent');
            const cancelBtn = document.getElementById('refreshCancel');
            const confirmBtn = document.getElementById('refreshConfirm');

            if (!modal || !modalContent) {
                console.error('Refresh modal elements not found');
                return Promise.resolve(false);
            }

            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
                
                // Re-initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 10);

            return new Promise((resolve) => {
                const handleCancel = () => {
                    hideRefreshModal();
                    resolve('cancel');
                };

                const handleConfirm = () => {
                    hideRefreshModal();
                    hasUnsavedChanges = false;
                    window.location.reload();
                    resolve('confirm');
                };

                if (cancelBtn) cancelBtn.onclick = handleCancel;
                if (confirmBtn) confirmBtn.onclick = handleConfirm;

                // Handle escape key
                const handleEscape = (e) => {
                    if (e.key === 'Escape') {
                        handleCancel();
                        document.removeEventListener('keydown', handleEscape);
                    }
                };
                document.addEventListener('keydown', handleEscape);

                // Handle backdrop click
                const handleBackdrop = (e) => {
                    if (e.target === modal) {
                        handleCancel();
                    }
                };
                modal.onclick = handleBackdrop;
            });
        }

        function hideRefreshModal() {
            const modal = document.getElementById('refreshModal');
            const modalContent = document.getElementById('refreshModalContent');
            
            if (modalContent) {
                modalContent.classList.remove('scale-100', 'opacity-100');
                modalContent.classList.add('scale-95', 'opacity-0');
            }
            
            setTimeout(() => {
                if (modal) modal.classList.add('hidden');
            }, 300);
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const container = document.getElementById('notificationContainer');
            if (!container) {
                // Create notification container if it doesn't exist
                const notificationContainer = document.createElement('div');
                notificationContainer.id = 'notificationContainer';
                notificationContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
                document.body.appendChild(notificationContainer);
            }

            const notification = document.createElement('div');
            notification.className = `px-6 py-4 rounded-lg shadow-lg text-white font-medium transform translate-x-full transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500' :
                type === 'error' ? 'bg-red-500' :
                type === 'warning' ? 'bg-yellow-500' :
                'bg-blue-500'
            }`;

            notification.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${type === 'success' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>' :
                          type === 'error' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>' :
                          type === 'warning' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>' :
                          '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'}
                    </svg>
                    ${message}
                </div>
            `;

            document.getElementById('notificationContainer').appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
    </script>

    <!-- Notification Container -->
    <div id="notificationContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <!-- Modern Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden" style="z-index: 99999;">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="confirmationModalContent">
            <div class="p-6">
                <!-- Icon -->
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full" id="confirmationIcon">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                
                <!-- Title -->
                <h3 class="text-lg font-semibold text-gray-900 text-center mb-2" id="confirmationTitle">
                    Confirm Action
                </h3>
                
                <!-- Message -->
                <p class="text-gray-600 text-center mb-6" id="confirmationMessage">
                    Are you sure you want to proceed?
                </p>
                
                <!-- Buttons -->
                <div class="flex space-x-3">
                    <button type="button" id="confirmationCancel" class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="button" id="confirmationConfirm" class="flex-1 px-4 py-2 text-white rounded-lg font-medium transition-colors duration-200">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Modal -->
<div id="unsavedChangesModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden" style="z-index: 99999;">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="unsavedChangesModalContent">
            <div class="p-5">
                <!-- Icon -->
                <div class="flex items-center justify-center w-10 h-10 mx-auto mb-3 rounded-full bg-orange-100">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-orange-600"></i>
                </div>
                
                <!-- Title -->
                <h3 class="text-base font-semibold text-gray-900 text-center mb-2">
                    Unsaved Changes
                </h3>
                
                <!-- Message -->
                <p class="text-sm text-gray-600 text-center mb-5" id="unsavedChangesMessage">
                    You have unsaved changes. Are you sure you want to leave without saving?
                </p>
                
                <!-- Buttons -->
                <div class="flex space-x-2">
                    <button type="button" id="unsavedChangesLeave" class="flex-1 px-3 py-2 text-sm text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors duration-200">
                        Leave
                    </button>
                    <button type="button" id="unsavedChangesSave" class="flex-1 px-3 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition-colors duration-200">
                        Save Draft
                    </button>
                    <button type="button" id="unsavedChangesCancel" class="flex-1 px-3 py-2 text-sm text-white bg-orange-600 hover:bg-orange-700 rounded-lg font-medium transition-colors duration-200">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Refresh Confirmation Modal -->
<div id="refreshModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden" style="z-index: 99999;">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="refreshModalContent">
            <div class="p-5">
                <!-- Icon -->
                <div class="flex items-center justify-center w-10 h-10 mx-auto mb-3 rounded-full bg-blue-100">
                    <i data-lucide="refresh-cw" class="w-5 h-5 text-blue-600"></i>
                </div>
                
                <!-- Title -->
                <h3 class="text-base font-semibold text-gray-900 text-center mb-2">
                    Reload Page?
                </h3>
                
                <!-- Message -->
                <p class="text-sm text-gray-600 text-center mb-5">
                    Changes you made may not be saved.
                </p>
                
                <!-- Buttons -->
                <div class="flex space-x-2">
                    <button type="button" id="refreshCancel" class="flex-1 px-3 py-2 text-sm text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="button" id="refreshConfirm" class="flex-1 px-3 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition-colors duration-200">
                        Reload
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

