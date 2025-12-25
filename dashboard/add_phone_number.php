<?php
session_start();
require_once '../config/database.php';

// Check if user needs to add phone number
if (!isset($_SESSION['add_phone_required']) || !isset($_SESSION['user_id_temp'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id_temp'];

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'teacher'])) {
    header("Location: login.php");
    exit();
}

// Phone number validation functions (same as auth.php)
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
        ]
    ];
    
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number = trim($_POST['phone_number']);
    $country_code = '+63'; // Philippines only
    
    if (empty($phone_number)) {
        $error = 'Phone number is required.';
    } else {
        // Use the same validation as auth.php
        $phoneValidation = validatePhoneNumber($phone_number, $country_code);
        
        if (!$phoneValidation['valid']) {
            $error = $phoneValidation['message'];
        } else {
            // Normalize phone number for storage
            $normalized_phone = normalizePhoneNumber($phone_number, $country_code);
            
            // Check if phone already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ? AND id != ?");
            $stmt->execute([$normalized_phone, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'This phone number is already registered to another account.';
            } else {
                try {
                    // Update user with phone number
                    $stmt = $pdo->prepare("UPDATE users SET phone_number = ? WHERE id = ?");
                    $stmt->execute([$normalized_phone, $user_id]);
                    
                    // Start phone verification process
                    require_once __DIR__ . '/../services/PhilSMSService.php';
                    $philSMS = new PhilSMSService();
                    
                    // Generate phone OTP
                    $phone_otp = sprintf('%06d', random_int(0, 999999));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                    
                    // Store phone OTP in database
                    $stmt = $pdo->prepare("
                        INSERT INTO otps (user_id, phone_number, otp_code, type, expires_at) 
                        VALUES (?, ?, ?, 'sms_registration', ?)
                    ");
                    $stmt->execute([$user_id, $normalized_phone, $phone_otp, $expires_at]);
                    
                    // Send SMS
                    $sms_result = $philSMS->sendOTP($normalized_phone, $phone_otp, 'registration');
                    
                    if ($sms_result['success']) {
                        $_SESSION['otp_user_id'] = $user_id;
                        $_SESSION['otp_type'] = 'sms_registration';
                        unset($_SESSION['add_phone_required']);
                        unset($_SESSION['user_id_temp']);
                        
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'message' => 'Phone number added! Please verify it with the SMS code sent to your phone.'
                        ];
                        header('Location: /AIToManabi_Updated/auth/verify_otp.php');
                        exit();
                    } else {
                        $error = 'Phone number added but SMS verification failed. Please contact support.';
                    }
                } catch (Exception $e) {
                    error_log("Add phone number error: " . $e->getMessage());
                    $error = 'An error occurred while adding your phone number. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Phone Number - AiToManabi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 font-jp min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full m-4">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Phone Number Required</h2>
            <p class="text-sm text-gray-600 mt-2">
                As an <?php echo ucfirst($user['role']); ?>, you need to add a phone number for account security.
            </p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4" id="phoneForm">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Contact Number <span class="text-red-500">*</span>
                </label>
                <div class="flex space-x-2">
                    <div class="flex items-center px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 text-sm">
                        🇵🇭 +63
                    </div>
                    <input type="tel" name="phone_number" id="phone_number" required 
                           pattern="^(09\d{9}|9\d{9}|\+639\d{9}|639\d{9})$"
                           maxlength="13"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="9XX XXX XXXX or 09XX XXX XXXX"
                           value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                           title="Enter a valid Philippine mobile number"
                           autocomplete="tel">
                </div>
                <div id="phone-error" class="mt-1 text-xs text-red-600 hidden"></div>
                <div class="mt-1 text-xs text-gray-500" id="phone-format-hint">
                    Format: 9XX XXX XXXX (without +63) or 09XX XXX XXXX
                </div>
            </div>
            
            <button type="submit" id="submitBtn"
                    class="w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                Add Phone Number & Verify
            </button>
            
            <div class="text-center">
                <a href="login.php" class="text-sm text-gray-600 hover:text-gray-800">
                    ← Back to Login
                </a>
            </div>
        </form>
        
        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-medium">Why do we need your phone number?</p>
                    <p class="text-blue-600 mt-1">
                        For enhanced security, admin and teacher accounts require both email and phone verification. 
                        Your phone number will be used for account verification and security alerts.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Phone number validation (same as signup.js)
        function getPhoneValidationRules() {
            return {
                formats: [
                    /^09\d{9}$/,      // 09XXXXXXXXX
                    /^\+639\d{9}$/,   // +639XXXXXXXXX  
                    /^9\d{9}$/,       // 9XXXXXXXXX
                    /^639\d{9}$/      // 639XXXXXXXXX
                ],
                formatDisplay: '9XX XXX XXXX or 09XX XXX XXXX',
                maxLength: 13,
                minLength: 10
            };
        }

        function validatePhoneNumber(phoneNumber) {
            if (!phoneNumber || phoneNumber.trim() === '') {
                return { valid: false, message: 'Phone number is required.' };
            }
            
            const rules = getPhoneValidationRules();
            const cleaned = phoneNumber.replace(/[^\d+]/g, '');
            
            // Check if any format matches
            const isValid = rules.formats.some(format => format.test(cleaned));
            
            if (!isValid) {
                return {
                    valid: false,
                    message: 'Please enter a valid Philippine mobile number. Expected format: ' + rules.formatDisplay
                };
            }

            // Validate that it's a valid Philippine mobile prefix
            let numberToCheck = cleaned;
            if (numberToCheck.startsWith('+63')) {
                numberToCheck = numberToCheck.substring(3);
            } else if (numberToCheck.startsWith('63')) {
                numberToCheck = numberToCheck.substring(2);
            } else if (numberToCheck.startsWith('09')) {
                numberToCheck = numberToCheck.substring(1);
            }

            if (!numberToCheck.startsWith('9') || numberToCheck.length !== 10) {
                return {
                    valid: false,
                    message: 'Please enter a valid Philippine mobile number starting with 9.'
                };
            }

            return { valid: true, message: 'Valid phone number format' };
        }

        function showError(message) {
            const errorDiv = document.getElementById('phone-error');
            const phoneInput = document.getElementById('phone_number');
            
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
            phoneInput.classList.add('border-red-500');
            phoneInput.classList.remove('border-gray-300');
        }

        function hideError() {
            const errorDiv = document.getElementById('phone-error');
            const phoneInput = document.getElementById('phone_number');
            
            errorDiv.classList.add('hidden');
            phoneInput.classList.remove('border-red-500');
            phoneInput.classList.add('border-gray-300');
        }

        // Initialize form validation
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phone_number');
            const form = document.getElementById('phoneForm');
            const submitBtn = document.getElementById('submitBtn');

            // Real-time validation on input
            phoneInput.addEventListener('input', function() {
                const validation = validatePhoneNumber(this.value);
                
                if (this.value && !validation.valid) {
                    showError(validation.message);
                    submitBtn.disabled = true;
                } else {
                    hideError();
                    submitBtn.disabled = false;
                }
            });

            // Form submission validation
            form.addEventListener('submit', function(e) {
                const validation = validatePhoneNumber(phoneInput.value);
                
                if (!validation.valid) {
                    e.preventDefault();
                    showError(validation.message);
                    submitBtn.disabled = true;
                    return false;
                }
            });
        });
    </script>
</body>
</html>