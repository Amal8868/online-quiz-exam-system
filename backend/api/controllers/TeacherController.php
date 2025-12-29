<?php
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Quiz.php';

class TeacherController {
    private $teacherModel;
    private $quizModel;
    
    public function __construct() {
        $this->teacherModel = new Teacher();
        $this->quizModel = new Quiz();
    }
    
    // Register a new teacher
    public function register($data) {
        try {
            if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
                throw new Exception('Name, email, and password are required', 400);
            }

            $name = trim($data['name']);
            $email = trim($data['email']);
            $password = $data['password'];

            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception('Fields cannot be empty', 400);
            }
            
            if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
                throw new Exception('Name can only contain letters and spaces (no numbers allowed)', 400);
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please provide a valid email address', 400);
            }
            
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters long', 400);
            }
            
            $teacherId = $this->teacherModel->register($name, $email, $password);
            
            $token = AuthMiddleware::generateJWT([
                'id' => $teacherId,
                'email' => $email,
                'role' => 'teacher'
            ]);
            
            return [
                'success' => true,
                'message' => 'Teacher registered successfully',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $teacherId,
                        'name' => $name,
                        'email' => $email,
                        'role' => 'teacher'
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Teacher login
    public function login($data) {
        try {
            if (empty($data['email']) || empty($data['password'])) {
                throw new Exception('Email and password are required', 400);
            }
            
            $result = $this->teacherModel->login($data['email'], $data['password']);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'data' => $result
            ];
            
        } catch (Exception $e) {
            http_response_code(401);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Reset password
    public function resetPassword($data) {
        try {
            if (empty($data['email']) || empty($data['password'])) {
                throw new Exception('Email and password are required', 400);
            }
            
            $this->teacherModel->resetPassword($data['email'], $data['password']);
            
            return [
                'success' => true,
                'message' => 'Password reset successful'
            ];
            
        } catch (Exception $e) {
            http_response_code(500);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Get teacher's dashboard data
    public function getDashboard($teacherId) {
        try {
            // Get teacher's quizzes
            $quizzes = $this->teacherModel->getQuizzes($teacherId); 
            // Note: I need to ensure TeacherModel::getQuizzes is updated to not join classes.
            // I'll update TeacherModel as well if needed, or query QuizModel here.
            
            // To be safe, I'll use QuizModel here if I implement a method there.
            // Let's assume I fix TeacherModel later or use a raw query here if needed, 
            // but for now I'll trust TeacherModel.php (wait, I haven't updated it yet to remove class join).
            // Actually, I should update Teacher.php to remove class dependency too.
            
            $stats = [
                'total_quizzes' => count($quizzes),
                'active_quizzes' => array_reduce($quizzes, function($carry, $quiz) {
                    return $carry + ($quiz['status'] === 'active' ? 1 : 0);
                }, 0),
                'total_students' => (int)$this->teacherModel->getTotalStudents($teacherId)
            ];
            
            return [
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_quizzes' => array_slice($quizzes, 0, 5)
                ]
            ];
            
        } catch (Exception $e) {
            http_response_code(500);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
