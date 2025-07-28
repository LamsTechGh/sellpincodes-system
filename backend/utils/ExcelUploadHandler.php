<?php
/**
 * Excel Upload Handler
 * QuickCardsGH System
 * By Lamstech Solutions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PincodeInventory.php';
require_once __DIR__ . '/../models/BatchUpload.php';

class ExcelUploadHandler {
    private $db;
    private $pincodeInventory;
    private $batchUpload;
    private $allowedExtensions = ['xlsx', 'xls', 'csv'];
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->pincodeInventory = new PincodeInventory();
        $this->batchUpload = new BatchUpload();
    }
    
    /**
     * Handle Excel file upload and processing
     */
    public function handleUpload($file, $serviceTypeId, $examTypeId, $uploadedBy) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // Create batch record
            $batchId = $this->batchUpload->createBatch(
                $serviceTypeId, 
                $examTypeId, 
                $file['name'], 
                $uploadedBy
            );
            
            if (!$batchId) {
                return [
                    'success' => false,
                    'message' => 'Failed to create batch record'
                ];
            }
            
            // Move uploaded file
            $uploadPath = EXCEL_UPLOAD_PATH . $batchId . '_' . $file['name'];
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $this->batchUpload->markBatchAsFailed($batchId, 'Failed to move uploaded file');
                return [
                    'success' => false,
                    'message' => 'Failed to save uploaded file'
                ];
            }
            
            // Process the file
            $result = $this->processExcelFile($uploadPath, $serviceTypeId, $examTypeId, $batchId);
            
            // Update batch status
            $status = $result['failed'] > 0 ? 'completed' : 'completed';
            $notes = '';
            if (!empty($result['errors'])) {
                $notes = implode("\n", array_slice($result['errors'], 0, 10)); // First 10 errors
                if (count($result['errors']) > 10) {
                    $notes .= "\n... and " . (count($result['errors']) - 10) . " more errors";
                }
            }
            
            $this->batchUpload->updateBatchProgress(
                $batchId,
                $result['successful'] + $result['failed'],
                $result['successful'],
                $result['failed'],
                $status,
                $notes
            );
            
            // Clean up uploaded file
            unlink($uploadPath);
            
            return [
                'success' => true,
                'message' => 'File processed successfully',
                'batch_id' => $batchId,
                'data' => $result
            ];
            
        } catch (Exception $e) {
            error_log("ExcelUploadHandler error: " . $e->getMessage());
            if (isset($batchId)) {
                $this->batchUpload->markBatchAsFailed($batchId, $e->getMessage());
            }
            return [
                'success' => false,
                'message' => 'An error occurred while processing the file: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => 'File upload error: ' . $this->getUploadErrorMessage($file['error'])
            ];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File size exceeds maximum allowed size of ' . ($this->maxFileSize / 1024 / 1024) . 'MB'
            ];
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'message' => 'Invalid file type. Allowed types: ' . implode(', ', $this->allowedExtensions)
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Process Excel file and extract data
     */
    private function processExcelFile($filePath, $serviceTypeId, $examTypeId, $batchId) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            return $this->processCSVFile($filePath, $serviceTypeId, $examTypeId, $batchId);
        } else {
            return $this->processExcelFileWithLibrary($filePath, $serviceTypeId, $examTypeId, $batchId);
        }
    }
    
    /**
     * Process CSV file
     */
    private function processCSVFile($filePath, $serviceTypeId, $examTypeId, $batchId) {
        $data = [];
        $errors = [];
        $rowNumber = 0;
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            // Skip header row
            $header = fgetcsv($handle, 1000, ",");
            
            // Validate header
            $expectedHeaders = ['serial_number', 'pin_code', 'voucher_code'];
            $headerMap = $this->mapHeaders($header, $expectedHeaders);
            
            if (!$headerMap['serial_number'] || !$headerMap['pin_code']) {
                return [
                    'successful' => 0,
                    'failed' => 0,
                    'errors' => ['Missing required columns: serial_number and pin_code are required']
                ];
            }
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowNumber++;
                
                if (count($row) < 2) {
                    $errors[] = "Row {$rowNumber}: Insufficient data";
                    continue;
                }
                
                $rowData = [
                    'serial_number' => trim($row[$headerMap['serial_number']] ?? ''),
                    'pin_code' => trim($row[$headerMap['pin_code']] ?? ''),
                    'voucher_code' => trim($row[$headerMap['voucher_code']] ?? '')
                ];
                
                // Validate row data
                if (empty($rowData['serial_number']) || empty($rowData['pin_code'])) {
                    $errors[] = "Row {$rowNumber}: Missing serial number or PIN code";
                    continue;
                }
                
                $data[] = $rowData;
            }
            fclose($handle);
        }
        
        // Import data to database
        return $this->pincodeInventory->importPincodes($data, $serviceTypeId, $examTypeId, $batchId);
    }
    
    /**
     * Process Excel file using a library (fallback to CSV-like processing)
     */
    private function processExcelFileWithLibrary($filePath, $serviceTypeId, $examTypeId, $batchId) {
        // For now, we'll implement a simple reader
        // In production, you might want to use PhpSpreadsheet or similar
        
        // Convert Excel to CSV temporarily for processing
        // This is a simplified approach - in production use proper Excel library
        return $this->processCSVFile($filePath, $serviceTypeId, $examTypeId, $batchId);
    }
    
    /**
     * Map CSV headers to expected column names
     */
    private function mapHeaders($headers, $expectedHeaders) {
        $map = [];
        
        foreach ($expectedHeaders as $expected) {
            $map[$expected] = null;
            
            foreach ($headers as $index => $header) {
                $normalizedHeader = strtolower(trim($header));
                $normalizedExpected = strtolower($expected);
                
                // Check for exact match or common variations
                if ($normalizedHeader === $normalizedExpected ||
                    $normalizedHeader === str_replace('_', ' ', $normalizedExpected) ||
                    $normalizedHeader === str_replace('_', '', $normalizedExpected)) {
                    $map[$expected] = $index;
                    break;
                }
                
                // Check for partial matches
                if (strpos($normalizedHeader, $normalizedExpected) !== false ||
                    strpos($normalizedExpected, $normalizedHeader) !== false) {
                    $map[$expected] = $index;
                    break;
                }
            }
        }
        
        return $map;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Generate sample Excel template
     */
    public function generateSampleTemplate() {
        $headers = ['serial_number', 'pin_code', 'voucher_code'];
        $sampleData = [
            ['2024001234567', 'ABC123DEF456', 'VCH001'],
            ['2024001234568', 'XYZ789GHI012', 'VCH002'],
            ['2024001234569', 'MNO345PQR678', 'VCH003']
        ];
        
        $filename = 'pincode_template_' . date('Ymd_His') . '.csv';
        $filepath = PDF_OUTPUT_PATH . $filename;
        
        $file = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($file, $headers);
        
        // Write sample data
        foreach ($sampleData as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath
        ];
    }
    
    /**
     * Validate pincode format
     */
    private function validatePincodeFormat($serialNumber, $pinCode) {
        // Basic validation - customize as needed
        if (strlen($serialNumber) < 8 || strlen($serialNumber) > 20) {
            return false;
        }
        
        if (strlen($pinCode) < 8 || strlen($pinCode) > 20) {
            return false;
        }
        
        // Check for alphanumeric characters
        if (!ctype_alnum($serialNumber) || !ctype_alnum($pinCode)) {
            return false;
        }
        
        return true;
    }
}
?>
