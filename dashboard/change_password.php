<?php
session_start();
// Ensure timezone is set to Philippines
date_default_timezone_set('Asia/Manila');
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if user is admin or teacher
if (!in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header("Location: ../index.php");
    exit();
}

// Determine which user's password to change
$target_user_id = $_SESSION['user_id']; // Default to current user
$is_admin_changing_other = false;

// Check if admin is changing another user's password
if (isset($_GET['user_id']) && $_SESSION['role'] === 'admin') {
    $target_user_id = (int)$_GET['user_id'];
    $is_admin_changing_other = true;
}

// Get target user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: ../index.php");
    exit();
}

// Check if user needs to change password (is_first_login = TRUE) - only for first login flow
if (!$is_admin_changing_other && !$user['is_first_login']) {
    // User has already changed password, redirect to appropriate dashboard
    switch($_SESSION['role']) {
        case 'admin':
            header("Location: admin.php");
            break;
        case 'teacher':
            header("Location: teacher.php");
            break;
        default:
            header("Location: dashboard.php");
    }
    exit();
}

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate current password (only required for self-password changes)
    if (!$is_admin_changing_other) {
        if (empty($current_password)) {
            $errors['current_password'] = 'Current password is required.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
    }
    
    // Validate new password (same validation as signup)
    if (empty($new_password)) {
        $errors['new_password'] = 'New password is required.';
    } elseif (strlen($new_password) < 12) {
        $errors['new_password'] = 'Password must be at least 12 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $errors['new_password'] = 'Password must include at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $errors['new_password'] = 'Password must include at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $errors['new_password'] = 'Password must include at least one number.';
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
        $errors['new_password'] = 'Password must include at least one special character.';
    }
    
    // Validate password confirmation
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($new_password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    
    // Check if new password is different from current password
    if (password_verify($new_password, $user['password'])) {
        $errors['new_password'] = 'New password must be different from your current password.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update password and set is_first_login to FALSE
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, 
                    is_first_login = FALSE,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            // Log the password change
            $stmt = $pdo->prepare("
                INSERT INTO admin_action_logs (admin_id, user_id, action, details) 
                VALUES (?, ?, 'password_change', ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $_SESSION['user_id'], 
                json_encode(['changed_by' => 'self', 'timestamp' => date('Y-m-d H:i:s')])
            ]);
            
            $pdo->commit();
            
            // Helper function to check if account is hybrid
            function isHybridAccount($pdo, $userId, $role) {
                if ($role === 'admin') {
                    // Check if admin has teacher permissions
                    $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_teacher_courses', 'nav_teacher_create_module', 'nav_teacher_placement_test', 'nav_teacher_settings')");
                    $stmt->execute([$userId]);
                    $teacher_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    return !empty($teacher_permissions);
                } elseif ($role === 'teacher') {
                    // Check if teacher has admin permissions
                    $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
                    $stmt->execute([$userId]);
                    $admin_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    return !empty($admin_permissions);
                }
                return false;
            }
            
            // Check if this is a hybrid account - if so, skip OTP verification
            if (isHybridAccount($pdo, $target_user_id, $user['role'])) {
                // Hybrid account - direct access without OTP
                $_SESSION['success_message'] = 'Password changed successfully!';
                
                // Redirect based on target user's role
                switch($user['role']) {
                    case 'admin':
                        header("Location: hybrid_admin.php");
                        break;
                    case 'teacher':
                        header("Location: hybrid_teacher.php");
                        break;
                    default:
                        header("Location: dashboard.php");
                }
                exit();
            } else {
                // Full admin/teacher account - require OTP verification after password change
                require_once '../auth/otp_handler.php';
                $otpHandler = new OTPHandler($pdo);
                
                // Generate and send OTP for password change confirmation
                $otp = $otpHandler->generateOTPCode();
                error_log("Password Change OTP - Target User ID: " . $target_user_id . ", Email: " . $user['email'] . ", OTP: " . $otp);
                
                if ($otpHandler->sendOTPWithUserId($user['email'], $otp, 'password_change', $target_user_id)) {
                    // Set session for OTP verification
                    $_SESSION['otp_user_id'] = $target_user_id;
                    $_SESSION['otp_type'] = 'password_change';
                    $_SESSION['password_change_success'] = 'Password changed successfully! Please verify your email to complete the process.';
                    
                    error_log("Password Change OTP sent successfully - Session set for target user: " . $target_user_id);
                    
                    // Redirect to OTP verification
                    header("Location: ../auth/verify_otp.php");
                    exit();
                } else {
                    // If OTP sending fails, still allow access but show warning
                    $_SESSION['success_message'] = 'Password changed successfully! However, verification email could not be sent. You can access your dashboard.';
                    
                    // Redirect based on scenario
                    if ($is_admin_changing_other) {
                        // Admin changing another user's password - redirect back to users.php
                        header("Location: users.php?success=password_changed");
                    } else {
                        // User changing their own password - redirect to appropriate dashboard
                        switch($_SESSION['role']) {
                            case 'admin':
                                header("Location: admin.php");
                                break;
                            case 'teacher':
                                header("Location: teacher.php");
                                break;
                            default:
                                header("Location: dashboard.php");
                        }
                    }
                    exit();
                }
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = 'An error occurred while changing your password. Please try again.';
            error_log("Password change error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Japanese Learning Platform</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/validation_signup.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/signup_card.css?v=<?php echo time(); ?>">
    
    <!-- Additional CSS for password strength indicators -->
    <style>
        .strength-weak {
            background: linear-gradient(to right, #ef4444, #ef4444);
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .strength-medium {
            background: linear-gradient(to right, #f59e0b, #f59e0b);
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .strength-strong {
            background: linear-gradient(to right, #10b981, #10b981);
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .password-strength-meter {
            background-color: #e5e7eb;
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 4px;
        }
        
        .requirement.met {
            color: #059669 !important;
        }
        
        .requirement.unmet {
            color: #dc2626 !important;
        }
        
        .requirement.met::before {
            content: "✓ ";
            font-weight: bold;
        }
        
        .requirement.unmet::before {
            content: "• ";
            font-weight: bold;
        }
        
        /* Card styling improvements */
        .neon-border-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            width: 100%;
        }
        
        .modern-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .modern-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .modern-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        /* Password tooltip positioning */
        .modern-input {
            position: relative;
        }
        
        #password-tooltip {
            position: absolute;
            left: -280px;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 50;
            min-width: 250px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        
        #password-tooltip:not(.hidden) {
            opacity: 1;
            visibility: visible;
        }
        
        #password-tooltip::after {
            content: '';
            position: absolute;
            right: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 8px solid #e5e7eb;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
        }
        
        #password-tooltip::before {
            content: '';
            position: absolute;
            right: -7px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 8px solid white;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
        }
    </style>
    <!-- particles.js lib -->
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <!-- Fallback particles.js CDN -->
    <script>
        if (typeof particlesJS === 'undefined') {
            document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"><\/script>');
        }
    </script>
    <!-- stats.js lib -->
    <script src="https://threejs.org/examples/js/libs/stats.min.js"></script>
</head>
<body class="font-jp">
    <!-- Modern Alert Container -->
    <div id="alert-container" class="alert-container"></div>
    
    <!-- Main Container -->
    <div class="signup-container">
        <!-- particles.js container -->
        <div id="particles-js"></div>
        
        <!-- Main Card - Smaller centered card -->
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="w-full max-w-md">
                <div class="neon-border-container">
                    <div class="neon-border-anim"></div>

                    <!-- Card Layout -->
                    <div class="card-layout">
                        <!-- Form Section -->
                        <div class="form-section">
                    
                    <!-- Header -->
                    <div class="modern-header">
                        <h1 class="modern-title">Change Password</h1>
                        <?php if ($is_admin_changing_other): ?>
                            <p class="modern-subtitle">Changing password for: <strong><?php echo htmlspecialchars($user['username']); ?></strong> (<?php echo htmlspecialchars($user['email']); ?>)</p>
                        <?php else: ?>
                            <p class="modern-subtitle">Please set a secure password for your account</p>
                        <?php endif; ?>
                    </div>
            
                    <!-- Form -->
                    <form method="POST" id="changePasswordForm">
                        <!-- Current Password Field (only for self-password changes) -->
                        <?php if (!$is_admin_changing_other): ?>
                        <div class="mb-3">
                            <label class="modern-label">Current Password <span class="required-star">*</span></label>
                            <div class="modern-input">
                                <input 
                                    type="password" 
                                    name="current_password" 
                                    id="current_password" 
                                    required 
                                    placeholder="Enter your current password"
                                    autocomplete="current-password"
                                >
                                <div class="input-icon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <button type="button" class="password-toggle" onclick="togglePassword('current_password')" tabindex="-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="inline-error-message" id="current_password-error">
                                <?php echo $errors['current_password'] ?? ''; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- New Password Field -->
                        <div class="mb-3">
                            <label class="modern-label">New Password <span class="required-star">*</span></label>
                            <div class="modern-input">
                                <input 
                                    type="password" 
                                    name="new_password" 
                                    id="new_password" 
                                    required 
                                    placeholder="Create a strong password"
                                    minlength="12" 
                                    maxlength="64"
                                    pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{12,64}"
                                    autocomplete="new-password"
                                    onfocus="showPasswordTooltip()" 
                                    onblur="hidePasswordTooltip()" 
                                    onmouseover="showPasswordTooltip()" 
                                    onmouseout="hidePasswordTooltip()"
                                />
                                <div class="input-icon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password')" tabindex="-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                                <!-- Password Tooltip -->
                                <div id="password-tooltip" class="hidden">
                                    <p class="font-medium mb-2 text-xs">Password Requirements:</p>
                                    <ul class="list-disc pl-4 space-y-1">
                                        <li id="length-check-tooltip" class="requirement unmet text-xs">Minimum 12 characters (14+ recommended)</li>
                                        <li id="uppercase-check-tooltip" class="requirement unmet text-xs">Include uppercase letters</li>
                                        <li id="lowercase-check-tooltip" class="requirement unmet text-xs">Include lowercase letters</li>
                                        <li id="number-check-tooltip" class="requirement unmet text-xs">Include numbers</li>
                                        <li id="special-check-tooltip" class="requirement unmet text-xs">Include special characters (e.g., ! @ # ?)</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="password-strength-meter">
                                <div id="strength-bar" class="strength-weak" style="width: 0%"></div>
                            </div>
                            <span id="strength-text" class="text-xs text-gray-500 block mt-1"></span>
                            <div class="inline-error-message" id="new_password-error">
                                <?php echo $errors['new_password'] ?? ''; ?>
                            </div>
                        </div>

                        <!-- Confirm Password Field -->
                        <div class="mb-4">
                            <label class="modern-label">Confirm New Password <span class="required-star">*</span></label>
                            <div class="modern-input">
                                <input 
                                    type="password" 
                                    name="confirm_password" 
                                    id="confirm_password" 
                                    required 
                                    placeholder="Confirm your new password"
                                    autocomplete="new-password"
                                >
                                <div class="input-icon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" tabindex="-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                            <span id="password-match" class="text-xs block mt-1"></span>
                            <div class="inline-error-message" id="confirm_password-error">
                                <?php echo $errors['confirm_password'] ?? ''; ?>
                            </div>
                        </div>

                        <!-- General Error Message -->
                        <?php if (isset($errors['general'])): ?>
                            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
                                <?php echo $errors['general']; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Change Password Button -->
                        <button type="submit" class="modern-btn">
                            <span>Change Password</span>
                        </button>
                    </form>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showAlert('success', '<?php echo addslashes($_SESSION['success_message']); ?>');
            });
        </script>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- External JavaScript - Removed signup.js to avoid terms validation -->
    
    <!-- Enhanced JavaScript for password change -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('changePasswordForm');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const currentPassword = document.getElementById('current_password');
            
            // Password strength calculation
            function calculatePasswordStrength(password) {
                let strength = 0;
                const checks = {
                    length: password.length >= 12,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^a-zA-Z0-9]/.test(password)
                };
                
                strength = Object.values(checks).filter(Boolean).length;
                return { strength, checks };
            }
            
            // Update password strength indicator
            function updatePasswordStrength() {
                const password = newPassword.value;
                const { strength, checks } = calculatePasswordStrength(password);
                const strengthBar = document.getElementById('strength-bar');
                const strengthText = document.getElementById('strength-text');
                
                // Update strength bar
                const percentage = (strength / 5) * 100;
                strengthBar.style.width = percentage + '%';
                
                // Update strength text and color
                if (strength < 2) {
                    strengthBar.className = 'strength-weak';
                    strengthText.textContent = 'Weak';
                    strengthText.className = 'text-xs block mt-1 text-red-500';
                } else if (strength < 4) {
                    strengthBar.className = 'strength-medium';
                    strengthText.textContent = 'Medium';
                    strengthText.className = 'text-xs block mt-1 text-yellow-500';
                } else {
                    strengthBar.className = 'strength-strong';
                    strengthText.textContent = 'Strong';
                    strengthText.className = 'text-xs block mt-1 text-green-500';
                }
                
                // Update requirement indicators
                updateRequirementIndicators(checks);
            }
            
            // Update requirement indicators
            function updateRequirementIndicators(checks) {
                const indicators = {
                    'length-check-tooltip': checks.length,
                    'uppercase-check-tooltip': checks.uppercase,
                    'lowercase-check-tooltip': checks.lowercase,
                    'number-check-tooltip': checks.number,
                    'special-check-tooltip': checks.special
                };
                
                Object.entries(indicators).forEach(([id, met]) => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.className = met ? 'requirement met text-xs text-green-600' : 'requirement unmet text-xs text-red-500';
                        element.innerHTML = met ? 
                            element.innerHTML.replace('•', '✓') : 
                            element.innerHTML.replace('✓', '•');
                    }
                });
            }
            
            // Real-time password matching
            function updatePasswordMatch() {
                const matchSpan = document.getElementById('password-match');
                if (confirmPassword.value && newPassword.value) {
                    if (confirmPassword.value === newPassword.value) {
                        matchSpan.textContent = '✓ Passwords match';
                        matchSpan.className = 'text-xs block mt-1 text-green-600';
                    } else {
                        matchSpan.textContent = '✗ Passwords do not match';
                        matchSpan.className = 'text-xs block mt-1 text-red-600';
                    }
                } else {
                    matchSpan.textContent = '';
                    matchSpan.className = 'text-xs block mt-1';
                }
            }
            
            // Event listeners
            newPassword.addEventListener('input', updatePasswordStrength);
            confirmPassword.addEventListener('input', updatePasswordMatch);
            
            // Form validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous errors
                document.querySelectorAll('.inline-error-message').forEach(el => {
                    el.textContent = '';
                });
                
                // Validate current password
                if (!currentPassword.value.trim()) {
                    document.getElementById('current_password-error').textContent = 'Current password is required.';
                    isValid = false;
                }
                
                // Validate new password
                if (!newPassword.value.trim()) {
                    document.getElementById('new_password-error').textContent = 'New password is required.';
                    isValid = false;
                } else {
                    const { checks } = calculatePasswordStrength(newPassword.value);
                    if (!checks.length) {
                        document.getElementById('new_password-error').textContent = 'Password must be at least 12 characters long.';
                        isValid = false;
                    } else if (!checks.uppercase) {
                        document.getElementById('new_password-error').textContent = 'Password must include at least one uppercase letter.';
                        isValid = false;
                    } else if (!checks.lowercase) {
                        document.getElementById('new_password-error').textContent = 'Password must include at least one lowercase letter.';
                        isValid = false;
                    } else if (!checks.number) {
                        document.getElementById('new_password-error').textContent = 'Password must include at least one number.';
                        isValid = false;
                    } else if (!checks.special) {
                        document.getElementById('new_password-error').textContent = 'Password must include at least one special character.';
                        isValid = false;
                    }
                }
                
                // Validate password confirmation
                if (!confirmPassword.value.trim()) {
                    document.getElementById('confirm_password-error').textContent = 'Please confirm your new password.';
                    isValid = false;
                } else if (newPassword.value !== confirmPassword.value) {
                    document.getElementById('confirm_password-error').textContent = 'Passwords do not match.';
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
            
            // Initialize
            updatePasswordStrength();
            updatePasswordMatch();
        });
        
        // Password tooltip functions
        function showPasswordTooltip() {
            const tooltip = document.getElementById('password-tooltip');
            if (tooltip) {
                tooltip.classList.remove('hidden');
            }
        }
        
        function hidePasswordTooltip() {
            const tooltip = document.getElementById('password-tooltip');
            if (tooltip) {
                tooltip.classList.add('hidden');
            }
        }
        
        // Password toggle function
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.parentNode.querySelector('.password-toggle');
            const icon = button.querySelector('svg');
            
            if (field.type === 'password') {
                field.type = 'text';
                // Change icon to hide password
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                `;
            } else {
                field.type = 'password';
                // Change icon to show password
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
    </script>
</body>
</html>
