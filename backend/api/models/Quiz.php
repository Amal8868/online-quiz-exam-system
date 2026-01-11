<?php
// This is the model for Quizzes. 
// It's like a blueprint for every test or exam a teacher creates.
require_once __DIR__ . '/Model.php';

class Quiz extends Model {
    protected $table = 'quizzes';
    protected $fillable = [
        'title',
        'description',
        'teacher_id',
        'subject_id', // This links the quiz to a subject.
        'room_code',  // The 6-digit code students use to join.
        'start_time',
        'end_time',
        'duration_minutes',
        'timer_type',
        'status' // 'draft', 'active', 'started', or 'finished'.
    ];

    // Sometimes we only have the room code (like when a student is joining).
    // This looks up the quiz using that code.
    public function findByRoomCode($roomCode) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        return $stmt->fetch();
    }
    
    // A quick check to see if students are allowed to enter the quiz right now.
    public function isActive($id) {
        $quiz = $this->find($id);
        return $quiz && $quiz['status'] === 'active';
    }

    // This handles manual rosters (adding a student to a quiz one by one).
    public function addAllowedStudent($quizId, $studentDbId) {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO quiz_allowed_students (quiz_id, student_id) VALUES (?, ?)");
            return $stmt->execute([$quizId, $studentDbId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Gets the list of students specifically invited to this quiz.
    public function getAllowedStudents($quizId) {
        $stmt = $this->db->prepare("
            SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as name, u.user_id as student_id 
            FROM users u
            JOIN quiz_allowed_students qas ON u.id = qas.student_id
            WHERE qas.quiz_id = ?
        ");
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }
    
    // Shows who is currently taking the quiz and what their progress is.
    public function getActiveParticipants($quizId) {
         $stmt = $this->db->prepare("
            SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as name, u.user_id as student_id, 
                   r.status as result_status, r.score, r.submitted_at 
            FROM users u
            JOIN results r ON u.id = r.student_id
            WHERE r.quiz_id = ?
        ");
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }

    // Gets the classes that have been assigned to this quiz.
    public function getAllowedClasses($quizId) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
            (SELECT COUNT(*) FROM class_students cs JOIN users u ON cs.student_id = u.id WHERE cs.class_id = c.id AND u.status = 'Active') as student_count
            FROM classes c
            JOIN quiz_classes qc ON c.id = qc.class_id
            WHERE qc.quiz_id = ?
        ");
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }

    // Deletes a quiz and also cleans up any attached files (like study materials).
    public function deleteQuiz($id) {
        $quiz = $this->find($id);
        if ($quiz && !empty($quiz['material_url'])) {
            $filePath = __DIR__ . '/../../' . $quiz['material_url'];
            if (file_exists($filePath)) {
                unlink($filePath); // Delete the file from the server.
            }
        }
        
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Finds all quizzes assigned to a specific class.
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

    public function countAll() {
        return (int)$this->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }
}
