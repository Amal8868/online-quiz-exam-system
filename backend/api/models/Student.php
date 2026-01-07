<?php
// This is the "Student Profile" model. 
// It's like the digital ID card for every student in the system.
require_once __DIR__ . '/Model.php';

class Student extends Model {
    protected $table = 'students';
    protected $fillable = ['name', 'student_id', 'class_id'];

    // This helps us find a student using their real ID (like 21-1234),
    // not just the database's internal numbering.
    public function findByStudentId($studentId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE student_id = ?");
        $stmt->execute([$studentId]);
        return $stmt->fetch();
    }

    // This is the "Bouncer" check.
    // It looks at the guest list to see if this specific student is allowed into the exam.
    public function isAllowedInQuiz($studentDbId, $quizId) {
        $stmt = $this->db->prepare("
            SELECT * FROM quiz_allowed_students 
            WHERE student_id = ? AND quiz_id = ?
        ");
        $stmt->execute([$studentDbId, $quizId]);
        return $stmt->fetch() !== false;
    }
    
    // Sometimes students get kicked out for suspicious activity.
    // This check tells us if they've been removed from the room.
    public function isKicked($studentDbId, $quizId) {
         $stmt = $this->db->prepare("
            SELECT * FROM kicked_students 
            WHERE student_id = ? AND quiz_id = ?
        ");
        $stmt->execute([$studentDbId, $quizId]);
        return $stmt->fetch() !== false;
    }

    // When we upload a whole list of students (the roster), we use this to
    // either create a new student or update the name of one we already know.
    public function findOrCreate($studentIdStr, $name) {
        $existing = $this->findByStudentId($studentIdStr);
        if ($existing) {
            // If they already exist, we just make sure their name is up-to-date.
            $this->update($existing['id'], ['name' => $name]);
            return $existing['id'];
        } else {
            // Otherwise, we "enroll" them for the first time.
            return $this->create([
                'student_id' => $studentIdStr,
                'name' => $name
            ]);
        }
    }
}
