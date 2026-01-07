<?php
// This is the "Exam Submission" controller. 
// It handles the "Life Cycle" of a student's exam attempt: from start to finish.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Quiz.php';

class SubmissionController extends BaseController {
    // 1. "I'm starting!" - This creates a new 'result' row marked as 'in_progress'. 
    // It's like putting a name on a blank test paper.
    public function createSubmission($studentId, $quizId, $data) {
        try {
            $resultModel = new Result();
            
            $resultId = $resultModel->create([
                'student_id' => $studentId,
                'quiz_id' => $quizId,
                'status' => 'in_progress',
                'started_at' => date('Y-m-d H:i:s')
            ]);
            return $this->success(['submission_id' => $resultId], 'Submission created', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 2. "Look at one test" - Fetches details of a specific attempt.
    public function getSubmission($teacherId, $submissionId) {
        try {
            $resultModel = new Result();
            $submission = $resultModel->find($submissionId);
            if (!$submission) {
                return $this->error('Submission not found', 404);
            }
            
            // SECURITY: Make sure the teacher asking for this test is the one who created the quiz.
            $quizModel = new Quiz();
            $quiz = $quizModel->find($submission['quiz_id']);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Unauthorized', 403);
            }
            return $this->success($submission);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 3. "Adjust results" - Used for updating scores or changing the status (e.g. from 'in_progress' to 'finished').
    public function updateSubmission($teacherId, $submissionId, $data) {
        try {
            $resultModel = new Result();
            $submission = $resultModel->find($submissionId);
            if (!$submission) {
                return $this->error('Submission not found', 404);
            }
            
            // Verify ownership again!
            $quizModel = new Quiz();
            $quiz = $quizModel->find($submission['quiz_id']);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Unauthorized', 403);
            }
            
            // A teacher is only allowed to change the total score and the status here.
            $allowed = ['score', 'status'];
            $updateData = [];
            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            if (empty($updateData)) {
                return $this->error('No valid fields to update', 400);
            }
            
            $resultModel->update($submissionId, $updateData);
            return $this->success(['message' => 'Submission updated']);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 4. "Oops, delete it" - Completely removes an attempt. 
    // Useful if a student had a technical glitch and needs to restart from scratch.
    public function deleteSubmission($teacherId, $submissionId) {
        try {
            $resultModel = new Result();
            $submission = $resultModel->find($submissionId);
            if (!$submission) {
                return $this->error('Submission not found', 404);
            }
            
            // SECURITY check: only the quiz owner can delete submissions.
            $quizModel = new Quiz();
            $quiz = $quizModel->find($submission['quiz_id']);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Unauthorized', 403);
            }
            
            $resultModel->delete($submissionId);
            return $this->success(['message' => 'Submission deleted']);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
?>
