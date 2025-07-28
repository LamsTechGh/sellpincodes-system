<?php
/**
 * SMS Handler
 * Handles SMS notifications
 * Sellpincodes System
 */

require_once __DIR__ . '/../config/database.php';

class SMSHandler {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Send SMS notification
     */
    public function sendSMS($phoneNumber, $message, $transactionId = null) {
        try {
            // Validate phone number
            if (!$this->validatePhoneNumber($phoneNumber)) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format'
                ];
            }
            
            // Log SMS attempt
            $smsLogId = $this->logSMSAttempt($transactionId, $phoneNumber, $message);
            
            // Send SMS via provider
            $result = $this->sendViaSMSProvider($phoneNumber, $message);
            
            // Update SMS log with result
            $this->updateSMSLog($smsLogId, $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("SMS sending error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMS sending failed'
            ];
        }
    }
    
    /**
     * Send checkers via SMS
     */
    public function sendCheckers($phoneNumber, $checkers, $transactionId = null) {
        try {
            if (empty($checkers)) {
                return [
                    'success' => false,
                    'message' => 'No checkers to send'
                ];
            }
            
            // Format checkers message
            $message = $this->formatCheckersMessage($checkers);
            
            // Send SMS
            return $this->sendSMS($phoneNumber, $message, $transactionId);
            
        } catch (Exception $e) {
            error_log("Send checkers SMS error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send checkers via SMS'
            ];
        }
    }
    
    /**
     * Send payment confirmation SMS
     */
    public function sendPaymentConfirmation($phoneNumber, $transactionData) {
        try {
            $message = $this->formatPaymentConfirmationMessage($transactionData);
            return $this->sendSMS($phoneNumber, $message, $transactionData['id'] ?? null);
            
        } catch (Exception $e) {
            error_log("Send payment confirmation SMS error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send payment confirmation'
            ];
        }
    }
    
    /**
     * Send payment reminder SMS
     */
    public function sendPaymentReminder($phoneNumber, $transactionData) {
        try {
            $message = $this->formatPaymentReminderMessage($transactionData);
            return $this->sendSMS($phoneNumber, $message, $transactionData['id'] ?? null);
            
        } catch (Exception $e) {
            error_log("Send payment reminder SMS error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send payment reminder'
            ];
        }
    }
    
    /**
     * Validate phone number format
     */
    private function validatePhoneNumber($phoneNumber) {
        // Ghana phone number format: 0XXXXXXXXX (10 digits starting with 0)
        return preg_match('/^0[2-9]\d{8}$/', $phoneNumber);
    }
    
    /**
     * Send SMS via provider (Mock implementation)
     */
    private function sendViaSMSProvider($phoneNumber, $message) {
        try {
            // Mock SMS API call
            $apiData = [
                'to' => $phoneNumber,
                'message' => $message,
                'sender_id' => SMS_SENDER_ID,
                'api_key' => SMS_API_KEY
            ];
            
            // Simulate API call delay
            usleep(200000); // 0.2 seconds
            
            // Mock success response (95% success rate)
            $success = mt_rand(1, 100) <= 95;
            
            if ($success) {
                $response = [
                    'status' => 'success',
                    'message_id' => 'SMS' . time() . mt_rand(1000, 9999),
                    'message' => 'SMS sent successfully',
                    'cost' => 0.05, // Mock cost
                    'timestamp' => date(DATE_FORMAT)
                ];
                
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'provider_response' => json_encode($response),
                    'message_id' => $response['message_id']
                ];
            } else {
                $response = [
                    'status' => 'failed',
                    'error_code' => 'NETWORK_ERROR',
                    'message' => 'Network error or invalid number',
                    'timestamp' => date(DATE_FORMAT)
                ];
                
                return [
                    'success' => false,
                    'message' => 'SMS sending failed',
                    'provider_response' => json_encode($response)
                ];
            }
            
        } catch (Exception $e) {
            error_log("SMS provider error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMS provider error',
                'provider_response' => json_encode(['error' => $e->getMessage()])
            ];
        }
    }
    
    /**
     * Format checkers message for SMS
     */
    private function formatCheckersMessage($checkers) {
        if (empty($checkers)) {
            return '';
        }
        
        $serviceName = $checkers[0]['service_name'] ?? 'Checker';
        $examName = !empty($checkers[0]['exam_name']) ? ' - ' . $checkers[0]['exam_name'] : '';
        $transactionId = $checkers[0]['transaction_id'] ?? '';
        $printId = $checkers[0]['print_id'] ?? '';
        
        $message = "Your {$serviceName}{$examName} Checkers:\n\n";
        
        foreach ($checkers as $index => $checker) {
            $num = $index + 1;
            $message .= "{$num}. Serial: {$checker['serial_number']}\n";
            $message .= "   PIN: {$checker['pin_code']}\n\n";
        }
        
        if (!empty($checkers[0]['expires_at'])) {
            $expiryDate = date('d/m/Y', strtotime($checkers[0]['expires_at']));
            $message .= "Valid until: {$expiryDate}\n";
        }
        
        $message .= "Transaction ID: {$transactionId}\n";
        $message .= "Print ID: {$printId}\n\n";
        $message .= "Keep these codes safe!\n";
        $message .= "For support: " . (defined('CONTACT_PHONE') ? CONTACT_PHONE : '0549616253');
        
        return $message;
    }
    
    /**
     * Format payment confirmation message
     */
    private function formatPaymentConfirmationMessage($transactionData) {
        $serviceName = $transactionData['service_name'] ?? 'Service';
        $amount = CURRENCY_SYMBOL . number_format($transactionData['total_amount'], 2);
        $transactionId = $transactionData['transaction_id'] ?? '';
        $printId = $transactionData['print_id'] ?? '';
        
        $message = "Payment Confirmed!\n\n";
        $message .= "Service: {$serviceName}\n";
        $message .= "Amount: {$amount}\n";
        $message .= "Quantity: {$transactionData['quantity']}\n";
        $message .= "Transaction ID: {$transactionId}\n";
        $message .= "Print ID: {$printId}\n\n";
        $message .= "Your checkers will be sent shortly.\n";
        $message .= "Thank you for your purchase!";
        
        return $message;
    }
    
    /**
     * Format payment reminder message
     */
    private function formatPaymentReminderMessage($transactionData) {
        $serviceName = $transactionData['service_name'] ?? 'Service';
        $amount = CURRENCY_SYMBOL . number_format($transactionData['total_amount'], 2);
        $transactionId = $transactionData['transaction_id'] ?? '';
        
        $message = "Payment Reminder\n\n";
        $message .= "Your payment for {$serviceName} is pending.\n";
        $message .= "Amount: {$amount}\n";
        $message .= "Transaction ID: {$transactionId}\n\n";
        $message .= "Please complete your mobile money payment to receive your checkers.\n";
        $message .= "Need help? Call: " . (defined('CONTACT_PHONE') ? CONTACT_PHONE : '0549616253');
        
        return $message;
    }
    
    /**
     * Log SMS attempt
     */
    private function logSMSAttempt($transactionId, $phoneNumber, $message) {
        try {
            $query = "INSERT INTO sms_logs (transaction_id, phone_number, message, status, created_at) 
                      VALUES (?, ?, ?, 'pending', ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $transactionId,
                $phoneNumber,
                $message,
                date(DATE_FORMAT)
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            error_log("SMS log error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update SMS log with result
     */
    private function updateSMSLog($smsLogId, $result) {
        try {
            if (!$smsLogId) return;
            
            $status = $result['success'] ? 'sent' : 'failed';
            $sentAt = $result['success'] ? date(DATE_FORMAT) : null;
            
            $query = "UPDATE sms_logs SET status = ?, provider_response = ?, sent_at = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $status,
                $result['provider_response'] ?? null,
                $sentAt,
                $smsLogId
            ]);
            
        } catch (Exception $e) {
            error_log("SMS log update error: " . $e->getMessage());
        }
    }
    
    /**
     * Get SMS statistics
     */
    public function getSMSStatistics($dateFrom = null, $dateTo = null) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($dateFrom && $dateTo) {
                $whereClause = "WHERE created_at BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }
            
            $query = "SELECT 
                        COUNT(*) as total_sms,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_sms,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_sms,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_sms
                      FROM sms_logs {$whereClause}";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("SMS statistics error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Retry failed SMS
     */
    public function retryFailedSMS($smsLogId) {
        try {
            // Get SMS log details
            $query = "SELECT * FROM sms_logs WHERE id = ? AND status = 'failed'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$smsLogId]);
            $smsLog = $stmt->fetch();
            
            if (!$smsLog) {
                return [
                    'success' => false,
                    'message' => 'SMS log not found or not failed'
                ];
            }
            
            // Retry sending
            $result = $this->sendSMS($smsLog['phone_number'], $smsLog['message'], $smsLog['transaction_id']);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("SMS retry error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMS retry failed'
            ];
        }
    }
    
    /**
     * Send bulk SMS
     */
    public function sendBulkSMS($recipients, $message) {
        try {
            $results = [];
            $successCount = 0;
            $failCount = 0;
            
            foreach ($recipients as $phoneNumber) {
                $result = $this->sendSMS($phoneNumber, $message);
                $results[] = [
                    'phone_number' => $phoneNumber,
                    'success' => $result['success'],
                    'message' => $result['message']
                ];
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
                
                // Small delay between sends
                usleep(100000); // 0.1 seconds
            }
            
            return [
                'success' => true,
                'message' => "Bulk SMS completed. {$successCount} sent, {$failCount} failed.",
                'total' => count($recipients),
                'sent' => $successCount,
                'failed' => $failCount,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("Bulk SMS error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Bulk SMS failed'
            ];
        }
    }
}
?>
