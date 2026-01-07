<?php
// This is the "Granddaddy" model! 
// Instead of writing the same database code over and over for Users, Quizzes, and Results, 
// I put all the common stuff here. Every other model just "extends" this one.
require_once __DIR__ . '/../config/config.php';

abstract class Model {
    protected $table;
    protected $primaryKey = 'id';
    protected $db;
    protected $fillable = []; // This is like a whitelist, only these fields can be saved.

    public function __construct() {
        // As soon as a model is created, it grabs a database connection.
        $this->db = getDBConnection();
    }

    // A quick way to find something by its ID.
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // This is a flexible "Get All" function. 
    // It can filter by conditions, sort, and even handle pagination (limit/offset).
    public function all($conditions = [], $orderBy = '', $limit = null, $offset = null) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // If we want to filter, like "WHERE status = 'Active'".
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

    // This makes creating new entries super easy. 
    // I made it use the $fillable array so that hackers can't inject random columns.
    public function create(array $data) {
        // Filter data to only include fillable fields.
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        
        $columns = implode(', ', array_keys($filteredData));
        $placeholders = ':' . implode(', :', array_keys($filteredData));
        
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        
        foreach ($filteredData as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        $stmt->execute();
        return $this->db->lastInsertId(); // Return the ID of the new thing we just made.
    }

    // Just like create, but for updating existing rows.
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

    // Delete something forever. Use with caution!
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    // A helper for when I need to write custom SQL queries.
    protected function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Transactions are like "Save Points" in a video game. 
    // If something goes wrong, we can go back to the start.
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
