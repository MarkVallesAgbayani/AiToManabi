<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    $userId = $_SESSION['user_id'];
    
    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
        exit;
    }
    
    // Validate password strength
    $password_validation = validatePasswordStrength($newPassword);
    if (!$password_validation['valid']) {
        echo json_encode(['success' => false, 'error' => $password_validation['message']]);
        exit;
    }
    
    // Verify current password
    $sql = "SELECT password, email FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit;
    }
    
    // Check if new password is different
    if (password_verify($newPassword, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'New password must be different from current password']);
        exit;
    }
    
    // Create temp password changes table
    createTempPasswordChangesTable($pdo);
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Store password temporarily for OTP verification
    $tempPasswordStmt = $pdo->prepare("
        INSERT INTO temp_password_changes (user_id, new_password, created_at, expires_at) 
        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ON DUPLICATE KEY UPDATE 
        new_password = VALUES(new_password), 
        created_at = NOW(), 
        expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
    ");
    $tempPasswordStmt->execute([$userId, $hashedPassword]);
    
    // Mark password reset notification as shown since user is changing their own password
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_notification_shown'")->fetch();
    if ($columns) {
        $stmt = $pdo->prepare("UPDATE users SET password_reset_notification_shown = TRUE WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    // Generate OTP
    $otp = generateSecureOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $verificationToken = bin2hex(random_bytes(32));
    
    // Insert OTP
    $otpStmt = $pdo->prepare("
        INSERT INTO otps (user_id, email, otp_code, type, expires_at, verification_token, created_at) 
        VALUES (?, ?, ?, 'password_change', ?, ?, NOW())
    ");
    $otpStmt->execute([$userId, $user['email'], $otp, $expiresAt, $verificationToken]);
    
    // Send OTP email
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../config/app_config.php';
    
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = SMTP_HOST;
    $mailer->SMTPAuth = true;
    $mailer->Username = SMTP_USERNAME;
    $mailer->Password = SMTP_PASSWORD;
    $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = SMTP_PORT;
    $mailer->setFrom(FROM_EMAIL, FROM_NAME);
    $mailer->CharSet = 'UTF-8';
    $mailer->Encoding = 'base64';
    
    $mailer->addAddress($user['email']);
    $mailer->isHTML(true);
    $mailer->Subject = "Password Change Verification - Japanese Learning Platform";
    $mailer->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2563eb; text-align: center;'>Password Change Verification</h2>
                <p>Hello,</p>
                <p>You have requested to change your password. Please use the following verification code to complete the process:</p>
                <div style='background-color: #f3f4f6; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                    <h1 style='color: #1f2937; font-size: 32px; letter-spacing: 4px; margin: 0;'>{$otp}</h1>
                </div>
                <p><strong>This code will expire in 5 minutes.</strong></p>
                <p>If you did not request this password change, please ignore this email and contact support immediately.</p>
                <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
                <p style='font-size: 12px; color: #6b7280; text-align: center;'>
                    This is an automated message from Japanese Learning Platform. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>
    ";
    
    $mailer->send();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Please check your email for verification code to complete password change.',
        'otp_sent' => true
    ]);
    
} catch (Exception $e) {
    error_log("Password change error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while changing password']);
}


function createTempPasswordChangesTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS temp_password_changes (
            user_id INT PRIMARY KEY,
            new_password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log("Error creating temp_password_changes table: " . $e->getMessage());
    }
}

function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include at least one number';
    }
    
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = 'Password must include at least one special character';
    }
    
    if (empty($errors)) {
        return ['valid' => true];
    } else {
        return ['valid' => false, 'message' => implode('. ', $errors) . '.'];
    }
}

function generateSecureOTP() {
    $bytes = random_bytes(3);
    $number = unpack('N', "\x00" . $bytes)[1];
    return str_pad($number % 1000000, 6, '0', STR_PAD_LEFT);
}
?>
