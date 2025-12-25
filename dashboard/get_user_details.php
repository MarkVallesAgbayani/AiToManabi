<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
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

try {
    // Get user info directly from users table (includes address fields)
    $sql = "SELECT 
                id, username, email, role, status, created_at, last_login_at,
                first_name, last_name, middle_name, suffix, age, phone_number,
                address_line1, address_line2, city, location
            FROM users
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Check if user is deleted or banned
    $restrictedStatuses = ['deleted', 'banned', 'suspended'];
    if (in_array(strtolower($user['status']), $restrictedStatuses)) {
        // Return minimal info for deleted/banned users
        $response = [
            'success' => true,
            'restricted' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'status' => ucfirst($user['status']),
                'message' => 'No account details available'
            ]
        ];
        echo json_encode($response);
        exit;
    }
    
    // Build full name for active users
    $fullName = trim(
        ($user['first_name'] ?? '') . ' ' . 
        ($user['middle_name'] ?? '') . ' ' . 
        ($user['last_name'] ?? '') . ' ' . 
        ($user['suffix'] ?? '')
    );
    
    if (empty(trim($fullName))) {
        $fullName = $user['username']; // Fallback to username
    }
    
    // Mask email for privacy
    $maskedEmail = maskEmail($user['email']);
    
    // Format the response for active users
    $response = [
        'success' => true,
        'restricted' => false,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $maskedEmail,
            'role' => ucfirst($user['role']),
            'status' => ucfirst($user['status']),
            'fullName' => $fullName,
            'age' => $user['age'] ?? 'N/A',
            'phone' => $user['phone_number'] ?? 'N/A',
            'addressLine1' => $user['address_line1'] ?? 'N/A',
            'addressLine2' => $user['address_line2'] ?? '',
            'city' => $user['city'] ?? 'N/A',
            'location' => $user['location'] ?? 'N/A',
            'createdAt' => $user['created_at'] ? date('M j, Y g:i A', strtotime($user['created_at'])) : 'N/A',
            'lastLogin' => $user['last_login_at'] ? date('M j, Y g:i A', strtotime($user['last_login_at'])) : 'Never'
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Error fetching user details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
