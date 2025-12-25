<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class OTPHandler {
    private $pdo;
    private $mailer;

    public function __construct($pdo) {
        // Ensure timezone is set to Philippines
        date_default_timezone_set('Asia/Manila');
        
        $this->pdo = $pdo;
        $this->initializeMailer();
    }

    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Load configuration
            require_once __DIR__ . '/../config/app_config.php';
            
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->SMTPOptions = [
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
    ],
];
            
            // Default sender
            $this->mailer->setFrom(FROM_EMAIL, FROM_NAME);
            
            // Set default charset and encoding
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            
        } catch (Exception $e) {
            error_log("Mailer initialization error: " . $e->getMessage());
            throw new Exception("Failed to initialize email system");
        }
    }

    public function generateOTP($userId, $email, $type) {
        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiration time using database time to avoid timezone issues
        $stmt = $this->pdo->prepare("SELECT NOW() + INTERVAL 5 MINUTE as expires_at, NOW() as current_db_time");
        $stmt->execute();
        $timeResult = $stmt->fetch();
        $expiresAt = $timeResult['expires_at'];
        $currentTime = $timeResult['current_db_time'];
        
        // Debug logging
        error_log("OTP Generated - User: $userId, Type: $type, Current: $currentTime, Expires: $expiresAt");
        
        // Store OTP in database
        $stmt = $this->pdo->prepare("
            INSERT INTO otps (user_id, email, otp_code, type, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $email, $otp, $type, $expiresAt]);
        
        return $otp;
    }

    public function generateOTPCode() {
        // Generate 6-digit OTP code only (no database insertion)
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function sendOTP($email, $otp, $type) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);
            
            // Generate verification token for direct link
            $verification_token = bin2hex(random_bytes(32));
            
            // Set expiration time using Philippine time
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            
            // Store verification token
            $stmt = $this->pdo->prepare("
                INSERT INTO otps (user_id, email, otp_code, verification_token, type, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->getUserIdByEmail($email), $email, $otp, $verification_token, $type, $expires_at]);
            
            return $this->sendEmail($email, $otp, $type, $verification_token);
        } catch (Exception $e) {
            error_log("Failed to send OTP email: " . $e->getMessage());
            return false;
        }
    }

    public function sendOTPWithUserId($email, $otp, $type, $user_id) {
        try {
            // First, verify the user exists and is committed to the database
            $verifyStmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $verifyStmt->execute([$user_id]);
            if (!$verifyStmt->fetch()) {
                error_log("User ID $user_id does not exist when trying to insert OTP");
                return false;
            }
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);
            
            // Generate verification token for direct link
            $verification_token = bin2hex(random_bytes(32));
            
            // Fix the expiration time - ensure it's 5 minutes from NOW using strtotime for consistency
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes')); // 5 minutes from now
            
            // Debug logging
            error_log("Creating OTP record - User ID: $user_id, Email: $email, Type: $type");
            error_log("Expiration time: $expires_at (Current time: " . date('Y-m-d H:i:s') . ")");
            error_log("Time difference: " . (strtotime($expires_at) - strtotime(date('Y-m-d H:i:s'))) . " seconds");
            
            // Store verification token in a separate transaction
            $this->pdo->beginTransaction();
            try {
                $created_at = date('Y-m-d H:i:s'); // Use PHP timezone for consistency
                $stmt = $this->pdo->prepare("
                    INSERT INTO otps (user_id, email, otp_code, verification_token, type, expires_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([$user_id, $email, $otp, $verification_token, $type, $expires_at, $created_at]);
                
                if (!$result) {
                    throw new Exception("Failed to execute OTP insert statement");
                }
                
                $otp_id = $this->pdo->lastInsertId();
                error_log("OTP record created successfully with ID: $otp_id");
                
                $this->pdo->commit();
                
                // Verify the OTP was actually inserted
                $verifyOtpStmt = $this->pdo->prepare("SELECT id FROM otps WHERE id = ?");
                $verifyOtpStmt->execute([$otp_id]);
                if (!$verifyOtpStmt->fetch()) {
                    error_log("OTP record was not found after insertion!");
                    return false;
                }
                
            } catch (Exception $e) {
                $this->pdo->rollBack();
                error_log("Failed to insert OTP record: " . $e->getMessage());
                error_log("SQL Error Info: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
            // Send email after successful OTP creation
            $email_sent = $this->sendEmail($email, $otp, $type, $verification_token);
            
            if ($email_sent) {
                error_log("Email sent successfully for OTP ID: $otp_id");
            } else {
                error_log("Email sending failed for OTP ID: $otp_id, but OTP record was created");
            }
            
            // Return true if OTP was created successfully, regardless of email status
            // The OTP record exists in the database, so the user can still verify
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send OTP email: " . $e->getMessage());
            error_log("User ID: $user_id, Email: $email, Type: $type");
            return false;
        }
    }

    private function sendEmail($email, $otp, $type, $verification_token) {
        try {
            // Get base URL
            $base_url = $this->getBaseUrl();
            
            switch ($type) {
                case 'registration':
                    $subject = 'Verify Your Email - AiToManabi LMS';
                    $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #dc2626  0%, #b91c1c  100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                            <h1 style='margin: 0; font-size: 28px;'>Welcome to AiToManabi LMS!</h1>
                            <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Please verify your email address</p>
                        </div>
                        
                        <div style='background: white; padding: 30px; border: 1px solid #e1e5e9; border-radius: 0 0 10px 10px;'>
                            <p style='color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>
                                Thank you for creating your account! To complete your registration, please use the verification code below.
                            </p>
                            
                            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                                <p style='margin: 0 0 15px 0; color: #666; font-size: 14px;'><strong>Verification Code:</strong></p>
                                <p style='margin: 0 0 10px 0; color: #333; font-size: 14px;'>Please enter this verification code:</p>
                                <div style='background: white; padding: 15px; border: 2px dashed #dc2626; border-radius: 5px; text-align: center;'>
                                    <span style='font-size: 24px; font-weight: bold; color: #dc2626; letter-spacing: 3px;'>{$otp}</span>
                                </div>
                                <p style='margin: 10px 0 0 0; color: #666; font-size: 12px;'>
                                    Please use this code on our verification page to complete your registration.
                                </p>
                            </div>
                            
                            <div style='border-top: 1px solid #e1e5e9; padding-top: 20px; margin-top: 25px;'>
                                <p style='color: #666; font-size: 12px; margin: 0;'>
                                    <strong>Important:</strong> This verification link will expire in 5 minutes for security reasons.
                                </p>
                                <p style='color: #666; font-size: 12px; margin: 5px 0 0 0;'>
                                    If you didn't create this account, please ignore this email.
                                </p>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                            <p>© 2024 AiToManabi LMS. All rights reserved.</p>
                        </div>
                    </div>";
                    break;
                    
                case 'login':
                    $subject = 'Login Verification Code - AiToManabi LMS';
                    $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                            <h1 style='margin: 0; font-size: 28px;'>Login Verification</h1>
                            <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Secure your account access</p>
                        </div>
                        
                        <div style='background: white; padding: 30px; border: 1px solid #e1e5e9; border-radius: 0 0 10px 10px;'>
                            <p style='color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>
                                We received a login attempt for your account. To ensure it's you, please use the verification code below.
                            </p>
                            
                            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                                <p style='margin: 0 0 15px 0; color: #666; font-size: 14px;'><strong>Verification Code:</strong></p>
                                <p style='margin: 0 0 10px 0; color: #333; font-size: 14px;'>Please enter this verification code:</p>
                                <div style='background: white; padding: 15px; border: 2px dashed #b91c1c; border-radius: 5px; text-align: center;'>
                                    <span style='font-size: 24px; font-weight: bold; color: #b91c1c; letter-spacing: 3px;'>{$otp}</span>
                                </div>
                                <p style='margin: 10px 0 0 0; color: #666; font-size: 12px;'>
                                    Please use this code on our verification page to complete your login verification.
                                </p>
                            </div>
                            
                            <div style='border-top: 1px solid #e1e5e9; padding-top: 20px; margin-top: 25px;'>
                                <p style='color: #666; font-size: 12px; margin: 0 0 10px 0;'>
                                    <strong>Security Notice:</strong> If you didn't try to log in, please secure your account immediately.
                                </p>
                                <p style='color: #dc2626; font-size: 12px; margin: 0; font-weight: bold;'>
                                    For your security, this OTP will expire in 5 minutes. Do not share it with anyone.
                                </p>
                            </div>
                        </div>
                    </div>";
                    break;
                    
                case 'password_change':
                    $subject = 'Password Change Verification - AiToManabi LMS';
                    $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                            <h1 style='margin: 0; font-size: 28px;'>Password Change Verification</h1>
                            <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Confirm your password change</p>
                        </div>
                        
                        <div style='background: white; padding: 30px; border: 1px solid #e1e5e9; border-radius: 0 0 10px 10px;'>
                            <p style='color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>
                                Your password has been successfully changed. To complete the process and secure your account, please use the verification code below.
                            </p>
                            
                            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                                <p style='margin: 0 0 15px 0; color: #666; font-size: 14px;'><strong>Verification Code:</strong></p>
                                <p style='margin: 0 0 10px 0; color: #333; font-size: 14px;'>Please enter this verification code:</p>
                                <div style='background: white; padding: 15px; border: 2px dashed #b91c1c; border-radius: 5px; text-align: center;'>
                                    <span style='font-size: 24px; font-weight: bold; color: #b91c1c; letter-spacing: 3px;'>{$otp}</span>
                                </div>
                                <p style='margin: 10px 0 0 0; color: #666; font-size: 12px;'>
                                    Please use this code on our verification page to complete your password change verification.
                                </p>
                            </div>
                            
                            <div style='border-top: 1px solid #e1e5e9; padding-top: 20px; margin-top: 25px;'>
                                <p style='color: #666; font-size: 12px; margin: 0 0 10px 0;'>
                                    <strong>Security Notice:</strong> If you didn't change your password, please contact support immediately.
                                </p>
                                <p style='color: #dc2626; font-size: 12px; margin: 0; font-weight: bold;'>
                                    For your security, this OTP will expire in 5 minutes. Do not share it with anyone.
                                </p>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                            <p>© 2024 AiToManabi LMS. All rights reserved.</p>
                        </div>
                    </div>";
                    break;
                    
                case 'password_reset':
                    $subject = 'Password Reset Verification - AiToManabi LMS';
                    $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                            <h1 style='margin: 0; font-size: 28px;'>Password Reset</h1>
                            <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Reset your account password</p>
                        </div>
                        
                        <div style='background: white; padding: 30px; border: 1px solid #e1e5e9; border-radius: 0 0 10px 10px;'>
                            <p style='color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>
                                We received a request to reset your password. Please use the verification code below to proceed with the password reset.
                            </p>
                            
                            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                                <p style='margin: 0 0 15px 0; color: #666; font-size: 14px;'><strong>Verification Code:</strong></p>
                                <p style='margin: 0 0 10px 0; color: #333; font-size: 14px;'>Please enter this verification code:</p>
                                <div style='background: white; padding: 15px; border: 2px dashed #b91c1c; border-radius: 5px; text-align: center;'>
                                    <span style='font-size: 24px; font-weight: bold; color: #b91c1c; letter-spacing: 3px;'>{$otp}</span>
                                </div>
                                <p style='margin: 10px 0 0 0; color: #666; font-size: 12px;'>
                                    Please use this code on our verification page to complete your password reset.
                                </p>
                            </div>
                            
                            <div style='border-top: 1px solid #e1e5e9; padding-top: 20px; margin-top: 25px;'>
                                <p style='color: #666; font-size: 12px; margin: 0 0 10px 0;'>
                                    <strong>Security Notice:</strong> If you didn't request a password reset, please ignore this email.
                                </p>
                                <p style='color: #dc2626; font-size: 12px; margin: 0; font-weight: bold;'>
                                    For your security, this OTP will expire in 5 minutes. Do not share it with anyone.
                                </p>
                            </div>
                        </div>
                    </div>";
                    break;
            }
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Failed to send email: " . $e->getMessage());
            return false;
        }
    }

    public function verifyOTP($userId, $otp, $type) {
        try {
            // Get the most recent ACTIVE OTP for this user and type
            $stmt = $this->pdo->prepare("
                SELECT * FROM otps 
                WHERE user_id = ? 
                AND type = ? 
                AND is_used = 0
                AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId, $type]);
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$otpRecord) {
                error_log("No active OTP found for user $userId and type $type");
                return false;
            }
            
            // Check if OTP code matches
            if ($otpRecord['otp_code'] !== $otp) {
                error_log("OTP code mismatch - Expected: {$otpRecord['otp_code']}, Received: $otp");
                return false;
            }
            
            // Mark OTP as used
            $updateStmt = $this->pdo->prepare("
                UPDATE otps 
                SET is_used = 1 
                WHERE id = ?
            ");
            $updateStmt->execute([$otpRecord['id']]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("OTP verification error: " . $e->getMessage());
            return false;
        }
    }

    private function cleanupExpiredOTPs() {
        try {
            // Use Philippine time for consistency
            $currentTime = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("
                DELETE FROM otps 
                WHERE expires_at <= ? 
                OR is_used = TRUE
            ");
            $stmt->execute([$currentTime]);
        } catch (PDOException $e) {
            error_log("Error cleaning up expired OTPs: " . $e->getMessage());
        }
    }

    private function markUserAsVerified($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET email_verified = TRUE 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Error marking user as verified: " . $e->getMessage());
        }
    }

    public function isEmailVerified($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT email_verified 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error checking email verification: " . $e->getMessage());
            return false;
        }
    }

    private function getUserIdByEmail($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting user ID by email: " . $e->getMessage());
            return null;
        }
    }

    private function getBaseUrl() {
        // Use the centralized configuration
        require_once __DIR__ . '/../config/app_config.php';
        return APP_BASE_URL;
    }

public function verifyByToken($token) {
    try {
        // Get OTP record by verification token
        $stmt = $this->pdo->prepare("
            SELECT * FROM otps 
            WHERE verification_token = ? 
            AND expires_at > NOW() 
            AND is_used = 0
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$otpRecord) {
            error_log("Token verification failed - Token: $token");
            return false;
        }
        
        // Mark OTP as used
        $updateStmt = $this->pdo->prepare("
            UPDATE otps 
            SET is_used = 1 
            WHERE id = ?
        ");
        $updateStmt->execute([$otpRecord['id']]);
        
        // If this is a registration OTP, mark the user as verified
        if ($otpRecord['type'] === 'registration') {
            $this->markUserAsVerified($otpRecord['user_id']);
        }
        
        return [
            'success' => true,
            'user_id' => $otpRecord['user_id'],
            'type' => $otpRecord['type']
        ];
        
    } catch (PDOException $e) {
        error_log("Database error during token verification: " . $e->getMessage());
        return false;
    }
}
}