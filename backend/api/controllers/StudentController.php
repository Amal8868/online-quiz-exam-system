<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/Result.php';

class StudentController extends BaseController {
    private $studentModel;
    private $quizModel;
    private $resultModel;

    public function __construct() {
        $this->studentModel = new Student();
        $this->quizModel = new Quiz();
        $this->resultModel = new Result();
    }

    // Student verification
    public function verifyStudent($data) {
        try {
            $this->validateRequiredFields($data, ['student_id', 'class_id']);
            
            $result = $this->studentModel->verifyStudent($data['student_id'], $data['class_id']);
            
            return $this->success($result, 'Student verified');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // Get quiz by room code
    public function getQuizByRoomCode($roomCode) {
        try {
            $quiz = $this->quizModel->getQuizByRoomCode($roomCode);
            
            // Check if quiz is active
            if (!$quiz['is_active']) {
                throw new Exception('This quiz is not active', 400);
            }
            
            return $this->success($quiz);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    // Submit exam
    public function submitExam($data) {
        try {
            $this->validateRequiredFields($data, ['student_id', 'quiz_id', 'answers']);
            
            $result = $this->studentModel->submitQuiz(
                $data['student_id'], 
                $data['quiz_id'], 
                $data['answers']
            );
            
            return $this->success($result, 'Exam submitted successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // Log violation
    public function logViolation($data) {
        try {
            $this->validateRequiredFields($data, ['student_id', 'quiz_id', 'violation_type']);
            
            $result = $this->studentModel->recordViolation(
                $data['student_id'], 
                $data['quiz_id'], 
                $data['violation_type']
            );
            
            return $this->success($result, 'Violation recorded');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // Get single result
    public function getResult($resultId) {
        try {
            // This method needs to be implemented in Student model or Result model
            // For now, let's assume we can fetch it via Result model
            $result = $this->resultModel->find($resultId);
            
            if (!$result) {
                throw new Exception('Result not found', 404);
            }
            
            return $this->success($result);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
