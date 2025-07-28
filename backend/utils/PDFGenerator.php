<?php
/**
 * PDF Generator for Purchase Receipts
 * QuickCardsGH System
 * By Lamstech Solutions
 */

require_once __DIR__ . '/../config/database.php';

class PDFGenerator {
    private $companyName;
    private $siteName;
    private $contactInfo;
    
    public function __construct() {
        $this->companyName = COMPANY_NAME;
        $this->siteName = SITE_NAME;
        $this->contactInfo = [
            'phone' => CONTACT_PHONE,
            'email' => CONTACT_EMAIL,
            'whatsapp' => CONTACT_WHATSAPP
        ];
    }
    
    /**
     * Generate PDF receipt for purchase
     */
    public function generatePurchaseReceipt($purchaseData, $pincodes) {
        try {
            $filename = 'receipt_' . $purchaseData['reference_code'] . '_' . date('Ymd_His') . '.pdf';
            $filepath = PDF_OUTPUT_PATH . $filename;
            
            // Create HTML content for PDF
            $html = $this->generateReceiptHTML($purchaseData, $pincodes);
            
            // Convert HTML to PDF (using simple HTML to PDF conversion)
            $this->htmlToPDF($html, $filepath);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'download_url' => '/backend/uploads/pdf/' . $filename
            ];
            
        } catch (Exception $e) {
            error_log("PDFGenerator error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate HTML content for receipt
     */
    private function generateReceiptHTML($purchaseData, $pincodes) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Receipt - ' . $purchaseData['reference_code'] . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 5px;
        }
        .site-name {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }
        .contact-info {
            font-size: 12px;
            color: #888;
        }
        .receipt-title {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin: 30px 0;
            color: #1e3a8a;
        }
        .purchase-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .pincodes-section {
            margin-top: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .pincode-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .pincode-header {
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 8px;
        }
        .pincode-details {
            font-family: monospace;
            font-size: 14px;
        }
        .pincode-row {
            margin-bottom: 5px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .important-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .qr-section {
            text-align: center;
            margin: 20px 0;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">' . $this->companyName . '</div>
        <div class="site-name">' . $this->siteName . '</div>
        <div class="contact-info">
            Phone: ' . $this->contactInfo['phone'] . ' | 
            Email: ' . $this->contactInfo['email'] . ' | 
            WhatsApp: ' . $this->contactInfo['whatsapp'] . '
        </div>
    </div>
    
    <div class="receipt-title">PURCHASE RECEIPT</div>
    
    <div class="purchase-info">
        <div class="info-row">
            <span class="info-label">Reference Code:</span>
            <span class="info-value">' . $purchaseData['reference_code'] . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Service Type:</span>
            <span class="info-value">' . $purchaseData['service_name'] . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Phone Number:</span>
            <span class="info-value">' . $purchaseData['phone_number'] . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Quantity:</span>
            <span class="info-value">' . $purchaseData['quantity'] . ' checker(s)</span>
        </div>
        <div class="info-row">
            <span class="info-label">Amount Paid:</span>
            <span class="info-value">' . CURRENCY_SYMBOL . ' ' . number_format($purchaseData['total_amount'], 2) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Purchase Date:</span>
            <span class="info-value">' . date('d/m/Y H:i:s', strtotime($purchaseData['created_at'])) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Status:</span>
            <span class="info-value">PAID</span>
        </div>
    </div>
    
    <div class="pincodes-section">
        <div class="section-title">YOUR CHECKER CODES</div>';
        
        foreach ($pincodes as $index => $pincode) {
            $html .= '
        <div class="pincode-item">
            <div class="pincode-header">Checker ' . ($index + 1) . '</div>
            <div class="pincode-details">
                <div class="pincode-row"><strong>Serial Number:</strong> ' . $pincode['serial_number'] . '</div>
                <div class="pincode-row"><strong>PIN Code:</strong> ' . $pincode['pin_code'] . '</div>';
            
            if (!empty($pincode['voucher_code'])) {
                $html .= '<div class="pincode-row"><strong>Voucher Code:</strong> ' . $pincode['voucher_code'] . '</div>';
            }
            
            $html .= '
            </div>
        </div>';
        }
        
        $html .= '
    </div>
    
    <div class="important-note">
        <strong>IMPORTANT:</strong>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>Keep this receipt safe for your records</li>
            <li>Use your Reference Code (' . $purchaseData['reference_code'] . ') to retrieve these codes later</li>
            <li>These codes are valid for checking your examination results</li>
            <li>Contact us if you have any issues with your codes</li>
        </ul>
    </div>
    
    <div class="footer">
        <p>Thank you for choosing ' . $this->siteName . '</p>
        <p>Powered by ' . $this->companyName . '</p>
        <p>Generated on ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Convert HTML to PDF (Simple implementation)
     * In production, use libraries like TCPDF, FPDF, or wkhtmltopdf
     */
    private function htmlToPDF($html, $filepath) {
        // For now, we'll save as HTML file that can be printed as PDF
        // In production, integrate with a proper PDF library
        
        // Simple HTML to PDF conversion using DomPDF-like approach
        file_put_contents($filepath . '.html', $html);
        
        // If you have wkhtmltopdf installed, you can use:
        // exec("wkhtmltopdf {$filepath}.html {$filepath}");
        
        // For now, we'll create a simple text-based PDF-like file
        $this->createSimplePDF($html, $filepath);
        
        // Clean up HTML file
        if (file_exists($filepath . '.html')) {
            unlink($filepath . '.html');
        }
    }
    
    /**
     * Create a simple PDF-like file (fallback method)
     */
    private function createSimplePDF($html, $filepath) {
        // Strip HTML tags and create a formatted text version
        $text = strip_tags($html);
        $text = html_entity_decode($text);
        
        // Add PDF header (simplified)
        $pdfContent = "%PDF-1.4\n";
        $pdfContent .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdfContent .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdfContent .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n";
        $pdfContent .= "4 0 obj\n<< /Length " . strlen($text) . " >>\nstream\n";
        $pdfContent .= "BT /F1 12 Tf 50 750 Td\n";
        
        // Add text content (simplified)
        $lines = explode("\n", wordwrap($text, 80));
        $yPos = 750;
        foreach ($lines as $line) {
            if ($yPos < 50) break; // Prevent overflow
            $pdfContent .= "(" . addslashes(trim($line)) . ") Tj 0 -15 Td\n";
            $yPos -= 15;
        }
        
        $pdfContent .= "ET\nendstream\nendobj\n";
        $pdfContent .= "xref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000207 00000 n \n";
        $pdfContent .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n" . strlen($pdfContent) . "\n%%EOF";
        
        file_put_contents($filepath, $pdfContent);
    }
    
    /**
     * Generate bulk receipt for multiple purchases
     */
    public function generateBulkReceipts($purchases) {
        $results = [];
        
        foreach ($purchases as $purchase) {
            $result = $this->generatePurchaseReceipt($purchase['data'], $purchase['pincodes']);
            $results[] = [
                'reference_code' => $purchase['data']['reference_code'],
                'result' => $result
            ];
        }
        
        return $results;
    }
    
    /**
     * Clean up old PDF files
     */
    public function cleanupOldPDFs($daysOld = 30) {
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        $cleaned = 0;
        
        if (is_dir(PDF_OUTPUT_PATH)) {
            $files = scandir(PDF_OUTPUT_PATH);
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $filepath = PDF_OUTPUT_PATH . $file;
                if (is_file($filepath) && filemtime($filepath) < $cutoffTime) {
                    if (unlink($filepath)) {
                        $cleaned++;
                    }
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Get PDF file info
     */
    public function getPDFInfo($filename) {
        $filepath = PDF_OUTPUT_PATH . $filename;
        
        if (!file_exists($filepath)) {
            return null;
        }
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
            'created' => filemtime($filepath),
            'download_url' => '/backend/uploads/pdf/' . $filename
        ];
    }
}
?>
