<?php
/**
 * Teacher Settings API
 * Handles saving and loading teacher preferences and settings
 */

// Disable error display to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$teacher_id = $_SESSION['user_id'];

try {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    switch ($method) {
        case 'GET':
            // Load teacher settings
            $settings = loadTeacherSettings($pdo, $teacher_id);
            echo json_encode(['success' => true, 'data' => $settings]);
            break;
            
        case 'POST':
            // Save teacher settings
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            $result = saveTeacherSettings($pdo, $teacher_id, $input);
            echo json_encode(['success' => $result, 'message' => $result ? 'Settings saved successfully' : 'Failed to save settings']);
            break;
            
        case 'PUT':
            // Handle password change
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            $result = changePassword($pdo, $teacher_id, $input);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    error_log("Teacher settings API error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    error_log("Error file: " . $e->getFile() . " line: " . $e->getLine());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API Error: ' . $e->getMessage()]);
}

function loadTeacherSettings($pdo, $teacher_id) {
    // Check if teacher_preferences table exists, create if not
    createTeacherPreferencesTable($pdo);
    
    $stmt = $pdo->prepare("SELECT * FROM teacher_preferences WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$preferences) {
        // Return default settings
        return getDefaultSettings();
    }
    
    // Parse fields
    $settings = [
        'profile' => [
            'firstName' => $preferences['first_name'] ?? '',
            'lastName' => $preferences['last_name'] ?? '',
            'displayName' => $preferences['display_name'] ?? '',
            'profilePicture' => $preferences['profile_picture'] ?? '',
            'bio' => $preferences['bio'] ?? '',
            'phone' => $preferences['phone'] ?? '',
            'languages' => $preferences['languages'] ?? ''
        ],
        'security' => [
            'profileVisible' => $preferences['profile_visible'] ?? true,
            'contactVisible' => $preferences['contact_visible'] ?? true
        ]
    ];
    
    return $settings;
}

function saveTeacherSettings($pdo, $teacher_id, $settings) {
    // Check if teacher_preferences table exists, create if not
    createTeacherPreferencesTable($pdo);
    
    try {
        $pdo->beginTransaction();
        
        // Check if preferences exist
        $stmt = $pdo->prepare("SELECT id FROM teacher_preferences WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing preferences
            $stmt = $pdo->prepare("
                UPDATE teacher_preferences SET 
                    first_name = ?,
                    last_name = ?,
                    display_name = ?,
                    profile_picture = ?,
                    bio = ?,
                    phone = ?,
                    languages = ?,
                    profile_visible = ?,
                    contact_visible = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE teacher_id = ?
            ");
            
            $stmt->execute([
                $settings['profile']['firstName'] ?? '',
                $settings['profile']['lastName'] ?? '',
                $settings['profile']['displayName'] ?? '',
                $settings['profile']['profilePicture'] ?? '',
                $settings['profile']['bio'] ?? '',
                $settings['profile']['phone'] ?? '',
                $settings['profile']['languages'] ?? '',
                $settings['security']['profileVisible'] ?? true,
                $settings['security']['contactVisible'] ?? true,
                $teacher_id
            ]);
        } else {
            // Insert new preferences
            $stmt = $pdo->prepare("
                INSERT INTO teacher_preferences (
                    teacher_id, first_name, last_name, display_name, profile_picture, bio, phone, languages,
                    profile_visible, contact_visible
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $teacher_id,
                $settings['profile']['firstName'] ?? '',
                $settings['profile']['lastName'] ?? '',
                $settings['profile']['displayName'] ?? '',
                $settings['profile']['profilePicture'] ?? '',
                $settings['profile']['bio'] ?? '',
                $settings['profile']['phone'] ?? '',
                $settings['profile']['languages'] ?? '',
                $settings['security']['profileVisible'] ?? true,
                $settings['security']['contactVisible'] ?? true
            ]);
        }
        
        // Also update the main users table with basic info
        if (isset($settings['profile']['firstName']) || isset($settings['profile']['lastName'])) {
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    first_name = COALESCE(?, first_name),
                    last_name = COALESCE(?, last_name)
                WHERE id = ?
            ");
            $stmt->execute([
                $settings['profile']['firstName'] ?: null,
                $settings['profile']['lastName'] ?: null,
                $teacher_id
            ]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Error saving teacher settings: " . $e->getMessage());
        return false;
    }
}

function createTeacherPreferencesTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS teacher_preferences (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            display_name VARCHAR(100),
            profile_picture VARCHAR(255),
            bio TEXT,
            phone VARCHAR(20),
            languages VARCHAR(255),
            profile_visible BOOLEAN DEFAULT TRUE,
            contact_visible BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_teacher (teacher_id),
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
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
    
    $pdo->exec($sql);
}

function changePassword($pdo, $teacher_id, $input) {
    try {
        error_log("Starting password change for user: $teacher_id");
        
        $current_password = $input['current_password'] ?? '';
        $new_password = $input['new_password'] ?? '';
        $confirm_password = $input['confirm_password'] ?? '';
        
        error_log("Password change input validation - current: " . (empty($current_password) ? 'empty' : 'provided') . 
                 ", new: " . (empty($new_password) ? 'empty' : 'provided') . 
                 ", confirm: " . (empty($confirm_password) ? 'empty' : 'provided'));
        
        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            return ['success' => false, 'error' => 'All fields are required'];
        }
        
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'error' => 'New passwords do not match'];
        }
        
        // Validate password strength
        $password_validation = validatePasswordStrength($new_password);
        if (!$password_validation['valid']) {
            error_log("Password strength validation failed: " . $password_validation['message']);
            return ['success' => false, 'error' => $password_validation['message']];
        }
        error_log("Password strength validation passed");
        
        // Get current user password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$teacher_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("User not found for password change: $teacher_id");
            return ['success' => false, 'error' => 'User not found'];
        }
        error_log("User found for password change");
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            error_log("Current password verification failed for user: $teacher_id");
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        error_log("Current password verification passed");
        
        // Check if new password is different from current
        if (password_verify($new_password, $user['password'])) {
            error_log("New password is same as current password for user: $teacher_id");
            return ['success' => false, 'error' => 'New password must be different from current password'];
        }
        error_log("New password is different from current password");
        
        // Create temp password changes table
        createTempPasswordChangesTable($pdo);
        error_log("Temp password changes table created/verified");
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        error_log("Password hashed successfully");
        
        // Store password temporarily for OTP verification
        $tempPasswordStmt = $pdo->prepare("
            INSERT INTO temp_password_changes (user_id, new_password, created_at, expires_at) 
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE))
            ON DUPLICATE KEY UPDATE 
            new_password = VALUES(new_password), 
            created_at = NOW(), 
            expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
        ");
        $tempPasswordStmt->execute([$teacher_id, $hashed_password]);
        error_log("Temporary password stored successfully");
        
        // Mark password reset notification as shown since user is changing their own password
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_notification_shown'")->fetch();
        if ($columns) {
            $stmt = $pdo->prepare("UPDATE users SET password_reset_notification_shown = TRUE WHERE id = ?");
            $stmt->execute([$teacher_id]);
            error_log("Password reset notification marked as shown for user: $teacher_id");
        }
        
        // Generate OTP for password change verification
        error_log("Starting OTP generation for user: $teacher_id");
        
        // Get user email for OTP
        $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $userStmt->execute([$teacher_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("User not found for OTP generation: $teacher_id");
            return ['success' => false, 'error' => 'User not found'];
        }
        
        error_log("User email found: " . $user['email']);
        
        // Check if user email is valid
        if (empty($user['email'])) {
            error_log("User email is empty for user: $teacher_id");
            return ['success' => false, 'error' => 'User email not found'];
        }
        
        // Generate OTP directly
        $otp = generateSecureOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $verificationToken = bin2hex(random_bytes(32));
        
        error_log("Generated OTP: $otp, expires at: $expiresAt");
        
        // Insert OTP into database
        $otpStmt = $pdo->prepare("
            INSERT INTO otps (user_id, email, otp_code, type, expires_at, verification_token, created_at) 
            VALUES (?, ?, ?, 'password_change', ?, ?, NOW())
        ");
        $otpStmt->execute([$teacher_id, $user['email'], $otp, $expiresAt, $verificationToken]);
        error_log("OTP inserted into database successfully");
        
        // Send OTP email using PHPMailer directly (avoiding OTP handler's transaction)
        error_log("Attempting to send OTP email to: " . $user['email']);
        
        try {
            // Create a new PHPMailer instance to avoid transaction conflicts
            require_once '../../vendor/autoload.php';
            require_once '../../config/app_config.php';
            
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
            
            $email_sent = $mailer->send();
            
            if (!$email_sent) {
                error_log("OTP email failed for user: $teacher_id - " . $mailer->ErrorInfo);
                return ['success' => false, 'error' => 'Failed to send verification email'];
            } else {
                error_log("OTP email sent successfully to: " . $user['email']);
            }
            
        } catch (Exception $mailError) {
            error_log("Email sending error: " . $mailError->getMessage());
            return ['success' => false, 'error' => 'Failed to send verification email: ' . $mailError->getMessage()];
        }
        
        return [
            'success' => true, 
            'message' => 'Please check your email for verification code to complete password change.',
            'otp_sent' => true
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log("Password change error: " . $e->getMessage());
        error_log("Password change error trace: " . $e->getTraceAsString());
        error_log("Password change error file: " . $e->getFile() . " line: " . $e->getLine());
        return ['success' => false, 'error' => 'An error occurred while changing password. Please try again. Error: ' . $e->getMessage()];
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
    // Use cryptographically secure random
    $bytes = random_bytes(3); // 3 bytes = 24 bits, enough for 6-digit OTP
    $number = unpack('N', "\x00" . $bytes)[1];
    return str_pad($number % 1000000, 6, '0', STR_PAD_LEFT);
}

function getDefaultSettings() {
    return [
        'profile' => [
            'firstName' => '',
            'lastName' => '',
            'displayName' => '',
            'profilePicture' => '',
            'bio' => '',
            'phone' => '',
            'languages' => ''
        ],
        'security' => [
            'profileVisible' => true,
            'contactVisible' => true
        ]
    ];
}
?>
