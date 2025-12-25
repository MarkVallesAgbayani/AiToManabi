<?php
if (!isset($current_section)) {
    return;
}

// Get quiz for current section
$stmt = $pdo->prepare("
    SELECT q.*, COUNT(qq.id) as question_count 
    FROM quizzes q 
    LEFT JOIN quiz_questions qq ON q.id = qq.quiz_id 
    WHERE q.section_id = ? 
    GROUP BY q.id
");
$stmt->execute([$current_section['id']]);
$quiz = $stmt->fetch();

if ($quiz): ?>
    <!-- Quiz Section -->
    <div class="mt-8 border-t border-gray-200 dark:border-dark-border pt-8">
        <h3 class="text-2xl font-semibold mb-6 text-center text-brand-red">
            Section Quiz
        </h3>
        <?php include 'quiz.php'; ?>
    </div>
<?php endif; ?> 