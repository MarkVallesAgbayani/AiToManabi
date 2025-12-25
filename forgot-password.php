<?php
session_start();
require_once 'config/database.php';
require_once 'includes/email_notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            
            // Store token in database
            $stmt = $pdo->prepare("
                INSERT INTO password_reset_tokens (user_id, token)
                VALUES (?, ?)
            ");
            $stmt->execute([$user['id'], $token]);
            
            // Update user status
            $stmt = $pdo->prepare("
                UPDATE users 
                SET status = 'password_reset'
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);
            
            // Send reset email
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
            sendPasswordResetEmail($email, $user['username'], $reset_link);
            
            $_SESSION['success'] = "Password reset instructions have been sent to your email.";
            header("Location: login.php");
            exit();
        } else {
            throw new Exception("If an account exists with this email, you will receive password reset instructions.");
        }
    } catch (Exception $e) {
        $_SESSION['info'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Japanese LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Reset your password
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Enter your email address and we'll send you instructions to reset your password.
                </p>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
                    <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
                </div>
            <?php endif; ?>
            
            <form class="mt-8 space-y-6" method="POST">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" required 
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                               placeholder="Enter your email">
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Send reset instructions
                    </button>
                </div>

                <div class="text-sm text-center">
                    <a href="login.php" class="font-medium text-primary-600 hover:text-primary-500">
                        Back to login
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 