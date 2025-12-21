<?php
require_once __DIR__ . '/Model.php';

class Question extends Model {
    protected $table = 'questions';
    protected $fillable = [
        'quiz_id', 'question_text', 'question_type', 
        'options', 'correct_answer', 'points', 'time_limit_seconds'
    ];

    public function getByQuizId($quizId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE quiz_id = ? ORDER BY id ASC");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll();
        
        // Decode JSON options
        foreach ($questions as &$q) {
            if (!empty($q['options']) && is_string($q['options'])) {
                $decoded = json_decode($q['options'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $q['options'] = $decoded;
                }
            }
        }
        
        return $questions;
    }
}
