<?php
require_once '../../config/database.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $terms_content = trim($_POST['terms_content']);
        
        // Check if terms content is empty
        if (empty($terms_content)) {
            throw new Exception("Terms & Conditions content cannot be empty.");
        }

        // First, check if terms already exist
        $stmt = $pdo->prepare("SELECT id FROM terms_conditions LIMIT 1");
        $stmt->execute();
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing terms
            $stmt = $pdo->prepare("UPDATE terms_conditions SET 
                content = :content,
                updated_at = NOW()
                WHERE id = :id");
            $stmt->execute([
                'content' => $terms_content,
                'id' => $existing['id']
            ]);
        } else {
            // Insert new terms
            $stmt = $pdo->prepare("INSERT INTO terms_conditions (content, created_at, updated_at) 
                VALUES (:content, NOW(), NOW())");
            $stmt->execute(['content' => $terms_content]);
        }

        $success_message = "Terms & Conditions updated successfully!";
        
        // Log the action
        $admin_id = $_SESSION['user_id'];
        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, action_detail, created_at) 
            VALUES (:admin_id, 'update_terms', 'Terms & Conditions updated', NOW())");
        $log_stmt->execute(['admin_id' => $admin_id]);

    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch current terms
try {
    $stmt = $pdo->prepare("SELECT * FROM terms_conditions ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $terms = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching terms: " . $e->getMessage();
}

// Fetch admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Terms & Conditions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Add TinyMCE from CDN -->
    <script src="../../assets/tinymce/tinymce/js/tinymce/tinymce.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }

        // Initialize TinyMCE
        tinymce.init({
            selector: '#terms_content',
            height: 500,
            menubar: true,
            base_url: '../../assets/tinymce/tinymce/js/tinymce',
            suffix: '.min',
            license_key: 'gpl',
            promotion: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | ' +
                    'bold italic forecolor | alignleft aligncenter ' +
                    'alignright alignjustify | bullist numlist outdent indent | ' +
                    'removeformat | help',
            content_style: `
                body { 
                    font-family: Inter, system-ui, -apple-system, sans-serif;
                    font-size: 14px;
                    line-height: 1.5;
                    max-width: none;
                    padding: 1rem;
                }
                h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem; }
                h2 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem; }
                h3 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.75rem; }
                p { margin-bottom: 1rem; }
                ul, ol { margin-bottom: 1rem; padding-left: 1.5rem; }
                li { margin-bottom: 0.5rem; }
            `,
            mobile: {
                menubar: true,
                toolbar_mode: 'sliding',
                height: '100vh'
            },
            setup: function(editor) {
                // Add custom styles to the format dropdown
                editor.ui.registry.addButton('customformat', {
                    text: 'Format',
                    type: 'menubutton',
                    fetch: function(callback) {
                        callback([
                            {
                                type: 'nestedmenuitem',
                                text: 'Headings',
                                getSubmenuItems: function() {
                                    return [
                                        {
                                            type: 'menuitem',
                                            text: 'Section Title',
                                            onAction: function() {
                                                editor.formatter.apply('h2');
                                            }
                                        },
                                        {
                                            type: 'menuitem',
                                            text: 'Subsection',
                                            onAction: function() {
                                                editor.formatter.apply('h3');
                                            }
                                        }
                                    ];
                                }
                            }
                        ]);
                    }
                });
            }
        });
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* TinyMCE responsive styles */
        .tox-tinymce {
            border-radius: 0.375rem !important;
            border-color: #d1d5db !important;
        }
        .tox-tinymce:focus-within {
            border-color: #0ea5e9 !important;
            box-shadow: 0 0 0 1px #0ea5e9 !important;
        }
        @media (max-width: 768px) {
            .tox-tinymce {
                height: calc(100vh - 300px) !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Edit Terms & Conditions</h1>
                    <a href="content_management.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                        Back to Content Management
                    </a>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <?php if ($success_message): ?>
                <div class="mb-4 p-4 rounded-md bg-green-50 border border-green-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mb-4 p-4 rounded-md bg-red-50 border border-red-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" x-data="{ termsAccepted: false }" @submit.prevent="termsAccepted ? $el.submit() : $event.preventDefault()">
                    <div class="p-6">
                        <div class="mb-6">
                            <label for="terms_content" class="block text-sm font-medium text-gray-700 mb-2">
                                Terms & Conditions Content
                            </label>
                            <textarea 
                                id="terms_content"
                                name="terms_content"
                                class="hidden"
                            ><?php echo htmlspecialchars($terms['content'] ?? ''); ?></textarea>
                            <p class="mt-2 text-sm text-gray-500">
                                Use the editor above to format your terms and conditions. You can add headings, lists, and other formatting as needed.
                            </p>
                        </div>

                        <div class="flex items-start mb-6">
                            <div class="flex items-center h-5">
                                <input
                                    id="terms_acceptance"
                                    type="checkbox"
                                    x-model="termsAccepted"
                                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                >
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="terms_acceptance" class="font-medium text-gray-700">
                                    I confirm that these changes comply with all applicable laws and regulations
                                </label>
                                <p class="text-red-600 mt-1" x-show="!termsAccepted">
                                    You must confirm compliance before saving changes.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 text-right">
                        <button
                            type="submit"
                            :class="{'opacity-50 cursor-not-allowed': !termsAccepted, 'hover:bg-primary-700': termsAccepted}"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <?php if (isset($terms['updated_at'])): ?>
            <div class="mt-4 text-sm text-gray-500">
                Last updated: <?php echo date('F j, Y, g:i a', strtotime($terms['updated_at'])); ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html> 