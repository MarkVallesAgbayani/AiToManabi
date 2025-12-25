<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    $sql = "SELECT 
                p.id,
                p.amount,
                p.status,
                p.payment_date,
                p.paymongo_id,
                p.invoice_number,
                p.payment_type,
                c.title as course_title
            FROM payments p
            LEFT JOIN courses c ON p.course_id = c.id
            WHERE p.user_id = ?
            ORDER BY p.payment_date DESC
            LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
$formattedPayments = array_map(function($payment) {
    // Determine if it's a free course
    $isFree = (strtoupper($payment['payment_type']) === 'FREE' || floatval($payment['amount']) == 0);
    
    return [
        'id' => $payment['id'],
        'date' => date('M j, Y', strtotime($payment['payment_date'])),
        'amount' => number_format($payment['amount'], 2),
        'method' => $isFree ? 'Free' : ucfirst($payment['payment_type'] ?? 'Paid'),
        'status' => $payment['status'],
        'transactionId' => $payment['paymongo_id'] ?? $payment['invoice_number'] ?? 'N/A',
        'course' => $payment['course_title'] ?? 'Unknown Course'
    ];
}, $payments);

    
    echo json_encode([
        'success' => true,
        'payments' => $formattedPayments
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching payment history: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
