<?php
/**
 * Batch Upload Model
 * QuickCardsGH System
 * By Lamstech Solutions
 */

require_once __DIR__ . '/BaseModel.php';

class BatchUpload extends BaseModel {
    protected $table = 'batch_uploads';
    protected $fillable = [
        'batch_id', 'service_type_id', 'exam_type_id', 'filename', 
        'total_records', 'successful_imports', 'failed_imports',
        'upload_status', 'uploaded_by', 'upload_notes', 'completed_at'
    ];
    
    /**
     * Generate unique batch ID
     */
    public function generateBatchId() {
        do {
            $batchId = 'BATCH_' . date('Ymd_His') . '_' . strtoupper(substr(uniqid(), -6));
        } while ($this->exists(['batch_id' => $batchId]));
        
        return $batchId;
    }
    
    /**
     * Create new batch upload record
     */
    public function createBatch($serviceTypeId, $examTypeId, $filename, $uploadedBy) {
        try {
            $batchId = $this->generateBatchId();
            
            $data = [
                'batch_id' => $batchId,
                'service_type_id' => $serviceTypeId,
                'exam_type_id' => $examTypeId,
                'filename' => $filename,
                'total_records' => 0,
                'successful_imports' => 0,
                'failed_imports' => 0,
                'upload_status' => 'processing',
                'uploaded_by' => $uploadedBy
            ];
            
            $result = $this->create($data);
            if ($result) {
                return $batchId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("CreateBatch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update batch progress
     */
    public function updateBatchProgress($batchId, $totalRecords, $successful, $failed, $status = 'processing', $notes = null) {
        try {
            $data = [
                'total_records' => $totalRecords,
                'successful_imports' => $successful,
                'failed_imports' => $failed,
                'upload_status' => $status
            ];
            
            if ($notes) {
                $data['upload_notes'] = $notes;
            }
            
            if ($status === 'completed' || $status === 'failed') {
                $data['completed_at'] = date(DATE_FORMAT);
            }
            
            $query = "UPDATE {$this->table} 
                      SET total_records = :total_records,
                          successful_imports = :successful_imports,
                          failed_imports = :failed_imports,
                          upload_status = :upload_status,
                          upload_notes = :upload_notes,
                          completed_at = :completed_at,
                          updated_at = NOW()
                      WHERE batch_id = :batch_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':batch_id', $batchId);
            $stmt->bindParam(':total_records', $data['total_records']);
            $stmt->bindParam(':successful_imports', $data['successful_imports']);
            $stmt->bindParam(':failed_imports', $data['failed_imports']);
            $stmt->bindParam(':upload_status', $data['upload_status']);
            $stmt->bindParam(':upload_notes', $data['upload_notes']);
            $stmt->bindParam(':completed_at', $data['completed_at']);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("UpdateBatchProgress error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get batch by ID
     */
    public function getBatchById($batchId) {
        try {
            $query = "SELECT bu.*, st.name as service_name, et.name as exam_name, u.username as uploaded_by_name
                      FROM {$this->table} bu
                      LEFT JOIN service_types st ON bu.service_type_id = st.id
                      LEFT JOIN exam_types et ON bu.exam_type_id = et.id
                      LEFT JOIN users u ON bu.uploaded_by = u.id
                      WHERE bu.batch_id = :batch_id
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':batch_id', $batchId);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("GetBatchById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all batches with pagination
     */
    public function getAllBatches($page = 1, $limit = 20, $status = null) {
        try {
            $offset = ($page - 1) * $limit;
            $whereClause = '';
            $params = [];
            
            if ($status) {
                $whereClause = "WHERE bu.upload_status = :status";
                $params['status'] = $status;
            }
            
            $query = "SELECT bu.*, st.name as service_name, et.name as exam_name, u.username as uploaded_by_name
                      FROM {$this->table} bu
                      LEFT JOIN service_types st ON bu.service_type_id = st.id
                      LEFT JOIN exam_types et ON bu.exam_type_id = et.id
                      LEFT JOIN users u ON bu.uploaded_by = u.id
                      {$whereClause}
                      ORDER BY bu.created_at DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindParam(':' . $key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetAllBatches error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get batch count
     */
    public function getBatchCount($status = null) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($status) {
                $whereClause = "WHERE upload_status = :status";
                $params['status'] = $status;
            }
            
            $query = "SELECT COUNT(*) as count FROM {$this->table} {$whereClause}";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindParam(':' . $key, $value);
            }
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            error_log("GetBatchCount error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get batch statistics
     */
    public function getBatchStatistics($dateFrom = null, $dateTo = null) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($dateFrom && $dateTo) {
                $whereClause = "WHERE created_at BETWEEN :date_from AND :date_to";
                $params['date_from'] = $dateFrom;
                $params['date_to'] = $dateTo;
            }
            
            $query = "SELECT 
                        COUNT(*) as total_batches,
                        SUM(CASE WHEN upload_status = 'completed' THEN 1 ELSE 0 END) as completed_batches,
                        SUM(CASE WHEN upload_status = 'failed' THEN 1 ELSE 0 END) as failed_batches,
                        SUM(CASE WHEN upload_status = 'processing' THEN 1 ELSE 0 END) as processing_batches,
                        SUM(total_records) as total_records_processed,
                        SUM(successful_imports) as total_successful_imports,
                        SUM(failed_imports) as total_failed_imports
                      FROM {$this->table} {$whereClause}";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("GetBatchStatistics error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get recent batches for dashboard
     */
    public function getRecentBatches($limit = 10) {
        try {
            $query = "SELECT bu.*, st.name as service_name, et.name as exam_name, u.username as uploaded_by_name
                      FROM {$this->table} bu
                      LEFT JOIN service_types st ON bu.service_type_id = st.id
                      LEFT JOIN exam_types et ON bu.exam_type_id = et.id
                      LEFT JOIN users u ON bu.uploaded_by = u.id
                      ORDER BY bu.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetRecentBatches error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete batch and associated records
     */
    public function deleteBatch($batchId) {
        try {
            $this->beginTransaction();
            
            // First delete associated pincode inventory records
            $query1 = "DELETE FROM pincode_inventory WHERE batch_id = :batch_id";
            $stmt1 = $this->db->prepare($query1);
            $stmt1->bindParam(':batch_id', $batchId);
            $stmt1->execute();
            
            // Then delete the batch record
            $query2 = "DELETE FROM {$this->table} WHERE batch_id = :batch_id";
            $stmt2 = $this->db->prepare($query2);
            $stmt2->bindParam(':batch_id', $batchId);
            $result = $stmt2->execute();
            
            $this->commit();
            return $result;
        } catch (PDOException $e) {
            $this->rollback();
            error_log("DeleteBatch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get batches by service type
     */
    public function getBatchesByServiceType($serviceTypeId, $limit = 20) {
        try {
            $query = "SELECT bu.*, st.name as service_name, et.name as exam_name, u.username as uploaded_by_name
                      FROM {$this->table} bu
                      LEFT JOIN service_types st ON bu.service_type_id = st.id
                      LEFT JOIN exam_types et ON bu.exam_type_id = et.id
                      LEFT JOIN users u ON bu.uploaded_by = u.id
                      WHERE bu.service_type_id = :service_type_id
                      ORDER BY bu.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':service_type_id', $serviceTypeId);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetBatchesByServiceType error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark batch as failed
     */
    public function markBatchAsFailed($batchId, $errorMessage) {
        return $this->updateBatchProgress($batchId, 0, 0, 0, 'failed', $errorMessage);
    }
    
    /**
     * Get processing batches (for cleanup/monitoring)
     */
    public function getProcessingBatches($olderThanMinutes = 30) {
        try {
            $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$olderThanMinutes} minutes"));
            
            $query = "SELECT * FROM {$this->table} 
                      WHERE upload_status = 'processing' 
                      AND created_at < :cutoff_time";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cutoff_time', $cutoffTime);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("GetProcessingBatches error: " . $e->getMessage());
            return [];
        }
    }
}
?>
