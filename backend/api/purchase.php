<?php
/**
 * Purchase API Endpoint
 * Handles purchase requests for checkers
 * QuickCardsGH System
 * By Lamstech Solutions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Checker.php';
require_once __DIR__ . '/../models/PincodeInventory.php';
require_once __DIR__ . '/../models/PurchaseReference.php';
require_once __DIR__ . '/../utils/PaymentProcessor.php';
require_once __DIR__ . '/../utils/SMSHandler.php';
require_once __DIR__ . '/../utils/PDFGenerator.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $requiredFields = ['service_type_id', 'quantity', 'phone_number', 'momo_provider_id'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
            exit();
        }
    }
    
    $serviceTypeId = (int)$input['service_type_id'];
    $examTypeId = isset($input['exam_type_id']) ? (int)$input['exam_type_id'] : null;
    $quantity = (int)$input['quantity'];
    $phoneNumber = $input['phone_number'];
    $momoProviderId = (int)$input['momo_provider_id'];
    
    // Validate phone number format
    if (!preg_match('/^0[2-9]\d{8}$/', $phoneNumber)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
        exit();
    }
    
    // Validate quantity
    if ($quantity < 1 || $quantity > 200) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid quantity. Must be between 1 and 200']);
        exit();
    }
    
    // Check inventory availability
    $inventoryModel = new PincodeInventory();
    $availablePincodes = $inventoryModel->getAvailablePincodes($serviceTypeId, $examTypeId, $quantity);
    
    if (count($availablePincodes) < $quantity) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Insufficient inventory. Only ' . count($availablePincodes) . ' checkers available.'
        ]);
        exit();
    }
    
    // Get service information and pricing
    $database = new Database();
    $db = $database->getConnection();
    
    $serviceQuery = "SELECT st.*, et.name as exam_name 
                     FROM service_types st
                     LEFT JOIN exam_types et ON et.id = :exam_type_id
                     WHERE st.id = :service_type_id AND st.status = 'active'";
    
    $stmt = $db->prepare($serviceQuery);
    $stmt->bindParam(':service_type_id', $serviceTypeId);
    $stmt->bindParam(':exam_type_id', $examTypeId);
    $stmt->execute();
    
    $service = $stmt->fetch();
    if (!$service) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Service not found or inactive']);
        exit();
    }
    
    $unitPrice = $service['selling_price'];
    $totalAmount = $quantity * $unitPrice;
    
    // Initialize classes
    $paymentProcessor = new PaymentProcessor();
    $smsHandler = new SMSHandler();
    $checkerModel = new Checker();
    $transactionModel = new Transaction();
    $referenceModel = new PurchaseReference();
    
    // Create transaction data
    $transactionData = [
        'service_type_id' => $serviceTypeId,
        'exam_type_id' => $examTypeId,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'total_amount' => $totalAmount,
        'phone_number' => $phoneNumber,
        'momo_provider_id' => $momoProviderId,
        'payment_status' => 'pending',
        'customer_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    
    // Process payment initialization
    $paymentResult = $paymentProcessor->initializePayment(array_merge($input, [
        'service_name' => $service['name'],
        'total_amount' => $totalAmount
    ]));
    
    if (!$paymentResult['success']) {
        http_response_code(400);
        echo json_encode($paymentResult);
        exit();
    }
    
    // Create transaction record
    $transactionId = $transactionModel->createTransaction($transactionData);
    if (!$transactionId) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create transaction']);
        exit();
    }
    
    // Create purchase reference
    $purchaseReference = $referenceModel->createReference($transactionId, $phoneNumber, $serviceTypeId, $quantity, $totalAmount);
    if (!$purchaseReference) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create purchase reference']);
        exit();
    }
    
    // Simulate payment processing delay and completion
    // In production, this would be handled by webhooks from payment provider
    sleep(2); // Simulate processing time
    
    // Mock payment completion (80% success rate)
    $paymentCompleted = mt_rand(1, 10) <= 8;
    
    if ($paymentCompleted) {
        // Update payment status to completed
        $transactionModel->updatePaymentStatus($transactionId, 'completed', $paymentResult['transaction_id']);
        
        // Reserve pincodes from inventory
        $pincodeIds = array_column($availablePincodes, 'id');
        $reserveResult = $inventoryModel->markAsSold($pincodeIds, $phoneNumber, $purchaseReference);
        
        if (!$reserveResult) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to reserve pincodes']);
            exit();
        }
        
        // Create checker records
        $checkers = [];
        foreach ($availablePincodes as $pincode) {
            $checkerData = [
                'transaction_id' => $transactionId,
                'inventory_id' => $pincode['id'],
                'purchase_reference' => $purchaseReference,
                'checker_code' => 'CHK' . $purchaseReference . sprintf('%03d', count($checkers) + 1),
                'serial_number' => $pincode['serial_number'],
                'pin_code' => $pincode['pin_code'],
                'voucher_code' => $pincode['voucher_code'],
                'status' => 'active',
                'expires_at' => $pincode['expires_at']
            ];
            
            $checker = $checkerModel->create($checkerData);
            if ($checker) {
                $checkers[] = array_merge($checkerData, ['id' => $checker]);
            }
        }
        
        if (!empty($checkers)) {
            // Prepare purchase data for PDF and SMS
            $purchaseData = [
                'reference_code' => $purchaseReference,
                'service_name' => $service['name'],
                'exam_name' => $service['exam_name'],
                'phone_number' => $phoneNumber,
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'created_at' => date(DATE_FORMAT)
            ];
            
            // Generate PDF receipt
            $pdfGenerator = new PDFGenerator();
            $pdfResult = $pdfGenerator->generatePurchaseReceipt($purchaseData, $checkers);
            
            // Send checkers via SMS
            $smsMessage = formatCheckersForSMS($checkers, $purchaseReference, $service['name']);
            $smsResult = $smsHandler->sendSMS($phoneNumber, $smsMessage);
            
            // Get transaction details for response
            $transaction = $transactionModel->findById($transactionId);
            
            // Prepare response
            $response = [
                'success' => true,
                'message' => 'Purchase completed successfully',
                'transaction_id' => $paymentResult['transaction_id'],
                'print_id' => $paymentResult['print_id'],
                'purchase_reference' => $purchaseReference,
                'checkers' => [
                    'service_name' => $service['name'],
                    'exam_name' => $service['exam_name'],
                    'quantity' => $quantity,
                    'checkers' => $checkers
                ],
                'sms_sent' => $smsResult['success'],
                'pdf_generated' => $pdfResult['success'],
                'pdf_download_url' => $pdfResult['success'] ? $pdfResult['download_url'] : null
            ];
            
            echo json_encode($response);
        } else {
            // Failed to generate checkers
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Payment completed but failed to generate checkers. Please contact support.',
                'transaction_id' => $paymentResult['transaction_id']
            ]);
        }
    } else {
        // Payment failed
        $transactionModel->updatePaymentStatus($transactionId, 'failed');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Payment failed. Please check your mobile money account and try again.',
            'transaction_id' => $paymentResult['transaction_id']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Purchase API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your purchase. Please try again.'
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
