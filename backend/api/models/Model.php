<?php
require_once __DIR__ . '/../config/config.php';

abstract class Model {
    protected $table;
    protected $primaryKey = 'id';
    protected $db;
    protected $fillable = [];

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function all($conditions = [], $orderBy = '', $limit = null, $offset = null) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $params[] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY $orderBy";
        }
        
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
            if ($offset !== null) {
                $sql .= ' OFFSET ' . (int)$offset;
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data) {
        // Filter data to only include fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        
        $columns = implode(', ', array_keys($filteredData));
        $placeholders = ':' . implode(', :', array_keys($filteredData));
        
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        
        foreach ($filteredData as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        $stmt->execute();
        return $this->db->lastInsertId();
    }

    public function update($id, array $data) {
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        $set = [];
        
        foreach ($filteredData as $key => $value) {
            $set[] = "$key = :$key";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        
        foreach ($filteredData as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':id', $id);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    protected function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function beginTransaction() {
        return $this->db->beginTransaction();
    }

    protected function commit() {
        return $this->db->commit();
    }

    protected function rollBack() {
        return $this->db->rollBack();
    }
}
