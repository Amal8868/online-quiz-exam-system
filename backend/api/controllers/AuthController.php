<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController extends BaseController {
    // Teacher registration
    public function register($data) {
        try {
            $this->validateRequiredFields($data, ['name', 'email', 'password']);
            
            $teacher = new Teacher();
            $teacherId = $teacher->register(
                $data['name'],
                $data['email'],
                $data['password']
            );
            
            // Generate JWT token
            $token = AuthMiddleware::generateJWT([
                'id' => $teacherId,
                'email' => $data['email'],
                'role' => 'teacher'
            ]);
            
            return $this->success(['token' => $token], 'Registration successful', 201);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    // Teacher login
    public function login($data) {
        try {
            $this->validateRequiredFields($data, ['email', 'password']);
            
            $teacher = new Teacher();
            $result = $teacher->login($data['email'], $data['password']);
            
            return $this->success($result, 'Login successful');
            
        } catch (Exception $e) {
            return $this->error('Invalid email or password', 401);
        }
    }
    
    // Student verification
    public function verifyStudent($data) {
        try {
            $this->validateRequiredFields($data, ['student_id', 'class_id']);
            
            $student = new Student();
            $result = $student->verifyStudent($data['student_id'], $data['class_id']);
            
            return $this->success($result, 'Student verified');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
