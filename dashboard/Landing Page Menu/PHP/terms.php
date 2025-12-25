<?php
require_once '../../../config/database.php';

// Fetch terms and conditions content from database
try {
    $stmt = $pdo->prepare("SELECT content, updated_at FROM terms_conditions ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $terms_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $terms_content = $terms_data ? $terms_data['content'] : '';
    $last_updated = $terms_data ? $terms_data['updated_at'] : '';
} catch (PDOException $e) {
    error_log("Error fetching terms and conditions: " . $e->getMessage());
    $terms_content = '';
    $last_updated = '';
}

// Default content if none exists
if (empty($terms_content)) {
    $terms_content = '
    <h2>1. Introduction</h2>
    <p>Welcome to AiToManabi. These Terms and Conditions ("Terms") govern your use of our Japanese language learning platform.</p>
    
    <h2>2. User Responsibilities</h2>
    <p>Users are responsible for maintaining the confidentiality of their account credentials and for all activities that occur under their account.</p>
    
    <h2>3. Account Policies</h2>
    <p>Users must provide accurate information when creating an account and must be at least 13 years old to use our services.</p>
    
    <h2>4. Data Usage</h2>
    <p>We collect and use your data in accordance with our Privacy Policy to provide and improve our services.</p>
    
    <h2>5. Security</h2>
    <p>We implement appropriate security measures to protect your personal information and learning progress.</p>
    
    <h2>6. Governing Law</h2>
    <p>These Terms are governed by the laws of the Philippines and any disputes will be resolved in Philippine courts.</p>
    ';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - AiToManabi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&display=swap" rel="stylesheet">
    <!-- AOS Library -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <link rel="stylesheet" href="../css/terms.css">
    <style>
        .font-jp { font-family: 'Noto Sans JP', sans-serif; }
        .font-comfortaa { font-family: 'Comfortaa', sans-serif; }
        html { scroll-behavior: smooth; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        
        /* Fix text readability - override prose styles */
        .prose {
            color: #1f2937 !important;
        }
        .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
            color: #111827 !important;
            font-weight: 700;
        }
        .prose p {
            color: #374151 !important;
            line-height: 1.7;
        }
        .prose ul, .prose ol {
            color: #374151 !important;
        }
        .prose li {
            color: #374151 !important;
        }
        .prose strong {
            color: #111827 !important;
            font-weight: 600;
        }
        .prose a {
            color: #dc2626 !important;
            text-decoration: underline;
        }
        .prose a:hover {
            color: #b91c1c !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-jp min-h-screen">
    <!-- HEADER -->
    <header id="main-header" class="sticky top-0 z-40 bg-white border-b border-gray-200 transition-all duration-300 shadow-sm">
        <nav class="flex items-center justify-between p-6 lg:px-8">
            <div class="flex lg:flex-1">
                <a href="../../../index.php" class="-m-1.5 p-1.5">
                    <span class="text-2xl font-comfortaa text-red-500 font-bold">AiToManabi</span>
                </a>
            </div>
            <div class="hidden lg:flex lg:gap-x-8">
                <a href="courses.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">Courses</a>
                <a href="../../../index.php#pricing" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">Price</a>
                <a href="explore-features.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">Features</a>
                <a href="learnmore.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">About</a>
            </div>
            <div class="hidden lg:flex lg:flex-1 lg:justify-end">
                <div class="flex items-center space-x-4">
                    <a href="../../signup.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 px-4 py-2 rounded-lg hover:bg-red-50">Register</a>
                    <a href="../../login.php" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-lg hover:bg-red-700 transform hover:scale-105 transition-all duration-300 text-sm">Login</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- MAIN CONTENT -->
    <main class="py-12">
        <div class="max-w-4xl mx-auto px-6 lg:px-8">
            <!-- Page Header -->
            <div class="text-center mb-12" data-aos="fade-up">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-6">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 font-comfortaa">
                    Terms & <span class="text-red-600">Conditions</span>
                </h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Please read these terms carefully before using our Japanese learning platform.
                </p>
                <?php if ($last_updated): ?>
                    <p class="text-sm text-gray-500 mt-4">
                        Last updated: <?php 
                            // Create DateTime object from the stored timestamp
                            $philippines_time = new DateTime($last_updated);
                            // Convert to Philippines timezone
                            $philippines_time->setTimezone(new DateTimeZone('Asia/Manila'));
                            echo $philippines_time->format('F j, Y, g:i a') . ' (PHT)';
                        ?>
                    </p>
                <?php endif; ?>
            </div>


            <!-- Content Container -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden" data-aos="fade-up" data-aos-delay="200">
                <div class="p-8 md:p-12">
                    <div class="prose prose-lg max-w-none">
                        <div class="text-gray-800 leading-relaxed space-y-8">
                            <?php echo $terms_content; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back to Home -->
            <div class="text-center mt-12" data-aos="fade-up" data-aos-delay="300">
                <a href="../../../index.php" class="inline-flex items-center px-6 py-3 bg-red-600 text-white font-semibold rounded-lg shadow-lg hover:bg-red-700 transform hover:scale-105 transition-all duration-300">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="w-full bg-white text-black py-16 px-6 sm:px-10 mt-16">
        <div class="max-w-screen-xl mx-auto flex flex-col items-center text-center">
            
            <!-- Email Form Centered -->
            <div class="w-full max-w-2xl mb-12" data-aos="zoom-in-up" data-aos-delay="100">
                <h3 class="text-3xl font-bold mb-6 text-red-600" data-aos="fade-up" data-aos-delay="200">Stay in Touch</h3>
                <form class="flex flex-col sm:flex-row items-center gap-4 justify-center" data-aos="fade-up" data-aos-delay="300">
                    <input
                        type="email"
                        placeholder="Your email address"
                        class="w-full sm:flex-1 px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-red-500 placeholder-gray-500"
                    />
                    <button
                        type="submit"
                        class="px-6 py-3 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition"
                    >
                        Send
                    </button>
                </form>
            </div>

            <!-- Divider -->
            <div class="h-px bg-gray-200 w-full mb-8"></div>

            <!-- Links & Legal Bottom Row -->
            <div class="w-full flex flex-col sm:flex-row justify-between items-center text-sm text-gray-600 gap-4" data-aos="fade-up" data-aos-delay="100">
                <div class="flex gap-6" data-aos="fade-up" data-aos-delay="200">
                    <a href="terms.php" class="hover:text-black transition">Terms & Conditions</a>
                    <a href="privacy.php" class="hover:text-black transition">Privacy Policy</a>
                </div>
                <p class="text-center sm:text-right" data-aos="fade-up" data-aos-delay="300">&copy; 2025 AiToManabi. All rights reserved.</p>
            </div>

        </div>
    </footer>

    <script src="../JS/terms.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 600,
            easing: 'ease-in-out',
            once: true,
            offset: 50
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const headerHeight = document.getElementById('main-header').offsetHeight;
                    const targetPosition = target.offsetTop - headerHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Header scroll effect
        const header = document.getElementById('main-header');
        let lastScrollTop = 0;

        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > 100) {
                header.classList.add('bg-white/95', 'backdrop-blur-md');
                header.classList.remove('bg-white');
            } else {
                header.classList.remove('bg-white/95', 'backdrop-blur-md');
                header.classList.add('bg-white');
            }

            // Hide/show header on scroll
            if (scrollTop > lastScrollTop && scrollTop > 200) {
                header.style.transform = 'translateY(-100%)';
            } else {
                header.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop;
        });
    </script>
</body>
</html>
