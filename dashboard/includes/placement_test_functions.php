<?php
/**
 * Placement Test Helper Functions
 * These functions are used across the application to handle placement test logic
 */

/**
 * Check if student needs to take placement test
 * @param PDO $pdo Database connection
 * @param int $studentId Student ID
 * @return bool True if student needs to take placement test
 */
function needsPlacementTest($pdo, $studentId) {
    try {
        // Get the first published placement test
        $stmt = $pdo->prepare("
            SELECT id FROM placement_test 
            WHERE is_published = 1 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            // No published placement test available
            return false;
        }
        
        // Check if student has already taken the test
        $stmt = $pdo->prepare("
            SELECT id FROM placement_result 
            WHERE student_id = ? AND test_id = ?
        ");
        $stmt->execute([$studentId, $test['id']]);
        $existingResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no result exists, student needs to take the test
        return !$existingResult;
        
    } catch (Exception $e) {
        error_log("Error checking placement test requirement: " . $e->getMessage());
        // On error, don't force placement test
        return false;
    }
}

/**
 * Get the placement test redirect URL for students
 * @param PDO $pdo Database connection
 * @param string $basePath Base path for the redirect URL
 * @return string|null Redirect URL or null if no test available
 */
function getPlacementTestRedirectUrl($pdo, $basePath = '') {
    try {
        // Get the first published placement test ID
        $stmt = $pdo->prepare("SELECT id FROM placement_test WHERE is_published = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test) {
            return $basePath . 'Placement Test/placement_test_student.php?test_id=' . $test['id'];
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting placement test redirect URL: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if student has taken placement test
 * @param PDO $pdo Database connection
 * @param int $studentId Student ID
 * @param int $testId Test ID (optional, will use first published test if not provided)
 * @return bool True if student has taken the test
 */
function hasStudentTakenPlacementTest($pdo, $studentId, $testId = null) {
    try {
        if ($testId === null) {
            // Get the first published placement test
            $stmt = $pdo->prepare("SELECT id FROM placement_test WHERE is_published = 1 ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                return false;
            }
            $testId = $test['id'];
        }
        
        // Check if student has taken this test
        $stmt = $pdo->prepare("SELECT id FROM placement_result WHERE student_id = ? AND test_id = ?");
        $stmt->execute([$studentId, $testId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result !== false;
        
    } catch (Exception $e) {
        error_log("Error checking if student has taken placement test: " . $e->getMessage());
        return false;
    }
}

/**
 * Get student's placement test result
 * @param PDO $pdo Database connection
 * @param int $studentId Student ID
 * @param int $testId Test ID (optional, will use first published test if not provided)
 * @return array|null Student's placement result or null if not found
 */
function getStudentPlacementResult($pdo, $studentId, $testId = null) {
    try {
        if ($testId === null) {
            // Get the first published placement test
            $stmt = $pdo->prepare("SELECT id FROM placement_test WHERE is_published = 1 ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                return null;
            }
            $testId = $test['id'];
        }
        
        // Get student's placement result
        $stmt = $pdo->prepare("
            SELECT * FROM placement_result 
            WHERE student_id = ? AND test_id = ?
        ");
        $stmt->execute([$studentId, $testId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Decode JSON fields
            $result['answers'] = json_decode($result['answers'], true) ?? [];
            $result['difficulty_scores'] = json_decode($result['difficulty_scores'], true) ?? [];
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error getting student placement result: " . $e->getMessage());
        return null;
    }
}

/**
 * Get student's recommended level based on placement test
 * @param PDO $pdo Database connection
 * @param int $studentId Student ID
 * @param int $testId Test ID (optional, will use first published test if not provided)
 * @return string|null Recommended level or null if not found
 */
function getStudentRecommendedLevel($pdo, $studentId, $testId = null) {
    $result = getStudentPlacementResult($pdo, $studentId, $testId);
    return $result ? $result['recommended_level'] : null;
}
?>
