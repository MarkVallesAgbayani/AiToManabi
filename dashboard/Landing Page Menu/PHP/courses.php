<?php
// Start session and include database connection
session_start();
require_once '../../../config/database.php';

// Fetch all published courses with category and teacher information
try {
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name as category_name, u.username as teacher_name,
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
               (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id) as total_sections
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.status = 'published'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $courses = [];
}

// Function to determine course level based on category or price
function getCourseLevel($category_name, $price) {
    if (stripos($category_name, 'beginner') !== false || stripos($category_name, 'N5') !== false) {
        return 'Beginner';
    } elseif (stripos($category_name, 'intermediate') !== false || stripos($category_name, 'N4') !== false || stripos($category_name, 'N3') !== false) {
        return 'Intermediate';
    } elseif (stripos($category_name, 'advanced') !== false || stripos($category_name, 'N2') !== false || stripos($category_name, 'N1') !== false || stripos($category_name, 'business') !== false) {
        return 'Advanced';
    } else {
        return $price > 50 ? 'Advanced' : ($price > 20 ? 'Intermediate' : 'Beginner');
    }
}

// Function to estimate duration based on sections count
function getCourseDuration($total_sections) {
    if ($total_sections <= 5) return "2 Weeks";
    elseif ($total_sections <= 10) return "4 Weeks";
    elseif ($total_sections <= 15) return "6 Weeks";
    else return "8+ Weeks";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiToManabi - LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300..700&display=swap" rel="stylesheet">
    <!-- AOS Library for animations -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <style>
        .font-jp { font-family: 'Noto Sans JP', sans-serif; }
        .font-comfortaa { font-family: 'Comfortaa', sans-serif; }
        
        /* Custom gradient backgrounds */
        .gradient-red { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .gradient-orange { background: linear-gradient(135deg, #f97316, #ea580c); }
        .gradient-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        
        /* Enhanced hover effects */
        .course-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }
        .course-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(239, 68, 68, 0.25);
        }
        
        /* Glassmorphism effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Price highlight animation */
        .price-highlight {
            position: relative;
            overflow: hidden;
        }
        .price-highlight::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .price-highlight:hover::before {
            left: 100%;
        }
        
        /* Enhanced button hover effects */
        .btn-purchase {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
        }
        .btn-purchase:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(239, 68, 68, 0.4);
        }
        .btn-purchase::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .btn-purchase:hover::before {
            left: 100%;
        }
        
        /* Level badges with gradients */
        .level-beginner { 
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .level-intermediate { 
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        .level-advanced { 
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Custom select styling */
        select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
        
        /* Header transition */
        #main-header {
            transition: transform 0.3s ease-in-out;
        }
        
        /* Card image overlay effect */
        .course-card img {
            transition: transform 0.5s ease;
        }
        .course-card:hover img {
            transform: scale(1.1);
        }
        
        /* Floating animation for badges */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        
        .level-beginner,
        .level-intermediate,
        .level-advanced {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Pulse effect for free courses */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        [data-course-type="free"] .bg-gradient-to-r {
            animation: pulse 2s ease-in-out infinite;
        }
        
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
        
        /* Enhanced focus states */
        .focus\:ring-red-500:focus {
            --tw-ring-color: rgba(239, 68, 68, 0.5);
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .course-card:hover {
                transform: translateY(-8px) scale(1.01);
            }
            
            .btn-purchase {
                font-size: 0.875rem;
                padding: 0.75rem 1.5rem;
            }
            
            /* Moderate course card sizes on mobile */
            .course-card {
                margin-bottom: 1rem;
            }
            
            .course-card .course-content {
                padding: 1rem;
            }
            
            .course-card h3 {
                font-size: 1.25rem;
                margin-bottom: 0.5rem;
            }
            
            .course-card p {
                font-size: 0.875rem;
                margin-bottom: 1rem;
                line-height: 1.4;
            }
            
            .course-card .course-meta {
                margin-bottom: 1rem;
                gap: 0.75rem;
            }
            
            .course-card .course-meta > div {
                font-size: 0.75rem;
            }
            
            .course-card .course-features {
                margin-bottom: 1rem;
                font-size: 0.75rem;
            }
            
            .course-card img {
                height: 12rem;
            }
            
            /* Moderate hero section on mobile */
            .hero-section h1 {
                font-size: 2.5rem;
                margin-bottom: 1rem;
            }
            
            .hero-section p {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }
            
            /* Moderate filter section on mobile */
            .filter-section {
                margin-bottom: 1.5rem;
            }
            
            .filter-section select,
            .filter-section input {
                font-size: 0.875rem;
                padding: 0.75rem;
                min-width: 140px;
            }
            
            /* Moderate section headers on mobile */
            .section-header h2 {
                font-size: 2rem;
                margin-bottom: 0.75rem;
            }
            
            .section-header p {
                font-size: 1rem;
            }
        }
        
        /* Loading state */
        .btn-purchase:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* No results animation */
        #noResults {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Course meta icons hover effect */
        .course-card svg {
            transition: all 0.3s ease;
        }
        .course-card:hover svg {
            transform: scale(1.1);
            filter: drop-shadow(0 4px 8px rgba(239, 68, 68, 0.3));
        }
        
        /* Enhanced text shadows for better readability */
        .text-shadow-lg {
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        /* Alpine.js x-cloak */
        [x-cloak] { 
            display: none !important; 
        }
    </style>
</head>

<body class="bg-gray-50 font-jp" x-data="{ mobileMenuOpen: false }">
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
    <!-- Header Navigation -->
    <header id="main-header" class="sticky top-0 z-40 bg-white border-b border-gray-200 transition-all duration-300 shadow-sm <?php echo $hasBanner ? 'top-8' : 'top-0'; ?>">
        <nav class="flex items-center justify-between p-6 lg:px-8">
            <div class="flex lg:flex-1">
                <a href="../../../index.php" class="-m-1.5 p-1.5">
                    <span class="text-2xl font-comfortaa text-red-500 font-bold">AiToManabi</span>
                </a>
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
            <div class="hidden lg:flex lg:gap-x-8">
                <a href="courses.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-full after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1">Courses</a>
                <a href="../../../index.php#pricing" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">Price</a>
                <a href="explore-features.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">Features</a>
                <a href="learnmore.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 relative after:content-[''] after:absolute after:w-0 after:h-0.5 after:bg-red-600 after:left-0 after:-bottom-1 after:transition-all after:duration-300 hover:after:w-full">About</a>
            </div>
            <div class="hidden lg:flex lg:flex-1 lg:justify-end">
                <div class="flex items-center space-x-4">
                    <a href="../../../dashboard/signup.php" class="text-sm font-semibold text-red-500 hover:text-red-600 transition-colors duration-300 px-4 py-2 rounded-lg hover:bg-red-50">Register</a>
                    <a href="../../../dashboard/login.php" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-lg hover:bg-red-700 transform hover:scale-105 transition-all duration-300 text-sm">Login</a>
                </div>
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
               class="block w-full text-left px-3 py-3 text-base font-semibold text-red-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-red-500 transition-colors duration-300">
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
               class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
              About
            </a>
            
            <!-- Divider -->
            <hr class="my-2 border-gray-200">
            
            <!-- Mobile Auth Buttons -->
            <a href="../../../dashboard/signup.php" 
               @click="mobileMenuOpen = false"
               class="block w-full text-left px-3 py-3 text-base font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 border-l-4 border-transparent hover:border-red-500 transition-colors duration-300">
              Register
            </a>
            <a href="../../../dashboard/login.php" 
               @click="mobileMenuOpen = false"
               class="block w-full text-left px-3 py-3 text-base font-semibold text-white bg-red-600 hover:bg-red-700 border-l-4 border-red-600 transition-colors duration-300 text-center">
              Login
            </a>
          </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section relative bg-gradient-to-br from-red-50 via-white to-orange-50 py-16">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="text-center" data-aos="fade-up">
                <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
                    Master <span class="text-red-500 font-comfortaa">Japanese</span> with Expert Guidance
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                    Discover our comprehensive Japanese language modules designed by expert instructors. From beginner to intermediate beginner levels.
                </p>
                
                <!-- Filter Section -->
                <div class="filter-section flex flex-col md:flex-row items-center justify-center gap-4 mb-8" data-aos="fade-up" data-aos-delay="200">
                    <!-- Course Type Filter -->
                    <div class="relative">
                        <select id="courseTypeFilter" class="appearance-none bg-white border border-gray-300 rounded-lg px-6 py-3 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 shadow-sm min-w-[180px]">
                            <option value="">All Modules</option>
                            <option value="free">Starter (Free)</option>
                            <option value="paid">Premium (Paid)</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Level Filter -->
                    <div class="relative">
                        <select id="levelFilter" class="appearance-none bg-white border border-gray-300 rounded-lg px-6 py-3 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 shadow-sm min-w-[180px]">
                            <option value="">All Levels</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="relative flex-1 max-w-md">
                        <input type="text" id="searchInput" placeholder="Search courses..." 
                               class="w-full bg-white border border-gray-300 rounded-lg px-6 py-3 pl-14 text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 shadow-sm">
                        <div class="absolute inset-y-0 left-0 flex items-center justify-center pl-4 text-gray-500">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <a href="#courses" class="inline-flex items-center px-8 py-4 bg-red-600 text-white font-semibold rounded-full shadow-lg hover:bg-red-700 transform hover:scale-105 transition-all duration-300">
                    Explore Modules
                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- Courses Section -->
    <section id="courses" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <!-- Section Header -->
            <div class="section-header text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-5xl font-bold text-gray-900 mb-4">
                    Our <span class="text-red-500">Learning Modules</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Choose from our carefully crafted modules designed to take you from beginner to fluent in Japanese.
                </p>
            </div>

            <!-- Course Grid -->
            <div id="courseGrid" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <?php if (empty($courses)): ?>
                    <!-- Sample Course Card for Demo -->
                    <div class="course-card bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 hover:shadow-2xl transition-all duration-500 group" 
                         data-aos="fade-up" data-course-type="paid" data-level="beginner">
                        <div class="relative">
                            <!-- Course Image -->
                            <div class="w-full h-64 bg-gradient-to-br from-red-100 to-orange-100 relative overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent z-10"></div>
                                <div class="flex items-center justify-center h-full z-20 relative">
                                    <span class="text-6xl">🇯🇵</span>
                                </div>
                                <!-- Price Badge -->
                                <div class="absolute top-4 left-4 z-30">
                                    <span class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg">
                                        ₱49.99
                                    </span>
                                </div>
                                <!-- Level Badge -->
                                <div class="absolute top-4 right-4 z-30">
                                    <span class="level-beginner text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg">
                                        Beginner
                                    </span>
                                </div>
                            </div>
                            
                                <!-- Course Content -->
                                <div class="course-content p-8">
                                    <!-- Course Title -->
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3 group-hover:text-red-600 transition-colors duration-300">
                                        Japanese for Beginners
                                    </h3>
                                    
                                    <!-- Course Description -->
                                    <p class="text-gray-600 mb-6 leading-relaxed line-clamp-3">
                                        Learn the basics of Japanese in 4 weeks with guided lessons and interactive exercises. Master hiragana, katakana, and essential vocabulary.
                                    </p>
                                    
                                    <!-- Course Meta -->
                                    <div class="course-meta flex flex-wrap gap-4 mb-6">
                                    <div class="flex items-center text-gray-600">
                                        <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="font-medium">4 Weeks</span>
                                    </div>
                                    <div class="flex items-center text-gray-600">
                                        <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        <span class="font-medium">50+ Students</span>
                                    </div>
                                    <div class="flex items-center text-gray-600">
                                        <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="font-medium">Certificate</span>
                                    </div>
                                </div>
                                
                                    <!-- Course Features -->
                                    <div class="course-features grid grid-cols-2 gap-2 mb-6 text-sm text-gray-600">
                                    <div class="flex items-center">
                                        <span class="text-green-500 mr-2">✓</span>
                                        Lifetime Access
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-green-500 mr-2">✓</span>
                                        Mobile Friendly
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-green-500 mr-2">✓</span>
                                        Expert Support
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-green-500 mr-2">✓</span>
                                        Progress Tracking
                                    </div>
                                </div>
                                
                                <!-- Purchase Button -->
                                <button onclick="redirectToRegister()" 
                                        class="btn-purchase w-full gradient-red text-white font-bold py-4 px-8 rounded-2xl text-lg shadow-lg transform transition-all duration-300 hover:scale-105 hover:shadow-xl relative overflow-hidden group">
                                    <span class="relative z-10">Purchase Module</span>
                                    <div class="absolute inset-0 bg-gradient-to-r from-red-600 to-red-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left"></div>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $index => $course): ?>
                        <?php 
                        $level = getCourseLevel($course['category_name'], $course['price']);
                        $courseType = $course['price'] > 0 ? 'paid' : 'free';
                        $levelClass = 'level-' . strtolower($level);
                        ?>
                        <div class="course-card bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 hover:shadow-2xl transition-all duration-500 group" 
                             data-aos="fade-up" 
                             data-aos-delay="<?php echo $index * 100; ?>"
                             data-course-type="<?php echo $courseType; ?>"
                             data-level="<?php echo strtolower($level); ?>"
                             data-title="<?php echo strtolower($course['title']); ?>"
                             data-description="<?php echo strtolower($course['description']); ?>">
                            <div class="relative">
                                <!-- Course Image -->
                                <div class="w-full h-64 bg-gradient-to-br from-red-100 to-orange-100 relative overflow-hidden">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent z-10"></div>
                                    <?php if ($course['image_path']): ?>
                                        <img src="../../../uploads/course_images/<?php echo htmlspecialchars($course['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($course['title']); ?>"
                                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                             onerror="this.parentElement.innerHTML='<div class=\'flex items-center justify-center h-full z-20 relative\'><span class=\'text-6xl\'>🇯🇵</span></div>'">
                                    <?php else: ?>
                                        <div class="flex items-center justify-center h-full z-20 relative">
                                            <span class="text-6xl">🇯🇵</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Price Badge -->
                                    <div class="absolute top-4 left-4 z-30">
                                        <?php if ($course['price'] > 0): ?>
                                            <span class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg">
                                                ₱<?php echo number_format($course['price'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg">
                                                FREE
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Level Badge -->
                                    <div class="absolute top-4 right-4 z-30">
                                        <span class="<?php echo $levelClass; ?> text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg">
                                            <?php echo $level; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Course Content -->
                                <div class="course-content p-8">
                                    <!-- Course Title -->
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3 group-hover:text-red-600 transition-colors duration-300">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </h3>
                                    
                                    <!-- Course Description -->
                                    <p class="text-gray-600 mb-6 leading-relaxed line-clamp-3">
                                        <?php 
                                        $description = $course['description'] ?? '';
                                        echo htmlspecialchars(substr($description, 0, 150)) . (strlen($description) > 150 ? '...' : ''); 
                                        ?>
                                    </p>
                                    
                                    <!-- Course Meta -->
                                    <div class="course-meta flex flex-wrap gap-4 mb-6">
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="font-medium"><?php echo getCourseDuration($course['total_sections']); ?></span>
                                        </div>
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            <span class="font-medium"><?php echo $course['student_count']; ?>+ Students</span>
                                        </div>
                                        <div class="flex items-center text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="font-medium">Certificate</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Course Features -->
                                    <div class="course-features grid grid-cols-2 gap-2 mb-6 text-sm text-gray-600">
                                        <div class="flex items-center">
                                            <span class="text-green-500 mr-2">✓</span>
                                            Lifetime Access
                                        </div>
                                        <div class="flex items-center">
                                            <span class="text-green-500 mr-2">✓</span>
                                            Mobile Friendly
                                        </div>
                                        <div class="flex items-center">
                                            <span class="text-green-500 mr-2">✓</span>
                                            Expert Support
                                        </div>
                                        <div class="flex items-center">
                                            <span class="text-green-500 mr-2">✓</span>
                                            Progress Tracking
                                        </div>
                                    </div>
                                    
                                    <!-- Purchase Button -->
                                    <button onclick="redirectToRegister(<?php echo $course['id']; ?>)" 
                                            class="btn-purchase w-full gradient-red text-white font-bold py-4 px-8 rounded-2xl text-lg shadow-lg transform transition-all duration-300 hover:scale-105 hover:shadow-xl relative overflow-hidden group">
                                        <span class="relative z-10">
                                            <?php echo $course['price'] > 0 ? 'Purchase Module' : 'Enroll Free'; ?>
                                        </span>
                                        <div class="absolute inset-0 bg-gradient-to-r from-red-600 to-red-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left"></div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- No Results Message -->
            <div id="noResults" class="hidden text-center py-16">
                <div class="max-w-md mx-auto">
                    <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Modules found</h3>
                    <p class="text-gray-600">Try adjusting your search filters or explore different categories.</p>
                    <button onclick="clearFilters()" class="mt-4 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="text-center mt-16" data-aos="fade-up">
                <div class="bg-gradient-to-r from-red-800 to-red-600 rounded-3xl p-8 text-white relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-red-300/20 to-red-600/20"></div>
                    <div class="relative z-10">
                        <h3 class="text-3xl font-bold mb-4">Ready to Start Your Japanese Journey?</h3>
                        <p class="text-red-100 mb-6 text-lg">Join thousands of students learning Japanese with our expert-designed modules.</p>
                        <a href="../../../dashboard/signup.php" class="inline-flex items-center px-8 py-4 bg-white text-red-600 font-semibold rounded-full shadow-lg hover:bg-gray-100 transform hover:scale-105 transition-all duration-300">
                            Get Started Today
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
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

    <!-- Scripts -->
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true
        });


        // Filter functionality
        const courseTypeFilter = document.getElementById('courseTypeFilter');
        const levelFilter = document.getElementById('levelFilter');
        const searchInput = document.getElementById('searchInput');
        const courseGrid = document.getElementById('courseGrid');
        const noResults = document.getElementById('noResults');

        function filterCourses() {
            const courseType = courseTypeFilter?.value.toLowerCase() || '';
            const level = levelFilter?.value.toLowerCase() || '';
            const searchTerm = searchInput?.value.toLowerCase() || '';
            
            const courseCards = document.querySelectorAll('.course-card');
            let visibleCount = 0;

            courseCards.forEach(card => {
                const cardType = card.dataset.courseType || '';
                const cardLevel = card.dataset.level || '';
                const cardTitle = card.dataset.title || '';
                const cardDescription = card.dataset.description || '';
                
                const matchesType = !courseType || cardType === courseType;
                const matchesLevel = !level || cardLevel === level;
                const matchesSearch = !searchTerm || 
                    cardTitle.includes(searchTerm) || 
                    cardDescription.includes(searchTerm);
                
                if (matchesType && matchesLevel && matchesSearch) {
                    card.style.display = 'block';
                    // Re-trigger animation
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                        card.style.transition = 'all 0.5s ease';
                    }, visibleCount * 100);
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide no results message
            if (noResults) {
                if (visibleCount === 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
            }
        }

        function clearFilters() {
            if (courseTypeFilter) courseTypeFilter.value = '';
            if (levelFilter) levelFilter.value = '';
            if (searchInput) searchInput.value = '';
            filterCourses();
        }

        // Add event listeners for filters
        if (courseTypeFilter) {
            courseTypeFilter.addEventListener('change', filterCourses);
        }
        if (levelFilter) {
            levelFilter.addEventListener('change', filterCourses);
        }
        if (searchInput) {
            // Debounce search input
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(filterCourses, 300);
            });
        }

        // Redirect to register page with optional course ID
        function redirectToRegister(courseId = null) {
            let url = '../../../dashboard/signup.php';
            if (courseId) {
                url += '?course_id=' + courseId;
            }
            window.location.href = url;
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading states to buttons
        document.querySelectorAll('.btn-purchase').forEach(button => {
            button.addEventListener('click', function() {
                const originalText = this.innerHTML;
                this.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Loading...';
                this.disabled = true;
                
                // Re-enable after redirect (in case it fails)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 3000);
            });
        });

        // Intersection Observer for scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe course cards for scroll animations
        document.querySelectorAll('.course-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });

        // Header scroll effect
        let lastScrollTop = 0;
        const header = document.getElementById('main-header');
        
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                header.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                header.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop;
        });

        // Add line-clamp utility for consistent text truncation
        const style = document.createElement('style');
        style.textContent = `
            .line-clamp-3 {
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
