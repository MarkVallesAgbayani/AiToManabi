<?php
/**
 * Newsletter Subscription Handler
 * Processes email subscriptions from the "Stay in Touch" form
 */

// Include PHPMailer classes - MUST be at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include configuration
require_once 'config/app_config.php';
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php?error=invalid_request");
    exit;
}

// Get and validate email
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.php?error=invalid_email");
    exit;
}

try {
    // Store in database
    $sql = "INSERT INTO newsletter_subscribers (email, subscribed_at, ip_address) VALUES (?, NOW(), ?)";
    $stmt = $pdo->prepare($sql);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt->execute([$email, $ip_address]);
    
    // Send confirmation email using PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration from app_config.php
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Email settings
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(FROM_EMAIL, FROM_NAME);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to AiToManabi Newsletter!';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ef4444; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 12px 30px; background: #ef4444; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Welcome to AiToManabi!</h1>
                </div>
                <div class="content">
                    <p>Hi there!</p>
                    <p>Thank you for subscribing to our newsletter. We\'re excited to have you join our community of Japanese language learners!</p>
                    <p>You\'ll receive updates about:</p>
                    <ul>
                        <li>New learning modules and features</li>
                        <li>Exclusive offers and promotions</li>
                    </ul>
                    <div style="text-align: center;">
                        <a href="' . APP_BASE_URL . '/dashboard/signup.php" class="button">Start Learning Now</a>
                    </div>
                    <p>If you have any questions, feel free to reply to this email.</p>
                    <p>Happy learning!<br><strong>The AiToManabi Team</strong></p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' AiToManabi. All rights reserved.</p>
                    <p>You received this email because you subscribed on our website.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Welcome to AiToManabi!\n\nThank you for subscribing to our newsletter. We're excited to have you join our community of Japanese language learners!\n\nStart learning now: " . APP_BASE_URL . "/dashboard/signup.php\n\nThe AiToManabi Team";
        
        $mail->send();
        
        // Success - redirect with success message
        header("Location: index.php?success=subscribed");
        exit;
        
    } catch (Exception $e) {
        // Email failed but subscription saved
        error_log("Newsletter email failed: " . $mail->ErrorInfo);
        header("Location: index.php?success=subscribed_no_email");
        exit;
    }
    
} catch (PDOException $e) {
    // Check if it's a duplicate entry
    if ($e->getCode() == 23000) {
        header("Location: index.php?error=already_subscribed");
    } else {
        error_log("Newsletter subscription error: " . $e->getMessage());
        header("Location: index.php?error=subscription_failed");
    }
    exit;
}
?>
