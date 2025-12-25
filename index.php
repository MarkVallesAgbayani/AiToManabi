<?php
// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}

// Include performance monitoring
require_once 'config/database.php';
require_once 'dashboard/performance_monitoring_functions.php';
require_once 'dashboard/system_uptime_tracker.php';

// Manual performance logging for index page
if (defined('ENABLE_PERFORMANCE_MONITORING') && ENABLE_PERFORMANCE_MONITORING === true) {
    $start_time = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    
    // Register shutdown function to log performance
    register_shutdown_function(function() use ($start_time) {
        try {
            // Check if database connection exists
            if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
                error_log("Performance logging: Database connection not available");
                return;
            }
            
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            $status = $duration <= 3.0 ? 'fast' : ($duration <= 10.0 ? 'slow' : 'timeout');
            
            $sql = "
                INSERT INTO page_performance_log (
                    page_name, action_name, full_url, start_time, end_time, 
                    load_duration, status, user_id, session_id, ip_address, 
                    user_agent, device_type, browser, os
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $GLOBALS['pdo']->prepare($sql);
            $result = $stmt->execute([
                'Home Page',
                'Page Load',
                $_SERVER['REQUEST_URI'] ?? '',
                date('Y-m-d H:i:s', (int)$start_time),
                date('Y-m-d H:i:s', (int)$end_time),
                round($duration, 3),
                $status,
                $_SESSION['user_id'] ?? null,
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                'Desktop',
                'Chrome', 
                'Windows'
            ]);
            
            if (!$result) {
                error_log("Performance logging: Insert failed");
            }
            
        } catch (Exception $e) {
            error_log("Home page performance logging failed: " . $e->getMessage());
        }
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AiToManabi - Language Learning Platform</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&display=swap" rel="stylesheet">
  <!-- AOS Library -->
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <style>
    .font-jp { font-family: 'Noto Sans JP', sans-serif; }
    .font-comfortaa { font-family: 'Comfortaa', sans-serif; }
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

    /* Parallax optimizations */
    @media (max-width: 768px) {
    .parallax-bg {
      background-attachment: scroll !important;
    }
  }
  
  /* Alpine.js x-cloak */
  [x-cloak] { 
    display: none !important; 
  }
    
    
    /* Prevent horizontal scrolling on mobile */
    body {
      overflow-x: hidden;
      max-width: 100vw;
    }
    
    /* Ensure all containers don't exceed viewport width */
    * {
      box-sizing: border-box;
    }
    
    /* Mobile-specific styles */
    @media (max-width: 768px) {
      html, body {
        overflow-x: hidden;
        max-width: 100vw;
      }
      
      .container, .max-w-7xl, .max-w-5xl, .max-w-4xl, .max-w-2xl {
        max-width: 100vw;
        overflow-x: hidden;
        padding-left: 1rem;
        padding-right: 1rem;
      }
      
      /* Ensure all sections don't overflow */
      section {
        overflow-x: hidden;
        max-width: 100vw;
      }
      
      /* Fix mobile menu visibility */
      #mobile-menu {
        background: white !important;
        border-top: 1px solid #e5e7eb;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 999;
      }
      
      #mobile-menu a {
        color: #ef4444 !important;
        font-weight: 600 !important;
        display: block !important;
        opacity: 1 !important;
      }
      
      #mobile-menu a:hover {
        background-color: #fef2f2 !important;
        color: #dc2626 !important;
      }
      
      /* Prevent any element from causing horizontal scroll */
      * {
        max-width: 100vw;
        box-sizing: border-box;
      }
      
      /* Fix hero section overflow */
      .relative.h-screen {
        overflow-x: hidden;
      }
      
      /* Fix image overflow */
      img {
        max-width: 100%;
        height: auto;
      }
    }
    
    /* Smooth scrolling for anchor links */
    html {
      scroll-behavior: smooth;
    }
    
    /* Enhanced text shadows for better readability */
    .text-shadow-lg {
      text-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
    }
    
    .text-shadow-xl {
      text-shadow: 0 8px 16px rgba(0, 0, 0, 0.6);
    }
    
    /* Header scroll behavior */
    #main-header {
      transition: transform 0.3s ease-in-out;
    }

  </style>
</head>
<body class="bg-gray-100 font-jp" x-data="{ mobileMenuOpen: false }">
  
<?php 
  // Check if announcement banner exists and has content
  $hasBanner = false;
  if (file_exists('components/announcement_banner.php')) {
    // Check if there's an active announcement
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM announcement_banner WHERE is_published = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasBanner = $result['count'] > 0;
    
    if ($hasBanner) {
      include 'components/announcement_banner.php';
    }
  }
?>
  <!-- HEADER -->
  <header id="main-header" class="fixed left-0 right-0 z-50 bg-white border-b border-gray-200 shadow-sm <?php echo $hasBanner ? 'top-8' : 'top-0'; ?>">
    <nav class="flex items-center justify-between p-4 lg:p-6 lg:px-8">
      <div class="flex lg:flex-1">
        <a href="#" class="-m-1.5 p-1.5">
          <span class="text-xl lg:text-2xl font-comfortaa text-red-500 font-bold">AiToManabi</span>
        </a>
      </div>
      
      <!-- Desktop Navigation -->
      <div class="hidden lg:flex lg:gap-x-8">
        <a href="dashboard/Landing Page Menu/PHP/courses.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">Courses</a>
        <a href="#pricing" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">Price</a>
        <a href="dashboard/Landing Page Menu/PHP/explore-features.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">Features</a>
        <a href="dashboard/Landing Page Menu/PHP/learnmore.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">About</a>
      </div>
      
      <!-- Desktop Auth Buttons -->
      <div class="hidden lg:flex lg:flex-1 lg:justify-end">
        <div class="flex items-center space-x-4">
          <a href="dashboard/signup.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 px-4 py-2 rounded-lg hover:bg-red-50">Register</a>
          <a href="dashboard/login.php" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-lg hover:bg-red-700 transform hover:scale-105 transition-all duration-300 text-sm">Login</a>
        </div>
      </div>

      <!-- Mobile Menu Button -->
      <div class="lg:hidden">
        <button @click="mobileMenuOpen = !mobileMenuOpen" 
                class="inline-flex items-center justify-center p-2 rounded-md text-red-500 hover:text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-300" 
                aria-expanded="false">
          <span class="sr-only">Open main menu</span>
          <!-- Hamburger icon -->
          <svg x-cloak x-show="!mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
          <!-- Close icon -->
          <svg x-cloak x-show="mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </nav>

    <!-- Mobile Navigation Menu -->
    <div x-cloak x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="lg:hidden bg-white border-t border-gray-200 shadow-lg">
      <div class="px-2 pt-2 pb-3 space-y-1">
        <!-- Navigation Links -->
        <a href="dashboard/Landing Page Menu/PHP/courses.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-red-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-red-500 transition-colors duration-300">
          Courses
        </a>
        <a href="#pricing" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
          Price
        </a>
        <a href="dashboard/Landing Page Menu/PHP/explore-features.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
          Features
        </a>
        <a href="dashboard/Landing Page Menu/PHP/learnmore.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
          About
        </a>
        
        <!-- Divider -->
        <hr class="my-2 border-gray-200">
        
        <!-- Mobile Auth Buttons -->
        <a href="dashboard/signup.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
          Register
        </a>
        <a href="dashboard/login.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-white bg-red-600 hover:bg-red-700 border-l-4 border-red-600 transition-colors duration-300 text-center">
          Login
        </a>
      </div>
    </div>
  </header>

  <!-- PARALLAX HERO SECTION -->
  <section class="relative h-screen flex items-center justify-center overflow-hidden">
    <!-- Parallax Background -->
    <div class="absolute inset-0 bg-cover bg-center bg-no-repeat parallax-bg" 
         style="background-image: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1920&q=80'); 
                background-attachment: fixed;
                background-position: center center;">
    </div>
    
    <!-- Overlay for better text visibility -->
    <div class="absolute inset-0 bg-black bg-opacity-40"></div>
    
    <!-- Gradient overlay for enhanced depth -->
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-black/20 to-black/60"></div>
    
    <!-- Content -->
    <div class="relative z-10 text-center text-white px-4 sm:px-6 max-w-4xl mx-auto">
      <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold tracking-tight mb-4 sm:mb-6 drop-shadow-2xl leading-tight">
        Start your journey with 
        <span class="text-red-400 font-comfortaa block sm:inline">AiToManabi</span>
      </h1>
      <p class="text-base sm:text-lg md:text-xl lg:text-2xl text-gray-200 mb-6 sm:mb-8 drop-shadow-lg max-w-2xl mx-auto leading-relaxed">
        Discover the beauty of Japanese language through AI-powered learning
      </p>
      <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
        <a href="dashboard/signup.php" 
           class="w-full sm:w-auto px-6 sm:px-8 py-3 sm:py-4 bg-red-600 text-white font-semibold rounded-lg shadow-lg hover:bg-red-700 transform hover:scale-105 transition-all duration-300 text-base sm:text-lg min-h-[48px] flex items-center justify-center">
          Begin Your Journey
        </a>
        <a href="dashboard/Landing Page Menu/PHP/explore-features.php" 
           class="w-full sm:w-auto px-6 sm:px-8 py-3 sm:py-4 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-gray-900 transform hover:scale-105 transition-all duration-300 text-base sm:text-lg min-h-[48px] flex items-center justify-center">
          Explore Features
        </a>
      </div>
    </div>
    
    <!-- Scroll indicator -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
      <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
      </svg>
    </div>
  </section>
  
  <section class="relative isolate px-4 sm:px-6 pt-16 sm:pt-24 lg:pt-32 lg:px-8 bg-red-600">
  <div class="bg-red-600">
    <div class="mx-auto max-w-7xl py-12 sm:py-16 md:py-24 lg:py-32 px-4 sm:px-6 lg:px-8">
      <div class="relative isolate overflow-hidden bg-red-700 px-4 sm:px-6 pt-8 sm:pt-12 md:pt-16 shadow-2xl rounded-2xl sm:rounded-3xl md:px-8 lg:px-16 xl:px-24 lg:flex lg:gap-x-12 xl:gap-x-20 lg:pt-0">
        <svg viewBox="0 0 1024 1024" class="absolute top-1/2 left-1/2 -z-10 size-[64rem] -translate-y-1/2 [mask-image:radial-gradient(closest-side,white,transparent)] sm:left-full sm:-ml-80 lg:left-1/2 lg:ml-0 lg:-translate-x-1/2 lg:translate-y-0" aria-hidden="true">
          <circle cx="512" cy="512" r="512" fill="url(#759c1415-0410-454c-8f7c-9a820de03641)" fill-opacity="0.7" />
          <defs>
            <radialGradient id="759c1415-0410-454c-8f7c-9a820de03641">
              <stop stop-color="#7775D6" />
              <stop offset="1" stop-color="#E935C1" />
            </radialGradient>
          </defs>
        </svg>

        <div class="mx-auto max-w-md text-center lg:mx-0 lg:flex-auto lg:py-16 xl:py-32 lg:text-left" data-aos="fade-right" data-aos-delay="100">
          <h2 class="text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-balance text-white leading-tight" data-aos="fade-up" data-aos-delay="200">
            Start using <span class="text-red-300">AiToManabi</span> today.
          </h2>
          <p class="mt-4 sm:mt-6 text-base sm:text-lg leading-relaxed text-pretty text-red-100" data-aos="fade-up" data-aos-delay="300">
            Learn Japanese faster and smarter with AI-powered tools. AiToManabi personalizes your learning path, gives instant feedback, and helps you master vocabulary, grammar, and conversation with ease.
          </p>
          <div class="mt-6 sm:mt-8 lg:mt-10 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-x-6 lg:justify-start" data-aos="fade-up" data-aos-delay="400">
            <a href="dashboard/signup.php" class="w-full sm:w-auto rounded-md bg-white px-4 sm:px-3.5 py-3 sm:py-2.5 text-sm font-semibold text-gray-900 shadow-xs hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white min-h-[44px] flex items-center justify-center">Get started</a>
            <a href="dashboard/Landing Page Menu/PHP/learnmore.php" class="text-sm/6 font-semibold text-white hover:text-red-200 transition-colors duration-300">Learn more <span aria-hidden="true">→</span></a>
          </div>
        </div>

        <div class="relative mt-16 lg:mt-8 lg:flex-shrink-0 lg:flex lg:items-center" data-aos="fade-left" data-aos-delay="200">
          <img 
            src="./assets/images/robot.webp" 
            alt="AIToManabi Robot Assistant" 
            class="w-full max-w-md mx-auto rounded-xl shadow-lg ring-1 ring-white/10" 
            data-aos="zoom-in" data-aos-delay="300"
          >
        </div>
      </div>
    </div>
  </div>
</section>


  <section class="overflow-hidden bg-white pb-8 pt-12 sm:pb-12 sm:pt-16 lg:pb-[90px] lg:pt-[120px] dark:bg-dark">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="-mx-4 flex flex-wrap items-center justify-between">
        <div class="w-full px-4 lg:w-6/12 order-2 lg:order-1" data-aos="fade-right" data-aos-delay="100">
          <div class="-mx-2 sm:-mx-3 flex flex-col sm:flex-row items-center sm:-mx-4">
            <div class="w-full px-2 sm:px-3 md:px-4 xl:w-1/2">
              <div class="py-2 sm:py-3 md:py-4" data-aos="zoom-in" data-aos-delay="200">
                <img src="./assets/images/Whychooseus_img1.jpg" alt="Image 1"
                  class="w-full rounded-xl sm:rounded-2xl" />
              </div>
              <div class="py-2 sm:py-3 md:py-4" data-aos="zoom-in" data-aos-delay="300">
                <img src="./assets/images/ai tutor.webp" alt="AI Tutor"
                  class="w-full rounded-xl sm:rounded-2xl" />
              </div>
            </div>
            <div class="w-full px-2 sm:px-3 md:px-4 xl:w-1/2">
              <div class="relative z-10 my-2 sm:my-4" data-aos="zoom-in" data-aos-delay="400">
                <img src="./assets/images/happykid.webp" alt="Happy Kid Learning"
                  class="w-full rounded-xl sm:rounded-2xl" />
                <span class="absolute -bottom-3 sm:-bottom-7 -right-3 sm:-right-7 z-[-1] hidden sm:block">
                  <svg width="134" height="106" viewBox="0 0 134 106" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="1.66667" cy="104" r="1.66667" transform="rotate(-90 1.66667 104)" fill="#3056D3" />
                    <circle cx="16.3333" cy="104" r="1.66667" transform="rotate(-90 16.3333 104)" fill="#3056D3" />
                    <circle cx="31" cy="104" r="1.66667" transform="rotate(-90 31 104)" fill="#3056D3" />
                    <circle cx="45.6667" cy="104" r="1.66667" transform="rotate(-90 45.6667 104)" fill="#3056D3" />
                    <circle cx="60.3334" cy="104" r="1.66667" transform="rotate(-90 60.3334 104)" fill="#3056D3" />
                    <circle cx="88.6667" cy="104" r="1.66667" transform="rotate(-90 88.6667 104)" fill="#3056D3" />
                    <circle cx="117.667" cy="104" r="1.66667" transform="rotate(-90 117.667 104)" fill="#3056D3" />
                    <circle cx="74.6667" cy="104" r="1.66667" transform="rotate(-90 74.6667 104)" fill="#3056D3" />
                    <circle cx="103" cy="104" r="1.66667" transform="rotate(-90 103 104)" fill="#3056D3" />
                    <circle cx="132" cy="104" r="1.66667" transform="rotate(-90 132 104)" fill="#3056D3" />
                    <circle cx="1.66667" cy="89.3333" r="1.66667" transform="rotate(-90 1.66667 89.3333)"
                      fill="#3056D3" />
                    <circle cx="16.3333" cy="89.3333" r="1.66667" transform="rotate(-90 16.3333 89.3333)"
                      fill="#3056D3" />
                    <circle cx="31" cy="89.3333" r="1.66667" transform="rotate(-90 31 89.3333)" fill="#3056D3" />
                    <circle cx="45.6667" cy="89.3333" r="1.66667" transform="rotate(-90 45.6667 89.3333)"
                      fill="#3056D3" />
                    <circle cx="60.3333" cy="89.3338" r="1.66667" transform="rotate(-90 60.3333 89.3338)"
                      fill="#3056D3" />
                    <circle cx="88.6667" cy="89.3338" r="1.66667" transform="rotate(-90 88.6667 89.3338)"
                      fill="#3056D3" />
                    <circle cx="117.667" cy="89.3338" r="1.66667" transform="rotate(-90 117.667 89.3338)"
                      fill="#3056D3" />
                    <circle cx="74.6667" cy="89.3338" r="1.66667" transform="rotate(-90 74.6667 89.3338)"
                      fill="#3056D3" />
                    <circle cx="103" cy="89.3338" r="1.66667" transform="rotate(-90 103 89.3338)" fill="#3056D3" />
                    <circle cx="132" cy="89.3338" r="1.66667" transform="rotate(-90 132 89.3338)" fill="#3056D3" />
                    <circle cx="1.66667" cy="74.6673" r="1.66667" transform="rotate(-90 1.66667 74.6673)"
                      fill="#3056D3" />
                    <circle cx="1.66667" cy="31.0003" r="1.66667" transform="rotate(-90 1.66667 31.0003)"
                      fill="#3056D3" />
                    <circle cx="16.3333" cy="74.6668" r="1.66667" transform="rotate(-90 16.3333 74.6668)"
                      fill="#3056D3" />
                    <circle cx="16.3333" cy="31.0003" r="1.66667" transform="rotate(-90 16.3333 31.0003)"
                      fill="#3056D3" />
                    <circle cx="31" cy="74.6668" r="1.66667" transform="rotate(-90 31 74.6668)" fill="#3056D3" />
                    <circle cx="31" cy="31.0003" r="1.66667" transform="rotate(-90 31 31.0003)" fill="#3056D3" />
                    <circle cx="45.6667" cy="74.6668" r="1.66667" transform="rotate(-90 45.6667 74.6668)"
                      fill="#3056D3" />
                    <circle cx="45.6667" cy="31.0003" r="1.66667" transform="rotate(-90 45.6667 31.0003)"
                      fill="#3056D3" />
                    <circle cx="60.3333" cy="74.6668" r="1.66667" transform="rotate(-90 60.3333 74.6668)"
                      fill="#3056D3" />
                    <circle cx="60.3333" cy="30.9998" r="1.66667" transform="rotate(-90 60.3333 30.9998)"
                      fill="#3056D3" />
                    <circle cx="88.6667" cy="74.6668" r="1.66667" transform="rotate(-90 88.6667 74.6668)"
                      fill="#3056D3" />
                    <circle cx="88.6667" cy="30.9998" r="1.66667" transform="rotate(-90 88.6667 30.9998)"
                      fill="#3056D3" />
                    <circle cx="117.667" cy="74.6668" r="1.66667" transform="rotate(-90 117.667 74.6668)"
                      fill="#3056D3" />
                    <circle cx="117.667" cy="30.9998" r="1.66667" transform="rotate(-90 117.667 30.9998)"
                      fill="#3056D3" />
                    <circle cx="74.6667" cy="74.6668" r="1.66667" transform="rotate(-90 74.6667 74.6668)"
                      fill="#3056D3" />
                    <circle cx="74.6667" cy="30.9998" r="1.66667" transform="rotate(-90 74.6667 30.9998)"
                      fill="#3056D3" />
                    <circle cx="103" cy="74.6668" r="1.66667" transform="rotate(-90 103 74.6668)" fill="#3056D3" />
                    <circle cx="103" cy="30.9998" r="1.66667" transform="rotate(-90 103 30.9998)" fill="#3056D3" />
                    <circle cx="132" cy="74.6668" r="1.66667" transform="rotate(-90 132 74.6668)" fill="#3056D3" />
                    <circle cx="132" cy="30.9998" r="1.66667" transform="rotate(-90 132 30.9998)" fill="#3056D3" />
                    <circle cx="1.66667" cy="60.0003" r="1.66667" transform="rotate(-90 1.66667 60.0003)"
                      fill="#3056D3" />
                    <circle cx="1.66667" cy="16.3333" r="1.66667" transform="rotate(-90 1.66667 16.3333)"
                      fill="#3056D3" />
                    <circle cx="16.3333" cy="60.0003" r="1.66667" transform="rotate(-90 16.3333 60.0003)"
                      fill="#3056D3" />
                    <circle cx="16.3333" cy="16.3333" r="1.66667" transform="rotate(-90 16.3333 16.3333)"
                      fill="#3056D3" />
                    <circle cx="31" cy="60.0003" r="1.66667" transform="rotate(-90 31 60.0003)" fill="#3056D3" />
                    <circle cx="31" cy="16.3333" r="1.66667" transform="rotate(-90 31 16.3333)" fill="#3056D3" />
                    <circle cx="45.6667" cy="60.0003" r="1.66667" transform="rotate(-90 45.6667 60.0003)"
                      fill="#3056D3" />
                    <circle cx="45.6667" cy="16.3333" r="1.66667" transform="rotate(-90 45.6667 16.3333)"
                      fill="#3056D3" />
                    <circle cx="60.3333" cy="60.0003" r="1.66667" transform="rotate(-90 60.3333 60.0003)"
                      fill="#3056D3" />
                    <circle cx="60.3333" cy="16.3333" r="1.66667" transform="rotate(-90 60.3333 16.3333)"
                      fill="#3056D3" />
                    <circle cx="88.6667" cy="60.0003" r="1.66667" transform="rotate(-90 88.6667 60.0003)"
                      fill="#3056D3" />
                    <circle cx="88.6667" cy="16.3333" r="1.66667" transform="rotate(-90 88.6667 16.3333)"
                      fill="#3056D3" />
                    <circle cx="117.667" cy="60.0003" r="1.66667" transform="rotate(-90 117.667 60.0003)"
                      fill="#3056D3" />
                    <circle cx="117.667" cy="16.3333" r="1.66667" transform="rotate(-90 117.667 16.3333)"
                      fill="#3056D3" />
                    <circle cx="74.6667" cy="60.0003" r="1.66667" transform="rotate(-90 74.6667 60.0003)"
                      fill="#3056D3" />
                    <circle cx="74.6667" cy="16.3333" r="1.66667" transform="rotate(-90 74.6667 16.3333)"
                      fill="#3056D3" />
                    <circle cx="103" cy="60.0003" r="1.66667" transform="rotate(-90 103 60.0003)" fill="#3056D3" />
                    <circle cx="103" cy="16.3333" r="1.66667" transform="rotate(-90 103 16.3333)" fill="#3056D3" />
                    <circle cx="132" cy="60.0003" r="1.66667" transform="rotate(-90 132 60.0003)" fill="#3056D3" />
                    <circle cx="132" cy="16.3333" r="1.66667" transform="rotate(-90 132 16.3333)" fill="#3056D3" />
                    <circle cx="1.66667" cy="45.3333" r="1.66667" transform="rotate(-90 1.66667 45.3333)"
                      fill="#3056D3" />
                    <circle cx="1.66667" cy="1.66683" r="1.66667" transform="rotate(-90 1.66667 1.66683)"
                      fill="#3056D3" />
                    <circle cx="16.3333" cy="45.3333" r="1.66667" transform="rotate(-90 16.3333 45.3333)"
                      fill="#3056D3" />
                    <circle cx="16.3333" cy="1.66683" r="1.66667" transform="rotate(-90 16.3333 1.66683)"
                      fill="#3056D3" />
                    <circle cx="31" cy="45.3333" r="1.66667" transform="rotate(-90 31 45.3333)" fill="#3056D3" />
                    <circle cx="31" cy="1.66683" r="1.66667" transform="rotate(-90 31 1.66683)" fill="#3056D3" />
                    <circle cx="45.6667" cy="45.3333" r="1.66667" transform="rotate(-90 45.6667 45.3333)"
                      fill="#3056D3" />
                    <circle cx="45.6667" cy="1.66683" r="1.66667" transform="rotate(-90 45.6667 1.66683)"
                      fill="#3056D3" />
                    <circle cx="60.3333" cy="45.3338" r="1.66667" transform="rotate(-90 60.3333 45.3338)"
                      fill="#3056D3" />
                    <circle cx="60.3333" cy="1.66683" r="1.66667" transform="rotate(-90 60.3333 1.66683)"
                      fill="#3056D3" />
                    <circle cx="88.6667" cy="45.3338" r="1.66667" transform="rotate(-90 88.6667 45.3338)"
                      fill="#3056D3" />
                    <circle cx="88.6667" cy="1.66683" r="1.66667" transform="rotate(-90 88.6667 1.66683)"
                      fill="#3056D3" />
                    <circle cx="117.667" cy="45.3338" r="1.66667" transform="rotate(-90 117.667 45.3338)"
                      fill="#3056D3" />
                    <circle cx="117.667" cy="1.66683" r="1.66667" transform="rotate(-90 117.667 1.66683)"
                      fill="#3056D3" />
                    <circle cx="74.6667" cy="45.3338" r="1.66667" transform="rotate(-90 74.6667 45.3338)"
                      fill="#3056D3" />
                    <circle cx="74.6667" cy="1.66683" r="1.66667" transform="rotate(-90 74.6667 1.66683)"
                      fill="#3056D3" />
                    <circle cx="103" cy="45.3338" r="1.66667" transform="rotate(-90 103 45.3338)" fill="#3056D3" />
                    <circle cx="103" cy="1.66683" r="1.66667" transform="rotate(-90 103 1.66683)" fill="#3056D3" />
                    <circle cx="132" cy="45.3338" r="1.66667" transform="rotate(-90 132 45.3338)" fill="#3056D3" />
                    <circle cx="132" cy="1.66683" r="1.66667" transform="rotate(-90 132 1.66683)" fill="#3056D3" />
                  </svg>
                </span>
              </div>
            </div>
    </div>
        </div>
        <div class="w-full px-4 lg:w-1/2 xl:w-5/12 order-1 lg:order-2" data-aos="fade-left" data-aos-delay="100">
          <div class="mt-6 lg:mt-0 text-center lg:text-left">
            <span class="mb-3 sm:mb-4 block text-base sm:text-lg font-semibold text-primary text-red-600" data-aos="fade-up" data-aos-delay="200">
              Why Choose Us
            </span>
            <h2 class="mb-4 sm:mb-5 text-2xl sm:text-3xl lg:text-4xl font-bold text-dark dark:text-white leading-tight" data-aos="fade-up" data-aos-delay="300">
              Make your language learning more effective.
            </h2>
            <p class="mb-4 sm:mb-5 text-sm sm:text-base text-body-color dark:text-dark-6 leading-relaxed" data-aos="fade-up" data-aos-delay="400">
              AiToManabi is a platform that uses AI to help you learn languages.
              It is a long established fact that a reader will be distracted
              by the readable content of a page when looking at its layout.
              The point of using AIToManabi is that it has a more-or-less.
            </p>
            <p class="mb-6 sm:mb-8 text-sm sm:text-base text-body-color dark:text-dark-6 leading-relaxed" data-aos="fade-up" data-aos-delay="500">
              A revolutionary approach to language learning that combines cutting-edge
              AI technology with proven educational methods. Our platform adapts to your learning style, 
              provides instant feedback, and creates a personalized curriculum just for you. 
              Start your journey to Japanese fluency today with AiToManabi.
            </p>
            <a href="dashboard/signup.php"
              class="inline-flex items-center justify-center rounded-md border border-transparent bg-primary px-6 sm:px-7 py-3 text-center text-sm sm:text-base font-medium text-white hover:bg-primary/90 min-h-[44px] transition-all duration-300"
              data-aos="zoom-in" data-aos-delay="600">
              Get Started
            </a>
      </div>
        </div>
      </div>
    </div>
    
  </section>


  <section class="py-12 sm:py-16 lg:py-24 relative bg-red-600 text-white">
  <div class="w-full max-w-7xl px-4 sm:px-6 lg:px-8 mx-auto">
    <div class="w-full justify-start items-center gap-8 lg:gap-12 grid lg:grid-cols-2 grid-cols-1">
      <!-- Left images -->
      <div class="w-full justify-center items-start gap-4 sm:gap-6 grid sm:grid-cols-2 grid-cols-1 lg:order-first order-last" data-aos="fade-right" data-aos-delay="100">
        <div class="pt-12 sm:pt-16 lg:pt-24 lg:justify-center sm:justify-end justify-start items-start gap-2.5 flex" data-aos="zoom-in" data-aos-delay="200">
          <img class="rounded-lg sm:rounded-xl object-cover w-full" src="./assets/images/robotconversation.webp" alt="Robot Conversation" />
        </div>
        <img class="sm:ml-0 ml-auto rounded-lg sm:rounded-xl object-cover w-full" src="./assets/images/robotchat.webp" alt="Robot Chat" data-aos="zoom-in" data-aos-delay="300" />
      </div>

      <!-- Right content -->
      <div class="w-full flex-col justify-center lg:items-start items-center gap-6 sm:gap-8 lg:gap-10 inline-flex" data-aos="fade-left" data-aos-delay="100">
        <div class="w-full flex-col justify-center items-start gap-6 sm:gap-8 flex">
          <!-- Heading and paragraph -->
          <div class="w-full flex-col justify-start lg:items-start items-center gap-3 flex">
            <h2 class="text-white text-2xl sm:text-3xl lg:text-4xl font-bold font-manrope leading-tight lg:text-start text-center" data-aos="fade-up" data-aos-delay="200">
              Learn Smarter with AiToManabi
            </h2>
            <p class="text-red-100 text-sm sm:text-base font-normal leading-relaxed lg:text-start text-center" data-aos="fade-up" data-aos-delay="300">
              AiToManabi uses advanced AI technology to personalize your Japanese learning journey — from grammar and vocabulary to quizzes and real conversations.
            </p>
            <!-- Modern checklist -->
            <ul class="text-white text-sm sm:text-base font-semibold flex flex-col gap-2 sm:gap-3 mt-4 w-full">
              <li class="flex items-start gap-3" data-aos="fade-up" data-aos-delay="400">
                <svg class="w-5 h-5 text-white bg-green-600 rounded-full p-0.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span class="leading-relaxed">Explore our collection of Modules, Lessons, and Quizzes</span>
              </li>
              <li class="flex items-start gap-3" data-aos="fade-up" data-aos-delay="500">
                <svg class="w-5 h-5 text-white bg-green-600 rounded-full p-0.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span class="leading-relaxed">Adaptive learning powered by AI</span>
              </li>
              <li class="flex items-start gap-3" data-aos="fade-up" data-aos-delay="600">
                <svg class="w-5 h-5 text-white bg-green-600 rounded-full p-0.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span class="leading-relaxed">Track progress with smart feedback</span>
              </li>
            </ul>
          </div>

        <!-- Call to action -->
        <a href="dashboard/signup.php"
          class="w-full sm:w-auto px-6 sm:px-4 py-3 sm:py-2.5 bg-white text-red-600 hover:bg-gray-100 transition-all duration-300 rounded-lg text-sm font-semibold text-center min-h-[44px] flex items-center justify-center"
          data-aos="zoom-in" data-aos-delay="700">
          Start Learning Now
        </a>
      </div>
    </div>
  </div>
</section>


<section class="bg-white py-12 sm:py-16 lg:py-24 xl:py-32">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl lg:text-center" data-aos="fade-up" data-aos-delay="100">
      <h2 class="text-sm sm:text-base font-semibold text-red-600" data-aos="fade-up" data-aos-delay="200">Smarter Learning with AiToManabi</h2>
      <p class="mt-2 text-2xl sm:text-3xl lg:text-4xl xl:text-5xl font-bold tracking-tight text-gray-900 leading-tight" data-aos="fade-up" data-aos-delay="300">Everything you need to <span class="text-red-600">master Japanese</span></p>
      <p class="mt-4 sm:mt-6 text-base sm:text-lg text-gray-600 leading-relaxed" data-aos="fade-up" data-aos-delay="400">From AI-personalized lessons to progress tracking and smart revision tools — AiToManabi gives you the power to learn faster, smarter, and with more focus.</p>
    </div>

    <div class="mx-auto mt-12 sm:mt-16 max-w-2xl lg:mt-20 xl:mt-24 lg:max-w-4xl">
      <dl class="grid max-w-xl grid-cols-1 gap-x-6 sm:gap-x-8 gap-y-8 sm:gap-y-12 lg:max-w-none lg:grid-cols-2 lg:gap-y-16">
        
        <div class="relative pl-16" data-aos="fade-right" data-aos-delay="100">
          <dt class="text-base font-semibold text-gray-900">
            <div class="absolute top-0 left-0 flex size-10 items-center justify-center rounded-lg bg-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
            </svg>
            </div>
            AI-Powered Learning Paths
          </dt>
          <dd class="mt-2 text-base text-gray-600">Get custom recommendations and dynamic modules based on your strengths, pace, and progress.</dd>
        </div>

        <div class="relative pl-16" data-aos="fade-left" data-aos-delay="100">
          <dt class="text-base font-semibold text-gray-900">
            <div class="absolute top-0 left-0 flex size-10 items-center justify-center rounded-lg bg-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z" />
            </svg>
            </div>
            Instant Feedback
          </dt>
          <dd class="mt-2 text-base text-gray-600">Learn from mistakes in real-time with AI feedback on grammar, vocabulary, and sentence structure.</dd>
        </div>

        <div class="relative pl-16" data-aos="fade-right" data-aos-delay="100">
          <dt class="text-base font-semibold text-gray-900">
            <div class="absolute top-0 left-0 flex size-10 items-center justify-center rounded-lg bg-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
            </div>
            Smart Vocabulary Drills
          </dt>
          <dd class="mt-2 text-base text-gray-600">Master words and phrases with spaced repetition and intelligent review cycles tailored to you.</dd>
        </div>

        <div class="relative pl-16" data-aos="fade-left" data-aos-delay="100">
          <dt class="text-base font-semibold text-gray-900">
            <div class="absolute top-0 left-0 flex size-10 items-center justify-center rounded-lg bg-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
            </svg>
            </div>
            Conversation Practice
          </dt>
          <dd class="mt-2 text-base text-gray-600">Practice real-world dialogues with interactive conversation simulations and speaking tasks.</dd>
        </div>

      </dl>
    </div>
  </div>
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="#dc2626" fill-opacity="1" d="M0,288L26.7,256C53.3,224,107,160,160,154.7C213.3,149,267,203,320,208C373.3,213,427,171,480,170.7C533.3,171,587,213,640,245.3C693.3,277,747,299,800,261.3C853.3,224,907,128,960,74.7C1013.3,21,1067,11,1120,37.3C1173.3,64,1227,128,1280,149.3C1333.3,171,1387,149,1413,138.7L1440,128L1440,320L1413.3,320C1386.7,320,1333,320,1280,320C1226.7,320,1173,320,1120,320C1066.7,320,1013,320,960,320C906.7,320,853,320,800,320C746.7,320,693,320,640,320C586.7,320,533,320,480,320C426.7,320,373,320,320,320C266.7,320,213,320,160,320C106.7,320,53,320,27,320L0,320Z"></path></svg> 
</section>

<section id="pricing" class="bg-white py-12 sm:py-16 lg:py-24 xl:py-32">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="text-center max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">
      <h2 class="text-sm sm:text-base font-semibold text-red-600" data-aos="fade-up" data-aos-delay="200">Flexible Learning</h2>
      <p class="mt-2 text-2xl sm:text-3xl lg:text-4xl xl:text-5xl font-bold text-gray-900 leading-tight" data-aos="fade-up" data-aos-delay="300">Get Started for Free — Unlock More When You're Ready</p>
      <p class="mt-4 sm:mt-6 text-base sm:text-lg text-gray-600 leading-relaxed" data-aos="fade-up" data-aos-delay="400">Experience Japanese learning with no pressure. Begin with a free module, and choose what you want to explore next — at your own pace.</p>
    </div>

    <div class="mt-12 sm:mt-16 grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 lg:gap-10 max-w-4xl mx-auto">

      <!-- Starter Access 3D Card -->
      <div class="w-full h-full bg-gradient-to-br from-red-400 to-red-700 rounded-[16px] sm:rounded-[20px] transition-all duration-300 hover:shadow-[0px_0px_30px_1px_rgba(255,90,90,0.3)]" data-aos="flip-up" data-aos-delay="100">
        <div class="w-full h-full bg-white rounded-[16px] sm:rounded-[20px] hover:scale-[0.98] transition-all duration-200 p-6 sm:p-8 border border-gray-100">
          <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Starter Access</h3>
          <p class="mt-3 sm:mt-4 text-sm sm:text-base text-gray-600 leading-relaxed">Perfect for newcomers — instantly access your first module for free and start your journey today.</p>
          <ul class="mt-4 sm:mt-6 space-y-3 sm:space-y-4 text-xs sm:text-sm text-gray-700">
            <li class="flex items-start space-x-3 sm:space-x-4">
              <!-- 3D Checkbox -->
              <label class="relative cursor-pointer w-[24px] h-[24px] sm:w-[30px] sm:h-[30px] rounded-full bg-white shadow-[inset_2px_2px_5px_#d1d5db,inset_-2px_-2px_5px_#ffffff] flex items-center justify-center transition hover:scale-105 flex-shrink-0 mt-0.5">
                <input type="checkbox" class="peer hidden" checked />
                <span class="absolute inset-0 rounded-full peer-checked:shadow-[inset_2px_2px_5px_#b91c1c,inset_-2px_-2px_5px_#f87171] transition-all duration-300"></span>
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-red-600 opacity-0 peer-checked:opacity-100 transition duration-300 z-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </label>
              <span class="leading-relaxed">Access to 1 introductory module</span>
            </li>
            <li class="flex items-start space-x-3 sm:space-x-4">
            <label class="relative cursor-pointer w-[24px] h-[24px] sm:w-[30px] sm:h-[30px] rounded-full bg-white shadow-[inset_2px_2px_5px_#d1d5db,inset_-2px_-2px_5px_#ffffff] flex items-center justify-center transition hover:scale-105 flex-shrink-0 mt-0.5">
                <input type="checkbox" class="peer hidden" checked />
                <span class="absolute inset-0 rounded-full peer-checked:shadow-[inset_2px_2px_5px_#b91c1c,inset_-2px_-2px_5px_#f87171] transition-all duration-300"></span>
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-red-600 opacity-0 peer-checked:opacity-100 transition duration-300 z-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </label>
              <span class="leading-relaxed">Learn essential vocabulary and grammar</span>
            </li>
            <li class="flex items-start space-x-3 sm:space-x-4">
            <label class="relative cursor-pointer w-[24px] h-[24px] sm:w-[30px] sm:h-[30px] rounded-full bg-white shadow-[inset_2px_2px_5px_#d1d5db,inset_-2px_-2px_5px_#ffffff] flex items-center justify-center transition hover:scale-105 flex-shrink-0 mt-0.5">
                <input type="checkbox" class="peer hidden" checked />
                <span class="absolute inset-0 rounded-full peer-checked:shadow-[inset_2px_2px_5px_#b91c1c,inset_-2px_-2px_5px_#f87171] transition-all duration-300"></span>
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-red-600 opacity-0 peer-checked:opacity-100 transition duration-300 z-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </label>
              <span class="leading-relaxed">Interactive exercises to get you started</span>
            </li>
          </ul>
          <a href="dashboard/signup.php" class="mt-4 sm:mt-6 inline-block bg-red-600 text-white font-medium px-4 sm:px-5 py-2.5 sm:py-2 rounded-md hover:bg-red-700 transition min-h-[44px] flex items-center justify-center text-sm sm:text-base">Start Free</a>
        </div>
      </div>

      <!-- Unlock More 3D Card -->
      <div class="w-full h-full bg-gradient-to-br from-red-400 to-red-700 rounded-[16px] sm:rounded-[20px] transition-all duration-300 hover:shadow-[0px_0px_30px_1px_rgba(255,90,90,0.3)]" data-aos="flip-up" data-aos-delay="200">
        <div class="w-full h-full bg-white rounded-[16px] sm:rounded-[20px] hover:scale-[0.98] transition-all duration-200 p-6 sm:p-8 border border-gray-100">
          <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Unlock More</h3>
          <p class="mt-3 sm:mt-4 text-sm sm:text-base text-gray-600 leading-relaxed">Dive deeper into Japanese with individual modules focused on speaking, kanji, listening, and more — unlocked as you choose.</p>
          <ul class="mt-4 sm:mt-6 space-y-3 sm:space-y-4 text-xs sm:text-sm text-gray-700">
            <li class="flex items-start space-x-3 sm:space-x-4">
              <!-- 3D Checkbox -->
              <label class="relative cursor-pointer w-[24px] h-[24px] sm:w-[30px] sm:h-[30px] rounded-full bg-white shadow-[inset_2px_2px_5px_#d1d5db,inset_-2px_-2px_5px_#ffffff] flex items-center justify-center transition hover:scale-105 flex-shrink-0 mt-0.5">
                <input type="checkbox" class="peer hidden" checked />
                <span class="absolute inset-0 rounded-full peer-checked:shadow-[inset_2px_2px_5px_#b91c1c,inset_-2px_-2px_5px_#f87171] transition-all duration-300"></span>
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-red-600 opacity-0 peer-checked:opacity-100 transition duration-300 z-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </label>
              <span class="leading-relaxed">Choose from different themed modules</span>
            </li>
            <li class="flex items-start space-x-3 sm:space-x-4">
            <label class="relative cursor-pointer w-[24px] h-[24px] sm:w-[30px] sm:h-[30px] rounded-full bg-white shadow-[inset_2px_2px_5px_#d1d5db,inset_-2px_-2px_5px_#ffffff] flex items-center justify-center transition hover:scale-105 flex-shrink-0 mt-0.5">
                <input type="checkbox" class="peer hidden" checked />
                <span class="absolute inset-0 rounded-full peer-checked:shadow-[inset_2px_2px_5px_#b91c1c,inset_-2px_-2px_5px_#f87171] transition-all duration-300"></span>
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-red-600 opacity-0 peer-checked:opacity-100 transition duration-300 z-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </label>
              <span class="leading-relaxed">Practice real-life conversations</span>
            </li>
            <li class="flex items-start space-x-3 sm:space-x-4">
            <label class="relative cursor-pointer w-[24px] h-[24px] sm:w-[30px] sm:h-[30px] rounded-full bg-white shadow-[inset_2px_2px_5px_#d1d5db,inset_-2px_-2px_5px_#ffffff] flex items-center justify-center transition hover:scale-105 flex-shrink-0 mt-0.5">
                <input type="checkbox" class="peer hidden" checked />
                <span class="absolute inset-0 rounded-full peer-checked:shadow-[inset_2px_2px_5px_#b91c1c,inset_-2px_-2px_5px_#f87171] transition-all duration-300"></span>
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-red-600 opacity-0 peer-checked:opacity-100 transition duration-300 z-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </label>
              <span class="leading-relaxed">Flexible & affordable — only pay for what you need</span>
            </li>
          </ul>
          <a href="dashboard/Landing Page Menu/PHP/courses.php" class="mt-4 sm:mt-6 inline-block bg-red-600 text-white font-medium px-4 sm:px-5 py-2.5 sm:py-2 rounded-md hover:bg-red-700 transition min-h-[44px] flex items-center justify-center text-sm sm:text-base">Explore Modules</a>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="relative bg-red-600 py-12 sm:py-16 lg:py-24 xl:py-32 text-white overflow-hidden">
  <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(255,255,255,0.05),transparent_70%)] pointer-events-none"></div>
  
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-8 sm:mb-12 lg:mb-16" data-aos="fade-up" data-aos-delay="100">
      <h2 class="text-2xl sm:text-3xl lg:text-4xl xl:text-5xl font-extrabold tracking-tight drop-shadow-lg leading-tight" data-aos="fade-up" data-aos-delay="200">Frequently Asked Questions</h2>
      <p class="mt-3 sm:mt-4 text-base sm:text-lg lg:text-xl text-red-100 max-w-2xl mx-auto leading-relaxed" data-aos="fade-up" data-aos-delay="300">Everything you need to know about getting started and using our Japanese learning platform.</p>
    </div>

    <div class="space-y-4 sm:space-y-6 lg:space-y-8">
      <!-- FAQ Card -->
      <details class="group relative bg-white/10 backdrop-blur-lg rounded-xl sm:rounded-2xl px-4 sm:px-6 py-4 sm:py-5 shadow-[0_15px_30px_-5px_rgba(0,0,0,0.3)] hover:scale-[1.02] hover:shadow-[0_25px_50px_-10px_rgba(255,255,255,0.2)] transform transition-all duration-300 border border-white/10" data-aos="fade-in" data-aos-delay="100">
        <summary class="flex items-center justify-between text-base sm:text-lg font-semibold cursor-pointer text-white leading-relaxed">
          <span class="pr-4">Is the first module really free?</span>
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white/70 group-open:rotate-180 transform transition-transform duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </summary>
        <p class="mt-3 sm:mt-4 text-xs sm:text-sm text-red-100 leading-relaxed">Yes! Once you sign up, you instantly gain access to the first module at no cost, with no strings attached.</p>
      </details>

      <!-- FAQ Card -->
      <details class="group relative bg-white/10 backdrop-blur-lg rounded-xl sm:rounded-2xl px-4 sm:px-6 py-4 sm:py-5 shadow-[0_15px_30px_-5px_rgba(0,0,0,0.3)] hover:scale-[1.02] hover:shadow-[0_25px_50px_-10px_rgba(255,255,255,0.2)] transform transition-all duration-300 border border-white/10" data-aos="fade-in" data-aos-delay="200">
        <summary class="flex items-center justify-between text-base sm:text-lg font-semibold cursor-pointer text-white leading-relaxed">
          <span class="pr-4">Do I have to buy all modules at once?</span>
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white/70 group-open:rotate-180 transform transition-transform duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </summary>
        <p class="mt-3 sm:mt-4 text-xs sm:text-sm text-red-100 leading-relaxed">Nope! You can unlock individual modules as you go. Only pay for what you need, when you need it.</p>
      </details>

      <!-- FAQ Card -->
      <details class="group relative bg-white/10 backdrop-blur-lg rounded-xl sm:rounded-2xl px-4 sm:px-6 py-4 sm:py-5 shadow-[0_15px_30px_-5px_rgba(0,0,0,0.3)] hover:scale-[1.02] hover:shadow-[0_25px_50px_-10px_rgba(255,255,255,0.2)] transform transition-all duration-300 border border-white/10" data-aos="fade-in" data-aos-delay="300">
        <summary class="flex items-center justify-between text-base sm:text-lg font-semibold cursor-pointer text-white leading-relaxed">
          <span class="pr-4">Can I reset my progress?</span>
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white/70 group-open:rotate-180 transform transition-transform duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </summary>
        <p class="mt-3 sm:mt-4 text-xs sm:text-sm text-red-100 leading-relaxed">Yes, you can reset your progress from your dashboard at any time to start fresh.</p>
      </details>

      <!-- FAQ Card -->
      <details class="group relative bg-white/10 backdrop-blur-lg rounded-xl sm:rounded-2xl px-4 sm:px-6 py-4 sm:py-5 shadow-[0_15px_30px_-5px_rgba(0,0,0,0.3)] hover:scale-[1.02] hover:shadow-[0_25px_50px_-10px_rgba(255,255,255,0.2)] transform transition-all duration-300 border border-white/10" data-aos="fade-in" data-aos-delay="400">
        <summary class="flex items-center justify-between text-base sm:text-lg font-semibold cursor-pointer text-white leading-relaxed">
          <span class="pr-4">Is there a mobile version?</span>
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white/70 group-open:rotate-180 transform transition-transform duration-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </summary>
        <p class="mt-3 sm:mt-4 text-xs sm:text-sm text-red-100 leading-relaxed">Absolutely! Our platform is fully responsive and works beautifully on all devices.</p>
      </details>
    </div>
  </div>
</section>


</body>

<!-- Modal Container -->
<div id="newsletter-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4 bg-black bg-opacity-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all duration-300 scale-95 opacity-0" id="modal-content">
        <!-- Success State -->
        <div id="modal-success" class="hidden p-8 text-center">
            <!-- Icon -->
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <!-- Title -->
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Successfully Subscribed!</h3>
            <!-- Message -->
            <p class="text-gray-600 mb-6">Check your email for a welcome message from AiToManabi.</p>
            <!-- Button -->
            <button onclick="closeModal()" class="w-full px-6 py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition-all duration-300 transform hover:scale-105">
                Got it
            </button>
        </div>
        
        <!-- Error State -->
        <div id="modal-error" class="hidden p-8 text-center">
            <!-- Icon -->
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <!-- Title -->
            <h3 class="text-2xl font-bold text-gray-900 mb-2" id="error-title">Oops!</h3>
            <!-- Message -->
            <p class="text-gray-600 mb-6" id="error-message">Something went wrong. Please try again.</p>
            <!-- Button -->
            <button onclick="closeModal()" class="w-full px-6 py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition-all duration-300 transform hover:scale-105">
                Close
            </button>
        </div>
    </div>
</div>

<footer class="w-full bg-white text-black py-8 sm:py-12 lg:py-16 px-4 sm:px-6 lg:px-10">
    <div class="max-w-screen-xl mx-auto flex flex-col items-center text-center">
        <!-- Email Form Centered -->
        <div class="w-full max-w-2xl mb-8 sm:mb-10 lg:mb-12" data-aos="zoom-in-up" data-aos-delay="100">
            <h3 class="text-2xl sm:text-3xl font-bold mb-4 sm:mb-6 text-red-600" data-aos="fade-up" data-aos-delay="200">Stay in Touch</h3>
            
            <form action="newsletter_handler.php" method="POST" id="newsletter-form" class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 justify-center" data-aos="fade-up" data-aos-delay="300">
                <input type="email" name="email" placeholder="Your email address" required 
                    class="w-full sm:flex-1 px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-red-500 placeholder-gray-500 text-sm sm:text-base min-h-[44px]">
                <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition min-h-[44px] text-sm sm:text-base">
                    Send
                </button>
            </form>
        </div>
        
        <!-- Divider -->
        <div class="h-px bg-gray-200 w-full mb-6 sm:mb-8"></div>
        
        <!-- Links & Legal Bottom Row -->
        <div class="w-full flex flex-col sm:flex-row justify-between items-center text-xs sm:text-sm text-gray-600 gap-3 sm:gap-4" data-aos="fade-up" data-aos-delay="100">
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-6" data-aos="fade-up" data-aos-delay="200">
                <a href="dashboard/Landing Page Menu/PHP/terms.php" class="hover:text-black transition">Terms & Conditions</a>
                <a href="dashboard/Landing Page Menu/PHP/privacy.php" class="hover:text-black transition">Privacy Policy</a>
            </div>
            <p class="text-center sm:text-right" data-aos="fade-up" data-aos-delay="300">&copy; 2025 AiToManabi. All rights reserved.</p>
        </div>
    </div>
</footer>


<script>
  // Initialize AOS with re-animation support
  AOS.init({
    duration: 600,
    easing: 'ease-in-out',
    once: false, // Allows repeat on scroll
    mirror: true, // Animates on scroll up too
    offset: 50, // Reduced offset for better trigger timing
    delay: 0,
    anchorPlacement: 'top-bottom' // Ensures animations trigger properly
  });

  // Smart header scroll behavior
  let lastScrollY = 0;
  let ticking = false;

  function updateHeader() {
    const header = document.getElementById('main-header');
    const currentScrollY = window.scrollY;
    
    if (currentScrollY > lastScrollY && currentScrollY > 100) {
      // Scrolling down - hide header
      header.style.transform = 'translateY(-100%)';
    } else {
      // Scrolling up - show header
      header.style.transform = 'translateY(0)';
    }
    
    lastScrollY = currentScrollY;
    ticking = false;
  }

  function requestTick() {
    if (!ticking) {
      requestAnimationFrame(updateHeader);
      ticking = true;
    }
  }

  // Add scroll event listener
  window.addEventListener('scroll', requestTick);


    // Check URL parameters on page load
    window.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('success')) {
            const successType = urlParams.get('success');
            showModal('success');
            // Clean URL
            setTimeout(() => {
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 100);
        } else if (urlParams.has('error')) {
            const errorType = urlParams.get('error');
            let errorTitle = 'Oops!';
            let errorMessage = 'Something went wrong. Please try again.';
            
            switch(errorType) {
                case 'invalid_email':
                    errorTitle = 'Invalid Email';
                    errorMessage = 'Please enter a valid email address.';
                    break;
                case 'already_subscribed':
                    errorTitle = 'Already Subscribed';
                    errorMessage = 'This email is already on our mailing list!';
                    break;
                case 'subscription_failed':
                    errorTitle = 'Subscription Failed';
                    errorMessage = 'Unable to subscribe. Please try again later.';
                    break;
            }
            
            document.getElementById('error-title').textContent = errorTitle;
            document.getElementById('error-message').textContent = errorMessage;
            showModal('error');
            
            // Clean URL
            setTimeout(() => {
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 100);
        }
    });
    
    function showModal(type) {
        const modal = document.getElementById('newsletter-modal');
        const modalContent = document.getElementById('modal-content');
        const successDiv = document.getElementById('modal-success');
        const errorDiv = document.getElementById('modal-error');
        
        // Show correct content
        if (type === 'success') {
            successDiv.classList.remove('hidden');
            errorDiv.classList.add('hidden');
        } else {
            errorDiv.classList.remove('hidden');
            successDiv.classList.add('hidden');
        }
        
        // Show modal with animation
        modal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }
    
    function closeModal() {
        const modal = document.getElementById('newsletter-modal');
        const modalContent = document.getElementById('modal-content');
        
        // Hide with animation
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    // Close modal on background click
    document.getElementById('newsletter-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

</script>
</html>
