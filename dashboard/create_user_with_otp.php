<?php
session_start();
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once '../auth/otp_handler.php';
require_once 'audit_logger.php';

// Check if user is logged in and has permission to create users
$has_user_management_access = false;

// First check if user has nav_user_management permission
if (function_exists('hasPermission')) {
    $has_user_management_access = hasPermission($pdo, $_SESSION['user_id'], 'nav_user_management');
}

// Fallback: Check if user has admin role
if (!$has_user_management_access && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $has_user_management_access = true;
}

// Final fallback: Check user role directly from database
if (!$has_user_management_access) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        $has_user_management_access = true;
    }
}

if (!isset($_SESSION['user_id']) || !$has_user_management_access) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $template_id = $_POST['template_id'] ?? '';
        $custom_permissions = $_POST['permissions'] ?? [];
        
        // Debug: Log the received template_id
        error_log("Received template_id: " . $template_id);
        error_log("POST data: " . print_r($_POST, true));
        
        // Personal information
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $middle_name = trim($_POST['middle_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $address_line2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $age = (int)$_POST['age'];
        $phone_number = trim($_POST['phone_number'] ?? '');
        $country_code = trim($_POST['country_code'] ?? '+63');
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($role) || empty($first_name) || empty($last_name) || empty($age) || empty($phone_number)) {
            $missing_fields = [];
            if (empty($username)) $missing_fields[] = 'username';
            if (empty($email)) $missing_fields[] = 'email';
            if (empty($password)) $missing_fields[] = 'password';
            if (empty($role)) $missing_fields[] = 'role';
            if (empty($first_name)) $missing_fields[] = 'first_name';
            if (empty($last_name)) $missing_fields[] = 'last_name';
            if (empty($age)) $missing_fields[] = 'age';
            if (empty($phone_number)) $missing_fields[] = 'phone_number';
            
            throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }
        
        if ($age < 18 || $age > 100) {
            throw new Exception('Age must be between 18 and 100');
        }
        
        // Normalize phone number to +639XXXXXXXXX format
        $normalized_phone = null;
        if (!empty($phone_number)) {
            // Remove all non-digit characters
            $cleaned = preg_replace('/[^\d]/', '', $phone_number);
            
            // Handle various Philippine number formats
            if (preg_match('/^639\d{9}$/', $cleaned)) {
                // Already has 63 country code
                $normalized_phone = '+' . $cleaned;
            } elseif (preg_match('/^09\d{9}$/', $cleaned)) {
                // Remove leading 0 and add +63
                $normalized_phone = '+63' . substr($cleaned, 1);
            } elseif (preg_match('/^9\d{9}$/', $cleaned)) {
                // Add +63 prefix
                $normalized_phone = '+63' . $cleaned;
            } else {
                throw new Exception('Invalid Philippine phone number format. Use 09XXXXXXXXX or 9XXXXXXXXX format.');
            }
        }
        
        // Check if username, email, or phone already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR phone_number = ?");
        $stmt->execute([$username, $email, $normalized_phone]);
        if ($stmt->rowCount() > 0) {
            throw new Exception('Username, email, or phone number already exists');
        }
        
        // Step 1: Create user and assign roles in a transaction
        $pdo->beginTransaction();
        
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user with email_verified = FALSE and phone_verified = FALSE for all users
            // Admin and teacher will need to verify both email AND phone before first login
            // Set is_first_login = TRUE for admin and teacher roles
            $is_first_login = in_array($role, ['admin', 'teacher']) ? TRUE : FALSE;
            $email_verified = FALSE;  // All users need email verification
            $phone_verified = FALSE;  // Admin/teacher need phone verification too
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, phone_number, password, role, email_verified, phone_verified, is_first_login, first_name, last_name, middle_name, suffix, address_line1, address_line2, city, age, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $username, $email, $normalized_phone, $hashed_password, $role, $email_verified, $phone_verified, $is_first_login,
                $first_name, $last_name, $middle_name, $suffix, $address_line1, $address_line2, $city, $age
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Log audit entry
            $auditLogger = new AuditLogger($pdo);
            $auditLogger->logEntry([
                'action_type' => 'CREATE',
                'action_description' => 'Created new user account: ' . $username,
                'resource_type' => 'User Account',
                'resource_id' => 'User ID: ' . $user_id,
                'resource_name' => $username,
                'outcome' => 'Success',
                'new_value' => 'Role: ' . $role . ', Email: ' . $email . ', Name: ' . $first_name . ' ' . $last_name,
                'context' => [
                    'user_id' => $user_id,
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'template_id' => $template_id,
                    'is_first_login' => $is_first_login,
                    'email_verified' => $email_verified
                ]
            ]);
            
            // Verify user was created successfully
            $verifyStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $verifyStmt->execute([$user_id]);
            if (!$verifyStmt->fetch()) {
                throw new Exception('User creation failed - user not found in database');
            }
            
            // Assign role template if provided
            if (!empty($template_id)) {
                error_log("Assigning template_id $template_id to user_id $user_id");
                assignRoleTemplate($pdo, $user_id, $template_id, $_SESSION['user_id']);
                error_log("Template assignment completed");
            } else {
                error_log("No template_id provided, skipping template assignment");
            }
            
            // Assign custom permissions if provided
            if (!empty($custom_permissions)) {
                assignCustomPermissions($pdo, $user_id, $custom_permissions, $_SESSION['user_id']);
            }
            
            // Commit the user creation and role assignment
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        // Step 2: Generate and send OTP for all users (students, admin, teacher)
        $otp_sent = false;
        
        error_log("Starting email OTP generation for user $user_id ($email) with role: $role");
        
        $otpHandler = new OTPHandler($pdo);
        $otp = $otpHandler->generateOTPCode(); // Only generate the code, don't insert to DB
        
        error_log("Generated email OTP code: $otp for user $user_id");
        
        $otp_sent = $otpHandler->sendOTPWithUserId($email, $otp, 'registration', $user_id);
        
        if (!$otp_sent) {
            // If OTP fails, we should still have the user created, but log the error
            error_log("Failed to send email OTP for user $user_id, but user was created successfully");
            error_log("User can still be manually verified by an admin");
            // Don't throw exception here as user was created successfully
        } else {
            error_log("Email OTP sent successfully for user $user_id");
        }
        
        // Return success response with role-specific message
        $message = '';
        if ($role === 'student') {
            $message = 'User created successfully! Verification email has been sent.';
        } else {
            $message = 'User created successfully! Email and phone verification required before first login.';
        }
            
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'user_id' => $user_id,
            'email' => $email,
            'phone_number' => $normalized_phone,
            'role' => $role,
            'otp_sent' => $otp_sent,
            'email_verified' => FALSE,
            'phone_verified' => FALSE,
            'requires_verification' => true
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
