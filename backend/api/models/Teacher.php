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
    
    // Get all quizzes for a teacher (updated to not use classes)
    public function getQuizzes($teacherId) {
        $sql = "SELECT q.*, 
                (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count,
                (SELECT COUNT(*) FROM quiz_allowed_students WHERE quiz_id = q.id) as allowed_students
                FROM quizzes q
                WHERE q.teacher_id = ?
                ORDER BY q.created_at DESC";
                
        return $this->query($sql, [$teacherId])->fetchAll();
    }
}
