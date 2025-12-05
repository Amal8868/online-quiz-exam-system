<?php
require_once __DIR__ . '/Model.php';

class Quiz extends Model {
    protected $table = 'quizzes';
    protected $fillable = [
        'teacher_id', 'class_id', 'title', 'description', 'room_code', 
        'time_limit', 'start_time', 'end_time', 'is_active', 'show_results'
    ];
    
    // Create a new quiz
    public function createQuiz($teacherId, $classId, $title, $description = '', $timeLimit = 30, $startTime = null, $endTime = null) {
        // Verify the teacher owns this class
        $classModel = new ClassModel();
        $class = $classModel->find($classId);
        
        if (!$class || $class['teacher_id'] != $teacherId) {
            throw new Exception('Class not found or access denied', 403);
        }
        
        // Generate a unique room code
        $teacherModel = new Teacher();
        $roomCode = $teacherModel->generateRoomCode();
        
        // Create the quiz
        $quizId = $this->create([
            'teacher_id' => $teacherId,
            'class_id' => $classId,
            'title' => $title,
            'description' => $description,
            'room_code' => $roomCode,
            'time_limit' => $timeLimit,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_active' => $startTime ? (strtotime($startTime) <= time() && (!$endTime || strtotime($endTime) >= time())) : true
        ]);
        
        return [
            'id' => $quizId,
            'room_code' => $roomCode
        ];
    }

    // Get quiz by room code
    public function getQuizByRoomCode($roomCode) {
        $quiz = $this->query(
            "SELECT q.*, c.name as class_name, t.name as teacher_name 
             FROM quizzes q
             JOIN classes c ON q.class_id = c.id
             JOIN teachers t ON q.teacher_id = t.id
             WHERE q.room_code = ?", 
            [$roomCode]
        )->fetch();
        
        if (!$quiz) {
            throw new Exception('Quiz not found', 404);
        }
        
        return $quiz;
    }
    
    // Get quiz details with questions and options
    public function getQuizWithQuestions($quizId, $teacherId = null) {
        // Verify the teacher owns this quiz if teacherId is provided
        if ($teacherId !== null) {
            $quiz = $this->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                throw new Exception('Quiz not found or access denied', 404);
            }
        }
        
        // Get quiz details
        $quiz = $this->query(
            "SELECT q.*, c.name as class_name, t.name as teacher_name 
             FROM quizzes q
             JOIN classes c ON q.class_id = c.id
             JOIN teachers t ON q.teacher_id = t.id
             WHERE q.id = ?", 
            [$quizId]
        )->fetch();
        
        if (!$quiz) {
            throw new Exception('Quiz not found', 404);
        }
        
        // Get questions with options
        $questionModel = new Question();
        $questions = $questionModel->getQuestionsByQuiz($quizId);
        
        $quiz['questions'] = $questions;
        $quiz['question_count'] = count($questions);
        
        return $quiz;
    }
    
    // Get quizzes for a class
    public function getQuizzesByClass($classId, $teacherId = null, $includeQuestions = false) {
        $params = [$classId];
        $sql = "SELECT q.*, 
                (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count,
                (SELECT COUNT(DISTINCT r.student_id) FROM results r WHERE r.quiz_id = q.id) as student_count
                FROM quizzes q
                WHERE q.class_id = ?";
        
        if ($teacherId !== null) {
            $sql .= " AND q.teacher_id = ?";
            $params[] = $teacherId;
        }
        
        $sql .= " ORDER BY q.created_at DESC";
        
        $quizzes = $this->query($sql, $params)->fetchAll();
        
        if ($includeQuestions) {
            $questionModel = new Question();
            foreach ($quizzes as &$quiz) {
                $quiz['questions'] = $questionModel->getQuestionsByQuiz($quiz['id']);
            }
        }
        
        return $quizzes;
    }
    
    // Get active quizzes for a student
    public function getActiveQuizzesForStudent($studentId) {
        $sql = "SELECT q.*, c.name as class_name, 
                (SELECT COUNT(*) FROM results r WHERE r.quiz_id = q.id AND r.student_id = ?) as attempt_count
                FROM quizzes q
                JOIN students s ON q.class_id = s.class_id
                JOIN classes c ON q.class_id = c.id
                WHERE s.id = ?
                AND q.is_active = 1
                AND (q.start_time IS NULL OR q.start_time <= NOW())
                AND (q.end_time IS NULL OR q.end_time >= NOW())
                AND (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) > 0
                ORDER BY q.start_time DESC, q.created_at DESC";
                
        return $this->query($sql, [$studentId, $studentId])->fetchAll();
    }
    
    // Check if a student can take a quiz
    public function canStudentTakeQuiz($quizId, $studentId) {
        $sql = "SELECT q.*, 
                (SELECT COUNT(*) FROM results r WHERE r.quiz_id = q.id AND r.student_id = ?) as attempt_count,
                (SELECT COUNT(*) FROM kicked_students k WHERE k.quiz_id = q.id AND k.student_id = ?) as is_kicked
                FROM quizzes q
                JOIN students s ON q.class_id = s.class_id
                WHERE q.id = ? AND s.id = ?";
                
        $quiz = $this->query($sql, [$studentId, $studentId, $quizId, $studentId])->fetch();
        
        if (!$quiz) {
            throw new Exception('Quiz not found or you are not enrolled in this class', 404);
        }
        
        if ($quiz['is_kicked']) {
            throw new Exception('You have been removed from this quiz', 403);
        }
        
        if (!$quiz['is_active']) {
            throw new Exception('This quiz is not active', 400);
        }
        
        if ($quiz['start_time'] && strtotime($quiz['start_time']) > time()) {
            throw new Exception('This quiz has not started yet', 400);
        }
        
        if ($quiz['end_time'] && strtotime($quiz['end_time']) < time()) {
            throw new Exception('This quiz has ended', 400);
        }
        
        if ($quiz['attempt_count'] > 0) {
            // Check if multiple attempts are allowed (not implemented in this version)
            throw new Exception('You have already attempted this quiz', 400);
        }
        
        return true;
    }
}
