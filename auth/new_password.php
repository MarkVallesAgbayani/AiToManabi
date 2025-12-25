<?php
session_start();
require_once '../config/database.php';

// Debug log
error_log("New Password Page - Session variables: " . 
          "otp_user_id: " . ($_SESSION['otp_user_id'] ?? 'not set') . 
          ", reset_email: " . ($_SESSION['reset_email'] ?? 'not set') . 
          ", otp_type: " . ($_SESSION['otp_type'] ?? 'not set'));

// Check if user is authorized to reset password
if (!isset($_SESSION['otp_user_id']) || !isset($_SESSION['reset_email'])) {
    error_log("New Password Page - Missing session variables, redirecting to forgot-password.php");
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
    <title>Set New Password - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .font-jp { font-family: 'Noto Sans JP', sans-serif; }
        input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
        }
        input:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 1px #ef4444;
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #6B7280;
            margin-top: 0.5rem;
        }
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        .requirement::before {
            content: "•";
            margin-right: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-jp min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full m-4">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Set New Password</h2>
            <a href="../dashboard/login.php" class="text-gray-500 hover:text-gray-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $success; ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">New Password</label>
                <input type="password" name="password" required 
                       class="mt-1 block w-full"
                       placeholder="Enter new password">
                <div class="password-requirements">
                    <div class="requirement">At least 12 characters long</div>
                    <div class="requirement">Include at least one uppercase letter</div>
                    <div class="requirement">Include at least one lowercase letter</div>
                    <div class="requirement">Include at least one number</div>
                    <div class="requirement">Include at least one special character</div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                <input type="password" name="confirm_password" required 
                       class="mt-1 block w-full"
                       placeholder="Confirm new password">
            </div>
            
            <button type="submit" class="w-full bg-red-600 text-white rounded-md py-2 px-4 hover:bg-red-700">
                Reset Password
            </button>
        </form>
    </div>
</body>
</html> 