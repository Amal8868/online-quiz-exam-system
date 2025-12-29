<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Question.php';
require_once __DIR__ . '/../models/Quiz.php';

class QuestionController extends BaseController {
    // List all questions for a quiz
    public function getQuestions($teacherId, $quizId) {
        $quizModel = new Quiz();
        $quiz = $quizModel->find($quizId);
        if (!$quiz || $quiz['teacher_id'] != $teacherId) {
            return $this->error('Quiz not found or unauthorized', 404);
        }
        $questionModel = new Question();
        $questions = $questionModel->getByQuizId($quizId);
        return $this->success($questions);
    }

    // Update a question
    public function updateQuestion($teacherId, $questionId, $data) {
        $questionModel = new Question();
        $question = $questionModel->find($questionId);
        if (!$question) {
            return $this->error('Question not found', 404);
        }
        // Verify ownership via quiz
        $quizModel = new Quiz();
        $quiz = $quizModel->find($question['quiz_id']);
        if (!$quiz || $quiz['teacher_id'] != $teacherId) {
            return $this->error('Unauthorized', 403);
        }
        // Allowed fields
        $allowed = ['question_text', 'question_type', 'options', 'correct_answer', 'points', 'time_limit_seconds'];
        $updateData = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if ($field === 'options' && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        if (empty($updateData)) {
            return $this->error('No valid fields to update', 400);
        }
        $questionModel->update($questionId, $updateData);
        return $this->success(['message' => 'Question updated']);
    }

    // Delete a question
    public function deleteQuestion($teacherId, $questionId) {
        $questionModel = new Question();
        $question = $questionModel->find($questionId);
        if (!$question) {
            return $this->error('Question not found', 404);
        }
        $quizModel = new Quiz();
        $quiz = $quizModel->find($question['quiz_id']);
        if (!$quiz || $quiz['teacher_id'] != $teacherId) {
            return $this->error('Unauthorized', 403);
        }
        $questionModel->delete($questionId);
        return $this->success(['message' => 'Question deleted']);
    }
}
?>
