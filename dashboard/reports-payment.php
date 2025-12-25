<?php
require_once __DIR__ . '/../vendor/autoload.php';
/**
 * Professional Payment Invoice Generator for AIToManabi Learning Platform
 * 
 * This class provides a unified interface for generating payment invoices in PDF format:
 * - Professional PDF styling with company branding
 * - Detailed payment information
 * - Philippine timezone support
 * - Multiple payment types (PAID/FREE)
 * - Comprehensive payment details
 * 
 * Features:
 * - Flexible data input from payment records
 * - Professional PDF styling with company branding
 * - Multiple payment types support
 * - Admin information privacy controls
 * - Summary metrics and statistics
 * - Error handling and fallback options
 * 
 * @author AIToManabi Development Team
 * @version 1.0
 * @since 2025-09-04
 */

class PaymentInvoiceGenerator {
    private $pdo;
    private $defaultConfig;
    
    public function __construct($database) {
        $this->pdo = $database;
        
        // Default configuration
        $this->defaultConfig = [
            'company_name' => 'AiToManabi',
            'company_email' => 'aitomanabilms@gmail.com',
            'company_website' => 'www.aitomanabi.com',
            'company_address' => 'Philippines',
            'report_version' => '1.0',
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'currency_symbol' => '₱'
        ];
    }
    
    /**
     * Generate payment invoice in PDF format
     * 
     * @param int $paymentId Payment ID to generate invoice for
     * @param int $userId User ID requesting the invoice
     * @param array $config Configuration options
     */
    public function generatePaymentInvoice($paymentId, $userId, $config = []) {
        // Check if autoload exists
        $autoloadPath = '../vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            // Try alternative path
            $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
        }
        
        if (!file_exists($autoloadPath)) {
            die("Error: mPDF library not found. Please run 'composer install' to install dependencies.");
        }
        
        require_once $autoloadPath;
        
        try {
            $config = array_merge($this->defaultConfig, $config);
            
            // Set Philippine timezone
            date_default_timezone_set($config['timezone']);
            
            // Get payment data
            $paymentData = $this->getPaymentData($paymentId, $userId);
            
            if (!$paymentData) {
                die("Error: Payment not found or access denied.");
            }
            
            // Generate filename
            $filename = 'invoice_' . $paymentData['invoice_number'] . '_' . date('Y-m-d') . '.pdf';
            
            // Create PDF instance with proper margins for header/footer (like admin format)
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
            $mpdf->SetTitle('Payment Invoice - ' . $paymentData['invoice_number']);
            $mpdf->SetAuthor($config['company_name']);
            $mpdf->SetSubject('Payment Invoice');
            $mpdf->SetKeywords('invoice, payment, ' . strtolower($config['company_name']));
            
            // Create HTML content
            $html = $this->generateInvoiceHTML($config, $paymentData);
            
            // Set up proper header for every page
            $header = $this->generateInvoiceHeader($config, $paymentData);
            $mpdf->SetHTMLHeader($header);
            
            // Set up proper footer for every page
            $footer = $this->generateInvoiceFooter($config, $paymentData);
            $mpdf->SetHTMLFooter($footer);
            
            // Write HTML to PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF
            $mpdf->Output($filename, 'D');
            
        } catch (Exception $e) {
            error_log("Payment Invoice Error: " . $e->getMessage());
            error_log("Payment Invoice Stack Trace: " . $e->getTraceAsString());
            
            // More detailed error message
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>Payment Invoice Generation Error</h2>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
            echo '<p>Please check the error log for more details or contact the administrator.</p>';
            echo '<script>console.error("Payment Invoice Error: ' . addslashes($e->getMessage()) . '");</script>';
        }
    }
    
    // Private helper methods
    
    private function getPaymentData($paymentId, $userId) {
        try {
            // Comprehensive payment query with all related data
            $sql = "SELECT 
                        p.*,
                        u.username,
                        u.email,
                        u.first_name,
                        u.last_name,
                        c.title as course_title,
                        c.description as course_description,
                        c.level as course_level,
                        pd.course_name as payment_course_name
                    FROM payments p
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN courses c ON p.course_id = c.id
                    LEFT JOIN payment_details pd ON p.id = pd.payment_id
                    WHERE p.id = ? AND p.user_id = ?
                    LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$paymentId, $userId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment) {
                // Ensure we have proper course name
                if (empty($payment['course_title']) && !empty($payment['payment_course_name'])) {
                    $payment['course_title'] = $payment['payment_course_name'];
                }
                
                // Generate transaction ID if not present
                if (empty($payment['paymongo_id'])) {
                    $payment['paymongo_id'] = 'TXN-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT);
                }
                
                return $payment;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Payment data error: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateInvoiceHTML($config, $paymentData) {
        // Get current timestamp in Philippine timezone
        $currentTime = new DateTime('now', new DateTimeZone($config['timezone']));
        $formattedTime = $currentTime->format('F d, Y g:i A T');
        
        // Format payment date
        $paymentDate = new DateTime($paymentData['payment_date'], new DateTimeZone($config['timezone']));
        $formattedPaymentDate = $paymentDate->format('F d, Y');
        
        // Use stored invoice number if available to keep consistency across systems
        $invoiceId = $paymentData['invoice_number'] ?? ('INV-' . date('Ymd') . '-' . str_pad($paymentData['id'], 4, '0', STR_PAD_LEFT));
        
        // Status styling
        $statusColor = match($paymentData['payment_status']) {
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
                border-radius: 6px;
                padding: 20px;
                margin: 15px 0;
                font-size: 9pt;
            }
            
            .payment-info-vertical {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .payment-info-row {
                display: flex;
                flex-direction: column;
                gap: 4px;
                padding: 8px 0;
                border-bottom: 1px solid #d1d5db;
            }
            
            .payment-info-row:last-child {
                border-bottom: none;
            }
            
            .payment-label {
                font-weight: bold;
                color: #4b5563;
                font-size: 8pt;
                margin-bottom: 2px;
            }
            
            .payment-value {
                color: #374151;
                font-size: 9pt;
                word-break: break-all;
                line-height: 1.4;
            }
        </style>
        
        <!-- Metadata Section -->
        <div class="metadata-section">
            <table class="metadata-table">
                <tr>
                    <td class="metadata-label">Invoice #:</td>
                    <td>' . htmlspecialchars($invoiceId) . '</td>
                    <td class="metadata-label">Date:</td>
                    <td>' . $paymentDate->format('M j, Y') . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Student:</td>
                    <td>' . htmlspecialchars($paymentData['username']) . '</td>
                    <td class="metadata-label">Status:</td>
                    <td><span class="status-' . $paymentData['payment_status'] . '">' . ($paymentData['payment_status'] === 'completed' ? ((strtoupper($paymentData['payment_type']) === 'FREE') ? 'FREE' : 'PAID') : ucfirst($paymentData['payment_status'])) . '</span></td>
                </tr>
            </table>
        </div>
        
        <!-- Executive Summary -->
        <div class="executive-summary">
            <div class="summary-title">PAYMENT SUMMARY</div>
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="metric-value">PHP ' . number_format($paymentData['amount'], 2) . '</div>
                        <div class="metric-label">Total Amount</div>
                    </td>
                    <td>
                        <div class="metric-value">' . $paymentDate->format('M j') . '</div>
                        <div class="metric-label">Payment Date</div>
                    </td>
                    <td>
                        <div class="metric-value">' . htmlspecialchars($paymentData['payment_method'] ?? 'Online') . '</div>
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
                    <td class="text-left">' . htmlspecialchars($paymentData['course_title'] ?? 'Course') . '</td>
                    <td class="text-left">' . htmlspecialchars($paymentData['email']) . '</td>
                    <td class="text-right">PHP ' . number_format($paymentData['amount'], 2) . '</td>
                    <td class="status-' . $paymentData['payment_status'] . '">' . ($paymentData['payment_status'] === 'completed' ? ((strtoupper($paymentData['payment_type']) === 'FREE') ? 'FREE' : 'PAID') : ucfirst($paymentData['payment_status'])) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div class="payment-info">
            <h4 style="margin: 0 0 16px 0; font-size: 10pt; color: #374151; font-weight: bold;">Payment Information</h4>
            <div class="payment-info-vertical">
                <div class="payment-info-row">
                    <div class="payment-label">Transaction ID:</div>
                    <div class="payment-value">' . htmlspecialchars($paymentData['paymongo_id'] ?? 'N/A') . '</div>
                </div>
                <div class="payment-info-row">
                    <div class="payment-label">Payment Date:</div>
                    <div class="payment-value">' . $paymentDate->format('M j, Y g:i A T') . '</div>
                </div>
                <div class="payment-info-row">
                    <div class="payment-label">Invoice Number:</div>
                    <div class="payment-value">' . htmlspecialchars($paymentData['invoice_number'] ?? $invoiceId) . '</div>
                </div>
                <div class="payment-info-row">
                    <div class="payment-label">Generated:</div>
                    <div class="payment-value">' . date('M j, Y g:i A T') . '</div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    private function generateInvoiceHeader($config, $paymentData) {
        // Use stored invoice number if available
        $invoiceId = $paymentData['invoice_number'] ?? ('INV-' . date('Ymd') . '-' . str_pad($paymentData['id'], 4, '0', STR_PAD_LEFT));
        
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
                        <div style="font-size: 8pt; font-weight: bold; margin-bottom: 1px; color: #333;">INVOICE</div>
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
    
    private function generateInvoiceFooter($config, $paymentData) {
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
    
    private function getPaymentMethod($paymentData) {
        // Determine payment method based on available data
        if (strtoupper($paymentData['payment_type']) === 'FREE') {
            return 'Free Course';
        }
        
        // Check if we have paymongo_id (indicates online payment)
        if (!empty($paymentData['paymongo_id'])) {
            // Try to determine the method from paymongo_id format
            if (stripos($paymentData['paymongo_id'], 'gcash') !== false) {
                return 'GCash';
            } elseif (stripos($paymentData['paymongo_id'], 'grabpay') !== false) {
                return 'GrabPay';
            } elseif (stripos($paymentData['paymongo_id'], 'card') !== false) {
                return 'Credit/Debit Card';
            } else {
                return 'Online Payment';
            }
        }
        
        return 'Payment Gateway';
    }
}

// Handle direct access for invoice generation
if (isset($_GET['payment_id']) && isset($_GET['user_id'])) {
    session_start();
    
    // Security check - ensure user is requesting their own invoice
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $_GET['user_id']) {
        http_response_code(403);
        exit('Unauthorized access');
    }
    
    // Check if user is student or admin
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['student', 'admin'])) {
        http_response_code(403);
        exit('Access denied');
    }
    
    require_once '../config/database.php';
    
    $paymentId = intval($_GET['payment_id']);
    $userId = intval($_GET['user_id']);
    
    if ($paymentId <= 0 || $userId <= 0) {
        exit('Invalid payment ID or user ID');
    }
    
    $invoiceGenerator = new PaymentInvoiceGenerator($pdo);
    $invoiceGenerator->generatePaymentInvoice($paymentId, $userId);
    exit;
}
?>
