<?php
/**
 * Payment Processor
 * Handles Mobile Money payments
 * Sellpincodes System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Transaction.php';

class PaymentProcessor {
    private $db;
    private $transactionModel;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->transactionModel = new Transaction();
    }
    
    /**
     * Initialize payment
     */
    public function initializePayment($transactionData) {
        try {
            // Validate transaction data
            $validation = $this->validateTransactionData($transactionData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'errors' => $validation['errors']
                ];
            }
            
            // Create transaction record
            $transaction = $this->createTransaction($transactionData);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Failed to create transaction record'
                ];
            }
            
            // Process mobile money payment
            $paymentResult = $this->processMobileMoneyPayment($transaction);
            
            // Log payment attempt
            $this->logPaymentAttempt($transaction['id'], 'initialize', $transactionData, $paymentResult);
            
            if ($paymentResult['success']) {
                // Update transaction with payment reference
                $this->transactionModel->updatePaymentStatus(
                    $transaction['id'],
                    'processing',
                    $paymentResult['reference']
                );
                
                return [
                    'success' => true,
                    'message' => 'Payment initialized successfully',
                    'transaction_id' => $transaction['transaction_id'],
                    'print_id' => $transaction['print_id'],
                    'payment_reference' => $paymentResult['reference'],
                    'amount' => $transaction['total_amount']
                ];
            } else {
                // Update transaction as failed
                $this->transactionModel->updatePaymentStatus($transaction['id'], 'failed');
                
                return [
                    'success' => false,
                    'message' => $paymentResult['message']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Payment initialization error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment processing failed. Please try again.'
            ];
        }
    }
    
    /**
     * Validate transaction data
     */
    private function validateTransactionData($data) {
        $errors = [];
        
        // Required fields
        $required = ['service_type_id', 'quantity', 'phone_number', 'momo_provider_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Validate phone number format (Ghana)
        if (!empty($data['phone_number'])) {
            if (!preg_match('/^0[2-9]\d{8}$/', $data['phone_number'])) {
                $errors[] = 'Invalid phone number format';
            }
        }
        
        // Validate quantity
        if (!empty($data['quantity']) && (!is_numeric($data['quantity']) || $data['quantity'] < 1)) {
            $errors[] = 'Quantity must be a positive number';
        }
        
        // Validate service type exists
        if (!empty($data['service_type_id'])) {
            $stmt = $this->db->prepare("SELECT id FROM service_types WHERE id = ? AND status = 'active'");
            $stmt->execute([$data['service_type_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid service type';
            }
        }
        
        // Validate momo provider exists
        if (!empty($data['momo_provider_id'])) {
            $stmt = $this->db->prepare("SELECT id FROM momo_providers WHERE id = ? AND status = 'active'");
            $stmt->execute([$data['momo_provider_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid mobile money provider';
            }
        }
        
        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? 'Valid' : 'Validation failed',
            'errors' => $errors
        ];
    }
    
    /**
     * Create transaction record
     */
    private function createTransaction($data) {
        try {
            // Calculate pricing
            $pricing = $this->calculatePricing($data['service_type_id'], $data['quantity']);
            if (!$pricing) {
                throw new Exception('Unable to calculate pricing');
            }
            
            // Generate transaction and print IDs
            $transactionId = $this->transactionModel->generateTransactionId();
            $printId = $this->transactionModel->generatePrintId();
            
            // Set expiry (24 hours for payment)
            $expiresAt = date(DATE_FORMAT, strtotime('+24 hours'));
            
            $transactionData = [
                'transaction_id' => $transactionId,
                'print_id' => $printId,
                'service_type_id' => $data['service_type_id'],
                'exam_type_id' => $data['exam_type_id'] ?? null,
                'quantity' => $data['quantity'],
                'unit_price' => $pricing['unit_price'],
                'total_amount' => $pricing['total_price'],
                'phone_number' => $data['phone_number'],
                'momo_provider_id' => $data['momo_provider_id'],
                'payment_status' => 'pending',
                'customer_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'status' => 'active',
                'expires_at' => $expiresAt
            ];
            
            return $this->transactionModel->create($transactionData);
            
        } catch (Exception $e) {
            error_log("Create transaction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate pricing based on service type and quantity
     */
    private function calculatePricing($serviceTypeId, $quantity) {
        try {
            $query = "SELECT unit_price, total_price FROM pricing_tiers 
                      WHERE service_type_id = ? 
                      AND min_quantity <= ? 
                      AND (max_quantity >= ? OR max_quantity IS NULL)
                      AND status = 'active'
                      ORDER BY min_quantity DESC LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$serviceTypeId, $quantity, $quantity]);
            $pricing = $stmt->fetch();
            
            if (!$pricing) {
                return null;
            }
            
            // Calculate total based on quantity
            $totalPrice = $pricing['unit_price'] * $quantity;
            
            return [
                'unit_price' => $pricing['unit_price'],
                'total_price' => $totalPrice
            ];
            
        } catch (Exception $e) {
            error_log("Calculate pricing error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Process mobile money payment (Mock implementation)
     */
    private function processMobileMoneyPayment($transaction) {
        try {
            // Get provider details
            $stmt = $this->db->prepare("SELECT * FROM momo_providers WHERE id = ?");
            $stmt->execute([$transaction['momo_provider_id']]);
            $provider = $stmt->fetch();
            
            if (!$provider) {
                return [
                    'success' => false,
                    'message' => 'Invalid mobile money provider'
                ];
            }
            
            // Mock payment processing
            // In production, this would integrate with actual MoMo APIs
            $paymentData = [
                'amount' => $transaction['total_amount'],
                'phone_number' => $transaction['phone_number'],
                'provider' => $provider['code'],
                'reference' => 'PAY' . time() . mt_rand(1000, 9999),
                'description' => 'Payment for ' . $transaction['transaction_id']
            ];
            
            // Simulate API call delay
            usleep(500000); // 0.5 seconds
            
            // Mock success response (90% success rate)
            $success = mt_rand(1, 10) <= 9;
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Payment request sent successfully',
                    'reference' => $paymentData['reference'],
                    'provider_response' => json_encode([
                        'status' => 'pending',
                        'message' => 'Payment prompt sent to customer',
                        'reference' => $paymentData['reference']
                    ])
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Payment request failed. Please try again.',
                    'provider_response' => json_encode([
                        'status' => 'failed',
                        'message' => 'Insufficient balance or network error'
                    ])
                ];
            }
            
        } catch (Exception $e) {
            error_log("Mobile money payment error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment processing failed'
            ];
        }
    }
    
    /**
     * Verify payment status (Mock implementation)
     */
    public function verifyPayment($transactionId, $paymentReference) {
        try {
            // Find transaction
            $transaction = $this->transactionModel->findByTransactionId($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaction not found'
                ];
            }
            
            // Mock verification (simulate checking with MoMo provider)
            usleep(300000); // 0.3 seconds
            
            // Mock verification result (80% success rate for pending payments)
            $verified = mt_rand(1, 10) <= 8;
            
            if ($verified) {
                // Update transaction as completed
                $this->transactionModel->updatePaymentStatus(
                    $transaction['id'],
                    'completed',
                    $paymentReference,
                    'MOMO' . time() . mt_rand(1000, 9999)
                );
                
                return [
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'status' => 'completed'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Payment verification pending',
                    'status' => 'processing'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Payment verification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment verification failed'
            ];
        }
    }
    
    /**
     * Log payment attempt
     */
    private function logPaymentAttempt($transactionId, $action, $requestData, $responseData) {
        try {
            $query = "INSERT INTO payment_logs (transaction_id, action, request_data, response_data, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $transactionId,
                $action,
                json_encode($requestData),
                json_encode($responseData),
                $responseData['success'] ? 'success' : 'failed',
                date(DATE_FORMAT)
            ]);
            
        } catch (Exception $e) {
            error_log("Payment log error: " . $e->getMessage());
        }
    }
    
    /**
     * Process refund (Mock implementation)
     */
    public function processRefund($transactionId, $reason = 'Customer request') {
        try {
            $transaction = $this->transactionModel->findByTransactionId($transactionId);
            if (!$transaction || $transaction['payment_status'] !== 'completed') {
                return [
                    'success' => false,
                    'message' => 'Transaction not eligible for refund'
                ];
            }
            
            // Mock refund processing
            $refundReference = 'REF' . time() . mt_rand(1000, 9999);
            
            // Update transaction status
            $this->transactionModel->update($transaction['id'], [
                'status' => 'cancelled',
                'payment_status' => 'refunded'
            ]);
            
            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_reference' => $refundReference
            ];
            
        } catch (Exception $e) {
            error_log("Refund processing error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Refund processing failed'
            ];
        }
    }
}
?>
