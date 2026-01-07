<?php
// This is the model for Questions. 
// Every quiz has a bunch of these, and each one holds the points and the correct answer.
require_once __DIR__ . '/Model.php';

class Question extends Model {
    protected $table = 'questions';
    protected $fillable = [
        'quiz_id', 'question_text', 'question_type', 
        'options', 'correct_answer', 'points', 'time_limit_seconds'
    ];

    // This fetches all the questions for a specific quiz.
    public function getByQuizId($quizId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE quiz_id = ? ORDER BY id ASC");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll();
        
        // My little trick: The 'options' (like A, B, C, D) are stored as a JSON string in the DB.
        // I "decode" them here so the frontend can easily read them as a list.
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
