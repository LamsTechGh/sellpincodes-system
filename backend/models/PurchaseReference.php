<?php
/**
 * Purchase Reference Model
 * QuickCardsGH System
 * By Lamstech Solutions
 */

require_once __DIR__ . '/BaseModel.php';

class PurchaseReference extends BaseModel {
    protected $table = 'purchase_references';
    protected $fillable = [
        'reference_code', 'phone_number', 'transaction_id', 'service_type_id',
        'quantity', 'total_amount', 'status', 'expires_at'
    ];
    
    /**
     * Generate unique purchase reference code
     */
    public function generateReferenceCode($phoneNumber) {
        do {
            // Use last 4 digits of phone + timestamp + random
            $phoneSuffix = substr($phoneNumber, -4);
            $timestamp = substr(time(), -4);
            $random = strtoupper(substr(uniqid(), -4));
            
            $referenceCode = REFERENCE_PREFIX . $phoneSuffix . $timestamp . $random;
            
            // Ensure minimum length
            if (strlen($referenceCode) < MIN_REFERENCE_LENGTH) {
                $referenceCode .= strtoupper(substr(uniqid(), -(MIN_REFERENCE_LENGTH - strlen($referenceCode))));
            }
            
        } while ($this->exists(['reference_code' => $referenceCode]));
        
        return $referenceCode;
    }
    
    /**
     * Create purchase reference
     */
    public function createReference($transactionId, $phoneNumber, $serviceTypeId, $quantity, $totalAmount) {
        try {
            $referenceCode = $this->generateReferenceCode($phoneNumber);
            $expiryDate = date('Y-m-d H:i:s', strtotime('+1 year'));
            
            $data = [
                'reference_code' => $referenceCode,
                'phone_number' => $phoneNumber,
                'transaction_id' => $transactionId,
                'service_type_id' => $serviceTypeId,
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'status' => 'active',
                'expires_at' => $expiryDate
            ];
            
            $result = $this->create($data);
            if ($result) {
                return $referenceCode;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("CreateReference error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find reference by code and phone number
     */
    public function findByReferenceAndPhone($referenceCode, $phoneNumber) {
        try {
            $query = "SELECT pr.*, t.*, st.name as service_name, st.code as service_code
                      FROM {$this->table} pr
                      JOIN transactions t ON pr.transaction_id = t.id
                      JOIN service_types st ON pr.service_type_id = st.id
                      WHERE pr.reference_code = :reference_code 
                      AND pr.phone_number = :phone_number
                      AND pr.status = 'active'
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':reference_code', $referenceCode);
            $stmt->bindParam(':phone_number', $phoneNumber);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FindByReferenceAndPhone error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find reference by code only (for admin purposes)
     */
    public function findByReference($referenceCode) {
        try {
            $query = "SELECT pr.*, t.*, st.name as service_name, st.code as service_code
                      FROM {$this->table} pr
                      JOIN transactions t ON pr.transaction_id = t.id
                      JOIN service_types st ON pr.service_type_id = st.id
                      WHERE pr.reference_code = :reference_code
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':reference_code', $referenceCode);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FindByReference error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get references by phone number
     */
    public function getByPhoneNumber($phoneNumber, $limit = 10) {
        try {
            $query = "SELECT pr.*, st.name as service_name
                      FROM {$this->table} pr
                      JOIN service_types st ON pr.service_type_id = st.id
                      WHERE pr.phone_number = :phone_number
                      ORDER BY pr.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':phone_number', $phoneNumber);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetByPhoneNumber error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark reference as used
     */
    public function markAsUsed($referenceCode) {
        try {
            $query = "UPDATE {$this->table} 
                      SET status = 'used' 
                      WHERE reference_code = :reference_code";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':reference_code', $referenceCode);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("MarkAsUsed error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get expired references
     */
    public function getExpiredReferences() {
        try {
            $query = "SELECT * FROM {$this->table} 
                      WHERE expires_at < NOW() 
                      AND status = 'active'";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetExpiredReferences error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark expired references
     */
    public function markExpiredReferences() {
        try {
            $query = "UPDATE {$this->table} 
                      SET status = 'expired' 
                      WHERE expires_at < NOW() 
                      AND status = 'active'";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("MarkExpiredReferences error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get reference statistics
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
                        COUNT(*) as total_references,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_references,
                        SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_references,
                        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_references,
                        SUM(total_amount) as total_value
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
     * Validate reference format
     */
    public function validateReferenceFormat($referenceCode) {
        // Check if reference starts with prefix and meets minimum length
        if (strpos($referenceCode, REFERENCE_PREFIX) !== 0) {
            return false;
        }
        
        if (strlen($referenceCode) < MIN_REFERENCE_LENGTH) {
            return false;
        }
        
        // Check if contains only alphanumeric characters
        if (!ctype_alnum($referenceCode)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get recent references for admin dashboard
     */
    public function getRecentReferences($limit = 20) {
        try {
            $query = "SELECT pr.*, st.name as service_name
                      FROM {$this->table} pr
                      JOIN service_types st ON pr.service_type_id = st.id
                      ORDER BY pr.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetRecentReferences error: " . $e->getMessage());
            return [];
        }
    }
}
?>
