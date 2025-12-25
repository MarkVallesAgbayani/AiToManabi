<?php
session_start();

// Check if user came from successful registration
if (!isset($_SESSION['account_created']) || $_SESSION['account_created'] !== true) {
    // If not from successful registration, redirect to signup
    header('Location: signup.php');
    exit();
}

// Clear the session flag
unset($_SESSION['account_created']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created Successfully - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/signup_success.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-red-500 via-red-600 to-red-700 flex items-center justify-center p-4 font-jp">
    
    <!-- Success Card -->
    <div class="success-card bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full text-center transform transition-all duration-1000 ease-out">
        
        <!-- Success Image -->
        <div class="mb-6">
            <img src="../assets/images/account_success.jpg" 
                 alt="Account Created Successfully" 
                 class="success-image mx-auto max-w-[280px] w-full h-auto opacity-0 transform translate-y-4"
                 onerror="this.style.display='none'; document.getElementById('fallback-icon').style.display='block';">
            
            <!-- Fallback Icon if image doesn't exist -->
            <div id="fallback-icon" class="hidden">
                <div class="success-icon-container mx-auto w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="success-icon w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Success Message with Icon -->
        <div class="flex items-center justify-center mb-2">
            <svg class="checkmark-icon w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h1 class="text-red-600 font-bold text-xl tracking-wide">
                🎉 Successfully created account!
            </h1>
        </div>

        <!-- Subtext -->
        <p class="text-gray-600 text-sm mt-1 mb-6">
            Please login to continue.
        </p>

        <!-- Progress Bar -->
        <div class="mb-4">
            <div class="w-full bg-gray-200 rounded-full h-1">
                <div class="progress-bar bg-red-500 h-1 rounded-full transition-all duration-75 ease-linear" style="width: 0%"></div>
            </div>
        </div>

        <!-- Redirect Text -->
        <p class="text-gray-400 text-xs italic">
            Redirecting to login in <span id="countdown">3</span> seconds...
        </p>

        <!-- Manual Login Button -->
        <div class="mt-6">
            <a href="login.php" 
               class="inline-flex items-center px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-medium text-sm rounded-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                Login Now
            </a>
        </div>
    </div>

    <script src="js/signup_success.js"></script>
</body>
</html>
