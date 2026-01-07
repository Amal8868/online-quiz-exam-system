<?php
// This is the "Question Editor" controller. 
// It handles editing and deleting individual questions within a quiz.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Question.php';
require_once __DIR__ . '/../models/Quiz.php';

class QuestionController extends BaseController {
    // 1. "Show me the list" - Gets all the questions for a specific quiz.
    public function getQuestions($teacherId, $quizId) {
        $quizModel = new Quiz();
        $quiz = $quizModel->find($quizId);
        
        // SECURITY: We check if the quiz exists and if the teacher asking for it is the owner.
        if (!$quiz || $quiz['teacher_id'] != $teacherId) {
            return $this->error('Quiz not found or unauthorized', 404);
        }
        
        $questionModel = new Question();
        $questions = $questionModel->getByQuizId($quizId);
        return $this->success($questions);
    }

    // 2. "Fix a typo" - Updates the text, points, or options of a question.
    public function updateQuestion($teacherId, $questionId, $data) {
        $questionModel = new Question();
        $question = $questionModel->find($questionId);
        if (!$question) {
            return $this->error('Question not found', 404);
        }
        
        // Ownership Check: We find the quiz this question belongs to and check the teacher ID.
        $quizModel = new Quiz();
        $quiz = $quizModel->find($question['quiz_id']);
        if (!$quiz || $quiz['teacher_id'] != $teacherId) {
            return $this->error('Unauthorized', 403);
        }
        
        // These are the things a teacher is allowed to change.
        $allowed = ['question_text', 'question_type', 'options', 'correct_answer', 'points', 'time_limit_seconds'];
        $updateData = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                // If the options are in a list (array), we convert them to a JSON string for the database.
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

    // 3. "Remove it" - Deletes a question permanently.
    public function deleteQuestion($teacherId, $questionId) {
        $questionModel = new Question();
        $question = $questionModel->find($questionId);
        if (!$question) {
            return $this->error('Question not found', 404);
        }
        
        // Always check permissions before deleting!
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
