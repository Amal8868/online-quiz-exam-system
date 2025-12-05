<?php
require_once __DIR__ . '/Model.php';

class Violation extends Model {
    protected $table = 'student_violations';
    protected $fillable = ['result_id', 'student_id', 'quiz_id', 'violation_type', 'violation_count', 'details', 'severity', 'status'];
    
    // Record a violation for a student
    public function recordViolation($resultId, $studentId, $quizId, $violationType, $details = null) {
        // Get current violation count for this result
        $currentCount = $this->getViolationCount($resultId);
        $newCount = $currentCount + 1;
        
        // Record the violation
        return $this->create([
            'result_id' => $resultId,
            'student_id' => $studentId,
            'quiz_id' => $quizId,
            'violation_type' => $violationType,
            'violation_count' => $newCount,
            'details' => $details,
            'status' => 'active',
            'severity' => 'medium'
        ]);
    }
    
    // Get the current violation count for a result
    public function getViolationCount($resultId) {
        $result = $this->query(
            "SELECT MAX(violation_count) as max_count 
             FROM {$this->table} 
             WHERE result_id = ?",
            [$resultId]
        )->fetch();
        
        return (int)($result['max_count'] ?? 0);
    }
    
    // Get all violations for a student in a quiz
    public function getStudentViolations($studentId, $quizId = null) {
        $params = [$studentId];
        $sql = "SELECT * FROM {$this->table} 
                WHERE student_id = ?";
        
        if ($quizId !== null) {
            $sql .= " AND quiz_id = ?";
            $params[] = $quizId;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $this->query($sql, $params)->fetchAll();
    }
    
    // Alias for getStudentViolations for controller compatibility
    public function getByStudentId($studentId) {
        return $this->getStudentViolations($studentId);
    }
    
    // Get all violations for a quiz (for teachers)
    public function getQuizViolations($quizId, $teacherId = null) {
        $params = [];
        $sql = "SELECT v.*, s.name as student_name, s.student_id
                FROM {$this->table} v
                JOIN students s ON v.student_id = s.id
                WHERE v.quiz_id = ?";
        
        $params[] = $quizId;
        
        if ($teacherId !== null) {
            $sql .= " JOIN quizzes q ON v.quiz_id = q.id AND q.teacher_id = ?";
            $params[] = $teacherId;
        }
        
        $sql .= " ORDER BY v.created_at DESC";
        
        return $this->query($sql, $params)->fetchAll();
    }
    
    // Alias for getQuizViolations without teacher verification
    public function getByQuizId($quizId) {
        return $this->getQuizViolations($quizId);
    }
    
    // Get a single violation by ID
    public function getById($id) {
        return $this->query(
            "SELECT v.*, s.name as student_name, s.student_id
             FROM {$this->table} v
             JOIN students s ON v.student_id = s.id
             WHERE v.id = ?",
            [$id]
        )->fetch();
    }
    
    // Update a violation
    public function update($id, $data) {
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        return parent::update($id, $filteredData);
    }
    
    // Check if a student has been kicked from a quiz
    public function isStudentKicked($studentId, $quizId) {
        $kickedModel = new KickedStudent();
        return $kickedModel->isStudentKicked($studentId, $quizId);
    }
}