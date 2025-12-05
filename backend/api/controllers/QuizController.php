<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/Question.php';

class QuizController extends BaseController {
    // Get all quizzes for a teacher
    public function getQuizzes($teacherId) {
        try {
            $quiz = new Quiz();
            $quizzes = $quiz->getQuizzes($teacherId);
            return $this->success($quizzes);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    // Create a new quiz
    public function createQuiz($teacherId, $data) {
        try {
            $this->validateRequiredFields($data, ['class_id', 'title']);
            
            $quiz = new Quiz();
            $result = $quiz->createQuiz(
                $teacherId,
                $data['class_id'],
                $data['title'],
                $data['description'] ?? '',
                $data['time_limit'] ?? 30,
                $data['start_time'] ?? null,
                $data['end_time'] ?? null
            );
            
            return $this->success($result, 'Quiz created successfully', 201);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    // Get quiz details with questions
    public function getQuiz($teacherId, $quizId) {
        try {
            $quiz = new Quiz();
            $quizData = $quiz->getQuizWithQuestions($quizId, $teacherId);
            return $this->success($quizData);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    // Add question to quiz
    public function addQuestion($teacherId, $quizId, $data) {
        try {
            $this->validateRequiredFields($data, ['question_text', 'question_type']);
            
            $question = new Question();
            $questionId = $question->createQuestion(
                $quizId,
                $data['question_text'],
                $data['question_type'],
                $data['points'] ?? 1,
                $data['time_limit'] ?? null,
                $data['options'] ?? []
            );
            
            return $this->success(['question_id' => $questionId], 'Question added successfully', 201);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
