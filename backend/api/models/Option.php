<?php
require_once __DIR__ . '/Model.php';

class Option extends Model {
    protected $table = 'options';
    protected $fillable = ['question_id', 'option_text', 'is_correct'];
    
    // Get options for a question
    public function getOptionsByQuestion($questionId) {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE question_id = ? ORDER BY id ASC", 
            [$questionId]
        )->fetchAll();
    }
    
    // Delete all options for a question
    public function deleteByQuestion($questionId) {
        return $this->query(
            "DELETE FROM {$this->table} WHERE question_id = ?", 
            [$questionId]
        );
    }
    
    // Get the correct answer for a question
    public function getCorrectAnswer($questionId) {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE question_id = ? AND is_correct = 1", 
            [$questionId]
        )->fetch();
    }
    
    // Check if an option is the correct answer
    public function isCorrectAnswer($optionId, $questionId) {
        $option = $this->query(
            "SELECT is_correct FROM {$this->table} WHERE id = ? AND question_id = ?", 
            [$optionId, $questionId]
        )->fetch();
        
        return $option && $option['is_correct'] == 1;
    }
}
