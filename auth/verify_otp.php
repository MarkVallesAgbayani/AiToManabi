<?php
session_start();
// Ensure timezone is set to Philippines
date_default_timezone_set('Asia/Manila');
require_once '../config/database.php';
require_once 'otp_handler.php';
require_once '../dashboard/includes/placement_test_functions.php';

// Helper function to set modern alerts
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

// Debug log - Initial session state
error_log("Verify OTP Page - Initial session state: " . 
          "otp_user_id: " . ($_SESSION['otp_user_id'] ?? 'not set') . 
          ", reset_email: " . ($_SESSION['reset_email'] ?? 'not set') . 
          ", otp_type: " . ($_SESSION['otp_type'] ?? 'not set'));

// Initialize OTP handler
$otpHandler = new OTPHandler($pdo);

// Get OTP expiration time for timer
$otpExpirationTime = null;
if (isset($_SESSION['otp_user_id']) && isset($_SESSION['otp_type'])) {
    $stmt = $pdo->prepare("
        SELECT expires_at FROM otps 
        WHERE user_id = ? 
        AND type = ? 
        AND is_used = 0
        AND expires_at > NOW()
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['otp_user_id'], $_SESSION['otp_type']]);
    $otpRecord = $stmt->fetch();
    if ($otpRecord) {
        $otpExpirationTime = $otpRecord['expires_at'];
    }
}


// Check if user has session variables or token for verification
$token = $_GET['token'] ?? '';
$hasSession = isset($_SESSION['otp_user_id']) && isset($_SESSION['otp_type']);

if (!$hasSession && empty($token)) {
    error_log("Verify OTP Page - Missing required session variables or token");
    error_log("Available session variables: " . print_r($_SESSION, true));
    header('Location: /AIToManabi_Updated/dashboard/login.php');
    exit();
}

// If user came from email link, process token
if (!empty($token)) {
    $result = $otpHandler->verifyByToken($token);
    
    if ($result && $result['success']) {
        // Set session variables for OTP verification
        $_SESSION['otp_user_id'] = $result['user_id'];
        $_SESSION['otp_type'] = $result['type'];
        
        // Set success message if it's password change
        if ($result['type'] === 'password_change') {
            $_SESSION['password_change_success'] = 'Password changed successfully! Please verify your email to complete the process.';
        }
    } else {
        $error = 'Invalid or expired verification link. Please try again.';
    }
}

$error = '';
$success = '';

// Determine if we're in the SMS/phone verification step.
// Some flows may set a session flag, set otp_type to 'sms_registration',
// or include a query param (e.g. ?sms_registration=1) when landing on this page.
$isSmsRegistration = false;
if (isset($_SESSION['phone_verification_step']) || (isset($_SESSION['otp_type']) && $_SESSION['otp_type'] === 'sms_registration') || (isset($_GET['sms_registration']) && $_GET['sms_registration'] == '1')) {
    $isSmsRegistration = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';
    
    if (empty($otp)) {
        $error = 'Please enter the OTP code.';
    } else {
        // Debug log - Before OTP verification
        error_log("Verify OTP Page - Attempting verification: " . 
                  "User ID: " . $_SESSION['otp_user_id'] . 
                  ", Type: " . $_SESSION['otp_type'] . 
                  ", OTP: " . $otp . 
                  ", Reset Email: " . ($_SESSION['reset_email'] ?? 'not set') .
                  ", Password Change Success: " . ($_SESSION['password_change_success'] ?? 'not set'));
        
        if ($otpHandler->verifyOTP($_SESSION['otp_user_id'], $otp, $_SESSION['otp_type'])) {
            switch ($_SESSION['otp_type']) {
                case 'registration':
                    // Mark user's email as verified in the database
                    $stmt = $pdo->prepare("UPDATE users SET email_verified = TRUE WHERE id = ?");
                    $stmt->execute([$_SESSION['otp_user_id']]);
                    
                    // Get user's phone number to initiate SMS verification
                    $stmt = $pdo->prepare("SELECT phone_number FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['otp_user_id']]);
                    $user = $stmt->fetch();
                    
                    if ($user && !empty($user['phone_number'])) {
                        // Send SMS OTP for phone verification
                        try {
                            require_once __DIR__ . '/../services/PhilSMSService.php';
                            $philSMS = new PhilSMSService();
                            
                            // Generate SMS OTP
                            $sms_otp = sprintf('%06d', random_int(0, 999999));
                            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                            
                            // Store SMS OTP in database
                            $stmt = $pdo->prepare("
                                INSERT INTO otps (user_id, phone_number, otp_code, type, expires_at) 
                                VALUES (?, ?, ?, 'sms_registration', ?)
                            ");
                            $stmt->execute([$_SESSION['otp_user_id'], $user['phone_number'], $sms_otp, $expires_at]);
                            
                            // Send SMS
                            $sms_result = $philSMS->sendOTP($user['phone_number'], $sms_otp, 'registration');
                            
                            if ($sms_result['success']) {
                                // Update session for SMS verification phase
                                $_SESSION['otp_type'] = 'sms_registration';
                                $_SESSION['phone_verification_step'] = true;
                                
                                setAlert('success', '📧 Email verified! Now please verify your phone number. SMS sent to ' . substr($user['phone_number'], 0, 6) . '****');
                                header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                                exit();
                            } else {
                                // SMS failed, but email is verified - complete registration
                                error_log("SMS sending failed during registration: " . ($sms_result['error'] ?? 'Unknown error'));
                                setAlert('warning', '📧 Email verified! SMS verification failed, but your account is created. You can add phone verification later.');
                            }
                        } catch (Exception $e) {
                            error_log("SMS verification error during registration: " . $e->getMessage());
                            setAlert('warning', '📧 Email verified! Phone verification unavailable, but your account is created.');
                        }
                    }
                    
                    // Complete registration
                    setAlert('success', '🎉 Account successfully created and verified! Please login to continue.');
                    
                    // Clear OTP session data
                    unset($_SESSION['otp_user_id']);
                    unset($_SESSION['otp_type']);
                    unset($_SESSION['phone_verification_step']);
                    
                    // Redirect to login page
                    header('Location: /AIToManabi_Updated/dashboard/login.php');
                    break;
                    
                case 'sms_registration':
                    // Mark user's phone as verified
                    $stmt = $pdo->prepare("UPDATE users SET phone_verified = TRUE WHERE id = ?");
                    $stmt->execute([$_SESSION['otp_user_id']]);
                    
                    // Set success alert
                    setAlert('success', '🎉 Phone number verified! Your account is now fully verified. Please login to continue.');
                    
                    // Clear session data
                    unset($_SESSION['otp_user_id']);
                    unset($_SESSION['otp_type']);
                    unset($_SESSION['phone_verification_step']);
                    
                    // Redirect to login
                    header('Location: /AIToManabi_Updated/dashboard/login.php');
                    break;
                    
                case 'login':
                    // Get user details
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['otp_user_id']]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Log OTP verification activity for analytics
                        try {
                            require_once '../dashboard/real_time_activity_logger.php';
                            $logger = new RealTimeActivityLogger($pdo);
                            $logger->logActivity($user['id'], 'otp_verification', 'authentication', 'otp_success', [
                                'verification_method' => 'otp',
                                'user_role' => $user['role']
                            ]);
                        } catch (Exception $e) {
                            error_log("Activity logging error: " . $e->getMessage());
                        }
                        
                        // Check if admin/teacher needs to change password (first time login)
                        if (in_array($user['role'], ['admin', 'teacher']) && $user['is_first_login']) {
                            header('Location: /AIToManabi_Updated/dashboard/change_password.php');
                            exit();
                        }
                        
                        // Redirect based on role after OTP verification
                        switch($user['role']) {
                            case 'admin':
                                // ALWAYS redirect admin to regular admin dashboard
                                header('Location: /AIToManabi_Updated/dashboard/admin.php');
                                break;
                                
                            case 'teacher':
                                // Check for hybrid permissions for teacher
                                $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
                                $stmt->execute([$user['id']]);
                                $admin_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                if (!empty($admin_permissions)) {
                                    $_SESSION['is_hybrid'] = true;
                                    $_SESSION['permissions'] = $admin_permissions;
                                    // Teacher has admin permissions - redirect to hybrid teacher
                                    header('Location: /AIToManabi_Updated/dashboard/hybrid_teacher.php');
                                } else {
                                    // Regular teacher
                                    header('Location: /AIToManabi_Updated/dashboard/teacher.php');
                                }
                                break;
                                
                            case 'student':
                                // Check if student needs to take placement test
                                if (needsPlacementTest($pdo, $user['id'])) {
                                    $redirectUrl = getPlacementTestRedirectUrl($pdo, '/dashboard/');
                                    if ($redirectUrl) {
                                        header('Location: ' . $redirectUrl);
                                    } else {
                                        header('Location: /AIToManabi_Updated/dashboard/dashboard.php');
                                    }
                                } else {
                                    header('Location: /AIToManabi_Updated/dashboard/dashboard.php');
                                }
                                break;
                                
                            default:
                                header('Location: /AIToManabi_Updated/dashboard/dashboard.php');
                        }
                    } else {
                        $error = 'User not found.';
                    }
                    break;
                    
                case 'password_change':
                    // Password change verification - redirect to appropriate dashboard
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['otp_user_id']]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // Clear OTP session data
                        unset($_SESSION['otp_user_id']);
                        unset($_SESSION['otp_type']);
                        
                        // Set success message
                        if (isset($_SESSION['password_change_success'])) {
                            setAlert('success', $_SESSION['password_change_success']);
                            unset($_SESSION['password_change_success']);
                        }
                        
                        // Redirect based on role
                        switch($user['role']) {
                            case 'admin':
                                // Check for hybrid permissions for admin
                                $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_teacher_courses', 'nav_teacher_create_module', 'nav_teacher_placement_test', 'nav_teacher_settings')");
                                $stmt->execute([$user['id']]);
                                $teacher_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                if (!empty($teacher_permissions)) {
                                    header('Location: /AIToManabi_Updated/dashboard/hybrid_admin.php');
                                } else {
                                    header('Location: /AIToManabi_Updated/dashboard/admin.php');
                                }
                                break;
                                
                            case 'teacher':
                                // Check for hybrid permissions for teacher
                                $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
                                $stmt->execute([$user['id']]);
                                $admin_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                if (!empty($admin_permissions)) {
                                    $_SESSION['is_hybrid'] = true;
                                    $_SESSION['permissions'] = $admin_permissions;
                                    header('Location: /AIToManabi_Updated/dashboard/hybrid_teacher.php');
                                } else {
                                    header('Location: /AIToManabi_Updated/dashboard/teacher.php');
                                }
                                break;
                                
                            default:
                                header('Location: /AIToManabi_Updated/dashboard/dashboard.php');
                        }
                    } else {
                        $error = 'User not found.';
                    }
                    break;
                    
                case 'password_reset':
                    error_log("OTP verified for password reset - User ID: " . $_SESSION['otp_user_id'] . 
                             ", Email: " . $_SESSION['reset_email'] . 
                             ", Type: " . $_SESSION['otp_type']);
                    header('Location: /AIToManabi_Updated/auth/new-password-creation.php');
                    break;
            }
            exit();  
        } else {
            $error = 'Invalid or expired OTP code. Please try again or request a new code.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .font-jp { font-family: 'Noto Sans JP', sans-serif; }
        input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
        }
        input:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 1px #ef4444;
        }
    </style>
</head>
<body class="bg-gray-100 font-jp min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full m-4">
        <div class="flex justify-between items-center mb-6">
            <?php if ($isSmsRegistration): ?>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Verify Phone Number</h2>
                        <p class="text-sm text-gray-600 mt-1">Step 2: Enter the SMS code sent to your phone</p>
                    </div>
                <?php else: ?>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Verify Email</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo ($_SESSION['otp_type'] ?? '') === 'registration' ? 'Step 1: Enter the code sent to your email' : 'Enter the verification code'; ?>
                        </p>
                    </div>
                <?php endif; ?>
            <a href="../dashboard/login.php" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </a>
        </div>
        
        <!-- Progress Indicator for Registration -->
        <?php if (($_SESSION['otp_type'] ?? '') === 'registration' || $isSmsRegistration): ?>
        <div class="mb-6">
            <div class="flex items-center justify-center space-x-4">
                <div class="flex items-center <?php echo (isset($_SESSION['otp_type']) && $_SESSION['otp_type'] === 'registration') ? 'text-blue-600' : 'text-green-600'; ?>">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full <?php echo (isset($_SESSION['otp_type']) && $_SESSION['otp_type'] === 'registration') ? 'bg-blue-600' : 'bg-green-600'; ?> text-white text-sm font-medium mr-2">
                        <?php echo (isset($_SESSION['otp_type']) && $_SESSION['otp_type'] === 'registration') ? '1' : '✓'; ?>
                    </div>
                    <span class="text-sm font-medium">Email</span>
                </div>
                <div class="w-8 h-0.5 bg-gray-300"></div>
                <div class="flex items-center <?php echo $isSmsRegistration ? 'text-blue-600' : 'text-gray-400'; ?>">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full <?php echo $isSmsRegistration ? 'bg-blue-600' : 'bg-gray-300'; ?> text-white text-sm font-medium mr-2">
                        2
                    </div>
                    <span class="text-sm font-medium">Phone</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $success; ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Enter 6-digit OTP</label>
                <input type="text" name="otp" required 
                       pattern="[0-9]{6}" maxlength="6" minlength="6"
                       class="mt-1 block w-full text-center tracking-widest text-2xl"
                       placeholder="000000">
            </div>
            
            <button type="submit" class="w-full bg-red-600 text-white rounded-md py-2 px-4 hover:bg-red-700">
                Verify OTP
            </button>
            
            <div class="text-center text-sm text-gray-600">
                <p class="mb-3">Didn't receive the code?</p>
                
                <?php if (($_SESSION['otp_type'] ?? '') === 'sms_registration'): ?>
                    <!-- SMS Registration Step - Show SMS resend only -->
                    <?php 
                    // Get phone number for SMS registration step
                    $stmt = $pdo->prepare("SELECT phone_number FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['otp_user_id']]);
                    $user = $stmt->fetch();
                    if ($user && !empty($user['phone_number'])): 
                        // Mask phone number for display
                        $masked_phone = substr($user['phone_number'], 0, 6) . '****';
                    ?>
                    <button type="button" id="resendSMSBtn" onclick="resendOTP('sms')" 
                            class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white py-2 px-4 rounded-md font-medium transition-colors duration-200 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span id="resendSMSText">Resend SMS to <?php echo $masked_phone; ?></span>
                        <svg id="resendSMSSpinner" class="hidden animate-spin ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>

                    <button type="button" id="resendEmailBtn" onclick="resendOTP('email')" style="display:none;"
                            class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white py-2 px-4 rounded-md font-medium transition-colors duration-200 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span id="resendEmailText">Backup Email Resend</span>
                        <svg id="resendEmailSpinner" class="hidden animate-spin ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Email Primary for other OTP types (registration, login, password_reset, etc.) -->
                    <button type="button" id="resendEmailBtn" onclick="resendOTP('email')" 
                            class="w-full mb-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white py-2 px-4 rounded-md font-medium transition-colors duration-200 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span id="resendEmailText">Resend via Email</span>
                    <svg id="resendEmailSpinner" class="hidden animate-spin ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>

                <!-- SMS Backup Button (if user has verified phone) -->
                <?php if (isset($_SESSION['otp_user_id'])): ?>
                    <?php 
                    // Check if user has verified phone number for SMS backup
                    $stmt = $pdo->prepare("SELECT phone_number, phone_verified FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['otp_user_id']]);
                    $user = $stmt->fetch();
                    if ($user && $user['phone_verified'] == 1 && !empty($user['phone_number'])): 
                        // Mask phone number for display
                        $masked_phone = substr($user['phone_number'], 0, 6) . '****';
                    ?>
                    <button type="button" id="resendSMSBtn" onclick="resendOTP('sms')" 
                            class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white py-2 px-4 rounded-md font-medium transition-colors duration-200 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span id="resendSMSText">Backup SMS to <?php echo $masked_phone; ?></span>
                        <svg id="resendSMSSpinner" class="hidden animate-spin ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Cooldown and Timer Info -->
                <div class="mt-3">
                    <span id="resendCooldown" class="hidden text-xs text-gray-500">
                        Resend available in <span id="cooldownTimer">60</span> seconds
                    </span>
                    <?php if ($otpExpirationTime): ?>
                    <div id="otpTimer" class="text-xs text-gray-500 mt-1">
                        OTP expires in <span id="timerDisplay">--:--</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Modern Alert Container -->
    <div id="alertContainer" class="fixed top-4 right-4 z-50 max-w-sm w-full"></div>

    <script>
        // OTP Timer functionality
        <?php if ($otpExpirationTime): ?>
        // Initialize OTP timer with server expiration time
        const serverExpirationTime = new Date('<?php echo $otpExpirationTime; ?>').getTime();
        window.otpExpirationTime = serverExpirationTime;
        
        // Start the timer
        window.otpTimerInterval = setInterval(() => {
            updateOTPTimer(serverExpirationTime);
        }, 1000);
        
        // Initial call
        updateOTPTimer(serverExpirationTime);
        <?php endif; ?>
        
        // Auto-format OTP input
        document.querySelector('input[name="otp"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Modern Alert System
        function showModernAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert-' + Date.now();
            
            const alertHTML = `
                <div id="${alertId}" class="bg-white border-l-4 border-red-500 shadow-lg rounded-lg p-4 mb-4 transform transition-all duration-300 ease-in-out translate-x-full opacity-0">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900">${message}</p>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <button onclick="closeAlert('${alertId}')" class="inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition ease-in-out duration-150">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            alertContainer.insertAdjacentHTML('beforeend', alertHTML);
            
            // Animate in
            setTimeout(() => {
                const alertElement = document.getElementById(alertId);
                alertElement.classList.remove('translate-x-full', 'opacity-0');
                alertElement.classList.add('translate-x-0', 'opacity-100');
            }, 10);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                closeAlert(alertId);
            }, 5000);
        }

        function closeAlert(alertId) {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                alertElement.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    alertElement.remove();
                }, 300);
            }
        }

        function resendOTP(type) {
            // Determine which button was clicked
            const isEmail = type === 'email';
            const isSMS = type === 'sms';
            
            // Get appropriate button elements
            const resendBtn = isEmail ? document.getElementById('resendEmailBtn') : document.getElementById('resendSMSBtn');
            const resendText = isEmail ? document.getElementById('resendEmailText') : document.getElementById('resendSMSText');
            const resendSpinner = isEmail ? document.getElementById('resendEmailSpinner') : document.getElementById('resendSMSSpinner');
            const resendCooldown = document.getElementById('resendCooldown');
            
            // Check if any button is in cooldown
            if (resendCooldown && !resendCooldown.classList.contains('hidden')) {
                showModernAlert('warning', 'Please wait for the cooldown to finish before requesting another code.');
                return;
            }
            
            // Check if button exists (SMS button may not exist for all users)
            if (!resendBtn) {
                showModernAlert('error', 'This verification method is not available.');
                return;
            }
            
            // Show loading state
            resendBtn.disabled = true;
            const originalText = resendText.textContent;
            resendText.textContent = isEmail ? 'Sending Email...' : 'Sending SMS...';
            resendSpinner.classList.remove('hidden');
            
            // Disable all visible resend buttons during request
            const allResendBtns = document.querySelectorAll('#resendEmailBtn:not([style*="display:none"]), #resendSMSBtn');
            allResendBtns.forEach(btn => btn.disabled = true);
            
            fetch('resend_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ type: type })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const successMsg = isEmail ? 
                        'New OTP has been sent to your email.' : 
                        'Backup OTP has been sent to your phone via SMS.';
                    showModernAlert('success', successMsg);
                    
                    // Start cooldown timer for all buttons
                    startResendCooldownTimer();
                    
                    // Restart OTP expiration timer with fresh time
                    restartOTPTimer();
                    
                    // Re-enable OTP input field and form immediately
                    reEnableOTPForm();
                } else {
                    showModernAlert('error', data.message || `Failed to send ${type.toUpperCase()} OTP. Please try again.`);
                }
            })
            .catch(error => {
                console.error(`Resend ${type.toUpperCase()} OTP Error:`, error);
                showModernAlert('error', `Failed to send ${type.toUpperCase()} OTP. Please check your connection and try again.`);
            })
            .finally(() => {
                // Reset loading state for the clicked button
                resendText.textContent = originalText;
                resendSpinner.classList.add('hidden');
                
                // If not in cooldown, re-enable visible buttons
                if (!resendCooldown || resendCooldown.classList.contains('hidden')) {
                    const visibleBtns = document.querySelectorAll('#resendEmailBtn:not([style*="display:none"]), #resendSMSBtn');
                    visibleBtns.forEach(btn => btn.disabled = false);
                }
            });
        }
        
        function startResendCooldownTimer() {
            const resendEmailBtn = document.getElementById('resendEmailBtn');
            const resendSMSBtn = document.getElementById('resendSMSBtn');
            const resendEmailText = document.getElementById('resendEmailText');
            const resendSMSText = document.getElementById('resendSMSText');
            const resendCooldown = document.getElementById('resendCooldown');
            const cooldownTimer = document.getElementById('cooldownTimer');
            
            let remainingTime = 60; // 1 minute cooldown
            
            // Disable all visible resend buttons
            const allResendBtns = document.querySelectorAll('#resendEmailBtn:not([style*="display:none"]), #resendSMSBtn');
            allResendBtns.forEach(btn => btn.disabled = true);
            
            // Reset button texts based on context
            if (resendEmailText) {
                // Check if we're in SMS registration step
                const isSmSRegistration = window.location.search.includes('sms_registration') || 
                                         document.querySelector('h2')?.textContent?.includes('Verify Phone Number');
                
                if (!isSmSRegistration) {
                    resendEmailText.textContent = 'Resend via Email';
                }
                // Skip setting text for SMS registration as button is hidden
            }
            
            if (resendSMSText) {
                // Extract phone number if present, or use default text
                const phoneMatch = resendSMSText.textContent.match(/(?:Resend SMS to|Backup SMS to) (.*)/);
                if (phoneMatch) {
                    const isSmSRegistration = window.location.search.includes('sms_registration') || 
                                             document.querySelector('h2')?.textContent?.includes('Verify Phone Number');
                    
                    if (isSmSRegistration) {
                        resendSMSText.textContent = `Resend SMS to ${phoneMatch[1]}`;
                    } else {
                        resendSMSText.textContent = `Backup SMS to ${phoneMatch[1]}`;
                    }
                } else {
                    resendSMSText.textContent = 'Resend SMS';
                }
            }
            
            // Show cooldown
            resendCooldown.classList.remove('hidden');
            cooldownTimer.textContent = remainingTime;
            
            const timer = setInterval(() => {
                remainingTime--;
                cooldownTimer.textContent = remainingTime;
                
                if (remainingTime <= 0) {
                    clearInterval(timer);
                    // Re-enable all visible buttons
                    const visibleBtns = document.querySelectorAll('#resendEmailBtn:not([style*="display:none"]), #resendSMSBtn');
                    visibleBtns.forEach(btn => btn.disabled = false);
                    resendCooldown.classList.add('hidden');
                }
            }, 1000);
        }
        
        function restartOTPTimer() {
            // Clear any existing OTP timer
            if (window.otpTimerInterval) {
                clearInterval(window.otpTimerInterval);
            }
            
            // Set new expiration time (5 minutes from now)
            const newExpirationTime = new Date().getTime() + (5 * 60 * 1000);
            
            // Update the global expiration time variable
            window.otpExpirationTime = newExpirationTime;
            
            // Reset timer display HTML first
            const timerElement = document.getElementById('otpTimer');
            if (timerElement) {
                timerElement.innerHTML = 'OTP expires in <span id="timerDisplay">--:--</span>';
            }
            
            // Start the timer again
            window.otpTimerInterval = setInterval(() => {
                updateOTPTimer(newExpirationTime);
            }, 1000);
            
            // Initial call
            updateOTPTimer(newExpirationTime);
        }
        
        function updateOTPTimer(expirationTime) {
            const now = new Date().getTime();
            const timeLeft = expirationTime - now;
            
            const timerDisplay = document.getElementById('timerDisplay');
            const timerElement = document.getElementById('otpTimer');
            
            // Check if elements exist
            if (!timerDisplay || !timerElement) {
                return;
            }
            
            if (timeLeft > 0) {
                const minutes = Math.floor(timeLeft / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                
                timerDisplay.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                
                // Change color to red when less than 1 minute
                if (timeLeft <= 60000) { // 1 minute = 60000ms
                    timerElement.className = 'text-xs text-red-500';
                } else {
                    timerElement.className = 'text-xs text-gray-500';
                }
            } else {
                // OTP expired
                timerDisplay.textContent = '00:00';
                timerElement.innerHTML = '<span class="text-red-500">OTP has expired</span>';
                
                // Disable the form
                disableOTPForm();
                
                // Clear the timer
                if (window.otpTimerInterval) {
                    clearInterval(window.otpTimerInterval);
                }
                
                return;
            }
        }
        
        function disableOTPForm() {
            // Disable the form when OTP expires
            document.querySelector('input[name="otp"]').disabled = true;
            document.querySelector('button[type="submit"]').disabled = true;
            document.querySelector('button[type="submit"]').textContent = 'OTP Expired';
            document.querySelector('button[type="submit"]').className = 'w-full bg-gray-400 text-white rounded-md py-2 px-4 cursor-not-allowed';
        }
        
        function reEnableOTPForm() {
            // Re-enable the form when new OTP is sent
            const otpInput = document.querySelector('input[name="otp"]');
            const submitButton = document.querySelector('button[type="submit"]');
            
            // Enable input field
            otpInput.disabled = false;
            otpInput.value = ''; // Clear the input field
            otpInput.focus(); // Focus on input for immediate typing
            
            // Enable submit button
            submitButton.disabled = false;
            submitButton.textContent = 'Verify OTP';
            submitButton.className = 'w-full bg-red-600 text-white rounded-md py-2 px-4 hover:bg-red-700';
        }

    </script>
</body>
</html>
