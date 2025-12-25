<?php
session_start();

// Debug: Log that auth.php is being accessed
error_log("AUTH.PHP ACCESSED - Method: " . $_SERVER['REQUEST_METHOD'] . ", Action: " . ($_POST['action'] ?? 'none'));
require_once __DIR__ . '/../config/database.php';
require_once 'otp_handler.php';

$otpHandler = new OTPHandler($pdo);

// Helper function to set modern alerts
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

// Helper function to set inline validation errors
function setInlineErrors($errors) {
    $_SESSION['inline_errors'] = $errors;
}

// Phone number validation functions
function getPhoneValidationRules($countryCode) {
    $rules = [
        '+63' => [ // Philippines
            'patterns' => [
                '/^09\d{9}$/',      // Local format: 09XXXXXXXXX
                '/^\+639\d{9}$/',   // International format: +639XXXXXXXXX
                '/^9\d{9}$/'        // Without leading 0: 9XXXXXXXXX
            ],
            'minLength' => 10,
            'maxLength' => 13,
            'description' => 'Philippines format (09XX XXX XXXX or +63 9XX XXX XXXX)'
        ],
        '+1' => [ // USA/Canada
            'patterns' => [
                '/^\d{10}$/',       // 1234567890
                '/^\+1\d{10}$/',    // +11234567890
                '/^1\d{10}$/'       // 11234567890
            ],
            'minLength' => 10,
            'maxLength' => 12,
            'description' => 'US/Canada format ((XXX) XXX-XXXX or +1 XXX XXX XXXX)'
        ],
        '+44' => [ // UK
            'patterns' => [
                '/^0\d{10}$/',      // 01234567890
                '/^\+44\d{10}$/',   // +441234567890
                '/^7\d{9}$/'        // Mobile: 7XXXXXXXXX
            ],
            'minLength' => 10,
            'maxLength' => 13,
            'description' => 'UK format (0XXXX XXXXXX or +44 XXXX XXXXXX)'
        ],
        '+81' => [ // Japan
            'patterns' => [
                '/^0\d{9,10}$/',    // 090-1234-5678 or 03-1234-5678
                '/^\+81\d{9,10}$/'  // +81901234567
            ],
            'minLength' => 10,
            'maxLength' => 13,
            'description' => 'Japan format (090-XXXX-XXXX or +81 90 XXXX XXXX)'
        ],
        '+65' => [ // Singapore
            'patterns' => [
                '/^[89]\d{7}$/',    // 81234567 or 91234567
                '/^\+65[89]\d{7}$/' // +6581234567
            ],
            'minLength' => 8,
            'maxLength' => 11,
            'description' => 'Singapore format (8XXX XXXX or +65 8XXX XXXX)'
        ],
        '+91' => [ // India
            'patterns' => [
                '/^[6-9]\d{9}$/',   // 9876543210
                '/^\+91[6-9]\d{9}$/' // +919876543210
            ],
            'minLength' => 10,
            'maxLength' => 13,
            'description' => 'India format (9XXXX XXXXX or +91 9XXXX XXXXX)'
        ]
    ];
    
    // Default rule for other countries
    return $rules[$countryCode] ?? [
        'patterns' => ['/^\d{7,15}$/'],
        'minLength' => 7,
        'maxLength' => 15,
        'description' => 'Valid phone number'
    ];
}

function validatePhoneNumber($phoneNumber, $countryCode) {
    // Remove all spaces and formatting
    $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
    
    if (empty($cleaned)) {
        return ['valid' => false, 'message' => 'Contact number is required.'];
    }
    
    $rules = getPhoneValidationRules($countryCode);
    
    // Check length
    if (strlen($cleaned) < $rules['minLength'] || strlen($cleaned) > $rules['maxLength']) {
        return [
            'valid' => false, 
            'message' => "Phone number length is invalid for {$rules['description']}."
        ];
    }
    
    // Check patterns
    $isValid = false;
    foreach ($rules['patterns'] as $pattern) {
        if (preg_match($pattern, $cleaned)) {
            $isValid = true;
            break;
        }
    }
    
    if (!$isValid) {
        return [
            'valid' => false, 
            'message' => "Please enter a valid phone number. Expected: {$rules['description']}"
        ];
    }
    
    return ['valid' => true, 'cleaned' => $cleaned];
}

function normalizePhoneNumber($phoneNumber, $countryCode) {
    $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
    
    // Normalize to international format
    if ($countryCode === '+63') {
        // Philippines
        if (preg_match('/^09\d{9}$/', $cleaned)) {
            return '+63' . substr($cleaned, 1); // Remove leading 0, add +63
        } elseif (preg_match('/^9\d{9}$/', $cleaned)) {
            return '+63' . $cleaned; // Add +63
        } elseif (preg_match('/^\+639\d{9}$/', $cleaned)) {
            return $cleaned; // Already in international format
        }
    }
    
    // For other countries, ensure country code is included
    if (!str_starts_with($cleaned, $countryCode)) {
        return $countryCode . ltrim($cleaned, '+0');
    }
    
    return $cleaned;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("AUTH.PHP: POST request received");
    error_log("AUTH.PHP: POST data: " . print_r($_POST, true));
    if (isset($_POST['action'])) {
        error_log("AUTH.PHP: Action found: " . $_POST['action']);
        if ($_POST['action'] === 'signup') {
            error_log("AUTH.PHP: Starting signup process");
            
            // DEBUG: Log all received data
            error_log("AUTH.PHP DEBUG: Terms value = " . ($_POST['terms'] ?? 'NOT_SET'));
            error_log("AUTH.PHP DEBUG: Email = " . ($_POST['email'] ?? 'NOT_SET'));
            error_log("AUTH.PHP DEBUG: Phone = " . ($_POST['phone_number'] ?? 'NOT_SET'));
            error_log("AUTH.PHP DEBUG: Password length = " . strlen($_POST['password'] ?? ''));
            
            // Check if terms are accepted
            if (!isset($_POST['terms']) || $_POST['terms'] !== '1') {
                error_log("Terms not accepted - terms value: " . ($_POST['terms'] ?? 'NOT_SET'));
                setAlert('warning', 'Your acceptance of the Terms of Service is required to continue');
                
                // Preserve form data in session for form repopulation
                $_SESSION['form_data'] = [
                    'first_name' => $_POST['first_name'] ?? '',
                    'last_name' => $_POST['last_name'] ?? '',
                    'middle_name' => $_POST['middle_name'] ?? '',
                    'username' => $_POST['username'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'phone_number' => $_POST['phone_number'] ?? '',
                    'country_code' => $_POST['country_code'] ?? '+63'
                ];
                
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            error_log("Terms accepted, proceeding with validation");

            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // New: Capture and sanitize name fields
            $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
            $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
            $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
            
            // Capture and sanitize phone number fields
            $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
            $country_code = isset($_POST['country_code']) ? trim($_POST['country_code']) : '+63';

            // Enhanced name validation
            if ($first_name === '' || $last_name === '') {
                setInlineErrors([
                    'first_name' => $first_name === '' ? 'First Name is required.' : '',
                    'last_name' => $last_name === '' ? 'Last Name is required.' : ''
                ]);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            
            // Validate name format (only letters, spaces, hyphens, apostrophes)
            $namePattern = '/^[a-zA-ZÀ-ÿ\s\'-]{1,50}$/u';
            $nameErrors = [];
            
            if (!preg_match($namePattern, $first_name) || preg_match('/[\s\'-]{2,}/', $first_name) || preg_match('/^[\s\'-]|[\s\'-]$/', $first_name)) {
                $nameErrors['first_name'] = 'Only letters, spaces, hyphens allowed.';
            }
            
            if (!preg_match($namePattern, $last_name) || preg_match('/[\s\'-]{2,}/', $last_name) || preg_match('/^[\s\'-]|[\s\'-]$/', $last_name)) {
                $nameErrors['last_name'] = 'Only letters, spaces, hyphens allowed.';
            }
            
            if ($middle_name && (!preg_match($namePattern, $middle_name) || preg_match('/[\s\'-]{2,}/', $middle_name) || preg_match('/^[\s\'-]|[\s\'-]$/', $middle_name))) {
                $nameErrors['middle_name'] = 'Only letters, spaces, hyphens allowed.';
            }
            
            if (!empty($nameErrors)) {
                setInlineErrors($nameErrors);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            
	            // Enforce name length between 3 and 50 characters
	            $nameLengthErrors = [];
	            if (strlen($first_name) < 3) {
	                $nameLengthErrors['first_name'] = 'First name must be at least 3 characters.';
	            } elseif (strlen($first_name) > 50) {
	                $nameLengthErrors['first_name'] = 'First name cannot exceed 50 characters.';
	            }
	            if (strlen($last_name) < 3) {
	                $nameLengthErrors['last_name'] = 'Last name must be at least 3 characters.';
	            } elseif (strlen($last_name) > 50) {
	                $nameLengthErrors['last_name'] = 'Last name cannot exceed 50 characters.';
	            }
	            if ($middle_name !== null && $middle_name !== '') {
	                if (strlen($middle_name) < 3) {
	                    $nameLengthErrors['middle_name'] = 'Middle name must be at least 3 characters.';
	                } elseif (strlen($middle_name) > 50) {
	                    $nameLengthErrors['middle_name'] = 'Middle name cannot exceed 50 characters.';
	                }
	            }
	            if (!empty($nameLengthErrors)) {
	                setInlineErrors($nameLengthErrors);
	                header("Location: /AIToManabi_Updated/dashboard/signup.php");
	                exit();
	            }

            // Validate password match
            if ($password !== $confirm_password) {
                error_log("Passwords do not match");
                setAlert('error', 'Passwords do not match.');
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            error_log("Password validation passed");

            // Validate password strength
            if (strlen($password) < 12) {
                setAlert('error', 'Password must be at least 12 characters long.');
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }

            if (!preg_match('/[A-Z]/', $password)) {
                setAlert('error', 'Password must include at least one uppercase letter.');
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }

            if (!preg_match('/[a-z]/', $password)) {
                setAlert('error', 'Password must include at least one lowercase letter.');
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }

            if (!preg_match('/[0-9]/', $password)) {
                setAlert('error', 'Password must include at least one number.');
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }

            if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
                error_log("AUTH.PHP DEBUG: Password failed special character check: '$password'");
                setAlert('error', 'Password must include at least one special character.');
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            
            error_log("AUTH.PHP DEBUG: All password validations passed for: '$password'");

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Enhanced email validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_log("Email validation failed: $email");
                setInlineErrors(['email' => 'Please enter a valid email address.']);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            error_log("Email validation passed: $email");
            
            // Phone number validation
            if (empty($phone_number)) {
                error_log("Phone number validation failed: empty");
                setInlineErrors(['phone_number' => 'Contact number is required.']);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            
            error_log("AUTH.PHP DEBUG: About to validate phone: '$phone_number' with country: '$country_code'");
            $phoneValidation = validatePhoneNumber($phone_number, $country_code);
            error_log("AUTH.PHP DEBUG: Phone validation result: " . json_encode($phoneValidation));
            if (!$phoneValidation['valid']) {
                error_log("Phone number validation failed: " . $phoneValidation['message']);
                setInlineErrors(['phone_number' => $phoneValidation['message']]);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            
            // Normalize phone number for storage
            $normalized_phone = normalizePhoneNumber($phone_number, $country_code);
            error_log("Phone number validation passed: $normalized_phone");
            
            // Additional email validation checks
            if (strlen($email) > 254) {
                setInlineErrors(['email' => 'Email address is too long.']);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
            
            $emailParts = explode('@', $email);
            if (count($emailParts) === 2) {
                $localPart = $emailParts[0];
                $domainPart = $emailParts[1];
                
                if (strlen($localPart) > 64) {
                    setInlineErrors(['email' => 'Email address format is invalid.']);
                    header("Location: /AIToManabi_Updated/dashboard/signup.php");
                    exit();
                }
                
                // Check for valid TLD
                if (strpos($domainPart, '.') !== false) {
                    $domainParts = explode('.', $domainPart);
                    $tld = end($domainParts);
                    if (strlen($tld) < 2 || strlen($tld) > 10) {
                        setInlineErrors(['email' => 'Please enter a valid email address.']);
                        header("Location: /AIToManabi_Updated/dashboard/signup.php");
                        exit();
                    }
                }
                
                // Check for consecutive dots
                if (strpos($email, '..') !== false) {
                    setInlineErrors(['email' => 'Please enter a valid email address.']);
                    header("Location: /AIToManabi_Updated/dashboard/signup.php");
                    exit();
                }

	                // Allow only real/approved email providers
	                $allowedDomains = [
	                	'gmail.com',
	                	'outlook.com',
	                	'hotmail.com',
	                	'live.com',
	                	'icloud.com',
	                	'proton.me',
	                	'protonmail.com',
	                ];
	                $emailDomain = strtolower($domainPart);
	                if (!in_array($emailDomain, $allowedDomains, true) && !preg_match('/\.edu\.ph$/i', $emailDomain)) {
	                	setInlineErrors(['email' => 'Please use an email from approved providers (e.g., gmail.com, outlook.com).']);
	                	header("Location: /AIToManabi_Updated/dashboard/signup.php");
	                	exit();
	                }
            }

            // Enhanced username validation
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                setInlineErrors(['username' => 'Username: 3-20 chars, letters/numbers only.']);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }

            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                setInlineErrors(['username' => 'That username is taken. Please choose another.']);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                setInlineErrors(['email' => 'This email is already registered.']);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }

            // Enhanced phone number validation - check for existing phone numbers including deleted/banned accounts
            $stmt = $pdo->prepare("SELECT id, status, deleted_at FROM users WHERE phone_number = ?");
            $stmt->execute([$normalized_phone]);
            if ($stmt->rowCount() > 0) {
                $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Prevent registration regardless of account status (active, banned, deleted, etc.)
                error_log("Phone number validation failed: Phone already exists for user ID " . $existingUser['id'] . " with status '" . $existingUser['status'] . "'");
                setInlineErrors(['phone_number' => 'This phone number is already registered.']);
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }

            try {
                // Start transaction
                $pdo->beginTransaction();

                // Insert new user with student role, name fields, and phone number
                // Set is_first_login = FALSE for self-registered users (they chose their own password)
                error_log("About to create user account for: $username");
                error_log("Registration data - Username: $username, Email: $email, Phone: $normalized_phone, First: $first_name, Last: $last_name, Middle: $middle_name");
                $stmt = $pdo->prepare("INSERT INTO users (username, email, phone_number, password, role, email_verified, first_name, last_name, middle_name, is_first_login) VALUES (?, ?, ?, ?, 'student', FALSE, ?, ?, ?, FALSE)");
                if (!$stmt->execute([$username, $email, $normalized_phone, $hashedPassword, $first_name, $last_name, $middle_name])) {
                    error_log("Failed to create user account - SQL error: " . implode(', ', $stmt->errorInfo()));
                    throw new Exception("Failed to create user account.");
                }
                $userId = $pdo->lastInsertId();
                error_log("User account created successfully with ID: $userId");

                // Commit transaction immediately after user creation for fast response
                $pdo->commit();
                
                // Set session variables for OTP verification
                $_SESSION['otp_user_id'] = $userId;
                $_SESSION['otp_type'] = 'registration';
                
                // Debug: Log session variables
                error_log("Session variables set - otp_user_id: " . $_SESSION['otp_user_id'] . ", otp_type: " . $_SESSION['otp_type']);

                // Generate and send OTP in background (non-blocking)
                try {
                    error_log("About to generate OTP for user ID: $userId, email: $email");
                    $otp = $otpHandler->generateOTPCode(); // Generate OTP code only
                    error_log("OTP generated successfully, about to send email");
                    $otpHandler->sendOTPWithUserId($email, $otp, 'registration', $userId);
                    error_log("OTP email sent successfully");
                } catch (Exception $emailError) {
                    // Log email error but don't fail the registration
                    error_log("Email sending error (non-critical): " . $emailError->getMessage());
                    // User can still verify later or request new OTP
                }

                // Debug: Log before redirect
                error_log("About to redirect to verify_otp.php");
                
                // Ensure no output has been sent
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                exit();

            } catch (Exception $e) {
                // Rollback transaction on error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Registration error: " . $e->getMessage());
                setAlert('error', $e->getMessage());
                header("Location: /AIToManabi_Updated/dashboard/signup.php");
                exit();
            }
        }
        
        if ($_POST['action'] === 'login') {
            $email_or_username = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            // Comprehensive input validation
            $errors = [];
            
            // Check for empty fields
            if (empty($email_or_username)) {
                $errors['email'] = 'Please enter your email or username.';
            } elseif (strlen($email_or_username) < 3) {
                $errors['email'] = 'Email or username must be at least 3 characters long.';
            } elseif (strlen($email_or_username) > 100) {
                $errors['email'] = 'Email or username is too long.';
            }
            
            if (empty($password)) {
                $errors['password'] = 'Please enter your password.';
            }
            
            // If there are validation errors, return them
            if (!empty($errors)) {
                setInlineErrors($errors);
                header("Location: /AIToManabi_Updated/dashboard/login.php");
                exit();
            }

            // Basic rate limiting - check for too many attempts
            $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $attempt_key = 'login_attempts_' . hash('sha256', $client_ip . $email_or_username);
            
            // Check if there are too many recent attempts
            if (!isset($_SESSION[$attempt_key])) {
                $_SESSION[$attempt_key] = ['count' => 0, 'last_attempt' => 0];
            }
            
            $current_time = time();
            $attempts = $_SESSION[$attempt_key];
            
            // Reset attempts after 15 minutes
            if ($current_time - $attempts['last_attempt'] > 900) {
                $_SESSION[$attempt_key] = ['count' => 0, 'last_attempt' => $current_time];
                $attempts = $_SESSION[$attempt_key];
            }
            
            // Block if too many attempts (5 attempts in 15 minutes)
            if ($attempts['count'] >= 5) {
                $remaining_time = 15 - floor(($current_time - $attempts['last_attempt']) / 60);
                setInlineErrors(['email' => "Too many login attempts. Please try again in {$remaining_time} minutes."]);
                header("Location: /AIToManabi_Updated/dashboard/login.php");
                exit();
            }

            // Check if input is email or username and query accordingly
            if (filter_var($email_or_username, FILTER_VALIDATE_EMAIL)) {
                // Input is an email
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            } else {
                // Input is a username
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            }
            $stmt->execute([$email_or_username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Reset failed attempts on successful login
                unset($_SESSION[$attempt_key]);
                
                // Check if user is deleted
                if ($user['deleted_at'] !== null) {
                    // Set deletion modal data in session
                    $_SESSION['deletion_modal'] = [
                        'show' => true,
                        'reason' => !empty($user['deletion_reason']) ? htmlspecialchars($user['deletion_reason']) : 'No specific reason provided',
                        'deleted_at' => !empty($user['deleted_at']) ? $user['deleted_at'] : 'Unknown',
                        'restoration_deadline' => !empty($user['restoration_deadline']) ? $user['restoration_deadline'] : null,
                        'timestamp' => time()
                    ];
                    
                    header("Location: /AIToManabi_Updated/dashboard/login.php");
                    exit();
                }
                
                // Check if user is banned
                if ($user['status'] === 'banned') {
                    // Set ban modal data in session
                    $_SESSION['ban_modal'] = [
                        'show' => true,
                        'reason' => !empty($user['ban_reason']) ? htmlspecialchars($user['ban_reason']) : 'No specific reason provided',
                        'banned_at' => !empty($user['banned_at']) ? $user['banned_at'] : 'Unknown',
                        'timestamp' => time()
                    ];
                    
                    header("Location: /AIToManabi_Updated/dashboard/login.php");
                    exit();
                }
                
                // Check verification requirements based on role
                if (in_array($user['role'], ['admin', 'teacher'])) {
                    // Admin and Teacher need BOTH email AND phone verification
                    if (!$otpHandler->isEmailVerified($user['id'])) {
                        // Start email verification process
                        $otp = $otpHandler->generateOTP($user['id'], $user['email'], 'registration');
                        if ($otpHandler->sendOTP($user['email'], $otp, 'registration')) {
                            $_SESSION['otp_user_id'] = $user['id'];
                            $_SESSION['otp_type'] = 'registration';
                            setAlert('info', 'Email verification required. Please check your email for the verification code.');
                            header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                        } else {
                            setAlert('error', 'Failed to send email verification code. Please try again.');
                            header("Location: /AIToManabi_Updated/dashboard/login.php");
                        }
                        exit();
                    }
                    
                    // Check if phone number exists
                    if (empty($user['phone_number'])) {
                        // Redirect to add phone number
                        $_SESSION['add_phone_required'] = true;
                        $_SESSION['user_id_temp'] = $user['id'];
                        setAlert('warning', 'Phone number required. Please add your phone number to complete account setup.');
                        header("Location: /AIToManabi_Updated/dashboard/add_phone_number.php");
                        exit();
                    }
                    
                    // Check if phone is verified
                    if (!$user['phone_verified']) {
                        // Start phone verification process
                        require_once __DIR__ . '/../services/PhilSMSService.php';
                        $philSMS = new PhilSMSService();
                        
                        try {
                            // Generate phone OTP
                            $phone_otp = sprintf('%06d', random_int(0, 999999));
                            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                            
                            // Store phone OTP in database
                            $stmt = $pdo->prepare("
                                INSERT INTO otps (user_id, phone_number, otp_code, type, expires_at) 
                                VALUES (?, ?, ?, 'sms_registration', ?)
                                ON DUPLICATE KEY UPDATE otp_code = VALUES(otp_code), expires_at = VALUES(expires_at)
                            ");
                            $stmt->execute([$user['id'], $user['phone_number'], $phone_otp, $expires_at]);
                            
                            // Send SMS
                            $sms_result = $philSMS->sendOTP($user['phone_number'], $phone_otp, 'registration');
                            
                            if ($sms_result['success']) {
                                $_SESSION['otp_user_id'] = $user['id'];
                                $_SESSION['otp_type'] = 'sms_registration';
                                setAlert('info', 'Phone verification required. Please check your SMS for the verification code.');
                                header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                            } else {
                                setAlert('error', 'Failed to send SMS verification code. Please try again or contact support.');
                                header("Location: /AIToManabi_Updated/dashboard/login.php");
                            }
                        } catch (Exception $e) {
                            error_log("Phone verification error: " . $e->getMessage());
                            setAlert('error', 'Phone verification unavailable. Please contact support.');
                            header("Location: /AIToManabi_Updated/dashboard/login.php");
                        }
                        exit();
                    }
                } else {
                    // Students only need email verification
                    if (!$otpHandler->isEmailVerified($user['id'])) {
                        setInlineErrors(['email' => 'Please verify your email before logging in. Check your inbox for the verification link.']);
                        header("Location: /AIToManabi_Updated/dashboard/login.php");
                        exit();
                    }
                }

                // Update last_login_at and login_time
                $stmtUpdate = $pdo->prepare("
                    UPDATE users 
                    SET last_login_at = NOW(),
                        login_time = NOW()
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$user['id']]);
                error_log('Updated login_time for user ID: ' . $user['id']);
                
                // Log successful login with enhanced logger
                require_once '../dashboard/enhanced_login_logger.php';
                $loginLogger = new EnhancedLoginLogger($pdo);
                $loginLogger->logSuccessfulLogin($user['id']);

                // Log activity for analytics
                try {
                    require_once '../dashboard/real_time_activity_logger.php';
                    $logger = new RealTimeActivityLogger($pdo);
                    $logger->logActivity($user['id'], 'login', 'authentication', 'login_success', [
                        'login_method' => 'password',
                        'user_role' => $user['role']
                    ]);
                } catch (Exception $e) {
                    error_log("Activity logging error: " . $e->getMessage());
                }

                // Set basic session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Check if admin/teacher needs to change password (first time login)
                if (in_array($user['role'], ['admin', 'teacher']) && $user['is_first_login']) {
                    header("Location: /AIToManabi_Updated/dashboard/change_password.php");
                    exit();
                }

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

                // Handle different roles - OTP only for full admin/teacher, not hybrid accounts
                switch($user['role']) {
                    case 'admin':
                        // ALWAYS treat admin as full admin - no hybrid detection
                        // Full admin - require OTP verification
                        $otp = $otpHandler->generateOTP($user['id'], $user['email'], 'login');
                        if ($otpHandler->sendOTP($user['email'], $otp, 'login')) {
                            $_SESSION['otp_user_id'] = $user['id'];
                            $_SESSION['otp_type'] = 'login';
                            header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                        } else {
                            setAlert('error', 'Failed to send verification code. Please try again.');
                            header("Location: /AIToManabi_Updated/dashboard/login.php");
                        }
                        break;
                        
                    case 'teacher':
                        if (isHybridAccount($pdo, $user['id'], 'teacher')) {
                            // Hybrid teacher - direct access
                            $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
                            $stmt->execute([$user['id']]);
                            $admin_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            $_SESSION['is_hybrid'] = true;
                            $_SESSION['permissions'] = $admin_permissions;
                            header("Location: /AIToManabi_Updated/dashboard/hybrid_teacher.php");
                        } else {
                            // Full teacher - require OTP verification
                            $otp = $otpHandler->generateOTP($user['id'], $user['email'], 'login');
                            if ($otpHandler->sendOTP($user['email'], $otp, 'login')) {
                                $_SESSION['otp_user_id'] = $user['id'];
                                $_SESSION['otp_type'] = 'login';
                                header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                            } else {
                                setAlert('error', 'Failed to send verification code. Please try again.');
                                header("Location: /AIToManabi_Updated/dashboard/login.php");
                            }
                        }
                        break;
                        
                    case 'student':
                        // Students always require OTP verification
                        $otp = $otpHandler->generateOTP($user['id'], $user['email'], 'login');
                        if ($otpHandler->sendOTP($user['email'], $otp, 'login')) {
                            $_SESSION['otp_user_id'] = $user['id'];
                            $_SESSION['otp_type'] = 'login';
                            header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                        } else {
                            setAlert('error', 'Failed to send verification code. Please try again.');
                            header("Location: /AIToManabi_Updated/dashboard/login.php");
                        }
                        break;

                    default:
                        setAlert('error', 'Invalid user role. Please contact support.');
                        header("Location: /AIToManabi_Updated/dashboard/login.php");
                }
                exit();
            } else {
                // Increment failed login attempts
                $_SESSION[$attempt_key]['count']++;
                $_SESSION[$attempt_key]['last_attempt'] = $current_time;
                
                // Log failed login attempt with enhanced logger
                require_once '../dashboard/enhanced_login_logger.php';
                $loginLogger = new EnhancedLoginLogger($pdo);
                $loginLogger->logFailedLogin($email_or_username, 'invalid_credentials');
                
                // Add progressive delay to slow down brute force attacks
                $delay = min($_SESSION[$attempt_key]['count'], 5); // Max 5 seconds delay
                sleep($delay);
                
                // Generic error message for security - don't reveal if user exists or not
                $remaining_attempts = 5 - $_SESSION[$attempt_key]['count'];
                if ($remaining_attempts <= 2) {
                    setInlineErrors(['password' => "Incorrect password. You have {$remaining_attempts} attempts remaining."]);
                } else {
                    setInlineErrors(['password' => 'Incorrect email/username or password. Please double-check your credentials.']);
                }
                header("Location: /AIToManabi_Updated/dashboard/login.php");
                exit();
            }
        }

        if ($_POST['action'] === 'forgot_password') {
            $email = $_POST['email'] ?? '';

            if (empty($email)) {
                $_SESSION['error'] = 'Email is required.';
                header('Location: /AIToManabi_Updated/forgetpassword/forgot-password.php');
                exit();
            }

            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate and send OTP
                $otp = $otpHandler->generateOTP($user['id'], $email, 'password_reset');
                if ($otpHandler->sendOTP($email, $otp, 'password_reset')) {
                    $_SESSION['otp_user_id'] = $user['id'];
                    $_SESSION['otp_type'] = 'password_reset';
                    $_SESSION['reset_email'] = $email;
                    header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                } else {
                    $_SESSION['error'] = 'Failed to send verification code.';
                    header('Location: /AIToManabi_Updated/forgetpassword/forgot-password.php');
                }
            } else {
                $_SESSION['error'] = 'Email not found.';
                header('Location: /AIToManabi_Updated/forgetpassword/forgot-password.php');
            }
            exit();
        }
    }
}

// If no action or not POST request, redirect to index
header("Location: /AIToManabi_Updated/index.php");
exit();
?> 