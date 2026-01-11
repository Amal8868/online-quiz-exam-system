<?php
require_once __DIR__ . '/Model.php';

class Subject extends Model {
    protected $table = 'subjects';
    protected $fillable = ['name', 'code', 'description'];

    public function assignToClasses($subjectId, $classIds) {
        $stmt = $this->db->prepare('DELETE FROM class_subjects WHERE subject_id = ?');
        $stmt->execute([$subjectId]);

        if (empty($classIds)) return true;

        $query = 'INSERT INTO class_subjects (class_id, subject_id) VALUES ';
        $placeholders = [];
        $params = [];

        foreach ($classIds as $classId) {
            $placeholders[] = "(?, ?)";
            $params[] = $classId;
            $params[] = $subjectId;
        }

        $query .= implode(', ', $placeholders);
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    
    public function getClasses($subjectId) {
        $stmt = $this->db->prepare('SELECT class_id FROM class_subjects WHERE subject_id = ?');
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getByClass($classId) {
         $sql = 'SELECT s.* FROM subjects s
                 JOIN class_subjects cs ON s.id = cs.subject_id
                 WHERE cs.class_id = ?';
         $stmt = $this->db->prepare($sql);
         $stmt->execute([$classId]);
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
