<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/Quiz.php';

class ResultController extends BaseController {
    private $resultModel;
    private $quizModel;

    public function __construct() {
        $this->resultModel = new Result();
        $this->quizModel = new Quiz();
    }

    // Get results for a quiz (teacher view)
    public function getQuizResults($teacherId, $quizId) {
        try {
            // Verify teacher owns this quiz
            $quiz = $this->quizModel->getQuiz($quizId);
            if ($quiz['teacher_id'] != $teacherId) {
                throw new Exception('Unauthorized', 403);
            }

            $results = $this->resultModel->getQuizResults($quizId);
            return $this->success($results);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // Grade a student's answer (for manual grading)
    public function gradeAnswer($teacherId, $attemptId, $questionId, $score) {
        try {
            $result = $this->resultModel->gradeAnswer($attemptId, $questionId, $score, $teacherId);
            return $this->success($result, 'Answer graded successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // Get detailed result for a student
    public function getStudentResult($teacherId, $attemptId) {
        try {
            $result = $this->resultModel->getDetailedResult($attemptId, $teacherId);
            return $this->success($result);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
