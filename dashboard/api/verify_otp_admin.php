<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $otpCode = $input['otp_code'] ?? '';
    $type = $input['type'] ?? '';
    $userId = $_SESSION['user_id'];
    
    if (empty($otpCode) || empty($type)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    // Verify OTP
    $stmt = $pdo->prepare("
        SELECT * FROM otps 
        WHERE user_id = ? AND otp_code = ? AND type = ? AND expires_at > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId, $otpCode, $type]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired verification code']);
        exit;
    }
    
    // Get temp password
    $tempStmt = $pdo->prepare("
        SELECT new_password FROM temp_password_changes 
        WHERE user_id = ? AND expires_at > NOW()
    ");
    $tempStmt->execute([$userId]);
    $tempPassword = $tempStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tempPassword) {
        echo json_encode(['success' => false, 'error' => 'Password change request expired. Please try again.']);
        exit;
    }
    
    // Update password
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->execute([$tempPassword['new_password'], $userId]);
    
    // Delete OTP and temp password
    $deleteOtp = $pdo->prepare("DELETE FROM otps WHERE user_id = ? AND type = ?");
    $deleteOtp->execute([$userId, $type]);
    
    $deleteTemp = $pdo->prepare("DELETE FROM temp_password_changes WHERE user_id = ?");
    $deleteTemp->execute([$userId]);
    
    // Log to audit trail
    try {
        $auditSql = "INSERT INTO audit_trail (user_id, action, details, created_at) 
                     VALUES (?, 'password_change', 'User changed password', NOW())";
        $auditStmt = $pdo->prepare($auditSql);
        $auditStmt->execute([$userId]);
    } catch (PDOException $auditError) {
        error_log("Audit trail logging failed: " . $auditError->getMessage());
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password changed successfully!'
    ]);
    
} catch (Exception $e) {
    error_log("OTP verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred during verification']);
}
?>
