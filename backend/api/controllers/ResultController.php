<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/Quiz.php';

class ResultController extends BaseController {
    
    // Get Results for a specific Class and Quiz
    public function getClassQuizResults($teacherId, $classId, $quizId) {
        try {
            // Security check: teacher owns class
            $classModel = new ClassModel();
            $class = $classModel->getClassWithStudents($classId, $teacherId); // Throws if not owner
            
            $resultModel = new Result();
            $results = $resultModel->getResultsByClassAndQuiz($classId, $quizId);
            
            return $this->success($results);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // Get detailed answers for grading
    public function getStudentResult($teacherId, $resultId) {
        try {
            $resultModel = new Result();
            $data = $resultModel->getStudentResultDetails($resultId);
            
            if (!$data) {
                return $this->error('Result not found', 404);
            }

            // Optional: Add security check to ensure teacher teaches this student's class
            // For now assuming if they have the ID, they can view (middleware usually handles auth token)
            
            return $this->success($data);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // Submit manual grade
    public function saveGrade($teacherId, $resultId) {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['question_id']) || !isset($data['points'])) {
            return $this->error('Missing required fields', 400);
        }

        try {
            $resultModel = new Result();
            $success = $resultModel->updateManualGrade($resultId, $data['question_id'], $data['points']);
            
            if ($success) {
                return $this->success(['message' => 'Grade updated']);
            } else {
                return $this->error('Failed to update grade', 500);
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400); // 400 for validation errors
        }
    }
}
