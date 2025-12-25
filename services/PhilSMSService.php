<?php

class PhilSMSService {
    private $api_token;
    private $sender_id;
    private $api_url;
    
    public function __construct() {
        // Your PhilSMS credentials
        $this->api_token = "2912|uSlAoV73K1l8jAdm1lmHcgvTrgtWLX22F3JpoDpK";
        $this->sender_id = "PhilSMS";
        $this->api_url = "https://app.philsms.com/api/v3/sms/send";
    }
    
    /**
     * Send SMS via PhilSMS API
     * @param string $phone_number Phone number with country code (+639XXXXXXXXX)
     * @param string $message SMS message content
     * @return array Response with success status and message
     */
    public function sendSMS($phone_number, $message) {
        try {
            // Log the SMS attempt
            error_log("PhilSMS: Attempting to send SMS to {$phone_number}");
            
            // Prepare SMS data
            $send_data = [
                'sender_id' => $this->sender_id,
                'recipient' => $this->normalizePhoneNumber($phone_number),
                'message' => $message
            ];
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($send_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->api_token
            ]);
            
            // Execute request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Handle cURL errors
            if ($curl_error) {
                error_log("PhilSMS cURL Error: " . $curl_error);
                return [
                    'success' => false,
                    'message' => 'Network error occurred',
                    'error' => $curl_error,
                    'http_code' => 0
                ];
            }
            
            // Parse response
            $response_data = json_decode($response, true);
            
            // Log the response
            error_log("PhilSMS Response (HTTP {$http_code}): " . $response);
            
            // Check HTTP status
            if ($http_code >= 200 && $http_code < 300) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'response' => $response_data,
                    'http_code' => $http_code
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'SMS sending failed',
                    'response' => $response_data,
                    'http_code' => $http_code,
                    'error' => $response_data['message'] ?? 'Unknown error'
                ];
            }
            
        } catch (Exception $e) {
            error_log("PhilSMS Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMS service error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send OTP via SMS
     * @param string $phone_number Phone number
     * @param string $otp_code 6-digit OTP code
     * @param string $type Type of OTP (registration, verification, etc.)
     * @return array Response with success status
     */
    public function sendOTP($phone_number, $otp_code, $type = 'verification') {
        // Create OTP message
        $message = $this->createOTPMessage($otp_code, $type);
        
        // Send SMS
        $result = $this->sendSMS($phone_number, $message);
        
        // Log SMS to database
        $this->logSMSAttempt($phone_number, $message, $otp_code, $result);
        
        return $result;
    }
    
    /**
     * Create OTP message content
     * @param string $otp_code 6-digit OTP
     * @param string $type OTP type
     * @return string Formatted SMS message
     */
    private function createOTPMessage($otp_code, $type = 'verification') {
        switch ($type) {
            case 'registration':
                return "Welcome to AiToManabi! Your registration verification code is: {$otp_code}. This code expires in 5 minutes. Do not share this code with anyone.";
                
            case 'login':
                return "AiToManabi Login Code: {$otp_code}. This code expires in 5 minutes. If you didn't request this, please ignore this message.";
                
            case 'password_reset':
                return "AiToManabi Password Reset Code: {$otp_code}. This code expires in 5 minutes. If you didn't request this, please secure your account.";
                
            default:
                return "Your AiToManabi verification code is: {$otp_code}. This code expires in 5 minutes. Keep this code confidential.";
        }
    }
    
    /**
     * Normalize phone number format for PhilSMS
     * @param string $phone_number Input phone number
     * @return string Normalized phone number (+639XXXXXXXXX)
     */
    private function normalizePhoneNumber($phone_number) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^\d]/', '', $phone_number);
        
        // Handle different input formats
        if (strlen($cleaned) == 11 && substr($cleaned, 0, 2) == '09') {
            // 09XXXXXXXXX -> +639XXXXXXXXX
            return '+63' . substr($cleaned, 1);
        } elseif (strlen($cleaned) == 10 && substr($cleaned, 0, 1) == '9') {
            // 9XXXXXXXXX -> +639XXXXXXXXX
            return '+63' . $cleaned;
        } elseif (strlen($cleaned) == 12 && substr($cleaned, 0, 2) == '63') {
            // 639XXXXXXXXX -> +639XXXXXXXXX
            return '+' . $cleaned;
        } elseif (strlen($cleaned) == 13 && substr($cleaned, 0, 3) == '639') {
            // Already in correct format, ensure + prefix
            return '+' . $cleaned;
        }
        
        // If already has +63 prefix, return as is
        if (strpos($phone_number, '+63') === 0) {
            return $phone_number;
        }
        
        // Default: assume it's a Philippine number and add +63
        return '+63' . $cleaned;
    }
    
    /**
     * Log SMS attempt to database
     * @param string $phone_number Phone number
     * @param string $message SMS message
     * @param string $otp_code OTP code
     * @param array $result SMS sending result
     */
    private function logSMSAttempt($phone_number, $message, $otp_code, $result) {
        try {
            // Include database connection and get the PDO instance
            require_once __DIR__ . '/../config/database.php';
            
            // The database.php file creates $pdo variable, we need to use it in this scope
            global $pdo;
            
            // Check if PDO connection exists
            if (!$pdo) {
                error_log("PhilSMS: Database connection not available for SMS logging");
                return;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO sms_logs (
                    phone_number, message, otp_code, provider, status, 
                    provider_response, error_message, sent_at
                ) VALUES (?, ?, ?, 'PhilSMS', ?, ?, ?, ?)
            ");
            
            $status = $result['success'] ? 'sent' : 'failed';
            $provider_response = isset($result['response']) ? json_encode($result['response']) : null;
            $error_message = $result['success'] ? null : ($result['error'] ?? 'Unknown error');
            $sent_at = $result['success'] ? date('Y-m-d H:i:s') : null;
            
            $stmt->execute([
                $phone_number,
                $message,
                $otp_code,
                $status,
                $provider_response,
                $error_message,
                $sent_at
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to log SMS attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Check SMS rate limits
     * @param string $phone_number Phone number to check
     * @return array Rate limit status
     */
public function checkRateLimit($phone_number) {
    try {
        // Include database connection
        require_once __DIR__ . '/../config/database.php';
        global $pdo;
        
        if (!$pdo) {
            return ['allowed' => true, 'remaining' => 5, 'limit' => 5, 'reset_in' => 3600];
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as sms_count 
            FROM sms_logs 
            WHERE phone_number = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND status = 'sent'
        ");
        $stmt->execute([$phone_number]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $hourly_limit = 5;
        $remaining = max(0, $hourly_limit - $result['sms_count']);
        
        return [
            'allowed' => $result['sms_count'] < $hourly_limit,
            'remaining' => $remaining,
            'limit' => $hourly_limit,
            'reset_in' => 3600
        ];
        
    } catch (Exception $e) {
        error_log("SMS rate limit check failed: " . $e->getMessage());
        return ['allowed' => true, 'remaining' => 1, 'limit' => 5, 'reset_in' => 3600];
    }
}
}