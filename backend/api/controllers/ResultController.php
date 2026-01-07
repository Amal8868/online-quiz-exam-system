<?php
// This is the "Grading Hub" controller. 
// it handles looking at student scores and letting teachers manually grade short-answer questions.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/Quiz.php';

class ResultController extends BaseController {
    
    // 1. "How did the class do?" - Fetches all results for a specific quiz taken by a specific class.
    public function getClassQuizResults($teacherId, $classId, $quizId) {
        try {
            // Security check: We verify that the teacher actually owns this class before showing scores.
            $classModel = new ClassModel();
            $class = $classModel->getClassWithStudents($classId, $teacherId); 
            
            $resultModel = new Result();
            $results = $resultModel->getResultsByClassAndQuiz($classId, $quizId);
            
            return $this->success($results);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // 2. "Individual Breakdown" - Shows exactly what a single student answered for every question.
    public function getStudentResult($teacherId, $resultId) {
        try {
            $resultModel = new Result();
            $data = $resultModel->getStudentResultDetails($resultId);
            
            if (!$data) {
                return $this->error('Result not found', 404);
            }
            
            return $this->success($data);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 3. "Manual Grade" - This is for when a teacher reads a short answer and decides the points.
    public function saveGrade($teacherId, $resultId) {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['question_id']) || !isset($data['points'])) {
            return $this->error('Missing required fields', 400);
        }

        try {
            $resultModel = new Result();
            
            // We tell the Result model to update the points specifically for this question in this attempt.
            $success = $resultModel->updateManualGrade($resultId, $data['question_id'], $data['points']);
            
            if ($success) {
                return $this->success(['message' => 'Grade updated']);
            } else {
                return $this->error('Failed to update grade', 500);
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400); 
        }
    }
}
