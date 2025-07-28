<?php
/**
 * Base Model Class
 * Sellpincodes System
 */

require_once __DIR__ . '/../config/database.php';

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Find record by ID
     */
    public function find($id) {
        try {
            $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ? $this->hideFields($result) : null;
        } catch (PDOException $e) {
            error_log("Find error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find all records with optional conditions
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null) {
        try {
            $query = "SELECT * FROM {$this->table}";
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $field => $value) {
                    $whereClause[] = "{$field} = :{$field}";
                    $params[$field] = $value;
                }
                $query .= " WHERE " . implode(' AND ', $whereClause);
            }
            
            if ($orderBy) {
                $query .= " ORDER BY {$orderBy}";
            }
            
            if ($limit) {
                $query .= " LIMIT {$limit}";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll();
            return array_map([$this, 'hideFields'], $results);
        } catch (PDOException $e) {
            error_log("FindAll error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create new record
     */
    public function create($data) {
        try {
            $data = $this->filterFillable($data);
            $data['created_at'] = date(DATE_FORMAT);
            $data['updated_at'] = date(DATE_FORMAT);
            
            $fields = array_keys($data);
            $placeholders = ':' . implode(', :', $fields);
            
            $query = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
            $stmt = $this->db->prepare($query);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            if ($stmt->execute()) {
                $id = $this->db->lastInsertId();
                return $this->find($id);
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update record
     */
    public function update($id, $data) {
        try {
            $data = $this->filterFillable($data);
            $data['updated_at'] = date(DATE_FORMAT);
            
            $setClause = [];
            foreach ($data as $field => $value) {
                $setClause[] = "{$field} = :{$field}";
            }
            
            $query = "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE {$this->primaryKey} = :id";
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':id', $id);
            foreach ($data as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            if ($stmt->execute()) {
                return $this->find($id);
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete record
     */
    public function delete($id) {
        try {
            $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute custom query
     */
    protected function query($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Hide sensitive fields from output
     */
    protected function hideFields($data) {
        if (empty($this->hidden) || !is_array($data)) {
            return $data;
        }
        
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        
        return $data;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->db->rollback();
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->db;
    }
    
    /**
     * Count records
     */
    public function count($conditions = []) {
        try {
            $query = "SELECT COUNT(*) as total FROM {$this->table}";
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $field => $value) {
                    $whereClause[] = "{$field} = :{$field}";
                    $params[$field] = $value;
                }
                $query .= " WHERE " . implode(' AND ', $whereClause);
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return (int) $result['total'];
        } catch (PDOException $e) {
            error_log("Count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if record exists
     */
    public function exists($conditions) {
        return $this->count($conditions) > 0;
    }
}
?>
