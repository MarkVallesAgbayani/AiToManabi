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

// Check if payment ID is provided
if (!isset($_GET['payment_id'])) {
    die('Payment ID is required');
}

$payment_id = $_GET['payment_id'];

// Set timezone to Philippines for timestamps
date_default_timezone_set('Asia/Manila');

// Get payment details
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.username as student_name,
        u.email as student_email,
        c.title as course_title
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN courses c ON p.course_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    // Log failed invoice generation attempt
    $auditLogger = new AuditLogger($pdo);
    $auditLogger->logEntry([
        'action_type' => 'DOWNLOAD',
        'action_description' => 'Attempted to download invoice for non-existent payment',
        'resource_type' => 'Invoice',
        'resource_id' => "Payment ID: $payment_id",
        'resource_name' => 'Invoice Download',
        'outcome' => 'Failed',
        'context' => [
            'payment_id' => $payment_id,
            'reason' => 'Payment not found'
        ]
    ]);
    die('Payment not found');
}

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
        'company_name' => 'AiToManabi',
        'company_email' => 'aitomanabilms@gmail.com',
        'company_website' => 'www.aitomanabi.com',
        'report_version' => '1.0'
    ];
    
    // Create PDF instance with proper margins for header/footer (like reports.php)
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 30,
        'margin_bottom' => 25,
        'margin_header' => 9,
        'margin_footer' => 9
    ]);
    
    // Set document metadata
    $mpdf->SetTitle('Invoice #' . $payment_id . ' - ' . $config['company_name']);
    $mpdf->SetAuthor($config['company_name']);
    $mpdf->SetSubject('Payment Invoice');
    $mpdf->SetKeywords('invoice, payment, ' . strtolower($config['company_name']));
    
    // Generate invoice ID
    $invoiceId = 'INV-' . date('Ymd') . '-' . str_pad($payment_id, 4, '0', STR_PAD_LEFT);
    
    // Payment date in PH timezone
    $payment_date = new DateTime($payment['payment_date']);
    $payment_date->setTimezone(new DateTimeZone('Asia/Manila'));
    
    // Create HTML content
    $html = generateInvoiceHTML($config, $payment, $invoiceId, $payment_date);
    
    // Set up proper header for every page (like reports.php)
    $header = generateInvoiceHeader($config, 'INVOICE', $invoiceId);
    $mpdf->SetHTMLHeader($header);
    
    // Set up proper footer for every page (like reports.php)
    $footer = generateInvoiceFooter($config);
    $mpdf->SetHTMLFooter($footer);
    
    // Write HTML to PDF
    $mpdf->WriteHTML($html);
    
    // Log successful invoice generation
    $auditLogger = new AuditLogger($pdo);
    $auditLogger->logEntry([
        'action_type' => 'DOWNLOAD',
        'action_description' => 'Downloaded invoice for payment',
        'resource_type' => 'Invoice',
        'resource_id' => "Payment ID: $payment_id",
        'resource_name' => 'Invoice Download',
        'outcome' => 'Success',
        'context' => [
            'payment_id' => $payment_id,
            'student_name' => $payment['student_name'],
            'course_title' => $payment['course_title'],
            'amount' => $payment['amount'],
            'payment_status' => $payment['payment_status'],
            'filename' => 'invoice-' . $payment_id . '_' . date('Y-m-d') . '.pdf'
        ]
    ]);
    
    // Output PDF
    $filename = 'invoice-' . $payment_id . '_' . date('Y-m-d') . '.pdf';
    $mpdf->Output($filename, 'D');
    
} catch (Exception $e) {
    error_log("Invoice Generation Error: " . $e->getMessage());
    
    // Log failed invoice generation
    $auditLogger = new AuditLogger($pdo);
    $auditLogger->logEntry([
        'action_type' => 'DOWNLOAD',
        'action_description' => 'Failed to generate invoice due to system error',
        'resource_type' => 'Invoice',
        'resource_id' => "Payment ID: $payment_id",
        'resource_name' => 'Invoice Download',
        'outcome' => 'Failed',
        'context' => [
            'payment_id' => $payment_id,
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]
    ]);
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>Invoice Generation Error</h2>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Please check the error log for more details or contact the administrator.</p>';
}

function generateInvoiceHTML($config, $payment, $invoiceId, $payment_date) {
    // Status styling
    $statusColor = match($payment['payment_status']) {
        'completed' => '#059669',
        'pending' => '#d97706', 
        'failed' => '#dc2626',
        'refunded' => '#6b7280',
        default => '#6b7280'
    };
    
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
            padding: 8px;
            margin-bottom: 12px;
            font-size: 8pt;
        }
        
        .metadata-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .metadata-table td {
            padding: 3px 6px;
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
            padding: 6px;
            margin-bottom: 12px;
            text-align: center;
        }
        
        .summary-title {
            font-size: 8pt;
            font-weight: bold;
            color: #475569;
            margin-bottom: 6px;
            text-align: center;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .summary-table td {
            width: 33.33%;
            text-align: center;
            padding: 4px 3px;
            border-right: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        
        .summary-table td:last-child {
            border-right: none;
        }
        
        .metric-value {
            font-size: 10pt;
            font-weight: bold;
            color: #0369a1;
            display: block;
            margin-bottom: 2px;
        }
        
        .metric-label {
            font-size: 6pt;
            color: #64748b;
            display: block;
            font-weight: normal;
            line-height: 1.2;
        }
        
        .data-section-title {
            color: #374151;
            margin: 10px 0 6px 0;
            font-size: 10pt;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 3px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        
        .data-table th {
            background: linear-gradient(135deg, #374151, #4b5563);
            color: white;
            padding: 4px 3px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #6b7280;
            font-size: 7pt;
        }
        
        .data-table td {
            padding: 3px;
            text-align: center;
            border: 1px solid #d1d5db;
            font-size: 7pt;
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
        
        .payment-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
            margin: 10px 0;
            font-size: 7pt;
        }
        
        .payment-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payment-info-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        
        .payment-label {
            font-weight: bold;
            color: #4b5563;
            width: 25%;
        }
    </style>
    
    <!-- Metadata Section -->
    <div class="metadata-section">
        <table class="metadata-table">
            <tr>
                <td class="metadata-label">Invoice #:</td>
                <td>' . htmlspecialchars($invoiceId) . '</td>
                <td class="metadata-label">Date:</td>
                <td>' . $payment_date->format('M j, Y') . '</td>
            </tr>
            <tr>
                <td class="metadata-label">Student:</td>
                <td>' . htmlspecialchars($payment['student_name']) . '</td>
                <td class="metadata-label">Status:</td>
                <td><span class="status-' . $payment['payment_status'] . '">' . ucfirst($payment['payment_status']) . '</span></td>
            </tr>
        </table>
    </div>
    
    <!-- Executive Summary -->
    <div class="executive-summary">
        <div class="summary-title">PAYMENT SUMMARY</div>
        <table class="summary-table">
            <tr>
                <td>
                    <div class="metric-value">PHP ' . number_format($payment['amount'], 2) . '</div>
                    <div class="metric-label">Total Amount</div>
                </td>
                <td>
                    <div class="metric-value">' . $payment_date->format('M j') . '</div>
                    <div class="metric-label">Payment Date</div>
                </td>
                <td>
                    <div class="metric-value">' . htmlspecialchars($payment['payment_method'] ?? 'Online') . '</div>
                    <div class="metric-label">Payment Method</div>
                </td>
            </tr>
        </table>
    </div>
    
    <h3 class="data-section-title">INVOICE DETAILS</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Course</th>
                <th>Student Email</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-left">' . htmlspecialchars($payment['course_title']) . '</td>
                <td class="text-left">' . htmlspecialchars($payment['student_email']) . '</td>
                <td class="text-right">PHP ' . number_format($payment['amount'], 2) . '</td>
                <td class="status-' . $payment['payment_status'] . '">' . ucfirst($payment['payment_status']) . '</td>
            </tr>
        </tbody>
    </table>
    
    <div class="payment-info">
        <h4 style="margin: 0 0 6px 0; font-size: 8pt; color: #374151;">Payment Information</h4>
        <table class="payment-info-table">
            <tr>
                <td class="payment-label">Transaction ID:</td>
                <td>' . htmlspecialchars($payment['transaction_id'] ?? 'N/A') . '</td>
                <td class="payment-label">Payment Date:</td>
                <td>' . $payment_date->format('M j, Y g:i A T') . '</td>
            </tr>
            <tr>
                <td class="payment-label">Invoice Number:</td>
                <td>' . htmlspecialchars($payment['invoice_number'] ?? $invoiceId) . '</td>
                <td class="payment-label">Generated:</td>
                <td>' . date('M j, Y g:i A T') . '</td>
            </tr>
        </table>
    </div>';
    
    return $html;
}

function generateInvoiceHeader($config, $reportTitle, $invoiceId) {
    return '
    <div style="width: 100%; color: #333; padding: 6px 0; border-bottom: 2px solid #0369a1;">
        <table style="width: 100%; border-collapse: collapse; margin: 0;">
            <tr>
                <td style="width: 33.33%; vertical-align: middle; text-align: left;">
                    <div style="display: inline-block; vertical-align: middle;">
                        <img src="data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../assets/images/logo.png')) . '" alt="AiToManabi Logo" style="width: 40px; height: 40px; vertical-align: middle;" />
                    </div>
                </td>
                <td style="width: 33.33%; text-align: center; vertical-align: middle;">
                    <div style="font-size: 8pt; font-weight: bold; margin-bottom: 1px; color: #333;">' . strtoupper($reportTitle) . '</div>
                    <div style="font-size: 6pt; color: #666;">ID: ' . htmlspecialchars($invoiceId) . '</div>
                </td>
                <td style="width: 33.33%; text-align: right; vertical-align: middle;">
                    <div style="font-size: 9pt; font-weight: bold; margin-bottom: 1px; color: #333;">AiToManabi</div>
                    <div style="font-size: 6pt; color: #666;">Learning Management System</div>
                </td>
            </tr>
        </table>
    </div>';
}

function generateInvoiceFooter($config) {
    // Generate current timestamp for footer (Philippine timezone)
    $readableTime = date('M j, Y g:i A T');
    
    return '
    <div style="width: 100%; font-size: 5pt; color: #374151; border-top: 1px solid #e5e7eb; padding: 4px 0; background: #f8fafc;">
        <table style="width: 100%; border-collapse: collapse; margin: 0;">
            <tr>
                <td style="width: 40%; vertical-align: top;">
                    <div style="font-weight: bold; color: #1e40af; margin-bottom: 1px; font-size: 6pt;">' . htmlspecialchars($config['company_name']) . '</div>
                    <div style="line-height: 1.2; color: #4b5563; font-size: 5pt;">
                        ' . htmlspecialchars($config['company_email']) . ' | ' . htmlspecialchars($config['company_website']) . '
                    </div>
                </td>
                <td style="width: 20%; text-align: center; vertical-align: middle;">
                    <div style="font-size: 5pt; color: #6b7280;">Generated by ' . htmlspecialchars($config['company_name']) . '</div>
                </td>
                <td style="width: 40%; text-align: right; vertical-align: top;">
                    <div style="font-weight: bold; color: #1e40af; margin-bottom: 1px; font-size: 6pt;">Document Info</div>
                    <div style="line-height: 1.2; color: #4b5563; font-size: 5pt;">
                        Generated: ' . $readableTime . ' | Page {PAGENO} of {nbpg}
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: center; padding-top: 2px; border-top: 1px solid #d1d5db; margin-top: 2px;">
                    <div style="font-size: 4pt; color: #dc2626; font-weight: bold; line-height: 1.1;">
                        ⚠ CONFIDENTIAL: For authorized use only. Unauthorized access, copying, or distribution is strictly prohibited.
                    </div>
                </td>
            </tr>
        </table>
    </div>';
}
?>
