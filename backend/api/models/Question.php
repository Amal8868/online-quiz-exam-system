<?php
require_once __DIR__ . '/Model.php';

class Question extends Model {
    protected $table = 'questions';
    protected $fillable = ['quiz_id', 'question_text', 'question_type', 'points', 'time_limit'];
    
    // Create a new question with options
    public function createQuestion($quizId, $questionText, $questionType, $points = 1, $timeLimit = null, $options = []) {
        $this->beginTransaction();
        
        try {
            // Verify the quiz exists and get the teacher_id
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            
            if (!$quiz) {
                throw new Exception('Quiz not found', 404);
            }
            
            // Create the question
            $questionId = $this->create([
                'quiz_id' => $quizId,
                'question_text' => $questionText,
                'question_type' => $questionType,
                'points' => $points,
                'time_limit' => $timeLimit
            ]);
            
            // Add options if provided (for MCQs and True/False)
            if (in_array($questionType, ['mcq', 'true_false']) && !empty($options)) {
                $optionModel = new Option();
                
                foreach ($options as $option) {
                    $optionModel->create([
                        'question_id' => $questionId,
                        'option_text' => $option['text'],
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0
                    ]);
                }
            }
            
            $this->commit();
            return $questionId;
            
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }
    
    // Get questions for a quiz
    public function getQuestionsByQuiz($quizId, $includeOptions = true) {
        $questions = $this->query(
            "SELECT * FROM {$this->table} WHERE quiz_id = ? ORDER BY id ASC", 
            [$quizId]
        )->fetchAll();
        
        if ($includeOptions) {
            $optionModel = new Option();
            
            foreach ($questions as &$question) {
                if (in_array($question['question_type'], ['mcq', 'true_false'])) {
                    $question['options'] = $optionModel->getOptionsByQuestion($question['id']);
                } else {
                    $question['options'] = [];
                }
            }
        }
        
        return $questions;
    }
    
    // Get a single question with options
    public function getQuestionWithOptions($questionId) {
        $question = $this->find($questionId);
        
        if (!$question) {
            throw new Exception('Question not found', 404);
        }
        
        if (in_array($question['question_type'], ['mcq', 'true_false'])) {
            $optionModel = new Option();
            $question['options'] = $optionModel->getOptionsByQuestion($questionId);
        } else {
            $question['options'] = [];
        }
        
        return $question;
    }
    
    // Update a question and its options
    public function updateQuestion($questionId, $questionText, $points = null, $timeLimit = null, $options = null) {
        $this->beginTransaction();
        
        try {
            $updates = [];
            
            if ($questionText !== null) {
                $updates['question_text'] = $questionText;
            }
            
            if ($points !== null) {
                $updates['points'] = $points;
            }
            
            if ($timeLimit !== null) {
                $updates['time_limit'] = $timeLimit;
            }
            
            if (!empty($updates)) {
                $this->update($questionId, $updates);
            }
            
            // Update options if provided
            if ($options !== null) {
                $question = $this->find($questionId);
                
                if (in_array($question['question_type'], ['mcq', 'true_false'])) {
                    $optionModel = new Option();
                    
                    // Delete existing options
                    $optionModel->deleteByQuestion($questionId);
                    
                    // Add new options
                    foreach ($options as $option) {
                        $optionModel->create([
                            'question_id' => $questionId,
                            'option_text' => $option['text'],
                            'is_correct' => !empty($option['is_correct']) ? 1 : 0
                        ]);
                    }
                }
            }
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }
    
    // Delete a question and its options
    public function deleteQuestion($questionId) {
        $this->beginTransaction();
        
        try {
            // Delete options first
            $optionModel = new Option();
            $optionModel->deleteByQuestion($questionId);
            
            // Delete the question
            $this->delete($questionId);
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }
    
    // Get the next question in a quiz for a student
    public function getNextQuestion($quizId, $studentId) {
        // Get questions the student hasn't answered yet
        $sql = "SELECT q.* 
                FROM questions q
                LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.student_id = ?
                WHERE q.quiz_id = ? AND sa.id IS NULL
                ORDER BY q.id ASC
                LIMIT 1";
                
        $question = $this->query($sql, [$studentId, $quizId])->fetch();
        
        if (!$question) {
            return null; // No more questions
        }
        
        // Get options for MCQs and True/False
        if (in_array($question['question_type'], ['mcq', 'true_false'])) {
            $optionModel = new Option();
            $question['options'] = $optionModel->getOptionsByQuestion($question['id']);
        } else {
            $question['options'] = [];
        }
        
        return $question;
    }
}
