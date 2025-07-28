<?php
/**
 * Transaction Model
 * Sellpincodes System
 */

require_once __DIR__ . '/BaseModel.php';

class Transaction extends BaseModel {
    protected $table = 'transactions';
    protected $fillable = [
        'transaction_id', 'print_id', 'service_type_id', 'exam_type_id',
        'quantity', 'unit_price', 'total_amount', 'phone_number',
        'momo_provider_id', 'payment_status', 'payment_reference',
        'momo_transaction_id', 'customer_ip', 'user_agent', 'status',
        'expires_at', 'paid_at'
    ];
    
    /**
     * Generate unique transaction ID
     */
    public function generateTransactionId() {
        do {
            $transactionId = 'TXN' . date('ymd') . strtoupper(substr(uniqid(), -6));
        } while ($this->exists(['transaction_id' => $transactionId]));
        
        return $transactionId;
    }
    
    /**
     * Generate unique print ID
     */
    public function generatePrintId() {
        do {
            $printId = 'PRT' . date('ymd') . strtoupper(substr(uniqid(), -6));
        } while ($this->exists(['print_id' => $printId]));
        
        return $printId;
    }
    
    /**
     * Find transaction by transaction ID
     */
    public function findByTransactionId($transactionId) {
        try {
            $query = "SELECT t.*, st.name as service_name, st.code as service_code,
                             et.name as exam_name, et.code as exam_code,
                             mp.name as momo_provider_name, mp.code as momo_provider_code
                      FROM {$this->table} t
                      LEFT JOIN service_types st ON t.service_type_id = st.id
                      LEFT JOIN exam_types et ON t.exam_type_id = et.id
                      LEFT JOIN momo_providers mp ON t.momo_provider_id = mp.id
                      WHERE t.transaction_id = :transaction_id LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':transaction_id', $transactionId);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FindByTransactionId error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find transaction by print ID
     */
    public function findByPrintId($printId) {
        try {
            $query = "SELECT t.*, st.name as service_name, st.code as service_code,
                             et.name as exam_name, et.code as exam_code,
                             mp.name as momo_provider_name, mp.code as momo_provider_code
                      FROM {$this->table} t
                      LEFT JOIN service_types st ON t.service_type_id = st.id
                      LEFT JOIN exam_types et ON t.exam_type_id = et.id
                      LEFT JOIN momo_providers mp ON t.momo_provider_id = mp.id
                      WHERE t.print_id = :print_id LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':print_id', $printId);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FindByPrintId error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find transactions by phone number
     */
    public function findByPhoneNumber($phoneNumber, $limit = 10) {
        try {
            $query = "SELECT t.*, st.name as service_name, st.code as service_code,
                             et.name as exam_name, et.code as exam_code,
                             mp.name as momo_provider_name, mp.code as momo_provider_code
                      FROM {$this->table} t
                      LEFT JOIN service_types st ON t.service_type_id = st.id
                      LEFT JOIN exam_types et ON t.exam_type_id = et.id
                      LEFT JOIN momo_providers mp ON t.momo_provider_id = mp.id
                      WHERE t.phone_number = :phone_number
                      ORDER BY t.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':phone_number', $phoneNumber);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FindByPhoneNumber error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($id, $status, $reference = null, $momoTransactionId = null) {
        try {
            $data = [
                'payment_status' => $status,
                'updated_at' => date(DATE_FORMAT)
            ];
            
            if ($reference) {
                $data['payment_reference'] = $reference;
            }
            
            if ($momoTransactionId) {
                $data['momo_transaction_id'] = $momoTransactionId;
            }
            
            if ($status === 'completed') {
                $data['paid_at'] = date(DATE_FORMAT);
            }
            
            return $this->update($id, $data);
        } catch (Exception $e) {
            error_log("UpdatePaymentStatus error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get transaction statistics
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
                        COUNT(*) as total_transactions,
                        SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_transactions,
                        SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
                        AVG(CASE WHEN payment_status = 'completed' THEN total_amount ELSE NULL END) as avg_transaction_amount,
                        SUM(CASE WHEN payment_status = 'completed' THEN quantity ELSE 0 END) as total_checkers_sold
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
     * Get recent transactions
     */
    public function getRecentTransactions($limit = 20) {
        try {
            $query = "SELECT t.*, st.name as service_name, st.code as service_code,
                             et.name as exam_name, et.code as exam_code,
                             mp.name as momo_provider_name, mp.code as momo_provider_code
                      FROM {$this->table} t
                      LEFT JOIN service_types st ON t.service_type_id = st.id
                      LEFT JOIN exam_types et ON t.exam_type_id = et.id
                      LEFT JOIN momo_providers mp ON t.momo_provider_id = mp.id
                      ORDER BY t.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetRecentTransactions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark transaction as expired
     */
    public function markAsExpired($id) {
        return $this->update($id, [
            'status' => 'expired',
            'payment_status' => 'cancelled'
        ]);
    }
    
    /**
     * Get expired transactions
     */
    public function getExpiredTransactions() {
        try {
            $query = "SELECT * FROM {$this->table} 
                      WHERE expires_at < NOW() 
                      AND status = 'active' 
                      AND payment_status IN ('pending', 'processing')";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetExpiredTransactions error: " . $e->getMessage());
            return [];
        }
    }
}
?>
