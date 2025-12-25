<?php
/**
 * OTP Resend System with Email Support
 * Handles email OTP resending with rate limiting and cooldown
 */

session_start();
require_once '../config/database.php';
require_once 'otp_handler.php';

// Set JSON response header
header('Content-Type: application/json');

// Helper function to send JSON response
function sendResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Validate session
if (!isset($_SESSION['otp_user_id']) || !isset($_SESSION['otp_type'])) {
    sendResponse(false, 'Invalid session. Please try again.');
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.');
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$resendType = $input['type'] ?? 'email'; // Support both email and SMS

if (!in_array($resendType, ['email', 'sms'])) {
    sendResponse(false, 'Invalid resend type. Use "email" or "sms".');
}

try {
    // Include database configuration to get $pdo
    require_once '../config/database.php';
    $otpHandler = new OTPHandler($pdo);
    $userId = $_SESSION['otp_user_id'];
    $otpType = $_SESSION['otp_type'];
    
    // Get user information
    $userStmt = $pdo->prepare("SELECT id, email, phone_number, phone_verified, username FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendResponse(false, 'User not found.');
    }
    
    // Validate SMS availability
    if ($resendType === 'sms') {
        if (empty($user['phone_number'])) {
            sendResponse(false, 'No phone number registered for this account.');
        }
        // Allow SMS resend for sms_registration even if phone not verified yet
        if ($user['phone_verified'] != 1 && $otpType !== 'sms_registration') {
            sendResponse(false, 'Phone number not verified. SMS backup not available.');
        }
    }
    
    // Check rate limiting
    if (!checkRateLimit($pdo, $userId, $resendType, $user)) {
        sendResponse(false, 'Too many attempts. Please try again later.');
    }
    
    // Check cooldown
    if (!checkCooldown($pdo, $userId, $resendType)) {
        $cooldownTime = getCooldownTime($pdo, $userId, $resendType);
        sendResponse(false, 'Please wait before requesting another OTP' . " (Wait {$cooldownTime} seconds)");
    }
    
    // Get or generate OTP
    $otp = getOrGenerateOTP($pdo, $userId, $user['email'], $user['phone_number'], $otpType, $resendType);
    
    if (!$otp) {
        sendResponse(false, 'Failed to generate OTP. Please try again.');
    }
    
    // Send OTP based on type
    if ($resendType === 'email') {
        $success = sendEmailOTP($otpHandler, $user['email'], $otp, $otpType, $userId);
        $message = $success ? 'OTP sent successfully via email' : 'Failed to send email OTP';
    } else { // SMS
        $success = sendSMSOTP($pdo, $user['phone_number'], $otp, $otpType, $userId);
        $message = $success ? 'Backup OTP sent successfully via SMS' : 'Failed to send SMS OTP';
    }
    
    if ($success) {
        // Record the attempt
        recordAttempt($pdo, $userId, $resendType, $user);
        // Set cooldown
        setCooldown($pdo, $userId, $resendType);
        
        sendResponse(true, $message, [
            'type' => $resendType,
            'cooldown' => 60 // 60 seconds cooldown
        ]);
    } else {
        sendResponse(false, $message);
    }
    
} catch (Exception $e) {
    error_log("OTP Resend Error: " . $e->getMessage());
    sendResponse(false, 'An error occurred. Please try again.');
}

/**
 * Check rate limiting for OTP attempts
 */
function checkRateLimit($pdo, $userId, $type, $user) {
    $windowStart = date('Y-m-d H:i:s', strtotime('-10 minutes')); // 10 minutes window
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM otp_rate_limits 
        WHERE user_id = ? 
        AND attempt_type = ? 
        AND last_attempt_at >= ?
    ");
    $stmt->execute([$userId, $type, $windowStart]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['attempt_count'] < 3; // Max 3 attempts per window
}

/**
 * Check if user is in cooldown period
 */
function checkCooldown($pdo, $userId, $type) {
    $stmt = $pdo->prepare("
        SELECT cooldown_until 
        FROM otp_cooldowns 
        WHERE user_id = ? 
        AND attempt_type = ? 
        AND cooldown_until > NOW()
    ");
    $stmt->execute([$userId, $type]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return !$result; // Return true if no active cooldown
}

/**
 * Get remaining cooldown time
 */
function getCooldownTime($pdo, $userId, $type) {
    $stmt = $pdo->prepare("
        SELECT TIMESTAMPDIFF(SECOND, NOW(), cooldown_until) as remaining 
        FROM otp_cooldowns 
        WHERE user_id = ? 
        AND attempt_type = ? 
        AND cooldown_until > NOW()
    ");
    $stmt->execute([$userId, $type]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['remaining'] : 0;
}

/**
 * Generate new OTP for resend (always generates fresh OTP)
 */
function getOrGenerateOTP($pdo, $userId, $email, $phoneNumber, $otpType, $resendType) {
    // Always generate a new OTP for resend requests
    $otp = generateSecureOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes')); // 5 minutes expiry
    
    // Mark any existing valid OTPs as used to prevent confusion
    $stmt = $pdo->prepare("
        UPDATE otps 
        SET is_used = TRUE 
        WHERE user_id = ? 
        AND type = ? 
        AND expires_at > NOW() 
        AND is_used = FALSE
    ");
    $stmt->execute([$userId, $otpType]);
    
    // Insert new OTP with appropriate type
    // For SMS resend, keep the same OTP type as the session to ensure verification works
    $otpDbType = $otpType;
    
    $stmt = $pdo->prepare("
        INSERT INTO otps (user_id, email, phone_number, otp_code, type, expires_at, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $email, $phoneNumber, $otp, $otpDbType, $expiresAt]);
    
    return $otp;
}

/**
 * Generate cryptographically secure OTP
 */
function generateSecureOTP() {
    // Use cryptographically secure random to generate 6-digit OTP
    $otp = random_int(100000, 999999);
    return str_pad($otp, 6, '0', STR_PAD_LEFT);
}

/**
 * Send OTP via email
 */
function sendEmailOTP($otpHandler, $email, $otp, $otpType, $userId) {
    try {
        return $otpHandler->sendOTPWithUserId($email, $otp, $otpType, $userId);
    } catch (Exception $e) {
        error_log("Email OTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send OTP via SMS using PhilSMS
 */
function sendSMSOTP($pdo, $phoneNumber, $otp, $otpType, $userId) {
    try {
        // Include PhilSMS service
        require_once __DIR__ . '/../services/PhilSMSService.php';
        
        $philSMS = new PhilSMSService();
        
        // Check SMS rate limits
        $rateLimit = $philSMS->checkRateLimit($phoneNumber);
        if (!$rateLimit['allowed']) {
            error_log("SMS Rate limit exceeded for $phoneNumber");
            return false;
        }
        
        // Send SMS OTP
        $result = $philSMS->sendOTP($phoneNumber, $otp, 'login_backup');
        
        if ($result['success']) {
            error_log("SMS OTP sent successfully to $phoneNumber");
            return true;
        } else {
            error_log("SMS OTP failed: " . ($result['error'] ?? 'Unknown error'));
            return false;
        }
        
    } catch (Exception $e) {
        error_log("SMS OTP Error: " . $e->getMessage());
        return false;
    }
}



/**
 * Record OTP attempt for rate limiting
 */
function recordAttempt($pdo, $userId, $type, $user) {
    $stmt = $pdo->prepare("
        INSERT INTO otp_rate_limits (user_id, phone_number, email, attempt_type, attempts_count) 
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE 
        attempts_count = attempts_count + 1,
        last_attempt_at = NOW()
    ");
    $stmt->execute([$userId, $user['phone_number'], $user['email'], $type]);
}

/**
 * Set cooldown period
 */
function setCooldown($pdo, $userId, $type) {
    $cooldownUntil = date('Y-m-d H:i:s', strtotime('+60 seconds')); // 60 seconds cooldown
    
    $stmt = $pdo->prepare("
        INSERT INTO otp_cooldowns (user_id, attempt_type, cooldown_until) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        cooldown_until = VALUES(cooldown_until)
    ");
    $stmt->execute([$userId, $type, $cooldownUntil]);
}

?>