<?php
// This is the "Teacher Management" controller. 
// It handles everything specific to teachers: signing up, looking at their dashboard, and changing passwords.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/User.php';

class TeacherController extends BaseController {
    private $teacherModel;
    private $quizModel;
    private $userModel;
    
    public function __construct() {
        // We initialize all the models we need to talk to.
        $this->teacherModel = new Teacher();
        $this->quizModel = new Quiz();
        $this->userModel = new User();
    }
    
    // 1. "I want to join!" - Handles teacher registration.
    public function register($data) {
        try {
            // Validate that they didn't leave anything blank.
            if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
                throw new Exception('Name, email, and password are required', 400);
            }

            $name = trim($data['name']);
            $email = trim($data['email']);
            $password = $data['password'];
            $gender = isset($data['gender']) ? $data['gender'] : null;

            // SECURITY: Check that the name doesn't contain numbers.
            if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
                throw new Exception('Name can only contain letters and spaces (no numbers allowed)', 400);
            }
            
            // SECURITY: Ensure it's a real-looking email.
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please provide a valid email address', 400);
            }
            
            // SECURITY: Make sure the password isn't too weak.
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters long', 400);
            }

            // Check if this teacher has already signed up before.
            if ($this->userModel->findByEmail($email)) {
                throw new Exception('Email already registered', 409);
            }

            // We split "Amal N" into "Amal" and "N".
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0];
            $lastName = isset($parts[1]) ? $parts[1] : '';

            // We generate a clean username like 'tea_1001'.
            $username = $this->userModel->generateSequentialUsername('Teacher');
            
            // We create a unique ID for this teacher.
            $userId = 'TCH' . time() . rand(100, 999);

            $newUser = [
                'user_id' => $userId, 
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $username,
                'email' => $email,
                'password' => $password, 
                'user_type' => 'Teacher',
                'gender' => $gender,
                'status' => 'Active',
                // We mark it as 'first_login' so we can remind them to change their temporary password if needed.
                'first_login' => 1
            ];

            $id = $this->userModel->createUser($newUser);

            if (!$id) {
                throw new Exception('Failed to register user', 500);
            }
            
            // Give them their "VIP Pass" (JWT) right away!
            $token = AuthMiddleware::generateJWT([
                'id' => $id,
                'email' => $email,
                'role' => 'Teacher'
            ]);
            
            return [
                'success' => true,
                'message' => 'Teacher registered successfully',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $id,
                        'name' => $name,
                        'email' => $email,
                        'role' => 'Teacher',
                        'gender' => $gender
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
    
    // 2. "Let me in!" - Redirects to the teacher model for verification.
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

    // 3. "I forgot my password" - Helps teachers get back in.
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
    
    // 4. "Overview" - Gathers the numbers for the dashboard cards (Total Quizzes, Students, etc.)
    public function getDashboard($teacherId) {
        try {
            // Fetch everything the teacher has created.
            $quizzes = $this->teacherModel->getQuizzes($teacherId); 
            
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
                    'recent_quizzes' => array_slice($quizzes, 0, 5) // Just show the top 5.
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

    // 5. "Update Security" - Used for changing passwords.
    public function changePassword($teacherId, $data) {
        try {
            if (empty($data['new_password'])) {
                throw new Exception('New password is required', 400);
            }
            
            if (strlen($data['new_password']) < 6) {
                throw new Exception('Password must be at least 6 characters long', 400);
            }
            
            $this->userModel->updateUser($teacherId, [
                'password' => $data['new_password'],
                'first_login' => 0 // They've officially changed their temp password now.
            ]);
            
            return $this->success(null, 'Password changed successfully');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
