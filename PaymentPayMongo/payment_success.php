<?php
session_start();
require_once 'config.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/payment_errors.log');

try {
    $sessionId = $_GET['session_id'] ?? null;
    $tempId = $_GET['temp_id'] ?? null;
    $courseId = null;
    $userId = null;

    if ($tempId) {
        // Get session ID and user info from temp mapping
        $stmt = $pdo->prepare("SELECT checkout_session_id, user_id, course_id FROM temp_checkout_mapping WHERE temp_id = ?");
        $stmt->execute([$tempId]);
        $mapping = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mapping) {
            $sessionId = $mapping['checkout_session_id'];
            $userId = $mapping['user_id'];
            $courseId = $mapping['course_id'];
            
            // Clean up temp mapping
            $stmt = $pdo->prepare("DELETE FROM temp_checkout_mapping WHERE temp_id = ?");
            $stmt->execute([$tempId]);
        }
    }

    if (!$sessionId || str_contains($sessionId, '{') || $sessionId === 'TO_BE_REPLACED') {
        throw new Exception('Invalid or missing payment session ID.');
    }

    // Verify payment with PayMongo
    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', 'https://api.paymongo.com/v1/checkout_sessions/' . $sessionId, [
        'headers' => [
            'accept' => 'application/json',
            'authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
        ]
    ]);

    $paymentData = json_decode($response->getBody(), true);
    $paymentStatus = $paymentData['data']['attributes']['payment_intent']['attributes']['status'] ?? 'unknown';

    if ($paymentStatus !== 'succeeded') {
        throw new Exception('Payment not completed successfully. Status: ' . $paymentStatus);
    }

    // Get course and user info if not already available
    if (!$courseId || !$userId) {
        $stmt = $pdo->prepare("SELECT user_id, course_id FROM payment_sessions WHERE checkout_session_id = ?");
        $stmt->execute([$sessionId]);
        $paymentSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$paymentSession) {
            throw new Exception('Payment session not found in database.');
        }
        
        $userId = $paymentSession['user_id'];
        $courseId = $paymentSession['course_id'];
    }

    // Check if already enrolled (prevent duplicate enrollments)
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE course_id = ? AND student_id = ?");
    $stmt->execute([$courseId, $userId]);
    $existingEnrollment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingEnrollment) {
        // Enroll the student
        $stmt = $pdo->prepare("INSERT INTO enrollments (course_id, student_id, enrolled_at) VALUES (?, ?, NOW())");
        $stmt->execute([$courseId, $userId]);
        
        error_log("Student enrolled successfully - Course ID: $courseId, Student ID: $userId");
        $enrollmentMessage = "You have been successfully enrolled in the course!";
    } else {
        $enrollmentMessage = "You were already enrolled in this course.";
        error_log("Student was already enrolled - Course ID: $courseId, Student ID: $userId");
    }

    // Update payment session status to completed
    $stmt = $pdo->prepare("UPDATE payment_sessions SET status = 'completed', updated_at = NOW() WHERE checkout_session_id = ?");
    $stmt->execute([$sessionId]);

    // Insert payment record for paid enrollments if not already present
    $stmt = $pdo->prepare("SELECT amount, invoice_number FROM payment_sessions WHERE checkout_session_id = ?");
    $stmt->execute([$sessionId]);
    $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $amount = $sessionRow ? floatval($sessionRow['amount']) : 0;

    $stmt = $pdo->prepare("SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND amount = ? AND payment_type = 'PAID'");
    $stmt->execute([$userId, $courseId, $amount]);
    $existingPayment = $stmt->fetch();

    if (!$existingPayment && $amount > 0) {
        // Use the same invoice number used in checkout/session if available
        $invoiceNumber = $sessionRow['invoice_number'] ?? ('INV-' . strtoupper(uniqid()));
        $paymongoId = $sessionId;
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, course_id, amount, payment_status, payment_date, paymongo_id, invoice_number, payment_type) VALUES (?, ?, ?, 'completed', NOW(), ?, ?, 'PAID')");
        $stmt->execute([$userId, $courseId, $amount, $paymongoId, $invoiceNumber]);
    }

    // Get course details for display
    $stmt = $pdo->prepare("SELECT title, description FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    // Success page HTML
    ?>
    <!DOCTYPE html>
    <html lang="en" x-data :class="{ 'dark': $store.darkMode.on }">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Successful - AiToManabi</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            primary: '#ef4444',
                            secondary: '#ef4444',
                            accent: '#ef4444',
                            dark: {
                                bg: '#18181b',
                                surface: '#27272a',
                                border: '#3f3f46'
                            }
                        },
                        fontFamily: {
                            'rubik': ['"Rubik"', 'sans-serif']
                        },
                        boxShadow: {
                            'japanese': '0 4px 20px rgba(239, 68, 68, 0.05)',
                            'japanese-hover': '0 8px 30px rgba(239, 68, 68, 0.1)',
                            'success': '0 25px 50px rgba(34, 197, 94, 0.15)',
                            'success-glow': '0 0 40px rgba(34, 197, 94, 0.3)'
                        },
                        animation: {
                            'bounce-in': 'bounceIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                            'fade-in': 'fadeIn 0.6s ease-out',
                            'slide-up': 'slideUp 0.6s ease-out',
                            'pulse-success': 'pulseSuccess 2s ease-in-out infinite',
                            'shimmer': 'shimmer 2s infinite'
                        },
                        keyframes: {
                            bounceIn: {
                                '0%': { transform: 'scale(0.3)', opacity: '0' },
                                '50%': { transform: 'scale(1.1)' },
                                '70%': { transform: 'scale(0.9)' },
                                '100%': { transform: 'scale(1)', opacity: '1' }
                            },
                            fadeIn: {
                                '0%': { opacity: '0', transform: 'translateY(20px)' },
                                '100%': { opacity: '1', transform: 'translateY(0)' }
                            },
                            slideUp: {
                                '0%': { opacity: '0', transform: 'translateY(30px)' },
                                '100%': { opacity: '1', transform: 'translateY(0)' }
                            },
                            pulseSuccess: {
                                '0%, 100%': { transform: 'scale(1)', boxShadow: '0 0 0 0 rgba(34, 197, 94, 0.4)' },
                                '50%': { transform: 'scale(1.05)', boxShadow: '0 0 0 20px rgba(34, 197, 94, 0)' }
                            },
                            shimmer: {
                                '0%': { backgroundPosition: '-200% 0' },
                                '100%': { backgroundPosition: '200% 0' }
                            }
                        }
                    }
                }
            }
        </script>
        <style>
            [x-cloak] { 
                display: none !important; 
            }
            .japanese-transition {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .success-gradient {
                background: linear-gradient(135deg, #10b981 0%, #22c55e 25%, #34d399 50%, #22c55e 75%, #10b981 100%);
            }
            .red-gradient {
                background: linear-gradient(135deg, #dc2626 0%, #ef4444 25%, #f87171 50%, #ef4444 75%, #dc2626 100%);
            }
            .shimmer-effect {
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
                background-size: 200% 100%;
                animation: shimmer 2s infinite;
            }
            .glass-effect {
                backdrop-filter: blur(20px);
                background: rgba(255, 255, 255, 0.95);
            }
            .dark .glass-effect {
                background: rgba(39, 39, 42, 0.95);
            }
            .floating-shapes {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
                z-index: -1;
            }
            .shape {
                position: absolute;
                border-radius: 50%;
                opacity: 0.1;
                animation: float 6s ease-in-out infinite;
            }
            .shape-1 {
                width: 80px;
                height: 80px;
                background: linear-gradient(45deg, #ef4444, #f87171);
                top: 20%;
                left: 10%;
                animation-delay: 0s;
            }
            .shape-2 {
                width: 120px;
                height: 120px;
                background: linear-gradient(45deg, #22c55e, #34d399);
                top: 60%;
                right: 15%;
                animation-delay: 2s;
            }
            .shape-3 {
                width: 60px;
                height: 60px;
                background: linear-gradient(45deg, #ef4444, #dc2626);
                bottom: 30%;
                left: 20%;
                animation-delay: 4s;
            }
            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-20px) rotate(180deg); }
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('darkMode', {
                    on: localStorage.getItem('darkMode') === 'true',
                    toggle() {
                        this.on = !this.on;
                        localStorage.setItem('darkMode', this.on);
                    }
                });

                // Initialize dark mode on page load
                if (localStorage.getItem('darkMode') === null) {
                    Alpine.store('darkMode').on = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    localStorage.setItem('darkMode', Alpine.store('darkMode').on);
                }
            });
        </script>
    </head>
    <body class="min-h-screen font-rubik bg-gradient-to-br from-gray-50 via-white to-red-50 dark:from-dark-bg dark:via-dark-surface dark:to-red-900/10 transition-colors duration-200">
        <!-- Floating Background Shapes -->
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>

        <!-- Main Content -->
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-effect border border-white/20 dark:border-gray-700/30 rounded-3xl shadow-success max-w-2xl w-full p-8 md:p-12 text-center animate-fade-in">
                
                <!-- Success Icon -->
                <div class="relative mb-8">
                    <div class="success-gradient w-24 h-24 md:w-32 md:h-32 rounded-full flex items-center justify-center mx-auto shadow-success-glow animate-bounce-in">
                        <svg class="w-12 h-12 md:w-16 md:h-16 text-white animate-pulse-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="absolute inset-0 success-gradient rounded-full blur-xl opacity-30 animate-pulse-success"></div>
                </div>

                <!-- Success Title -->
                <h1 class="text-3xl md:text-4xl lg:text-5xl rubik-bold bg-gradient-to-r from-green-600 via-green-500 to-emerald-500 bg-clip-text text-transparent mb-4 animate-slide-up">
                    Payment Successful!
                </h1>

                <!-- Success Message -->
                <p class="text-lg md:text-xl text-gray-600 dark:text-gray-300 rubik-medium mb-8 animate-slide-up" style="animation-delay: 0.2s;">
                    <?php echo htmlspecialchars($enrollmentMessage); ?>
                </p>

                <!-- Course Information -->
                <?php if ($course): ?>
                <div class="glass-effect border border-red-200/30 dark:border-red-800/30 rounded-2xl p-6 md:p-8 mb-8 animate-slide-up" style="animation-delay: 0.4s;">
                    <div class="flex items-center justify-center mb-4">
                        <div class="red-gradient w-12 h-12 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <div class="text-left">
                            <h3 class="text-xl md:text-2xl rubik-bold text-gray-900 dark:text-white mb-2">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h3>
                            <p class="text-gray-600 dark:text-gray-300 rubik-regular leading-relaxed">
                                <?php echo htmlspecialchars($course['description']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8 animate-slide-up" style="animation-delay: 0.6s;">
                    <a href="../dashboard/continue_learning.php?id=<?php echo htmlspecialchars($courseId); ?>" class="group relative overflow-hidden red-gradient text-white px-8 py-4 rounded-2xl rubik-bold text-lg japanese-transition hover:shadow-japanese-hover transform hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-red-500/20">
                        <div class="shimmer-effect absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <div class="relative flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            Go to Module Content
                        </div>
                    </a>
                    
                    <a href="../dashboard/my_learning.php" class="group relative overflow-hidden bg-white dark:bg-dark-surface border-2 border-red-500 text-red-500 dark:text-red-400 px-8 py-4 rounded-2xl rubik-bold text-lg japanese-transition hover:bg-red-50 dark:hover:bg-red-900/20 hover:shadow-japanese transform hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-red-500/20">
                        <div class="relative flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            My Courses
                        </div>
                    </a>
                </div>

                <!-- Session ID -->
                <div class="text-xs text-gray-400 dark:text-gray-500 rubik-regular animate-slide-up" style="animation-delay: 0.8s;">
                    Session ID: <span class="font-mono"><?php echo htmlspecialchars($sessionId); ?></span>
                </div>

                <!-- Celebration Elements -->
                <div class="absolute -top-4 -left-4 w-8 h-8 bg-gradient-to-r from-yellow-400 to-orange-400 rounded-full opacity-70 animate-bounce" style="animation-delay: 1s;"></div>
                <div class="absolute -top-2 -right-6 w-6 h-6 bg-gradient-to-r from-pink-400 to-red-400 rounded-full opacity-70 animate-bounce" style="animation-delay: 1.5s;"></div>
                <div class="absolute -bottom-4 -left-2 w-5 h-5 bg-gradient-to-r from-blue-400 to-indigo-400 rounded-full opacity-70 animate-bounce" style="animation-delay: 2s;"></div>
                <div class="absolute -bottom-2 -right-4 w-7 h-7 bg-gradient-to-r from-green-400 to-emerald-400 rounded-full opacity-70 animate-bounce" style="animation-delay: 2.5s;">                </div>
            </div>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    error_log('Payment success page error: ' . $e->getMessage());
    ?>
    <!DOCTYPE html>
    <html lang="en" x-data :class="{ 'dark': $store.darkMode.on }">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Error - AiToManabi</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            primary: '#ef4444',
                            secondary: '#ef4444',
                            accent: '#ef4444',
                            dark: {
                                bg: '#18181b',
                                surface: '#27272a',
                                border: '#3f3f46'
                            }
                        },
                        fontFamily: {
                            'rubik': ['"Rubik"', 'sans-serif']
                        },
                        boxShadow: {
                            'japanese': '0 4px 20px rgba(239, 68, 68, 0.05)',
                            'japanese-hover': '0 8px 30px rgba(239, 68, 68, 0.1)',
                            'error': '0 25px 50px rgba(239, 68, 68, 0.15)',
                            'error-glow': '0 0 40px rgba(239, 68, 68, 0.3)'
                        },
                        animation: {
                            'bounce-in': 'bounceIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                            'fade-in': 'fadeIn 0.6s ease-out',
                            'slide-up': 'slideUp 0.6s ease-out',
                            'pulse-error': 'pulseError 2s ease-in-out infinite',
                            'shimmer': 'shimmer 2s infinite'
                        },
                        keyframes: {
                            bounceIn: {
                                '0%': { transform: 'scale(0.3)', opacity: '0' },
                                '50%': { transform: 'scale(1.1)' },
                                '70%': { transform: 'scale(0.9)' },
                                '100%': { transform: 'scale(1)', opacity: '1' }
                            },
                            fadeIn: {
                                '0%': { opacity: '0', transform: 'translateY(20px)' },
                                '100%': { opacity: '1', transform: 'translateY(0)' }
                            },
                            slideUp: {
                                '0%': { opacity: '0', transform: 'translateY(30px)' },
                                '100%': { opacity: '1', transform: 'translateY(0)' }
                            },
                            pulseError: {
                                '0%, 100%': { transform: 'scale(1)', boxShadow: '0 0 0 0 rgba(239, 68, 68, 0.4)' },
                                '50%': { transform: 'scale(1.05)', boxShadow: '0 0 0 20px rgba(239, 68, 68, 0)' }
                            },
                            shimmer: {
                                '0%': { backgroundPosition: '-200% 0' },
                                '100%': { backgroundPosition: '200% 0' }
                            }
                        }
                    }
                }
            }
        </script>
        <style>
            [x-cloak] { 
                display: none !important; 
            }
            .japanese-transition {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .error-gradient {
                background: linear-gradient(135deg, #dc2626 0%, #ef4444 25%, #f87171 50%, #ef4444 75%, #dc2626 100%);
            }
            .red-gradient {
                background: linear-gradient(135deg, #dc2626 0%, #ef4444 25%, #f87171 50%, #ef4444 75%, #dc2626 100%);
            }
            .shimmer-effect {
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
                background-size: 200% 100%;
                animation: shimmer 2s infinite;
            }
            .glass-effect {
                backdrop-filter: blur(20px);
                background: rgba(255, 255, 255, 0.95);
            }
            .dark .glass-effect {
                background: rgba(39, 39, 42, 0.95);
            }
            .floating-shapes {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
                z-index: -1;
            }
            .shape {
                position: absolute;
                border-radius: 50%;
                opacity: 0.1;
                animation: float 6s ease-in-out infinite;
            }
            .shape-1 {
                width: 80px;
                height: 80px;
                background: linear-gradient(45deg, #ef4444, #f87171);
                top: 20%;
                left: 10%;
                animation-delay: 0s;
            }
            .shape-2 {
                width: 120px;
                height: 120px;
                background: linear-gradient(45deg, #dc2626, #ef4444);
                top: 60%;
                right: 15%;
                animation-delay: 2s;
            }
            .shape-3 {
                width: 60px;
                height: 60px;
                background: linear-gradient(45deg, #f87171, #fca5a5);
                bottom: 30%;
                left: 20%;
                animation-delay: 4s;
            }
            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-20px) rotate(180deg); }
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('darkMode', {
                    on: localStorage.getItem('darkMode') === 'true',
                    toggle() {
                        this.on = !this.on;
                        localStorage.setItem('darkMode', this.on);
                    }
                });

                // Initialize dark mode on page load
                if (localStorage.getItem('darkMode') === null) {
                    Alpine.store('darkMode').on = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    localStorage.setItem('darkMode', Alpine.store('darkMode').on);
                }
            });
        </script>
    </head>
    <body class="min-h-screen font-rubik bg-gradient-to-br from-red-50 via-white to-gray-50 dark:from-dark-bg dark:via-dark-surface dark:to-red-900/10 transition-colors duration-200">
        <!-- Floating Background Shapes -->
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>

        <!-- Main Content -->
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-effect border border-red-200/30 dark:border-red-800/30 rounded-3xl shadow-error max-w-2xl w-full p-8 md:p-12 text-center animate-fade-in">
                
                <!-- Error Icon -->
                <div class="relative mb-8">
                    <div class="error-gradient w-24 h-24 md:w-32 md:h-32 rounded-full flex items-center justify-center mx-auto shadow-error-glow animate-bounce-in">
                        <svg class="w-12 h-12 md:w-16 md:h-16 text-white animate-pulse-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <div class="absolute inset-0 error-gradient rounded-full blur-xl opacity-30 animate-pulse-error"></div>
                </div>

                <!-- Error Title -->
                <h1 class="text-3xl md:text-4xl lg:text-5xl rubik-bold bg-gradient-to-r from-red-600 via-red-500 to-pink-500 bg-clip-text text-transparent mb-4 animate-slide-up">
                    Payment Error
                </h1>

                <!-- Error Message -->
                <p class="text-lg md:text-xl text-gray-600 dark:text-gray-300 rubik-medium mb-8 animate-slide-up" style="animation-delay: 0.2s;">
                    <?php echo htmlspecialchars($e->getMessage()); ?>
                </p>

                <!-- Action Button -->
                <div class="flex justify-center mb-8 animate-slide-up" style="animation-delay: 0.4s;">
                    <a href="../dashboard/my_learning.php" class="group relative overflow-hidden red-gradient text-white px-8 py-4 rounded-2xl rubik-bold text-lg japanese-transition hover:shadow-japanese-hover transform hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-red-500/20">
                        <div class="shimmer-effect absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <div class="relative flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            Return to Dashboard
                        </div>
                    </a>
                </div>

                <!-- Support Message -->
                <div class="text-sm text-gray-500 dark:text-gray-400 rubik-regular animate-slide-up" style="animation-delay: 0.6s;">
                    If you continue to experience issues, please contact our support team.
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>