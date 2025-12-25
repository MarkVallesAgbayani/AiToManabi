<?php
// Start session and include database connection
session_start();
require_once '../../../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learn More - AiToManabi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&display=swap" rel="stylesheet">
    <!-- AOS Library -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <link rel="stylesheet" href="../css/learnmore.css">
    <style>
        .font-jp { font-family: 'Noto Sans JP', sans-serif; }
        .font-comfortaa { font-family: 'Comfortaa', sans-serif; }
        .text-shadow-lg { text-shadow: 0 4px 8px rgba(0, 0, 0, 0.5); }
        .text-shadow-xl { text-shadow: 0 8px 16px rgba(0, 0, 0, 0.6); }
        html { scroll-behavior: smooth; }
        
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
    </style>
</head>
<body class="bg-gray-100 font-jp min-h-screen" x-data="{ mobileMenuOpen: false }">
  <?php 
    // Check if announcement banner exists and has content
    $hasBanner = false;
    if (file_exists('../../../components/announcement_banner.php')) {
      // Check if there's an active announcement
      $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM announcement_banner WHERE is_published = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $hasBanner = $result['count'] > 0;
      
      if ($hasBanner) {
        include '../../../components/announcement_banner.php';
      }
    }
  ?>
  <!-- HEADER -->
  <header id="main-header" class="sticky top-0 z-40 bg-white border-b border-gray-200 transition-all duration-300 shadow-sm <?php echo $hasBanner ? 'top-8' : 'top-0'; ?>">
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
        <a href="courses.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
          Courses
        </a>
        <a href="../../../index.php#pricing" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
          Price
        </a>
        <a href="explore-features.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
          Features
        </a>
        <a href="learnmore.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-red-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-red-500 transition-colors duration-300">
          About
        </a>
        
        <!-- Divider -->
        <hr class="my-2 border-gray-200">
        
        <!-- Mobile Auth Buttons -->
        <a href="../../signup.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
          Register
        </a>
        <a href="../../login.php" 
           @click="mobileMenuOpen = false"
           class="block w-full text-left px-3 py-3 text-base font-semibold text-white bg-red-600 hover:bg-red-700 border-l-4 border-red-600 transition-colors duration-300 text-center">
          Login
        </a>
      </div>
    </div>
  </header>

  <!-- HERO SECTION WITH BACKGROUND IMAGE -->
  <section class="hero-section relative h-screen flex items-center justify-center overflow-hidden">
    <!-- Static Background -->
    <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" 
         style="background-image: url('../../../assets/images/learnmore.webp'); 
                background-position: center center;">
    </div>
    
    <!-- Very light overlay for text readability only -->
    <div class="absolute inset-0 bg-black bg-opacity-20"></div>
    
    <!-- Content -->
    <div class="relative z-10 text-center text-white px-6 max-w-4xl mx-auto">
      <h1 class="text-5xl md:text-7xl font-bold tracking-tight mb-6 drop-shadow-2xl font-comfortaa" data-aos="fade-up" data-aos-delay="100">
        Learn More About 
        <span class="text-red-400">AiToManabi</span>
      </h1>
      <p class="text-xl md:text-2xl text-gray-200 mb-8 max-w-3xl mx-auto leading-relaxed text-shadow-lg" data-aos="fade-up" data-aos-delay="200">
        Discover the revolutionary AI-powered platform that's transforming Japanese language education for students and teachers worldwide.
      </p>
      <div class="flex flex-col sm:flex-row items-center justify-center gap-4" data-aos="fade-up" data-aos-delay="300">
        <a href="../../signup.php" class="px-8 py-4 bg-red-600 text-white font-semibold rounded-lg shadow-lg hover:bg-red-700 transform hover:scale-105 transition-all duration-300 text-lg">
          Start Learning Today
        </a>
        <a href="#about" class="px-8 py-4 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-gray-900 transform hover:scale-105 transition-all duration-300 text-lg">
          Discover More
        </a>
      </div>
    </div>
  </section>

  <!-- About Section -->
  <section id="about" class="py-20 bg-white">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="text-center mb-16" data-aos="fade-up" data-aos-delay="100">
        <h2 class="text-5xl font-bold text-gray-900 mb-6 font-comfortaa">
          Why Choose <span class="text-red-600">AiToManabi</span>
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
          We combine cutting-edge AI technology with proven educational methods to create the most effective Japanese learning experience.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <!-- AI-Powered Learning -->
        <div class="feature-card group bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl hover:shadow-2xl transition-all duration-500 p-8 border border-white/20 hover:scale-105 hover:bg-white/90" data-aos="zoom-in" data-aos-delay="200">
          <div class="flex items-center justify-center w-24 h-24 bg-gradient-to-br from-red-100 to-red-200 rounded-3xl mb-8 mx-auto group-hover:scale-110 transition-transform duration-300">
            <i data-lucide="brain-circuit" class="w-12 h-12 text-red-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4 text-center">
            AI-Powered Learning
          </h3>
          <p class="text-gray-600 text-center leading-relaxed">
            Our advanced AI adapts to your learning style, providing personalized lessons and real-time feedback to accelerate your progress.
          </p>
        </div>

        <!-- Expert Curriculum -->
        <div class="feature-card group bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl hover:shadow-2xl transition-all duration-500 p-8 border border-white/20 hover:scale-105 hover:bg-white/90" data-aos="zoom-in" data-aos-delay="300">
          <div class="flex items-center justify-center w-24 h-24 bg-gradient-to-br from-red-100 to-red-200 rounded-3xl mb-8 mx-auto group-hover:scale-110 transition-transform duration-300">
            <i data-lucide="graduation-cap" class="w-12 h-12 text-red-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4 text-center">
            Expert Curriculum
          </h3>
          <p class="text-gray-600 text-center leading-relaxed">
            Developed by Japanese language experts and native speakers, our curriculum ensures authentic and comprehensive learning.
          </p>
        </div>

        <!-- Interactive Experience -->
        <div class="feature-card group bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl hover:shadow-2xl transition-all duration-500 p-8 border border-white/20 hover:scale-105 hover:bg-white/90" data-aos="zoom-in" data-aos-delay="400">
          <div class="flex items-center justify-center w-24 h-24 bg-gradient-to-br from-red-100 to-red-200 rounded-3xl mb-8 mx-auto group-hover:scale-110 transition-transform duration-300">
            <i data-lucide="gamepad-2" class="w-12 h-12 text-red-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4 text-center">
            Interactive Experience
          </h3>
          <p class="text-gray-600 text-center leading-relaxed">
            Engage with immersive quizzes, and text reading that make learning Japanese fun and memorable.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Statistics Section -->
  <section class="py-20 bg-red-600 text-white relative overflow-hidden">
    <div class="absolute inset-0 bg-black bg-opacity-20"></div>
    <div class="relative z-10 container mx-auto px-4">
       <div class="text-center mb-16" data-aos="fade-up" data-aos-delay="100">
         <h2 class="text-4xl md:text-5xl font-bold mb-6 text-white font-comfortaa">
           Powered by <span class="text-red-200">AI Technology</span>
         </h2>
         <p class="text-xl text-red-100 max-w-3xl mx-auto leading-relaxed">
           Experience the future of Japanese language learning with our advanced AI-powered platform.
         </p>
       </div>

       <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
         <div class="text-center" data-aos="fade-up" data-aos-delay="200">
           <div class="text-4xl md:text-5xl font-bold text-white mb-2">Always Available</div>
           <div class="text-red-200 text-lg">AI Tutoring</div>
         </div>
         <div class="text-center" data-aos="fade-up" data-aos-delay="300">
           <div class="text-4xl md:text-5xl font-bold text-white mb-2">AI</div>
           <div class="text-red-200 text-lg">Personalized Learning</div>
         </div>
         <div class="text-center" data-aos="fade-up" data-aos-delay="400">
           <div class="text-4xl md:text-5xl font-bold text-white mb-2">Real-time</div>
           <div class="text-red-200 text-lg">Progress Feedback</div>
         </div>
       </div>
    </div>
  </section>

  <!-- Features Overview -->
  <section class="py-20 bg-gray-50">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="text-center mb-16">
        <h2 class="text-5xl font-bold text-gray-900 mb-6 font-comfortaa">
          Comprehensive <span class="text-red-600">Learning Platform</span>
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
          Everything you need to master Japanese, from basic vocabulary to advanced conversation skills.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
        <div class="space-y-8">
          <div class="flex items-start space-x-4">
            <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
              <i data-lucide="book-open" class="w-6 h-6 text-red-600"></i>
            </div>
            <div>
              <h3 class="text-xl font-bold text-gray-900 mb-2">Structured Learning Path</h3>
              <p class="text-gray-600">Follow a carefully designed curriculum that takes you from beginner to advanced levels.</p>
            </div>
          </div>
          
          <div class="flex items-start space-x-4">
            <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
              <i data-lucide="mic" class="w-6 h-6 text-red-600"></i>
            </div>
            <div>
              <h3 class="text-xl font-bold text-gray-900 mb-2">Pronunciation Practice</h3>
              <p class="text-gray-600">Perfect your pronunciation with AI-powered speech recognition and feedback.</p>
            </div>
          </div>
          
           <div class="flex items-start space-x-4">
             <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
               <i data-lucide="users" class="w-6 h-6 text-red-600"></i>
             </div>
             <div>
               <h3 class="text-xl font-bold text-gray-900 mb-2">Progress Tracking</h3>
               <p class="text-gray-600">Monitor your learning journey with detailed analytics and personalized progress reports.</p>
             </div>
           </div>
        </div>

        <div class="relative">
          <div class="relative overflow-hidden rounded-3xl shadow-2xl">
            <img src="../../../assets/images/progress.jpg" alt="Happy Student Learning" class="w-full h-96 object-cover" style="display: block !important; max-width: 100%; height: auto; opacity: 1 !important; transform: none !important; transition: none !important; animation: none !important;">
            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Call to Action Section -->
  <section class="py-24 bg-red-600 text-white relative overflow-hidden">
    <div class="absolute inset-0 bg-black bg-opacity-20"></div>
    <div class="relative z-10 container mx-auto px-4 text-center">
      <div class="max-w-4xl mx-auto">
        <h3 class="text-4xl md:text-5xl font-bold mb-6 text-white font-comfortaa" data-aos="fade-up" data-aos-delay="200">
          Ready to Start Your <span class="text-red-200">Japanese Journey</span>?
        </h3>
        <p class="text-xl text-red-100 mb-8 max-w-3xl mx-auto leading-relaxed" data-aos="fade-up" data-aos-delay="300">
          Join other learners who have transformed their Japanese skills with AiToManabi.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4" data-aos="fade-up" data-aos-delay="400">
          <a href="../../signup.php" class="px-8 py-4 bg-white text-red-600 font-semibold rounded-lg shadow-lg hover:bg-gray-100 transform hover:scale-105 transition-all duration-300 text-lg">
            Get Started Free
          </a>
          <a href="explore-features.php" class="px-8 py-4 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-gray-900 transform hover:scale-105 transition-all duration-300 text-lg">
            Explore Features
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="w-full bg-white text-black py-16 px-6 sm:px-10">
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

  <script src="../JS/learnmore.js"></script>
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
  </script>
</body>
</html>
