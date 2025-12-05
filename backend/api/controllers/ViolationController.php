<?php
// backend/api/controllers/ViolationController.php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Violation.php';
require_once __DIR__ . '/../models/Quiz.php';

class ViolationController extends BaseController {
    private $violationModel;

    public function __construct() {
        $this->violationModel = new Violation();
    }

    // Record a new violation
    public function recordViolation($data) {
        try {
            $this->validateRequiredFields($data, [
                'student_id',
                'quiz_id',
                'violation_type'
            ]);

            $violationId = $this->violationModel->create([
                'student_id' => $data['student_id'],
                'quiz_id' => $data['quiz_id'],
                'violation_type' => $data['violation_type'],
                'description' => $data['description'] ?? null,
                'severity' => $data['severity'] ?? 'medium',
                'recorded_by' => $data['recorded_by'] ?? null
            ]);

            return $this->success([
                'violation_id' => $violationId
            ], 'Violation recorded successfully');

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // Get violations for a quiz
    public function getQuizViolations($quizId, $teacherId) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->getQuiz($quizId);
            
            if ($quiz['teacher_id'] != $teacherId) {
                throw new Exception('Unauthorized', 403);
            }

            $violations = $this->violationModel->getByQuizId($quizId);
            return $this->success($violations);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // Get violations for a student
    public function getStudentViolations($studentId) {
        try {
            $violations = $this->violationModel->getByStudentId($studentId);
            return $this->success($violations);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}