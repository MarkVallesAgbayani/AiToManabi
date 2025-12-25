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
        $data = [
            'content' => trim($_POST['content']),
            'background_color' => $_POST['background_color'],
            'text_color' => $_POST['text_color'],
            'button_text' => trim($_POST['button_text']),
            'button_url' => trim($_POST['button_url']),
            'button_color' => $_POST['button_color'],
            'button_icon' => $_POST['button_icon'],
            'discount_value' => trim($_POST['discount_value']),
            'discount_type' => $_POST['discount_type'],
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'is_published' => isset($_POST['is_published']) ? 1 : 0
        ];

        // Validate content
        if (empty($data['content'])) {
            throw new Exception("Announcement content cannot be empty.");
        }

        // Check if announcement already exists
        $stmt = $pdo->prepare("SELECT id FROM announcement_banner LIMIT 1");
        $stmt->execute();
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing announcement
            $sql = "UPDATE announcement_banner SET 
                content = :content,
                background_color = :background_color,
                text_color = :text_color,
                button_text = :button_text,
                button_url = :button_url,
                button_color = :button_color,
                button_icon = :button_icon,
                discount_value = :discount_value,
                discount_type = :discount_type,
                start_date = :start_date,
                end_date = :end_date,
                is_published = :is_published,
                updated_at = NOW()
                WHERE id = :id";
            $data['id'] = $existing['id'];
        } else {
            // Create new announcement
            $sql = "INSERT INTO announcement_banner 
                (content, background_color, text_color, button_text, button_url, 
                button_color, button_icon, discount_value, discount_type, 
                start_date, end_date, is_published, created_at, updated_at)
                VALUES 
                (:content, :background_color, :text_color, :button_text, :button_url,
                :button_color, :button_icon, :discount_value, :discount_type,
                :start_date, :end_date, :is_published, NOW(), NOW())";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        // Log the action
        $admin_id = $_SESSION['user_id'];
        $action = $existing ? 'update_announcement' : 'create_announcement';
        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, action_detail, created_at) 
            VALUES (:admin_id, :action, :detail, NOW())");
        $log_stmt->execute([
            'admin_id' => $admin_id,
            'action' => $action,
            'detail' => 'Announcement ' . ($existing ? 'updated' : 'created')
        ]);

        $success_message = "Announcement " . ($existing ? "updated" : "created") . " successfully!";

    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch current announcement
try {
    $stmt = $pdo->prepare("SELECT * FROM announcement_banner ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching announcement: " . $e->getMessage();
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
    <title>Edit Announcement Banner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Edit Announcement Banner</h1>
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
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="p-6">
                        <!-- Live Preview -->
                        <div class="mb-6 p-4 rounded-lg border"
                             x-data="{
                                 content: '<?php echo addslashes($announcement['content'] ?? ''); ?>',
                                 bgColor: '<?php echo $announcement['background_color'] ?? '#dc2626'; ?>',
                                 textColor: '<?php echo $announcement['text_color'] ?? '#ffffff'; ?>',
                                 buttonText: '<?php echo addslashes($announcement['button_text'] ?? ''); ?>',
                                 buttonUrl: '<?php echo addslashes($announcement['button_url'] ?? ''); ?>',
                                 buttonColor: '<?php echo $announcement['button_color'] ?? '#0EA5E9'; ?>'
                             }"
                             :style="`background-color: ${bgColor}; color: ${textColor};`">
                            <div class="flex items-center justify-between">
                                <span x-text="content || 'Your announcement text here...'"></span>
                                <template x-if="buttonText && buttonUrl">
                                    <a :href="buttonUrl" class="ml-3 px-3 py-1 rounded-full text-white text-sm"
                                       :style="`background-color: ${buttonColor}`" x-text="buttonText"></a>
                                </template>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="mb-6">
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                                Announcement Text
                                <span class="text-xs text-gray-500" x-text="charCount + '/' + maxChars"></span>
                            </label>
                            <textarea 
                                id="content"
                                name="content"
                                rows="3"
                                x-model="content"
                                @input="if(content.length > maxChars) content = content.substring(0, maxChars)"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                required
                            ><?php echo htmlspecialchars($announcement['content'] ?? ''); ?></textarea>
                        </div>

                        <!-- Colors -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="background_color" class="block text-sm font-medium text-gray-700 mb-2">
                                    Background Color
                                </label>
                                <input type="color" 
                                       id="background_color"
                                       name="background_color" 
                                       x-model="bgColor"
                                       value="<?php echo htmlspecialchars($announcement['background_color'] ?? '#dc2626'); ?>"
                                       class="h-10 w-full rounded-md border-gray-300">
                            </div>
                            <div>
                                <label for="text_color" class="block text-sm font-medium text-gray-700 mb-2">
                                    Text Color
                                </label>
                                <input type="color" 
                                       id="text_color"
                                       name="text_color" 
                                       x-model="textColor"
                                       value="<?php echo htmlspecialchars($announcement['text_color'] ?? '#ffffff'); ?>"
                                       class="h-10 w-full rounded-md border-gray-300">
                            </div>
                        </div>

                        <!-- Button Settings -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="button_text" class="block text-sm font-medium text-gray-700 mb-2">
                                    Button Text
                                </label>
                                <input type="text" 
                                       id="button_text"
                                       name="button_text" 
                                       x-model="buttonText"
                                       value="<?php echo htmlspecialchars($announcement['button_text'] ?? ''); ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="button_url" class="block text-sm font-medium text-gray-700 mb-2">
                                    Button URL
                                </label>
                                <input type="url" 
                                       id="button_url"
                                       name="button_url" 
                                       x-model="buttonUrl"
                                       value="<?php echo htmlspecialchars($announcement['button_url'] ?? ''); ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="button_color" class="block text-sm font-medium text-gray-700 mb-2">
                                    Button Color
                                </label>
                                <input type="color" 
                                       id="button_color"
                                       name="button_color" 
                                       x-model="buttonColor"
                                       value="<?php echo htmlspecialchars($announcement['button_color'] ?? '#0EA5E9'); ?>"
                                       class="h-10 w-full rounded-md border-gray-300">
                            </div>
                            <div>
                                <label for="button_icon" class="block text-sm font-medium text-gray-700 mb-2">
                                    Button Icon (FontAwesome class)
                                </label>
                                <input type="text" 
                                       id="button_icon"
                                       name="button_icon" 
                                       value="<?php echo htmlspecialchars($announcement['button_icon'] ?? ''); ?>"
                                       placeholder="fas fa-tag"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                        </div>

                        <!-- Discount Settings -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="discount_value" class="block text-sm font-medium text-gray-700 mb-2">
                                    Discount Value
                                </label>
                                <input type="text" 
                                       id="discount_value"
                                       name="discount_value" 
                                       value="<?php echo htmlspecialchars($announcement['discount_value'] ?? ''); ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="discount_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Discount Type
                                </label>
                                <select id="discount_type"
                                        name="discount_type" 
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    <option value="percentage" <?php echo ($announcement['discount_type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                    <option value="fixed" <?php echo ($announcement['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                                </select>
                            </div>
                        </div>

                        <!-- Schedule -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Start Date (optional)
                                </label>
                                <input type="datetime-local" 
                                       id="start_date"
                                       name="start_date" 
                                       value="<?php echo $announcement['start_date'] ?? ''; ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    End Date (optional)
                                </label>
                                <input type="datetime-local" 
                                       id="end_date"
                                       name="end_date" 
                                       value="<?php echo $announcement['end_date'] ?? ''; ?>"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                        </div>

                        <!-- Publishing -->
                        <div class="flex items-center mb-6">
                            <input type="checkbox" 
                                   id="is_published"
                                   name="is_published" 
                                   <?php echo ($announcement['is_published'] ?? 0) ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="is_published" class="ml-2 block text-sm text-gray-900">
                                Publish Announcement
                            </label>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 text-right">
                        <button type="submit"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <?php if (isset($announcement['updated_at'])): ?>
            <div class="mt-4 text-sm text-gray-500">
                Last updated: <?php echo date('F j, Y, g:i a', strtotime($announcement['updated_at'])); ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('announcementForm', () => ({
                content: '<?php echo addslashes($announcement['content'] ?? ''); ?>',
                charCount: 0,
                maxChars: 200,
                init() {
                    this.charCount = this.content.length;
                    this.$watch('content', value => {
                        this.charCount = value.length;
                    });
                }
            }));
        });
    </script>
</body>
</html>
