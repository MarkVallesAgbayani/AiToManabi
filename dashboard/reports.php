<?php
require_once __DIR__ . '/../vendor/autoload.php';
/**
 * Centralized Report Generator for AIToManabi Learning Platform
 * 
 * This class provides a unified interface for generating reports in PDF format:
 * - PDF (Professional PDF reports with branding)
 * 
 * Features:
 * - Flexible data input from any source
 * - Configurable column selection
 * - Professional PDF styling with company branding
 * - Multiple confidentiality levels
 * - Admin information privacy controls
 * - Summary metrics and statistics
 * - Error handling and fallback options
 * 
 * @author AIToManabi Development Team
 * @version 1.0
 * @since 2025-09-04
 */

class ReportGenerator {
    private $pdo;
    private $defaultConfig;
    
    public function __construct($database) {
        $this->pdo = $database;
        
        // Default configuration
        $this->defaultConfig = [
            'export_columns' => ['date', 'total_active', 'students', 'teachers', 'admins'],
            'data_source' => 'auto',
            'export_detail' => 'summary',
            'report_purpose' => 'general',
            'confidentiality' => 'internal',
            'summary_metrics' => ['totals', 'averages'],
            'data_grouping' => 'chronological',
            'admin_info_level' => 'name_only',
            'company_name' => 'AiToManabi',
            'company_email' => 'aitomanabilms@gmail.com',
            'company_website' => 'www.aitomanabi.com',
            'report_version' => '1.0'
        ];
    }
    
    
    /**
     * Export data to PDF format with professional styling
     * 
     * @param array $data The data to export
     * @param array $config Configuration options
     * @param array $summaryStats Optional summary statistics
     * @param string $reportTitle Custom report title
     * @param string $filename Optional custom filename
     */
    public function exportToPDF($data, $config = [], $summaryStats = [], $reportTitle = 'REPORT', $filename = null) {
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
            
            if (!$filename) {
                $filename = $this->generateFilename('pdf', $config);
            }
            
            // Check if we have data
            if (empty($data)) {
                error_log("PDF Export Error: No data found for user role report. Filters applied: " . json_encode($config));
                die("Error: No data available for PDF export. This could be due to: 1) No users in database, 2) Date range too restrictive, 3) Role/status filters excluding all users. Please check your filters and try again.");
            }
            
            // Get admin info for the report
            $admin = $this->getAdminInfo();
            
            // Create PDF instance with proper margins for header/footer
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
            $mpdf->SetTitle($reportTitle . ' - ' . $config['company_name']);
            $mpdf->SetAuthor($config['company_name']);
            $mpdf->SetSubject($reportTitle);
            $mpdf->SetKeywords('report, analytics, ' . strtolower($config['company_name']));
            
            // Generate report ID
            $reportId = $this->generateReportId($reportTitle);
            
            // Prepare filter summary
            $filterText = $this->generateFilterSummary($config);
            
            // Calculate summary metrics if not provided
            if (empty($summaryStats)) {
                $summaryStats = $this->calculateSummaryStats($data, $config);
            }
            
            // Create HTML content
            $html = $this->generatePDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle);
            
            // Set up proper header for every page
            $header = $this->generatePDFHeader($config, $reportTitle, $reportId);
            $mpdf->SetHTMLHeader($header);
            
            // Set up proper footer for every page
            $footer = $this->generatePDFFooter($config);
            $mpdf->SetHTMLFooter($footer);
            
            // Write HTML to PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF
            $mpdf->Output($filename, 'D');
            
        } catch (Exception $e) {
            error_log("PDF Export Error: " . $e->getMessage());
            error_log("PDF Export Stack Trace: " . $e->getTraceAsString());
            
            // More detailed error message
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>PDF Generation Error</h2>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
            echo '<p><strong>Data Count:</strong> ' . count($data) . ' records</p>';
            echo '<p><strong>Config:</strong> ' . htmlspecialchars(json_encode($config)) . '</p>';
            echo '<p>Please check the error log for more details or contact the administrator.</p>';
            echo '<script>console.error("PDF Error: ' . addslashes($e->getMessage()) . '");</script>';
        }
    }
    
    /**
     * Generate report based on usage analytics data
     * 
     * @param array $filters Database filters
     * @param string $format Export format (pdf only)
     * @param array $config Additional configuration
     */
    public function generateUsageAnalyticsReport($filters, $format, $config = []) {
        // Get data using the same functions from usage-analytics.php
        $data = $this->getDetailedActivityData($filters, 0, 10000);
        $summaryStats = $this->getDashboardStats($filters);
        
        $config = array_merge($this->defaultConfig, $filters, $config);
        
        if (strtolower($format) === 'pdf') {
            $this->exportToPDF($data, $config, $summaryStats, 'USAGE ANALYTICS REPORT');
        } else {
            throw new InvalidArgumentException("Only PDF format is supported");
        }
    }
    
    /**
     * Generate report based on user role data
     * 
     * @param array $filters Database filters
     * @param string $format Export format (pdf only)
     * @param array $config Additional configuration
     */
    public function generateUserRoleReport($filters, $format, $config = []) {
        // Get data using the same functions from user-role-report.php
        $data = $this->getUsersReportData($filters, 0, 10000);
        $summaryStats = $this->getUserRoleSummary($filters);
        
        $config = array_merge($this->defaultConfig, $filters, $config);
        
        if (strtolower($format) === 'pdf') {
            $this->exportUserRoleToPDF($data, $config, $summaryStats, 'USER ROLES REPORT');
        } else {
            throw new InvalidArgumentException("Only PDF format is supported");
        }
    }
    
    /**
     * Generate report based on quiz performance data
     * 
     * @param array $filters Database filters
     * @param string $format Export format (pdf only)
     * @param array $config Additional configuration
     */
    public function generateQuizPerformanceReport($filters, $format, $config = []) {
        // Get data using quiz performance functions
        $data = $this->getQuizPerformanceData($filters, 0, 10000);
        $summaryStats = $this->getQuizPerformanceSummary($filters);
        
        $config = array_merge($this->defaultConfig, $filters, $config);
        
        if (strtolower($format) === 'pdf') {
            $this->exportQuizPerformanceToPDF($data, $config, $summaryStats, 'QUIZ PERFORMANCE REPORT');
        } else {
            throw new InvalidArgumentException("Only PDF format is supported");
        }
    }

    /**
     * Generate report for Login Activity or Broken Links
     * @param array $filters
     * @param string $type 'login' or 'broken_links'
     * @param string $format
     * @param array $config
     */
    public function generateLoginActivityReport($filters, $type, $format, $config = []) {
        $config = array_merge($this->defaultConfig, $filters, $config);
        if (strtolower($format) !== 'pdf') {
            throw new InvalidArgumentException("Only PDF format is supported");
        }
        if ($type === 'login') {
            $data = $this->getLoginActivityData($filters, 0, 10000);
            $summary = $this->getLoginSummary($filters);
            $this->exportLoginOrLinksToPDF($data, $config, $summary, 'LOGIN ACTIVITY REPORT', 'login');
        } else if ($type === 'broken_links') {
            $data = $this->getBrokenLinksData($filters, 0, 10000);
            $summary = $this->getBrokenLinksSummary($filters);
            $this->exportLoginOrLinksToPDF($data, $config, $summary, 'BROKEN LINKS REPORT', 'broken_links');
        } else {
            throw new InvalidArgumentException('Unknown login activity report type');
        }
    }

    /**
     * Generate Audit Trails report
     */
    public function generateAuditTrailsReport($filters, $format, $config = []) {
        $config = array_merge($this->defaultConfig, $filters, $config);
        if (strtolower($format) !== 'pdf') {
            throw new InvalidArgumentException("Only PDF format is supported");
        }
        $data = $this->getAuditTrailsData($filters, 0, 10000);
        $summary = $this->getAuditTrailsSummary($filters);
        $this->exportAuditTrailsToPDF($data, $config, $summary, 'AUDIT TRAILS REPORT');
    }

    /**
     * Generate Progress Tracking report
     */
    public function generateProgressTrackingReport($data, $summaryStats, $filters, $config = []) {
        $config = array_merge($this->defaultConfig, $filters, $config);
        $this->exportProgressTrackingToPDF($data, $config, $summaryStats, 'PROGRESS TRACKING REPORT');
    }
    
    /**
     * Generate Engagement Monitoring report
     */
    public function generateEngagementMonitoringReport($data, $summaryStats, $filters, $config = []) {
        $config = array_merge($this->defaultConfig, $filters, $config);
        $this->exportEngagementMonitoringToPDF($data, $config, $summaryStats, 'ENGAGEMENT MONITORING REPORT');
    }
    
    /**
     * Generate Completion Reports
     */
    public function generateCompletionReportsReport($data, $summaryStats, $filters, $config = []) {
        $config = array_merge($this->defaultConfig, $filters, $config);
        $this->exportCompletionReportsToPDF($data, $config, $summaryStats, 'COMPLETION REPORTS');
    }
    
    // Private helper methods
    
    private function generateFilename($extension, $config) {
        $reportType = $config['report_purpose'] ?? 'report';
        return strtolower($reportType) . '_' . date('Y-m-d_H-i-s') . '.' . $extension;
    }
    
    private function generateReportId($reportTitle) {
        $prefix = 'RPT-' . strtoupper(substr(str_replace(' ', '', $reportTitle), 0, 2));
        return $prefix . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
    
    
    private function getAdminInfo() {
        if (!isset($_SESSION['user_id'])) {
            return ['username' => 'System', 'role' => 'admin'];
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT username, role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['username' => 'System', 'role' => 'admin'];
        } catch (PDOException $e) {
            return ['username' => 'System', 'role' => 'admin'];
        }
    }
    
    private function generateFilterSummary($config) {
        $filterSummary = [];
        
        if (!empty($config['date_from']) && !empty($config['date_to'])) {
            $filterSummary[] = "Date Range: " . $config['date_from'] . " to " . $config['date_to'];
        }
        if (!empty($config['role_filter'])) {
            $filterSummary[] = "Role: " . ucfirst($config['role_filter']);
        }
        if (!empty($config['status_filter'])) {
            $filterSummary[] = "Status: " . ucfirst($config['status_filter']);
        }
        if (!empty($config['search'])) {
            $filterSummary[] = "Search: '" . $config['search'] . "'";
        }
        if (!empty($config['view_type'])) {
            $filterSummary[] = "View: " . ucfirst($config['view_type']);
        }
        
        return !empty($filterSummary) ? implode(', ', $filterSummary) : 'No filters applied';
    }
    
    private function calculateSummaryStats($data, $config) {
        $totalActive = array_sum(array_column($data, 'total_active'));
        $averageUsers = count($data) > 0 ? round($totalActive / count($data), 1) : 0;
        
        $peakDay = '';
        $peakCount = 0;
        foreach ($data as $row) {
            if ($row['total_active'] > $peakCount) {
                $peakCount = $row['total_active'];
                $peakDay = $row['activity_date'];
            }
        }
        // Calculate growth rate using available helper (falls back internally if needed)
        $growthRate = 0;
        try {
            $growthRate = $this->calculateGrowthRate($config, $totalActive);
        } catch (Exception $e) {
            // If growth calculation fails, leave as 0 and log
            error_log('calculateSummaryStats growth rate error: ' . $e->getMessage());
            $growthRate = 0;
        }

        return [
            'total_active' => (int)$totalActive,
            'daily_average' => $averageUsers,
            'peak_date' => $peakDay ?: 'N/A',
            'peak_count' => (int)$peakCount,
            'growth_rate' => $growthRate
        ];
    }
    
    private function generatePDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle) {
        // Get confidentiality level
        $confidentiality = $config['confidentiality'] ?? 'internal';
        $confidentialityText = [
            'internal' => 'INTERNAL USE ONLY',
            'confidential' => 'CONFIDENTIAL',
            'restricted' => 'RESTRICTED ACCESS',
            'public' => 'PUBLIC REPORT'
        ][$confidentiality];
        
        // Admin info level
        $adminInfoLevel = $config['admin_info_level'] ?? 'name_only';
        $adminInfo = '';
        switch ($adminInfoLevel) {
            case 'name_only':
                $adminInfo = htmlspecialchars($admin['username']);
                break;
            case 'name_role':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ')';
                break;
            case 'full_details':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ') - ' . $config['company_email'];
                break;
            case 'anonymous':
                $adminInfo = 'System Administrator';
                break;
        }
        
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
                width: 33.33%;
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
            
            .totals-row {
                background: linear-gradient(135deg, #fef3c7, #fde68a) !important;
                font-weight: bold;
                border-top: 2px solid #f59e0b !important;
            }
            
            .totals-row td {
                border-top: 2px solid #f59e0b !important;
                font-weight: bold;
            }
            
            .disclaimer {
                background-color: #fef2f2;
                border: 1px solid #fca5a5;
                padding: 8px;
                margin: 15px 0;
                border-radius: 4px;
                font-size: 7pt;
            }
            
            .text-center { text-align: center; }

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

        </style>
        
        <!-- Metadata Section -->
        <div class="metadata-section">
            <table class="metadata-table">
                <tr>
                    <td class="metadata-label">Generated By:</td>
                    <td>' . $adminInfo . '</td>
                    <td class="metadata-label">Period:</td>
                    <td>' . (!empty($config['date_from']) && !empty($config['date_to']) ? 
                        date('M j', strtotime($config['date_from'])) . ' - ' . date('M j, Y', strtotime($config['date_to'])) : 
                        'All Time') . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Filters Applied:</td>
                    <td>' . htmlspecialchars($filterText) . '</td>
                    <td class="metadata-label">Confidentiality:</td>
                    <td style="color: #dc2626; font-weight: bold;">' . $confidentialityText . '</td>
                </tr>
            </table>
        </div>
        
<div class="executive-summary">
    <div class="summary-title">EXECUTIVE SUMMARY</div>
    <table class="summary-table">
        <tr>
            <td>
                <div class="metric-value">' . number_format($summaryStats['total_active'] ?? 0) . '</div>
                <div class="metric-label">Total Active Users</div>
            </td>
            <td>
                <div class="metric-value">' . number_format($summaryStats['daily_average'] ?? 0, 1) . '</div>
                <div class="metric-label">Daily Average</div>
            </td>
            <td>
                <div class="metric-value">' . number_format($summaryStats['peak_count'] ?? 0) . '</div>
                <div class="metric-label">Peak Activity Count</div>
            </td>
            <td>
                <div class="metric-value">' . (($summaryStats['growth_rate'] ?? 0) >= 0 ? '+' : '') . ($summaryStats['growth_rate'] ?? 0) . '%</div>
                <div class="metric-label">Growth Rate</div>
            </td>
        </tr>
    </table>
</div>';
        
        // Generate table headers
        $selectedColumns = $config['export_columns'] ?? ['date', 'total_active', 'students', 'teachers', 'admins'];
        $tableHeaders = '';
        foreach ($selectedColumns as $column) {
            switch ($column) {
                case 'date':
                case 'activity_date':
                    $tableHeaders .= '<th>Date</th>';
                    break;
                case 'total_active':
                    $tableHeaders .= '<th>Total Active</th>';
                    break;
                case 'students':
                case 'unique_students':
                    $tableHeaders .= '<th>Students</th>';
                    break;
                case 'teachers':
                case 'unique_teachers':
                    $tableHeaders .= '<th>Teachers</th>';
                    break;
                case 'admins':
                case 'unique_admins':
                    $tableHeaders .= '<th>Admins</th>';
                    break;
                case 'growth_rate':
                    $tableHeaders .= '<th>Growth %</th>';
                    break;
            }
        }
        
        $html .= '
        <h3 class="data-section-title">DETAILED ACTIVITY DATA</h3>
        <table class="data-table">
            <thead>
                <tr>' . $tableHeaders . '</tr>
            </thead>
            <tbody>';
        
        // Generate table rows and calculate totals
        $totalActive = 0;
        $totalStudents = 0;
        $totalTeachers = 0;
        $totalAdmins = 0;
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($selectedColumns as $column) {
                switch ($column) {
                    case 'date':
                    case 'activity_date':
                        $date = $row['activity_date'] ?? $row['date'] ?? '';
                        $html .= '<td>' . ($date ? date('M j, Y', strtotime($date)) : 'N/A') . '</td>';
                        break;
                    case 'total_active':
                        $value = $row['total_active'] ?? 0;
                        $html .= '<td>' . number_format($value) . '</td>';
                        $totalActive += $value;
                        break;
                    case 'students':
                    case 'unique_students':
                        $value = $row['unique_students'] ?? $row['students'] ?? 0;
                        $html .= '<td>' . number_format($value) . '</td>';
                        $totalStudents += $value;
                        break;
                    case 'teachers':
                    case 'unique_teachers':
                        $value = $row['unique_teachers'] ?? $row['teachers'] ?? 0;
                        $html .= '<td>' . number_format($value) . '</td>';
                        $totalTeachers += $value;
                        break;
                    case 'admins':
                    case 'unique_admins':
                        $value = $row['unique_admins'] ?? $row['admins'] ?? 0;
                        $html .= '<td>' . number_format($value) . '</td>';
                        $totalAdmins += $value;
                        break;
                    case 'growth_rate':
                        $html .= '<td>-</td>';
                        break;
                }
            }
            $html .= '</tr>';
        }
        
        // Add totals row
        $html .= '<tr class="totals-row">';
        foreach ($selectedColumns as $column) {
            switch ($column) {
                case 'date':
                case 'activity_date':
                    $html .= '<td><strong>TOTALS</strong></td>';
                    break;
                case 'total_active':
                    $html .= '<td><strong>' . number_format($totalActive) . '</strong></td>';
                    break;
                case 'students':
                case 'unique_students':
                    $html .= '<td><strong>' . number_format($totalStudents) . '</strong></td>';
                    break;
                case 'teachers':
                case 'unique_teachers':
                    $html .= '<td><strong>' . number_format($totalTeachers) . '</strong></td>';
                    break;
                case 'admins':
                case 'unique_admins':
                    $html .= '<td><strong>' . number_format($totalAdmins) . '</strong></td>';
                    break;
                case 'growth_rate':
                    $html .= '<td><strong>-</strong></td>';
                    break;
            }
        }
        $html .= '</tr>';
        
        $html .= '
            </tbody>
        </table>';
        
        return $html;
    }
    
    private function generatePDFFooter($config) {
        // Generate current timestamp for footer
        $currentTimestamp = date('Y-m-d H:i:s T');
        $readableTime = date('M j, Y g:i A T');
        
        // Get confidentiality level for disclaimer
        $confidentiality = $config['confidentiality'] ?? 'internal';
        $showDisclaimer = $confidentiality !== 'public';
        
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
                            Status: ' . (($config['confidentiality'] ?? 'internal') === 'internal' ? 'INTERNAL USE' : strtoupper($config['confidentiality'])) . ' | Page {PAGENO} of {nbpg}
                        </div>
                    </td>
                </tr>' . 
                ($showDisclaimer ? '
                <tr>
                    <td colspan="3" style="text-align: center; padding-top: 4px; border-top: 1px solid #d1d5db; margin-top: 4px;">
                        <div style="font-size: 5pt; color: #dc2626; font-weight: bold; line-height: 1.2;">
                            ⚠ CONFIDENTIAL: For authorized use only. Unauthorized access, copying, or distribution is strictly prohibited.
                        </div>
                    </td>
                </tr>' : '') . '
            </table>
        </div>';
    }
    
    private function generatePDFHeader($config, $reportTitle, $reportId) {
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
    
    // Usage Analytics specific methods (copied from usage-analytics.php)
    
    private function getDetailedActivityData($filters, $offset, $limit) {
        try {
            // Try user_activity_log first
            $data = $this->getDetailedActivityFromActivityLog($filters, $offset, $limit);
            
            // If no data, try fallback sources
            if (empty($data)) {
                $data = $this->getDetailedActivityFromFallback($filters, $offset, $limit);
            }
            
            return $data;
        } catch (PDOException $e) {
            error_log("Detailed activity data error: " . $e->getMessage());
            return $this->getDetailedActivityFromFallback($filters, $offset, $limit);
        }
    }
    
    private function getDetailedActivityFromActivityLog($filters, $offset, $limit) {
        $whereClause = "WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
        $params = [$filters['date_from'], $filters['date_to']];
        
        if (!empty($filters['role_filter'])) {
            $whereClause .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        if (!empty($filters['search'])) {
            $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        
        $groupBy = '';
        $selectDate = '';
        
        switch ($filters['view_type']) {
            case 'daily':
                $selectDate = "DATE(ual.created_at) as activity_date";
                $groupBy = "GROUP BY DATE(ual.created_at)";
                break;
            case 'weekly':
                // Use Sunday–Saturday week grouping (mode 0) to match UI/export
                $selectDate = "CONCAT(YEAR(ual.created_at), '-W', LPAD(WEEK(ual.created_at, 0), 2, '0')) as activity_date";
                $groupBy = "GROUP BY YEAR(ual.created_at), WEEK(ual.created_at, 0)";
                break;
            case 'monthly':
                $selectDate = "DATE_FORMAT(ual.created_at, '%Y-%m') as activity_date";
                $groupBy = "GROUP BY DATE_FORMAT(ual.created_at, '%Y-%m')";
                break;
            case 'yearly':
                $selectDate = "YEAR(ual.created_at) as activity_date";
                $groupBy = "GROUP BY YEAR(ual.created_at)";
                break;
        }
        
        $sql = "SELECT 
                    $selectDate,
                    COUNT(DISTINCT ual.user_id) as total_active,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' THEN ual.user_id END) as unique_students,
                    COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN ual.user_id END) as unique_teachers,
                    COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN ual.user_id END) as unique_admins
                FROM user_activity_log ual
                JOIN users u ON ual.user_id = u.id
                $whereClause
                $groupBy
                ORDER BY activity_date DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getDetailedActivityFromFallback($filters, $offset, $limit) {
        // Fallback to login_logs
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['role_filter'])) {
            $whereClause .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        if (!empty($filters['search'])) {
            $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        
        // Add date filters for login_logs
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(ll.login_time) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(ll.login_time) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $groupBy = '';
        $selectDate = '';
        
        switch ($filters['view_type']) {
            case 'daily':
                $selectDate = "DATE(ll.login_time) as activity_date";
                $groupBy = "GROUP BY DATE(ll.login_time)";
                break;
            case 'weekly':
                // Use Sunday–Saturday week grouping (mode 0) to match UI/export
                $selectDate = "CONCAT(YEAR(ll.login_time), '-W', LPAD(WEEK(ll.login_time, 0), 2, '0')) as activity_date";
                $groupBy = "GROUP BY YEAR(ll.login_time), WEEK(ll.login_time, 0)";
                break;
            case 'monthly':
                $selectDate = "DATE_FORMAT(ll.login_time, '%Y-%m') as activity_date";
                $groupBy = "GROUP BY DATE_FORMAT(ll.login_time, '%Y-%m')";
                break;
            case 'yearly':
                $selectDate = "YEAR(ll.login_time) as activity_date";
                $groupBy = "GROUP BY YEAR(ll.login_time)";
                break;
        }
        
        $sql = "SELECT 
                    $selectDate,
                    COUNT(DISTINCT ll.user_id) as total_active,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' THEN ll.user_id END) as unique_students,
                    COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN ll.user_id END) as unique_teachers,
                    COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN ll.user_id END) as unique_admins
                FROM login_logs ll
                JOIN users u ON ll.user_id = u.id
                $whereClause
                $groupBy
                ORDER BY activity_date DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getDashboardStats($filters) {
        try {
            $stats = [];
            
            // Try to get stats from user_activity_log first
            $stats = $this->getDashboardStatsFromActivityLog($filters);
            
            // If no data, try fallback sources
            if ($stats['total_active'] == 0) {
                $stats = $this->getDashboardStatsFromFallback($filters);
            }
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
            return $this->getDashboardStatsFromFallback($filters);
        }
    }
    
    private function getDashboardStatsFromActivityLog($filters) {
        $stats = [];
        
        // Total active users in selected period
        $sql = "SELECT COUNT(DISTINCT ual.user_id) as total_active
                FROM user_activity_log ual
                JOIN users u ON ual.user_id = u.id
                WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
        
        $params = [$filters['date_from'], $filters['date_to']];
        if (!empty($filters['role_filter'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['total_active'] = $stmt->fetchColumn();
        
        // Daily average
        $days = max(1, (strtotime($filters['date_to']) - strtotime($filters['date_from'])) / (24 * 60 * 60));
        $stats['daily_average'] = round($stats['total_active'] / $days, 1);
        
        // Peak active day
        $sql = "SELECT DATE(ual.created_at) as peak_date, COUNT(DISTINCT ual.user_id) as peak_count
                FROM user_activity_log ual
                JOIN users u ON ual.user_id = u.id
                WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
        
        if (!empty($filters['role_filter'])) {
            $sql .= " AND u.role = ?";
        }
        
        $sql .= " GROUP BY DATE(ual.created_at)
                  ORDER BY peak_count DESC
                  LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $peak = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['peak_date'] = $peak['peak_date'] ?? 'N/A';
        $stats['peak_count'] = $peak['peak_count'] ?? 0;
        
        // Growth rate calculation based on view_type
        $stats['growth_rate'] = $this->calculateGrowthRate($filters, $stats['total_active']);
        
        return $stats;
    }

    private function calculateGrowthRate($filters, $current_active) {
        try {
            $view_type = $filters['view_type'] ?? 'daily';
            $date_to = $filters['date_to'];
            
            // Calculate previous period based on view_type
            switch ($view_type) {
                case 'daily':
                    $prev_start = date('Y-m-d', strtotime($date_to . ' -1 day'));
                    $prev_end = $prev_start;
                    break;
                case 'weekly':
                    // Previous week
                    $prev_start = date('Y-m-d', strtotime($date_to . ' -1 week'));
                    $prev_end = date('Y-m-d', strtotime($prev_start . ' +6 days'));
                    break;
                case 'monthly':
                    // Previous month
                    $prev_start = date('Y-m-d', strtotime($date_to . ' -1 month'));
                    $prev_end = date('Y-m-t', strtotime($prev_start)); // Last day of previous month
                    break;
                case 'yearly':
                    // Previous year
                    $prev_start = date('Y-m-d', strtotime($date_to . ' -1 year'));
                    $prev_end = date('Y-12-31', strtotime($prev_start)); // Last day of previous year
                    break;
                default:
                    // For custom ranges, use equivalent previous period
                    $period_length = (strtotime($filters['date_to']) - strtotime($filters['date_from']));
                    $prev_start = date('Y-m-d', strtotime($filters['date_from']) - $period_length);
                    $prev_end = date('Y-m-d', strtotime($filters['date_from']) - 1);
            }
            
            // Get previous period active users
            $sql = "SELECT COUNT(DISTINCT ual.user_id) as prev_active
                    FROM user_activity_log ual
                    JOIN users u ON ual.user_id = u.id
                    WHERE ual.created_at >= ? AND ual.created_at <= DATE_ADD(?, INTERVAL 1 DAY)";
            
            $prev_params = [$prev_start, $prev_end];
            if (!empty($filters['role_filter'])) {
                $sql .= " AND u.role = ?";
                $prev_params[] = $filters['role_filter'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($prev_params);
            $prev_active = $stmt->fetchColumn();
            
            // Calculate growth rate
            if ($prev_active > 0) {
                $growth_rate = round((($current_active - $prev_active) / $prev_active) * 100, 1);
                return min($growth_rate, 100); // Cap at 100%
            } else {
                return $current_active > 0 ? 100 : 0;
            }
            
        } catch (PDOException $e) {
            error_log("Growth rate calculation error: " . $e->getMessage());
            return 0;
        }
    }
    
    private function getDashboardStatsFromFallback($filters) {
        $stats = [];
        
        // Fallback to login_logs
        $sql = "SELECT COUNT(DISTINCT ll.user_id) as total_active
                FROM login_logs ll
                JOIN users u ON ll.user_id = u.id
                WHERE DATE(ll.login_time) >= ? AND DATE(ll.login_time) <= ?";
        
        $params = [$filters['date_from'], $filters['date_to']];
        if (!empty($filters['role_filter'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['total_active'] = $stmt->fetchColumn();
        
        // Calculate other stats
        $days = max(1, (strtotime($filters['date_to']) - strtotime($filters['date_from'])) / (24 * 60 * 60));
        $stats['daily_average'] = round($stats['total_active'] / $days, 1);
        $stats['peak_date'] = 'N/A';
        $stats['peak_count'] = 0;
        $stats['growth_rate'] = $this->calculateGrowthRateFromFallback($filters, $stats['total_active']);
        
        return $stats;
    }

    private function calculateGrowthRateFromFallback($filters, $current_active) {
        try {
            $view_type = $filters['view_type'] ?? 'daily';
            $date_to = $filters['date_to'];
            
            // Calculate previous period based on view_type
            switch ($view_type) {
                case 'daily':
                    $prev_start = date('Y-m-d', strtotime($date_to . ' -1 day'));
                    $prev_end = $prev_start;
                    break;
                case 'weekly':
                    $prev_start = date('Y-m-d', strtotime($date_to . ' -1 week'));
                    $prev_end = date('Y-m-d', strtotime($prev_start . ' +6 days'));
                    break;
                case 'monthly':
                    $prev_start = date('Y-m-d', strtotime($date_to . ' -1 month'));
                    $prev_end = date('Y-m-t', strtotime($prev_start));
                    break;
                case 'yearly':
                    $prev_start = date('Y-m-d', strtotime($date_to . ' -1 year'));
                    $prev_end = date('Y-12-31', strtotime($prev_start));
                    break;
                default:
                    $period_length = (strtotime($filters['date_to']) - strtotime($filters['date_from']));
                    $prev_start = date('Y-m-d', strtotime($filters['date_from']) - $period_length);
                    $prev_end = date('Y-m-d', strtotime($filters['date_from']) - 1);
            }
            
            // Try comprehensive_audit_trail first, then fallback to login_logs
            $sql = "SELECT COUNT(DISTINCT ll.user_id) as prev_active
                    FROM login_logs ll
                    JOIN users u ON ll.user_id = u.id
                    WHERE DATE(ll.login_time) >= ? AND DATE(ll.login_time) <= ?";
            
            $params = [$prev_start, $prev_end];
            if (!empty($filters['role_filter'])) {
                $sql .= " AND u.role = ?";
                $params[] = $filters['role_filter'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $prev_active = $stmt->fetchColumn();
            
            // Calculate growth rate
            if ($prev_active > 0) {
                $growth_rate = round((($current_active - $prev_active) / $prev_active) * 100, 1);
                return min($growth_rate, 100); // Cap at 100%
            } else {
                return $current_active > 0 ? 100 : 0;
            }
            
        } catch (PDOException $e) {
            error_log("Fallback growth rate calculation error: " . $e->getMessage());
            return 0;
        }
    }
    
    // User Role Report specific methods
    
    private function getUsersReportData($filters, $offset, $limit) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];
            
            // Search filter
            if (!empty($filters['search'])) {
                $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Role filter
            if (!empty($filters['role_filter'])) {
                $whereClause .= " AND u.role = ?";
                $params[] = $filters['role_filter'];
            }
            
            // Status filter
            if (!empty($filters['status_filter'])) {
                if ($filters['status_filter'] === 'active') {
                    $whereClause .= " AND (u.status = 'active' OR u.status IS NULL)";
                } elseif ($filters['status_filter'] === 'inactive') {
                    $whereClause .= " AND u.status IN ('inactive', 'suspended', 'banned', 'deleted')";
                }
            }
            
            // Date range filter - Only apply if valid dates
            if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
                $whereClause .= " AND DATE(u.created_at) >= ? AND DATE(u.created_at) <= ?";
                $params[] = $filters['date_from'];
                $params[] = $filters['date_to'];
            } elseif (!empty($filters['date_from'])) {
                $whereClause .= " AND DATE(u.created_at) >= ?";
                $params[] = $filters['date_from'];
            } elseif (!empty($filters['date_to'])) {
                $whereClause .= " AND DATE(u.created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            // Simplified SQL with enrolled courses calculation
            $sql = "SELECT 
                        u.id,
                        u.username,
                        u.email,
                        u.first_name,
                        u.last_name,
                        u.role,
                        COALESCE(u.status, 'active') as status,
                        u.created_at,
                        u.updated_at,
                        CASE 
                            WHEN u.role = 'student' THEN COALESCE(enrolled_count.count, 0)
                            ELSE 0 
                        END as enrolled_courses
                    FROM users u
                    LEFT JOIN (
                        SELECT student_id, COUNT(*) as count 
                        FROM enrollments 
                        GROUP BY student_id
                    ) enrolled_count ON u.id = enrolled_count.student_id AND u.role = 'student'
                    $whereClause
                    ORDER BY u.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no results with filters, try basic query
            if (empty($result) && count($params) > 2) {
                $sql = "SELECT 
                            u.id,
                            u.username,
                            u.email,
                            u.first_name,
                            u.last_name,
                            u.role,
                            COALESCE(u.status, 'active') as status,
                            u.created_at,
                            u.updated_at,
                            0 as enrolled_courses
                        FROM users u
                        ORDER BY u.created_at DESC
                        LIMIT ? OFFSET ?";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$limit, $offset]);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Users report error: " . $e->getMessage());
            // Ultimate fallback
            try {
                $sql = "SELECT id, username, email, first_name, last_name, role, 'active' as status, created_at, created_at as updated_at, 0 as enrolled_courses FROM users LIMIT ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$limit]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                return [];
            }
        }
    }
    
    private function getUserRoleSummary($filters) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters['search'])) {
                $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($filters['role_filter'])) {
                $whereClause .= " AND u.role = ?";
                $params[] = $filters['role_filter'];
            }
            
            if (!empty($filters['status_filter'])) {
                if ($filters['status_filter'] === 'active') {
                    $whereClause .= " AND (u.status = 'active' OR u.status IS NULL)";
                } elseif ($filters['status_filter'] === 'inactive') {
                    $whereClause .= " AND u.status IN ('inactive', 'suspended', 'banned', 'deleted')";
                }
            }
            
            // Date range filter
            if (!empty($filters['date_from'])) {
                $whereClause .= " AND DATE(u.created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereClause .= " AND DATE(u.created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $sql = "SELECT 
                        u.role,
                        COUNT(*) as total,
                        COUNT(CASE WHEN (u.status = 'active' OR u.status IS NULL) THEN 1 END) as active,
                        COUNT(CASE WHEN u.status IN ('inactive', 'suspended', 'banned', 'deleted') THEN 1 END) as inactive
                    FROM users u
                    $whereClause
                    GROUP BY u.role
                    ORDER BY total DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Role summary error: " . $e->getMessage());
            return [];
        }
    }
    
    // Quiz Performance Report specific methods
    
    private function getQuizPerformanceData($filters, $offset, $limit) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];
            
            // Date range filter
            if (!empty($filters['date_from'])) {
                $whereClause .= " AND DATE(qa.completed_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereClause .= " AND DATE(qa.completed_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            // Student filter
            if (!empty($filters['student_id'])) {
                $whereClause .= " AND qa.student_id = ?";
                $params[] = $filters['student_id'];
            }
            
            // Quiz filter
            if (!empty($filters['quiz_id'])) {
                $whereClause .= " AND qa.quiz_id = ?";
                $params[] = $filters['quiz_id'];
            }
            
            $sql = "SELECT 
                        qa.id,
                        u.username,
                        q.title as quiz_title,
                        c.title as module_name,
                        qa.score,
                        qa.total_points,
                        qa.completed_at,
                        ROUND((qa.score / qa.total_points) * 100, 1) as percentage
                    FROM quiz_attempts qa
                    JOIN users u ON qa.student_id = u.id
                    JOIN quizzes q ON qa.quiz_id = q.id
                    JOIN sections s ON q.section_id = s.id
                    JOIN courses c ON s.course_id = c.id
                    $whereClause
                    ORDER BY qa.completed_at DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Quiz performance data error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getQuizPerformanceSummary($filters) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];
            
            // Date range filter
            if (!empty($filters['date_from'])) {
                $whereClause .= " AND DATE(qa.completed_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereClause .= " AND DATE(qa.completed_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            // Student filter
            if (!empty($filters['student_id'])) {
                $whereClause .= " AND qa.student_id = ?";
                $params[] = $filters['student_id'];
            }
            
            // Quiz filter
            if (!empty($filters['quiz_id'])) {
                $whereClause .= " AND qa.quiz_id = ?";
                $params[] = $filters['quiz_id'];
            }
            
            $sql = "SELECT 
                        COUNT(*) as total_attempts,
                        COUNT(DISTINCT qa.student_id) as active_students,
                        COUNT(DISTINCT qa.quiz_id) as total_quizzes,
                        ROUND(AVG((qa.score / qa.total_points) * 100), 1) as average_score
                    FROM quiz_attempts qa
                    JOIN users u ON qa.student_id = u.id
                    JOIN quizzes q ON qa.quiz_id = q.id
                    $whereClause";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_attempts' => $result['total_attempts'] ?? 0,
                'active_students' => $result['active_students'] ?? 0,
                'total_quizzes' => $result['total_quizzes'] ?? 0,
                'average_score' => $result['average_score'] ?? 0
            ];
        } catch (PDOException $e) {
            error_log("Quiz performance summary error: " . $e->getMessage());
            return [
                'total_attempts' => 0,
                'active_students' => 0,
                'total_quizzes' => 0,
                'average_score' => 0
            ];
        }
    }
    
    private function exportQuizPerformanceToPDF($data, $config, $summaryStats, $reportTitle) {
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
            if (!$data || empty($data)) {
                error_log("PDF Export Error: No quiz performance data found. Filters applied: " . json_encode($config));
                die("Error: No quiz performance data available for PDF export. This could be due to: 1) No quiz attempts in database, 2) Date range too restrictive, 3) Student/Quiz filters excluding all data. Please check your filters and try again.");
            }
            
            // Get admin info for the report
            $admin = $this->getAdminInfo();
            
            // Create PDF instance with proper margins for header/footer
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
            $mpdf->SetTitle($reportTitle . ' - ' . $config['company_name']);
            $mpdf->SetAuthor($config['company_name']);
            $mpdf->SetSubject($reportTitle);
            $mpdf->SetKeywords('report, quiz, performance, analytics, ' . strtolower($config['company_name']));
            
            // Generate report ID
            $reportId = $this->generateReportId($reportTitle);
            
            // Prepare filter summary
            $filterText = $this->generateQuizPerformanceFilterSummary($config);
            
            // Create HTML content
            $html = $this->generateQuizPerformancePDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle);
            
            // Set up proper header for every page
            $header = $this->generatePDFHeader($config, $reportTitle, $reportId);
            $mpdf->SetHTMLHeader($header);
            
            // Set up proper footer for every page
            $footer = $this->generatePDFFooter($config);
            $mpdf->SetHTMLFooter($footer);
            
            // Write HTML to PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF
            $filename = 'quiz_performance_' . date('Y-m-d_H-i-s') . '.pdf';
            $mpdf->Output($filename, 'D');
            
        } catch (Exception $e) {
            error_log("PDF Export Error: " . $e->getMessage());
            error_log("PDF Export Stack Trace: " . $e->getTraceAsString());
            
            // More detailed error message
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>PDF Generation Error</h2>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
            echo '<p><strong>Data Count:</strong> ' . count($data) . ' records</p>';
            echo '<p><strong>Config:</strong> ' . htmlspecialchars(json_encode($config)) . '</p>';
            echo '<p>Please check the error log for more details or contact the administrator.</p>';
        }
    }
    
    private function generateQuizPerformanceFilterSummary($config) {
        $filterSummary = [];
        
        if (!empty($config['date_from']) && !empty($config['date_to'])) {
            $filterSummary[] = "Date Range: " . $config['date_from'] . " to " . $config['date_to'];
        }
        if (!empty($config['student_id'])) {
            $filterSummary[] = "Student ID: " . $config['student_id'];
        }
        if (!empty($config['quiz_id'])) {
            $filterSummary[] = "Quiz ID: " . $config['quiz_id'];
        }
        
        return !empty($filterSummary) ? implode(', ', $filterSummary) : 'No filters applied';
    }
    
    private function generateQuizPerformancePDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle) {
        // Get confidentiality level
        $confidentiality = $config['confidentiality'] ?? 'internal';
        $confidentialityText = [
            'internal' => 'INTERNAL USE ONLY',
            'confidential' => 'CONFIDENTIAL',
            'restricted' => 'RESTRICTED ACCESS',
            'public' => 'PUBLIC REPORT'
        ][$confidentiality];
        
        // Admin info level
        $adminInfoLevel = $config['admin_info_level'] ?? 'name_only';
        $adminInfo = '';
        switch ($adminInfoLevel) {
            case 'name_only':
                $adminInfo = htmlspecialchars($admin['username']);
                break;
            case 'name_role':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ')';
                break;
            case 'full_details':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ') - ' . $config['company_email'];
                break;
            case 'anonymous':
                $adminInfo = 'System Administrator';
                break;
        }
        
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
            
            .score-excellent { color: #059669; font-weight: bold; }
            .score-good { color: #d97706; font-weight: bold; }
            .score-poor { color: #dc2626; font-weight: bold; }
            
            .text-center { text-align: center; }
        </style>
        
        <!-- Metadata Section -->
        <div class="metadata-section">
            <table class="metadata-table">
                <tr>
                    <td class="metadata-label">Generated By:</td>
                    <td>' . $adminInfo . '</td>
                    <td class="metadata-label">Period:</td>
                    <td>' . (!empty($config['date_from']) && !empty($config['date_to']) ? 
                        date('M j', strtotime($config['date_from'])) . ' - ' . date('M j, Y', strtotime($config['date_to'])) : 
                        'All Time') . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Filters Applied:</td>
                    <td>' . htmlspecialchars($filterText) . '</td>
                    <td class="metadata-label">Confidentiality:</td>
                    <td style="color: #dc2626; font-weight: bold;">' . $confidentialityText . '</td>
                </tr>
            </table>
        </div>
        
        <!-- Executive Summary -->
        <div class="executive-summary">
            <div class="summary-title">EXECUTIVE SUMMARY</div>
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['total_attempts']) . '</div>
                        <div class="metric-label">Total Attempts</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['active_students']) . '</div>
                        <div class="metric-label">Active Students</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['total_quizzes']) . '</div>
                        <div class="metric-label">Total Quizzes</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['average_score'], 1) . '%</div>
                        <div class="metric-label">Average Score</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <h3 class="data-section-title">QUIZ PERFORMANCE DATA</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Module Name</th>
                    <th>Quiz Title</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>';
        
        // Generate table rows
        foreach ($data as $row) {
            $scoreClass = '';
            $percentage = $row['percentage'] ?? 0;
            
            if ($percentage >= 80) {
                $scoreClass = 'score-excellent';
            } elseif ($percentage >= 60) {
                $scoreClass = 'score-good';
            } else {
                $scoreClass = 'score-poor';
            }
            
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['username']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['module_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['quiz_title']) . '</td>';
            $html .= '<td>' . $row['score'] . '/' . $row['total_points'] . '</td>';
            $html .= '<td class="' . $scoreClass . '">' . number_format($percentage, 1) . '%</td>';
            $html .= '<td>' . date('M j, Y', strtotime($row['completed_at'])) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '
            </tbody>
        </table>';
        
        return $html;
    }

    // ========================= PROGRESS TRACKING =========================
    private function exportProgressTrackingToPDF($data, $config, $summaryStats, $reportTitle) {
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
            if (!$data || empty($data)) {
                error_log("PDF Export Error: No progress tracking data found. Filters applied: " . json_encode($config));
                die("Error: No progress tracking data available for PDF export. This could be due to: 1) No student enrollments in database, 2) Course filters excluding all data, 3) Progress filters too restrictive. Please check your filters and try again.");
            }

            // Get admin info for the report
            $admin = $this->getAdminInfo();

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
            $mpdf->SetTitle($reportTitle . ' - ' . $config['company_name']);
            $mpdf->SetAuthor($config['company_name']);
            $mpdf->SetSubject($reportTitle);
            $mpdf->SetKeywords('report, progress, tracking, students, courses, ' . strtolower($config['company_name']));

            // Generate report ID
            $reportId = $this->generateReportId($reportTitle);

            // Prepare filter summary
            $filterText = $this->generateProgressTrackingFilterSummary($config);

            // Create HTML content
            $html = $this->generateProgressTrackingPDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle);

            // Set up proper header for every page
            $header = $this->generatePDFHeader($config, $reportTitle, $reportId);
            $mpdf->SetHTMLHeader($header);

            // Set up proper footer for every page
            $footer = $this->generatePDFFooter($config);
            $mpdf->SetHTMLFooter($footer);

            // Write HTML to PDF
            $mpdf->WriteHTML($html);

            // Output PDF
            $filename = 'progress_tracking_' . date('Y-m-d_H-i-s') . '.pdf';
            $mpdf->Output($filename, 'D');

        } catch (Exception $e) {
            error_log("PDF Export Error: " . $e->getMessage());
            error_log("PDF Export Stack Trace: " . $e->getTraceAsString());

            // More detailed error message
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>PDF Generation Error</h2>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
            echo '<p><strong>Data Count:</strong> ' . count($data) . ' records</p>';
            echo '<p><strong>Config:</strong> ' . htmlspecialchars(json_encode($config)) . '</p>';
            echo '<p>Please check the error log for more details or contact the administrator.</p>';
        }
    }

    private function generateProgressTrackingFilterSummary($config) {
        $filterSummary = [];

        if (!empty($config['course_filter']) && $config['course_filter'] !== 'all') {
            $filterSummary[] = "Course: " . $config['course_filter'];
        }
        if (!empty($config['progress_filter']) && $config['progress_filter'] !== 'all') {
            $filterSummary[] = "Progress: " . ucfirst(str_replace('_', ' ', $config['progress_filter']));
        }
        if (!empty($config['search'])) {
            $filterSummary[] = "Search: " . $config['search'];
        }
        if (!empty($config['date_from']) && !empty($config['date_to'])) {
            $filterSummary[] = "Date Range: " . $config['date_from'] . " to " . $config['date_to'];
        }

        return !empty($filterSummary) ? implode(', ', $filterSummary) : 'No filters applied';
    }

    private function generateProgressTrackingPDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle) {
        // Get confidentiality level
        $confidentiality = $config['confidentiality'] ?? 'internal';
        $confidentialityText = [
            'internal' => 'INTERNAL USE ONLY',
            'confidential' => 'CONFIDENTIAL',
            'restricted' => 'RESTRICTED ACCESS',
            'public' => 'PUBLIC REPORT'
        ][$confidentiality];

        // Admin info level
        $adminInfoLevel = $config['admin_info_level'] ?? 'name_only';
        $adminInfo = '';
        switch ($adminInfoLevel) {
            case 'name_only':
                $adminInfo = htmlspecialchars($admin['username']);
                break;
            case 'name_role':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ')';
                break;
            case 'full_details':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ') - ' . $config['company_email'];
                break;
            case 'anonymous':
                $adminInfo = 'System Administrator';
                break;
        }

        $html = '
        <style>
            body {
                font-family: "DejaVu Sans", Arial, sans-serif;
                font-size: 8pt;
                line-height: 1.2;
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
                font-size: 7pt;
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
                width: 18%;
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
                margin: 0 auto;
            }

            .summary-table td {
                padding: 4px 2px;
                text-align: center;
                border-right: 1px solid #cbd5e1;
            }

            .summary-table td:last-child {
                border-right: none;
            }

            .metric-value {
                font-size: 10pt;
                font-weight: bold;
                color: #0369a1;
                display: block;
                margin-bottom: 1px;
            }

            .metric-label {
                font-size: 6pt;
                color: #64748b;
                display: block;
                font-weight: normal;
                line-height: 1.1;
            }

            .data-section-title {
                font-size: 10pt;
                font-weight: bold;
                color: #1e293b;
                margin: 8px 0 6px 0;
                padding: 4px 0;
                border-bottom: 1px solid #e2e8f0;
            }

            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 6px;
                font-size: 7pt;
            }

            .data-table th {
                background-color: #f8fafc;
                color: #374151;
                font-weight: bold;
                padding: 4px 2px;
                text-align: left;
                border-bottom: 2px solid #cbd5e1;
                font-size: 6pt;
                white-space: nowrap;
            }

            .data-table td {
                padding: 3px 2px;
                border-bottom: 1px solid #e5e7eb;
                vertical-align: top;
            }

            .data-table tr:nth-child(even) {
                background-color: #f8fafc;
            }

            .data-table tr:hover {
                background-color: #e2e8f0;
            }

            .totals-row {
                background-color: #fef3c7 !important;
                font-weight: bold;
            }

            .totals-row td {
                border-top: 2px solid #f59e0b;
                border-bottom: 2px solid #f59e0b;
            }
        </style>

        <!-- Metadata Section -->
        <div class="metadata-section">
            <table class="metadata-table">
                <tr>
                    <td class="metadata-label">Generated By:</td>
                    <td>' . $adminInfo . '</td>
                    <td class="metadata-label">Report ID:</td>
                    <td>' . $reportId . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Generated On:</td>
                    <td>' . date('M j, Y g:i A T') . '</td>
                    <td class="metadata-label">Confidentiality:</td>
                    <td style="color:#dc2626;font-weight:bold;">' . $confidentialityText . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Filters Applied:</td>
                    <td colspan="3">' . htmlspecialchars($filterText) . '</td>
                </tr>
            </table>
        </div>

        <!-- Executive Summary -->
        <div class="executive-summary">
            <div class="summary-title">EXECUTIVE SUMMARY</div>
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['total_students']) . '</div>
                        <div class="metric-label">Total Students</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['completed_students']) . '</div>
                        <div class="metric-label">Completed</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['in_progress_students']) . '</div>
                        <div class="metric-label">In Progress</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['not_started_students']) . '</div>
                        <div class="metric-label">Not Started</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['average_progress'], 1) . '%</div>
                        <div class="metric-label">Avg Progress</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['total_courses']) . '</div>
                        <div class="metric-label">Total Courses</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Data Table -->
        <h3 class="data-section-title">STUDENT PROGRESS TRACKING DATA</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Progress %</th>
                    <th>Completed Modules</th>
                    <th>Total Modules</th>
                    <th>Current Section</th>
                    <th>Chapters Completed</th>
                    <th>Last Activity</th>
                    <th>Enrollment Date</th>
                </tr>
            </thead>
            <tbody>';

        // Generate table rows
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['student_name'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['student_email'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['course_title'] ?? '') . '</td>';
            $html .= '<td style="text-align:center;">' . htmlspecialchars($row['progress_percentage'] ?? 0) . '%</td>';
            $html .= '<td style="text-align:center;">' . htmlspecialchars($row['completed_modules'] ?? 0) . '</td>';
            $html .= '<td style="text-align:center;">' . htmlspecialchars($row['total_modules'] ?? 0) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['current_section'] ?? 'Not started') . '</td>';
            $html .= '<td style="text-align:center;">' . htmlspecialchars($row['chapters_completed'] ?? 0) . '</td>';
            $html .= '<td>' . ($row['last_activity'] ? date('M j, Y H:i', strtotime($row['last_activity'])) : 'Never') . '</td>';
            $html .= '<td>' . ($row['enrollment_date'] ? date('M j, Y', strtotime($row['enrollment_date'])) : 'N/A') . '</td>';
            $html .= '</tr>';
        }

        $html .= '
            </tbody>
        </table>';

        return $html;
    }

    // ========================= LOGIN ACTIVITY / BROKEN LINKS =========================
    private function getLoginActivityData($filters, $offset, $limit) {
        try {
            $where = "WHERE 1=1";
            $params = [];
            if (!empty($filters['search'])) {
                $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) LIKE ? OR ll.ip_address LIKE ?)";
                for ($i=0;$i<4;$i++) { $params[] = '%'.$filters['search'].'%'; }
            }
            if (!empty($filters['role_filter'])) { $where .= " AND u.role = ?"; $params[] = $filters['role_filter']; }
            if (!empty($filters['status_filter'])) { $where .= " AND ll.status = ?"; $params[] = $filters['status_filter']; }
            if (!empty($filters['date_from'])) { $where .= " AND DATE(ll.login_time) >= ?"; $params[] = $filters['date_from']; }
            if (!empty($filters['date_to'])) { $where .= " AND DATE(ll.login_time) <= ?"; $params[] = $filters['date_to']; }
            $sql = "SELECT ll.id, ll.user_id, u.username, COALESCE(u.first_name,'') as first_name, COALESCE(u.last_name,'') as last_name, u.role, ll.login_time, ll.ip_address, ll.status, COALESCE(ll.location,'') as location FROM login_logs ll JOIN users u ON ll.user_id = u.id $where ORDER BY ll.login_time DESC LIMIT ? OFFSET ?";
            $params[] = $limit; $params[] = $offset;
            $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('Login activity export error: '.$e->getMessage()); return []; }
    }

    private function getBrokenLinksData($filters, $offset, $limit) {
        try {
            $where = "WHERE 1=1"; $params = [];
            if (!empty($filters['severity_filter'])) { $where .= " AND bl.severity = ?"; $params[] = $filters['severity_filter']; }
            if (!empty($filters['broken_status_filter'])) {
                if ($filters['broken_status_filter'] === 'broken') { $where .= " AND bl.status_code >= 400"; }
                else if ($filters['broken_status_filter'] === 'working') { $where .= " AND bl.status_code < 400"; }
            }
            if (!empty($filters['date_from'])) { $where .= " AND DATE(bl.first_detected) >= ?"; $params[] = $filters['date_from']; }
            if (!empty($filters['date_to'])) { $where .= " AND DATE(bl.last_checked) <= ?"; $params[] = $filters['date_to']; }
            $sql = "SELECT bl.id, bl.url, bl.reference_page, bl.reference_module, bl.first_detected, bl.last_checked, bl.status_code, bl.severity FROM broken_links bl $where ORDER BY bl.last_checked DESC, bl.severity DESC LIMIT ? OFFSET ?";
            $params[] = $limit; $params[] = $offset;
            $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('Broken links export error: '.$e->getMessage()); return []; }
    }

    private function getLoginSummary($filters) {
        try {
            $sqlSuccess = "SELECT COUNT(*) FROM login_logs WHERE status='success'";
            $params = [];
            if (!empty($filters['date_from'])) { $sqlSuccess .= " AND DATE(login_time) >= ?"; $params[] = $filters['date_from']; }
            if (!empty($filters['date_to'])) { $sqlSuccess .= " AND DATE(login_time) <= ?"; $params[] = $filters['date_to']; }
            $stmt = $this->pdo->prepare($sqlSuccess); $stmt->execute($params); $success = (int)$stmt->fetchColumn();
            $sqlFailed = str_replace("status='success'", "status='failed'", $sqlSuccess);
            $stmt = $this->pdo->prepare($sqlFailed); $stmt->execute($params); $failed = (int)$stmt->fetchColumn();
            return ['logins_success' => $success, 'logins_failed' => $failed];
        } catch (PDOException $e) { return ['logins_success'=>0,'logins_failed'=>0]; }
    }

    private function getBrokenLinksSummary($filters) {
        try {
            $sql = "SELECT SUM(CASE WHEN status_code>=400 THEN 1 ELSE 0 END) as broken, COUNT(*) as total FROM broken_links";
            $stmt = $this->pdo->query($sql); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['broken' => (int)($row['broken']??0), 'total' => (int)($row['total']??0)];
        } catch (PDOException $e) { return ['broken'=>0,'total'=>0]; }
    }

    private function exportLoginOrLinksToPDF($data, $config, $summary, $reportTitle, $type) {
        $autoloadPath = '../vendor/autoload.php';
        if (!file_exists($autoloadPath)) { $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php'; }
        if (!file_exists($autoloadPath)) { die("Error: mPDF library not found. Please run 'composer install' to install dependencies."); }
        require_once $autoloadPath;
        // Allow empty datasets and still render an empty-state PDF
        $admin = $this->getAdminInfo();
        $mpdf = new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','orientation'=>'P','margin_left'=>10,'margin_right'=>10,'margin_top'=>30,'margin_bottom'=>25,'margin_header'=>9,'margin_footer'=>9]);
        $mpdf->SetTitle($reportTitle . ' - ' . ($config['company_name'] ?? ''));
        $reportId = $this->generateReportId($reportTitle);
        $filterText = $this->generateFilterSummary($config);
        $html = $this->buildLoginOrLinksHTML($config, $admin, $reportId, $filterText, $summary, $data, $reportTitle, $type);
        $mpdf->SetHTMLHeader($this->generatePDFHeader($config, $reportTitle, $reportId));
        $mpdf->SetHTMLFooter($this->generatePDFFooter($config));
        $mpdf->WriteHTML($html);
        $mpdf->Output(strtolower(str_replace(' ','_', $reportTitle)) . '_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
    }

private function buildLoginOrLinksHTML($config, $admin, $reportId, $filterText, $summary, $data, $reportTitle, $type) {
    $confidentiality = $config['confidentiality'] ?? 'internal';
    $confidentialityText = [ 'internal'=>'INTERNAL USE ONLY','confidential'=>'CONFIDENTIAL','restricted'=>'RESTRICTED ACCESS','public'=>'PUBLIC REPORT'][$confidentiality];
    $adminInfo = htmlspecialchars($admin['username']);
    
    // Updated CSS with 4-box layout and all required classes
    $html = '<style>
        body{font-family:"DejaVu Sans",Arial,sans-serif;font-size:9pt;line-height:1.3;color:#333}
        .metadata-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:10px;margin-bottom:15px;font-size:8pt}
        .metadata-table{width:100%;border-collapse:collapse}
        .metadata-table td{padding:4px 8px;border-bottom:1px solid #e5e7eb;vertical-align:top}
        .metadata-label{font-weight:bold;color:#374151;width:20%}
        .executive-summary{background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border:1px solid #94a3b8;border-radius:4px;padding:10px;margin-bottom:15px}
        .summary-title{font-size:10pt;font-weight:bold;color:#475569;margin-bottom:10px;text-align:center}
        .summary-table{width:100%;border-collapse:collapse;table-layout:fixed;margin:0}
        .summary-table td{width:25%;text-align:center;padding:8px 4px;border-right:1px solid #cbd5e1;vertical-align:middle}
        .summary-table td:last-child{border-right:none}
        .metric-value{font-size:14pt;font-weight:bold;color:#0369a1;display:block;margin-bottom:3px}
        .metric-label{font-size:7pt;color:#64748b;display:block;font-weight:normal;line-height:1.2}
        .data-table{width:100%;border-collapse:collapse;font-size:8pt;margin-top:15px}
        .data-table th{background:linear-gradient(135deg,#374151,#4b5563);color:#fff;padding:6px 4px;border:1px solid #6b7280;font-weight:bold}
        .data-table td{padding:4px;border:1px solid #d1d5db;text-align:center}
    </style>';
    
    // Metadata section
    $html .= '<div class="metadata-section"><table class="metadata-table">';
    $html .= '<tr><td class="metadata-label">Generated By:</td><td>'.$adminInfo.'</td><td class="metadata-label">Period:</td><td>'.(!empty($config['date_from']) && !empty($config['date_to']) ? date('M j', strtotime($config['date_from'])).' - '.date('M j, Y', strtotime($config['date_to'])) : 'All Time').'</td></tr>';
    $html .= '<tr><td class="metadata-label">Filters Applied:</td><td>'.htmlspecialchars($filterText).'</td><td class="metadata-label">Confidentiality:</td><td style="color:#dc2626;font-weight:bold;">'.$confidentialityText.'</td></tr>';
    $html .= '</table></div>';
    
    // Executive Summary with 4 boxes
    $html .= '<div class="executive-summary">';
    $html .= '<div class="summary-title">EXECUTIVE SUMMARY</div>';
    $html .= '<table class="summary-table"><tr>';
    
    if ($type === 'login') {
        // 4 boxes for Login Activity
        $total = $summary['logins_success'] + $summary['logins_failed'];
        $successRate = $total > 0 ? round(($summary['logins_success'] / $total) * 100, 1) : 0;
        
        $html .= '<td><div class="metric-value">'.number_format($total).'</div><div class="metric-label">Total Logins</div></td>';
        $html .= '<td><div class="metric-value">'.number_format($summary['logins_success']).'</div><div class="metric-label">Successful</div></td>';
        $html .= '<td><div class="metric-value">'.number_format($summary['logins_failed']).'</div><div class="metric-label">Failed</div></td>';
        $html .= '<td><div class="metric-value">'.$successRate.'%</div><div class="metric-label">Success Rate</div></td>';
    } else {
        // 4 boxes for Broken Links
        $brokenPercent = $summary['total'] > 0 ? round(($summary['broken'] / $summary['total']) * 100, 1) : 0;
        $working = $summary['total'] - $summary['broken'];
        
        $html .= '<td><div class="metric-value">'.number_format($summary['total']).'</div><div class="metric-label">Total Links</div></td>';
        $html .= '<td><div class="metric-value">'.number_format($working).'</div><div class="metric-label">Working</div></td>';
        $html .= '<td><div class="metric-value">'.number_format($summary['broken']).'</div><div class="metric-label">Broken</div></td>';
        $html .= '<td><div class="metric-value">'.$brokenPercent.'%</div><div class="metric-label">Error Rate</div></td>';
    }
    
    $html .= '</tr></table></div>';
    
    // Data table section
    if ($type === 'login') {
        $html .= '<h3 style="text-align:center;margin:12px 0;color:#374151;font-size:11pt">LOGIN ACTIVITY DATA</h3>';
        $html .= '<table class="data-table"><thead><tr>';
        $html .= '<th>User ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Login Time</th><th>IP</th><th>Location</th><th>Status</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($data as $row) {
            $full = trim(($row['first_name']??'').' '.($row['last_name']??''));
            $html .= '<tr>';
            $html .= '<td>#'.(int)$row['user_id'].'</td>';
            $html .= '<td>'.htmlspecialchars($row['username']).'</td>';
            $html .= '<td>'.htmlspecialchars($full ?: $row['username']).'</td>';
            $html .= '<td>'.ucfirst(htmlspecialchars($row['role'])).'</td>';
            $html .= '<td>'.date('M j, Y H:i', strtotime($row['login_time'])).'</td>';
            $html .= '<td>'.htmlspecialchars($row['ip_address']).'</td>';
            $html .= '<td>'.htmlspecialchars($row['location'] ?: 'Unknown').'</td>';
            $html .= '<td>'.($row['status']==='success' ? 'Success' : 'Failed').'</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html .= '<h3 style="text-align:center;margin:12px 0;color:#374151;font-size:11pt">BROKEN LINKS DATA</h3>';
        $html .= '<table class="data-table"><thead><tr>';
        $html .= '<th>URL</th><th>Reference Page</th><th>Module</th><th>First Detected</th><th>Last Checked</th><th>Status</th><th>Severity</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($data as $row) {
            $sev = $row['severity']==='critical' ? 'Critical' : 'Warning';
            $html .= '<tr>';
            $html .= '<td style="text-align:left">'.htmlspecialchars($row['url']).'</td>';
            $html .= '<td>'.htmlspecialchars($row['reference_page']??'').'</td>';
            $html .= '<td>'.htmlspecialchars($row['reference_module']??'').'</td>';
            $html .= '<td>'.date('M j, Y', strtotime($row['first_detected'])).'</td>';
            $html .= '<td>'.date('M j, Y H:i', strtotime($row['last_checked'])).'</td>';
            $html .= '<td>'.$row['status_code'].'</td>';
            $html .= '<td>'.$sev.'</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }
    
    return $html;
}

    // ========================= AUDIT TRAILS =========================
    private function getAuditTrailsData($filters, $offset, $limit) {
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['user_filter'])) {
            $where .= " AND (username LIKE ? OR user_id = ?)";
            $params[] = '%' . $filters['user_filter'] . '%';
            $params[] = $filters['user_filter'];
        }
        if (!empty($filters['action_filter'])) {
            $where .= " AND action_type = ?";
            $params[] = $filters['action_filter'];
        }
        if (!empty($filters['outcome_filter'])) {
            $where .= " AND outcome = ?";
            $params[] = $filters['outcome_filter'];
        }
        
        $sql = "SELECT 
                    timestamp,
                    user_id,
                    username,
                    action_type,
                    resource_type,
                    resource_id,
                    ip_address,
                    outcome
                FROM comprehensive_audit_trail 
                $where 
                ORDER BY timestamp DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Audit data error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getAuditTrailsSummary($filters) {
        try {
            $where = "WHERE 1=1";
            $params = [];
            
            // Apply filters
            if (!empty($filters['date_from'])) {
                $where .= " AND DATE(timestamp) >= ?";
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $where .= " AND DATE(timestamp) <= ?";
                $params[] = $filters['date_to'];
            }
            if (!empty($filters['user_filter'])) {
                $where .= " AND (username LIKE ? OR user_id = ?)";
                $params[] = '%' . $filters['user_filter'] . '%';
                $params[] = $filters['user_filter'];
            }
            if (!empty($filters['action_filter'])) {
                $where .= " AND action_type = ?";
                $params[] = $filters['action_filter'];
            }
            if (!empty($filters['outcome_filter'])) {
                $where .= " AND outcome = ?";
                $params[] = $filters['outcome_filter'];
            }
            
            // Query comprehensive_audit_trail table
            $sql = "SELECT 
                        COUNT(*) as total, 
                        SUM(CASE WHEN outcome = 'Success' THEN 1 ELSE 0 END) as success, 
                        SUM(CASE WHEN outcome IN ('Failed', 'Partial') THEN 1 ELSE 0 END) as failed 
                    FROM comprehensive_audit_trail 
                    $where";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Audit Summary: Total=" . ($row['total'] ?? 0) . ", Success=" . ($row['success'] ?? 0) . ", Failed=" . ($row['failed'] ?? 0));
            
            return [
                'total' => (int)($row['total'] ?? 0), 
                'success' => (int)($row['success'] ?? 0), 
                'failed' => (int)($row['failed'] ?? 0)
            ];
            
        } catch (PDOException $e) {
            error_log("Audit Trails Summary Error: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }
        

    private function exportAuditTrailsToPDF($data, $config, $summary, $reportTitle) {
        $autoloadPath = '../vendor/autoload.php'; if (!file_exists($autoloadPath)) { $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php'; }
        if (!file_exists($autoloadPath)) { die("Error: mPDF library not found. Please run 'composer install' to install dependencies."); }
        if (!file_exists($autoloadPath)) { die("Error: mPDF library not found. Please run 'composer install' to install dependencies."); }
        require_once $autoloadPath; // Allow empty exports
        $admin = $this->getAdminInfo();
        $mpdf = new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','orientation'=>'P','margin_left'=>10,'margin_right'=>10,'margin_top'=>30,'margin_bottom'=>25,'margin_header'=>9,'margin_footer'=>9]);
        $mpdf->SetTitle($reportTitle . ' - ' . ($config['company_name'] ?? ''));
        $reportId = $this->generateReportId($reportTitle);
        $filterText = $this->generateFilterSummary($config);
        $html = $this->buildAuditTrailsHTML($config, $admin, $reportId, $filterText, $summary, $data, $reportTitle);
        $mpdf->SetHTMLHeader($this->generatePDFHeader($config, $reportTitle, $reportId));
        $mpdf->SetHTMLFooter($this->generatePDFFooter($config));
        $mpdf->WriteHTML($html);
        $mpdf->Output('audit_trails_'.date('Y-m-d_H-i-s').'.pdf', 'D');
    }

private function buildAuditTrailsHTML($config, $admin, $reportId, $filterText, $summary, $data, $reportTitle) {
    $confidentiality = $config['confidentiality'] ?? 'internal';
    $confidentialityText = [ 'internal'=>'INTERNAL USE ONLY','confidential'=>'CONFIDENTIAL','restricted'=>'RESTRICTED ACCESS','public'=>'PUBLIC REPORT'][$confidentiality];
    $adminInfo = htmlspecialchars($admin['username']);
    
    // Updated CSS with 4-box layout and all required classes
    $html = '<style>
        body{font-family:"DejaVu Sans",Arial,sans-serif;font-size:9pt;line-height:1.3;color:#333}
        .metadata-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:10px;margin-bottom:15px;font-size:8pt}
        .metadata-table{width:100%;border-collapse:collapse}
        .metadata-table td{padding:4px 8px;border-bottom:1px solid #e5e7eb;vertical-align:top}
        .metadata-label{font-weight:bold;color:#374151;width:20%}
        .executive-summary{background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border:1px solid #94a3b8;border-radius:4px;padding:10px;margin-bottom:15px}
        .summary-title{font-size:10pt;font-weight:bold;color:#475569;margin-bottom:10px;text-align:center}
        .summary-table{width:100%;border-collapse:collapse;table-layout:fixed;margin:0}
        .summary-table td{width:25%;text-align:center;padding:8px 4px;border-right:1px solid #cbd5e1;vertical-align:middle}
        .summary-table td:last-child{border-right:none}
        .metric-value{font-size:14pt;font-weight:bold;color:#0369a1;display:block;margin-bottom:3px}
        .metric-label{font-size:7pt;color:#64748b;display:block;font-weight:normal;line-height:1.2}
        .data-table{width:100%;border-collapse:collapse;font-size:8pt;margin-top:15px}
        .data-table th{background:linear-gradient(135deg,#374151,#4b5563);color:#fff;padding:6px 4px;border:1px solid #6b7280;font-weight:bold}
        .data-table td{padding:4px;border:1px solid #d1d5db;text-align:center}
    </style>';
    
    // Metadata section
    $html .= '<div class="metadata-section"><table class="metadata-table">';
    $html .= '<tr><td class="metadata-label">Generated By:</td><td>'.$adminInfo.'</td><td class="metadata-label">Period:</td><td>'.(!empty($config['date_from']) && !empty($config['date_to']) ? date('M j', strtotime($config['date_from'])).' - '.date('M j, Y', strtotime($config['date_to'])) : 'All Time').'</td></tr>';
    $html .= '<tr><td class="metadata-label">Filters Applied:</td><td>'.htmlspecialchars($filterText).'</td><td class="metadata-label">Confidentiality:</td><td style="color:#dc2626;font-weight:bold;">'.$confidentialityText.'</td></tr>';
    $html .= '</table></div>';
    
    // Executive Summary with 4 boxes
    $html .= '<div class="executive-summary">';
    $html .= '<div class="summary-title">EXECUTIVE SUMMARY</div>';
    $html .= '<table class="summary-table"><tr>';
    
    // Calculate success rate
    $successRate = $summary['total'] > 0 ? round(($summary['success'] / $summary['total']) * 100, 1) : 0;
    
    // 4 boxes for Audit Trails
    $html .= '<td><div class="metric-value">'.number_format($summary['total']).'</div><div class="metric-label">Total Actions</div></td>';
    $html .= '<td><div class="metric-value">'.number_format($summary['success']).'</div><div class="metric-label">Successful</div></td>';
    $html .= '<td><div class="metric-value">'.number_format($summary['failed']).'</div><div class="metric-label">Failed</div></td>';
    $html .= '<td><div class="metric-value">'.$successRate.'%</div><div class="metric-label">Success Rate</div></td>';
    
    $html .= '</tr></table></div>';
    
    // Data table section
    $html .= '<h3 style="text-align:center;margin:12px 0;color:#374151;font-size:11pt">AUDIT TRAILS DATA</h3>';
    $html .= '<table class="data-table"><thead><tr>';
    $html .= '<th>Timestamp (UTC)</th><th>User</th><th>Action</th><th>Resource</th><th>IP</th><th>Outcome</th>';
    $html .= '</tr></thead><tbody>';
    
    if (empty($data)) {
        $html .= '<tr><td colspan="6" style="text-align:center;color:#6b7280;padding:12px">No records found for the selected filters</td></tr>';
    } else {
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>'.date('Y-m-d H:i:s', strtotime($row['timestamp'])).'</td>';
            $html .= '<td>'.htmlspecialchars($row['username']).' (#'.htmlspecialchars($row['user_id']).')</td>';
            $html .= '<td>'.htmlspecialchars($row['action_type']).'</td>';
            $html .= '<td>'.htmlspecialchars(trim(($row['resource_type']??'').' '.($row['resource_id']??''))).'</td>';
            $html .= '<td>'.htmlspecialchars($row['ip_address']).'</td>';
            $html .= '<td>'.htmlspecialchars($row['outcome']).'</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody></table>';
    return $html;
}

    // User Roles specific PDF export (honors export configuration columns)
    private function exportUserRoleToPDF($data, $config, $summaryStats, $reportTitle) {
        $autoloadPath = '../vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
        }
        if (!file_exists($autoloadPath)) {
            die("Error: mPDF library not found. Please run 'composer install' to install dependencies.");
        }
        require_once $autoloadPath;
        try {
            if (empty($data)) {
                error_log("PDF Export Error: No user role data found. Filters: " . json_encode($config));
                die("Error: No user data available for PDF export. Please adjust your filters and try again.");
            }
            $admin = $this->getAdminInfo();
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
            $mpdf->SetTitle($reportTitle . ' - ' . ($config['company_name'] ?? ''));
            $mpdf->SetAuthor($config['company_name'] ?? '');
            $mpdf->SetSubject($reportTitle);
            $mpdf->SetKeywords('report, users, roles');
            $reportId = $this->generateReportId($reportTitle);
            $filterText = $this->generateFilterSummary($config);
            $html = $this->generateUserRolePDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle);
            $header = $this->generatePDFHeader($config, $reportTitle, $reportId);
            $mpdf->SetHTMLHeader($header);
            $footer = $this->generatePDFFooter($config);
            $mpdf->SetHTMLFooter($footer);
            $mpdf->WriteHTML($html);
            $filename = 'user_roles_' . date('Y-m-d_H-i-s') . '.pdf';
            $mpdf->Output($filename, 'D');
        } catch (Exception $e) {
            error_log('User Roles PDF Export Error: ' . $e->getMessage());
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>PDF Generation Error</h2>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }

    private function generateUserRolePDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle) {
        $confidentiality = $config['confidentiality'] ?? 'internal';
        $confidentialityText = [
            'internal' => 'INTERNAL USE ONLY',
            'confidential' => 'CONFIDENTIAL',
            'restricted' => 'RESTRICTED ACCESS',
            'public' => 'PUBLIC REPORT'
        ][$confidentiality];

        $adminInfoLevel = $config['admin_info_level'] ?? 'name_only';
        $adminInfo = '';
        switch ($adminInfoLevel) {
            case 'name_only':
                $adminInfo = htmlspecialchars($admin['username']);
                break;
            case 'name_role':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ')';
                break;
            case 'full_details':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ') - ' . ($config['company_email'] ?? '');
                break;
            default:
                $adminInfo = 'System Administrator';
        }

        // quick aggregates from summary
        $totalsByRole = ['student' => 0, 'teacher' => 0, 'admin' => 0];
        foreach ($summaryStats as $row) {
            if (isset($totalsByRole[$row['role']])) {
                $totalsByRole[$row['role']] = (int)$row['total'];
            }
        }
        $totalUsers = count($data);
        $activeUsers = 0;
        foreach ($data as $r) {
            $st = $r['status'] ?? 'active';
            if ($st === 'active' || $st === null) { $activeUsers++; }
        }

        $selected = $config['export_columns'] ?? ['user_id','username','full_name','email','role','status','created_at'];
        $headers = '';
        foreach ($selected as $c) {
            $label = match ($c) {
                'user_id' => 'User ID',
                'username' => 'Username',
                'full_name' => 'Full Name',
                'email' => 'Email',
                'role' => 'Role',
                'status' => 'Status',
                'created_at' => 'Date Registered',
                'enrolled_courses' => 'Enrolled Courses',
                'teaching_courses' => 'Teaching Courses',
                default => ucfirst(str_replace('_',' ', $c))
            };
            $headers .= '<th>' . $label . '</th>';
        }

        $html = '
        <style>
            body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9pt; }
            .metadata-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:10px;margin-bottom:15px;font-size:8pt}
            .metadata-table{width:100%;border-collapse:collapse}
            .metadata-table td{padding:4px 8px;vertical-align:top;border-bottom:1px solid #e5e7eb}
            .metadata-label{font-weight:bold;color:#374151;width:20%}
            .executive-summary{background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border:1px solid #94a3b8;border-radius:4px;padding:8px;margin-bottom:15px;text-align:center}
            .summary-table{width:100%;border-collapse:collapse;table-layout:fixed}
            .summary-table td{width:25%;text-align:center;padding:6px 4px;border-right:1px solid #cbd5e1}
            .summary-table td:last-child{border-right:none}
            .metric-value{font-size:12pt;font-weight:bold;color:#0369a1;display:block;margin-bottom:2px}
            .metric-label{font-size:7pt;color:#64748b;display:block}
            .data-section-title{color:#374151;margin:15px 0 8px 0;font-size:11pt;font-weight:bold;text-align:center;border-bottom:1px solid #d1d5db;padding-bottom:4px}
            .data-table{width:100%;border-collapse:collapse;margin-bottom:15px;font-size:8pt}
            .data-table th{background:linear-gradient(135deg,#374151,#4b5563);color:#fff;padding:6px 4px;text-align:center;border:1px solid #6b7280}
            .data-table td{padding:4px;text-align:center;border:1px solid #d1d5db}
            .data-table tr:nth-child(even){background:#f9fafb}
        </style>
        <div class="metadata-section">
            <table class="metadata-table">
                <tr>
                    <td class="metadata-label">Generated By:</td>
                    <td>' . $adminInfo . '</td>
                    <td class="metadata-label">Period:</td>
                    <td>' . (!empty($config['date_from']) && !empty($config['date_to']) ?
                        date('M j', strtotime($config['date_from'])) . ' - ' . date('M j, Y', strtotime($config['date_to'])) : 'All Time') . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Filters Applied:</td>
                    <td>' . htmlspecialchars($filterText) . '</td>
                    <td class="metadata-label">Confidentiality:</td>
                    <td style="color:#dc2626;font-weight:bold;">' . $confidentialityText . '</td>
                </tr>
            </table>
        </div>
        <div class="executive-summary">
            <div class="summary-title">EXECUTIVE SUMMARY</div>
            <table class="summary-table"><tr>
                <td><div class="metric-value">' . number_format($totalUsers) . '</div><div class="metric-label">Total Users</div></td>
                <td><div class="metric-value">' . number_format($activeUsers) . '</div><div class="metric-label">Active Users</div></td>
                <td><div class="metric-value">' . number_format($totalsByRole['student']) . '</div><div class="metric-label">Students</div></td>
                <td><div class="metric-value">' . number_format($totalsByRole['teacher'] + $totalsByRole['admin']) . '</div><div class="metric-label">Teachers + Admins</div></td>
            </tr></table>
        </div>
        <h3 class="data-section-title">USER LIST</h3>
        <table class="data-table"><thead><tr>' . $headers . '</tr></thead><tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($selected as $col) {
                switch ($col) {
                    case 'user_id':
                        $html .= '<td>#' . (int)$row['id'] . '</td>';
                        break;
                    case 'username':
                        $html .= '<td>' . htmlspecialchars($row['username']) . '</td>';
                        break;
                    case 'full_name':
                        $full = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                        $html .= '<td>' . htmlspecialchars($full !== '' ? $full : $row['username']) . '</td>';
                        break;
                    case 'email':
                        $html .= '<td>' . htmlspecialchars($row['email']) . '</td>';
                        break;
                    case 'role':
                        $html .= '<td>' . ucfirst(htmlspecialchars($row['role'])) . '</td>';
                        break;
                    case 'status':
                        $st = $row['status'] ?? 'active';
                        $html .= '<td>' . ucfirst(htmlspecialchars($st)) . '</td>';
                        break;
                    case 'created_at':
                        $html .= '<td>' . date('M j, Y', strtotime($row['created_at'])) . '</td>';
                        break;
                    case 'enrolled_courses':
                    case 'teaching_courses':
                        $val = (int)($row[$col] ?? 0);
                        $html .= '<td>' . $val . '</td>';
                        break;
                    default:
                        $html .= '<td>-</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Export Engagement Monitoring to PDF
     */
    private function exportEngagementMonitoringToPDF($data, $config, $summaryStats, $reportTitle) {
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
            if (!$data || empty($data)) {
                error_log("PDF Export Error: No engagement monitoring data found. Filters applied: " . json_encode($config));
                die("Error: No engagement monitoring data available for PDF export. This could be due to: 1) No student enrollments in database, 2) Date range too restrictive, 3) Student/course filters excluding all data. Please check your filters and try again.");
            }

            // Get admin info for the report
            $admin = $this->getAdminInfo();

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
            $mpdf->SetTitle($reportTitle . ' - ' . $config['company_name']);
            $mpdf->SetAuthor($config['company_name']);
            $mpdf->SetSubject($reportTitle);
            $mpdf->SetKeywords('report, engagement, monitoring, students, courses, ' . strtolower($config['company_name']));

            // Generate report ID
            $reportId = $this->generateReportId($reportTitle);

            // Prepare filter summary
            $filterText = $this->generateEngagementMonitoringFilterSummary($config);

            // Create HTML content
            $html = $this->generateEngagementMonitoringPDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle);

            // Set up proper header for every page
            $header = $this->generatePDFHeader($config, $reportTitle, $reportId);
            $mpdf->SetHTMLHeader($header);

            // Set up proper footer for every page
            $footer = $this->generatePDFFooter($config);
            $mpdf->SetHTMLFooter($footer);

            // Write HTML to PDF
            $mpdf->WriteHTML($html);

            // Output PDF
            $filename = 'engagement_monitoring_' . date('Y-m-d_H-i-s') . '.pdf';
            $mpdf->Output($filename, 'D');

        } catch (Exception $e) {
            error_log("PDF Export Error: " . $e->getMessage());
            error_log("PDF Export Stack Trace: " . $e->getTraceAsString());

            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>PDF Generation Error</h2>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
            echo '<p><strong>Data Count:</strong> ' . count($data) . ' records</p>';
            echo '<p><strong>Config:</strong> ' . htmlspecialchars(json_encode($config)) . '</p>';
            echo '<p>Please check the error log for more details or contact the administrator.</p>';
        }
    }
    
    /**
     * Export Completion Reports to PDF
     */
    private function exportCompletionReportsToPDF($data, $config, $summaryStats, $reportTitle) {
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
            if (!$data || empty($data)) {
                error_log("PDF Export Error: No completion reports data found. Filters applied: " . json_encode($config));
                die("Error: No completion reports data available for PDF export. This could be due to: 1) No student enrollments in database, 2) Date range too restrictive, 3) Course filters excluding all data. Please check your filters and try again.");
            }

            // Get admin info for the report
            $admin = $this->getAdminInfo();

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
            $mpdf->SetTitle($reportTitle . ' - ' . $config['company_name']);
            $mpdf->SetAuthor($config['company_name']);
            $mpdf->SetSubject($reportTitle);
            $mpdf->SetKeywords('report, completion, students, courses, ' . strtolower($config['company_name']));

            // Generate report ID
            $reportId = $this->generateReportId($reportTitle);

            // Prepare filter summary
            $filterText = $this->generateCompletionReportsFilterSummary($config);

            // Create HTML content
            $html = $this->generateCompletionReportsPDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle);

            // Set up proper header for every page
            $header = $this->generatePDFHeader($config, $reportTitle, $reportId);
            $mpdf->SetHTMLHeader($header);

            // Set up proper footer for every page
            $footer = $this->generatePDFFooter($config);
            $mpdf->SetHTMLFooter($footer);

            // Write HTML to PDF
            $mpdf->WriteHTML($html);

            // Output PDF
            $filename = 'completion_reports_' . date('Y-m-d_H-i-s') . '.pdf';
            $mpdf->Output($filename, 'D');

        } catch (Exception $e) {
            error_log("PDF Export Error: " . $e->getMessage());
            error_log("PDF Export Stack Trace: " . $e->getTraceAsString());

            header('Content-Type: text/html; charset=utf-8');
            echo '<h2>PDF Generation Error</h2>';
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
            echo '<p><strong>Data Count:</strong> ' . count($data) . ' records</p>';
            echo '<p><strong>Config:</strong> ' . htmlspecialchars(json_encode($config)) . '</p>';
            echo '<p>Please check the error log for more details or contact the administrator.</p>';
        }
    }
    
    /**
     * Generate Engagement Monitoring filter summary
     */
    private function generateEngagementMonitoringFilterSummary($config) {
        $filterSummary = [];

        if (!empty($config['date_from']) && !empty($config['date_to'])) {
            $filterSummary[] = "Date Range: " . $config['date_from'] . " to " . $config['date_to'];
        }
        if (!empty($config['student_id']) && $config['student_id'] !== '') {
            $filterSummary[] = "Student: " . $config['student_id'];
        }
        if (!empty($config['course_id']) && $config['course_id'] !== '') {
            $filterSummary[] = "Course: " . $config['course_id'];
        }

        return !empty($filterSummary) ? implode(', ', $filterSummary) : 'No filters applied';
    }
    
    /**
     * Generate Completion Reports filter summary
     */
    private function generateCompletionReportsFilterSummary($config) {
        $filterSummary = [];

        if (!empty($config['date_from']) && !empty($config['date_to'])) {
            $filterSummary[] = "Date Range: " . $config['date_from'] . " to " . $config['date_to'];
        }
        if (!empty($config['course_id']) && $config['course_id'] !== '') {
            $filterSummary[] = "Course: " . $config['course_id'];
        }

        return !empty($filterSummary) ? implode(', ', $filterSummary) : 'No filters applied';
    }
    
    /**
     * Generate Engagement Monitoring PDF HTML
     */
    private function generateEngagementMonitoringPDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle) {
        // Get confidentiality level
        $confidentiality = $config['confidentiality'] ?? 'internal';
        $confidentialityText = [
            'internal' => 'INTERNAL USE ONLY',
            'confidential' => 'CONFIDENTIAL',
            'restricted' => 'RESTRICTED ACCESS',
            'public' => 'PUBLIC REPORT'
        ][$confidentiality];

        // Admin info level
        $adminInfoLevel = $config['admin_info_level'] ?? 'name_only';
        $adminInfo = '';
        switch ($adminInfoLevel) {
            case 'name_only':
                $adminInfo = htmlspecialchars($admin['username']);
                break;
            case 'name_role':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ')';
                break;
            case 'full_details':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ') - ' . $config['company_email'];
                break;
            case 'anonymous':
                $adminInfo = 'System Administrator';
                break;
        }

        $html = '
        <style>
            body {
                font-family: "DejaVu Sans", Arial, sans-serif;
                font-size: 8pt;
                line-height: 1.2;
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
                font-size: 7pt;
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
                width: 18%;
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
                margin: 0 auto;
            }

            .summary-table td {
                padding: 4px 2px;
                text-align: center;
                border-right: 1px solid #cbd5e1;
            }

            .summary-table td:last-child {
                border-right: none;
            }

            .metric-value {
                font-size: 10pt;
                font-weight: bold;
                color: #0369a1;
                display: block;
                margin-bottom: 1px;
            }

            .metric-label {
                font-size: 6pt;
                color: #64748b;
                display: block;
                font-weight: normal;
                line-height: 1.1;
            }

            .data-section-title {
                font-size: 10pt;
                font-weight: bold;
                color: #1e293b;
                margin: 8px 0 6px 0;
                padding: 4px 0;
                border-bottom: 1px solid #e2e8f0;
            }

            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 6px;
                font-size: 7pt;
            }

            .data-table th {
                background-color: #f8fafc;
                color: #374151;
                font-weight: bold;
                padding: 4px 2px;
                text-align: left;
                border-bottom: 2px solid #cbd5e1;
                font-size: 6pt;
                white-space: nowrap;
            }

            .data-table td {
                padding: 3px 2px;
                border-bottom: 1px solid #e5e7eb;
                vertical-align: top;
            }

            .data-table tr:nth-child(even) {
                background-color: #f8fafc;
            }

            .data-table tr:hover {
                background-color: #e2e8f0;
            }

            .totals-row {
                background-color: #fef3c7 !important;
                font-weight: bold;
            }

            .totals-row td {
                border-top: 2px solid #f59e0b;
                border-bottom: 2px solid #f59e0b;
            }
        </style>

        <!-- Metadata Section -->
        <div class="metadata-section">
            <table class="metadata-table">
                <tr>
                    <td class="metadata-label">Generated By:</td>
                    <td>' . $adminInfo . '</td>
                    <td class="metadata-label">Report ID:</td>
                    <td>' . $reportId . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Generated On:</td>
                    <td>' . date('M j, Y g:i A T') . '</td>
                    <td class="metadata-label">Confidentiality:</td>
                    <td style="color:#dc2626;font-weight:bold;">' . $confidentialityText . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Filters Applied:</td>
                    <td colspan="3">' . htmlspecialchars($filterText) . '</td>
                </tr>
            </table>
        </div>

        <!-- Executive Summary -->
        <div class="executive-summary">
            <div class="summary-title">EXECUTIVE SUMMARY</div>
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['login_frequency'] ?? 0, 1) . '</div>
                        <div class="metric-label">Login Frequency (per week)</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['dropoff_rate'] ?? 0, 1) . '%</div>
                        <div class="metric-label">Drop-off Rate</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['avg_enrollment_days'] ?? 0, 1) . '</div>
                        <div class="metric-label">Avg Enrollment Days</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['recent_enrollments'] ?? 0) . '</div>
                        <div class="metric-label">Recent Enrollments</div>
                    </td>
                </tr>
            </table>
        </div>';

        // Add most engaged students table if data exists
        if (!empty($data['most_engaged'])) {
            $html .= '<h3 class="data-section-title">MOST ENGAGED STUDENTS</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Enrolled Courses</th>
                        <th>Avg Enrollment Days</th>
                        <th>Last Enrollment</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($data['most_engaged'] as $student) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($student['username'] ?? '') . '</td>';
                $html .= '<td>' . number_format($student['enrolled_courses'] ?? 0) . '</td>';
                $html .= '<td>' . number_format($student['avg_enrollment_days'] ?? 0, 1) . '</td>';
                $html .= '<td>' . date('M j, Y', strtotime($student['last_enrollment'] ?? '')) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }

        // Add recent enrollments table if data exists
        if (!empty($data['recent_enrollments'])) {
            $html .= '<h3 class="data-section-title">RECENT ENROLLMENTS</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Enrolled At</th>
                        <th>Time Ago</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($data['recent_enrollments'] as $enrollment) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($enrollment['username'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($enrollment['course_title'] ?? '') . '</td>';
                $html .= '<td>' . date('M j, Y', strtotime($enrollment['enrolled_at'] ?? '')) . '</td>';
                $html .= '<td>' . htmlspecialchars($enrollment['time_ago'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }

        // Add course engagement data if exists
        if (!empty($data['course_engagement'])) {
            $html .= '<h3 class="data-section-title">COURSE ENGAGEMENT ANALYSIS</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Enrollment Count</th>
                        <th>Avg Enrollment Days</th>
                        <th>Recent Enrollments</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($data['course_engagement'] as $course) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($course['title'] ?? '') . '</td>';
                $html .= '<td>' . number_format($course['enrollment_count'] ?? 0) . '</td>';
                $html .= '<td>' . number_format($course['avg_enrollment_days'] ?? 0, 1) . '</td>';
                $html .= '<td>' . number_format($course['recent_enrollments'] ?? 0) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }

        return $html;
    }
    
    /**
     * Generate Completion Reports PDF HTML
     */
    private function generateCompletionReportsPDFHTML($config, $admin, $reportId, $filterText, $summaryStats, $data, $reportTitle) {
        // Get confidentiality level
        $confidentiality = $config['confidentiality'] ?? 'internal';
        $confidentialityText = [
            'internal' => 'INTERNAL USE ONLY',
            'confidential' => 'CONFIDENTIAL',
            'restricted' => 'RESTRICTED ACCESS',
            'public' => 'PUBLIC REPORT'
        ][$confidentiality];

        // Admin info level
        $adminInfoLevel = $config['admin_info_level'] ?? 'name_only';
        $adminInfo = '';
        switch ($adminInfoLevel) {
            case 'name_only':
                $adminInfo = htmlspecialchars($admin['username']);
                break;
            case 'name_role':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ')';
                break;
            case 'full_details':
                $adminInfo = htmlspecialchars($admin['username']) . ' (' . ucfirst($admin['role']) . ') - ' . $config['company_email'];
                break;
            case 'anonymous':
                $adminInfo = 'System Administrator';
                break;
        }

        $html = '
        <style>
            body {
                font-family: "DejaVu Sans", Arial, sans-serif;
                font-size: 8pt;
                line-height: 1.2;
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
                font-size: 7pt;
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
                width: 18%;
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
                margin: 0 auto;
            }

            .summary-table td {
                padding: 4px 2px;
                text-align: center;
                border-right: 1px solid #cbd5e1;
            }

            .summary-table td:last-child {
                border-right: none;
            }

            .metric-value {
                font-size: 10pt;
                font-weight: bold;
                color: #0369a1;
                display: block;
                margin-bottom: 1px;
            }

            .metric-label {
                font-size: 6pt;
                color: #64748b;
                display: block;
                font-weight: normal;
                line-height: 1.1;
            }

            .data-section-title {
                font-size: 10pt;
                font-weight: bold;
                color: #1e293b;
                margin: 8px 0 6px 0;
                padding: 4px 0;
                border-bottom: 1px solid #e2e8f0;
            }

            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 6px;
                font-size: 7pt;
            }

            .data-table th {
                background-color: #f8fafc;
                color: #374151;
                font-weight: bold;
                padding: 4px 2px;
                text-align: left;
                border-bottom: 2px solid #cbd5e1;
                font-size: 6pt;
                white-space: nowrap;
            }

            .data-table td {
                padding: 3px 2px;
                border-bottom: 1px solid #e5e7eb;
                vertical-align: top;
            }

            .data-table tr:nth-child(even) {
                background-color: #f8fafc;
            }

            .data-table tr:hover {
                background-color: #e2e8f0;
            }

            .totals-row {
                background-color: #fef3c7 !important;
                font-weight: bold;
            }

            .totals-row td {
                border-top: 2px solid #f59e0b;
                border-bottom: 2px solid #f59e0b;
            }
        </style>

        <!-- Metadata Section -->
        <div class="metadata-section">
            <table class="metadata-table">
                <tr>
                    <td class="metadata-label">Generated By:</td>
                    <td>' . $adminInfo . '</td>
                    <td class="metadata-label">Report ID:</td>
                    <td>' . $reportId . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Generated On:</td>
                    <td>' . date('M j, Y g:i A T') . '</td>
                    <td class="metadata-label">Confidentiality:</td>
                    <td style="color:#dc2626;font-weight:bold;">' . $confidentialityText . '</td>
                </tr>
                <tr>
                    <td class="metadata-label">Filters Applied:</td>
                    <td colspan="3">' . htmlspecialchars($filterText) . '</td>
                </tr>
            </table>
        </div>

        <!-- Executive Summary -->
        <div class="executive-summary">
            <div class="summary-title">EXECUTIVE SUMMARY</div>
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['overall_stats']['completion_rate'] ?? 0, 1) . '%</div>
                        <div class="metric-label">Overall Completion Rate</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['progress_stats']['avg_progress'] ?? 0, 1) . '%</div>
                        <div class="metric-label">Average Progress</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['timeliness_data']['on_time_completions'] ?? 0) . '</div>
                        <div class="metric-label">On-time Completions</div>
                    </td>
                    <td>
                        <div class="metric-value">' . number_format($summaryStats['timeliness_data']['delayed_completions'] ?? 0) . '</div>
                        <div class="metric-label">Delayed Completions</div>
                    </td>
                </tr>
            </table>
        </div>';

        // Add module breakdown table if data exists
        if (!empty($data['module_breakdown'])) {
            $html .= '<h3 class="data-section-title">MODULE COMPLETION BREAKDOWN</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Enrollments</th>
                        <th>Completed</th>
                        <th>Completion Rate</th>
                        <th>Avg Progress %</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($data['module_breakdown'] as $module) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($module['title'] ?? '') . '</td>';
                $html .= '<td>' . number_format($module['total_enrollments'] ?? 0) . '</td>';
                $html .= '<td>' . number_format($module['completed_enrollments'] ?? 0) . '</td>';
                $html .= '<td>' . number_format($module['completion_rate'] ?? 0, 1) . '%</td>';
                $html .= '<td>' . number_format($module['avg_progress_percentage'] ?? 0, 1) . '%</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }

        // Add completion timeline data if exists
        if (!empty($data['completion_trends'])) {
            $html .= '<h3 class="data-section-title">COMPLETION TIMELINE (MONTHLY SUMMARY)</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Enrollments</th>
                        <th>Completions</th>
                        <th>Completion Rate</th>
                    </tr>
                </thead>
                <tbody>';
            
            // Group by month
            $monthlyData = [];
            foreach ($data['completion_trends'] as $trend) {
                $month = date('Y-m', strtotime($trend['enrollment_date']));
                if (!isset($monthlyData[$month])) {
                    $monthlyData[$month] = [
                        'month' => $month,
                        'enrollments' => 0,
                        'completions' => 0
                    ];
                }
                $monthlyData[$month]['enrollments'] += $trend['daily_enrollments'];
                $monthlyData[$month]['completions'] += $trend['daily_completions'];
            }
            
            foreach ($monthlyData as $monthData) {
                $completionRate = $monthData['enrollments'] > 0 ? 
                    round(($monthData['completions'] / $monthData['enrollments']) * 100, 1) : 0;
                
                $html .= '<tr>';
                $html .= '<td>' . date('M Y', strtotime($monthData['month'] . '-01')) . '</td>';
                $html .= '<td>' . number_format($monthData['enrollments']) . '</td>';
                $html .= '<td>' . number_format($monthData['completions']) . '</td>';
                $html .= '<td>' . $completionRate . '%</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }

        return $html;
    }
}

// Example usage for other modules:
/*
// Initialize the report generator
$reportGenerator = new ReportGenerator($pdo);

// Usage Analytics Report
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $filters = [
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'view_type' => $_GET['view_type'] ?? 'daily',
        'role_filter' => $_GET['role_filter'] ?? '',
        'export_columns' => $_GET['export_columns'] ?? ['date', 'total_active', 'students', 'teachers', 'admins'],
        'confidentiality' => $_GET['confidentiality'] ?? 'internal',
        'admin_info_level' => $_GET['admin_info_level'] ?? 'name_only'
    ];
    
    $reportGenerator->generateUsageAnalyticsReport($filters, $_GET['export']);
    exit;
}

// Custom Report Example with Fixed Header & Footer
$customData = [
    ['date' => '2025-09-01', 'total_active' => 150, 'unique_students' => 120, 'unique_teachers' => 25, 'unique_admins' => 5],
    ['date' => '2025-09-02', 'total_active' => 175, 'unique_students' => 140, 'unique_teachers' => 30, 'unique_admins' => 5],
    ['date' => '2025-09-03', 'total_active' => 165, 'unique_students' => 135, 'unique_teachers' => 25, 'unique_admins' => 5],
    ['date' => '2025-09-04', 'total_active' => 185, 'unique_students' => 155, 'unique_teachers' => 25, 'unique_admins' => 5]
    // Works with any amount of data - header/footer stay fixed!
];

$config = [
    'export_columns' => ['date', 'total_active', 'students', 'teachers', 'admins'],
    'confidentiality' => 'internal',
    'report_purpose' => 'weekly_summary',
    'admin_info_level' => 'name_role',
    'company_name' => 'AIToManabi Learning Platform',
    'company_email' => 'aitomanabilms@gmail.com',
    'company_website' => 'www.aitomanabi.com'
];

// The generated PDF will have:
// ✅ Fixed header with company logo and report info
// ✅ Fixed footer with contact info and "Generated by [System] | Time: [timestamp]"
// ✅ Responsive design for mobile/desktop
// ✅ Professional styling regardless of data size
// ✅ Content scrolls between fixed header and footer
$reportGenerator->exportToPDF($customData, $config, [], 'WEEKLY SUMMARY REPORT');

// For Testing the Fixed Layout with Different Data Sizes:
// Test with 2 rows: Header/Footer stay fixed
// Test with 200 rows: Header/Footer stay fixed, content scrolls
// Test on mobile: Header/Footer adapt responsively
*/
?>
