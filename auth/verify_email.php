<?php
session_start();
require_once '../config/database.php';
require_once '../dashboard/includes/placement_test_functions.php';
require_once 'otp_handler.php';

$otpHandler = new OTPHandler($pdo);
$error = '';
$success = '';
$verification_type = '';
$show_otp_form = false;

// Get token from URL
$token = $_GET['token'] ?? '';

// Handle OTP form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $otp = $_POST['otp'] ?? '';
    
    if (empty($otp)) {
        $error = 'Please enter the OTP code.';
    } else {
        // Get user ID from session or token
        $userId = $_SESSION['otp_user_id'] ?? null;
        $otpType = $_SESSION['otp_type'] ?? 'registration';
        
        if ($userId && $otpHandler->verifyOTP($userId, $otp, $otpType)) {
            // Get user details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Clear OTP session data
                unset($_SESSION['otp_user_id']);
                unset($_SESSION['otp_type']);
                
                // Check if admin/teacher needs to change password (first time login)
                if (in_array($user['role'], ['admin', 'teacher']) && $user['is_first_login']) {
                    header('Location: ../dashboard/change_password.php');
                    exit();
                }
                
                // Helper function to check if account is hybrid
                function isHybridAccount($pdo, $userId, $role) {
                    if ($role === 'admin') {
                        $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_teacher_courses', 'nav_teacher_create_module', 'nav_teacher_placement_test', 'nav_teacher_settings')");
                        $stmt->execute([$userId]);
                        $teacher_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        return !empty($teacher_permissions);
                    } elseif ($role === 'teacher') {
                        $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
                        $stmt->execute([$userId]);
                        $admin_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        return !empty($admin_permissions);
                    }
                    return false;
                }
                
                // Redirect based on role
                switch($user['role']) {
                    case 'admin':
                        if (isHybridAccount($pdo, $user['id'], 'admin')) {
                            header('Location: ../dashboard/hybrid_admin.php');
                        } else {
                            header('Location: ../dashboard/admin.php');
                        }
                        break;
                        
                    case 'teacher':
                        if (isHybridAccount($pdo, $user['id'], 'teacher')) {
                            $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
                            $stmt->execute([$user['id']]);
                            $admin_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            $_SESSION['is_hybrid'] = true;
                            $_SESSION['permissions'] = $admin_permissions;
                            header('Location: ../dashboard/hybrid_teacher.php');
                        } else {
                            header('Location: ../dashboard/teacher.php');
                        }
                        break;
                        
                    case 'student':
                        if (function_exists('needsPlacementTest') && needsPlacementTest($pdo, $user['id'])) {
                            $redirectUrl = getPlacementTestRedirectUrl($pdo, '../dashboard/');
                            if ($redirectUrl) {
                                header('Location: ' . $redirectUrl);
                            } else {
                                header('Location: ../dashboard/dashboard.php');
                            }
                        } else {
                            header('Location: ../dashboard/dashboard.php');
                        }
                        break;
                        
                    default:
                        header('Location: ../dashboard/dashboard.php');
                }
                exit();
            } else {
                $error = 'User not found.';
            }
        } else {
            $error = 'Invalid or expired OTP code. Please try again.';
        }
    }
}

// Handle token-based verification (for email links)
if (!empty($token)) {
    $result = $otpHandler->verifyByToken($token);
    
    if ($result && $result['success']) {
        $verification_type = $result['type'];
        
        switch ($result['type']) {
            case 'registration':
                $success = 'Email verified successfully! Your account has been activated. You can now log in.';
                break;
                
            case 'login':
                // Set session for OTP verification
                $_SESSION['otp_user_id'] = $result['user_id'];
                $_SESSION['otp_type'] = 'login';
                $show_otp_form = true;
                $success = 'Please enter the OTP code sent to your email to complete verification.';
                break;
                
            case 'password_reset':
                $_SESSION['reset_user_id'] = $result['user_id'];
                header('Location: new-password-creation.php');
                exit();
                break;
                
            default:
                $error = 'Invalid verification type.';
        }
    } else {
        $error = 'Invalid or expired verification link. Please request a new verification email.';
    }
} else {
    // No token - show OTP form for manual verification
    $show_otp_form = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - AiToManabi LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="gradient-bg rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Email Verification</h1>
            <p class="text-gray-600 mt-2">Verifying your email address</p>
        </div>

        <!-- Main Content -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <?php if ($error): ?>
                <!-- Error State -->
                <div class="text-center">
                    <div class="bg-red-100 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Verification Failed</h2>
                    <p class="text-red-600 mb-6"><?php echo htmlspecialchars($error); ?></p>
                    
                    <div class="space-y-3">
                        <a href="../dashboard/login.php" 
                           class="w-full bg-blue-600 text-white rounded-lg px-4 py-2 font-medium hover:bg-blue-700 transition-colors block text-center">
                            Go to Login
                        </a>
                        <a href="../dashboard/signup.php" 
                           class="w-full bg-gray-100 text-gray-700 rounded-lg px-4 py-2 font-medium hover:bg-gray-200 transition-colors block text-center">
                            Create New Account
                        </a>
                    </div>
                </div>
            <?php elseif ($success && !$show_otp_form): ?>
                <!-- Success State -->
                <div class="text-center">
                    <div class="bg-green-100 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Verification Successful!</h2>
                    <p class="text-green-600 mb-6"><?php echo htmlspecialchars($success); ?></p>
                    
                    <div class="space-y-3">
                        <a href="../dashboard/login.php" 
                           class="w-full gradient-bg text-white rounded-lg px-4 py-2 font-medium hover:opacity-90 transition-opacity block text-center">
                            Continue to Login
                        </a>
                    </div>
                </div>
            <?php elseif ($show_otp_form): ?>
                <!-- OTP Form -->
                <div class="text-center">
                    <div class="bg-blue-100 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Enter Verification Code</h2>
                    <p class="text-gray-600 mb-6">
                        <?php echo $success ? htmlspecialchars($success) : 'Please enter the 6-digit code sent to your email address.'; ?>
                    </p>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="otp" class="block text-sm font-medium text-gray-700 mb-2">Verification Code</label>
                            <input type="text" 
                                   id="otp" 
                                   name="otp" 
                                   maxlength="6" 
                                   pattern="[0-9]{6}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center text-lg font-mono tracking-widest"
                                   placeholder="000000"
                                   required
                                   autocomplete="one-time-code">
                        </div>
                        
                        <button type="submit" 
                                class="w-full gradient-bg text-white rounded-lg px-4 py-3 font-medium hover:opacity-90 transition-opacity">
                            Verify Email
                        </button>
                    </form>
                    
                    <div class="mt-6 text-sm text-gray-500">
                        <p>Didn't receive the code? Check your spam folder or</p>
                        <a href="../dashboard/login.php" class="text-blue-600 hover:text-blue-700 font-medium">
                            try logging in again
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Loading State -->
                <div class="text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Verifying...</h2>
                    <p class="text-gray-600">Please wait while we verify your email address.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-sm text-gray-500">
                Need help? <a href="mailto:support@aitomanabi.com" class="text-blue-600 hover:text-blue-700">Contact Support</a>
            </p>
        </div>
    </div>

    <script>
        // Auto-redirect after 3 seconds if there's an error
        <?php if ($error): ?>
        setTimeout(function() {
            window.location.href = '../dashboard/login.php';
        }, 5000);
        <?php endif; ?>
        
        // OTP input enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp');
            if (otpInput) {
                otpInput.focus();
                
                // Only allow numbers
                otpInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/[^0-9]/g, '');
                });
                
                // Auto-submit when 6 digits are entered
                otpInput.addEventListener('input', function(e) {
                    if (e.target.value.length === 6) {
                        e.target.form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>
