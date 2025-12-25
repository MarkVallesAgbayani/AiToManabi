<?php
require_once '../config/config.php';
require_once '../src/controllers/AuthController.php';

// Start session
session_start();

// Route the request
$request = $_SERVER['REQUEST_URI'];

switch ($request) {
    case '/':
        require '../src/views/layouts/landing.php';
        break;
    case '/login':
        require '../src/views/auth/login.php';
        break;
    case '/register':
        require '../src/views/auth/register.php';
        break;
    default:
        http_response_code(404);
        require '../src/views/layouts/404.php';
        break;
} 