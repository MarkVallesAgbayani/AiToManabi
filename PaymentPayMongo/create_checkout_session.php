<?php
require_once 'config.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/payment_errors.log');

try {
    error_log('Received payment request: ' . print_r($_POST, true));

    $courseId = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

    if (!$courseId || !$userId) {
        throw new Exception('Missing required parameters.');
    }

    // Get course info
    $stmt = $pdo->prepare("SELECT title, price, description FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) throw new Exception('Course not found.');

    $client = new \GuzzleHttp\Client();

    // Create a unique temporary identifier for this checkout
    $tempId = uniqid('checkout_', true);
    
    // Generate invoice number before checkout
    $invoiceNumber = 'INV-' . strtoupper(uniqid());
    
    // Start database transaction
    try {
        $pdo->beginTransaction();
        
        // Store temporary mapping
        $stmt = $pdo->prepare("INSERT INTO temp_checkout_mapping (temp_id, user_id, course_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$tempId, $userId, $courseId]);
        
        // Store invoice number in payment_sessions table initially
        $stmt = $pdo->prepare("INSERT INTO payment_sessions (checkout_session_id, user_id, course_id, amount, invoice_number, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute(['TEMP_' . $tempId, $userId, $courseId, $course['price'], $invoiceNumber]);
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Database transaction error: ' . $e->getMessage());
        throw new Exception('Failed to initialize payment session.');
    }
    
    $successUrl = SITE_URL . '/PaymentPayMongo/payment_success.php?temp_id=' . urlencode($tempId);
    $cancelUrl = SITE_URL . '/dashboard/view_course.php?id=' . $courseId;
    

// Build the checkout data
$checkoutData = [
    'data' => [
        'attributes' => [
            'line_items' => [[
            'currency' => 'PHP',
            'amount' => intval($course['price'] * 100),
            // Include full invoice number for consistency
            'name' => $course['title'] . ' - Inv: ' . $invoiceNumber,
            'quantity' => 1,
            'description' => $course['description']
            ]],
            'payment_method_types' => ['card', 'gcash', 'paymaya', 'grab_pay', 'dob'],
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'description' => $course['title'],
            'statement_descriptor' => 'INV ' . substr($invoiceNumber, 4, 10),
            'send_email_receipt' => true,
            'show_description' => true,
            'show_line_items' => true,
            'metadata' => [
                'course_id' => $courseId,
                'user_id' => $userId,
                'temp_id' => $tempId,
                'invoice_number' => $invoiceNumber,
                'payment_type' => 'PAID'
            ]
        ]
    ]
];    
    // Create the checkout session
    $response = $client->request('POST', 'https://api.paymongo.com/v1/checkout_sessions', [
        'headers' => [
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
            'authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
        ],
        'json' => $checkoutData
    ]);

    $responseData = json_decode($response->getBody(), true);
    $checkoutSessionId = $responseData['data']['id'];
    $checkoutUrl = $responseData['data']['attributes']['checkout_url'];

    // Update the temporary mapping with the actual session ID
    $stmt = $pdo->prepare("UPDATE temp_checkout_mapping SET checkout_session_id = ? WHERE temp_id = ?");
    $stmt->execute([$checkoutSessionId, $tempId]);

    // ADD THIS: Update payment_sessions with actual checkout session ID
    $stmt = $pdo->prepare("UPDATE payment_sessions SET checkout_session_id = ? WHERE checkout_session_id = ?");
    $stmt->execute([$checkoutSessionId, 'TEMP_' . $tempId]);

    echo json_encode([
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'session_id' => $checkoutSessionId,
        'temp_id' => $tempId
    ]);

} catch (Exception $e) {
    error_log('Payment creation error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}