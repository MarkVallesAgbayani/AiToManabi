<?php
session_start();
require_once '../config/database.php';

// Debug log
error_log("Forgot Password Page - Session variables: " . 
          "otp_user_id: " . ($_SESSION['otp_user_id'] ?? 'not set') . 
          ", reset_email: " . ($_SESSION['reset_email'] ?? 'not set') . 
          ", otp_type: " . ($_SESSION['otp_type'] ?? 'not set'));

// Clear any existing session variables
unset($_SESSION['otp_user_id']);
unset($_SESSION['reset_email']);
unset($_SESSION['otp_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot Password - Japanese Learning Platform</title>
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
  </style>
</head>
<body class="bg-gray-100 font-jp min-h-screen flex items-center justify-center">
  <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full m-4">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Reset Password</h2>
      <a href="../dashboard/login.php" class="text-gray-500 hover:text-gray-700">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </a>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <p class="text-gray-600 mb-6">Enter your email address and we'll send you a verification code to reset your password.</p>
    
    <form action="../auth/auth.php" method="POST" class="space-y-4">
      <input type="hidden" name="action" value="forgot_password">
      <div>
        <label class="block text-sm font-medium text-gray-700">Email Address</label>
        <input type="email" name="email" required class="mt-1 block w-full" 
               placeholder="Enter your registered email"
               value="<?php echo isset($_SESSION['reset_email']) ? htmlspecialchars($_SESSION['reset_email']) : ''; ?>">
      </div>
      <button type="submit" class="w-full bg-red-600 text-white rounded-md py-2 px-4 hover:bg-red-700">
        Send Verification Code
      </button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-600">
      Remember your password?
      <a href="../dashboard/login.php" class="text-red-600 hover:text-red-500 font-semibold ml-1">Login here</a>
    </p>
  </div>
</body>
</html>
