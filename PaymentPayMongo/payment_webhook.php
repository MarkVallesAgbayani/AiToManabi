<?php
session_start();
// Webhook handler for PayMongo payment success
require_once '../config/database.php';

// Set timezone for database connection
$pdo->exec("SET time_zone = '+08:00'");

$logFile = __DIR__ . '/../logs/payment_webhook.log';
function webhook_log($msg) {
    file_put_contents(__DIR__ . '/../logs/payment_webhook.log', date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL, FILE_APPEND);
}

// 1. Read raw POST payload
$payload = file_get_contents('php://input');
webhook_log('Raw payload: ' . $payload);

// 2. (Optional) Verify webhook signature if provided by PayMongo
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? null;
if ($signature) {
    webhook_log('Signature: ' . $signature);
}

// 3. Parse JSON
$data = json_decode($payload, true);
if (!$data) {
    webhook_log('Invalid JSON payload');
    http_response_code(400);
    exit('Invalid JSON');
}

// 4. Check event type
$eventType = $data['data']['attributes']['type'] ?? '';
if ($eventType !== 'checkout_session.payment_successful' && $eventType !== 'checkout_session.payment.paid') {
    webhook_log('Ignoring event type: ' . $eventType);
    http_response_code(200);
    exit('Ignored');
}

// 5. Extract checkout_session_id and metadata
$session = $data['data']['attributes']['data'] ?? [];
$checkoutSessionId = $session['id'] ?? null;
$metadata = $session['attributes']['metadata'] ?? [];
$userId = $metadata['user_id'] ?? null;
$courseId = $metadata['course_id'] ?? null;

webhook_log("Processing payment_successful for session_id=$checkoutSessionId, user_id=$userId, course_id=$courseId");

if (!$checkoutSessionId || !$userId || !$courseId) {
    webhook_log('Missing required info for enrollment.');
    http_response_code(400);
    exit('Missing info');
}

try {
    // 6. Update payment_sessions to completed and fetch amount/invoice robustly
    $stmt = $pdo->prepare("SELECT amount, status, invoice_number FROM payment_sessions WHERE checkout_session_id = ?");
    $stmt->execute([$checkoutSessionId]);
    $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sessionRow) {
        webhook_log("ERROR: payment_sessions row not found for session_id=$checkoutSessionId");
        http_response_code(400);
        exit('Session not found');
    }
    $amount = floatval($sessionRow['amount']);
    
    // Always update status to completed if not already
    if ($sessionRow['status'] !== 'completed') {
        $stmt = $pdo->prepare("UPDATE payment_sessions SET status = 'completed', completed_at = NOW() WHERE checkout_session_id = ?");
        $stmt->execute([$checkoutSessionId]);
        webhook_log("Updated payment_sessions for $checkoutSessionId");
    }
    
    $paymentType = $metadata['payment_type'] ?? 'PAID';
    if ($amount > 0) {
        // Check if already exists
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND amount = ? AND payment_type = ?");
        $stmt->execute([$userId, $courseId, $amount, $paymentType]);
        $existingPayment = $stmt->fetch();
        
        if (!$existingPayment) {
            // Generate invoice number
            $invoiceNumber = $metadata['invoice_number'] ?? ($sessionRow['invoice_number'] ?? ('INV-' . strtoupper(uniqid())));
            
            // Get paymongo_id from event/session if available
            $paymongoId = $session['id'] ?? ($data['data']['id'] ?? null);
            
            // Use NOW() which now uses Asia/Manila timezone
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, course_id, amount, payment_status, payment_date, paymongo_id, invoice_number, payment_type) VALUES (?, ?, ?, 'completed', NOW(), ?, ?, ?)");
            $stmt->execute([$userId, $courseId, $amount, $paymongoId, $invoiceNumber, $paymentType]);
            
            webhook_log("Inserted payment record for user_id=$userId, course_id=$courseId, amount=$amount, type=$paymentType, invoice=$invoiceNumber, paymongo_id=$paymongoId");
        } else {
            webhook_log("Payment record already exists for user_id=$userId, course_id=$courseId, amount=$amount, type=$paymentType");
        }
    } else {
        webhook_log("ERROR: Amount is zero or less for paid session_id=$checkoutSessionId, user_id=$userId, course_id=$courseId");
    }

    // 7. Enroll user if not already enrolled
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?");
    $stmt->execute([$courseId, $userId]);
    $alreadyEnrolled = $stmt->fetch();
    
    if ($alreadyEnrolled) {
        webhook_log("User $userId already enrolled in course $courseId");
    } else {
        $stmt = $pdo->prepare("INSERT INTO enrollments (course_id, user_id, enrolled_at) VALUES (?, ?, NOW())");
        $stmt->execute([$courseId, $userId]);
        webhook_log("Inserted enrollment for user_id=$userId, course_id=$courseId");
        
        // Initialize course_progress
        $stmt = $pdo->prepare("INSERT INTO course_progress (course_id, student_id, completed_sections, completion_percentage, completion_status) VALUES (?, ?, 0, 0, 'not_started')");
        $stmt->execute([$courseId, $userId]);
        webhook_log("Initialized course_progress for user_id=$userId, course_id=$courseId");
        
        // Initialize section progress
        $stmt = $pdo->prepare("INSERT INTO progress (student_id, section_id, completed) SELECT ?, s.id, 0 FROM sections s WHERE s.course_id = ?");
        $stmt->execute([$userId, $courseId]);
        webhook_log("Initialized section progress for user_id=$userId, course_id=$courseId");
    }
    
    http_response_code(200);
    exit('OK');
} catch (Exception $e) {
    webhook_log('DB error: ' . $e->getMessage());
    http_response_code(500);
    exit('DB error');
}
