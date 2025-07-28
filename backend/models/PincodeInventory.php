<?php
/**
 * Pincode Inventory Model
 * QuickCardsGH System
 * By Lamstech Solutions
 */

require_once __DIR__ . '/BaseModel.php';

class PincodeInventory extends BaseModel {
    protected $table = 'pincode_inventory';
    protected $fillable = [
        'service_type_id', 'exam_type_id', 'serial_number', 'pin_code', 
        'voucher_code', 'batch_id', 'status', 'sold_at', 'sold_to_phone',
        'purchase_reference', 'expires_at', 'notes'
    ];
    
    /**
     * Get available pincodes for a service type
     */
    public function getAvailablePincodes($serviceTypeId, $examTypeId = null, $quantity = 1) {
        try {
            $query = "SELECT * FROM {$this->table} 
                      WHERE service_type_id = :service_type_id 
                      AND status = 'available'";
            
            $params = ['service_type_id' => $serviceTypeId];
            
            if ($examTypeId) {
                $query .= " AND exam_type_id = :exam_type_id";
                $params['exam_type_id'] = $examTypeId;
            }
            
            $query .= " ORDER BY created_at ASC LIMIT :quantity";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindParam(':' . $key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetAvailablePincodes error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark pincodes as sold
     */
    public function markAsSold($pincodeIds, $phoneNumber, $purchaseReference) {
        try {
            $this->beginTransaction();
            
            $placeholders = str_repeat('?,', count($pincodeIds) - 1) . '?';
            $query = "UPDATE {$this->table} 
                      SET status = 'sold', 
                          sold_at = NOW(), 
                          sold_to_phone = ?, 
                          purchase_reference = ?
                      WHERE id IN ($placeholders)";
            
            $params = array_merge([$phoneNumber, $purchaseReference], $pincodeIds);
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);
            
            $this->commit();
            return $result;
        } catch (PDOException $e) {
            $this->rollback();
            error_log("MarkAsSold error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get inventory statistics
     */
    public function getInventoryStats($serviceTypeId = null) {
        try {
            $whereClause = $serviceTypeId ? "WHERE service_type_id = :service_type_id" : "";
            $query = "SELECT 
                        COUNT(*) as total_cards,
                        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_cards,
                        SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_cards,
                        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_cards,
                        SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) as damaged_cards
                      FROM {$this->table} {$whereClause}";
            
            $stmt = $this->db->prepare($query);
            if ($serviceTypeId) {
                $stmt->bindParam(':service_type_id', $serviceTypeId);
            }
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("GetInventoryStats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get revenue statistics
     */
    public function getRevenueStats($serviceTypeId = null, $dateFrom = null, $dateTo = null) {
        try {
            $whereClause = "WHERE pi.status = 'sold'";
            $params = [];
            
            if ($serviceTypeId) {
                $whereClause .= " AND pi.service_type_id = :service_type_id";
                $params['service_type_id'] = $serviceTypeId;
            }
            
            if ($dateFrom && $dateTo) {
                $whereClause .= " AND pi.sold_at BETWEEN :date_from AND :date_to";
                $params['date_from'] = $dateFrom;
                $params['date_to'] = $dateTo;
            }
            
            $query = "SELECT 
                        COUNT(*) as total_sold,
                        SUM(st.selling_price) as total_revenue,
                        SUM(st.admin_price) as total_cost,
                        SUM(st.selling_price - st.admin_price) as total_profit
                      FROM {$this->table} pi
                      JOIN service_types st ON pi.service_type_id = st.id
                      {$whereClause}";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("GetRevenueStats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Import pincodes from Excel data
     */
    public function importPincodes($data, $serviceTypeId, $examTypeId, $batchId) {
        try {
            $this->beginTransaction();
            
            $successful = 0;
            $failed = 0;
            $errors = [];
            
            foreach ($data as $index => $row) {
                try {
                    // Validate required fields
                    if (empty($row['serial_number']) || empty($row['pin_code'])) {
                        $failed++;
                        $errors[] = "Row " . ($index + 1) . ": Missing serial number or PIN code";
                        continue;
                    }
                    
                    // Check for duplicates
                    if ($this->exists(['serial_number' => $row['serial_number']])) {
                        $failed++;
                        $errors[] = "Row " . ($index + 1) . ": Serial number already exists";
                        continue;
                    }
                    
                    $pincodeData = [
                        'service_type_id' => $serviceTypeId,
                        'exam_type_id' => $examTypeId,
                        'serial_number' => $row['serial_number'],
                        'pin_code' => $row['pin_code'],
                        'voucher_code' => $row['voucher_code'] ?? null,
                        'batch_id' => $batchId,
                        'status' => 'available',
                        'expires_at' => isset($row['expires_at']) ? $row['expires_at'] : date('Y-m-d H:i:s', strtotime('+1 year'))
                    ];
                    
                    if ($this->create($pincodeData)) {
                        $successful++;
                    } else {
                        $failed++;
                        $errors[] = "Row " . ($index + 1) . ": Failed to insert record";
                    }
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }
            
            $this->commit();
            return [
                'successful' => $successful,
                'failed' => $failed,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            $this->rollback();
            error_log("ImportPincodes error: " . $e->getMessage());
            return [
                'successful' => 0,
                'failed' => count($data),
                'errors' => ['Database error: ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Get pincodes by batch ID
     */
    public function getByBatchId($batchId) {
        try {
            $query = "SELECT pi.*, st.name as service_name, et.name as exam_name
                      FROM {$this->table} pi
                      LEFT JOIN service_types st ON pi.service_type_id = st.id
                      LEFT JOIN exam_types et ON pi.exam_type_id = et.id
                      WHERE pi.batch_id = :batch_id
                      ORDER BY pi.created_at ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':batch_id', $batchId);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetByBatchId error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get pincodes by purchase reference
     */
    public function getByPurchaseReference($purchaseReference) {
        try {
            $query = "SELECT pi.*, st.name as service_name, et.name as exam_name
                      FROM {$this->table} pi
                      LEFT JOIN service_types st ON pi.service_type_id = st.id
                      LEFT JOIN exam_types et ON pi.exam_type_id = et.id
                      WHERE pi.purchase_reference = :purchase_reference
                      ORDER BY pi.sold_at ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':purchase_reference', $purchaseReference);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetByPurchaseReference error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update pincode status
     */
    public function updateStatus($id, $status, $notes = null) {
        $data = ['status' => $status];
        if ($notes) {
            $data['notes'] = $notes;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts($threshold = 10) {
        try {
            $query = "SELECT 
                        st.name as service_name,
                        st.id as service_type_id,
                        COUNT(*) as available_count
                      FROM {$this->table} pi
                      JOIN service_types st ON pi.service_type_id = st.id
                      WHERE pi.status = 'available'
                      GROUP BY pi.service_type_id, st.name
                      HAVING available_count <= :threshold
                      ORDER BY available_count ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':threshold', $threshold, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetLowStockAlerts error: " . $e->getMessage());
            return [];
        }
    }
}
?>
