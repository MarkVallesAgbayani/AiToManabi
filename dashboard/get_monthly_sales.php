<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once '../config/database.php';

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // Get monthly sales for the specified year
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(payment_date) as month,
            SUM(amount) as total
        FROM payments
        WHERE YEAR(payment_date) = ?
        GROUP BY MONTH(payment_date)
        ORDER BY month
    ");
    $stmt->execute([$year]);
    $monthlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get previous year's total for growth rate calculation
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total
        FROM payments
        WHERE YEAR(payment_date) = ?
    ");
    $stmt->execute([$year - 1]);
    $previousYearTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Initialize response data
    $response = [
        'monthly' => [],
        'total' => 0,
        'highest' => 0,
        'highestMonth' => '',
        'average' => 0,
        'growthRate' => 0,
        'projected' => 0
    ];

    // Process monthly data
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $totalSales = 0;
    $monthCount = 0;
    $highest = 0;
    $highestMonth = '';

    foreach ($monthlySales as $sale) {
        $monthIndex = $sale['month'] - 1;
        $amount = floatval($sale['total']);
        $response['monthly'][$months[$monthIndex]] = $amount;
        
        $totalSales += $amount;
        $monthCount++;

        if ($amount > $highest) {
            $highest = $amount;
            $highestMonth = $months[$monthIndex];
        }
    }

    // Calculate statistics
    $response['total'] = $totalSales;
    $response['highest'] = $highest;
    $response['highestMonth'] = $highestMonth;
    $response['average'] = $monthCount > 0 ? $totalSales / $monthCount : 0;
    
    // Calculate growth rate
    if ($previousYearTotal > 0) {
        $response['growthRate'] = round((($totalSales - $previousYearTotal) / $previousYearTotal) * 100, 1);
    }

    // Calculate projected sales (simple moving average)
    if ($monthCount >= 3) {
        $lastThreeMonths = array_slice(array_values($response['monthly']), -3);
        $response['projected'] = array_sum($lastThreeMonths) / 3;
    } else {
        $response['projected'] = $response['average'];
    }

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} 