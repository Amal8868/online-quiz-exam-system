<?php
// This is the "Answer Sheet" model. 
// It keeps track of exactly which choice each student made for every question.
require_once __DIR__ . '/Model.php';

class StudentAnswer extends Model {
    protected $table = 'student_answers';
    protected $fillable = [
        'result_id', 'student_id', 'question_id', 'selected_option_id',
        'answer_text', 'is_correct', 'points_earned', 'time_spent', 'is_manually_graded'
    ];
    
    // This fetches all the answers a student gave for a particular exam attempt.
    // I also join it with the 'questions' table so we can see the text of what they answered.
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
    
    // This is the "Save Button" logic. 
    // It either saves a fresh answer or updates one if the student changed their mind.
    public function saveAnswer($resultId, $studentId, $questionId, $selectedOptionId = null, $answerText = null, $timeSpent = 0) {
        // First, check if there's already an answer for this question in this attempt.
        $existing = $this->query(
            "SELECT id FROM {$this->table} 
             WHERE result_id = ? AND question_id = ?",
            [$resultId, $questionId]
        )->fetch();
        
        // Find out what kind of question this is (MCQ, Short Answer, etc.)
        $question = $this->query(
            "SELECT * FROM questions WHERE id = ?",
            [$questionId]
        )->fetch();
        
        if (!$question) {
            throw new Exception('Question not found', 404);
        }
        
        // Instant Grading Logic: 
        // If it's a multiple choice question, we can check if it's correct right away!
        $isCorrect = null;
        $pointsEarned = 0;
        
        if (in_array($question['question_type'], ['mcq', 'true_false'])) {
            if ($selectedOptionId) {
                $optionModel = new Option();
                // Check the 'options' table to see if this ID is the right one.
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
            // Short answers can't be auto-graded, so we flag them for the teacher.
            'is_manually_graded' => ($question['question_type'] === 'short_answer')
        ];
        
        if ($existing) {
            // Update the record if they changed their answer.
            $this->update($existing['id'], $answerData);
            return $existing['id'];
        } else {
            // Create a brand new row if it's their first time answering this.
            return $this->create($answerData);
        }
    }
    
    // How many questions has the student finished so far? 
    // Useful for showing a progress bar!
    public function getAnsweredCount($resultId) {
        $result = $this->query(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE result_id = ?",
            [$resultId]
        )->fetch();
        
        return (int)$result['count'];
    }
    
    // This is like a "Next Stage" button. 
    // It finds the first question that doesn't have an answer yet.
    public function getNextUnansweredQuestion($resultId, $quizId) {
        // We use a LEFT JOIN and look for 'NULL' to see what's missing.
        $sql = "SELECT q.* 
                FROM questions q
                LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.result_id = ?
                WHERE q.quiz_id = ? AND sa.id IS NULL
                ORDER BY q.id ASC
                LIMIT 1";
                
        $question = $this->query($sql, [$resultId, $quizId])->fetch();
        
        if ($question) {
            // If it's a multiple choice question, we also need to pack the choices (options).
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
