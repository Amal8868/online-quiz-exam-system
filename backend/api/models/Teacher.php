<?php
// This is the "Teacher's Lounge" model. 
// It handles everything related to teachers: signing up, logging in, and seeing their quiz stats.
require_once __DIR__ . '/Model.php';

class Teacher extends Model {
    protected $table = 'teachers';
    protected $fillable = ['name', 'email', 'password'];

    // 1. "Sign Up" - Creates a new teacher account.
    public function register($name, $email, $password) {
        // First, check if someone is already using this email.
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            throw new Exception('Email already registered', 409); // 409 means "This already exists!"
        }
        
        // SECURITY: Never save a password as plain text! 
        // We "hash" it so even if someone steals the database, they can't read the passwords.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
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
    
    // 2. "Login" - Verifies the teacher and gives them a "VIP Pass" (JWT Token).
    public function login($email, $password) {
        // Look for the teacher by their email.
        $teacher = $this->query("SELECT * FROM {$this->table} WHERE email = ?", [$email])->fetch();
        
        if (!$teacher) {
            throw new Exception('Email address not found');
        }

        // Compare the password they typed with the hashed one we have.
        if (!password_verify($password, $teacher['password'])) {
            throw new Exception('Incorrect password');
        }
        
        // We hide the password before sending the teacher data back to the browser.
        unset($teacher['password']);
        
        // This generates a "Secret Token" so the teacher doesn't have to login on every page.
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
    
    // 3. "My Content" - Lists all quizzes created by this teacher.
    public function getQuizzes($teacherId) {
        // I used a "Sub-query" (the SQL inside the SELECT) to count questions 
        // and student attempts without needing to write multiple queries.
        $sql = "SELECT q.*, 
                (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count,
                (SELECT COUNT(*) FROM results r WHERE r.quiz_id = q.id) as attempt_count
                FROM quizzes q
                WHERE q.teacher_id = ?
                ORDER BY q.created_at DESC";
                
        return $this->query($sql, [$teacherId])->fetchAll();
    }

    // 4. "Impact" - Counts how many unique students this teacher is responsible for.
    public function getTotalStudents($teacherId) {
        // We look through all the classes this teacher owns or teaches in.
        $sql = "SELECT COUNT(DISTINCT cs.student_id) 
                FROM class_students cs
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN class_teachers ct ON c.id = ct.class_id 
                WHERE ct.teacher_id = ? OR c.teacher_id = ?";
        return $this->query($sql, [$teacherId, $teacherId])->fetchColumn();
    }

    // 5. "Emergency Reset" - Changes a teacher's password.
    public function resetPassword($email, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->query("UPDATE {$this->table} SET password = ? WHERE email = ?", [$hashedPassword, $email]);
    }
}
