<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();
require_once '../config/database.php';

// Get preserved form data if available
$form_data = $_SESSION['form_data'] ?? [];
// Clear the form data from session after retrieving it
unset($_SESSION['form_data']);

// Fetch terms and conditions from database
$terms_content = '';
try {
    $stmt = $pdo->prepare("SELECT content FROM terms_conditions ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $terms = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($terms) {
        $terms_content = $terms['content'];
    }
} catch (PDOException $e) {
    // Silently handle error and use default empty content
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register - Japanese Learning Platform</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/validation_signup.css">
  <link rel="stylesheet" href="css/signup_card.css">
  <!-- particles.js lib -->
  <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
  <!-- Fallback particles.js CDN -->
  <script>
    if (typeof particlesJS === 'undefined') {
      document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"><\/script>');
    }
  </script>
  <!-- stats.js lib -->
  <script src="https://threejs.org/examples/js/libs/stats.min.js"></script>
</head>
<body class="font-jp">
  <!-- Modern Alert Container -->
  <div id="alert-container" class="alert-container"></div>
  
  <!-- Main Container -->
  <div class="signup-container">
  <!-- particles.js container -->
  <div id="particles-js"></div>
  
    <!-- Main Card -->
    <div class="neon-border-container">
  <div class="neon-border-anim"></div>

      <!-- Close Button -->
      <a href="../index.php" class="close-btn" title="Back to Home">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </a>
      
      <!-- Card Layout -->
      <div class="card-layout">
        <!-- Form Section -->
        <div class="form-section">
          
          <!-- Header -->
          <div class="modern-header">
            <h1 class="modern-title">Create Account</h1>
            <p class="modern-subtitle">Start your Japanese learning journey today</p>
          </div>
      
          <!-- Form -->
        <form id="signup-form" action="../auth/auth.php" method="POST">
        <input type="hidden" name="action" value="signup">
        
        <!-- Name Fields -->
            <div class="form-grid">
          <div>
            <label class="modern-label">Last Name <span class="required-star">*</span></label>
            <div class="modern-input">
              <input 
                type="text" 
                name="last_name" 
                required 
                placeholder="Last Name"
                autocomplete="family-name"
                value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>"
              >
              <div class="input-icon">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
              </div>
            </div>
            <div class="inline-error-message" id="last_name-error"></div>
          </div>
          <div>
            <label class="modern-label">First Name <span class="required-star">*</span></label>
            <div class="modern-input">
              <input 
                type="text" 
                name="first_name" 
                required 
                placeholder="First Name"
                autocomplete="given-name"
                value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>"
              >
              <div class="input-icon">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
              </div>
            </div>
            <div class="inline-error-message" id="first_name-error"></div>
          </div>
          <div>
            <label class="modern-label">Middle Name <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
            <div class="modern-input">
              <input 
                type="text" 
                name="middle_name" 
                placeholder="Middle Name"
                autocomplete="additional-name"
                value="<?php echo htmlspecialchars($form_data['middle_name'] ?? ''); ?>"
              >
              <div class="input-icon">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                </svg>
              </div>
            </div>
            <div class="inline-error-message" id="middle_name-error"></div>
          </div>
        </div>
        
        <!-- Username Field -->
        <div class="mb-3">
          <label class="modern-label">Username <span class="required-star">*</span></label>
          <div class="modern-input">
            <input 
              type="text" 
              name="username" 
              required 
              placeholder="Username"
              autocomplete="username"
              value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
            >
            <div class="input-icon">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
              </svg>
            </div>
          </div>
          <div class="inline-error-message" id="username-error"></div>
        </div>
        
        <!-- Email Field -->
        <div class="mb-3">
          <label class="modern-label">Email Address <span class="required-star">*</span></label>
          <div class="modern-input">
            <input 
              type="email" 
              name="email" 
              required 
              placeholder="Enter your email address"
              autocomplete="email"
              value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
            >
            <div class="input-icon">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
            </div>
          </div>
          <div class="inline-error-message" id="email-error"></div>
        </div>

        <!-- Contact Number Field -->
        <div class="mb-3">
          <label class="modern-label">Contact Number <span class="required-star">*</span></label>
          <div class="phone-input-container">
            <!-- Fixed Country Label -->
            <div class="country-selector">
              <div class="country-label">🇵🇭 +63</div>
              <input type="hidden" name="country_code" value="+63">
            </div>
            <!-- Phone Number Input -->
            <div class="modern-input phone-input">
              <input 
                type="tel" 
                name="phone_number" 
                id="phone_number"
                required 
                placeholder="9XX XXX XXXX or 09XX XXX XXXX"
                autocomplete="tel"
                title="Please enter a valid Philippine mobile number (e.g., 9567134586 or 09567134586)"
                inputmode="numeric"
                maxlength="13"
                value="<?php echo htmlspecialchars($form_data['phone_number'] ?? ''); ?>"
              >
              <div class="input-icon">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
              </div>
            </div>
          </div>
          <div class="phone-format-hint" id="phone-format-hint">
            Format: 9XX XXX XXXX (without +63) or 09XX XXX XXXX
          </div>
          <div class="inline-error-message" id="phone_number-error"></div>
        </div>

        

        <!-- Password Field -->
        <div class="mb-3">
          <label class="modern-label">Password <span class="required-star">*</span></label>
          <div class="modern-input">
          <input 
  type="password" 
  name="password" 
  id="password" 
  required 
  placeholder="Create a strong password"
  minlength="12" 
  maxlength="64"
  pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{12,64}"
  autocomplete="new-password"
  onfocus="showPasswordTooltip()" 
  onblur="hidePasswordTooltip()" 
  onmouseover="showPasswordTooltip()" 
  onmouseout="hidePasswordTooltip()"
/>

            <div class="input-icon">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
              </svg>
            </div>
            <button type="button" class="password-toggle" onclick="togglePassword('password')" tabindex="-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
            <!-- Password Tooltip -->
                <div id="password-tooltip" class="hidden">
              <p class="font-medium mb-2 text-xs">Password Requirements:</p>
              <ul class="list-disc pl-4 space-y-1">
                <li id="length-check-tooltip" class="requirement unmet text-xs">Minimum 12 characters (14+ recommended)</li>
                <li id="uppercase-check-tooltip" class="requirement unmet text-xs">Include uppercase letters</li>
                <li id="lowercase-check-tooltip" class="requirement unmet text-xs">Include lowercase letters</li>
                <li id="number-check-tooltip" class="requirement unmet text-xs">Include numbers</li>
                <li id="special-check-tooltip" class="requirement unmet text-xs">Include special characters (e.g., ! @ # ?)</li>
              </ul>
            </div>
          </div>
          <div class="password-strength-meter">
            <div id="strength-bar" class="strength-weak" style="width: 0%"></div>
          </div>
          <span id="strength-text" class="text-xs text-gray-500 block mt-1"></span>
        </div>

        <!-- Confirm Password Field -->
        <div class="mb-4">
          <label class="modern-label">Confirm Password <span class="required-star">*</span></label>
          <div class="modern-input">
            <input 
              type="password" 
              name="confirm_password" 
              id="confirm_password" 
              required 
              placeholder="Confirm your password"
              autocomplete="new-password"
            >
            <div class="input-icon">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" tabindex="-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
          <span id="password-match" class="text-xs block mt-1"></span>
        </div>

        <!-- Terms Agreement -->
        <div class="mb-4">
          <p class="text-xs text-gray-600 text-center">
            By proceeding, you acknowledge and agree to AiToManabi's 
            <button type="button"  
                    onclick="showTermsModal()" 
                    class="terms-link focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1">
              Terms & Conditions (Click to view)
            </button>.
          </p>
          <!-- Hidden input to store terms acceptance state -->
          <input type="hidden" name="terms" id="terms" value="0" required>
        </div>

        <!-- Register Button -->
            <button type="submit" class="modern-btn">
          <span>Create Account</span>
        </button>
      </form>

      <!-- Footer -->
      <div class="footer-text">
        Already have an account?
        <a href="login.php" class="footer-link ml-1">Sign In</a>
      </div>
    </div>
    
      </div>
    </div>
  </div>

  <!-- Modern Confirm Dialog Modal -->
  <div id="confirmModal" class="hidden fixed inset-0 z-50 overflow-y-auto modern-modal" aria-labelledby="confirm-modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"></div>

    <!-- Modal container -->
    <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
      <div class="modern-modal-content relative transform overflow-hidden rounded-xl text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
        <!-- Modal header -->
        <div class="bg-transparent px-5 py-3 border-b border-gray-200 border-opacity-20">
          <div class="flex items-center justify-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
              <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Modal content -->
        <div class="bg-transparent px-5 py-4">
          <div class="text-center">
            <h3 id="confirm-modal-title" class="text-lg font-semibold text-gray-900 mb-2">
              <!-- Title will be set dynamically -->
            </h3>
            <div class="mt-2">
              <p id="confirm-modal-message" class="text-sm text-gray-600">
                <!-- Message will be set dynamically -->
              </p>
            </div>
          </div>
        </div>

        <!-- Modal footer -->
        <div class="bg-transparent px-5 py-3 border-t border-gray-200 border-opacity-20">
          <div class="flex justify-center space-x-3">
            <button type="button"
                    id="confirm-modal-cancel"
                    class="modern-btn-secondary">
              <!-- Button text will be set dynamically -->
            </button>
            <button type="button"
                    id="confirm-modal-confirm"
                    class="modern-btn-danger">
              <!-- Button text will be set dynamically -->
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Terms Modal -->
  <div id="termsModal" class="hidden fixed inset-0 z-50 overflow-y-auto modern-modal" aria-labelledby="terms-modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

    <!-- Modal container -->
    <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
      <div class="modern-modal-content relative transform overflow-hidden rounded-xl text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl">
        <!-- Modal header -->
        <div class="bg-transparent px-5 py-3 border-b border-gray-200 border-opacity-20">
          <div class="flex items-center justify-between">
            <h2 id="terms-modal-title" class="text-lg font-semibold text-gray-900 leading-6">
              Terms & Conditions
            </h2>
            <button type="button" 
                    onclick="hideTermsModal()" 
                    class="close-btn"
                    aria-label="Close modal">
              <span class="sr-only">Close</span>
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Modal content -->
        <div class="bg-transparent px-5 py-3">
          <?php if (!empty($terms_content)): ?>
            <!-- Scrollable terms container -->
            <div id="terms-container" 
                 class="prose prose-sm max-w-none overflow-y-auto max-h-[50vh] mb-3 focus:outline-none text-sm"
                 tabindex="0">
              <div class="text-gray-600 text-sm">
                <?php echo $terms_content; ?>
              </div>
            </div>

            <!-- Terms acceptance checkbox -->
            <div class="flex items-start space-x-3 mt-4 border-t pt-3 border-gray-200 border-opacity-20">
              <div class="flex h-5 items-center">
                <input type="checkbox" 
                       id="terms-checkbox"
                       disabled
                       class="h-3.5 w-3.5 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-50"
                       aria-describedby="terms-checkbox-description">
              </div>
              <label for="terms-checkbox" class="text-xs text-gray-700" id="terms-checkbox-description">
                I have read and agree to the Terms & Conditions.
              </label>
            </div>
          <?php else: ?>
            <div class="text-center py-8">
              <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <h3 class="mt-3 text-sm font-medium text-gray-900">No Terms & Conditions Available</h3>
              <p class="mt-2 text-xs text-gray-500">
                The Terms & Conditions have not been set by the administrator yet.
              </p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Modal footer -->
        <div class="bg-transparent px-5 py-3 border-t border-gray-200 border-opacity-20">
          <div class="flex justify-end space-x-3">
            <button type="button"
                    onclick="hideTermsModal()"
                    class="rounded-lg bg-white bg-opacity-20 backdrop-blur-lg px-3 py-1.5 text-xs font-medium text-gray-700 border border-gray-300 border-opacity-30 hover:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-300">
              Cancel
            </button>
            <?php if (!empty($terms_content)): ?>
            <button type="button"
                    id="agree-button"
                    onclick="acceptTerms()"
                    disabled
                    class="modern-btn disabled:opacity-50 disabled:cursor-not-allowed text-xs">
              Agree
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Session Alert Data for JavaScript -->
  <?php if (isset($_SESSION['alert'])): ?>
  <script>
    var sessionAlert = {
      type: '<?php echo $_SESSION['alert']['type']; ?>',
      message: '<?php echo addslashes($_SESSION['alert']['message']); ?>'
    };
    <?php unset($_SESSION['alert']); ?>
  </script>
  <?php endif; ?>
  
  <!-- Session Inline Errors for JavaScript -->
  <?php if (isset($_SESSION['inline_errors'])): ?>
  <script>
    var sessionInlineErrors = <?php echo json_encode($_SESSION['inline_errors']); ?>;
    <?php unset($_SESSION['inline_errors']); ?>
  </script>
  <?php endif; ?>

  <!-- Debug Panel -->
  <div id="debug-panel" style="position: fixed; top: 10px; right: 10px; background: #000; color: #0f0; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; max-width: 400px; max-height: 300px; overflow-y: auto; z-index: 9999; display: none;">
    <div style="background: #333; padding: 5px; margin-bottom: 5px; border-radius: 3px;">
      <strong>🔍 DEBUG PANEL</strong>
      <button onclick="document.getElementById('debug-panel').style.display='none'" style="float: right; background: #f00; color: white; border: none; padding: 2px 5px; border-radius: 2px; cursor: pointer;">×</button>
    </div>
    <div id="debug-output"></div>
  </div>

  <!-- External JavaScript -->
  <script src="js/signup.js"></script>
  
  <!-- Modern Unsaved Changes Validation -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      let hasUnsavedChanges = false;
      let initialFormData = {};
      
      // Get all form inputs
      const formInputs = document.querySelectorAll('#signup-form input[type="text"], #signup-form input[type="email"], #signup-form input[type="tel"], #signup-form input[type="password"]');
      
      // Store initial form data
      function storeInitialData() {
        formInputs.forEach(input => {
          initialFormData[input.name] = input.value;
        });
      }
      
      // Check if form has unsaved changes
      function checkForUnsavedChanges() {
        hasUnsavedChanges = false;
        formInputs.forEach(input => {
          if (input.value !== (initialFormData[input.name] || '')) {
            hasUnsavedChanges = true;
          }
        });
      }
      
      // Store initial data when page loads
      storeInitialData();
      
      // Monitor form changes
      formInputs.forEach(input => {
        input.addEventListener('input', checkForUnsavedChanges);
        input.addEventListener('change', checkForUnsavedChanges);
      });
      
      // Handle form submission - clear unsaved changes flag
      document.getElementById('signup-form').addEventListener('submit', function() {
        hasUnsavedChanges = false;
      });
      
      // Note: Removed beforeunload handler to prevent browser alerts
      // The modern modal handles user interactions for F5, Ctrl+R, and close button
      
      // Handle F5 and Ctrl+R refresh specifically
      document.addEventListener('keydown', function(e) {
        // Check for F5 or Ctrl+R
        if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
          checkForUnsavedChanges();
          if (hasUnsavedChanges) {
            e.preventDefault();
            showModernConfirmDialog(
              'Unsaved Changes',
              'Your registration form contains unsaved data. Leaving now will lose your progress. Continue?',
              'Continue',
              'Stay',
              function() {
                // User chose to continue - allow refresh
                window.location.reload();
              },
              function() {
                // User chose to stay - do nothing
              }
            );
            return false;
          }
        }
      });
      
      // Handle close button click
      document.querySelector('.close-btn').addEventListener('click', function(e) {
        checkForUnsavedChanges();
        if (hasUnsavedChanges) {
          e.preventDefault();
          showModernConfirmDialog(
            'Unsaved Changes',
            'Your registration form contains unsaved data. Leaving now will lose your progress. Continue?',
            'Continue',
            'Stay',
            function() {
              // User chose to continue - navigate away
              window.location.href = this.href;
            }.bind(this),
            function() {
              // User chose to stay - do nothing
            }
          );
          return false;
        }
      });
    });
  </script>
  
  <!-- Debug Panel Script -->
  <script>
    // Show debug panel if debug mode is enabled
    if (window.location.search.includes('debug=1')) {
      document.getElementById('debug-panel').style.display = 'block';
    }
    
    // Override console.log to show in debug panel
    const originalLog = console.log;
    const debugOutput = document.getElementById('debug-output');
    
    console.log = function(...args) {
      originalLog.apply(console, args);
      const message = args.map(arg => 
        typeof arg === 'object' ? JSON.stringify(arg, null, 2) : String(arg)
      ).join(' ');
      debugOutput.innerHTML += '<div>' + message + '</div>';
      debugOutput.scrollTop = debugOutput.scrollHeight;
    };
  </script>
</body>
</html>