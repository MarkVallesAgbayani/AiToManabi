<?php
if (!isset($current_section)) {
    return;
}
?>
<div id="content-area" 
     class="min-h-[50vh] text-lg leading-relaxed text-gray-700 dark:text-gray-300"
     x-data="{ currentSection: null }"
     x-init="currentSection = $store.content.activeContent">
    
    <!-- Quiz Content -->
    <div x-show="$store.content.showQuiz" x-cloak>
        <?php
        // Get quiz for current section
        $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE section_id = ?");
        $stmt->execute([$current_section['id']]);
        $quiz = $stmt->fetch();
        
        if ($quiz) {
            include 'quiz.php';
        } else {
            echo '<div class="text-center py-12">
                    <p class="text-gray-500 dark:text-gray-400">No quiz available for this section.</p>
                  </div>';
        }
        ?>
    </div>

    <!-- Regular Content -->
    <div x-show="!$store.content.showQuiz">
        <?php if ($current_section): ?>
            <?php
            // Get the chapter content for this section
            $current_chapter = null;
            foreach ($chapters as $chapter) {
                foreach ($chapter['sections'] as $section) {
                    if ($section['id'] === $current_section['id']) {
                        $current_chapter = $chapter;
                        break 2;
                    }
                }
            }
            if ($current_chapter): ?>
                <div class="text-gray-600 dark:text-gray-400">
                    <h2 class="text-2xl font-semibold mb-4 text-center" 
                        style="font-family: 'Yu Mincho', 'Hiragino Mincho Pro', serif; color: #B22222;">
                        <?php echo htmlspecialchars($current_chapter['title'] ?? ''); ?>
                    </h2>
                    <div class="mb-6 text-lg leading-relaxed">
                        <?php 
                        if (isset($current_chapter['content']) && $current_chapter['content'] !== null) {
                            echo nl2br(htmlspecialchars($current_chapter['content']));
                        } else {
                            echo '<p class="text-gray-500">No content available for this chapter.</p>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-12">
                <p class="text-gray-500 dark:text-gray-400">
                    Select a section from the sidebar to view its content
                </p>
            </div>
        <?php endif; ?>
    </div>
</div> 