<?php
require_once __DIR__ . '/Model.php';

class StudentAnswer extends Model {
    protected $table = 'student_answers';
    protected $fillable = [
        'result_id', 'student_id', 'question_id', 'selected_option_id',
        'answer_text', 'is_correct', 'points_earned', 'time_spent', 'is_manually_graded'
    ];
    
    // Get all answers for a result
    public function getAnswersByResult($resultId) {
        return $this->query(
            "SELECT sa.*, q.question_text, q.question_type, q.points,
                    (SELECT option_text FROM options WHERE id = sa.selected_option_id) as selected_option_text
             FROM {$this->table} sa
             JOIN questions q ON sa.question_id = q.id
             WHERE sa.result_id = ?
             ORDER BY sa.id ASC",
            [$resultId]
        )->fetchAll();
    }
    
    // Save or update a student's answer
    public function saveAnswer($resultId, $studentId, $questionId, $selectedOptionId = null, $answerText = null, $timeSpent = 0) {
        // Check if answer already exists
        $existing = $this->query(
            "SELECT id FROM {$this->table} 
             WHERE result_id = ? AND question_id = ?",
            [$resultId, $questionId]
        )->fetch();
        
        // Get question details
        $question = $this->query(
            "SELECT * FROM questions WHERE id = ?",
            [$questionId]
        )->fetch();
        
        if (!$question) {
            throw new Exception('Question not found', 404);
        }
        
        // For MCQs and True/False, validate the selected option
        $isCorrect = null;
        $pointsEarned = 0;
        
        if (in_array($question['question_type'], ['mcq', 'true_false'])) {
            if ($selectedOptionId) {
                $optionModel = new Option();
                $isCorrect = $optionModel->isCorrectAnswer($selectedOptionId, $questionId) ? 1 : 0;
                $pointsEarned = $isCorrect ? $question['points'] : 0;
            }
        }
        
        $answerData = [
            'result_id' => $resultId,
            'student_id' => $studentId,
            'question_id' => $questionId,
            'selected_option_id' => $selectedOptionId,
            'answer_text' => $answerText,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'time_spent' => $timeSpent,
            'is_manually_graded' => ($question['question_type'] === 'short_answer')
        ];
        
        if ($existing) {
            // Update existing answer
            $this->update($existing['id'], $answerData);
            return $existing['id'];
        } else {
            // Create new answer
            return $this->create($answerData);
        }
    }
    
    // Get the number of answered questions for a result
    public function getAnsweredCount($resultId) {
        $result = $this->query(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE result_id = ?",
            [$resultId]
        )->fetch();
        
        return (int)$result['count'];
    }
    
    // Get the next unanswered question for a student
    public function getNextUnansweredQuestion($resultId, $quizId) {
        $sql = "SELECT q.* 
                FROM questions q
                LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.result_id = ?
                WHERE q.quiz_id = ? AND sa.id IS NULL
                ORDER BY q.id ASC
                LIMIT 1";
                
        $question = $this->query($sql, [$resultId, $quizId])->fetch();
        
        if ($question) {
            // Get options for MCQs and True/False
            if (in_array($question['question_type'], ['mcq', 'true_false'])) {
                $optionModel = new Option();
                $question['options'] = $optionModel->getOptionsByQuestion($question['id']);
            } else {
                $question['options'] = [];
            }
        }
        
        return $question;
    }
}
