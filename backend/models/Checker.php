<?php
/**
 * Checker Model
 * Sellpincodes System
 */

require_once __DIR__ . '/BaseModel.php';

class Checker extends BaseModel {
    protected $table = 'checkers';
    protected $fillable = [
        'transaction_id', 'checker_code', 'serial_number', 'pin_code',
        'status', 'used_at', 'expires_at'
    ];
    
    /**
     * Generate checker code
     */
    public function generateCheckerCode() {
        do {
            $checkerCode = 'CHK' . date('ymd') . strtoupper(substr(uniqid(), -8));
        } while ($this->exists(['checker_code' => $checkerCode]));
        
        return $checkerCode;
    }
    
    /**
     * Generate serial number
     */
    public function generateSerialNumber() {
        do {
            $serialNumber = date('Y') . sprintf('%08d', mt_rand(10000000, 99999999));
        } while ($this->exists(['serial_number' => $serialNumber]));
        
        return $serialNumber;
    }
    
    /**
     * Generate PIN code
     */
    public function generatePinCode($length = 12) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pin = '';
        
        for ($i = 0; $i < $length; $i++) {
            $pin .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        
        return $pin;
    }
    
    /**
     * Generate multiple checkers for a transaction
     */
    public function generateCheckersForTransaction($transactionId, $quantity, $expiryDays = 365) {
        try {
            $this->beginTransaction();
            
            $checkers = [];
            $expiryDate = date(DATE_FORMAT, strtotime("+{$expiryDays} days"));
            
            for ($i = 0; $i < $quantity; $i++) {
                $checkerData = [
                    'transaction_id' => $transactionId,
                    'checker_code' => $this->generateCheckerCode(),
                    'serial_number' => $this->generateSerialNumber(),
                    'pin_code' => $this->generatePinCode(),
                    'status' => 'active',
                    'expires_at' => $expiryDate
                ];
                
                $checker = $this->create($checkerData);
                if (!$checker) {
                    $this->rollback();
                    return false;
                }
                
                $checkers[] = $checker;
            }
            
            $this->commit();
            return $checkers;
        } catch (Exception $e) {
            $this->rollback();
            error_log("GenerateCheckersForTransaction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find checkers by transaction ID
     */
    public function findByTransactionId($transactionId) {
        try {
            $query = "SELECT c.*, t.transaction_id, t.print_id, t.phone_number,
                             st.name as service_name, st.code as service_code,
                             et.name as exam_name, et.code as exam_code
                      FROM {$this->table} c
                      JOIN transactions t ON c.transaction_id = t.id
                      LEFT JOIN service_types st ON t.service_type_id = st.id
                      LEFT JOIN exam_types et ON t.exam_type_id = et.id
                      WHERE t.id = :transaction_id
                      ORDER BY c.created_at ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':transaction_id', $transactionId);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FindByTransactionId error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Find checkers by checker code
     */
    public function findByCheckerCode($checkerCode) {
        try {
            $query = "SELECT c.*, t.transaction_id, t.print_id, t.phone_number,
                             st.name as service_name, st.code as service_code,
                             et.name as exam_name, et.code as exam_code
                      FROM {$this->table} c
                      JOIN transactions t ON c.transaction_id = t.id
                      LEFT JOIN service_types st ON t.service_type_id = st.id
                      LEFT JOIN exam_types et ON t.exam_type_id = et.id
                      WHERE c.checker_code = :checker_code LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':checker_code', $checkerCode);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FindByCheckerCode error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find checkers by serial number
     */
    public function findBySerialNumber($serialNumber) {
        try {
            $query = "SELECT c.*, t.transaction_id, t.print_id, t.phone_number,
                             st.name as service_name, st.code as service_code,
                             et.name as exam_name, et.code as exam_code
                      FROM {$this->table} c
                      JOIN transactions t ON c.transaction_id = t.id
                      LEFT JOIN service_types st ON t.service_type_id = st.id
                      LEFT JOIN exam_types et ON t.exam_type_id = et.id
                      WHERE c.serial_number = :serial_number LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':serial_number', $serialNumber);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FindBySerialNumber error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Mark checker as used
     */
    public function markAsUsed($id) {
        return $this->update($id, [
            'status' => 'used',
            'used_at' => date(DATE_FORMAT)
        ]);
    }
    
    /**
     * Mark checker as expired
     */
    public function markAsExpired($id) {
        return $this->update($id, [
            'status' => 'expired'
        ]);
    }
    
    /**
     * Get expired checkers
     */
    public function getExpiredCheckers() {
        try {
            $query = "SELECT * FROM {$this->table} 
                      WHERE expires_at < NOW() 
                      AND status = 'active'";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetExpiredCheckers error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get checker statistics
     */
    public function getStatistics($dateFrom = null, $dateTo = null) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($dateFrom && $dateTo) {
                $whereClause = "WHERE created_at BETWEEN :date_from AND :date_to";
                $params['date_from'] = $dateFrom;
                $params['date_to'] = $dateTo;
            }
            
            $query = "SELECT 
                        COUNT(*) as total_checkers,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_checkers,
                        SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_checkers,
                        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_checkers
                      FROM {$this->table} {$whereClause}";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("GetStatistics error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Format checkers for SMS/display
     */
    public function formatCheckersForSMS($checkers) {
        if (empty($checkers)) {
            return '';
        }
        
        $message = "Your " . $checkers[0]['service_name'] . " Checkers:\n\n";
        
        foreach ($checkers as $index => $checker) {
            $message .= ($index + 1) . ". Serial: " . $checker['serial_number'] . "\n";
            $message .= "   PIN: " . $checker['pin_code'] . "\n\n";
        }
        
        $message .= "Valid until: " . date('d/m/Y', strtotime($checkers[0]['expires_at'])) . "\n";
        $message .= "Keep these codes safe!";
        
        return $message;
    }
    
    /**
     * Format checkers for print
     */
    public function formatCheckersForPrint($checkers) {
        if (empty($checkers)) {
            return [];
        }
        
        $formatted = [
            'service_name' => $checkers[0]['service_name'],
            'exam_name' => $checkers[0]['exam_name'] ?? '',
            'transaction_id' => $checkers[0]['transaction_id'],
            'print_id' => $checkers[0]['print_id'],
            'phone_number' => $checkers[0]['phone_number'],
            'quantity' => count($checkers),
            'expires_at' => $checkers[0]['expires_at'],
            'checkers' => []
        ];
        
        foreach ($checkers as $checker) {
            $formatted['checkers'][] = [
                'serial_number' => $checker['serial_number'],
                'pin_code' => $checker['pin_code'],
                'checker_code' => $checker['checker_code']
            ];
        }
        
        return $formatted;
    }
}
?>
