<?php
session_start();
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'audit_logger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if user has permission to view payment history or is admin
if (!hasPermission($pdo, $_SESSION['user_id'], 'payment_view_history') && $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Set timezone to Philippines for timestamps
date_default_timezone_set('Asia/Manila');

// Get all payment history (no pagination for export)
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.username as student_name,
        u.email as student_email,
        c.title as course_title
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN courses c ON p.course_id = c.id
    ORDER BY p.payment_date DESC
");

try {
    $stmt->execute();
    $payment_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Payment query error: " . $e->getMessage());
    $payment_history = [];
}

// Calculate total revenue
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) as total_revenue 
    FROM payments 
    WHERE payment_status = 'completed'
");
$total_revenue = $stmt->fetchColumn();

// Calculate summary statistics
$completed_payments = array_filter($payment_history, function($p) { return $p['payment_status'] === 'completed'; });
$pending_payments = array_filter($payment_history, function($p) { return $p['payment_status'] === 'pending'; });
$failed_payments = array_filter($payment_history, function($p) { return $p['payment_status'] === 'failed'; });
$refunded_payments = array_filter($payment_history, function($p) { return $p['payment_status'] === 'refunded'; });

$summaryStats = [
    'total_revenue' => $total_revenue,
    'total_payments' => count($payment_history),
    'completed_payments' => count($completed_payments),
    'pending_payments' => count($pending_payments),
    'failed_payments' => count($failed_payments),
    'refunded_payments' => count($refunded_payments),
    'success_rate' => count($payment_history) > 0 ? round((count($completed_payments) / count($payment_history)) * 100, 1) : 0
];

// Fetch admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Check if autoload exists
$autoloadPath = '../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
}

if (!file_exists($autoloadPath)) {
    die("Error: mPDF library not found. Please run 'composer install' to install dependencies.");
}

require_once $autoloadPath;

try {
    // Configuration
    $config = [
        'export_columns' => ['date', 'student', 'course', 'amount', 'status', 'invoice'],
        'data_source' => 'payments',
        'export_detail' => 'full',
        'report_purpose' => 'payment_history',
        'confidentiality' => 'internal',
        'summary_metrics' => ['totals', 'averages', 'success_rate'],
        'data_grouping' => 'chronological',
        'admin_info_level' => 'name_only',
        'company_name' => 'AiToManabi',
        'company_email' => 'aitomanabilms@gmail.com',
        'company_website' => 'www.aitomanabi.com',
        'report_version' => '1.0'
    ];
    
    if (empty($payment_history)) {
        error_log("PDF Export Error: No payment data found.");
        die("Error: No payment data available for PDF export. Please check your database and try again.");
    }
    
    // Create PDF instance with proper margins for header/footer
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L', // Landscape for better table display
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 30,
        'margin_bottom' => 25,
        'margin_header' => 9,
        'margin_footer' => 9
    ]);
    
    // Set document metadata
    $mpdf->SetTitle('Payment History Report - ' . $config['company_name']);
    $mpdf->SetAuthor($config['company_name']);
    $mpdf->SetSubject('Payment History Report');
    $mpdf->SetKeywords('report, payment, history, analytics, ' . strtolower($config['company_name']));
    
    // Generate report ID
    $reportId = 'RPT-PH-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Admin info
    $adminInfo = htmlspecialchars($admin['username'] ?? 'System Administrator');
    
    // Create HTML content
    $html = generatePaymentHistoryHTML($config, $adminInfo, $reportId, $summaryStats, $payment_history);
    
    // Set up proper header for every page
    $header = generatePaymentHistoryHeader($config, 'PAYMENT HISTORY REPORT', $reportId);
    $mpdf->SetHTMLHeader($header);
    
    // Set up proper footer for every page
    $footer = generatePaymentHistoryFooter($config);
    $mpdf->SetHTMLFooter($footer);
    
    // Write HTML to PDF
    $mpdf->WriteHTML($html);
    
    // Log successful PDF export
    $auditLogger = new AuditLogger($pdo);
    $auditLogger->logEntry([
        'action_type' => 'EXPORT',
        'action_description' => 'Exported payment history to PDF',
        'resource_type' => 'Payment History Report',
        'resource_id' => "Report ID: $reportId",
        'resource_name' => 'Payment History PDF Export',
        'outcome' => 'Success',
        'context' => [
            'report_id' => $reportId,
            'total_payments' => count($payment_history),
            'total_revenue' => $total_revenue,
            'success_rate' => $summaryStats['success_rate'],
            'filename' => 'payment_history_' . date('Y-m-d_H-i-s') . '.pdf',
            'export_type' => 'PDF',
            'data_period' => 'All Time'
        ]
    ]);
    
    // Output PDF
    $filename = 'payment_history_' . date('Y-m-d_H-i-s') . '.pdf';
    $mpdf->Output($filename, 'D');
    
} catch (Exception $e) {
    error_log("PDF Export Error: " . $e->getMessage());
    error_log("PDF Export Stack Trace: " . $e->getTraceAsString());
    
    // Log failed PDF export
    $auditLogger = new AuditLogger($pdo);
    $auditLogger->logEntry([
        'action_type' => 'EXPORT',
        'action_description' => 'Failed to export payment history to PDF due to system error',
        'resource_type' => 'Payment History Report',
        'resource_id' => "Report ID: " . ($reportId ?? 'Unknown'),
        'resource_name' => 'Payment History PDF Export',
        'outcome' => 'Failed',
        'context' => [
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'data_count' => count($payment_history),
            'export_type' => 'PDF'
        ]
    ]);
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>PDF Generation Error</h2>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
    echo '<p><strong>Data Count:</strong> ' . count($payment_history) . ' records</p>';
    echo '<p>Please check the error log for more details or contact the administrator.</p>';
}

function generatePaymentHistoryHTML($config, $adminInfo, $reportId, $summaryStats, $payment_history) {
    $html = '
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #333;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        
        .metadata-section {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 8pt;
        }
        
        .metadata-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .metadata-table td {
            padding: 4px 8px;
            vertical-align: top;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .metadata-label {
            font-weight: bold;
            color: #374151;
            width: 20%;
        }
        
        .executive-summary {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border: 1px solid #94a3b8;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .summary-title {
            font-size: 9pt;
            font-weight: bold;
            color: #475569;
            margin-bottom: 8px;
            text-align: center;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .summary-table td {
            width: 25%;
            text-align: center;
            padding: 6px 4px;
            border-right: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        
        .summary-table td:last-child {
            border-right: none;
        }
        
        .metric-value {
            font-size: 12pt;
            font-weight: bold;
            color: #0369a1;
            display: block;
            margin-bottom: 2px;
        }
        
        .metric-label {
            font-size: 7pt;
            color: #64748b;
            display: block;
            font-weight: normal;
            line-height: 1.2;
        }
        
        .data-section-title {
            color: #374151;
            margin: 15px 0 8px 0;
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 8pt;
        }
        
        .data-table th {
            background: linear-gradient(135deg, #374151, #4b5563);
            color: white;
            padding: 6px 4px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #6b7280;
            font-size: 8pt;
        }
        
        .data-table td {
            padding: 4px;
            text-align: center;
            border: 1px solid #d1d5db;
            font-size: 8pt;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .data-table tr:nth-child(odd) {
            background-color: white;
        }
        
        .status-completed { color: #059669; font-weight: bold; }
        .status-pending { color: #d97706; font-weight: bold; }
        .status-failed { color: #dc2626; font-weight: bold; }
        .status-refunded { color: #6b7280; font-weight: bold; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
    </style>
    
    <!-- Metadata Section -->
    <div class="metadata-section">
        <table class="metadata-table">
            <tr>
                <td class="metadata-label">Generated By:</td>
                <td>' . $adminInfo . '</td>
                <td class="metadata-label">Report Period:</td>
                <td>All Time</td>
            </tr>
            <tr>
                <td class="metadata-label">Report ID:</td>
                <td>' . htmlspecialchars($reportId) . '</td>
                <td class="metadata-label">Confidentiality:</td>
                <td style="color: #dc2626; font-weight: bold;">INTERNAL USE ONLY</td>
            </tr>
        </table>
    </div>
    
    <!-- Executive Summary -->
    <div class="executive-summary">
        <div class="summary-title">EXECUTIVE SUMMARY</div>
        <table class="summary-table">
            <tr>
                <td>
                    <div class="metric-value">PHP ' . number_format($summaryStats['total_revenue'], 2) . '</div>
                    <div class="metric-label">Total Revenue</div>
                </td>
                <td>
                    <div class="metric-value">' . number_format($summaryStats['total_payments']) . '</div>
                    <div class="metric-label">Total Payments</div>
                </td>
                <td>
                    <div class="metric-value">' . number_format($summaryStats['completed_payments']) . '</div>
                    <div class="metric-label">Completed</div>
                </td>
                <td>
                    <div class="metric-value">' . number_format($summaryStats['success_rate'], 1) . '%</div>
                    <div class="metric-label">Success Rate</div>
                </td>
            </tr>
        </table>
    </div>
    
    <h3 class="data-section-title">PAYMENT HISTORY DATA</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Course</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Invoice</th>
            </tr>
        </thead>
        <tbody>';
    
    // Generate table rows
    foreach ($payment_history as $payment) {
        // Payment date in PH timezone
        $payment_date = new DateTime($payment['payment_date']);
        $payment_date->setTimezone(new DateTimeZone('Asia/Manila'));
        
        // Status class
        $statusClass = 'status-' . $payment['payment_status'];
        
        $html .= '<tr>';
        $html .= '<td>' . $payment_date->format('M j, Y') . '</td>';
        $html .= '<td class="text-left">' . htmlspecialchars($payment['student_name']) . '<br><small>' . htmlspecialchars($payment['student_email']) . '</small></td>';
        $html .= '<td class="text-left">' . htmlspecialchars($payment['course_title']) . '</td>';
        $html .= '<td class="text-right">PHP ' . number_format($payment['amount'], 2) . '</td>';
        $html .= '<td class="' . $statusClass . '">' . ucfirst($payment['payment_status']) . '</td>';
        $html .= '<td>' . htmlspecialchars($payment['invoice_number'] ?? 'N/A') . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '
        </tbody>
    </table>';
    
    return $html;
}

function generatePaymentHistoryHeader($config, $reportTitle, $reportId) {
    return '
    <div style="width: 100%; color: #333; padding: 8px 0; border-bottom: 2px solid #0369a1;">
        <table style="width: 100%; border-collapse: collapse; margin: 0;">
            <tr>
                <td style="width: 33.33%; vertical-align: middle; text-align: left;">
                    <div style="display: inline-block; vertical-align: middle;">
                        <img src="data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../assets/images/logo.png')) . '" alt="AiToManabi Logo" style="width: 50px; height: 50px; vertical-align: middle;" />
                    </div>
                </td>
                <td style="width: 33.33%; text-align: center; vertical-align: middle;">
                    <div style="font-size: 9pt; font-weight: bold; margin-bottom: 1px; color: #333;">' . strtoupper($reportTitle) . '</div>
                    <div style="font-size: 7pt; color: #666;">Report ID: ' . htmlspecialchars($reportId) . ' | Page {PAGENO} of {nbpg}</div>
                </td>
                <td style="width: 33.33%; text-align: right; vertical-align: middle;">
                    <div style="font-size: 10pt; font-weight: bold; margin-bottom: 1px; color: #333;">AiToManabi</div>
                    <div style="font-size: 7pt; color: #666;">Learning Management System</div>
                </td>
            </tr>
        </table>
    </div>';
}

function generatePaymentHistoryFooter($config) {
    // Generate current timestamp for footer (Philippine timezone)
    $currentTimestamp = date('Y-m-d H:i:s T');
    $readableTime = date('M j, Y g:i A T');
    
    return '
    <div style="width: 100%; font-size: 6pt; color: #374151; border-top: 1px solid #e5e7eb; padding: 6px 0; background: #f8fafc;">
        <table style="width: 100%; border-collapse: collapse; margin: 0;">
            <tr>
                <td style="width: 40%; vertical-align: top;">
                    <div style="font-weight: bold; color: #1e40af; margin-bottom: 2px; font-size: 7pt;">' . htmlspecialchars($config['company_name']) . '</div>
                    <div style="line-height: 1.3; color: #4b5563; font-size: 6pt;">
                        ' . htmlspecialchars($config['company_email']) . ' | ' . htmlspecialchars($config['company_website']) . '<br>
                    </div>
                </td>
                <td style="width: 20%; text-align: center; vertical-align: middle;">
                    <div style="font-size: 6pt; color: #6b7280;">Generated by ' . htmlspecialchars($config['company_name']) . ' Report System</div>
                </td>
                <td style="width: 40%; text-align: right; vertical-align: top;">
                    <div style="font-weight: bold; color: #1e40af; margin-bottom: 2px; font-size: 7pt;">Document Info</div>
                    <div style="line-height: 1.3; color: #4b5563; font-size: 6pt;">
                        Report v' . htmlspecialchars($config['report_version']) . ' | Generated: ' . $readableTime . '<br>
                        Status: INTERNAL USE | Page {PAGENO} of {nbpg}
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: center; padding-top: 4px; border-top: 1px solid #d1d5db; margin-top: 4px;">
                    <div style="font-size: 5pt; color: #dc2626; font-weight: bold; line-height: 1.2;">
                        ⚠ CONFIDENTIAL: For authorized use only. Unauthorized access, copying, or distribution is strictly prohibited.
                    </div>
                </td>
            </tr>
        </table>
    </div>';
}
?>
