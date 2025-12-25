<?php
/**
 * Student Settings API
 * Handles saving and loading student preferences and settings
 */

// Disable error display to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$student_id = $_SESSION['user_id'];

try {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    switch ($method) {
        case 'GET':
            // Load student settings
            $settings = loadStudentSettings($pdo, $student_id);
            echo json_encode(['success' => true, 'data' => $settings]);
            break;
            
        case 'POST':
            // Save student settings
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            // Log the input for debugging
            error_log("Student settings save request - Student ID: $student_id");
            error_log("Student settings input: " . json_encode($input));
            
            // Validate display name length
            if (isset($input['profile']['displayName']) && strlen($input['profile']['displayName']) > 30) {
                error_log("Display name too long: " . strlen($input['profile']['displayName']) . " characters");
                echo json_encode(['success' => false, 'message' => 'Display name must be 30 characters or less', 'error' => 'Validation failed']);
                exit;
            }
            
            $result = saveStudentSettings($pdo, $student_id, $input);
            if ($result) {
                error_log("Student settings saved successfully for student: $student_id");
                echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
            } else {
                error_log("Failed to save student settings for student: $student_id");
                echo json_encode(['success' => false, 'message' => 'Failed to save settings', 'error' => 'Database operation failed']);
            }
            break;
            
        case 'PUT':
            // Handle password change
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            $result = changeStudentPassword($pdo, $student_id, $input);
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
    
    error_log("Student settings API error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    error_log("Error file: " . $e->getFile() . " line: " . $e->getLine());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API Error: ' . $e->getMessage()]);
}

function loadStudentSettings($pdo, $student_id) {
    // Check if student_preferences table exists, create if not
    createStudentPreferencesTable($pdo);
    
    $stmt = $pdo->prepare("SELECT * FROM student_preferences WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$preferences) {
        // Return default settings
        return getDefaultStudentSettings();
    }
    
    // Parse fields
    $settings = [
        'profile' => [
            'displayName' => $preferences['display_name'] ?? '',
            'profilePicture' => $preferences['profile_picture'] ?? '',
            'bio' => $preferences['bio'] ?? '',
            'phone' => $preferences['phone'] ?? ''
        ],
        'security' => [
            'profileVisible' => $preferences['profile_visible'] ?? true,
            'contactVisible' => $preferences['contact_visible'] ?? true
        ]
    ];
    
    return $settings;
}

function saveStudentSettings($pdo, $student_id, $settings) {
    // Check if student_preferences table exists, create if not
    createStudentPreferencesTable($pdo);
    
    // Check if PDO connection is valid
    if (!$pdo) {
        error_log("PDO connection is null");
        return false;
    }
    
    // Test database connection
    try {
        $pdo->query("SELECT 1");
        error_log("Database connection test: successful");
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
    
    // Check if table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'student_preferences'");
        $tableExists = $stmt->rowCount() > 0;
        error_log("Student preferences table exists: " . ($tableExists ? 'yes' : 'no'));
        
        if (!$tableExists) {
            error_log("Student preferences table does not exist, attempting to create it");
            createStudentPreferencesTable($pdo);
            
            // Check again after creation
            $stmt = $pdo->query("SHOW TABLES LIKE 'student_preferences'");
            $tableExists = $stmt->rowCount() > 0;
            error_log("Student preferences table exists after creation: " . ($tableExists ? 'yes' : 'no'));
            
            if (!$tableExists) {
                error_log("Failed to create student preferences table");
                return false;
            }
        }
    } catch (Exception $e) {
        error_log("Error checking table existence: " . $e->getMessage());
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Log the settings being saved
        error_log("Saving student settings for ID: $student_id");
        error_log("Settings structure: " . json_encode($settings));
        
        // Check if student exists in users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$student_id]);
        $studentExists = $stmt->fetch();
        
        if (!$studentExists) {
            error_log("Student with ID $student_id does not exist or is not a student");
            $pdo->rollback();
            return false;
        }
        
        error_log("Student exists: yes");
        
        // Check if preferences exist
        $stmt = $pdo->prepare("SELECT id FROM student_preferences WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing preferences
            error_log("Updating existing student preferences for ID: $student_id");
            $stmt = $pdo->prepare("
                UPDATE student_preferences SET 
                    display_name = ?,
                    profile_picture = ?,
                    bio = ?,
                    phone = ?,
                    profile_visible = ?,
                    contact_visible = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE student_id = ?
            ");
            
            $updateData = [
                $settings['profile']['displayName'] ?? '',
                $settings['profile']['profilePicture'] ?? '',
                $settings['profile']['bio'] ?? '',
                $settings['profile']['phone'] ?? '',
                $settings['security']['profileVisible'] ?? true,
                $settings['security']['contactVisible'] ?? true,
                $student_id
            ];
            
            error_log("Update data: " . json_encode($updateData));
            $result = $stmt->execute($updateData);
            error_log("Update result: " . ($result ? 'success' : 'failed'));
            error_log("Rows affected: " . $stmt->rowCount());
            
            if (!$result) {
                error_log("Update failed - PDO Error Info: " . json_encode($stmt->errorInfo()));
                $pdo->rollback();
                return false;
            }
        } else {
            // Insert new preferences
            error_log("Inserting new student preferences for ID: $student_id");
            $stmt = $pdo->prepare("
                INSERT INTO student_preferences (
                    student_id, display_name, profile_picture, bio, phone,
                    profile_visible, contact_visible
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertData = [
                $student_id,
                $settings['profile']['displayName'] ?? '',
                $settings['profile']['profilePicture'] ?? '',
                $settings['profile']['bio'] ?? '',
                $settings['profile']['phone'] ?? '',
                $settings['security']['profileVisible'] ?? true,
                $settings['security']['contactVisible'] ?? true
            ];
            
            error_log("Insert data: " . json_encode($insertData));
            $result = $stmt->execute($insertData);
            error_log("Insert result: " . ($result ? 'success' : 'failed'));
            error_log("Insert ID: " . $pdo->lastInsertId());
            
            if (!$result) {
                error_log("Insert failed - PDO Error Info: " . json_encode($stmt->errorInfo()));
                $pdo->rollback();
                return false;
            }
        }
        
        $pdo->commit();
        error_log("Student settings transaction committed successfully");
        return true;
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Error saving student settings: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        error_log("PDO Error Info: " . json_encode($pdo->errorInfo()));
        return false;
    }
}

function createStudentPreferencesTable($pdo) {
    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS student_preferences (
                id INT PRIMARY KEY AUTO_INCREMENT,
                student_id INT NOT NULL,
                display_name VARCHAR(30),
                profile_picture VARCHAR(255),
                bio TEXT,
                phone VARCHAR(20),
                profile_visible BOOLEAN DEFAULT TRUE,
                contact_visible BOOLEAN DEFAULT TRUE,
                notification_preferences TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $result = $pdo->exec($sql);
        error_log("Student preferences table creation result: " . ($result !== false ? 'success' : 'failed'));
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'student_preferences'");
        $tableExists = $stmt->rowCount() > 0;
        error_log("Student preferences table exists: " . ($tableExists ? 'yes' : 'no'));
        
        // If table creation failed, try without foreign key constraint
        if (!$tableExists) {
            error_log("Attempting to create table without foreign key constraint");
            $fallbackSql = "
                CREATE TABLE IF NOT EXISTS student_preferences (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    student_id INT NOT NULL,
                    display_name VARCHAR(30),
                    profile_picture VARCHAR(255),
                    bio TEXT,
                    phone VARCHAR(20),
                    profile_visible BOOLEAN DEFAULT TRUE,
                    contact_visible BOOLEAN DEFAULT TRUE,
                    notification_preferences TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_student (student_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $fallbackResult = $pdo->exec($fallbackSql);
            error_log("Fallback table creation result: " . ($fallbackResult !== false ? 'success' : 'failed'));
            
            // Check again
            $stmt = $pdo->query("SHOW TABLES LIKE 'student_preferences'");
            $tableExists = $stmt->rowCount() > 0;
            error_log("Student preferences table exists after fallback: " . ($tableExists ? 'yes' : 'no'));
        }
        
    } catch (Exception $e) {
        error_log("Error creating student preferences table: " . $e->getMessage());
    }
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

function changeStudentPassword($pdo, $student_id, $input) {
    try {
        error_log("Starting password change for student: $student_id");
        
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
        $stmt->execute([$student_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("User not found for password change: $student_id");
            return ['success' => false, 'error' => 'User not found'];
        }
        error_log("User found for password change");
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            error_log("Current password verification failed for user: $student_id");
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        error_log("Current password verification passed");
        
        // Check if new password is different from current
        if (password_verify($new_password, $user['password'])) {
            error_log("New password is same as current password for user: $student_id");
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
        $tempPasswordStmt->execute([$student_id, $hashed_password]);
        error_log("Temporary password stored successfully");
        
        // Mark password reset notification as shown since user is changing their own password
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_notification_shown'")->fetch();
        if ($columns) {
            $stmt = $pdo->prepare("UPDATE users SET password_reset_notification_shown = TRUE WHERE id = ?");
            $stmt->execute([$student_id]);
            error_log("Password reset notification marked as shown for student: $student_id");
        }
        
        // Generate OTP for password change verification
        error_log("Starting OTP generation for student: $student_id");
        
        // Get user email for OTP
        $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $userStmt->execute([$student_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("User not found for OTP generation: $student_id");
            return ['success' => false, 'error' => 'User not found'];
        }
        
        error_log("User email found: " . $user['email']);
        
        // Check if user email is valid
        if (empty($user['email'])) {
            error_log("User email is empty for user: $student_id");
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
        $otpStmt->execute([$student_id, $user['email'], $otp, $expiresAt, $verificationToken]);
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
                error_log("OTP email failed for user: $student_id - " . $mailer->ErrorInfo);
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

function getPhilippinesTimestamp() {
    // Set timezone to Philippines
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Manila');
    
    // Get current timestamp in Philippines timezone
    $timestamp = date('Y-m-d H:i:s');
    
    // Restore original timezone
    date_default_timezone_set($originalTimezone);
    
    return $timestamp;
}

function getDefaultStudentSettings() {
    return [
        'profile' => [
            'displayName' => '',
            'profilePicture' => '',
            'bio' => '',
            'phone' => ''
        ],
        'security' => [
            'profileVisible' => true,
            'contactVisible' => true
        ]
    ];
}
?>