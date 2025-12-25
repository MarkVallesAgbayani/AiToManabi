<?php
/**
 * Student OTP Verification API
 * Handles OTP verification for password changes and other operations for students
 */

// Disable error display to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    $otp_code = $input['otp_code'] ?? '';
    $type = $input['type'] ?? 'password_change';
    
    if (empty($otp_code)) {
        throw new Exception('OTP code is required');
    }
    
    error_log("Student OTP Verification - User ID: " . $_SESSION['user_id'] . ", OTP: " . $otp_code . ", Type: " . $type);
    
    // Verify OTP directly from database
    $stmt = $pdo->prepare("
        SELECT id, user_id, email, otp_code, type, expires_at, is_used 
        FROM otps 
        WHERE user_id = ? 
        AND otp_code = ? 
        AND type = ? 
        AND expires_at > NOW() 
        AND is_used = FALSE
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([$_SESSION['user_id'], $otp_code, $type]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($otp) {
        error_log("Student OTP found and valid - ID: " . $otp['id']);
        
        // Mark OTP as used
        $updateStmt = $pdo->prepare("UPDATE otps SET is_used = TRUE WHERE id = ?");
        $updateStmt->execute([$otp['id']]);
        error_log("Student OTP marked as used");
        
        // If this is a password change verification, update the password
        if ($type === 'password_change') {
            try {
                error_log("Processing student password change verification");
                
                // Get the temporary password
                $tempStmt = $pdo->prepare("
                    SELECT new_password FROM temp_password_changes 
                    WHERE user_id = ? AND expires_at > NOW()
                ");
                $tempStmt->execute([$_SESSION['user_id']]);
                $tempPassword = $tempStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($tempPassword) {
                    error_log("Temporary password found, updating student password");
                    
                    // Update the actual password
                    $updatePasswordStmt = $pdo->prepare("
                        UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $updatePasswordStmt->execute([$tempPassword['new_password'], $_SESSION['user_id']]);
                    error_log("Student password updated successfully");
                    
                    // Delete the temporary password
                    $deleteTempStmt = $pdo->prepare("DELETE FROM temp_password_changes WHERE user_id = ?");
                    $deleteTempStmt->execute([$_SESSION['user_id']]);
                    error_log("Temporary password deleted");
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Password changed successfully!'
                    ]);
                } else {
                    error_log("No temporary password found or expired for student");
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Password change session expired. Please try again.'
                    ]);
                }
            } catch (Exception $e) {
                error_log("Student password update error: " . $e->getMessage());
                echo json_encode([
                    'success' => false, 
                    'error' => 'Failed to update password. Please try again.'
                ]);
            }
        } else {
            echo json_encode([
                'success' => true, 
                'message' => 'OTP verified successfully'
            ]);
        }
    } else {
        error_log("Student OTP not found or invalid - User ID: " . $_SESSION['user_id'] . ", OTP: " . $otp_code);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid or expired OTP code'
        ]);
    }
    
} catch (Exception $e) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    error_log("Student OTP verification error: " . $e->getMessage());
    error_log("Student OTP verification error trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
