<?php
// This is the "Multiple Choice" model. 
// It handles all the possible answers (options) for a question.
require_once __DIR__ . '/Model.php';

class Option extends Model {
    protected $table = 'options';
    protected $fillable = ['question_id', 'option_text', 'is_correct'];
    
    // We use this to fetch all the choices (A, B, C, D) for a specific question.
    public function getOptionsByQuestion($questionId) {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE question_id = ? ORDER BY id ASC", 
            [$questionId]
        )->fetchAll();
    }
    
    // If a teacher deletes a question, we also need to wipe out all the old choices.
    public function deleteByQuestion($questionId) {
        return $this->query(
            "DELETE FROM {$this->table} WHERE question_id = ?", 
            [$questionId]
        );
    }
    
    // This looks at our "Answer Key" to find which option is actually the right one.
    public function getCorrectAnswer($questionId) {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE question_id = ? AND is_correct = 1", 
            [$questionId]
        )->fetch();
    }
    
    // This is like a mini-grader. It checks if the option a student picked is marked as 'correct' in our database.
    public function isCorrectAnswer($optionId, $questionId) {
        $option = $this->query(
            "SELECT is_correct FROM {$this->table} WHERE id = ? AND question_id = ?", 
            [$optionId, $questionId]
        )->fetch();
        
        return $option && $option['is_correct'] == 1;
    }
}
