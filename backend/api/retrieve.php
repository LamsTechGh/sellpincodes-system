<?php
/**
 * Retrieve API Endpoint
 * Handles retrieval of old checkers using purchase reference
 * QuickCardsGH System
 * By Lamstech Solutions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Checker.php';
require_once __DIR__ . '/../models/PurchaseReference.php';
require_once __DIR__ . '/../models/PincodeInventory.php';
require_once __DIR__ . '/../utils/SMSHandler.php';
require_once __DIR__ . '/../utils/PDFGenerator.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Updated to use reference_code instead of transaction_id
    if (empty($input['phone_number']) || empty($input['reference_code'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Phone number and reference code are required'
        ]);
        exit();
    }
    
    if (!preg_match('/^0[2-9]\d{8}$/', $input['phone_number'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid phone number format'
        ]);
        exit();
    }
    
    $phoneNumber = $input['phone_number'];
    $referenceCode = strtoupper(trim($input['reference_code']));
    $resendSMS = isset($input['resend_sms']) && $input['resend_sms'] === true;
    
    // Initialize models
    $referenceModel = new PurchaseReference();
    $inventoryModel = new PincodeInventory();
    $transactionModel = new Transaction();
    $checkerModel = new Checker();
    $smsHandler = new SMSHandler();
    
    // Validate reference code format
    if (!$referenceModel->validateReferenceFormat($referenceCode)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid reference code format.'
        ]);
        exit();
    }
    
    // Find purchase reference
    $reference = $referenceModel->findByReferenceAndPhone($referenceCode, $phoneNumber);
    
    if (!$reference) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Purchase reference not found or phone number does not match.'
        ]);
        exit();
    }
    
    // Check if reference is still active
    if ($reference['status'] !== 'active') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Purchase reference is no longer active. Status: ' . $reference['status']
        ]);
        exit();
    }
    
    // Check if reference has expired
    if ($reference['expires_at'] && strtotime($reference['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Purchase reference has expired.'
        ]);
        exit();
    }
    
    // Get pincodes from inventory using purchase reference
    $pincodes = $inventoryModel->getByPurchaseReference($referenceCode);
    
    if (empty($pincodes)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No checkers found for this purchase reference.'
        ]);
        exit();
    }
    
    // Get transaction details
    $transaction = $transactionModel->findById($reference['transaction_id']);
    
    // Prepare checkers data
    $checkers = [];
    foreach ($pincodes as $pincode) {
        $checkers[] = [
            'serial_number' => $pincode['serial_number'],
            'pin_code' => $pincode['pin_code'],
            'voucher_code' => $pincode['voucher_code'],
            'status' => $pincode['status'],
            'expires_at' => $pincode['expires_at']
        ];
    }
    
    // Prepare purchase data for PDF regeneration
    $purchaseData = [
        'reference_code' => $referenceCode,
        'service_name' => $reference['service_name'],
        'phone_number' => $phoneNumber,
        'quantity' => count($checkers),
        'total_amount' => $reference['total_amount'],
        'created_at' => $reference['created_at']
    ];
    
    // Generate new PDF receipt
    $pdfGenerator = new PDFGenerator();
    $pdfResult = $pdfGenerator->generatePurchaseReceipt($purchaseData, $checkers);
    
    // Resend SMS if requested
    $smsResult = null;
    if ($resendSMS) {
        $smsContent = formatCheckersForSMS($checkers, $referenceCode, $reference['service_name']);
        $smsResult = $smsHandler->sendSMS($phoneNumber, $smsContent);
    }
    
    $response = [
        'success' => true,
        'message' => 'Checkers retrieved successfully',
        'transaction' => [
            'reference_code' => $referenceCode,
            'transaction_id' => $transaction['transaction_id'] ?? '',
            'print_id' => $transaction['print_id'] ?? '',
            'service_name' => $reference['service_name'],
            'quantity' => count($checkers),
            'total_amount' => $reference['total_amount'],
            'payment_status' => 'completed',
            'created_at' => $reference['created_at']
        ],
        'checkers' => [
            'service_name' => $reference['service_name'],
            'quantity' => count($checkers),
            'checkers' => $checkers
        ],
        'pdf_generated' => $pdfResult['success'],
        'pdf_download_url' => $pdfResult['success'] ? $pdfResult['download_url'] : null
    ];
    
    if ($smsResult) {
        $response['sms_resent'] = $smsResult['success'];
        $response['sms_message'] = $smsResult['message'] ?? 'SMS sent successfully';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Retrieve API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving your checkers. Please try again.'
    ]);
}

/**
 * Format checkers for SMS
 */
function formatCheckersForSMS($checkers, $purchaseReference, $serviceName) {
    $message = "QuickCards Ghana - Your {$serviceName} Checkers:\n\n";
    $message .= "Reference: {$purchaseReference}\n\n";
    
    foreach ($checkers as $index => $checker) {
        $message .= ($index + 1) . ". Serial: " . $checker['serial_number'] . "\n";
        $message .= "   PIN: " . $checker['pin_code'] . "\n";
        if (!empty($checker['voucher_code'])) {
            $message .= "   Voucher: " . $checker['voucher_code'] . "\n";
        }
        $message .= "\n";
    }
    
    $message .= "Use Reference Code to retrieve later.\n";
    $message .= "Thank you! - Lamstech Solutions";
    
    return $message;
}
?>
