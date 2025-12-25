<?php
// This file should be included in users.php
// It expects $permissions and $role_templates to be available
?>
<!-- Add User Modal -->
<div x-show="showAddModal" class="fixed z-10 inset-0 overflow-y-auto" x-cloak>
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div class="relative inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-7xl sm:w-full">
            <form id="addUserForm" method="POST" action="create_user_with_otp.php" class="divide-y divide-gray-200">
                <div class="bg-white px-12 py-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900">Add New User</h3>

                        
                        <button type="button" @click="showAddModal = false" class="text-gray-400 hover:text-gray-500 transition-colors">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    

                    
                    <!-- Progress Indicators -->
                    <div class="mb-10 px-4">
                        <!-- Step 1 -->
                        <div x-show="currentStep === 1" class="flex justify-center">
                            <div class="flex items-center w-full max-w-6xl">
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">1</div>
                                    <div class="absolute top-0 -ml-10 text-center mt-14">
                                        <span class="text-sm font-medium text-gray-700">Choose a role</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-gray-300"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-white border-2 border-gray-300 flex items-center justify-center shadow-sm">2</div>
                                </div>
                                <div class="flex-auto border-t-2 border-gray-300"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-white border-2 border-gray-300 flex items-center justify-center shadow-sm">3</div>
                                </div>
                                <div class="flex-auto border-t-2 border-gray-300"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-white border-2 border-gray-300 flex items-center justify-center shadow-sm">4</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2 -->
                        <div x-show="currentStep === 2" class="flex justify-center">
                            <div class="flex items-center w-full max-w-6xl">
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">1</div>
                                    <div class="absolute top-0 -ml-10 text-center mt-14">
                                        <span class="text-sm font-medium text-gray-700">Choose a role</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-blue-500"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">2</div>
                                    <div class="absolute top-0 -ml-16 text-center mt-14 w-32">
                                        <span class="text-sm font-medium text-gray-700">Assign Role</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-gray-300"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-white border-2 border-gray-300 flex items-center justify-center shadow-sm">3</div>
                                </div>
                                <div class="flex-auto border-t-2 border-gray-300"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-white border-2 border-gray-300 flex items-center justify-center shadow-sm">4</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3 -->
                        <div x-show="currentStep === 3" class="flex justify-center">
                            <div class="flex items-center w-full max-w-6xl">
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">1</div>
                                    <div class="absolute top-0 -ml-10 text-center mt-14">
                                        <span class="text-sm font-medium text-gray-700">Choose a role</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-blue-500"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">2</div>
                                    <div class="absolute top-0 -ml-16 text-center mt-14 w-32">
                                        <span class="text-sm font-medium text-gray-700">Assign Role</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-blue-500"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">3</div>
                                    <div class="absolute top-0 -ml-16 text-center mt-14 w-32">
                                        <span class="text-sm font-medium text-gray-700">Fill up Information</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-gray-300"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-white border-2 border-gray-300 flex items-center justify-center shadow-sm">4</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 4 -->
                        <div x-show="currentStep === 4" class="flex justify-center">
                            <div class="flex items-center w-full max-w-6xl">
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">1</div>
                                    <div class="absolute top-0 -ml-10 text-center mt-14">
                                        <span class="text-sm font-medium text-gray-700">Choose a role</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-blue-500"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">2</div>
                                    <div class="absolute top-0 -ml-16 text-center mt-14 w-32">
                                        <span class="text-sm font-medium text-gray-700">Assign Role</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-blue-500"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">3</div>
                                    <div class="absolute top-0 -ml-16 text-center mt-14 w-32">
                                        <span class="text-sm font-medium text-gray-700">Fill up Information</span>
                                    </div>
                                </div>
                                <div class="flex-auto border-t-2 border-blue-500"></div>
                                <div class="flex items-center relative">
                                    <div class="rounded-full h-10 w-10 bg-blue-500 flex items-center justify-center text-white font-bold shadow-md">4</div>
                                    <div class="absolute top-0 -ml-24 text-center mt-14 w-48">
                                        <span class="text-sm font-medium text-gray-700">Review & Confirmation</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="add_user" value="1">
                    
                    <!-- Step 1: Choose a Role -->
                    <div x-show="currentStep === 1" class="py-8">
                        <h4 class="text-center text-xl font-semibold text-gray-900 mb-12">Choose a role</h4>
                        
                        <div class="flex flex-col items-center space-y-12">
                            <div class="text-center text-2xl font-medium text-gray-800">
                                Admin, Teacher
                                <div class="text-xl text-gray-600 mt-1">Choose Default or Custom</div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-16 w-full max-w-5xl">
                                <!-- Admin Option -->
                                <div class="space-y-5">
                                    <button type="button" @click="roleType = 'admin'; setDefaultTemplateId()" 
                                            class="w-full p-6 border-2 rounded-xl text-center hover:bg-gray-50 transition-all duration-200 transform hover:-translate-y-1 hover:shadow-md"
                                            :class="{'border-blue-500 bg-blue-50': roleType === 'admin', 'border-gray-300': roleType !== 'admin'}">
                                        <div class="flex items-center justify-center mb-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" :class="{'text-blue-500': roleType === 'admin', 'text-gray-400': roleType !== 'admin'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        </div>
                                        <div class="text-xl font-medium" :class="{'text-blue-600': roleType === 'admin'}">Admin</div>
                                    </button>
                                    
                                    <div class="flex space-x-4" x-show="roleType === 'admin'">
                                        <button type="button" @click="roleOption = 'default'; selectedTemplate = 'default_permission'; setDefaultTemplateId()" 
                                                class="flex-1 py-3 px-4 border-2 rounded-lg text-center text-sm hover:bg-gray-50 transition-all duration-200"
                                                :class="{'border-blue-500 bg-blue-50 text-blue-600': roleOption === 'default' && roleType === 'admin', 'border-gray-300': roleOption !== 'default' || roleType !== 'admin'}">
                                            <div class="font-medium">Default</div>
                                            <div class="text-xs mt-1 text-gray-500">Full Admin Permissions</div>
                                        </button>
                                        <button type="button" @click="roleOption = 'custom'; selectedTemplate = 'custom_permissions'; setCustomTemplateId()" 
                                                class="flex-1 py-3 px-4 border-2 rounded-lg text-center text-sm hover:bg-gray-50 transition-all duration-200"
                                                :class="{'border-blue-500 bg-blue-50 text-blue-600': roleOption === 'custom' && roleType === 'admin', 'border-gray-300': roleOption !== 'custom' || roleType !== 'admin'}">
                                            <div class="font-medium">Custom</div>
                                            <div class="text-xs mt-1 text-gray-500">Tailored Permissions</div>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Teacher Option -->
                                <div class="space-y-5">
                                    <button type="button" @click="roleType = 'teacher'; setDefaultTemplateId()" 
                                            class="w-full p-6 border-2 rounded-xl text-center hover:bg-gray-50 transition-all duration-200 transform hover:-translate-y-1 hover:shadow-md"
                                            :class="{'border-blue-500 bg-blue-50': roleType === 'teacher', 'border-gray-300': roleType !== 'teacher'}">
                                        <div class="flex items-center justify-center mb-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" :class="{'text-blue-500': roleType === 'teacher', 'text-gray-400': roleType !== 'teacher'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                        </div>
                                        <div class="text-xl font-medium" :class="{'text-blue-600': roleType === 'teacher'}">Teacher</div>
                                    </button>
                                    
                                    <div class="flex space-x-4" x-show="roleType === 'teacher'">
                                        <button type="button" @click="roleOption = 'default'; selectedTemplate = 'default_permission'; setDefaultTemplateId()" 
                                                class="flex-1 py-3 px-4 border-2 rounded-lg text-center text-sm hover:bg-gray-50 transition-all duration-200"
                                                :class="{'border-blue-500 bg-blue-50 text-blue-600': roleOption === 'default' && roleType === 'teacher', 'border-gray-300': roleOption !== 'default' || roleType !== 'teacher'}">
                                            <div class="font-medium">Default</div>
                                            <div class="text-xs mt-1 text-gray-500">Full Teacher Permissions</div>
                                        </button>
                                        <button type="button" @click="roleOption = 'custom'; selectedTemplate = 'custom_permissions'; setCustomTemplateId()" 
                                                class="flex-1 py-3 px-4 border-2 rounded-lg text-center text-sm hover:bg-gray-50 transition-all duration-200"
                                                :class="{'border-blue-500 bg-blue-50 text-blue-600': roleOption === 'custom' && roleType === 'teacher', 'border-gray-300': roleOption !== 'custom' || roleType !== 'teacher'}">
                                            <div class="font-medium">Custom</div>
                                            <div class="text-xs mt-1 text-gray-500">Tailored Permissions</div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Assign Role -->
                    <div x-show="currentStep === 2" class="py-8">
                        <h4 class="text-center text-xl font-semibold text-gray-900 mb-10">Assign Role Permissions</h4>
                        
                        <!-- For Default Template -->
                        <div x-show="roleOption === 'default'" class="max-w-5xl mx-auto">
                            <div class="mb-6 p-6 border rounded-xl bg-gradient-to-br from-white to-gray-50 shadow-sm">
                                <div class="flex items-center mb-4">
                                    <div class="bg-gray-100 p-2 rounded-lg mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <h5 class="text-lg font-medium" x-text="roleType === 'admin' ? 'Default Permission - Full Admin Access' : 'Default Permission - Full Teacher Access'"></h5>
                                </div>
                                
                                <div class="bg-white p-4 rounded-lg border mb-4">
                                    <div class="text-gray-700">
                                        <template x-if="roleType === 'admin'">
                                            <div class="space-y-2">
                                                <p class="text-md">This role uses the <strong>Default Permission</strong> template with full administrative privileges including:</p>
                                                <ul class="list-disc list-inside pl-4 text-sm space-y-1 text-gray-600">
                                                    <li>All Navigation Menus Visible</li>
                                                    <li>Dashboard Access</li>
                                                    <li>Course Management</li>
                                                    <li>User Management</li>
                                                    <li>Reports & Analytics</li>
                                                    <li>Content Management</li>
                                                    <li>Payment History</li>
                                                    <li>Security Warnings</li>
                                                    <li>System Performance Logs</li>
                                                    <li>Audit Trails</li>
                                                    <li>Login Activity</li>
                                                    <li>User Roles Breakdowns</li>
                                                    <li>Error Logs</li>
                                                    <li>User Roles Reports</li>
                                                </ul>
                                            </div>
                                        </template>
                                        <template x-if="roleType === 'teacher'">
                                            <div class="space-y-2">
                                                <p class="text-md">This role uses the <strong>Default Permission</strong> template with full teaching privileges including:</p>
                                                <ul class="list-disc list-inside pl-4 text-sm space-y-1 text-gray-600">
                                                    <li>All Navigation Menus Visible</li>
                                                    <li>Teacher Dashboard</li>
                                                    <li>Courses</li>
                                                    <li>Create New Modules</li>
                                                    <li>Placement Test Access</li>
                                                    <li>Manage Questions</li>
                                                    <li>Configure Difficulty Levels</li>
                                                    <li>View Student Results</li>
                                                    <li>Analytics Statistics</li>
                                                    <li>Design Preview</li>
                                                    <li>Settings</li>
                                                    <li>Courses by Category</li>
                                                </ul>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                
                                <input type="hidden" name="role" :value="roleType">
                                <input type="hidden" name="template_id" :value="selectedTemplateId">
                                <select name="template_id" class="hidden" x-model="selectedTemplateId" :value="selectedTemplateId">
                                    <?php if (isset($role_templates) && is_array($role_templates)): ?>
                                    <?php foreach ($role_templates as $template): ?>
                                        <option value="<?php echo $template['id']; ?>" 
                                                x-bind:selected="selectedTemplate === '<?php 
                                                    $template_name_lower = strtolower($template['name']);
                                                    // Be more specific about template selection
                                                    if (strpos($template_name_lower, 'default permission') !== false) {
                                                        echo 'default_permission';
                                                    } elseif (strpos($template_name_lower, 'full admin access') !== false) {
                                                        echo 'full_admin_access';
                                                    } elseif (strpos($template_name_lower, 'default admin') !== false) {
                                                        echo 'default_admin';
                                                    } elseif (strpos($template_name_lower, 'admin') !== false) {
                                                        echo 'admin_default';
                                                    } elseif (strpos($template_name_lower, 'teacher') !== false) {
                                                        echo 'teacher_default';
                                                    } else {
                                                        echo 'other';
                                                    }
                                                ?>'"
                                                data-template-name="<?php echo htmlspecialchars($template['name']); ?>">
                                            <?php echo htmlspecialchars($template['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No role templates available</option>
                                    <?php endif; ?>
                                </select>
                                
                                <div class="text-center mt-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        <svg class="h-4 w-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span x-show="roleType === 'admin'">Using Full Admin Access template - Complete system access</span>
                                        <span x-show="roleType === 'teacher'">Using Default Teacher template - Full teacher access</span>
                                    </span>
                                </div>
                                
                            </div>
                        </div>
                        
                        <!-- For Custom Permissions -->
                        <div x-show="roleOption === 'custom'" class="max-w-6xl mx-auto">
                            <input type="hidden" name="role" :value="roleType">
                            <input type="hidden" name="template_id" value="">
                            
                            <div class="bg-white shadow-sm rounded-xl border p-6 mb-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center">
                                        <div class="bg-gray-100 p-2 rounded-lg mr-4">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                        <h5 class="text-lg font-medium" x-text="roleType === 'admin' ? 'Custom Admin Permissions' : 'Custom Teacher Permissions'"></h5>
                                            <p class="text-sm text-gray-600" x-show="roleType === 'admin'">Select specific admin permissions for this user</p>
                                        </div>
                                    </div>
                                    <button type="button"
                                            @click="roleType === 'admin' ? toggleAllAdminPermissions() : toggleAllPermissions()"
                                            class="text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                        </svg>
                                        <span x-text="roleType === 'admin' ? 'Select All Admin Permissions' : 'Select All Permissions'"></span>
                                    </button>
                                </div>
                                
                                <p class="text-sm text-gray-600 mb-4" x-show="roleType === 'admin'">Select specific admin permissions organized by category. Each category contains related permissions for better organization.</p>
                                <p class="text-sm text-gray-600 mb-4" x-show="roleType === 'teacher'">Select specific teacher permissions organized by category. Each category contains related permissions for better organization.</p>
                                
                                
                                
                                <!-- Admin Permission Categories with Collapsible Dropdown Cards -->
                                <div class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
                                    <?php 
                                    // Define teacher permission categories in order with icons and descriptions
                                    $teacher_categories = [
                                        'teacher_dashboard' => [
                                            'title' => 'Teacher Dashboard',
                                            'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z',
                                            'description' => 'Access to teacher dashboard and analytics'
                                        ],
                                        'teacher_courses' => [
                                            'title' => 'Courses Categories',
                                            'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                                            'description' => 'Browse and access course categories and modules'
                                        ],
                                        'teacher_course_management' => [
                                            'title' => 'Course Management',
                                            'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                                            'description' => 'Create, edit, and manage courses and modules'
                                        ],
                                        'teacher_drafts' => [
                                            'title' => 'Teacher Drafts Modules',
                                            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                            'description' => 'Manage draft modules and unpublished content'
                                        ],
                                        'teacher_archived' => [
                                            'title' => 'Teacher Archived Modules',
                                            'icon' => 'M5 8a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2V8zM5 8a2 2 0 00-2 2v6a2 2 0 002 2h2M5 8V6a2 2 0 012-2h2a2 2 0 012 2v2m-6 0h6m-6 0v6m6-6v6',
                                            'description' => 'Access and manage archived modules'
                                        ],
                                        'teacher_student_profiles' => [
                                            'title' => 'Student Profiles',
                                            'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z',
                                            'description' => 'View Profiles, View Progress'
                                        ],
                                        'teacher_progress_tracking' => [
                                            'title' => 'Progress Tracking',
                                            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                                            'description' => 'Track student learning progress and analytics'
                                        ],
                                        'teacher_quiz_performance' => [
                                            'title' => 'Quiz Performance',
                                            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                                            'description' => 'Monitor quiz results and student performance'
                                        ],
                                        'teacher_engagement_monitoring' => [
                                            'title' => 'Engagement Monitoring',
                                            'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
                                            'description' => 'Monitor student engagement and activity levels'
                                        ],
                                        'teacher_completion_reports' => [
                                            'title' => 'Completion Reports',
                                            'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                            'description' => 'Generate and view course completion reports'
                                        ],
                                        'teacher_placement_test' => [
                                            'title' => 'Placement Test',
                                            'icon' => 'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                                            'description' => 'Create and manage placement tests'
                                        ],
                                        'teacher_reports' => [
                                            'title' => 'Reports & Analytics',
                                            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                                            'description' => 'View reports and export data'
                                        ],
                                        'teacher_settings' => [
                                            'title' => 'Teacher Settings',
                                            'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                                            'description' => 'Manage teacher profile and preferences'
                                        ],
                                        'teacher_content_management' => [
                                            'title' => 'Content Management',
                                            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                            'description' => 'Create and manage lesson content'
                                        ],
                                        'teacher_audit' => [
                                            'title' => 'Audit & Logging',
                                            'icon' => 'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                                            'description' => 'View teaching activity logs and audit trails'
                                        ],
                                    ];
                                    
                                    // Define admin permission categories in order with icons and descriptions
                                    $admin_categories = [
                                        'admin_dashboard' => [
                                            'title' => 'Dashboard',
                                            'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z',
                                            'description' => 'Access to dashboard metrics and reports'
                                        ],
                                        'admin_course_management' => [
                                            'title' => 'Course Management',
                                            'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                                            'description' => 'Manage courses and categories'
                                        ],
                                        'admin_user_management' => [
                                            'title' => 'User Management',
                                            'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z',
                                            'description' => 'Manage users, roles, and permissions'
                                        ],
                                        'admin_usage_analytics' => [
                                            'title' => 'Usage Analytics',
                                            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                                            'description' => 'View usage analytics and generate PDF reports'
                                        ],
                                        'admin_user_roles_report' => [
                                            'title' => 'User Roles Report',
                                            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                                            'description' => 'View user roles breakdown and reports'
                                        ],
                                        'admin_login_activity' => [
                                            'title' => 'Login Activity & Broken Links',
                                            'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
                                            'description' => 'Monitor login activity and broken links'
                                        ],
                                        'admin_security_warnings' => [
                                            'title' => 'Security Warnings',
                                            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                                            'description' => 'View security warnings and recommendations'
                                        ],
                                        'admin_audit_trails' => [
                                            'title' => 'Audit Trails',
                                            'icon' => 'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                                            'description' => 'View audit trails and system logs'
                                        ],
                                        'admin_system_performance' => [
                                            'title' => 'System Performance',
                                            'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',
                                            'description' => 'Monitor system performance and uptime'
                                        ],
                                        'admin_system_error_logs' => [
                                            'title' => 'System Error Logs',
                                            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                                            'description' => 'View system error logs and monitoring'
                                        ],
                                        'admin_payment_history' => [
                                            'title' => 'Payment History',
                                            'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                                            'description' => 'View payment history and transactions'
                                        ],
                                        'admin_content_management' => [
                                            'title' => 'Content Management',
                                            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                            'description' => 'Manage announcements, terms, and privacy policy'
                                        ],
                                    ];
                                    
                                    // Get permissions from database if not already available
                                    if (!isset($all_permissions_by_category)) {
                                        $all_permissions_by_category = getAllPermissionsByCategory($pdo);
                                    }
                                    
                                    // Display teacher permission categories
                                    foreach ($teacher_categories as $category_key => $category_info): 
                                        if (isset($all_permissions_by_category[$category_key])): ?>
                                                <div class="permission-category border border-gray-200 rounded-lg bg-white shadow-sm" 
                                                     x-data="{ isCollapsed: false }"
                                                     x-show="roleType === 'teacher'">
                                                    <div class="bg-gradient-to-r from-red-50 to-pink-50 px-4 py-3 border-b border-gray-200 cursor-pointer"
                                                         @click="isCollapsed = !isCollapsed">
                                                        <div class="flex items-center justify-between">
                                                            <h6 class="text-sm font-medium text-gray-900 flex items-center">
                                                                <svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $category_info['icon']; ?>"/>
                                                                </svg>
                                                                <?php echo $category_info['title']; ?>
                                                            </h6>
                                                            <div class="flex items-center space-x-2">
                                                                <span class="text-xs text-gray-500 bg-white px-2 py-1 rounded-full">
                                                                    <?php echo count($all_permissions_by_category[$category_key]); ?> permissions
                                                                </span>
                                                                <button type="button"
                                                                        @click.stop="toggleCategoryPermissions($event, '<?php echo $category_key; ?>')"
                                                                        class="text-xs text-red-600 hover:text-red-800 font-medium bg-white px-2 py-1 rounded border border-red-200 hover:bg-red-50">
                                                                    Toggle All
                                                                </button>
                                                                <!-- Collapse/Expand Arrow -->
                                                                <button type="button" 
                                                                        @click.stop="isCollapsed = !isCollapsed"
                                                                        class="text-gray-500 hover:text-gray-700 transition-colors">
                                                                    <svg class="w-4 h-4 transition-transform duration-200" 
                                                                         :class="{'rotate-180': isCollapsed}" 
                                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <p class="text-xs text-gray-600 mt-1"><?php echo $category_info['description']; ?></p>
                                                    </div>
                                                    <div class="p-4 transition-all duration-300 ease-in-out" 
                                                         x-show="!isCollapsed" 
                                                         x-transition:enter="transition ease-out duration-200"
                                                         x-transition:enter-start="opacity-0 transform scale-95"
                                                         x-transition:enter-end="opacity-100 transform scale-100"
                                                         x-transition:leave="transition ease-in duration-150"
                                                         x-transition:leave-start="opacity-100 transform scale-100"
                                                         x-transition:leave-end="opacity-0 transform scale-95">
                                                        <div class="grid grid-cols-1 gap-2">
                                                            <?php foreach ($all_permissions_by_category[$category_key] as $permission): ?>
                                                                <label class="flex items-start group p-3 rounded-lg border border-gray-100 hover:bg-gray-50 hover:border-red-200 transition-all duration-200">
                                                                    <input type="checkbox"
                                                                           name="permissions[]"
                                                                           value="<?php echo $permission['name']; ?>"
                                                                           data-category="<?php echo $category_key; ?>"
                                                                           @change="updateSelectedPermissions()"
                                                                           class="rounded border-gray-300 text-red-600 focus:ring-red-500 h-4 w-4 mt-0.5 flex-shrink-0">
                                                                    <div class="ml-3 flex-1">
                                                                        <span class="text-sm text-gray-700 group-hover:text-gray-900 font-medium">
                                                                            <?php echo $permission['description']; ?>
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif;
                                        endforeach;
                                    
                                    // Display admin permission categories
                                    foreach ($admin_categories as $category_key => $category_info): 
                                        if (isset($all_permissions_by_category[$category_key])): ?>
                                            <div class="permission-category border border-gray-200 rounded-lg bg-white shadow-sm" 
                                                 x-data="{ isCollapsed: false }"
                                                 x-show="roleType === 'admin'">
                                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 border-b border-gray-200 cursor-pointer"
                                                     @click="isCollapsed = !isCollapsed">
                                                    <div class="flex items-center justify-between">
                                                        <h6 class="text-sm font-medium text-gray-900 flex items-center">
                                                            <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $category_info['icon']; ?>"/>
                                                            </svg>
                                                            <?php echo $category_info['title']; ?>
                                                        </h6>
                                                        <div class="flex items-center space-x-2">
                                                            <span class="text-xs text-gray-500 bg-white px-2 py-1 rounded-full">
                                                                <?php echo count($all_permissions_by_category[$category_key]); ?> permissions
                                                            </span>
                                                            <button type="button"
                                                                    @click.stop="toggleCategoryPermissions($event, '<?php echo $category_key; ?>')"
                                                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium bg-white px-2 py-1 rounded border border-blue-200 hover:bg-blue-50">
                                                                Toggle All
                                                            </button>
                                                            <!-- Collapse/Expand Arrow -->
                                                            <button type="button" 
                                                                    @click.stop="isCollapsed = !isCollapsed"
                                                                    class="text-gray-500 hover:text-gray-700 transition-colors">
                                                                <svg class="w-4 h-4 transition-transform duration-200" 
                                                                     :class="{'rotate-180': isCollapsed}" 
                                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1"><?php echo $category_info['description']; ?></p>
                                                </div>
                                                <div class="p-4 transition-all duration-300 ease-in-out" 
                                                     x-show="!isCollapsed" 
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 transform scale-95"
                                                     x-transition:enter-end="opacity-100 transform scale-100"
                                                     x-transition:leave="transition ease-in duration-150"
                                                     x-transition:leave-start="opacity-100 transform scale-100"
                                                     x-transition:leave-end="opacity-0 transform scale-95">
                                                    <div class="grid grid-cols-1 gap-2">
                                                        <?php foreach ($all_permissions_by_category[$category_key] as $permission): ?>
                                                            <label class="flex items-start group p-3 rounded-lg border border-gray-100 hover:bg-gray-50 hover:border-blue-200 transition-all duration-200">
                                                                <input type="checkbox"
                                                                       name="permissions[]"
                                                                       value="<?php echo $permission['name']; ?>"
                                                                       data-category="<?php echo $category_key; ?>"
                                                                       @change="updateSelectedPermissions()"
                                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-4 w-4 mt-0.5 flex-shrink-0">
                                                                <div class="ml-3 flex-1">
                                                                    <span class="text-sm text-gray-700 group-hover:text-gray-900 font-medium">
                                                                        <?php echo $permission['description']; ?>
                                                                    </span>
                                                                </div>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif;
                                        endforeach;
                                    ?>
                                    
                                    <!-- Show message if no permissions available -->
                                    <?php if (empty($all_permissions_by_category)): ?>
                                        <div class="text-center py-12 text-gray-500">
                                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <p class="text-lg font-medium">No permissions available</p>
                                            <p class="text-sm">Please run the database migration to add permissions</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-center mt-6">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                                          :class="roleType === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span x-text="selectedPermissions.length + ' permissions selected'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        </div>
                        
                        <!-- Initialize permissions data for Alpine.js -->
                        <script>
                            // Get permissions from database if not already available
                            <?php if (!isset($all_permissions_flat)): ?>
                                <?php $all_permissions_flat = getAllPermissionsFlat($pdo); ?>
                            <?php endif; ?>
                            window.allPermissions = <?php echo json_encode($all_permissions_flat ?? []); ?>;
                            // Add display names for better readability
                            window.allPermissions.forEach(function(permission) {
                                const permissionNames = {
                                    // Admin Core Permissions
                                    'nav_dashboard': 'Dashboard Access',
                                    'nav_course_management': 'Course Management',
                                    'nav_user_management': 'User Management',
                                    'nav_reports': 'Reports & Analytics',
                                    'nav_content_management': 'Content Management',
                                    'nav_payments': 'Payment History',
                                    
                                    // Admin System Permissions
                                    'nav_security_warnings': 'Security Warnings',
                                    'nav_performance_logs': 'System Performance Logs',
                                    'nav_audit_trails': 'Audit Trails',
                                    'nav_login_activity': 'Login Activity',
                                    'nav_user_roles_report': 'User Roles Reports',
                                    'nav_error_logs': 'Error Logs',
                                    'nav_usage_analytics': 'Usage Analytics Reports',
                                    
                                    // Teacher Core Permissions
                                    'nav_teacher_dashboard': 'Teacher Dashboard',
                                    'nav_teacher_courses': 'Courses',
                                    'nav_teacher_create_module': 'Create New Modules',
                                    'nav_teacher_placement_test': 'Placement Test Access',
                                    'nav_teacher_settings': 'Settings',
                                    'nav_teacher_courses_by_category': 'Courses by Category',
                                    
                                    // Teacher Management Permissions
                                    'nav_teacher_manage_questions': 'Manage Questions',
                                    'nav_teacher_difficulty_levels': 'Configure Difficulty Levels',
                                    'nav_teacher_student_results': 'View Student Results',
                                    'nav_teacher_analytics': 'Analytics Statistics',
                                    'nav_teacher_design_preview': 'Design Preview',
                                    
                                    // System Permissions
                                    'system_login': 'System Login',
                                    'system_logout': 'System Logout',
                                    'system_profile': 'Profile Management'
                                };
                                permission.displayName = permissionNames[permission.name] || permission.description;
                                
                                // Add category color coding
                                if (permission.name.includes('teacher')) {
                                    permission.categoryColor = 'border-red-200 bg-red-50';
                                    permission.categoryIcon = '🔴';
                                    permission.colorCategory = 'red';
                                } else if (permission.name.includes('nav_')) {
                                    permission.categoryColor = 'border-blue-200 bg-blue-50';
                                    permission.categoryIcon = '🔵';
                                    permission.colorCategory = 'blue';
                                } else {
                                    permission.categoryColor = 'border-gray-200 bg-gray-50';
                                    permission.categoryIcon = '';
                                    permission.colorCategory = 'gray';
                                }
                            });
                            
                            // Helper functions for Alpine.js
                            window.getPermissionStyle = function(permission) {
                                if (!permission.name) return 'border-gray-200 bg-white hover:bg-gray-50';
                                
                                if (permission.name.includes('teacher')) {
                                    return 'border-red-300 bg-red-50 hover:bg-red-100 hover:border-red-400';
                                } else if (permission.name.includes('nav_')) {
                                    return 'border-blue-300 bg-blue-50 hover:bg-blue-100 hover:border-blue-400';
                                } else {
                                    return 'border-gray-300 bg-gray-50 hover:bg-gray-100 hover:border-gray-400';
                                }
                            };
                            
                            window.getCategoryBadgeStyle = function(permission) {
                                if (!permission.name) return 'bg-gray-100 text-gray-800';
                                
                                if (permission.name.includes('teacher')) {
                                    return 'bg-red-100 text-red-800';
                                } else if (permission.name.includes('nav_')) {
                                    return 'bg-blue-100 text-blue-800';
                                } else {
                                    return 'bg-gray-100 text-gray-800';
                                }
                            };
                            
                            window.getCategoryIcon = function(permission) {
                                if (!permission.name) return '';
                                
                                if (permission.name.includes('teacher')) {
                                    return '🔴';
                                } else if (permission.name.includes('nav_')) {
                                    return '🔵';
                                } else {
                                    return '';
                                }
                            };
                            
                            window.getCategoryName = function(permission) {
                                if (!permission.category) return 'Unknown';
                                
                                const categoryNames = {
                                    'admin_dashboard': 'Admin Dashboard',
                                    'admin_course_management': 'Admin Course Management',
                                    'admin_user_management': 'Admin User Management',
                                    'admin_usage_analytics': 'Admin Usage Analytics',
                                    'admin_user_roles_report': 'Admin User Roles Report',
                                    'admin_login_activity': 'Admin Login Activity',
                                    'admin_security_warnings': 'Admin Security Warnings',
                                    'admin_audit_trails': 'Admin Audit Trails',
                                    'admin_system_performance': 'Admin System Performance',
                                    'admin_system_error_logs': 'Admin System Error Logs',
                                    'admin_payment_history': 'Admin Payment History',
                                    'admin_content_management': 'Admin Content Management',
                                    'teacher_dashboard': 'Teacher Dashboard',
                                    'teacher_course_management': 'Teacher Course Management',
                                    'teacher_student_management': 'Teacher Student Management',
                                    'teacher_placement_test': 'Teacher Placement Test',
                                    'teacher_reports': 'Teacher Reports',
                                    'teacher_settings': 'Teacher Settings',
                                    'teacher_content_management': 'Teacher Content Management',
                                    'teacher_audit': 'Teacher Audit',
                                    'system': 'System'
                                };
                                
                                return categoryNames[permission.category] || permission.category;
                            };
                        </script>
                    </div>
                    
                    <!-- Step 3: Fill Up Information -->
                    <div x-show="currentStep === 3" class="py-8">
                        <h4 class="text-center text-xl font-semibold text-gray-900 mb-6">Fill up Information</h4>
                        
                        <!-- Email & Phone Verification Notice -->
                        <div class="max-w-5xl mx-auto mb-8">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div class="text-sm text-blue-800">
                                        <p class="font-medium">Email & Phone Verification Required</p>
                                        <div class="text-blue-600 mt-1 space-y-1">
                                            <p>• Both email and phone verification are required before first login</p>
                                            <p>• A 6-digit OTP code will be sent to both email and phone number</p>
                                            <p>• Users must complete verification before they can access the system</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="max-w-5xl mx-auto">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 mb-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <!-- Personal Information -->
                                    <div class="space-y-6">
                                        <div class="flex items-center mb-6">
                                            <div class="bg-gray-100 p-2 rounded-lg mr-4">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                            <h5 class="text-lg font-medium text-gray-700">Personal Information</h5>
                                        </div>
                                        
                                        <div class="space-y-5">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                                    <input type="text" id="first_name" name="first_name" placeholder="First Name" required
                                                           x-model="userInfo.firstName"
                                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                </div>
                                                <div>
                                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                                    <input type="text" id="last_name" name="last_name" placeholder="Last Name" required
                                                           x-model="userInfo.lastName"
                                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                </div>
                                            </div>
                                            
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                                    <input type="text" id="middle_name" name="middle_name" placeholder="Middle Name"
                                                           x-model="userInfo.middleName"
                                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                </div>
                                                <div>
                                                    <label for="suffix" class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                                    <input type="text" id="suffix" name="suffix" placeholder="Suffix"
                                                           x-model="userInfo.suffix"
                                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                </div>
                                            </div>
                                            
                                            <div class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                                    <div class="space-y-3">
                                                        <div>
                                                            <label for="address_line1" class="block text-sm font-medium text-gray-600 mb-1">Address Line 1</label>
                                                            <input type="text" id="address_line1" name="address_line1" placeholder="Street address, P.O. box, company name"
                                                                   x-model="userInfo.addressLine1"
                                                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        </div>
                                                        <div>
                                                            <label for="address_line2" class="block text-sm font-medium text-gray-600 mb-1">Address Line 2</label>
                                                            <input type="text" id="address_line2" name="address_line2" placeholder="Apartment, suite, unit, building, floor, etc."
                                                                   x-model="userInfo.addressLine2"
                                                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        </div>
                                                        <div>
                                                            <label for="city" class="block text-sm font-medium text-gray-600 mb-1">City</label>
                                                            <input type="text" id="city" name="city" placeholder="City"
                                                                   x-model="userInfo.city"
                                                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label for="age" class="block text-sm font-medium text-gray-700 mb-1">Age <span class="text-red-500">*</span></label>
                                                    <input type="number" id="age" name="age" placeholder="Age" required min="18" max="100"
                                                           x-model="userInfo.age"
                                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                </div>
                                                
                                                <!-- Phone Number Field -->
                                                <div>
                                                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                                                    <div class="flex space-x-2">
                                                        <div class="flex items-center px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 text-sm">
                                                            🇵🇭 +63
                                                        </div>
                                                        <input type="tel" id="phone_number" name="phone_number" required 
                                                               x-model="userInfo.phoneNumber"
                                                               pattern="^(09\d{9}|9\d{9})$"
                                                               maxlength="11"
                                                               class="flex-1 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                               placeholder="9XX XXX XXXX or 09XX XXX XXXX"
                                                               title="Enter a valid Philippine mobile number">
                                                        <input type="hidden" name="country_code" value="+63">
                                                    </div>
                                                    <div class="mt-1 text-xs text-gray-500">
                                                        Format: 9XX XXX XXXX (without +63) or 09XX XXX XXXX
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Account Information -->
                                    <div class="space-y-6">
                                        <div class="flex items-center mb-6">
                                            <div class="bg-gray-100 p-2 rounded-lg mr-4">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                            </div>
                                            <h5 class="text-lg font-medium text-gray-700">Account Information</h5>
                                        </div>
                                        
                                        <div class="space-y-5">
                                            <div>
                                                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 sm:text-sm">@</span>
                                                    </div>
                                                    <input type="text" id="username" name="username" placeholder="Username" required minlength="3"
                                                           x-model="userInfo.username"
                                                           class="block w-full pl-10 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                    <input type="email" id="email" name="email" placeholder="Email Address" required
                                                           x-model="userInfo.email"
                                                           class="block w-full pl-10 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <input type="password" id="password" name="password" placeholder="Password" required minlength="8"
                                                           x-model="userInfo.password"
                                                           class="block w-full pr-10 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 cursor-pointer" 
                                                         @click="const input = document.getElementById('password'); input.type = input.type === 'password' ? 'text' : 'password'">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters</p>
                                            </div>
                                            
                                            <div>
                                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required
                                                           x-model="userInfo.confirmPassword"
                                                           @input="validatePasswords()"
                                                           class="block w-full pr-10 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                           :class="{'border-red-300 focus:ring-blue-500 focus:border-blue-500': passwordError}">
                                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 cursor-pointer"
                                                         @click="const input = document.getElementById('confirm_password'); input.type = input.type === 'password' ? 'text' : 'password'">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <p class="mt-1 text-xs text-red-500" x-show="passwordError" x-text="passwordError"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center text-sm text-gray-500">
                                <span class="text-red-500">*</span> Required fields
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Review & Confirmation -->
                    <div x-show="currentStep === 4" class="py-6">
                        <h4 class="text-center text-xl font-semibold text-gray-900 mb-6">Review and Confirmation</h4>
                        
                        <div class="max-w-6xl mx-auto">
                            <!-- Information Summary Box -->
                            <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                                <div class="border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white px-6 py-4">
                                    <h5 class="font-medium text-gray-800 flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                                        </svg>
                                        Account Summary
                                    </h5>
                                </div>
                                
                                <div class="p-6 space-y-6">
                                    <!-- Role Information -->
                                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 shadow-sm border border-blue-100">
                                        <h6 class="text-md font-medium text-gray-800 mb-4 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            Role Information
                                        </h6>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                            <div class="p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Role Type</p>
                                                <p class="font-medium text-gray-800 text-lg" x-text="roleType.charAt(0).toUpperCase() + roleType.slice(1)"></p>
                                            </div>
                                            <div class="p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Configuration</p>
                                                <p class="font-medium text-gray-800 text-lg" x-text="roleOption.charAt(0).toUpperCase() + roleOption.slice(1)"></p>
                                            </div>
                                            <div x-show="roleOption === 'custom' && selectedPermissions.length > 0" class="md:col-span-3 p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-2">Custom Permissions</p>
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <p class="font-medium text-gray-800" x-text="selectedPermissions.length + ' permissions selected'"></p>
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">Custom</span>
                                                    </div>
                                                    <div class="max-h-24 overflow-y-auto">
                                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-1">
                                                            <template x-for="permission in selectedPermissions" :key="permission">
                                                                <div class="flex items-center text-xs text-gray-600 bg-gray-50 px-2 py-1 rounded">
                                                                    <svg class="h-3 w-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                                    </svg>
                                                                    <span x-text="getPermissionDisplayName(permission)"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Personal Information -->
                                    <div class="bg-gradient-to-r from-green-50 to-teal-50 rounded-xl p-4 shadow-sm border border-green-100">
                                        <h6 class="text-md font-medium text-gray-800 mb-4 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                            </svg>
                                            Personal Information
                                        </h6>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                            <div class="md:col-span-2 p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Full Name</p>
                                                <p class="font-medium text-gray-800 text-lg" x-text="userInfo.firstName + ' ' + (userInfo.middleName ? userInfo.middleName.charAt(0) + '. ' : '') + userInfo.lastName + (userInfo.suffix ? ' ' + userInfo.suffix : '')"></p>
                                            </div>
                                            <div class="p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Age</p>
                                                <p class="font-medium text-gray-800" x-text="userInfo.age"></p>
                                            </div>
                                            <div class="p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Contact Number</p>
                                                <p class="font-medium text-gray-800" x-text="'+63 ' + userInfo.phoneNumber"></p>
                                            </div>
                                            <div class="md:col-span-3 p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Address</p>
                                                <div class="font-medium text-gray-800">
                                                    <div x-show="userInfo.addressLine1" x-text="userInfo.addressLine1"></div>
                                                    <div x-show="userInfo.addressLine2" x-text="userInfo.addressLine2"></div>
                                                    <div x-show="userInfo.city" x-text="userInfo.city"></div>
                                                    <div x-show="!userInfo.addressLine1 && !userInfo.addressLine2 && !userInfo.city" class="text-gray-400 italic">No address provided</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Account Information -->
                                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-4 shadow-sm border border-purple-100">
                                        <h6 class="text-md font-medium text-gray-800 mb-4 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-purple-600" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v-1l1-1 1-1-1.257-1.257A6 6 0 1118 8zm-6-4a1 1 0 102 0 1 1 0 00-2 0z" clip-rule="evenodd" />
                                            </svg>
                                            Account Information
                                        </h6>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                            <div class="p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Username</p>
                                                <p class="font-medium text-gray-800 flex items-center">
                                                    <span class="text-gray-400 mr-1">@</span>
                                                    <span x-text="userInfo.username"></span>
                                                </p>
                                            </div>
                                            <div class="p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Email</p>
                                                <p class="font-medium text-gray-800" x-text="userInfo.email"></p>
                                            </div>
                                            <div class="p-3 bg-white rounded-lg shadow-sm">
                                                <p class="text-gray-600 text-xs mb-1">Password</p>
                                                <p class="font-medium text-gray-800">
                                                    <span class="inline-flex items-center">
                                                        <span class="h-2 w-2 rounded-full bg-gray-500 mx-0.5"></span>
                                                        <span class="h-2 w-2 rounded-full bg-gray-500 mx-0.5"></span>
                                                        <span class="h-2 w-2 rounded-full bg-gray-500 mx-0.5"></span>
                                                        <span class="h-2 w-2 rounded-full bg-gray-500 mx-0.5"></span>
                                                        <span class="h-2 w-2 rounded-full bg-gray-500 mx-0.5"></span>
                                                        <span class="h-2 w-2 rounded-full bg-gray-500 mx-0.5"></span>
                                                        <span class="h-2 w-2 rounded-full bg-gray-500 mx-0.5"></span>
                                                        <span class="h-2 w-2 rounded-full bg-gray-500 mx-0.5"></span>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 text-center">
                                <div class="inline-flex items-center px-4 py-2 rounded-lg shadow-sm text-sm font-medium text-white bg-green-500 border border-green-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                    Please review the information before confirming
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Navigation Buttons - Inside the modal card -->
                    <div class="bg-gradient-to-r from-gray-50 to-white px-8 py-6 border-t border-gray-200 mt-8">
                        <div class="flex justify-between items-center">
                            <!-- Left side - Back button or spacer -->
                            <div class="flex-1">
                                <!-- Back Button - Hidden on first step -->
                                <button type="button" 
                                        x-show="currentStep > 1"
                                        @click="currentStep--"
                                        class="inline-flex items-center px-6 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:-translate-y-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    Back
                                </button>
                            </div>
                            
                            <!-- Center - Step indicator -->
                            <div class="flex-1 flex justify-center">
                                <div class="text-sm text-gray-500">
                                    Step <span x-text="currentStep"></span> of 4
                                </div>
                            </div>
                            
                            <!-- Right side - Next/Save buttons -->
                            <div class="flex-1 flex justify-end space-x-3">
                                <!-- Next button on steps 1-3 -->
                                <button type="button" 
                                        @click="nextStep()"
                                        x-show="currentStep >= 1 && currentStep <= 3"
                                        class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:-translate-y-0.5">
                                    Next Step
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                
                                <!-- Save button on last step -->
                                <button type="submit"
                                        x-show="currentStep === 4"
                                        @click.prevent="submitForm($event)"
                                        class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200 transform hover:-translate-y-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    Create User
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.7);
}

/* Enhanced Permission Cards */
.permission-card {
    transition: all 0.2s ease-in-out;
    position: relative;
}
.permission-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.permission-card input:checked + .permission-content {
    background-color: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.5);
}
.permission-card input:checked + .permission-content::after {
    content: '✓';
    position: absolute;
    top: 8px;
    right: 8px;
    color: #3b82f6;
    font-weight: bold;
    font-size: 14px;
}

/* Category badges with pulse animation */
.category-badge {
    animation: pulse-subtle 2s infinite;
}
@keyframes pulse-subtle {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

/* Step indicators */
.step-progress {
    background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%);
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}
@keyframes gradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
</style> 

