<?php
// Sidebar profile snippet - uses admin_profile_functions.php
if (!isset($pdo)) {
    // Expect $pdo to be available in pages that include this file
    throw new Exception('PDO instance $pdo is required to render sidebar profile');
}
require_once __DIR__ . '/admin_profile_functions.php';

$admin_profile = getAdminProfile($pdo, $_SESSION['user_id']);
$display_name = getAdminDisplayName($admin_profile);
$picture = getAdminProfilePicture($admin_profile);
?>
<div class="p-4 border-b flex items-center space-x-3">
    <?php if ($picture['has_image']): ?>
        <img src="<?php echo '../' . htmlspecialchars($picture['image_path']); ?>" 
             alt="Profile Picture" 
             class="w-12 h-12 rounded-full object-cover shadow-md sidebar-profile-picture"
             onerror="console.error('Failed to load image:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-lg shadow-md sidebar-profile-placeholder" style="display: none;">
            <?php echo htmlspecialchars($picture['initial']); ?>
        </div>
    <?php else: ?>
        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-lg shadow-md sidebar-profile-placeholder">
            <?php echo htmlspecialchars($picture['initial']); ?>
        </div>
    <?php endif; ?>
    <div class="flex-1 min-w-0">
        <div class="font-medium sidebar-display-name truncate"><?php echo htmlspecialchars($display_name); ?></div>
        <div class="text-sm text-gray-500 sidebar-role">Administrator</div>
    </div>
</div>