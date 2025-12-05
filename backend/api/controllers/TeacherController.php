<?php
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/ClassModel.php';

class TeacherController {
    private $teacherModel;
    private $classModel;
    
    public function __construct() {
        $this->teacherModel = new Teacher();
        $this->classModel = new ClassModel();
    }
    
    // Register a new teacher
    public function register($data) {
        try {
            // Validate input existence
            if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
                throw new Exception('Name, email, and password are required', 400);
            }

            // Sanitize inputs
            $name = trim($data['name']);
            $email = trim($data['email']);
            $password = $data['password'];

            // Validate empty fields after trim
            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception('Fields cannot be empty', 400);
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format', 400);
            }
            
            // Validate password length
            if (strlen($password) < 6) { // Changed to 6 to match common requirements, or keep 8
                throw new Exception('Password must be at least 6 characters long', 400);
            }
            
            // Register the teacher
            $teacherId = $this->teacherModel->register($name, $email, $password);
            
            // Generate JWT token
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
            
            // Log for debugging
            file_put_contents(__DIR__ . '/../../response_log.txt', print_r($response, true));
            return $response;
            
        } catch (Exception $e) {
            $code = $e->getCode();
            // Ensure code is a valid HTTP status code
            if ($code < 100 || $code > 599) {
                $code = 500;
            }
            http_response_code($code);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Teacher login
    public function login($data) {
        try {
            // Validate input
            if (empty($data['email']) || empty($data['password'])) {
                throw new Exception('Email and password are required', 400);
            }
            
            // Authenticate the teacher
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
    
    // Get teacher's dashboard data
    public function getDashboard($teacherId) {
        try {
            // Get teacher's classes
            $classes = $this->classModel->getTeacherClasses($teacherId);
            
            // Get teacher's quizzes
            $quizzes = $this->teacherModel->getQuizzes($teacherId);
            
            // Calculate statistics
            $stats = [
                'total_classes' => count($classes),
                'total_quizzes' => count($quizzes),
                'active_quizzes' => array_reduce($quizzes, function($carry, $quiz) {
                    return $carry + ($quiz['is_active'] ? 1 : 0);
                }, 0),
                'total_students' => 0 // This would require a query to calculate
            ];
            
            return [
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_quizzes' => array_slice($quizzes, 0, 5),
                    'recent_classes' => array_slice($classes, 0, 5)
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
    
    // Update teacher profile
    public function updateProfile($teacherId, $data) {
        try {
            $updates = [];
            
            // Validate and prepare updates
            if (!empty($data['name'])) {
                $updates['name'] = trim($data['name']);
            }
            
            if (!empty($data['current_password']) && !empty($data['new_password'])) {
                // Verify current password
                $teacher = $this->teacherModel->find($teacherId);
                if (!password_verify($data['current_password'], $teacher['password'])) {
                    throw new Exception('Current password is incorrect', 400);
                }
                
                // Update password
                $updates['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updates)) {
                throw new Exception('No valid updates provided', 400);
            }
            
            // Update the teacher
            $this->teacherModel->update($teacherId, $updates);
            
            return [
                'success' => true,
                'message' => 'Profile updated successfully'
            ];
            
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
