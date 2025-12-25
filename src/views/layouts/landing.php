<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .font-japanese {
            font-family: 'Noto Sans JP', sans-serif;
        }
        .bg-sakura {
            background-color: #FF1A42;
        }
        .hover\:bg-sakura:hover {
            background-color: #CC1535;
        }
        .text-sakura {
            color: #FF1A42;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <header class="bg-white shadow-sm">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-2xl font-japanese font-bold text-gray-900">
                            <?php echo SITE_NAME; ?>
                        </h1>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="/login" class="font-japanese text-gray-700 hover:text-sakura px-3 py-2 rounded-md text-sm font-medium">
                        ログイン
                    </a>
                    <a href="/register" class="font-japanese bg-sakura hover:bg-sakura text-white px-4 py-2 rounded-md text-sm font-medium ml-3">
                        新規登録
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <main>
        <div class="relative">
            <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gray-100"></div>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="relative shadow-xl sm:rounded-2xl sm:overflow-hidden">
                    <div class="absolute inset-0">
                        <img class="h-full w-full object-cover" src="/assets/images/japan-background.jpg" alt="Japanese landscape">
                        <div class="absolute inset-0 bg-gradient-to-r from-gray-800 to-black mix-blend-multiply"></div>
                    </div>
                    <div class="relative px-4 py-16 sm:px-6 sm:py-24 lg:py-32 lg:px-8">
                        <h2 class="text-center text-4xl font-japanese font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                            <span class="block text-white">新しい学習の形</span>
                            <span class="block text-sakura mt-2">オンライン日本語学習</span>
                        </h2>
                        <p class="mt-6 max-w-lg mx-auto text-center text-xl font-japanese text-gray-200 sm:max-w-3xl">
                            最高の講師陣と共に、あなたの日本語学習をサポートします。
                            いつでも、どこでも、自分のペースで学べます。
                        </p>
                        <div class="mt-10 max-w-sm mx-auto sm:max-w-none sm:flex sm:justify-center">
                            <div class="space-y-4 sm:space-y-0 sm:mx-auto sm:inline-grid sm:grid-cols-2 sm:gap-5">
                                <a href="/courses" class="flex items-center justify-center px-4 py-3 border border-transparent text-base font-japanese font-medium rounded-md text-white bg-sakura hover:bg-sakura sm:px-8">
                                    コースを見る
                                </a>
                                <a href="/about" class="flex items-center justify-center px-4 py-3 border border-transparent text-base font-japanese font-medium rounded-md text-gray-200 bg-gray-800 bg-opacity-60 hover:bg-opacity-70 sm:px-8">
                                    詳しく見る
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Features Section -->
    <div class="bg-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-3xl font-japanese font-extrabold text-gray-900 sm:text-4xl">
                    学習の特徴
                </h2>
                <p class="mt-4 max-w-2xl text-xl font-japanese text-gray-500 lg:mx-auto">
                    効率的で楽しい日本語学習を提供します
                </p>
            </div>

            <div class="mt-10">
                <div class="space-y-10 md:space-y-0 md:grid md:grid-cols-3 md:gap-x-8 md:gap-y-10">
                    <!-- Feature 1 -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-sakura text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <p class="ml-16 text-lg font-japanese font-medium text-gray-900">柔軟な学習スケジュール</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-sakura text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <p class="ml-16 text-lg font-japanese font-medium text-gray-900">豊富な教材</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-sakura text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <p class="ml-16 text-lg font-japanese font-medium text-gray-900">経験豊富な講師陣</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="text-base font-japanese text-gray-400">
                    &copy; 2024 <?php echo SITE_NAME; ?>. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>
</html> 