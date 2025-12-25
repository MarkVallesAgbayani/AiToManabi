<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';

// Handle template creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['create_template'])) {
            // Create new template
            $stmt = $pdo->prepare("INSERT INTO role_templates (name, description) VALUES (?, ?)");
            $stmt->execute([$_POST['name'], $_POST['description']]);
            $template_id = $pdo->lastInsertId();

            // Add permissions to template
            if (!empty($_POST['permissions'])) {
                $stmt = $pdo->prepare("INSERT INTO role_template_permissions (template_id, permission_id) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $permission_id) {
                    $stmt->execute([$template_id, $permission_id]);
                }
            }

            $_SESSION['success'] = [
                'title' => 'Template Created!',
                'message' => 'Role template has been created successfully.',
                'icon' => 'success'
            ];
        } elseif (isset($_POST['update_template'])) {
            $template_id = $_POST['template_id'];

            // Update template details
            $stmt = $pdo->prepare("UPDATE role_templates SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['description'], $template_id]);

            // Remove existing permissions
            $stmt = $pdo->prepare("DELETE FROM role_template_permissions WHERE template_id = ?");
            $stmt->execute([$template_id]);

            // Add new permissions
            if (!empty($_POST['permissions'])) {
                $stmt = $pdo->prepare("INSERT INTO role_template_permissions (template_id, permission_id) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $permission_id) {
                    $stmt->execute([$template_id, $permission_id]);
                }
            }

            $_SESSION['success'] = [
                'title' => 'Template Updated!',
                'message' => 'Role template has been updated successfully.',
                'icon' => 'success'
            ];
        } elseif (isset($_POST['delete_template'])) {
            $template_id = $_POST['template_id'];

            // Check if template is in use
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE template_id = ?");
            $stmt->execute([$template_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cannot delete template as it is currently in use by some users.");
            }

            // Delete template and its permissions
            $stmt = $pdo->prepare("DELETE FROM role_templates WHERE id = ?");
            $stmt->execute([$template_id]);

            $_SESSION['success'] = [
                'title' => 'Template Deleted!',
                'message' => 'Role template has been deleted successfully.',
                'icon' => 'success'
            ];
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = [
            'title' => 'Error!',
            'message' => $e->getMessage(),
            'icon' => 'error'
        ];
    }

    header("Location: role_templates.php");
    exit();
}

// Fetch all templates
$stmt = $pdo->query("
    SELECT rt.*, 
           GROUP_CONCAT(p.name) as permissions,
           COUNT(DISTINCT up.user_id) as users_count
    FROM role_templates rt
    LEFT JOIN role_template_permissions rtp ON rtp.template_id = rt.id
    LEFT JOIN permissions p ON p.id = rtp.permission_id
    LEFT JOIN user_permissions up ON up.template_id = rt.id
    GROUP BY rt.id
    ORDER BY rt.name
");
$templates = $stmt->fetchAll();

// Fetch all permissions grouped by category
$stmt = $pdo->query("SELECT * FROM permissions ORDER BY category, name");
$permissions = $stmt->fetchAll(PDO::FETCH_GROUP);

// Check for success/error messages
$alert = null;
if (isset($_SESSION['success'])) {
    $alert = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $alert = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Templates - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="//unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="h-full bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Include your sidebar here -->
        
        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <header class="bg-white shadow-sm">
                <div class="px-6 py-4">
                    <h1 class="text-2xl font-semibold text-gray-900">Role Templates</h1>
                </div>
            </header>

            <main class="p-6">
                <?php if ($alert): ?>
                    <div class="mb-4 bg-<?php echo $alert['icon'] === 'success' ? 'green' : 'red' ?>-100 border border-<?php echo $alert['icon'] === 'success' ? 'green' : 'red' ?>-400 text-<?php echo $alert['icon'] === 'success' ? 'green' : 'red' ?>-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold"><?php echo $alert['title']; ?></strong>
                        <span class="block sm:inline"><?php echo $alert['message']; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Templates List -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-medium text-gray-900">Role Templates</h2>
                            <button @click="showCreateModal = true" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Create Template
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($templates as $template): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($template['name']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($template['description']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $template['users_count']; ?> users
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?php
                                                $perms = explode(',', $template['permissions']);
                                                echo count($perms) . ' permissions';
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button @click="editTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)" class="text-primary-600 hover:text-primary-900">Edit</button>
                                                <button @click="deleteTemplate(<?php echo $template['id']; ?>)" class="ml-3 text-red-600 hover:text-red-900" <?php echo $template['users_count'] > 0 ? 'disabled' : ''; ?>>Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Template Modal -->
    <div x-show="showTemplateModal" class="fixed z-10 inset-0 overflow-y-auto" x-cloak>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" @submit="confirmTemplateAction($event)">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" x-text="isEditing ? 'Edit Template' : 'Create Template'"></h3>
                                <div class="mt-4 space-y-4">
                                    <input type="hidden" name="template_id" x-model="editingTemplate?.id">
                                    <input type="hidden" :name="isEditing ? 'update_template' : 'create_template'" value="1">

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Template Name</label>
                                        <input type="text" name="name" required x-model="editingTemplate?.name"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Description</label>
                                        <textarea name="description" rows="3" x-model="editingTemplate?.description"
                                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Permissions</label>
                                        <div class="max-h-60 overflow-y-auto border rounded-md p-4 space-y-4">
                                            <?php foreach ($permissions as $category => $perms): ?>
                                                <div class="permission-group">
                                                    <h4 class="text-sm font-medium text-gray-900 mb-2"><?php echo ucwords(str_replace('_', ' ', $category)); ?></h4>
                                                    <div class="space-y-2">
                                                        <?php foreach ($perms as $permission): ?>
                                                            <label class="inline-flex items-center">
                                                                <input type="checkbox" name="permissions[]" value="<?php echo $permission['id']; ?>"
                                                                       x-model="selectedPermissions"
                                                                       class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                                <span class="ml-2 text-sm text-gray-600"><?php echo $permission['description']; ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <span x-text="isEditing ? 'Update Template' : 'Create Template'"></span>
                        </button>
                        <button type="button" @click="showTemplateModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('templateManager', () => ({
                showTemplateModal: false,
                isEditing: false,
                editingTemplate: null,
                selectedPermissions: [],

                editTemplate(template) {
                    this.isEditing = true;
                    this.editingTemplate = template;
                    this.selectedPermissions = template.permissions ? template.permissions.split(',').map(Number) : [];
                    this.showTemplateModal = true;
                },

                createTemplate() {
                    this.isEditing = false;
                    this.editingTemplate = { name: '', description: '' };
                    this.selectedPermissions = [];
                    this.showTemplateModal = true;
                },

                deleteTemplate(templateId) {
                    Swal.fire({
                        title: 'Delete Template?',
                        text: 'This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Yes, Delete'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="delete_template" value="1">
                                <input type="hidden" name="template_id" value="${templateId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                },

                confirmTemplateAction(event) {
                    event.preventDefault();
                    const form = event.target;
                    const formData = new FormData(form);
                    
                    if (formData.getAll('permissions[]').length === 0) {
                        Swal.fire({
                            title: 'Warning',
                            text: 'No permissions selected. Are you sure you want to continue?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#0284c7',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: 'Yes, Continue'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    } else {
                        form.submit();
                    }
                }
            }));
        });
    </script>
</body>
</html> 