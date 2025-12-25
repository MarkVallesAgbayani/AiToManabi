<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function getMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'] ?? '';
        $mail->Password = $_ENV['SMTP_PASSWORD'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
        
        // Default sender
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com', $_ENV['MAIL_FROM_NAME'] ?? 'Japanese LMS');
        
        return $mail;
    } catch (Exception $e) {
        error_log("Error setting up mailer: " . $e->getMessage());
        throw new Exception("Failed to initialize email system. Please try again later.");
    }
}

function sendPasswordResetEmail($to_email, $username, $reset_link) {
    try {
        $mail = getMailer();
        
        // Recipients
        $mail->addAddress($to_email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Instructions';
        
        // HTML body
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #333;'>Password Reset Request</h2>
                <p>Hello {$username},</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$reset_link}' 
                       style='background-color: #0284c7; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Reset Password
                    </a>
                </p>
                <p>If you didn't request this password reset, you can safely ignore this email.</p>
                <p>This link will expire in 24 hours for security reasons.</p>
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
                <p style='color: #666; font-size: 12px;'>
                    This is an automated message, please do not reply to this email.
                </p>
            </div>
        ";
        
        // Plain text body (as fallback)
        $mail->AltBody = "
            Hello {$username},
            
            We received a request to reset your password. Please click the link below to create a new password:
            
            {$reset_link}
            
            If you didn't request this password reset, you can safely ignore this email.
            
            This link will expire in 24 hours for security reasons.
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error sending password reset email: " . $e->getMessage());
        throw new Exception("Failed to send password reset email. Please try again later.");
    }
}

class EmailNotifications {
    private $pdo;
    private $from_email = 'noreply@japanese-lms.com';
    private $from_name = 'Japanese LMS';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function sendAccountCreationEmail($user_id, $temp_password = null) {
        $stmt = $this->pdo->prepare("SELECT email, username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) return false;

        // Generate password reset token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $token, $expires_at]);

        $reset_link = "http://{$_SERVER['HTTP_HOST']}/reset-password.php?token=" . $token;

        $subject = "Welcome to Japanese LMS - Account Created";
        $message = "
            <html>
            <body>
                <h2>Welcome to Japanese LMS!</h2>
                <p>Dear {$user['username']},</p>
                <p>Your account has been created successfully.</p>
                " . ($temp_password ? "<p>Your temporary password is: <strong>{$temp_password}</strong></p>" : "") . "
                <p>Please click the link below to set up your password:</p>
                <p><a href='{$reset_link}'>Set Your Password</a></p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not request this account, please ignore this email.</p>
            </body>
            </html>
        ";

        return $this->sendEmail($user['email'], $subject, $message);
    }

    public function sendPasswordResetEmail($user_id) {
        $stmt = $this->pdo->prepare("SELECT email, username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) return false;

        // Generate password reset token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $token, $expires_at]);

        $reset_link = "http://{$_SERVER['HTTP_HOST']}/reset-password.php?token=" . $token;

        $subject = "Password Reset Request";
        $message = "
            <html>
            <body>
                <h2>Password Reset Request</h2>
                <p>Dear {$user['username']},</p>
                <p>We received a request to reset your password.</p>
                <p>Please click the link below to reset your password:</p>
                <p><a href='{$reset_link}'>Reset Password</a></p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not request this password reset, please ignore this email.</p>
            </body>
            </html>
        ";

        return $this->sendEmail($user['email'], $subject, $message);
    }

    private function sendEmail($to, $subject, $message) {
        $headers = array(
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: PHP/' . phpversion()
        );

        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
} 