<?php
require_once __DIR__ . '/Model.php';

class Student extends Model {
    protected $table = 'students';
    protected $fillable = ['name', 'student_id', 'class_id'];

    // Find student by their unique student_id string
    public function findByStudentId($studentId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE student_id = ?");
        $stmt->execute([$studentId]);
        return $stmt->fetch();
    }

    // Check if student is allowed in a quiz
    public function isAllowedInQuiz($studentDbId, $quizId) {
        $stmt = $this->db->prepare("
            SELECT * FROM quiz_allowed_students 
            WHERE student_id = ? AND quiz_id = ?
        ");
        $stmt->execute([$studentDbId, $quizId]);
        return $stmt->fetch() !== false;
    }
    
    // Check if student is kicked from a quiz
    public function isKicked($studentDbId, $quizId) {
         $stmt = $this->db->prepare("
            SELECT * FROM kicked_students 
            WHERE student_id = ? AND quiz_id = ?
        ");
        $stmt->execute([$studentDbId, $quizId]);
        return $stmt->fetch() !== false;
    }

    // Bulk create or update from roster
    public function findOrCreate($studentIdStr, $name) {
        $existing = $this->findByStudentId($studentIdStr);
        if ($existing) {
            // Optional: Update name if changed? For now, keep original or update.
            // Let's update name to match latest roster uploads
            $this->update($existing['id'], ['name' => $name]);
            return $existing['id'];
        } else {
            return $this->create([
                'student_id' => $studentIdStr,
                'name' => $name
            ]);
        }
    }
}
