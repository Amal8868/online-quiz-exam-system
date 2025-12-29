<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Quiz.php';

class SubmissionController extends BaseController {
    // Student creates a submission (starts quiz)
    public function createSubmission($studentId, $quizId, $data) {
        try {
            $resultModel = new Result();
            // Insert new result with status in_progress
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

    // Teacher fetches a submission details
    public function getSubmission($teacherId, $submissionId) {
        try {
            $resultModel = new Result();
            $submission = $resultModel->find($submissionId);
            if (!$submission) {
                return $this->error('Submission not found', 404);
            }
            // Verify teacher owns the quiz
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

    // Teacher updates submission (e.g., manual grading of short answer)
    public function updateSubmission($teacherId, $submissionId, $data) {
        try {
            $resultModel = new Result();
            $submission = $resultModel->find($submissionId);
            if (!$submission) {
                return $this->error('Submission not found', 404);
            }
            $quizModel = new Quiz();
            $quiz = $quizModel->find($submission['quiz_id']);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Unauthorized', 403);
            }
            // Allow updating score and status
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

    // Delete a submission
    public function deleteSubmission($teacherId, $submissionId) {
        try {
            $resultModel = new Result();
            $submission = $resultModel->find($submissionId);
            if (!$submission) {
                return $this->error('Submission not found', 404);
            }
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
