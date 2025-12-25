<?php
function get_user_permissions($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function is_hybrid_teacher($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() > 0;
}

$user_permissions = get_user_permissions($pdo, $_SESSION['user_id']);
$is_hybrid = is_hybrid_teacher($pdo, $_SESSION['user_id']);

// Set theme colors based on role and permissions
$theme_color = $is_hybrid ? 'text-purple-600' : 'text-emerald-600';
$hover_color = $is_hybrid ? 'hover:text-purple-700' : 'hover:text-emerald-700';
?>

<!-- Sidebar Navigation -->
<div class="w-64 bg-white h-screen shadow-lg">
    <nav class="mt-5">
        <?php if (in_array('nav_dashboard', $user_permissions)): ?>
            <a href="teacher_dashboard.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 <?php echo $hover_color; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
        <?php endif; ?>

        <?php if (in_array('nav_courses', $user_permissions)): ?>
            <a href="teacher_courses.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 <?php echo $hover_color; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Courses
            </a>
        <?php endif; ?>

        <?php if (in_array('nav_content', $user_permissions)): ?>
            <a href="teacher_content.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 <?php echo $hover_color; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Content
            </a>
        <?php endif; ?>

        <?php if (in_array('nav_users', $user_permissions)): ?>
            <a href="teacher_users.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 <?php echo $hover_color; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Users
                <?php if ($is_hybrid): ?>
                    <span class="ml-2 px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">Admin</span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('nav_reports', $user_permissions)): ?>
            <a href="teacher_reports.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 <?php echo $hover_color; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Reports
                <?php if ($is_hybrid): ?>
                    <span class="ml-2 px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">Admin</span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('nav_audit', $user_permissions)): ?>
            <a href="teacher_audit.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 <?php echo $hover_color; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Audit Trail
            </a>
        <?php endif; ?>

        <?php if (in_array('nav_settings', $user_permissions)): ?>
            <a href="teacher_settings.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 <?php echo $hover_color; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
        <?php endif; ?>
    </nav>
</div> 