<?php
// download_invoice.php using FPDF
require_once '../../config/database.php';

// FPDF library (no composer needed)
class PDF extends FPDF {
    function Header() {
        // Logo
        $logoPath = __DIR__ . '/../assets/images/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 8, 22);
        }
        // AiToManabi Title
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(239, 68, 68);
        $this->Cell(0, 15, 'AiToManabi', 0, 1, 'C');
        $this->Ln(2);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(170, 170, 170);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit('Unauthorized');
}

$paymentId = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
if (!$paymentId) {
    exit('Invalid payment ID');
}

// Fetch payment info
$stmt = $pdo->prepare("SELECT p.*, c.title as course_title, u.username, u.email FROM payments p JOIN courses c ON p.course_id = c.id JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.user_id = ?");
$stmt->execute([$paymentId, $_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    exit('Payment not found');
}

require_once(__DIR__ . '/fpdf/fpdf.php');
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(239, 68, 68);
$pdf->Cell(0, 12, 'Payment Invoice', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(24, 24, 27);
$pdf->Cell(50, 10, 'Invoice #:', 0, 0);
$pdf->Cell(0, 10, $payment['invoice_number'], 0, 1);
$pdf->Cell(50, 10, 'Date:', 0, 0);
$pdf->Cell(0, 10, date('M d, Y', strtotime($payment['payment_date'])), 0, 1);
$pdf->Cell(50, 10, 'Student:', 0, 0);
$pdf->Cell(0, 10, $payment['username'].' ('.$payment['email'].')', 0, 1);
$pdf->Cell(50, 10, 'Course:', 0, 0);
$pdf->Cell(0, 10, $payment['course_title'], 0, 1);
$pdf->Cell(50, 10, 'Amount:', 0, 0);
$pdf->SetTextColor(239, 68, 68);
$pdf->Cell(0, 10, '₱'.number_format($payment['amount'],2), 0, 1);
$pdf->SetTextColor(24, 24, 27);
$pdf->Cell(50, 10, 'Payment Type:', 0, 0);
$pdf->Cell(0, 10, $payment['payment_type'], 0, 1);
$pdf->Cell(50, 10, 'Bank/Method:', 0, 0);
$pdf->Cell(0, 10, ($payment['paymongo_id'] ? strtoupper($payment['paymongo_id']) : 'N/A'), 0, 1);
$pdf->Cell(50, 10, 'Status:', 0, 0);
$pdf->SetTextColor($payment['payment_status']==='completed'?22:245, $payment['payment_status']==='completed'?163:158, $payment['payment_status']==='completed'?74:66);
$pdf->Cell(0, 10, ucfirst($payment['payment_status']), 0, 1);
$pdf->SetTextColor(24, 24, 27);
$pdf->Ln(8);
$pdf->SetFont('Arial', 'I', 12);
$pdf->SetTextColor(239, 68, 68);
$pdf->MultiCell(0, 10, 'Thank you for your payment and for learning with AiToManabi!');

$pdf->Output('I', 'invoice_'.$payment['invoice_number'].'.pdf');
