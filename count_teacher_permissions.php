<?php
// Count teacher-related permissions from the provided data

$permissions = [
    // Teacher Navigation (161-172)
    161 => 'nav_teacher_dashboard',
    162 => 'nav_teacher_courses', 
    163 => 'nav_teacher_create_module',
    164 => 'nav_teacher_drafts',
    165 => 'nav_teacher_archive',
    166 => 'nav_teacher_student_management',
    167 => 'nav_teacher_placement_test',
    168 => 'nav_teacher_settings',
    169 => 'nav_teacher_content',
    170 => 'nav_teacher_reports',
    171 => 'nav_teacher_audit',
    172 => 'nav_teacher_courses_by_category',
    
    // Teacher Dashboard (173-179, 272-273)
    173 => 'teacher_dashboard_view_active_modules',
    174 => 'teacher_dashboard_view_active_students',
    175 => 'teacher_dashboard_view_completion_rate',
    176 => 'teacher_dashboard_view_published_modules',
    177 => 'teacher_dashboard_view_learning_analytics',
    178 => 'teacher_dashboard_view_quick_actions',
    179 => 'teacher_dashboard_view_recent_activities',
    272 => 'module_performance_analytics',
    273 => 'student_progress_overview',
    
    // Teacher Placement Test (212-215, 292-293)
    212 => 'teacher_placement_test_create',
    213 => 'teacher_placement_test_edit',
    214 => 'teacher_placement_test_delete',
    215 => 'teacher_placement_test_publish',
    292 => 'placement_test',
    293 => 'preview_placement',
    
    // Teacher Course Management (274-279)
    274 => 'preview_module',
    275 => 'add_quiz',
    276 => 'add_level',
    277 => 'edit_level',
    278 => 'delete_level',
    279 => 'create_new_module',
    
    // Teacher Drafts (280-284)
    280 => 'edit_modules',
    281 => 'published_modules',
    282 => 'archived_modules',
    283 => 'create_new_draft',
    284 => 'my_drafts',
    
    // Teacher Archive (285-287)
    285 => 'restore_to_drafts',
    286 => 'delete_permanently',
    287 => 'archived',
    
    // Teacher Courses (288-291)
    288 => 'unpublished_modules',
    289 => 'edit_course_module',
    290 => 'archived_course_module',
    291 => 'courses',
    
    // Teacher Student Profiles (294-297)
    294 => 'search_and_filter',
    295 => 'view_profile_button',
    296 => 'view_progress_button',
    297 => 'student_profiles',
    
    // Teacher Progress Tracking (298-306)
    298 => 'progress_tracking',
    299 => 'export_progress',
    300 => 'complete_modules',
    301 => 'in_progress',
    302 => 'average_progress',
    303 => 'active_students',
    304 => 'progress_distribution',
    305 => 'module_completion',
    306 => 'detailed_progress_tracking',
    
    // Teacher Quiz Performance (307-317)
    307 => 'quiz_performance',
    308 => 'filter_search',
    309 => 'export_pdf_quiz',
    310 => 'average_score',
    311 => 'total_attempts',
    312 => 'active_students_quiz',
    313 => 'total_quiz_students',
    314 => 'performance_trend',
    315 => 'quiz_difficulty_analysis',
    316 => 'top_performer',
    317 => 'recent_quiz_attempt',
    
    // Teacher Engagement Monitoring (318-328)
    318 => 'engagement_monitoring',
    319 => 'filter_engagement_monitoring',
    320 => 'export_pdf_engagement',
    321 => 'login_frequency',
    322 => 'drop_off_rate',
    323 => 'average_enrollment_days',
    324 => 'recent_enrollments',
    325 => 'time_spent_learning',
    326 => 'module_engagement',
    327 => 'most_engaged_students',
    328 => 'recent_enrollments_card',
    
    // Teacher Completion Reports (329-338)
    329 => 'completion_reports',
    330 => 'filter_completion_reports',
    331 => 'export_completion_reports',
    332 => 'overall_completion_rate',
    333 => 'average_progress_completion_reports',
    334 => 'on_time_completions',
    335 => 'delayed_completions',
    336 => 'module_completion_breakdown',
    337 => 'completion_timeline',
    338 => 'completion_breakdown'
];

echo "=== TEACHER PERMISSIONS ANALYSIS ===\n\n";

// Count by category
$categories = [
    'Teacher Navigation' => 0,
    'Teacher Dashboard' => 0,
    'Teacher Placement Test' => 0,
    'Teacher Course Management' => 0,
    'Teacher Drafts' => 0,
    'Teacher Archive' => 0,
    'Teacher Courses' => 0,
    'Teacher Student Profiles' => 0,
    'Teacher Progress Tracking' => 0,
    'Teacher Quiz Performance' => 0,
    'Teacher Engagement Monitoring' => 0,
    'Teacher Completion Reports' => 0
];

// Navigation (161-172)
for ($i = 161; $i <= 172; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Navigation']++;
}

// Dashboard (173-179, 272-273)
for ($i = 173; $i <= 179; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Dashboard']++;
}
if (isset($permissions[272])) $categories['Teacher Dashboard']++;
if (isset($permissions[273])) $categories['Teacher Dashboard']++;

// Placement Test (212-215, 292-293)
for ($i = 212; $i <= 215; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Placement Test']++;
}
if (isset($permissions[292])) $categories['Teacher Placement Test']++;
if (isset($permissions[293])) $categories['Teacher Placement Test']++;

// Course Management (274-279)
for ($i = 274; $i <= 279; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Course Management']++;
}

// Drafts (280-284)
for ($i = 280; $i <= 284; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Drafts']++;
}

// Archive (285-287)
for ($i = 285; $i <= 287; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Archive']++;
}

// Courses (288-291)
for ($i = 288; $i <= 291; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Courses']++;
}

// Student Profiles (294-297)
for ($i = 294; $i <= 297; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Student Profiles']++;
}

// Progress Tracking (298-306)
for ($i = 298; $i <= 306; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Progress Tracking']++;
}

// Quiz Performance (307-317)
for ($i = 307; $i <= 317; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Quiz Performance']++;
}

// Engagement Monitoring (318-328)
for ($i = 318; $i <= 328; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Engagement Monitoring']++;
}

// Completion Reports (329-338)
for ($i = 329; $i <= 338; $i++) {
    if (isset($permissions[$i])) $categories['Teacher Completion Reports']++;
}

$total = 0;
foreach ($categories as $category => $count) {
    echo "$category: $count permissions\n";
    $total += $count;
}

echo "\n=== SUMMARY ===\n";
echo "Total Teacher-Related Permissions: $total\n\n";

echo "=== PERMISSION ID RANGES ===\n";
echo "Navigation: 161-172 (12 permissions)\n";
echo "Dashboard: 173-179 + 272-273 (9 permissions)\n";
echo "Placement Test: 212-215 + 292-293 (6 permissions)\n";
echo "Course Management: 274-279 (6 permissions)\n";
echo "Drafts: 280-284 (5 permissions)\n";
echo "Archive: 285-287 (3 permissions)\n";
echo "Courses: 288-291 (4 permissions)\n";
echo "Student Profiles: 294-297 (4 permissions)\n";
echo "Progress Tracking: 298-306 (9 permissions)\n";
echo "Quiz Performance: 307-317 (11 permissions)\n";
echo "Engagement Monitoring: 318-328 (11 permissions)\n";
echo "Completion Reports: 329-338 (10 permissions)\n";

echo "\n=== VERIFICATION ===\n";
echo "Expected total from ranges: " . (12 + 9 + 6 + 6 + 5 + 3 + 4 + 4 + 9 + 11 + 11 + 10) . "\n";
echo "Actual counted permissions: $total\n";

if ($total == 90) {
    echo "✅ Count matches expected total!\n";
} else {
    echo "⚠️  Count mismatch - please review\n";
}
?>