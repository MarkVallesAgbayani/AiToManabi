<?php
session_start();
require_once '../config/database.php';

// Debug log
error_log("New Password Creation Page - Session variables: " . 
          "otp_user_id: " . ($_SESSION['otp_user_id'] ?? 'not set') . 
          ", reset_email: " . ($_SESSION['reset_email'] ?? 'not set') . 
          ", otp_type: " . ($_SESSION['otp_type'] ?? 'not set'));

// Check if user is authorized to reset password
if (!isset($_SESSION['otp_user_id']) || !isset($_SESSION['reset_email'])) {
    error_log("New Password Creation Page - Missing session variables, redirecting to forgot-password.php");
    header('Location: ../forgetpassword/forgot-password.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must include at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must include at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must include at least one number.';
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $error = 'Password must include at least one special character.';
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['otp_user_id']]);
            
            // Clear session variables
            unset($_SESSION['otp_user_id']);
            unset($_SESSION['otp_type']);
            unset($_SESSION['reset_email']);
            
            $_SESSION['success'] = 'Password has been reset successfully. You can now log in with your new password.';
            header('Location: ../dashboard/login.php');
            exit();
        } catch (PDOException $e) {
            error_log("Password reset failed: " . $e->getMessage());
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Password - Japanese Learning Platform</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../dashboard/css/validation_signup.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../dashboard/css/signup_card.css?v=<?php echo time(); ?>">
    
    <!-- Additional CSS for password strength indicators -->
    <style>
        .font-jp { font-family: 'Noto Sans JP', sans-serif; }
        
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
                        <h1 class="modern-title">Create New Password</h1>
                        <p class="modern-subtitle">Please set a secure password for your account</p>
                    </div>
        
                    <!-- Error/Success Messages -->
                    <?php if ($error): ?>
                        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
        
                    <!-- Form -->
                    <form method="POST" id="newPasswordForm">
                        <!-- New Password Field -->
                        <div class="mb-3">
                            <label class="modern-label">New Password <span class="required-star">*</span></label>
                            <div class="modern-input">
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="password" 
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
                                <button type="button" class="password-toggle" onclick="togglePassword('password')" tabindex="-1">
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
                            <div class="inline-error-message" id="password-error"></div>
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
                            <div class="inline-error-message" id="confirm_password-error"></div>
                        </div>

                        <!-- Reset Password Button -->
                        <button type="submit" class="modern-btn">
                            <span>Reset Password</span>
                        </button>
                    </form>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Enhanced JavaScript for password creation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('newPasswordForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
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
                const passwordValue = password.value;
                const { strength, checks } = calculatePasswordStrength(passwordValue);
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
                if (confirmPassword.value && password.value) {
                    if (confirmPassword.value === password.value) {
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
            password.addEventListener('input', updatePasswordStrength);
            confirmPassword.addEventListener('input', updatePasswordMatch);
            
            // Form validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous errors
                document.querySelectorAll('.inline-error-message').forEach(el => {
                    el.textContent = '';
                });
                
                // Validate password
                if (!password.value.trim()) {
                    document.getElementById('password-error').textContent = 'Password is required.';
                    isValid = false;
                } else {
                    const { checks } = calculatePasswordStrength(password.value);
                    if (!checks.length) {
                        document.getElementById('password-error').textContent = 'Password must be at least 12 characters long.';
                        isValid = false;
                    } else if (!checks.uppercase) {
                        document.getElementById('password-error').textContent = 'Password must include at least one uppercase letter.';
                        isValid = false;
                    } else if (!checks.lowercase) {
                        document.getElementById('password-error').textContent = 'Password must include at least one lowercase letter.';
                        isValid = false;
                    } else if (!checks.number) {
                        document.getElementById('password-error').textContent = 'Password must include at least one number.';
                        isValid = false;
                    } else if (!checks.special) {
                        document.getElementById('password-error').textContent = 'Password must include at least one special character.';
                        isValid = false;
                    }
                }
                
                // Validate password confirmation
                if (!confirmPassword.value.trim()) {
                    document.getElementById('confirm_password-error').textContent = 'Please confirm your password.';
                    isValid = false;
                } else if (password.value !== confirmPassword.value) {
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
    
    <!-- Initialize particles.js -->
    <script>
        // Initialize particles.js
        if (typeof particlesJS !== 'undefined') {
            particlesJS('particles-js', {
                particles: {
                    number: { value: 80 },
                    color: { value: '#ef4444' },
                    shape: { type: 'circle' },
                    opacity: { value: 0.5, random: true },
                    size: { value: 3, random: true },
                    line_linked: { enable: true, distance: 150, color: '#ef4444', opacity: 0.4, width: 1 },
                    move: { enable: true, speed: 2, direction: 'none', random: false, straight: false, out_mode: 'out', bounce: false }
                },
                interactivity: {
                    detect_on: 'canvas',
                    events: { onhover: { enable: true, mode: 'repulse' }, onclick: { enable: true, mode: 'push' }, resize: true },
                    modes: { grab: { distance: 400, line_linked: { opacity: 1 } }, bubble: { distance: 400, size: 40, duration: 2, opacity: 8, speed: 3 }, repulse: { distance: 200, duration: 0.4 }, push: { particles_nb: 4 }, remove: { particles_nb: 2 } }
                },
                retina_detect: true
            });
        }
    </script>
</body>
</html>
