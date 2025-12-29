<?php
require_once __DIR__ . '/Model.php';

class Quiz extends Model {
    protected $table = 'quizzes';
    protected $fillable = [
        'title', 
        'description', 
        'teacher_id', 
        'room_code', 
        'start_time', 
        'end_time', 
        'duration_minutes', 
        'timer_type', 
        'status'
    ];

    public function findByRoomCode($roomCode) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        return $stmt->fetch();
    }
    
    public function isActive($id) {
        $quiz = $this->find($id);
        return $quiz && $quiz['status'] === 'active';
    }

    public function addAllowedStudent($quizId, $studentDbId) {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO quiz_allowed_students (quiz_id, student_id) VALUES (?, ?)");
            return $stmt->execute([$quizId, $studentDbId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function getAllowedStudents($quizId) {
        $stmt = $this->db->prepare("
            SELECT s.* FROM students s
            JOIN quiz_allowed_students qas ON s.id = qas.student_id
            WHERE qas.quiz_id = ?
        ");
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }
    
    public function getActiveParticipants($quizId) {
         $stmt = $this->db->prepare("
            SELECT s.*, r.status as result_status, r.score, r.submitted_at 
            FROM students s
            JOIN results r ON s.id = r.student_id
            WHERE r.quiz_id = ?
        ");
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }

    public function getAllowedClasses($quizId) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
            (SELECT COUNT(*) FROM students WHERE class_id = c.id) as student_count
            FROM classes c
            JOIN quiz_classes qc ON c.id = qc.class_id
            WHERE qc.quiz_id = ?
        ");
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }

    public function deleteQuiz($id) {
        // Get material URL to delete file
        $quiz = $this->find($id);
        if ($quiz && !empty($quiz['material_url'])) {
            $filePath = __DIR__ . '/../../' . $quiz['material_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getByClassId($classId) {
        $stmt = $this->db->prepare("
            SELECT q.* FROM {$this->table} q
            JOIN quiz_classes qc ON q.id = qc.quiz_id
            WHERE qc.class_id = ?
            ORDER BY q.created_at DESC
        ");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }
}
