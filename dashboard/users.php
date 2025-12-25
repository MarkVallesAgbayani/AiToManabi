<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once '../includes/admin_notifications.php';

// NEW: Add session validation check
require_once __DIR__ . '/../includes/session_validator.php';
$sessionValidator = new SessionValidator($pdo);

if (!$sessionValidator->isSessionValid($_SESSION['user_id'])) {
    $sessionValidator->forceLogout('Your account access has been restricted. Please contact support if you believe this is an error.');
}


// Email masking function
function maskEmail($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    
    $parts = explode('@', $email);
    $username = $parts[0];
    $domain = $parts[1];
    
    // Mask username part
    $usernameLength = strlen($username);
    if ($usernameLength <= 2) {
        $maskedUsername = str_repeat('*', $usernameLength);
    } else {
        $visibleChars = min(2, floor($usernameLength / 3));
        $maskedUsername = substr($username, 0, $visibleChars) . str_repeat('*', $usernameLength - $visibleChars);
    }
    
    return $maskedUsername . '@' . $domain;
}


// Get all user permissions to check for access
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$all_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);


// Check admin access - either by permission or admin role
$has_user_management_access = false;

// First check if user has nav_user_management permission
if (function_exists('hasPermission')) {
    $has_user_management_access = hasPermission($pdo, $_SESSION['user_id'], 'nav_user_management');
}

// Fallback: Check if user has admin role
if (!$has_user_management_access && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $has_user_management_access = true;
}

// Final fallback: Check user role directly from database
if (!$has_user_management_access) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        $has_user_management_access = true;
    }
}

if (!$has_user_management_access) {
    header("Location: ../index.php");
    exit();
}

// Get current user's permissions
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);

// Get permissions that the current user can assign (only what they have)
$assignable_permissions = getAssignablePermissions($pdo, $_SESSION['user_id']);

// Group permissions by category for display
$permissions_by_category = [];
foreach ($assignable_permissions as $permission) {
    $category = $permission['category'];
    if (!isset($permissions_by_category[$category])) {
        $permissions_by_category[$category] = [];
    }
    $permissions_by_category[$category][] = $permission;
}

// Fetch role templates
$role_templates = getAvailableRoleTemplates($pdo);

// Fetch all permissions for the modal (grouped by category)
$stmt = $pdo->query("SELECT * FROM permissions ORDER BY category, name");
$all_permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$all_permissions_by_category = [];
foreach ($all_permissions as $permission) {
    $category = $permission['category'];
    if (!isset($all_permissions_by_category[$category])) {
        $all_permissions_by_category[$category] = [];
    }
    $all_permissions_by_category[$category][] = $permission;
}

// Create flat array for JavaScript
$all_permissions_flat = [];
foreach ($all_permissions_by_category as $category => $perms) {
    foreach ($perms as $permission) {
        $all_permissions_flat[] = [
            'name' => $permission['name'],
            'description' => $permission['description'],
            'category' => $permission['category']
        ];
    }
}


// Check for success/error messages from other actions
$alert = null;
if (isset($_SESSION['success'])) {
    $alert = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $alert = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch all users with their permissions using RBAC system
$stmt = $pdo->query("
    SELECT 
        u.id, 
        u.username, 
        u.email, 
        u.role, 
        u.created_at, 
        u.status,
        u.last_login_at, 
        u.login_attempts,
        u.deleted_at,
        CASE 
            WHEN u.deleted_at IS NOT NULL THEN
                CASE 
                    WHEN u.deleted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'recently_deleted'
                    ELSE 'deleted'
                END
            ELSE u.status 
        END as display_status,
        DATEDIFF(NOW(), u.deleted_at) as days_since_deletion,
        COALESCE(tp.profile_picture, sp.profile_picture, '') as profile_picture,
        GROUP_CONCAT(DISTINCT COALESCE(up.permission_name, p.name, 
            CASE u.role
                WHEN 'admin' THEN 'nav_dashboard,nav_course_management,nav_user_management,nav_reports,nav_payments,nav_content_management,nav_audit_trails,nav_security_warnings,nav_error_logs,nav_usage_analytics,nav_user_roles_report,dashboard_view_students_card,dashboard_view_teachers_card,dashboard_view_modules_card,dashboard_view_revenue_card,dashboard_view_course_completion,dashboard_view_user_retention,dashboard_view_sales_reports,dashboard_view_completion_metrics,dashboard_view_retention_metrics,dashboard_view_sales_metrics,system_login,system_logout,system_profile'
                WHEN 'teacher' THEN 'nav_teacher_dashboard,nav_teacher_courses,nav_teacher_content,nav_teacher_students,nav_teacher_reports,nav_teacher_settings,nav_teacher_audit,nav_teacher_create_module,nav_teacher_placement_test,nav_teacher_courses_by_category,system_login,system_logout,system_profile'
                WHEN 'student' THEN 'nav_student_dashboard,nav_student_courses,nav_student_learning,system_login,system_logout,system_profile'
                ELSE ''
            END
        )) as permissions,
        GROUP_CONCAT(DISTINCT rt.name) as template_names,
        CASE 
            WHEN COUNT(DISTINCT up.permission_name) > 0 AND COUNT(DISTINCT rt.id) > 0 THEN 'Hybrid'
            WHEN COUNT(DISTINCT up.permission_name) > 0 THEN 'Custom'
            WHEN COUNT(DISTINCT rt.id) > 0 THEN 'Template'
            ELSE 'Default'
        END as permission_type
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN role_templates rt ON ur.template_id = rt.id
    LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
    LEFT JOIN permissions p ON rtp.permission_id = p.id
    LEFT JOIN user_permissions up ON u.id = up.user_id
    LEFT JOIN teacher_preferences tp ON u.id = tp.teacher_id AND u.role = 'teacher'
    LEFT JOIN student_preferences sp ON u.id = sp.student_id AND u.role = 'student'
    GROUP BY u.id
    ORDER BY 
        CASE 
            WHEN u.deleted_at IS NULL THEN 0 
            ELSE 1 
        END,
        u.deleted_at DESC,
        u.created_at DESC
");
$users = $stmt->fetchAll();

// Function to get permission summary
function getPermissionSummary($permissions) {
    if (!$permissions) return 'No permissions';
    
    $perms = explode(',', $permissions);
    $categories = [];
    
    foreach ($perms as $perm) {
        if (strpos($perm, 'nav_') === 0) {
            $category = str_replace('nav_', '', $perm);
            $category = str_replace('_', ' ', $category);
            if (!isset($categories[$category])) {
                $categories[$category] = 1;
            } else {
                $categories[$category]++;
            }
        }
    }
    
    $summary = [];
    foreach ($categories as $category => $count) {
        $summary[] = ucfirst($category) . " ({$count})";
    }
    
    return implode(', ', $summary);
}

// Function to get user profile picture or generate initial
function getUserProfilePicture($user) {
    $picture = [
        'has_image' => false,
        'image_path' => '',
        'initial' => strtoupper(substr($user['username'], 0, 1))
    ];
    
    if (!empty($user['profile_picture'])) {
        // Try multiple possible paths
        $possible_paths = [
            __DIR__ . '/../uploads/profile_pictures/' . $user['profile_picture'],
            __DIR__ . '/uploads/profile_pictures/' . $user['profile_picture'],
            $_SERVER['DOCUMENT_ROOT'] . '/AIToManabi_Updated/uploads/profile_pictures/' . $user['profile_picture']
        ];
        
        $web_paths = [
            '../uploads/profile_pictures/' . $user['profile_picture'],
            'uploads/profile_pictures/' . $user['profile_picture'],
            '/AIToManabi_Updated/uploads/profile_pictures/' . $user['profile_picture']
        ];
        
        foreach ($possible_paths as $index => $file_path) {
            if (file_exists($file_path)) {
                $picture['has_image'] = true;
                $picture['image_path'] = $web_paths[$index];
                break;
            }
        }
    }
    
    return $picture;
}

// Fetch admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Get all permissions from database for dynamic navigation
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Management</title>
  <script src="https://unpkg.com/alpinejs@3/dist/cdn.min.js" defer></script>
      <!-- <link rel="stylesheet" href="your-styles.css" /> -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  
  <script>
  document.addEventListener('alpine:init', () => {
      Alpine.data('userManagement', () => ({
        showAddModal: false,
        selectedTemplate: '',
        showConfirmModal: false,
        showBanReasonModal: false,
        showDeleteReasonModal: false,
        selectedUser: null,
        selectedUserId: '',
        banReason: '',
        banUserId: null,
        deleteReason: '',
        deleteUserId: null,
        confirmAction: {
            title: '',
            message: '',
            actionUrl: '',
            userId: null,
            buttonColor: '',
            buttonText: '',
            formData: null
        },
        
        // Debug: Initialize with console log
        init() {
            console.log('Alpine.js userManagement component initialized');
            console.log('Initial state:', {
                currentStep: this.currentStep,
                roleType: this.roleType,
                roleOption: this.roleOption
            });
        },
        
        // Modal-specific properties
        currentStep: 1,
        roleType: '',
        roleOption: '',
        selectedTemplateId: '',
        selectedPermissions: [],
        userInfo: {
            firstName: '',
            lastName: '',
            middleName: '',
            suffix: '',
            addressLine1: '',
            addressLine2: '',
            city: '',
            age: '',
            phoneNumber: '',
            username: '',
            email: '',
            password: '',
            confirmPassword: ''
        },
        passwordError: '',
        currentPage: 0,
        permissionsPerPage: 6,
// Add to the data properties (at the top of userManagement)
showUserDetailModal: false,
activeTab: 'info',
selectedUserData: {},
paymentHistory: [],
purchasedModules: [],
loadingPayments: false,
loadingPurchases: false,

// Email masking function
maskEmail(email) {
    if (!email || !email.includes('@')) return email;
    
    const [username, domain] = email.split('@');
    const usernameLength = username.length;
    
    if (usernameLength <= 2) {
        return '*'.repeat(usernameLength) + '@' + domain;
    }
    
    const visibleChars = Math.min(2, Math.floor(usernameLength / 3));
    const maskedUsername = username.substring(0, visibleChars) + '*'.repeat(usernameLength - visibleChars);
    
    return maskedUsername + '@' + domain;
},


// Add these methods to the userManagement component
openUserDetailModal(userId, username, role) {
    this.showUserDetailModal = true;
    this.activeTab = 'info';
    this.selectedUserData = {
        id: userId,
        username: username,
        role: role
    };
    this.loadingPayments = false;
    this.loadingPurchases = false;
    
    // Fetch complete user information
    this.fetchUserDetails(userId);
},

closeUserDetailModal() {
    this.showUserDetailModal = false;
    this.selectedUserData = {};
    this.paymentHistory = [];
    this.purchasedModules = [];
},

async fetchUserDetails(userId) {
    try {
        const response = await fetch(`get_user_details.php?user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            // Mask the email before displaying
            this.selectedUserData = {
                ...this.selectedUserData,
                ...data.user,
                email: this.maskEmail(data.user.email) // Mask email here
            };
            
            // If student, fetch payment and purchase history
            if (data.user.role === 'student') {
                this.fetchPaymentHistory(userId);
                this.fetchPurchasedModules(userId);
            }
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to load user details',
                icon: 'error',
                confirmButtonColor: '#0284c7'
            });
        }
    } catch (error) {
        console.error('Error fetching user details:', error);
        Swal.fire({
            title: 'Error',
            text: 'Failed to load user details',
            icon: 'error',
            confirmButtonColor: '#0284c7'
        });
    }
},

async fetchPaymentHistory(userId) {
    this.loadingPayments = true;
    try {
        const response = await fetch(`get_payment_history.php?user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            this.paymentHistory = data.payments;
        }
    } catch (error) {
        console.error('Error fetching payment history:', error);
    } finally {
        this.loadingPayments = false;
    }
},

async fetchPurchasedModules(userId) {
    this.loadingPurchases = true;
    try {
        const response = await fetch(`get_purchased_modules.php?user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            this.purchasedModules = data.modules;
        }
    } catch (error) {
        console.error('Error fetching purchased modules:', error);
    } finally {
        this.loadingPurchases = false;
    }
},


        toggleCategoryPermissions(event, category) {
            event.preventDefault();
            const form = event.target.closest('form');
            const checkboxes = form.querySelectorAll(`input[data-category="${category}"]`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
        },

        setDefaultTemplateId() {
            // Find the appropriate default template based on role type
            const templateSelect = document.querySelector('select[name="template_id"]');
            if (templateSelect) {
                const options = templateSelect.querySelectorAll('option');
                let targetTemplateName = '';
                
                if (this.roleType === 'admin') {
                    targetTemplateName = 'Full Admin Access';
                } else if (this.roleType === 'teacher') {
                    targetTemplateName = 'Default Teacher';
                }
                
                for (let option of options) {
                    if (option.dataset.templateName === targetTemplateName) {
                        this.selectedTemplateId = option.value;
                        console.log('Set selectedTemplateId to:', this.selectedTemplateId, 'for role:', this.roleType);
                        break;
                    }
                }
            }
        },

        setCustomTemplateId() {
            // Find the Custom Permissions template ID
            const templateSelect = document.querySelector('select[name="template_id"]');
            if (templateSelect) {
                const options = templateSelect.querySelectorAll('option');
                for (let option of options) {
                    if (option.dataset.templateName === 'Custom Permissions') {
                        this.selectedTemplateId = option.value;
                        console.log('Set selectedTemplateId to Custom Permissions:', this.selectedTemplateId);
                        break;
                    }
                }
            }
        },

        openAddModal() {
            console.log('openAddModal called');
            this.showAddModal = true;
            this.selectedTemplate = 'full_admin_access'; // Set default to Full Admin Access
            this.selectedTemplateId = ''; // Will be set when template is selected
            // Reset modal state
            this.currentStep = 1;
            this.roleType = '';
            this.roleOption = '';
            this.selectedPermissions = [];
            
            // Set the default template ID after a short delay to ensure DOM is ready
            // Only set default template if role option is default
            setTimeout(() => {
                if (this.roleOption === 'default') {
                    this.setDefaultTemplateId();
                }
            }, 100);
            this.userInfo = {
                firstName: '',
                lastName: '',
                middleName: '',
                suffix: '',
                addressLine1: '',
                addressLine2: '',
                city: '',
                age: '',
                username: '',
                email: '',
                password: '',
                confirmPassword: ''
            };
            this.passwordError = '';
            this.currentPage = 0;
            
            console.log('Modal state reset:', {
                currentStep: this.currentStep,
                roleType: this.roleType,
                roleOption: this.roleOption
            });
            
            // Initialize permissions for the modal
            const allPermissions = [];
            <?php if (isset($all_permissions_by_category) && is_array($all_permissions_by_category)): ?>
                <?php foreach ($all_permissions_by_category as $category => $perms): ?>
                    <?php foreach ($perms as $permission): ?>
                        allPermissions.push({
                            name: '<?php echo addslashes($permission['name']); ?>',
                            description: '<?php echo addslashes($permission['description']); ?>',
                            category: '<?php echo addslashes($category); ?>',
                            displayName: '<?php echo addslashes($permission['description']); ?>'
                        });
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            window.allPermissions = allPermissions;
            
            const form = document.querySelector('#addUserForm');
            if (form) {
                form.reset();
            }
        },

        closeAddModal() {
            this.showAddModal = false;
            const form = document.querySelector('#addUserForm');
            if (form) {
                form.reset();
            }
        },

        showConfirmation(action, userId) {
            let config = {};
            switch(action) {
                case 'reset':
                    config = {
                        title: 'Reset Password?',
                        message: 'This will generate a new temporary password and send it to the user\'s email.',
                        actionUrl: 'reset_password.php',
                        buttonColor: 'yellow',
                        buttonText: 'Reset Password'
                    };
                    break;
                case 'ban':
                    // Show ban reason modal instead of simple confirmation
                    this.openBanReasonModal(userId);
                    return;
                case 'delete':
                    // Show delete reason modal instead of simple confirmation
                    this.openDeleteReasonModal(userId);
                    return;
                case 'unban':
                    config = {
                        title: 'Unban User?',
                        message: 'This will restore the user\'s access to their account.',
                        actionUrl: 'unban_user.php',
                        buttonColor: 'green',
                        buttonText: 'Unban User'
                    };
                    break;
                case 'delete':
                    config = {
                        title: 'Move to Deleted Users?',
                        message: 'The user will be moved to the deleted users section.',
                        actionUrl: 'delete_user.php',
                        buttonColor: 'red',
                        buttonText: 'Move to Deleted'
                    };
                    break;
                case 'restore':
                    config = {
                        title: 'Restore User?',
                        message: 'The user’s account and all related data will be restored.',
                        actionUrl: 'restore_user.php',
                        buttonColor: 'green',
                        buttonText: 'Restore User'
                    };
                    break;
                case 'permanent-delete':
                    config = {
                        title: 'Permanently Delete User?',
                        message: 'This action cannot be undone. All user data will be permanently removed.',
                        actionUrl: 'permanent_delete_user.php',
                        buttonColor: 'red',
                        buttonText: 'Delete Permanently'
                    };
                    break;
            }

            const formData = new FormData();
            formData.append('user_id', userId);

            this.confirmAction = {
                ...config,
                userId: userId,
                formData: formData
            };

            this.showConfirmModal = true;
        },
        
        // Modal-specific methods
        nextStep() {
            console.log('nextStep called, currentStep:', this.currentStep);
            console.log('roleType:', this.roleType, 'roleOption:', this.roleOption);
            console.log('Alpine.js component state:', {
                currentStep: this.currentStep,
                roleType: this.roleType,
                roleOption: this.roleOption,
                showAddModal: this.showAddModal
            });
            
            if (this.currentStep === 1) {
                if (!this.roleType || !this.roleOption) {
                    console.log('Validation failed: missing role or option');
                    Swal.fire({
                        title: 'Please Select Role',
                        text: 'Please choose a role type and option before proceeding',
                        icon: 'warning',
                        confirmButtonColor: '#0284c7'
                    });
                    return;
                }
                console.log('Step 1 validation passed, moving to step 2');
            } 
            else if (this.currentStep === 2) {
                if (this.roleOption === 'custom' && this.selectedPermissions.length === 0) {
                    Swal.fire({
                        title: 'Please Select Permissions',
                        text: 'Please select at least one permission for custom role',
                        icon: 'warning',
                        confirmButtonColor: '#0284c7'
                    });
                    return;
                }
            }
            else if (this.currentStep === 3) {
                // Basic validation for required fields
                if (!this.userInfo.firstName || !this.userInfo.lastName || 
                    !this.userInfo.age || !this.userInfo.username || !this.userInfo.email ||
                    !this.userInfo.password || !this.userInfo.confirmPassword) {
                    Swal.fire({
                        title: 'Missing Information',
                        text: 'Please fill in all required fields',
                        icon: 'error',
                        confirmButtonColor: '#0284c7'
                    });
                    return;
                }
                
                // Password matching validation
                if (this.validatePasswords()) {
                    return;
                }
            }
            
            this.currentStep++;
            console.log('Step incremented to:', this.currentStep);
        },
        
        validatePasswords() {
            if (!this.userInfo.password || !this.userInfo.confirmPassword) {
                this.passwordError = '';
                return false;
            }
            
            if (this.userInfo.password.length < 8) {
                this.passwordError = 'Password must be at least 8 characters';
                return true;
            }
            
            if (this.userInfo.password !== this.userInfo.confirmPassword) {
                this.passwordError = 'Passwords do not match';
                return true;
            }
            
            this.passwordError = '';
            return false;
        },
        
        updateSelectedPermissions() {
            const checkboxes = document.querySelectorAll('input[name="permissions[]"]:checked');
            this.selectedPermissions = Array.from(checkboxes).map(cb => cb.value);
        },
        
        toggleAllPermissions() {
            const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            this.updateSelectedPermissions();
        },
        
        toggleAllAdminPermissions() {
            const checkboxes = document.querySelectorAll('input[name="permissions[]"][data-category^="admin_"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            this.updateSelectedPermissions();
        },
        
        submitForm(event) {
            event.preventDefault();
            const form = event.target.closest('form');
            
            // Show confirmation dialog
            Swal.fire({
                title: 'Confirm User Creation',
                html: `
                    <div class="text-left">
                        <p class="mb-2"><strong>Username:</strong> ${this.userInfo.username}</p>
                        <p class="mb-2"><strong>Email:</strong> ${this.userInfo.email}</p>
                        <p class="mb-2"><strong>Name:</strong> ${this.userInfo.firstName} ${this.userInfo.lastName}</p>
                        <p class="mb-2"><strong>Role:</strong> ${this.roleType}</p>
                        <p class="mb-2"><strong>Permissions:</strong> ${this.roleOption === 'default' ? 'Default Permission template (62 permissions)' : this.selectedPermissions.length + ' permissions selected'}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0284c7',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Creating User',
                        text: 'Please wait while we create the user account and send verification email...',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit form via AJAX
                    const formData = new FormData(form);
                    
                    // Handle template_id based on role option
                    if (this.roleOption === 'default') {
                        // For default permissions, ensure template_id is set
                        if (!this.selectedTemplateId) {
                            this.setDefaultTemplateId();
                        }
                        formData.set('template_id', this.selectedTemplateId);
                    } else if (this.roleOption === 'custom') {
                        // For custom permissions, find and use the Custom Permissions template
                        this.setCustomTemplateId();
                        if (this.selectedTemplateId) {
                            formData.set('template_id', this.selectedTemplateId);
                        }
                    }
                    
                    // Debug: Log the template_id being sent
                    console.log('Role option:', this.roleOption);
                    console.log('Template ID being sent:', this.selectedTemplateId);
                    console.log('Form data template_id:', formData.get('template_id'));
                    
                    fetch('create_user_with_otp.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let verificationMessage = '';
                            if (data.role === 'student') {
                                verificationMessage = `
                                    <div class="text-left">
                                        <p class="mb-2">✅ User account has been created.</p>
                                        <p class="mb-2">📧 Verification email has been sent to: <strong>${data.email}</strong></p>
                                        <p class="mb-2">🔐 The user must verify their email before they can log in.</p>
                                        <p class="text-sm text-gray-600 mt-4">The user will receive a 6-digit OTP code in their email to complete the verification process.</p>
                                    </div>
                                `;
                            } else {
                                verificationMessage = `
                                    <div class="text-left">
                                        <p class="mb-2">✅ ${data.role.charAt(0).toUpperCase() + data.role.slice(1)} account has been created.</p>
                                        <p class="mb-2">📧 Verification email has been sent to: <strong>${data.email}</strong></p>
                                        <p class="mb-2">📱 Phone verification will be required: <strong>${data.phone_number}</strong></p>
                                        <p class="mb-2">🔐 The user must verify BOTH email and phone before they can log in.</p>
                                        <p class="text-sm text-gray-600 mt-4">
                                            <strong>Verification Process:</strong><br>
                                            1. User receives email OTP and verifies email<br>
                                            2. User receives SMS OTP and verifies phone<br>
                                            3. User can then access the system
                                        </p>
                                    </div>
                                `;
                            }
                            
                            Swal.fire({
                                title: 'User Created Successfully!',
                                html: verificationMessage,
                                icon: 'success',
                                confirmButtonColor: '#0284c7',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Close modal and refresh page
                                this.closeAddModal();
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error Creating User',
                                text: data.message || 'An error occurred while creating the user.',
                                icon: 'error',
                                confirmButtonColor: '#0284c7'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred while creating the user. Please try again.',
                            icon: 'error',
                            confirmButtonColor: '#0284c7'
                        });
                    });
                }
            });
        },
        
        getPermissionDisplayName(permissionName) {
            const permissionNames = {
                'nav_dashboard': 'Dashboard Access',
                'nav_course_management': 'Course Management',
                'nav_user_management': 'User Management',
                'nav_reports': 'Reports Section',
                'nav_usage_analytics': 'Usage Analytics Reports',
                'nav_performance_logs': 'Performance Logs',
                'nav_login_activity': 'Login Activity',
                'nav_security_warnings': 'Security Warnings',
                'nav_audit_trails': 'Audit Trails',
                'nav_user_roles_report': 'User Roles Reports',
                'nav_error_logs': 'Error Logs',
                'nav_payments': 'Payment History',
                'nav_content_management': 'Content Management',
                'nav_teacher_dashboard': 'Teacher Dashboard',
                'nav_teacher_courses': 'Teacher Courses',
                'nav_teacher_create_module': 'Create New Modules',
                'nav_teacher_placement_test': 'Placement Test',
                'nav_teacher_settings': 'Teacher Settings',
                'nav_teacher_content': 'Teacher Content',
                'nav_teacher_students': 'Student Management',
                'nav_teacher_reports': 'Teacher Reports',
                'nav_teacher_audit': 'Teacher Audit Trail',
                'nav_teacher_courses_by_category': 'Courses by Category',
                'nav_hybrid_users': 'User Management (Hybrid)',
                'nav_hybrid_reports': 'Admin Reports (Hybrid)',
                'nav_hybrid_courses': 'Course Management (Hybrid)',
                'nav_student_dashboard': 'Student Dashboard',
                'nav_student_courses': 'Student Courses',
                'nav_student_learning': 'Learning Materials',
                'system_login': 'System Login',
                'system_logout': 'System Logout',
                'system_profile': 'Profile Management',
                // Dashboard Card Permissions
                'dashboard_view_students_card': 'View Students Card',
                'dashboard_view_teachers_card': 'View Teachers Card',
                'dashboard_view_modules_card': 'View Modules Card',
                'dashboard_view_revenue_card': 'View Revenue Card',
                'dashboard_view_course_completion': 'View Course Completion Report',
                'dashboard_view_user_retention': 'View User Retention Report',
                'dashboard_view_sales_reports': 'View Sales Reports',
                'dashboard_view_completion_metrics': 'View Completion Metrics',
                'dashboard_view_retention_metrics': 'View Retention Metrics',
                'dashboard_view_sales_metrics': 'View Sales Metrics'
            };
            return permissionNames[permissionName] || permissionName;
        },
        
        // Handle confirmation modal actions
        handleConfirmAction() {
            if (this.confirmAction.actionUrl === 'reset_password.php') {
                this.handlePasswordReset();
            } else if (this.confirmAction.actionUrl === 'unban_user.php') {
                this.handleUnbanUser();
            } else {
                this.handleOtherAction();
            }
        },
        
        // Handle password reset action
        handlePasswordReset() {
            fetch(this.confirmAction.actionUrl, {
                method: 'POST',
                body: this.confirmAction.formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                this.showConfirmModal = false;
                if (data.success) {
                    this.showPasswordResetSuccess(data);
                } else {
                    Swal.fire({
                        title: 'Reset Failed',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#0284c7'
                    });
                }
            })
            .catch(error => {
                this.showConfirmModal = false;
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to reset password. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#0284c7'
                });
            });
        },
        
        // Handle other actions (restore, etc.)
        handleOtherAction() {
            fetch(this.confirmAction.actionUrl, {
                method: 'POST',
                body: this.confirmAction.formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                this.showConfirmModal = false;
                if (data.success) {
                    if (this.confirmAction.actionUrl === 'restore_user.php') {
                        // Show restore success modal
                        Swal.fire({
                            title: 'User Restored Successfully!',
                            html: `
                                <div class='text-left'>
                                    <div class='mb-4 p-4 bg-green-50 border border-green-200 rounded-lg'>
                                        <div class='flex items-center mb-2'>
                                            <svg class='w-5 h-5 text-green-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'/>
                                            </svg>
                                            <span class='font-semibold text-green-800'>User Restored</span>
                                        </div>
                                        <p class='text-sm text-green-700 mb-2'>User: <strong>${data.user.username}</strong> (${data.user.role})</p>
                                        <p class='text-sm text-green-700 mb-2'>Email: <strong>${data.user.email}</strong></p>
                                        <p class='text-sm text-green-700'>Restored at: <strong>${data.timestamp}</strong></p>
                                    </div>
                                    <div class='p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                                        <div class='flex items-center mb-2'>
                                            <svg class='w-5 h-5 text-blue-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                                            </svg>
                                            <span class='font-semibold text-blue-800'>Notification Sent</span>
                                        </div>
                                        <p class='text-sm text-blue-700'>
                                            ${data.email_sent ? 'Email notification sent successfully to the user' : 'Email delivery failed - please contact the user manually'}
                                        </p>
                                    </div>
                                </div>
                            `,
                            icon: 'success',
                            confirmButtonColor: '#10b981',
                            confirmButtonText: 'Done',
                            width: '600px',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown animate__faster'
                            }
                        }).then(() => {
                            window.location.reload();
                        });
                    } else if (this.confirmAction.actionUrl === 'permanent_delete_user.php') {
                        // Show permanent delete success modal
                        Swal.fire({
                            title: 'User Permanently Deleted!',
                            html: `
                                <div class='text-left'>
                                    <div class='mb-4 p-4 bg-red-50 border border-red-200 rounded-lg'>
                                        <div class='flex items-center mb-2'>
                                            <svg class='w-5 h-5 text-red-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'/>
                                            </svg>
                                            <span class='font-semibold text-red-800'>User Permanently Deleted</span>
                                        </div>
                                        <p class='text-sm text-red-700 mb-2'>User: <strong>${data.user.username}</strong> (${data.user.role})</p>
                                        <p class='text-sm text-red-700 mb-2'>Email: <strong>${data.user.email}</strong></p>
                                        <p class='text-sm text-red-700'>Deleted at: <strong>${data.timestamp}</strong></p>
                                    </div>
                                    <div class='mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
                                        <h4 class='font-semibold text-yellow-800 mb-2'>⚠️ Important Notice:</h4>
                                        <p class='text-sm text-yellow-700'>
                                            This user and all their data have been permanently removed from the system. 
                                            This action cannot be undone.
                                        </p>
                                    </div>
                                    <div class='p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                                        <div class='flex items-center mb-2'>
                                            <svg class='w-5 h-5 text-blue-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                                            </svg>
                                            <span class='font-semibold text-blue-800'>Final Notification Sent</span>
                                        </div>
                                        <p class='text-sm text-blue-700'>
                                            ${data.email_sent ? 'Final notification email sent to the user' : 'Email delivery failed - user has been permanently deleted'}
                                        </p>
                                    </div>
                                </div>
                            `,
                            icon: 'warning',
                            confirmButtonColor: '#dc2626',
                            confirmButtonText: 'Done',
                            width: '600px',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown animate__faster'
                            }
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        // Generic success for other actions
                        window.location.reload();
                    }
                } else {
                    // Handle deadline restriction for permanent delete
                    if (this.confirmAction.actionUrl === 'permanent_delete_user.php' && data.deadline) {
                        Swal.fire({
                            title: 'Cannot Delete Yet',
                            html: `
                                <div class='text-left'>
                                    <div class='mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
                                        <div class='flex items-center mb-2'>
                                            <svg class='w-5 h-5 text-yellow-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                                            </svg>
                                            <span class='font-semibold text-yellow-800'>30-Day Grace Period</span>
                                        </div>
                                        <p class='text-sm text-yellow-700 mb-2'>User: <strong>${data.user.username}</strong></p>
                                        <p class='text-sm text-yellow-700 mb-2'>Email: <strong>${data.user.email}</strong></p>
                                        <p class='text-sm text-yellow-700'>Days Remaining: <strong>${data.days_remaining} day${data.days_remaining !== 1 ? 's' : ''}</strong></p>
                                    </div>
                                    <div class='p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                                        <h4 class='font-semibold text-blue-800 mb-2'>Permanent Deletion Deadline:</h4>
                                        <p class='text-sm text-blue-700'><strong>${data.deadline}</strong></p>
                                        <p class='text-sm text-blue-700 mt-2'>The user can be permanently deleted after this date.</p>
                                    </div>
                                </div>
                            `,
                            icon: 'warning',
                            confirmButtonColor: '#f59e0b',
                            confirmButtonText: 'Understood',
                            width: '500px',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown animate__faster'
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Action Failed',
                            text: data.message || 'Unknown error occurred',
                            icon: 'error',
                            confirmButtonColor: '#0284c7'
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to perform action. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#0284c7'
                });
            });
        },
        
        // Handle unban user action
        handleUnbanUser() {
            fetch(this.confirmAction.actionUrl, {
                method: 'POST',
                body: this.confirmAction.formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                this.showConfirmModal = false;
                if (data.success) {
                    Swal.fire({
                        title: 'User Unbanned Successfully!',
                        html: `
                            <div class='text-left'>
                                <div class='mb-4 p-4 bg-green-50 border border-green-200 rounded-lg'>
                                    <div class='flex items-center mb-2'>
                                        <svg class='w-5 h-5 text-green-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'/>
                                        </svg>
                                        <span class='font-semibold text-green-800'>User Unbanned</span>
                                    </div>
                                    <p class='text-sm text-green-700 mb-2'>User: <strong>${data.user.username}</strong> (${data.user.role})</p>
                                    <p class='text-sm text-green-700 mb-2'>Email: <strong>${data.user.email}</strong></p>
                                    <p class='text-sm text-green-700'>Unbanned at: <strong>${data.timestamp}</strong></p>
                                </div>
                                <div class='p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                                    <div class='flex items-center mb-2'>
                                        <svg class='w-5 h-5 text-blue-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                                        </svg>
                                        <span class='font-semibold text-blue-800'>Notification Sent</span>
                                    </div>
                                    <p class='text-sm text-blue-700'>
                                        ${data.email_sent ? 'Email notification sent successfully to the user' : 'Email delivery failed - please contact the user manually'}
                                    </p>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'Done',
                        width: '600px',
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown animate__faster'
                        }
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    // Handle specific error cases
                    if (data.message === 'User is not banned') {
                        Swal.fire({
                            title: 'User Not Banned',
                            text: 'This user is not currently banned. No action needed.',
                            icon: 'info',
                            confirmButtonColor: '#10b981'
                        }).then(() => {
                            this.showConfirmModal = false;
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Unban Failed',
                            text: data.message || 'Unknown error occurred',
                            icon: 'error',
                            confirmButtonColor: '#10b981',
                            footer: data.debug ? `Debug: ${data.debug}` : ''
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Unban error:', error);
                this.showConfirmModal = false;
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to unban user. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#10b981',
                    footer: `Error: ${error.message}`
                });
            });
        },
        
        // Show password reset success modal
        showPasswordResetSuccess(data) {
            Swal.fire({
                title: 'Password Reset Successful!',
                html: `
                    <div class='text-left'>
                        <div class='mb-4 p-4 bg-green-50 border border-green-200 rounded-lg'>
                            <div class='flex items-center mb-2'>
                                <svg class='w-5 h-5 text-green-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'/>
                                </svg>
                                <span class='font-semibold text-green-800'>Password Reset Complete</span>
                            </div>
                            <p class='text-sm text-green-700 mb-2'>User: <strong>${data.user.username}</strong> (${data.user.role})</p>
                            <p class='text-sm text-green-700 mb-2'>Email: <strong>${data.user.email}</strong></p>
                            <p class='text-sm text-green-700'>Reset at: <strong>${data.timestamp}</strong></p>
                        </div>
                        <div class='mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                            <h4 class='font-semibold text-blue-800 mb-2'>Temporary Password:</h4>
                            <div class='flex items-center justify-between bg-white p-3 rounded border'>
                                <code class='text-lg font-mono text-blue-900' id='tempPassword'>${data.temp_password}</code>
                                <button onclick='copyToClipboard("${data.temp_password}")' class='ml-2 px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700'>
                                    Copy
                                </button>
                            </div>
                        </div>
                        <div class='p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
                            <div class='flex items-center mb-2'>
                                <svg class='w-5 h-5 text-yellow-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                                </svg>
                                <span class='font-semibold text-yellow-800'>Important Instructions</span>
                            </div>
                            <ul class='text-sm text-yellow-700 space-y-1'>
                                <li>• The user must use this temporary password to log in</li>
                                <li>• They will be prompted to create a new password on first login</li>
                                <li>• ${data.email_sent ? 'Email sent successfully' : 'Email delivery failed - please share the password manually'}</li>
                            </ul>
                        </div>
                    </div>
                `,
                icon: 'success',
                confirmButtonColor: '#0284c7',
                confirmButtonText: 'Done',
                width: '600px',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown animate__faster'
                }
            }).then(() => {
                window.location.reload();
            });
        },
        
        // Show ban reason modal
        openBanReasonModal(userId) {
            // Check if user is already banned by looking at the table row
            const userRow = document.querySelector(`[data-user-id="${userId}"]`);
            if (userRow) {
                const statusCell = userRow.querySelector('[data-status]');
                if (statusCell && statusCell.textContent.trim() === 'Banned') {
                    Swal.fire({
                        title: 'User Already Banned',
                        text: 'This user is already banned. Use the unban button to restore their access.',
                        icon: 'warning',
                        confirmButtonColor: '#dc2626'
                    });
                    return;
                }
            }
            
            this.banUserId = userId;
            this.banReason = '';
            this.showBanReasonModal = true;
        },
        
        // Close ban reason modal
        closeBanReasonModal() {
            this.showBanReasonModal = false;
            this.banReason = '';
            this.banUserId = null;
        },
        
        // Show delete reason modal
        openDeleteReasonModal(userId) {
            this.deleteUserId = userId;
            this.deleteReason = '';
            this.showDeleteReasonModal = true;
        },
        
        // Close delete reason modal
        closeDeleteReasonModal() {
            this.showDeleteReasonModal = false;
            this.deleteReason = '';
            this.deleteUserId = null;
        },
        
        // Handle ban user with reason
        handleBanUser() {
            if (!this.banReason.trim()) {
                Swal.fire({
                    title: 'Ban Reason Required',
                    text: 'Please provide a reason for banning this user.',
                    icon: 'warning',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
            
            if (this.banReason.length > 500) {
                Swal.fire({
                    title: 'Reason Too Long',
                    text: 'Ban reason must be 500 characters or less.',
                    icon: 'warning',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
            
            // Show loading state
            Swal.fire({
                title: 'Banning User...',
                text: 'Please wait while we process the ban request',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create form data
            const formData = new FormData();
            formData.append('user_id', this.banUserId);
            formData.append('ban_reason', this.banReason);
            
            // Make AJAX request
            fetch('ban_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Show success modal
                    Swal.fire({
                        title: 'User Banned Successfully!',
                        html: `
                            <div class='text-left'>
                                <div class='mb-4 p-4 bg-red-50 border border-red-200 rounded-lg'>
                                    <div class='flex items-center mb-2'>
                                        <svg class='w-5 h-5 text-red-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                                        </svg>
                                        <span class='font-semibold text-red-800'>User Banned</span>
                                    </div>
                                    <p class='text-sm text-red-700 mb-2'>User: <strong>${data.user.username}</strong> (${data.user.role})</p>
                                    <p class='text-sm text-red-700 mb-2'>Email: <strong>${data.user.email}</strong></p>
                                    <p class='text-sm text-red-700'>Banned at: <strong>${data.timestamp}</strong></p>
                                </div>
                                <div class='mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
                                    <h4 class='font-semibold text-yellow-800 mb-2'>Ban Reason:</h4>
                                    <p class='text-sm text-yellow-700 italic'>"${data.ban_reason}"</p>
                                </div>
                                <div class='p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                                    <div class='flex items-center mb-2'>
                                        <svg class='w-5 h-5 text-blue-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                                        </svg>
                                        <span class='font-semibold text-blue-800'>Notification Sent</span>
                                    </div>
                                    <p class='text-sm text-blue-700'>
                                        ${data.email_sent ? 'Email notification sent successfully to the user' : 'Email delivery failed - please contact the user manually'}
                                    </p>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#dc2626',
                        confirmButtonText: 'Done',
                        width: '600px',
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown animate__faster'
                        }
                    }).then(() => {
                        this.closeBanReasonModal();
                        window.location.reload();
                    });
                } else {
                    // Handle specific error cases
                    if (data.message === 'User is already banned') {
                        Swal.fire({
                            title: 'User Already Banned',
                            text: 'This user is already banned. Use the unban button to restore their access.',
                            icon: 'warning',
                            confirmButtonColor: '#dc2626'
                        }).then(() => {
                            this.closeBanReasonModal();
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Ban Failed',
                            text: data.message || 'Unknown error occurred',
                            icon: 'error',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to ban user. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#dc2626'
                });
            });
        },
        
        // Handle delete user with reason
        handleDeleteUser() {
            if (!this.deleteReason.trim()) {
                Swal.fire({
                    title: 'Deletion Reason Required',
                    text: 'Please provide a reason for deleting this user.',
                    icon: 'warning',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
            
            if (this.deleteReason.length > 500) {
                Swal.fire({
                    title: 'Reason Too Long',
                    text: 'Deletion reason must be 500 characters or less.',
                    icon: 'warning',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
            
            // Show loading state
            Swal.fire({
                title: 'Moving User to Deleted...',
                text: 'Please wait while we process the deletion request',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create form data
            const formData = new FormData();
            formData.append('user_id', this.deleteUserId);
            formData.append('deletion_reason', this.deleteReason);
            
            // Make AJAX request
            fetch('delete_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Show success modal
                    Swal.fire({
                        title: 'User Moved to Deleted Successfully!',
                        html: `
                            <div class='text-left'>
                                <div class='mb-4 p-4 bg-red-50 border border-red-200 rounded-lg'>
                                    <div class='flex items-center mb-2'>
                                        <svg class='w-5 h-5 text-red-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                                        </svg>
                                        <span class='font-semibold text-red-800'>User Moved to Deleted</span>
                                    </div>
                                    <p class='text-sm text-red-700 mb-2'>User: <strong>${data.user.username}</strong> (${data.user.role})</p>
                                    <p class='text-sm text-red-700 mb-2'>Email: <strong>${data.user.email}</strong></p>
                                    <p class='text-sm text-red-700'>Deleted at: <strong>${data.timestamp}</strong></p>
                                </div>
                                <div class='mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
                                    <h4 class='font-semibold text-yellow-800 mb-2'>Deletion Reason:</h4>
                                    <p class='text-sm text-yellow-700 italic'>"${data.deletion_reason}"</p>
                                </div>
                                <div class='mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                                    <h4 class='font-semibold text-blue-800 mb-2'>Restoration Information:</h4>
                                    <p class='text-sm text-blue-700 mb-2'>Restoration Deadline: <strong>${data.restoration_deadline}</strong></p>
                                    <p class='text-sm text-blue-700'>The user can be restored before this date</p>
                                </div>
                                <div class='p-4 bg-green-50 border border-green-200 rounded-lg'>
                                    <div class='flex items-center mb-2'>
                                        <svg class='w-5 h-5 text-green-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                                        </svg>
                                        <span class='font-semibold text-green-800'>Notification Sent</span>
                                    </div>
                                    <p class='text-sm text-green-700'>
                                        ${data.email_sent ? 'Email notification sent successfully to the user' : 'Email delivery failed - please contact the user manually'}
                                    </p>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#dc2626',
                        confirmButtonText: 'Done',
                        width: '600px',
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown animate__faster'
                        }
                    }).then(() => {
                        this.closeDeleteReasonModal();
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Deletion Failed',
                        text: data.message || 'Unknown error occurred',
                        icon: 'error',
                        confirmButtonColor: '#dc2626'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete user. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#dc2626'
                });
            });
        },
        
        // Quick reset password function - with confirmation modal
        quickResetPassword(userId, username) {
            // Show confirmation modal first
            Swal.fire({
                title: 'Quick Reset Password?',
                html: `
                    <div class="text-left">
                        <p class="mb-3">This will generate a new temporary password for <strong>${username}</strong> and send it to their email.</p>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                <span class="text-yellow-800 font-medium">Important:</span>
                            </div>
                            <ul class="text-yellow-700 text-sm mt-2 ml-7 space-y-1">
                                <li>• A new temporary password will be generated</li>
                                <li>• The user will receive it via email</li>
                                <li>• Previously generated passwords cannot be reused</li>
                            </ul>
                        </div>
                        <p class="text-sm text-gray-600">Are you sure you want to proceed?</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#eab308',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Reset Password',
                cancelButtonText: 'Cancel',
                focusConfirm: false
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performQuickReset(userId, username);
                }
            });
        },
        
        // Perform the actual password reset
        performQuickReset(userId, username) {
            // Show loading state
            Swal.fire({
                title: 'Resetting Password...',
                text: 'Please wait while we generate a new password for ' + username,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create form data
            const formData = new FormData();
            formData.append('user_id', userId);
            
            // Make AJAX request
            fetch('reset_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Show success modal with temporary password
                    Swal.fire({
                        title: 'Password Reset Complete!',
                        html: `
                            <div class='text-left'>
                                <div class='mb-4 p-4 bg-green-50 border border-green-200 rounded-lg'>
                                    <div class='flex items-center mb-2'>
                                        <svg class='w-5 h-5 text-green-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'/>
                                        </svg>
                                        <span class='font-semibold text-green-800'>Password Reset Complete</span>
                                    </div>
                                    <p class='text-sm text-green-700 mb-2'>User: <strong>${data.user.username}</strong> (${data.user.role})</p>
                                    <p class='text-sm text-green-700 mb-2'>Email: <strong>${data.user.email}</strong></p>
                                    <p class='text-sm text-green-700'>Reset at: <strong>${data.timestamp}</strong></p>
                                </div>
                                <div class='mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg'>
                                    <h4 class='font-semibold text-blue-800 mb-2'>Temporary Password:</h4>
                                    <div class='flex items-center justify-between bg-white p-3 rounded border'>
                                        <code class='text-lg font-mono text-blue-900' id='tempPassword'>${data.temp_password}</code>
                                        <button onclick='copyToClipboard(\"${data.temp_password}\")' class='ml-2 px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700'>
                                            Copy
                                        </button>
                                    </div>
                                </div>
                                <div class='p-4 bg-yellow-50 border border-yellow-200 rounded-lg'>
                                    <div class='flex items-center mb-2'>
                                        <svg class='w-5 h-5 text-yellow-600 mr-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                                        </svg>
                                        <span class='font-semibold text-yellow-800'>Important Instructions</span>
                                    </div>
                                    <ul class='text-sm text-yellow-700 space-y-1'>
                                        <li>• The user must use this temporary password to log in</li>
                                        <li>• They will be prompted to create a new password on first login</li>
                                        <li>• ${data.email_sent ? 'Email sent successfully' : 'Email delivery failed - please share the password manually'}</li>
                                    </ul>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#0284c7',
                        confirmButtonText: 'Done',
                        width: '600px',
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown animate__faster'
                        }
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Reset Failed',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#0284c7'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                console.error('Error details:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to reset password. Please check the console for details.',
                    icon: 'error',
                    confirmButtonColor: '#0284c7'
                });
            });
        }
    }));
  });
  
  </script>
</head>
<body class="h-full" x-data="userManagement" x-init="init()">
    
    <!-- Alpine.js Test -->
    <div x-data="{ test: 'Alpine.js is working!' }" x-show="false" x-text="test"></div>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="js/add_user_modal.js"></script>
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
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        .card-hover {
            transition: all 0.2s ease-in-out;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .tooltip-trigger {
            position: relative;
        }
        .tooltip-trigger:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 8px;
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 10;
        }
        
        /* Notification Bell Styles */
        .notification-bell {
            position: relative;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .notification-bell:hover {
            transform: scale(1.1);
        }
        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .notification-dropdown.show {
            display: block;
        }
    </style>
</head>
<body class="h-full bg-gray-100">
    <?php echo $notificationSystem->renderNotificationAssets(); ?>
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-primary-600 to-primary-800 text-white">
                <span class="text-2xl font-bold">Admin Portal</span>
            </div>
            
            <!-- Admin Profile -->
            <?php require_once __DIR__ . '/includes/sidebar_profile.php'; ?>

            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]">
                <div class="space-y-1">
                    <?php 
                    // Dashboard permissions check
                    $dashboard_permissions = [
                        'dashboard_view_students_card', 'dashboard_view_teachers_card', 'dashboard_view_modules_card', 'dashboard_view_revenue_card',
                        'dashboard_view_course_completion', 'dashboard_view_user_retention', 'dashboard_view_sales_reports',
                        'dashboard_view_completion_metrics', 'dashboard_view_retention_metrics', 'dashboard_view_sales_metrics'
                    ];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $dashboard_permissions)): ?>
                    <a href="admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    <?php 
                    // Course Management permissions check
                    $course_permissions = ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $course_permissions)): ?>
                    <a href="course_management_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>
                    <?php 
                    // User Management permissions check
                    $user_permissions = ['user_add_new', 'user_reset_password', 'user_ban_user', 'user_move_to_deleted', 'user_permanently_delete', 'user_restore_deleted'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $user_permissions)): ?>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>
                    <!-- Reports Dropdown -->
                    <?php 
                    // Reports permissions check
                    $reports_permissions = [
                        'analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 
                        'analytics_view_role_breakdown', 'analytics_view_activity_data', 
                        'user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 
                        'user_roles_view_details', 'login_activity_view_metrics', 'login_activity_view_report'
                    ];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $reports_permissions)): ?>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full text-gray-700 hover:bg-gray-100 focus:outline-none" :class="open ? 'bg-primary-50 text-primary-700 font-medium' : ''">
                         <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>   
                            <span class="flex-1 text-left">Reports</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 ml-auto transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="mt-1 ml-4 space-y-1" x-cloak>
                            <?php 
                            // Usage Analytics permissions check
                            $analytics_permissions = ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $analytics_permissions)): ?>
                            <a href="usage-analytics.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
        
                            Usage Analytics
                            </a>
                            <?php endif; ?>
                            <?php 
                            // User Roles Report permissions check
                            $user_roles_permissions = ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $user_roles_permissions)): ?>
                            <a href="user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
        
                            User Roles Breakdown
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Login Activity permissions check
                            $login_activity_permissions = ['login_activity_view_metrics', 'login_activity_view_report', 'login_activity_view', 'login_activity_export_pdf'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $login_activity_permissions)): ?>
                            <a href="login-activity.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                     <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>      
       
                            Login Activity
                            </a>
                            <?php endif; ?>

                            <?php
                            $security_permissions = ['security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $security_permissions)): ?>
                            <a href="security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-alert-icon lucide-shield-alert"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>     
                            Security Warnings
                            </a>
                            <?php endif; ?>

                            <?php
                            // Audit Trails permissions check
                            $audit_permissions = ['audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $audit_permissions)): ?>                            <a href="audit-trails.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
                            Audit Trails
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Performance Logs permissions check
                            $performance_permissions = ['nav_performance_logs', 'performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $performance_permissions)): ?>
                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
        
                            System Performance Logs
                            </a>
                            <?php endif; ?>
                            <?php 
                            $errorlogs_permissions = ['error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $errorlogs_permissions)): ?>
                            <a href="error-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            Error Logs
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php
                    // Payment History permissions check
                    $payment_permissions = ['payment_view_history', 'payment_export_invoice_pdf', 'payment_export_pdf'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $payment_permissions)): ?>
                    <a href="payment_history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>
                    <?php
                    $content_permission = ['content_manage_announcement', 'content_manage_terms', 'content_manage_privacy'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $content_permission)): ?>
                    <a href="../dashboard/contentmanagement/content_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        Content Management
                    </a>
                    <?php endif; ?>
                    <!-- Settings Menu - Available to all admins -->
                    <a href="admin_settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                                <!-- Push logout to bottom -->
                                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="flex items-center justify-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out-icon lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">User Management</h1>
                    
                    <div class="flex items-center gap-4">
                        <?php echo $notificationSystem->renderNotificationBell('System Notifications'); ?>
                        
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <?php if ($alert): ?>
                    <div class="mb-4 bg-<?php echo $alert['icon'] === 'success' ? 'green' : 'red' ?>-100 border border-<?php echo $alert['icon'] === 'success' ? 'green' : 'red' ?>-400 text-<?php echo $alert['icon'] === 'success' ? 'green' : 'red' ?>-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold"><?php echo $alert['title']; ?></strong>
                        <span class="block sm:inline"><?php echo $alert['message']; ?></span>
                    </div>
                <?php endif; ?>

                <!-- User Management Tools -->
                <div class="mb-6 flex flex-wrap gap-4 items-center justify-between">
                    <div class="flex gap-4">
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_add_new')): ?>
                        <button @click="openAddModal" 
                                class="add-user-btn inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            Add User
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    
                    <div class="flex gap-4">
                        <select id="view-filter" class="block rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <option value="active">Active Users</option>
                            <option value="deleted">Deleted Users</option>
                        </select>
                        <!-- Date Range Filter - Dropdown Style (Add before "All Statuses" dropdown) -->
<select id="date-filter" 
        onchange="filterUsersByDatePreset()" 
        class="block rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
    <option value="">All Dates</option>
    <option value="today">Today</option>
    <option value="yesterday">Yesterday</option>
    <option value="last7days">Last 7 Days</option>
    <option value="last30days">Last 30 Days</option>
    <option value="thisweek">This Week</option>
    <option value="thismonth">This Month</option>
    <option value="lastmonth">Last Month</option>
</select>


                        <select id="status-filter" class="block rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <option value="">All Statuses</option>
                            <option value="active" data-view="active">Active</option>
                            <option value="inactive" data-view="active">Inactive</option>
                            <option value="banned" data-view="active">Banned</option>
                            <option value="recently_deleted" data-view="deleted">Recently Deleted</option>
                        </select>
                        
                        <input type="text" id="search" placeholder="Search users..." 
                               class="block rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                    </div>
                </div>

                <!-- Add this right after the "Add New User" button section, before the Users Table -->
<div class="bg-white shadow rounded-lg p-4 mb-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">Sort Users</h3>
        <div class="flex items-center space-x-4">
            <!-- Sort By Dropdown -->
            <div class="flex items-center space-x-2">
                <label for="sortBy" class="text-sm font-medium text-gray-700">Sort by:</label>
                <select id="sortBy" 
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        onchange="sortUsers()">
                    <option value="username">Username</option>
                    <option value="email">Email</option>
                    <option value="role">Role</option>
                    <option value="status">Status</option>
                    <option value="created_at">Date Created</option>
                    <option value="last_login">Last Login</option>
                </select>
            </div>
            
            <!-- Sort Direction Dropdown -->
            <div class="flex items-center space-x-2">
                <label for="sortDirection" class="text-sm font-medium text-gray-700">Order:</label>
                <select id="sortDirection" 
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        onchange="sortUsers()">
                    <option value="asc">Ascending (A-Z, 0-9)</option>
                    <option value="desc">Descending (Z-A, 9-0)</option>
                </select>
            </div>
            
            <!-- Sort Button with Icon -->
            <button onclick="sortUsers()" 
                    class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="m3 16 4 4 4-4"/>
                    <path d="M7 20V4"/>
                    <path d="m21 8-4-4-4 4"/>
                    <path d="M17 4v16"/>
                </svg>
                Apply Sort
            </button>
        </div>
    </div>
</div>


                <!-- Users Table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permission Set</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr class="<?php echo $user['deleted_at'] ? 'bg-gray-50' : ''; ?>" data-user-id="<?php echo $user['id']; ?>">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <?php 
                                                    $picture = getUserProfilePicture($user);
                                                    if ($picture['has_image']): ?>
                                                        <img src="<?php echo htmlspecialchars($picture['image_path']); ?>" 
                                                             alt="Profile Picture" 
                                                             class="h-10 w-10 rounded-full object-cover shadow-sm">
                                                    <?php else: ?>
                                                        <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center">
                                                            <span class="text-primary-600 font-medium text-lg">
                                                                <?php echo htmlspecialchars($picture['initial']); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <button 
                                                                @click="openUserDetailModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['role']); ?>')"
                                                                class="text-primary-600 hover:text-primary-800 hover:underline font-semibold cursor-pointer transition-colors">
                                                                <?php echo htmlspecialchars($user['username']); ?>
                                                            </button>
                                                        </div>
<div class="text-sm text-gray-500">
    <?php echo htmlspecialchars(maskEmail($user['email'])); ?>
</div>
                                                        <?php if ($user['deleted_at']): ?>
                                                            <div class="text-xs text-gray-400">
                                                                Deleted <?php echo $user['days_since_deletion'] == 0 ? 'today' : ($user['days_since_deletion'] == 1 ? 'yesterday' : $user['days_since_deletion'] . ' days ago'); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                switch ($user['role']) {
                                                    case 'admin':
                                                        echo 'bg-purple-100 text-purple-800';
                                                        break;
                                                    case 'teacher':
                                                        echo 'bg-blue-100 text-blue-800';
                                                        break;
                                                    default:
                                                        echo 'bg-green-100 text-green-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500">
                                            <?php 
                                            $permission_type = $user['permission_type'] ?? 'No Access';
                                            $template_names = $user['template_names'] ?? '';
                                            
                                            switch ($permission_type) {
                                                case 'Template':
                                                    echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">' . htmlspecialchars($template_names) . '</span>';
                                                    break;
                                                case 'Custom':
                                                    echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Custom Permissions</span>';
                                                    break;
                                                case 'Hybrid':
                                                    echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">' . htmlspecialchars($template_names) . ' + Custom</span>';
                                                    break;
                                                case 'Default':
                                                    echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">Default ' . ucfirst($user['role']) . '</span>';
                                                    break;
                                                default:
                                                    echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">No Access</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500">
                                            <?php 
                                            $permission_count = 0;
                                            if ($user['permissions']) {
                                                $permission_count = count(explode(',', $user['permissions']));
                                            }
                                            ?>
                                            <button 
                                                onclick="showPermissionsModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['permissions']); ?>', '<?php echo htmlspecialchars($user['username']); ?>')"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors cursor-pointer"
                                                title="Click to view all permissions">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <?php echo $permission_count; ?> permission<?php echo $permission_count !== 1 ? 's' : ''; ?>
                                            </button>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php
                                                $statusClass = '';
                                                $statusIcon = '';
                                                switch ($user['display_status']) {
                                                    case 'active':
                                                        $statusClass = 'text-green-500';
                                                        $statusIcon = '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                        </svg>';
                                                        break;
                                                    case 'recently_deleted':
                                                        $statusClass = 'text-orange-500';
                                                        $statusIcon = '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                        </svg>';
                                                        break;
                                                    case 'deleted':
                                                        $statusClass = 'text-red-500';
                                                        $statusIcon = '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>';
                                                        break;
                                                    // ... other status cases remain the same ...
                                                }
                                                ?>
                                                <div class="<?php echo $statusClass; ?>">
                                                    <?php echo $statusIcon; ?>
                                                </div>
                                                <span class="ml-2 text-sm text-gray-500" data-status="<?php echo $user['display_status']; ?>">
                                                    <?php echo ucfirst($user['display_status']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="last-login-time" data-login-time="<?php echo $user['last_login_at'] ? $user['last_login_at'] : ''; ?>">
                                                <?php echo $user['last_login_at'] ? date('M j, Y g:i A', strtotime($user['last_login_at'])) : 'Never'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end space-x-3">
                                                <?php
                                                // Determine user state
                                                $isDeleted = !empty($user['deleted_at']);
                                                $isBanned = $user['status'] === 'banned';
                                                $isActive = !$isDeleted && !$isBanned;

                                                if ($isActive) {
                                                    // Active user actions
                                                    ?>
                                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_reset_password') || $_SESSION['role'] === 'admin'): ?>
                                                    <button @click="quickResetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                            class="text-yellow-600 hover:text-yellow-900 tooltip-trigger"
                                                            title="Quick Reset Password">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12c0 3.314 2.686 6 6 6s6-2.686 6-6-2.686-6-6-6-6 2.686-6 6zm12 0c0 3.314 2.686 6 6 6s6-2.686 6-6-2.686-6-6-6-6 2.686-6 6z"/><path d="M9 12h6"/><path d="M12 9v6"/></svg>
                                                    </button>
                                                    <?php endif; ?>

                                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_ban_user') || $_SESSION['role'] === 'admin'): ?>
                                                    <?php if ($user['status'] === 'banned'): ?>
                                                    <button @click="showConfirmation('unban', <?php echo $user['id']; ?>)"
                                                            class="text-green-600 hover:text-green-900 tooltip-trigger"
                                                            title="Unban User">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M9 12l2 2 4-4"/>
                                                            <path d="M21 12c.552 0 1-.448 1-1V5c0-.552-.448-1-1-1H3c-.552 0-1 .448-1 1v6c0 .552.448 1 1 1h18z"/>
                                                        </svg>
                                                    </button>
                                                    <?php else: ?>
                                                    <button @click="openBanReasonModal(<?php echo $user['id']; ?>)"
                                                            class="text-red-600 hover:text-red-900 tooltip-trigger"
                                                            title="Ban User">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php endif; ?>

                                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_move_to_deleted')): ?>
                                                    <button @click="showConfirmation('delete', <?php echo $user['id']; ?>)"
                                                            class="text-red-600 hover:text-red-900 tooltip-trigger"
                                                            title="Move to Deleted Users">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                                    </button>
                                                    <?php endif; ?>

                                                <?php } elseif ($isDeleted) { ?>
                                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_restore_deleted')): ?>
                                                    <button @click="showConfirmation('restore', <?php echo $user['id']; ?>)"
                                                            class="text-green-600 hover:text-green-900 tooltip-trigger"
                                                            title="Restore User">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                                    </button>
                                                    <?php endif; ?>

                                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_permanently_delete')): ?>
                                                    <button @click="showConfirmation('permanent-delete', <?php echo $user['id']; ?>)"
                                                            class="text-red-600 hover:text-red-900 tooltip-trigger"
                                                            title="Permanently Delete User">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                    </button>
                                                    <?php endif; ?>

                                                <?php } elseif ($isBanned) { ?>
                                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_ban_user')): ?>
                                                    <button @click="showConfirmation('unban', <?php echo $user['id']; ?>)"
                                                            class="text-green-600 hover:text-green-900 tooltip-trigger"
                                                            title="Unban User">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                                    </button>
                                                    <?php endif; ?>

                                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_move_to_deleted')): ?>
                                                    <button @click="showConfirmation('delete', <?php echo $user['id']; ?>)"
                                                            class="text-red-600 hover:text-red-900 tooltip-trigger"
                                                            title="Move to Deleted Users">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                                    </button>
                                                    <?php endif; ?>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Permissions Modal -->
    <div id="permissionsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative w-full max-w-5xl mx-auto bg-white rounded-xl shadow-2xl border">
            <div class="flex justify-between items-center px-4 sm:px-6 lg:px-8 py-4 sm:py-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                <div class="flex items-center flex-1 min-w-0">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3 sm:mr-4 flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900 truncate" id="permissionsModalTitle">User Permissions</h3>
                        <p class="text-xs sm:text-sm text-gray-600 mt-1 hidden sm:block">View all assigned permissions for this user</p>
                    </div>
                </div>
                <button onclick="closePermissionsModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full hover:bg-gray-100 flex-shrink-0 ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 max-h-80 sm:max-h-96 overflow-y-auto">
                <div id="permissionsContent" class="space-y-4 sm:space-y-6">
                    <!-- Permissions will be loaded here -->
                </div>
            </div>
            <div class="flex justify-end px-4 sm:px-6 lg:px-8 py-4 sm:py-6 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                <button onclick="closePermissionsModal()" class="px-4 sm:px-6 py-2 sm:py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium shadow-sm text-sm sm:text-base">
                    Close
                </button>
            </div>
            </div>
        </div>
    </div>


    <!-- Delete Reason Modal -->
    <div x-show="showDeleteReasonModal" class="fixed z-10 inset-0 overflow-y-auto" x-cloak>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showDeleteReasonModal" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showDeleteReasonModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10 bg-red-100">
                            <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Move User to Deleted</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-4">
                                    Please provide a reason for moving this user to deleted users. This reason will be sent to the user via email.
                                </p>
                                <div>
                                    <label for="delete-reason" class="block text-sm font-medium text-gray-700 mb-2">
                                        Deletion Reason <span class="text-red-500">*</span>
                                    </label>
                                    <textarea 
                                        x-model="deleteReason"
                                        id="delete-reason"
                                        rows="4"
                                        maxlength="500"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                        placeholder="Enter the reason for moving this user to deleted users..."
                                    ></textarea>
                                    <div class="mt-1 flex justify-between text-xs text-gray-500">
                                        <span>This reason will be sent to the user</span>
                                        <span x-text="deleteReason.length + '/500'"></span>
                                    </div>
                                </div>
                                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-yellow-800">Important Notice</h3>
                                            <div class="mt-2 text-sm text-yellow-700">
                                                <p>The user will be moved to deleted users but can be restored within 30 days. After this period, the account will be permanently deleted.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button"
                            @click="handleDeleteUser()"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Move to Deleted
                    </button>
                    <button type="button" @click="closeDeleteReasonModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ban Reason Modal -->
    <div x-show="showBanReasonModal" class="fixed z-10 inset-0 overflow-y-auto" x-cloak>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showBanReasonModal" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showBanReasonModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10 bg-red-100">
                            <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Ban User</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-4">
                                    Please provide a reason for banning this user. This reason will be sent to the user via email.
                                </p>
                                <div>
                                    <label for="ban-reason" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ban Reason <span class="text-red-500">*</span>
                                    </label>
                                    <textarea 
                                        x-model="banReason"
                                        id="ban-reason"
                                        rows="4"
                                        maxlength="500"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
                                        placeholder="Enter the reason for banning this user..."
                                    ></textarea>
                                    <div class="mt-1 flex justify-between text-xs text-gray-500">
                                        <span>This reason will be sent to the user</span>
                                        <span x-text="banReason.length + '/500'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button"
                            @click="handleBanUser()"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Ban User
                    </button>
                    <button type="button" @click="closeBanReasonModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Generic Confirmation Modal -->
    <div x-show="showConfirmModal" class="fixed z-10 inset-0 overflow-y-auto" x-cloak>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showConfirmModal" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showConfirmModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10"
                             :class="{
                                'bg-red-100': confirmAction.buttonColor === 'red',
                                'bg-yellow-100': confirmAction.buttonColor === 'yellow',
                                'bg-green-100': confirmAction.buttonColor === 'green'
                             }">
                            <svg class="h-6 w-6" 
                                 :class="{
                                    'text-red-600': confirmAction.buttonColor === 'red',
                                    'text-yellow-600': confirmAction.buttonColor === 'yellow',
                                    'text-green-600': confirmAction.buttonColor === 'green'
                                 }"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" x-text="confirmAction.title"></h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500" x-text="confirmAction.message"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button"
                            @click="handleConfirmAction()"
                            :class="{
                                'bg-red-600 hover:bg-red-700 focus:ring-red-500': confirmAction.buttonColor === 'red',
                                'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500': confirmAction.buttonColor === 'yellow',
                                'bg-green-600 hover:bg-green-700 focus:ring-green-500': confirmAction.buttonColor === 'green'
                            }"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm">
                        <span x-text="confirmAction.buttonText"></span>
                    </button>
                    <button type="button" @click="showConfirmModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the Add User Modal -->
    <?php 
    // Make sure permissions and role_templates are available for the modal
    if (!isset($all_permissions_by_category)) {
        $all_permissions_by_category = [];
    }
    if (!isset($role_templates)) {
        $role_templates = [];
    }
    require_once 'includes/add_user_modal.php'; 
    ?>

    <script>
        // Show alert if exists
        <?php if ($alert): ?>
            Swal.fire({
                title: '<?php echo addslashes($alert['title']); ?>',
                text: '<?php echo addslashes($alert['message']); ?>',
                icon: '<?php echo $alert['icon']; ?>',
                confirmButtonColor: '#0284c7',
                timer: 5000,
                timerProgressBar: true,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown animate__faster'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp animate__faster'
                },
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
        <?php endif; ?>

        // Enhanced filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const viewFilter = document.getElementById('view-filter');
            const statusFilter = document.getElementById('status-filter');
            const searchInput = document.getElementById('search');
            const addUserBtn = document.querySelector('.add-user-btn');
            let debounceTimer;

            function updateVisibility() {
                const view = viewFilter.value;
                const status = statusFilter.value.toLowerCase();
                const searchTerm = searchInput.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    // Get relevant data from the row
                    const userData = {
                        username: row.querySelector('td:nth-child(1) .text-gray-900')?.textContent.toLowerCase() || '',
                        email: row.querySelector('td:nth-child(1) .text-gray-500')?.textContent.toLowerCase() || '',
                        role: row.querySelector('td:nth-child(2) span')?.textContent.toLowerCase() || '',
                        status: row.querySelector('td:nth-child(5) .text-sm')?.textContent.toLowerCase() || '',
                        isDeleted: row.querySelector('td:nth-child(5) .text-sm')?.textContent.toLowerCase().includes('deleted') || false
                    };

                    // Check if row matches all filters
                    const matchesView = (view === 'active' && !userData.isDeleted) || 
                                      (view === 'deleted' && userData.isDeleted);
                    const matchesStatus = !status || userData.status.includes(status);
                    const matchesSearch = !searchTerm || 
                                        userData.username.includes(searchTerm) || 
                                        userData.email.includes(searchTerm) || 
                                        userData.role.includes(searchTerm);

                    // Update row visibility
                    row.style.display = matchesView && matchesStatus && matchesSearch ? '' : 'none';
                });

                // Update status filter options based on view
                Array.from(statusFilter.options).forEach(option => {
                    const dataView = option.getAttribute('data-view');
                    if (!dataView || dataView === view) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                        if (option.selected) {
                            statusFilter.value = '';
                        }
                    }
                });

                // Update "Add User" button visibility
                if (addUserBtn) {
                    addUserBtn.style.display = view === 'deleted' ? 'none' : '';
                }

                // Show "no results" message if needed
                const visibleRows = document.querySelectorAll('tbody tr:not([style*="display: none"])');
                const noResultsRow = document.getElementById('no-results-row');
                
                if (visibleRows.length === 0) {
                    if (!noResultsRow) {
                        const tbody = document.querySelector('tbody');
                        const tr = document.createElement('tr');
                        tr.id = 'no-results-row';
                        tr.innerHTML = `
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 bg-gray-50">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="text-lg font-medium">No users found</p>
                                    <p class="text-sm">Try adjusting your filters or search terms</p>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    }
                } else if (noResultsRow) {
                    noResultsRow.remove();
                }
            }

            // Add event listeners
            viewFilter.addEventListener('change', updateVisibility);
            statusFilter.addEventListener('change', updateVisibility);
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(updateVisibility, 300);
            });

            // Initialize filters
            updateVisibility();
        });

        // Handle all confirmation actions
        function showConfirmation(action, userId) {
            let config = {};
            switch(action) {
                case 'reset':
                    config = {
                        title: 'Reset Password?',
                        message: 'A temporary password will be sent to the user\'s email.',
                        actionUrl: 'reset_password.php',
                        buttonColor: 'yellow',
                        buttonText: 'Reset Password'
                    };
                    break;
                case 'ban':
                    config = {
                        title: 'Ban User?',
                        message: 'This will prevent the user from accessing their account.',
                        actionUrl: 'ban_user.php',
                        buttonColor: 'red',
                        buttonText: 'Ban User'
                    };
                    break;
                case 'unban':
                    config = {
                        title: 'Unban User?',
                        message: 'This will restore the user\'s access to their account.',
                        actionUrl: 'unban_user.php',
                        buttonColor: 'green',
                        buttonText: 'Unban User'
                    };
                    break;
                case 'delete':
                    config = {
                        title: 'Move to Deleted Users?',
                        message: 'The user will be moved to the deleted users section.',
                        actionUrl: 'delete_user.php',
                        buttonColor: 'red',
                        buttonText: 'Move to Deleted'
                    };
                    break;
                case 'restore':
                    config = {
                        title: 'Restore User?',
                        message: 'This will restore the user\'s account and all their data.',
                        actionUrl: 'restore_user.php',
                        buttonColor: 'green',
                        buttonText: 'Restore User'
                    };
                    break;
                case 'permanent-delete':
                    config = {
                        title: 'Permanently Delete User?',
                        message: 'This action cannot be undone. All user data will be permanently removed.',
                        actionUrl: 'permanent_delete_user.php',
                        buttonColor: 'red',
                        buttonText: 'Delete Permanently'
                    };
                    break;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            
            this.confirmAction = {
                ...config,
                userId: userId,
                formData: formData
            };
            
            this.showConfirmModal = true;
        }

        // Status filter functionality
        document.getElementById('status-filter').addEventListener('change', function() {
            const status = this.value;
            const view = document.getElementById('view-filter').value;
            const rows = document.querySelectorAll('tbody tr:not([style*="display: none"])');
            
            rows.forEach(row => {
                const statusCell = row.querySelector('td:nth-child(3) .flex .ml-2');
                const statusText = statusCell.textContent.trim().toLowerCase();
                
                if (!status || statusText.includes(status.toLowerCase())) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Search functionality
        document.getElementById('search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const view = document.getElementById('view-filter').value;
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const statusCell = row.querySelector('td:nth-child(3) .flex .ml-2');
                const isDeleted = statusCell.textContent.trim().toLowerCase().includes('deleted');
                
                if ((view === 'active' && !isDeleted) || (view === 'deleted' && isDeleted)) {
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Initialize view filter on page load
        document.getElementById('view-filter').dispatchEvent(new Event('change'));
        
        // Notification Bell Functions
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }
        
        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        
        // Real-time clock for Philippines timezone
        function updateRealTimeClock() {
            const now = new Date();
            const philippinesTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
            
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: 'Asia/Manila'
            };
            
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: 'Asia/Manila'
            };
            
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            if (timeElement) {
                timeElement.textContent = philippinesTime.toLocaleTimeString('en-US', timeOptions) + ' (PHT)';
            }
            
            if (dateElement) {
                dateElement.textContent = philippinesTime.toLocaleDateString('en-US', dateOptions);
            }
        }

        // Function to update last login times with relative time
        function updateLastLoginTimes() {
            const loginTimeElements = document.querySelectorAll('.last-login-time');
            
            loginTimeElements.forEach(element => {
                const loginTime = element.getAttribute('data-login-time');
                if (loginTime) {
                    // Convert database time to Philippines time
                    const dbTime = new Date(loginTime);
                    const philippinesTime = new Date(dbTime.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
                    const now = new Date();
                    const nowPhilippines = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
                    
                    // Calculate time difference
                    const diffMs = nowPhilippines - philippinesTime;
                    const diffMinutes = Math.floor(diffMs / (1000 * 60));
                    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                    
                    let displayText = '';
                    
                    if (diffMinutes < 1) {
                        displayText = 'Just now';
                    } else if (diffMinutes < 60) {
                        displayText = `${diffMinutes} minute${diffMinutes !== 1 ? 's' : ''} ago`;
                    } else if (diffHours < 24) {
                        displayText = `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
                    } else if (diffDays < 7) {
                        displayText = `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
                    } else {
                        // For older dates, show the actual date
                        const timeOptions = {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true,
                            timeZone: 'Asia/Manila'
                        };
                        displayText = philippinesTime.toLocaleDateString('en-US', timeOptions);
                    }
                    
                    element.textContent = displayText;
                    
                    // Add visual indicator for recent logins
                    element.className = 'last-login-time';
                    if (diffMinutes < 5) {
                        element.className += ' text-green-600 font-medium';
                    } else if (diffMinutes < 30) {
                        element.className += ' text-blue-600';
                    } else if (diffHours < 24) {
                        element.className += ' text-yellow-600';
                    } else {
                        element.className += ' text-gray-500';
                    }
                }
            });
        }

        // Initialize real-time clock
        document.addEventListener('DOMContentLoaded', function() {
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000); // Update every second
            updateLastLoginTimes();
            setInterval(updateLastLoginTimes, 10000); // Update every 10 seconds for better real-time experience
        });
        
        // Permissions Modal Functions
        // Standalone function to get permission display name
        function getPermissionDisplayName(permissionName) {
            const permissionNames = {
                'nav_dashboard': 'Dashboard Access',
                'nav_course_management': 'Course Management',
                'nav_user_management': 'User Management',
                'nav_reports': 'Reports Section',
                'nav_usage_analytics': 'Usage Analytics Reports',
                'nav_performance_logs': 'Performance Logs',
                'nav_login_activity': 'Login Activity',
                'nav_security_warnings': 'Security Warnings',
                'nav_audit_trails': 'Audit Trails',
                'nav_user_roles_report': 'User Roles Reports',
                'nav_error_logs': 'Error Logs',
                'nav_payments': 'Payment History',
                'nav_content_management': 'Content Management',
                'nav_teacher_dashboard': 'Teacher Dashboard',
                'nav_teacher_courses': 'Teacher Courses',
                'nav_teacher_create_module': 'Create Modules',
                'nav_teacher_drafts': 'Drafts Management',
                'nav_teacher_archive': 'Archive Management',
                'nav_teacher_student_management': 'Student Management',
                'nav_teacher_placement_test': 'Placement Test',
                'nav_teacher_settings': 'Teacher Settings',
                'nav_teacher_content': 'Content Management',
                'nav_teacher_reports': 'Teacher Reports',
                'nav_teacher_audit': 'Teacher Audit',
                'nav_teacher_courses_by_category': 'Courses by Category',
                'dashboard_view_metrics': 'View Dashboard Metrics',
                'dashboard_view_course_completion': 'Course Completion Report',
                'dashboard_view_user_retention': 'User Retention Report',
                'dashboard_view_sales_report': 'Sales Report',
                'course_add_category': 'Add Course Category',
                'course_view_categories': 'View Course Categories',
                'course_edit_category': 'Edit Course Categories',
                'course_delete_category': 'Delete Course Categories',
                'user_add_new': 'Add New User',
                'user_reset_password': 'Reset User Password',
                'user_ban_user': 'Ban/Unban User',
                'user_move_to_deleted': 'Move to Deleted',
                'analytics_export_pdf': 'Export Analytics PDF',
                'analytics_view_metrics': 'View Analytics Metrics',
                'analytics_view_active_trends': 'View Active Trends',
                'analytics_view_role_breakdown': 'View Role Breakdown',
                'analytics_view_activity_data': 'View Activity Data',
                'user_roles_view_metrics': 'View User Roles Metrics',
                'user_roles_search_filter': 'Search & Filter Users',
                'user_roles_export_pdf': 'Export User Roles PDF',
                'user_roles_view_details': 'View User Details',
                'login_activity_view_metrics': 'View Login Activity Metrics',
                'login_activity_view_report': 'View Login Activity Report',
                'login_activity_export_pdf': 'Export Login Activity PDF',
                'broken_links_view_report': 'View Broken Links Report',
                'broken_links_export_pdf': 'Export Broken Links PDF',
                'security_view_metrics': 'View Security Metrics',
                'security_view_suspicious_patterns': 'View Suspicious Patterns',
                'security_view_admin_activity': 'View Admin Activity',
                'security_view_recommendations': 'View Security Recommendations',
                'audit_view_metrics': 'View Audit Metrics',
                'audit_search_filter': 'Search & Filter Audit',
                'audit_export_pdf': 'Export Audit PDF',
                'audit_view_details': 'View Audit Details',
                'performance_view_metrics': 'View Performance Metrics',
                'performance_view_uptime_chart': 'View Uptime Chart',
                'performance_view_load_times': 'View Load Times',
                'error_logs_view_metrics': 'View Error Log Metrics',
                'error_logs_view_trends': 'View Error Trends',
                'error_logs_view_categories': 'View Error Categories',
                'error_logs_search_filter': 'Search & Filter Errors',
                'error_logs_export_pdf': 'Export Error Logs PDF',
                'payment_view_history': 'View Payment History',
                'content_manage_announcement': 'Manage Announcements',
                'content_manage_terms': 'Manage Terms & Conditions',
                'content_manage_privacy': 'Manage Privacy Policy'
            };
            
            return permissionNames[permissionName] || permissionName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        function showPermissionsModal(userId, permissions, username) {
            const modal = document.getElementById('permissionsModal');
            const title = document.getElementById('permissionsModalTitle');
            const content = document.getElementById('permissionsContent');
            
            // Set title
            title.textContent = `Permissions for ${username}`;
            
            // Parse permissions
            const permissionList = permissions ? permissions.split(',').map(p => p.trim()).filter(p => p) : [];
            
            if (permissionList.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-8 sm:py-12">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4 sm:mb-6">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg sm:text-xl font-semibold text-gray-700 mb-2">No permissions assigned</h3>
                        <p class="text-gray-500 text-sm sm:text-base max-w-md mx-auto leading-relaxed px-4">This user has no specific permissions assigned. They may be using default role permissions or have no access.</p>
                    </div>
                `;
            } else {
                // Group permissions by category
                const categorizedPermissions = {};
                permissionList.forEach(permission => {
                    // Try to determine category from permission name
                    let category = 'General';
                    if (permission.startsWith('nav_')) {
                        category = 'Navigation';
                    } else if (permission.startsWith('dashboard_')) {
                        category = 'Dashboard';
                    } else if (permission.startsWith('system_')) {
                        category = 'System';
                    } else if (permission.startsWith('teacher_')) {
                        category = 'Teacher';
                    } else if (permission.startsWith('student_')) {
                        category = 'Student';
                    } else if (permission.startsWith('admin_')) {
                        category = 'Administration';
                    }
                    
                    if (!categorizedPermissions[category]) {
                        categorizedPermissions[category] = [];
                    }
                    categorizedPermissions[category].push(permission);
                });
                
                // Build HTML content
                let html = '';
                Object.keys(categorizedPermissions).sort().forEach(category => {
                    html += `
                        <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg sm:rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm">
                            <h4 class="text-lg sm:text-xl font-bold text-gray-800 mb-3 sm:mb-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-0">
                                <div class="flex items-center">
                                    <div class="w-6 h-6 sm:w-8 sm:h-8 bg-blue-100 rounded-full flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <span class="truncate">${category}</span>
                                </div>
                                <span class="px-2 sm:px-3 py-1 bg-blue-100 text-blue-800 text-xs sm:text-sm font-semibold rounded-full self-start sm:self-auto sm:ml-3">
                                    ${categorizedPermissions[category].length} permission${categorizedPermissions[category].length !== 1 ? 's' : ''}
                                </span>
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                    `;
                    
                    categorizedPermissions[category].forEach(permission => {
                        const displayName = getPermissionDisplayName(permission);
                        html += `
                            <div class="flex items-center p-2 sm:p-3 bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                                <div class="w-5 h-5 sm:w-6 sm:h-6 bg-green-100 rounded-full flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                    <svg class="w-3 h-3 sm:w-4 sm:h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="text-xs sm:text-sm text-gray-700 font-medium leading-relaxed break-words">${displayName}</span>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                });
                
                content.innerHTML = html;
            }
            
            // Show modal
            modal.classList.remove('hidden');
        }
        
        function closePermissionsModal() {
            const modal = document.getElementById('permissionsModal');
            modal.classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('permissionsModal');
            if (event.target === modal) {
                closePermissionsModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePermissionsModal();
            }
        });
        
        // Copy to clipboard function for temporary password
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success feedback
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.classList.add('bg-green-600', 'hover:bg-green-700');
                button.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('bg-green-600', 'hover:bg-green-700');
                    button.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    Swal.fire({
                        title: 'Copied!',
                        text: 'Temporary password copied to clipboard',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } catch (err) {
                    Swal.fire({
                        title: 'Copy Failed',
                        text: 'Please manually copy the password',
                        icon: 'error',
                        confirmButtonColor: '#0284c7'
                    });
                }
                document.body.removeChild(textArea);
            });
        }



    </script>

    <!-- User Detail Modal -->
<div x-show="showUserDetailModal" 
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div @click.away="closeUserDetailModal()" 
             class="bg-white rounded-lg shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
            
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-primary-600 to-primary-800 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <h3 class="text-xl font-bold text-white" x-text="'User Details: ' + selectedUserData.username"></h3>
                </div>
                <button @click="closeUserDetailModal()" class="text-white hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Modal Body with Tabs -->
            <div class="overflow-y-auto max-h-[calc(90vh-80px)]">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <button @click="activeTab = 'info'" 
                                :class="activeTab === 'info' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Complete Information
                        </button>
                        <button @click="activeTab = 'payments'" 
                                x-show="selectedUserData.role === 'student'"
                                :class="activeTab === 'payments' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Payment History
                        </button>
                        <button @click="activeTab = 'purchases'" 
                                x-show="selectedUserData.role === 'student'"
                                :class="activeTab === 'purchases' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Purchased Modules
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
<!-- Complete Information Tab -->
<div x-show="activeTab === 'info'" x-cloak>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Personal Information -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-lg mb-4 text-gray-800">Personal Information</h4>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.fullName || 'N/A'"></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.email"></dd>
                </div>
                <!-- Age (Admin & Teacher Only) -->
                <div x-show="selectedUserData.role !== 'student'">
                    <dt class="text-sm font-medium text-gray-500">Age</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.age || 'N/A'"></dd>
                </div>
            </dl>
        </div>

        <!-- Account Information -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-lg mb-4 text-gray-800">Account Information</h4>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Username</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.username"></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Role</dt>
                    <dd class="mt-1">
                        <span class="px-2 py-1 text-xs font-medium rounded-full"
                              :class="{
                                  'bg-purple-100 text-purple-800': selectedUserData.role === 'admin',
                                  'bg-blue-100 text-blue-800': selectedUserData.role === 'teacher',
                                  'bg-green-100 text-green-800': selectedUserData.role === 'student'
                              }"
                              x-text="selectedUserData.role ? selectedUserData.role.charAt(0).toUpperCase() + selectedUserData.role.slice(1) : 'N/A'">
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1">
                        <span class="px-2 py-1 text-xs font-medium rounded-full"
                              :class="{
                                  'bg-green-100 text-green-800': selectedUserData.status === 'active',
                                  'bg-red-100 text-red-800': selectedUserData.status === 'banned',
                                  'bg-gray-100 text-gray-800': selectedUserData.status === 'inactive'
                              }"
                              x-text="selectedUserData.status ? selectedUserData.status.charAt(0).toUpperCase() + selectedUserData.status.slice(1) : 'N/A'">
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.createdAt"></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.lastLogin || 'Never'"></dd>
                </div>
            </dl>
        </div>

        <!-- Address Information (Admin & Teacher Only) -->
        <div x-show="selectedUserData.role !== 'student'" class="bg-gray-50 p-4 rounded-lg md:col-span-2">
            <h4 class="font-semibold text-lg mb-4 text-gray-800">Address Information</h4>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Address Line 1</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.addressLine1 || 'N/A'"></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Address Line 2</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.addressLine2 || 'N/A'"></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">City</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.city || 'N/A'"></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Location</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="selectedUserData.location || 'N/A'"></dd>
                </div>
            </dl>
        </div>
    </div>
</div>
<!-- Payment History Tab (Student Only) -->
<div x-show="activeTab === 'payments'" x-cloak>
    <div x-show="loadingPayments" class="text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        <p class="mt-2 text-gray-600">Loading payment history...</p>
    </div>

    <div x-show="!loadingPayments && paymentHistory.length === 0" class="text-center py-8 text-gray-500">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="mt-2">No payment history found</p>
    </div>

    <div x-show="!loadingPayments && paymentHistory.length > 0" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="payment in paymentHistory" :key="payment.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="payment.date"></td>
                        <td class="px-6 py-4 text-sm text-gray-900 font-medium" x-text="payment.course"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                            <span x-text="payment.method === 'Free' ? 'Free' : '₱' + payment.amount"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a :href="'generate_invoice.php?payment_id=' + payment.id" 
                               class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200"
                               :title="'Download Invoice for Payment #' + payment.id">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Invoice
                            </a>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>


<!-- Purchased Modules Tab (Student Only) -->
<div x-show="activeTab === 'purchases'" x-cloak>
    <div x-show="loadingPurchases" class="text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        <p class="mt-2 text-gray-600">Loading purchased modules...</p>
    </div>

    <div x-show="!loadingPurchases && purchasedModules.length === 0" class="text-center py-8 text-gray-500">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
        </svg>
        <p class="mt-2">No purchased modules found</p>
    </div>

    <div x-show="!loadingPurchases && purchasedModules.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <template x-for="module in purchasedModules" :key="module.id">
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex-1">
                        <h5 class="font-semibold text-gray-900 mb-1" x-text="module.title"></h5>
                        <p class="text-sm text-gray-500" x-text="module.category"></p>
                    </div>
                    <!-- Status Badge -->
                    <span class="px-2 py-1 text-xs font-medium rounded-full ml-2 flex-shrink-0"
                          :class="{
                              'bg-green-100 text-green-800': module.status === 'Completed',
                              'bg-blue-100 text-blue-800': module.status === 'In Progress'
                          }"
                          x-text="module.status">
                    </span>
                </div>
                
                <div class="flex items-center justify-between text-sm mb-3">
                    <span class="font-medium text-primary-600" x-text="'₱' + module.price"></span>
                    <span class="text-xs text-gray-500" x-text="'Enrolled: ' + module.purchaseDate"></span>
                </div>
                
                <!-- Progress Bar -->
                <div>
                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                        <span>Progress</span>
                        <span x-text="module.progress + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all"
                             :class="{
                                 'bg-green-600': module.progress === 100,
                                 'bg-primary-600': module.progress < 100
                             }"
                             :style="'width: ' + module.progress + '%'">
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sortUsers() {
    const sortBy = document.getElementById('sortBy').value;
    const sortDirection = document.getElementById('sortDirection').value;
    const table = document.querySelector('tbody');
    const rows = Array.from(table.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        let aValue, bValue;
        
        switch(sortBy) {
            case 'username':
                aValue = a.querySelector('.text-sm.font-medium.text-gray-900 button')?.textContent.trim().toLowerCase() || '';
                bValue = b.querySelector('.text-sm.font-medium.text-gray-900 button')?.textContent.trim().toLowerCase() || '';
                break;
            case 'email':
                aValue = a.querySelector('.text-sm.text-gray-500')?.textContent.trim().toLowerCase() || '';
                bValue = b.querySelector('.text-sm.text-gray-500')?.textContent.trim().toLowerCase() || '';
                break;
            case 'role':
                aValue = a.querySelectorAll('td')[1]?.textContent.trim().toLowerCase() || '';
                bValue = b.querySelectorAll('td')[1]?.textContent.trim().toLowerCase() || '';
                break;
            case 'status':
                const aStatusEl = a.querySelectorAll('td')[4];
                const bStatusEl = b.querySelectorAll('td')[4];
                aValue = aStatusEl?.textContent.trim().toLowerCase() || '';
                bValue = bStatusEl?.textContent.trim().toLowerCase() || '';
                break;
            case 'created_at':
                aValue = new Date(a.querySelectorAll('td')[6]?.textContent.trim() || 0).getTime();
                bValue = new Date(b.querySelectorAll('td')[6]?.textContent.trim() || 0).getTime();
                break;
            case 'last_login':
                aValue = new Date(a.querySelectorAll('td')[5]?.textContent.trim() || 0).getTime();
                bValue = new Date(b.querySelectorAll('td')[5]?.textContent.trim() || 0).getTime();
                break;
            default:
                return 0;
        }
        
        // Handle string comparisons
        if (typeof aValue === 'string' && typeof bValue === 'string') {
            if (sortDirection === 'asc') {
                return aValue.localeCompare(bValue);
            } else {
                return bValue.localeCompare(aValue);
            }
        }
        
        // Handle number comparisons (dates, etc.)
        if (sortDirection === 'asc') {
            return aValue - bValue;
        } else {
            return bValue - aValue;
        }
    });
    
    // Clear and re-append sorted rows
    table.innerHTML = '';
    rows.forEach(row => table.appendChild(row));
    
    // Show success notification
    showSortNotification(sortBy, sortDirection);
}

function showSortNotification(sortBy, direction) {
    const sortByText = document.getElementById('sortBy').selectedOptions[0].text;
    const directionText = direction === 'asc' ? 'Ascending' : 'Descending';
    
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: `Sorted by ${sortByText} (${directionText})`,
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
}
</script>
<script>
// Filter users by date preset from dropdown
function filterUsersByDatePreset() {
    const preset = document.getElementById('date-filter').value;
    
    if (!preset) {
        // Show all rows when "All Dates" is selected
        clearDateFilter();
        return;
    }
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let startDate, endDate;
    
    switch(preset) {
        case 'today':
            startDate = new Date(today);
            endDate = new Date(today);
            endDate.setHours(23, 59, 59, 999);
            break;
        case 'yesterday':
            startDate = new Date(today);
            startDate.setDate(today.getDate() - 1);
            endDate = new Date(startDate);
            endDate.setHours(23, 59, 59, 999);
            break;
        case 'last7days':
            startDate = new Date(today);
            startDate.setDate(today.getDate() - 6);
            endDate = new Date(today);
            endDate.setHours(23, 59, 59, 999);
            break;
        case 'last30days':
            startDate = new Date(today);
            startDate.setDate(today.getDate() - 29);
            endDate = new Date(today);
            endDate.setHours(23, 59, 59, 999);
            break;
        case 'thisweek':
            startDate = new Date(today);
            const dayOfWeek = today.getDay();
            const diff = dayOfWeek === 0 ? 6 : dayOfWeek - 1; // Monday as start of week
            startDate.setDate(today.getDate() - diff);
            endDate = new Date(today);
            endDate.setHours(23, 59, 59, 999);
            break;
        case 'thismonth':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today);
            endDate.setHours(23, 59, 59, 999);
            break;
        case 'lastmonth':
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
            endDate.setHours(23, 59, 59, 999);
            break;
        default:
            clearDateFilter();
            return;
    }
    
    filterUsersByDateRange(startDate, endDate);
}

// Filter users by date range
function filterUsersByDateRange(startDate, endDate) {
    const rows = document.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        // Get the created date from the 7th column (index 6)
        const createdDateCell = row.querySelectorAll('td')[6];
        if (!createdDateCell) return;
        
        const createdDateText = createdDateCell.textContent.trim();
        const createdDate = new Date(createdDateText);
        
        // Check if date is within range
        const isInRange = createdDate >= startDate && createdDate <= endDate;
        
        // Only show/hide based on date filter, preserve other filters
        if (isInRange) {
            // Check other filters before showing
            const view = document.getElementById('view-filter').value;
            const status = document.getElementById('status-filter').value.toLowerCase();
            const searchTerm = document.getElementById('search').value.toLowerCase();
            
            const userData = {
                username: row.querySelector('td:nth-child(1) .text-gray-900')?.textContent.toLowerCase() || '',
                email: row.querySelector('td:nth-child(1) .text-gray-500')?.textContent.toLowerCase() || '',
                role: row.querySelector('td:nth-child(2) span')?.textContent.toLowerCase() || '',
                status: row.querySelector('td:nth-child(5) .text-sm')?.textContent.toLowerCase() || '',
                isDeleted: row.querySelector('td:nth-child(5) .text-sm')?.textContent.toLowerCase().includes('deleted') || false
            };
            
            const matchesView = (view === 'active' && !userData.isDeleted) || (view === 'deleted' && userData.isDeleted);
            const matchesStatus = !status || userData.status.includes(status);
            const matchesSearch = !searchTerm || 
                userData.username.includes(searchTerm) || 
                userData.email.includes(searchTerm) || 
                userData.role.includes(searchTerm);
            
            if (matchesView && matchesStatus && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show notification
    const presetText = document.getElementById('date-filter').selectedOptions[0].text;
    
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: `Filtered by: ${presetText}`,
        text: `${visibleCount} user(s) found`,
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
}

// Clear date filter
function clearDateFilter() {
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        // Re-apply other filters
        const view = document.getElementById('view-filter').value;
        const status = document.getElementById('status-filter').value.toLowerCase();
        const searchTerm = document.getElementById('search').value.toLowerCase();
        
        const userData = {
            username: row.querySelector('td:nth-child(1) .text-gray-900')?.textContent.toLowerCase() || '',
            email: row.querySelector('td:nth-child(1) .text-gray-500')?.textContent.toLowerCase() || '',
            role: row.querySelector('td:nth-child(2) span')?.textContent.toLowerCase() || '',
            status: row.querySelector('td:nth-child(5) .text-sm')?.textContent.toLowerCase() || '',
            isDeleted: row.querySelector('td:nth-child(5) .text-sm')?.textContent.toLowerCase().includes('deleted') || false
        };
        
        const matchesView = (view === 'active' && !userData.isDeleted) || (view === 'deleted' && userData.isDeleted);
        const matchesStatus = !status || userData.status.includes(status);
        const matchesSearch = !searchTerm || 
            userData.username.includes(searchTerm) || 
            userData.email.includes(searchTerm) || 
            userData.role.includes(searchTerm);
        
        if (matchesView && matchesStatus && matchesSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

</body>
</html> 
