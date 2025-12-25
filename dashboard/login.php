<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}

session_start();

// Handle session alerts
$sessionAlert = null;
if (isset($_SESSION['alert'])) {
    $sessionAlert = $_SESSION['alert'];
    unset($_SESSION['alert']); // Clear the alert after retrieving it
}

// Handle ban modal
$banModal = null;
if (isset($_SESSION['ban_modal'])) {
    $banModal = $_SESSION['ban_modal'];
    unset($_SESSION['ban_modal']); // Clear the ban modal after retrieving it
}

// Handle deletion modal
$deletionModal = null;
if (isset($_SESSION['deletion_modal'])) {
    $deletionModal = $_SESSION['deletion_modal'];
    unset($_SESSION['deletion_modal']); // Clear the deletion modal after retrieving it
}


// Handle timeout messages from URL parameters
$timeoutMessage = null;
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $timeoutMessage = isset($_GET['message']) ? $_GET['message'] : 'Your session has expired due to inactivity. Please log in again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Japanese Learning Platform</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/Validation_login.css">
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
  <style>
    /* ---- reset ---- */
    body{ 
      margin:0; 
      font:normal 75% Arial, Helvetica, sans-serif; 
    } 
    canvas{ 
      display: block; 
      vertical-align: bottom; 
    } 
    /* ---- particles.js container ---- */
    #particles-js{ 
      position: fixed; 
      top: 0;
      left: 0;
      width: 100%; 
      height: 100vh; 
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      background-repeat: no-repeat; 
      background-size: cover; 
      background-position: 50% 50%; 
      z-index: 1;
      pointer-events: auto;
      overflow: visible;
    }
    
    /* Ensure particles canvas is visible */
    #particles-js canvas {
      display: block !important;
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      z-index: 1 !important;
      opacity: 1 !important;
      visibility: visible !important;
    }
    
    /* Fix blurriness and improve particle visibility */
    #particles-js canvas {
      image-rendering: -webkit-optimize-contrast !important;
      image-rendering: crisp-edges !important;
      image-rendering: pixelated !important;
      transform: translateZ(0) !important;
      backface-visibility: hidden !important;
    } 
    /* ---- stats.js ---- */
    .count-particles{ 
      background: #000022; 
      position: absolute; 
      top: 48px; 
      left: 0; 
      width: 80px; 
      color: #13E8E9; 
      font-size: .8em; 
      text-align: left; 
      text-indent: 4px; 
      line-height: 14px; 
      padding-bottom: 2px; 
      font-family: Helvetica, Arial, sans-serif; 
      font-weight: bold; 
      display: none; /* Hide the particle counter */
    } 
    .js-count-particles{ 
      font-size: 1.1em; 
    } 
    #stats, .count-particles{ 
      -webkit-user-select: none; 
      margin-top: 5px; 
      margin-left: 5px; 
    } 
    #stats{ 
      border-radius: 3px 3px 0 0; 
      overflow: hidden; 
    } 
    .count-particles{ 
      border-radius: 0 0 3px 3px; 
    }

    .font-jp { font-family: 'Noto Sans JP', sans-serif; }
    
    /* Modern Input Field Styling */
    .modern-input {
      position: relative;
      margin-bottom: 1.5rem;
    }
    
    .modern-input input {
      width: 100%;
      padding: 1rem 1rem 1rem 3rem;
      border: 2px solid #e2e8f0;
      border-radius: 1rem;
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      font-size: 0.95rem;
      color: #334155;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    
    .modern-input input:focus {
      outline: none;
      border-color: #dc2626;
      background: #ffffff;
      box-shadow: 
        0 0 0 4px rgba(220, 38, 38, 0.1),
        0 10px 15px -3px rgba(220, 38, 38, 0.1),
        0 4px 6px -2px rgba(220, 38, 38, 0.05);
      transform: translateY(-2px);
    }
    
    .modern-input input::placeholder {
      color: #94a3b8;
      font-weight: 400;
    }
    
    .modern-input .input-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      transition: all 0.3s ease;
      z-index: 2;
    }
    
    .modern-input input:focus + .input-icon {
      color: #dc2626;
      transform: translateY(-50%) scale(1.1);
    }
    
    .password-toggle {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #64748b;
      padding: 0.5rem;
      border-radius: 0.75rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 3;
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(10px);
    }
    
    .password-toggle:hover {
      color: #dc2626;
      background: rgba(220, 38, 38, 0.1);
      transform: translateY(-50%) scale(1.1);
    }
    
    /* Modern Button Styling */
    .modern-btn {
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #991b1b 100%);
      border: none;
      border-radius: 1rem;
      padding: 1rem 2rem;
      color: white;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 
        0 10px 15px -3px rgba(220, 38, 38, 0.3),
        0 4px 6px -2px rgba(220, 38, 38, 0.1);
      position: relative;
      overflow: hidden;
    }
    
    .modern-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: left 0.6s;
    }
    
    .modern-btn:hover {
      transform: translateY(-3px);
      box-shadow: 
        0 20px 25px -5px rgba(220, 38, 38, 0.4),
        0 10px 10px -5px rgba(220, 38, 38, 0.2);
    }
    
    .modern-btn:hover::before {
      left: 100%;
    }
    
    .modern-btn:active {
      transform: translateY(-1px);
    }
    
    /* Modern Card Header */
    .modern-header {
      text-align: center;
      margin-bottom: 2rem;
    }
    
    .modern-title {
      font-size: 2rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 0.5rem;
      background: linear-gradient(135deg, #1e293b 0%, #475569 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .modern-subtitle {
      color: #64748b;
      font-size: 0.95rem;
      font-weight: 400;
    }
    
    /* Close button styling */
    .close-btn {
      position: absolute;
      top: 1.5rem;
      right: 1.5rem;
      color: #64748b;
      transition: all 0.3s ease;
      padding: 0.5rem;
      border-radius: 0.75rem;
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(10px);
    }
    
    .close-btn:hover {
      color: #dc2626;
      background: rgba(220, 38, 38, 0.1);
      transform: scale(1.1);
    }
    
    /* Footer link styling */
    .footer-text {
      text-align: center;
      margin-top: 2rem;
      color: #64748b;
      font-size: 0.9rem;
    }
    
    .footer-link {
      color: #dc2626;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
    }
    
    .footer-link::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: -2px;
      left: 0;
      background: #dc2626;
      transition: width 0.3s ease;
    }
    
    .footer-link:hover {
      color: #b91c1c;
    }
    
    .footer-link:hover::after {
      width: 100%;
    }
    
    /* Loading spinner styles */
    .hidden {
      display: none !important;
    }
    
    .animate-spin {
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      from {
        transform: rotate(0deg);
      }
      to {
        transform: rotate(360deg);
      }
    }
    
    /* Disabled button state */
    .modern-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
    }
    
    .modern-btn:disabled:hover {
      transform: none !important;
      box-shadow: 
        0 10px 15px -3px rgba(220, 38, 38, 0.3),
        0 4px 6px -2px rgba(220, 38, 38, 0.1);
    }
    
    /* Forgot password link */
    .forgot-link {
      color: #64748b;
      font-size: 0.85rem;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
    }
    
    .forgot-link::after {
      content: '';
      position: absolute;
      width: 0;
      height: 1px;
      bottom: -2px;
      left: 0;
      background: #dc2626;
      transition: width 0.3s ease;
    }
    
    .forgot-link:hover {
      color: #dc2626;
    }
    
    .forgot-link:hover::after {
      width: 100%;
    }

    /* Professional 3D Modern Card Effect */
    .neon-border-container {
      position: relative;
      z-index: 10;
      border-radius: 1.5rem;
      overflow: visible;
      background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.98) 0%, 
        rgba(255, 255, 255, 0.95) 50%, 
        rgba(255, 255, 255, 0.92) 100%);
      backdrop-filter: blur(25px);
      -webkit-backdrop-filter: blur(25px);
      border: 1px solid rgba(255, 255, 255, 0.4);
      box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.1),
        0 8px 16px rgba(220, 38, 38, 0.15),
        0 0 0 1px rgba(255, 255, 255, 0.2) inset,
        0 0 0 1px rgba(220, 38, 38, 0.05) inset,
        0 4px 8px rgba(0, 0, 0, 0.05);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      transform-style: preserve-3d;
      perspective: 1000px;
    }
    
    .neon-border-container:hover {
      box-shadow: 
        0 25px 50px rgba(0, 0, 0, 0.15),
        0 12px 24px rgba(220, 38, 38, 0.2),
        0 0 0 1px rgba(255, 255, 255, 0.3) inset,
        0 0 0 1px rgba(220, 38, 38, 0.1) inset,
        0 6px 12px rgba(0, 0, 0, 0.08);
      transform: translateY(-4px) rotateX(2deg) rotateY(1deg);
    }
    
    .neon-border-anim {
      pointer-events: none;
      position: absolute;
      top: -12px; left: -12px; right: -12px; bottom: -12px;
      z-index: 9;
      border-radius: 1.75rem;
      background: linear-gradient(135deg, 
        rgba(220, 38, 38, 0.08) 0%, 
        rgba(185, 28, 28, 0.12) 50%, 
        rgba(220, 38, 38, 0.06) 100%);
      border: 1px solid rgba(220, 38, 38, 0.2);
      box-shadow:
        0 0 30px rgba(220, 38, 38, 0.3),
        0 0 60px rgba(220, 38, 38, 0.15),
        0 0 90px rgba(220, 38, 38, 0.08),
        0 8px 16px rgba(0, 0, 0, 0.1);
      opacity: 0.9;
      animation: modern-glow 6s ease-in-out infinite alternate;
      transform-style: preserve-3d;
    }
    
    @keyframes modern-glow {
      0% { 
        box-shadow: 
          0 0 30px rgba(220, 38, 38, 0.3),
          0 0 60px rgba(220, 38, 38, 0.15),
          0 0 90px rgba(220, 38, 38, 0.08),
          0 8px 16px rgba(0, 0, 0, 0.1);
        opacity: 0.9;
        transform: scale(1);
      }
      50% {
        box-shadow: 
          0 0 40px rgba(220, 38, 38, 0.4),
          0 0 80px rgba(220, 38, 38, 0.2),
          0 0 120px rgba(220, 38, 38, 0.1),
          0 12px 24px rgba(0, 0, 0, 0.12);
        opacity: 1;
        transform: scale(1.02);
      }
      100% { 
        box-shadow: 
          0 0 35px rgba(220, 38, 38, 0.35),
          0 0 70px rgba(220, 38, 38, 0.18),
          0 0 105px rgba(220, 38, 38, 0.09),
          0 10px 20px rgba(0, 0, 0, 0.11);
        opacity: 0.95;
        transform: scale(1.01);
      }
    }

    /* Inline Error Messages */
    /* ==================== */
    .inline-error-message {
      font-size: 12px;
      font-weight: 400;
      margin: 0;
      padding: 0;
      border-radius: 0;
      background: transparent;
      color: #ef4444;
      border: none;
      box-shadow: none;
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
      opacity: 0;
      transform: translateY(0);
      transition: opacity 0.3s ease, max-height 0.3s ease;
      pointer-events: none;
      max-height: 0;
      overflow: hidden;
      line-height: 1.4;
      word-wrap: break-word;
      hyphens: auto;
      display: block;
      position: static;
      width: 100%;
      box-sizing: border-box;
      margin-top: 4px;
    }

    .inline-error-message.show {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
      max-height: 48px;
      display: block;
      position: static;
    }

    .inline-error-message.success {
      background: transparent;
      color: #10b981;
      box-shadow: none;
    }

    .inline-error-message.warning {
      background: transparent;
      color: #f59e0b;
      box-shadow: none;
    }

    /* Input field error state */
    .modern-input.has-error input {
      border-color: #ef4444;
      background: rgba(239, 68, 68, 0.05);
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    .modern-input.has-error .input-icon {
      color: #ef4444;
    }

    /* Input field success state */
    .modern-input.has-success input {
      border-color: #10b981;
      background: rgba(16, 185, 129, 0.05);
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .modern-input.has-success .input-icon {
      color: #10b981;
    }

    /* For fields with margin bottom */
    .mb-3 {
      position: relative;
      margin-bottom: 1.5rem;
    }

    .mb-3 .inline-error-message {
      position: static;
      margin-top: 4px;
    }

    /* Modern Alert System */
    .alert-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      max-width: 400px;
      pointer-events: none;
    }

    .alert {
      position: relative;
      margin-bottom: 12px;
      border-radius: 12px;
      box-shadow: 
        0 10px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      pointer-events: auto;
      animation: slideInRight 0.4s ease-out forwards;
    }

    .alert.hiding {
      animation: slideOutRight 0.3s ease-in forwards;
    }

    .alert-content {
      display: flex;
      align-items: flex-start;
      padding: 16px 20px;
      gap: 12px;
      position: relative;
    }

    .alert-icon {
      flex-shrink: 0;
      width: 20px;
      height: 20px;
      margin-top: 2px;
    }

    .alert-message {
      flex: 1;
      font-size: 14px;
      font-weight: 500;
      line-height: 1.4;
      margin: 0;
    }

    .alert-close {
      flex-shrink: 0;
      width: 24px;
      height: 24px;
      border: none;
      background: none;
      color: inherit;
      cursor: pointer;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0.7;
      transition: all 0.2s ease;
    }

    .alert-close:hover {
      opacity: 1;
      background: rgba(255, 255, 255, 0.1);
      transform: scale(1.1);
    }

    .alert-close svg {
      width: 14px;
      height: 14px;
    }

    /* Alert Types */
    .alert-error {
      background: linear-gradient(135deg, 
        rgba(239, 68, 68, 0.95) 0%, 
        rgba(220, 38, 38, 0.95) 100%);
      color: white;
      border-color: rgba(255, 255, 255, 0.3);
    }

    .alert-success {
      background: linear-gradient(135deg, 
        rgba(16, 185, 129, 0.95) 0%, 
        rgba(5, 150, 105, 0.95) 100%);
      color: white;
      border-color: rgba(255, 255, 255, 0.3);
    }

    .alert-warning {
      background: linear-gradient(135deg, 
        rgba(255, 51, 0, 0.95) 0%, 
        rgba(255, 38, 0, 0.95) 100%);
      color: white;
      border-color: rgba(255, 255, 255, 0.3);
    }

    .alert-info {
      background: linear-gradient(135deg, 
        rgba(59, 130, 246, 0.95) 0%, 
        rgba(37, 99, 235, 0.95) 100%);
      color: white;
      border-color: rgba(255, 255, 255, 0.3);
    }

    /* Progress Bar for Auto-dismiss */
    .alert-progress {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 3px;
      border-radius: 0 0 12px 12px;
      overflow: hidden;
    }

    .alert-progress-bar {
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.4);
      animation: progressShrink 5s linear forwards;
      transform-origin: left;
    }

    /* Animations */
    @keyframes slideInRight {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes slideOutRight {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(100%);
        opacity: 0;
      }
    }

    @keyframes progressShrink {
      from {
        transform: scaleX(1);
      }
      to {
        transform: scaleX(0);
      }
    }

    /* Mobile Responsive Design - Match Signup Layout */
    @media (max-width: 767px) {
      /* Remove particles.js and use solid gradient background */
      #particles-js {
        display: none !important;
      }
      
      body {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #991b1b 100%);
        position: relative;
      }
      
      /* Add subtle geometric pattern overlay */
      body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: 
          radial-gradient(circle at 25% 25%, rgba(255,255,255,0.1) 1px, transparent 1px),
          radial-gradient(circle at 75% 75%, rgba(255,255,255,0.08) 1px, transparent 1px);
        background-size: 30px 30px;
        background-position: 0 0, 15px 15px;
        pointer-events: none;
        z-index: 1;
      }
      
      .neon-border-container {
        max-width: 90%;
        width: 90%;
        margin: 0 auto;
        padding: 1.5rem;
        border-radius: 1rem;
        position: relative;
        z-index: 10;
      }
      
      .neon-border-anim {
        border-radius: 1rem;
      }
      
      .modern-header {
        margin-bottom: 1.5rem;
        text-align: center;
      }
      
      .modern-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
      }
      
      .modern-subtitle {
        font-size: 0.8rem;
        color: #64748b;
      }
      
      .close-btn {
        top: 1rem;
        right: 1rem;
        width: 28px;
        height: 28px;
        padding: 0.25rem;
      }
      
      .close-btn svg {
        width: 14px;
        height: 14px;
      }
      
      .modern-input {
        margin-bottom: 1rem;
      }
      
      .modern-input input {
        padding: 0.75rem 0.75rem 0.75rem 2.5rem;
        font-size: 0.8rem;
        height: 2.5rem;
        border-radius: 0.75rem;
      }
      
      .modern-input .input-icon {
        left: 0.75rem;
        width: 1rem;
        height: 1rem;
      }
      
      .password-toggle {
        right: 0.75rem;
        width: 1.5rem;
        height: 1.5rem;
        padding: 0.25rem;
      }
      
      .password-toggle svg {
        width: 1rem;
        height: 1rem;
      }
      
      .modern-btn {
        padding: 0.75rem 1.5rem;
        font-size: 0.9rem;
        border-radius: 0.75rem;
      }
      
      .forgot-link {
        font-size: 0.8rem;
      }
      
      .footer-text {
        margin-top: 1.5rem;
        font-size: 0.8rem;
      }
      
      .mb-3 {
        margin-bottom: 1rem;
      }
      
      .inline-error-message {
        font-size: 0.75rem;
        margin-top: 0.25rem;
      }
    }
    
    /* Extra Small Mobile */
    @media (max-width: 480px) {
      .neon-border-container {
        max-width: 95%;
        width: 95%;
        padding: 1.25rem;
      }
      
      .modern-title {
        font-size: 1.25rem;
      }
      
      .modern-subtitle {
        font-size: 0.75rem;
      }
      
      .modern-input input {
        padding: 0.6rem 0.6rem 0.6rem 2.25rem;
        font-size: 0.75rem;
        height: 2.25rem;
      }
      
      .modern-input .input-icon {
        left: 0.6rem;
        width: 0.875rem;
        height: 0.875rem;
      }
      
      .password-toggle {
        right: 0.6rem;
        width: 1.25rem;
        height: 1.25rem;
      }
      
      .password-toggle svg {
        width: 0.875rem;
        height: 0.875rem;
      }
      
      .modern-btn {
        padding: 0.6rem 1.25rem;
        font-size: 0.8rem;
      }
      
      .forgot-link {
        font-size: 0.75rem;
      }
      
      .footer-text {
        font-size: 0.75rem;
      }
      
      .inline-error-message {
        font-size: 0.7rem;
      }
    }
    
    /* Responsive Design */
    @media (max-width: 640px) {
      .alert-container {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
      }
      
      .alert-content {
        padding: 14px 16px;
        gap: 10px;
      }
      
      .alert-message {
        font-size: 13px;
      }
    }
  </style>
</head>
<body class="bg-gray-100 font-jp min-h-screen flex items-center justify-center relative">
  <!-- Modern Alert Container -->
  <div id="alert-container" class="alert-container"></div>
  
  <!-- particles.js container -->
  <div id="particles-js"></div>
  <!-- stats - count particles -->
  <div class="count-particles">
    <span class="js-count-particles">--</span> particles
  </div>
  
  <div class="neon-border-container p-10 rounded-lg max-w-lg w-full m-4 overflow-visible">
    <div class="neon-border-anim"></div>
    
    <!-- Modern Header -->
    <div class="modern-header">
      <h1 class="modern-title">Welcome Back!</h1>
      <p class="modern-subtitle">Sign in to continue your Japanese Learning Journey</p>
      <a href="../index.php" class="close-btn">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </a>
    </div>



    <!-- Modern Form -->
    <form action="../auth/auth.php" method="POST" id="loginForm" novalidate>
      <input type="hidden" name="action" value="login">
      
      <!-- Email/Username Field -->
      <div class="mb-3">
        <div class="modern-input">
          <input 
            type="text" 
            name="email" 
            required 
            placeholder="Enter your email or username"
            autocomplete="username"
            minlength="3"
            maxlength="100"
          >
          <div class="input-icon">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
          </div>
        </div>
        <div class="inline-error-message" id="email-error"></div>
      </div>

      <!-- Password Field -->
      <div class="mb-3">
        <div class="modern-input">
          <input 
            type="password" 
            name="password" 
            id="password" 
            required 
            placeholder="Enter your password"
            autocomplete="current-password"
            minlength="6"
            maxlength="128"
          >
          <div class="input-icon">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
          </div>
          <button type="button" class="password-toggle" onclick="togglePassword('password')" tabindex="-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
        <div class="inline-error-message" id="password-error"></div>
      </div>

      <!-- Forgot Password Link -->
      <div class="flex justify-end mb-6">
        <a href="../forgetpassword/forgot-password.php" class="forgot-link">
          Forgot your password?
        </a>
      </div>

      <!-- Login Button -->
      <button type="submit" class="modern-btn w-full" id="loginBtn">
        <span id="btnText">Sign In</span>
        <span id="btnSpinner" class="hidden">
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Signing In...
        </span>
      </button>
    </form>

    <!-- Footer -->
    <div class="footer-text">
      Don't have an account?
      <a href="signup.php" class="footer-link ml-1">Create New Account</a>
    </div>
  </div>

  <!-- Session Inline Errors Data for JavaScript -->
  <?php if (isset($_SESSION['inline_errors'])): ?>
  <script>
    var sessionErrors = <?php echo json_encode($_SESSION['inline_errors']); ?>;
    <?php unset($_SESSION['inline_errors']); ?>
  </script>
  <?php endif; ?>

  <script>
    // Pass session alert data to JavaScript
    <?php if ($sessionAlert): ?>
    const sessionAlert = {
      type: '<?php echo htmlspecialchars($sessionAlert['type']); ?>',
      message: '<?php echo htmlspecialchars($sessionAlert['message']); ?>'
    };
    <?php else: ?>
    const sessionAlert = null;
    <?php endif; ?>
    
    // Handle timeout message
    <?php if ($timeoutMessage): ?>
    const timeoutMessage = '<?php echo htmlspecialchars($timeoutMessage); ?>';
    <?php else: ?>
    const timeoutMessage = null;
    <?php endif; ?>
  </script>
  <script src="js/login.js"></script>

  <!-- Ban Modal -->
  <?php if ($banModal): ?>
  <div id="banModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: flex;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 transform transition-all duration-300 scale-100">
      <!-- Modal Header -->
      <div class="bg-gradient-to-r from-red-500 to-red-600 text-white p-6 rounded-t-2xl">
        <div class="flex items-center justify-center mb-2">
          <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
          </div>
        </div>
        <h3 class="text-xl font-bold text-center">Account Access Restricted</h3>
        <p class="text-red-100 text-center text-sm mt-1">Your account has been temporarily restricted</p>
      </div>

      <!-- Modal Body -->
      <div class="p-6">
        <div class="mb-6">
          <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <h4 class="font-semibold text-red-800 mb-2 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
              </svg>
              Reason for Restriction
            </h4>
            <p class="text-red-700 text-sm italic">"<?php echo htmlspecialchars($banModal['reason']); ?>"</p>
          </div>

          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
            <h4 class="font-semibold text-gray-800 mb-2 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
              </svg>
              Restriction Details
            </h4>
            <div class="text-sm text-gray-600">
              <p class="mb-1"><strong>Restricted on:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($banModal['banned_at'])); ?></p>
              <p><strong>Status:</strong> <span class="text-red-600 font-semibold">Access Restricted</span></p>
            </div>
          </div>

          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="font-semibold text-blue-800 mb-2 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
              </svg>
              What This Means
            </h4>
            <ul class="text-sm text-blue-700 space-y-1">
              <li>• You cannot log in to your account</li>
              <li>• Access to all courses and features is restricted</li>
              <li>• Your data remains safe and will be restored when access is reinstated</li>
            </ul>
          </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
          <h4 class="font-semibold text-yellow-800 mb-2 flex items-center">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            Need Help?
          </h4>
          <p class="text-sm text-yellow-700 mb-2">
            If you believe this restriction was made in error or have questions, please contact our support team.
          </p>
          <div class="flex flex-col sm:flex-row gap-2">
            <button onclick="openContactSupport()" class="inline-flex items-center px-3 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
              Contact Support
            </button>
            <button onclick="closeBanModal()" class="inline-flex items-center px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
              Close
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-gray-50 px-6 py-4 rounded-b-2xl">
        <div class="text-center">
          <p class="text-xs text-gray-500">
            This is an automated message from <strong>AiToManabi LMS</strong>
          </p>
          <p class="text-xs text-gray-400 mt-1">
            Restriction applied on: <?php echo date('F j, Y \a\t g:i A', $banModal['timestamp']); ?> (Philippine Time)
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Contact Support Modal -->
  <div id="contactSupportModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50" style="display: none; z-index: 9999;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-100">
      <!-- Modal Header -->
      <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-t-2xl">
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-3">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold">Contact Support</h3>
              <p class="text-blue-100 text-sm">Appeal your account restriction</p>
            </div>
          </div>
          <button onclick="closeContactSupport()" class="text-white hover:text-blue-200 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="p-6">
        <form id="contactSupportForm" onsubmit="submitSupportRequest(event)">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Your Email Address</label>
            <input type="email" id="supportEmail" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter your email address">
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
            <input type="text" id="supportSubject" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Account Restriction Appeal" value="Account Restriction Appeal">
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Your Message</label>
            <textarea id="supportMessage" required rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Please explain why you believe this restriction was made in error. Include any relevant details that might help us review your case."></textarea>
          </div>

          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
            <p class="text-sm text-blue-700">
              <strong>Note:</strong> We will review your appeal within 24-48 hours. Please provide as much detail as possible to help us understand your situation.
            </p>
          </div>

          <div class="flex flex-col sm:flex-row gap-2">
            <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors font-medium">
              <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
              </svg>
              Send Appeal
            </button>
            <button type="button" onclick="closeContactSupport()" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors font-medium">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function closeBanModal() {
      const modal = document.getElementById('banModal');
      modal.style.display = 'none';
    }

    function openContactSupport() {
      const contactModal = document.getElementById('contactSupportModal');
      
      if (contactModal) {
        contactModal.style.display = 'flex';
        contactModal.style.zIndex = '9999';
        console.log('Contact support modal opened');
      } else {
        console.error('Contact support modal not found');
        // Fallback: open email directly
        const mailtoLink = `mailto:aitosensei@aitomanabi.com?subject=${encodeURIComponent('Account Restriction Appeal')}&body=${encodeURIComponent('Please help me with my account restriction appeal.')}`;
        window.location.href = mailtoLink;
      }
    }

    function closeContactSupport() {
      const contactModal = document.getElementById('contactSupportModal');
      contactModal.style.display = 'none';
    }

    function submitSupportRequest(event) {
      console.log('Form submission triggered!');
      event.preventDefault();
      
      const email = document.getElementById('supportEmail').value;
      const subject = document.getElementById('supportSubject').value;
      const message = document.getElementById('supportMessage').value;
      
      console.log('Form data collected:', { email, subject, message });
      
      // Validate form data
      if (!email || !subject || !message) {
        alert('Please fill in all required fields.');
        return;
      }
      
      // Show confirmation dialog
      console.log('Showing confirmation dialog...');
      showConfirmationDialog(email, subject, message);
    }

    function showConfirmationDialog(email, subject, message) {
      console.log('Creating confirmation dialog...');
      console.log('Dialog data:', { email, subject, message });
      
      // Create confirmation modal HTML
      const confirmationModalHTML = `
        <div id="confirmationModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50" style="display: flex; z-index: 10001; position: fixed; top: 0; left: 0; width: 100%; height: 100%;">
          <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-100">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-t-2xl">
              <div class="flex items-center justify-center mb-2">
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <h3 class="text-xl font-bold text-center">Confirm Appeal Submission</h3>
              <p class="text-blue-100 text-center text-sm mt-1">Please review your appeal before sending</p>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
              <div class="mb-4">
                <h4 class="font-semibold text-gray-800 mb-2">Your Appeal Details:</h4>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3">
                  <p class="text-sm text-gray-600 mb-1"><strong>Email:</strong> ${email}</p>
                  <p class="text-sm text-gray-600 mb-1"><strong>Subject:</strong> ${subject}</p>
                  <p class="text-sm text-gray-600"><strong>Message:</strong></p>
                  <p class="text-sm text-gray-700 mt-1 italic">"${message}"</p>
                </div>
              </div>

              <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-blue-700">
                  <strong>Note:</strong> Clicking "Send Appeal" will open your email client with a pre-filled message. You'll need to send the email to complete your appeal.
                </p>
              </div>

              <div class="flex flex-col sm:flex-row gap-2">
                <button onclick="confirmAppealSubmission('${email}', '${subject}', '${message}')" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                  <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                  </svg>
                  Send Appeal
                </button>
                <button onclick="closeConfirmationModal()" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors font-medium">
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      `;

      // Add modal to page
      console.log('Adding confirmation modal to page...');
      document.body.insertAdjacentHTML('beforeend', confirmationModalHTML);
      console.log('Confirmation modal added to page');
      
      // Check if modal was added
      const addedModal = document.getElementById('confirmationModal');
      console.log('Confirmation modal element:', addedModal);
      
      // Test visibility
      if (addedModal) {
        console.log('Modal display style:', addedModal.style.display);
        console.log('Modal z-index:', addedModal.style.zIndex);
        console.log('Modal position:', addedModal.style.position);
        
        // Force visibility
        addedModal.style.display = 'flex';
        addedModal.style.zIndex = '10001';
        addedModal.style.position = 'fixed';
        addedModal.style.top = '0';
        addedModal.style.left = '0';
        addedModal.style.width = '100%';
        addedModal.style.height = '100%';
        
        console.log('Modal forced to be visible');
      }
    }

    function confirmAppealSubmission(email, subject, message) {
      console.log('Confirming appeal submission...');
      console.log('Appeal data:', { email, subject, message });
      
      // Close confirmation modal
      closeConfirmationModal();
      
      // Create mailto link with pre-filled content
      const mailtoLink = `mailto:aitosensei@aitomanabi.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(`Email: ${email}\n\nMessage:\n${message}`)}`;
      
      console.log('Mailto link created:', mailtoLink);
      
      // Open default email client
      console.log('Opening email client...');
      window.location.href = mailtoLink;
      
      // Show success modal
      console.log('Showing success modal...');
      showSuccessModal();
      
      // Close the contact modal
      closeContactSupport();
    }

    function closeConfirmationModal() {
      const confirmationModal = document.getElementById('confirmationModal');
      if (confirmationModal) {
        confirmationModal.remove();
      }
    }

    function showSuccessModal() {
      console.log('showSuccessModal function called!');
      
      // Create success modal HTML
      const successModalHTML = `
        <div id="successModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50" style="display: flex; z-index: 10000;">
          <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-100">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-t-2xl">
              <div class="flex items-center justify-center mb-2">
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                </div>
              </div>
              <h3 class="text-xl font-bold text-center">Appeal Sent Successfully!</h3>
              <p class="text-green-100 text-center text-sm mt-1">Your appeal has been submitted</p>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
              <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                  <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Thank you for your appeal!</h4>
                <p class="text-gray-600 text-sm mb-4">
                  Your email client should have opened with a pre-filled message. Please send the email to complete your appeal.
                </p>
              </div>

              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h5 class="font-semibold text-blue-800 mb-2 flex items-center">
                  <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                  </svg>
                  What happens next?
                </h5>
                <ul class="text-sm text-blue-700 space-y-1">
                  <li>• Our support team will review your appeal</li>
                  <li>• You'll receive a response within 24-48 hours</li>
                  <li>• Check your email for updates on your case</li>
                </ul>
              </div>

              <div class="flex flex-col sm:flex-row gap-2">
                <button onclick="closeSuccessModal()" class="flex-1 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors font-medium">
                  <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                  Got it!
                </button>
                <button onclick="closeSuccessModal(); closeBanModal();" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors font-medium">
                  Close All
                </button>
              </div>
            </div>
          </div>
        </div>
      `;

      // Add modal to page
      console.log('Adding success modal to page...');
      document.body.insertAdjacentHTML('beforeend', successModalHTML);
      console.log('Success modal added to page');
      
      // Check if modal was added
      const addedModal = document.getElementById('successModal');
      console.log('Success modal element:', addedModal);
    }

    function closeSuccessModal() {
      const successModal = document.getElementById('successModal');
      if (successModal) {
        successModal.remove();
      }
    }

    // Close modal when clicking outside
    document.getElementById('banModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeBanModal();
      }
    });

    document.getElementById('contactSupportModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeContactSupport();
      }
    });

    // Prevent modal from closing on escape key (user must click close)
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        e.preventDefault();
      }
    });
  </script>
  <?php endif; ?>

    <!-- Deletion Modal -->
  <?php if ($deletionModal): ?>
  <div id="deletionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: flex;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 transform transition-all duration-300 scale-100">
      <!-- Modal Header -->
      <div class="bg-gradient-to-r from-red-500 to-red-600 text-white p-6 rounded-t-2xl">
        <div class="flex items-center justify-center mb-2">
          <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
          </div>
        </div>
        <h3 class="text-xl font-bold text-center">Account Deleted</h3>
        <p class="text-red-100 text-center text-sm mt-1">Your account has been moved to deleted users</p>
      </div>

      <!-- Modal Body -->
      <div class="p-6">
        <div class="mb-6">
          <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <h4 class="font-semibold text-red-800 mb-2 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
              </svg>
              Reason for Deletion
            </h4>
            <p class="text-red-700 text-sm italic">"<?php echo htmlspecialchars($deletionModal['reason']); ?>"</p>
          </div>

          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
            <h4 class="font-semibold text-gray-800 mb-2 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
              </svg>
              Deletion Details
            </h4>
            <div class="text-sm text-gray-600">
              <p class="mb-1"><strong>Deleted on:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($deletionModal['deleted_at'])); ?> (Philippine Time)</p>
              <?php if ($deletionModal['restoration_deadline']): ?>
              <p><strong>Restoration Deadline:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($deletionModal['restoration_deadline'])); ?> (Philippine Time)</p>
              <?php endif; ?>
              <p><strong>Status:</strong> <span class="text-red-600 font-semibold">Account Deleted</span></p>
            </div>
          </div>

          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="font-semibold text-blue-800 mb-2 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
              </svg>
              What This Means
            </h4>
            <ul class="text-sm text-blue-700 space-y-1">
              <li>• You cannot log in to your account</li>
              <li>• All your data is preserved but hidden from the system</li>
              <li>• Your account can be restored before the deadline</li>
              <li>• Contact support if you believe this was done in error</li>
            </ul>
          </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
          <h4 class="font-semibold text-yellow-800 mb-2 flex items-center">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            Need Help?
          </h4>
          <p class="text-sm text-yellow-700 mb-2">
            If you believe this deletion was made in error or have questions, please contact our support team.
          </p>
          <div class="flex flex-col sm:flex-row gap-2">
            <button onclick="openContactSupport()" class="inline-flex items-center px-3 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
              Contact Support
            </button>
            <button onclick="closeDeletionModal()" class="inline-flex items-center px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
              Close
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-gray-50 px-6 py-4 rounded-b-2xl">
        <div class="text-center">
          <p class="text-xs text-gray-500">
            This is an automated message from <strong>AiToManabi LMS</strong>
          </p>
          <p class="text-xs text-gray-400 mt-1">
            Deletion applied on: <?php echo date('F j, Y \a\t g:i A', $deletionModal['timestamp']); ?> (Philippine Time)
          </p>
        </div>
      </div>
    </div>
  </div>

  <script>
    function closeDeletionModal() {
      const modal = document.getElementById('deletionModal');
      modal.style.display = 'none';
    }

    // Close modal when clicking outside
    document.getElementById('deletionModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeDeletionModal();
      }
    });

    // Prevent modal from closing on escape key (user must click close)
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        e.preventDefault();
      }
    });
  </script>
  <?php endif; ?>
</body>
</html>