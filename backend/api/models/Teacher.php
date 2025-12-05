<?php
require_once __DIR__ . '/Model.php';

class Teacher extends Model {
    protected $table = 'teachers';
    protected $fillable = ['name', 'email', 'password'];

    // Register a new teacher
    public function register($name, $email, $password) {
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            throw new Exception('Email already registered', 409); // 409 Conflict
        }
        
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Create the teacher
        try {
            $teacherId = $this->create([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword
            ]);
            return $teacherId;
        } catch (PDOException $e) {
            throw new Exception('Database error: ' . $e->getMessage(), 500);
        }
    }
    
    // Authenticate a teacher
    public function login($email, $password) {
        $teacher = $this->query("SELECT * FROM {$this->table} WHERE email = ?", [$email])->fetch();
        
        if (!$teacher || !password_verify($password, $teacher['password'])) {
            throw new Exception('Invalid email or password');
        }
        
        // Remove password before returning
        unset($teacher['password']);
        
        // Generate JWT token
        $token = AuthMiddleware::generateJWT([
            'id' => $teacher['id'],
            'email' => $teacher['email'],
            'role' => 'teacher'
        ]);
        
        return [
            'user' => $teacher,
            'token' => $token
        ];
    }
    
    // Generate a unique room code for a quiz
    public function generateRoomCode() {
        $db = getDBConnection();
        
        do {
            $roomCode = '';
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            
            for ($i = 0; $i < 6; $i++) {
                $roomCode .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            // Check if room code already exists
            $stmt = $db->prepare("SELECT id FROM quizzes WHERE room_code = ?");
            $stmt->execute([$roomCode]);
            $exists = $stmt->fetch();
            
        } while ($exists);
        
        return $roomCode;
    }
    
    // Get all quizzes for a teacher
    public function getQuizzes($teacherId) {
        $sql = "SELECT q.*, c.name as class_name, 
                (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count,
                (SELECT COUNT(DISTINCT r.student_id) FROM results r WHERE r.quiz_id = q.id) as student_count
                FROM quizzes q
                JOIN classes c ON q.class_id = c.id
                WHERE q.teacher_id = ?
                ORDER BY q.created_at DESC";
                
        return $this->query($sql, [$teacherId])->fetchAll();
    }
    
    // Get quiz results
    public function getQuizResults($quizId, $teacherId) {
        $sql = "SELECT r.*, s.name as student_name, s.student_id, 
                (SELECT COUNT(*) FROM student_answers sa WHERE sa.result_id = r.id AND sa.is_correct = 1) as correct_answers
                FROM results r
                JOIN students s ON r.student_id = s.id
                JOIN quizzes q ON r.quiz_id = q.id
                WHERE r.quiz_id = ? AND q.teacher_id = ?";
                
        return $this->query($sql, [$quizId, $teacherId])->fetchAll();
    }
}
