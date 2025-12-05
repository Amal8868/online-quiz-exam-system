<?php
require_once __DIR__ . '/Model.php';

class Result extends Model {
    protected $table = 'results';
    protected $fillable = [
        'student_id', 'quiz_id', 'total_questions', 'questions_answered', 
        'correct_answers', 'score', 'time_spent', 'status', 'submitted_at'
    ];
    
    // Get a student's result for a specific quiz
    public function getStudentQuizResult($studentId, $quizId) {
        return $this->query(
            "SELECT * FROM {$this->table} 
             WHERE student_id = ? AND quiz_id = ?",
            [$studentId, $quizId]
        )->fetch();
    }
    
    // Get detailed results for a specific attempt
    public function getDetailedResult($resultId, $studentId = null) {
        $params = [$resultId];
        $sql = "SELECT r.*, q.title as quiz_title, q.description as quiz_description,
                s.name as student_name, s.student_id,
                c.name as class_name, c.section,
                (SELECT COUNT(*) FROM questions WHERE quiz_id = r.quiz_id) as total_questions
                FROM {$this->table} r
                JOIN quizzes q ON r.quiz_id = q.id
                JOIN students s ON r.student_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE r.id = ?";
        
        if ($studentId !== null) {
            $sql .= " AND r.student_id = ?";
            $params[] = $studentId;
        }
        
        $result = $this->query($sql, $params)->fetch();
        
        if (!$result) {
            throw new Exception('Result not found', 404);
        }
        
        // Get all questions with student's answers
        $sql = "SELECT q.*, sa.answer_text, sa.selected_option_id, sa.is_correct, 
                sa.points_earned, sa.is_manually_graded,
                (SELECT GROUP_CONCAT(CONCAT(o.id, ':', o.option_text, ':', o.is_correct) SEPARATOR '||')
                 FROM options o WHERE o.question_id = q.id) as options
                FROM questions q
                LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.result_id = ?
                WHERE q.quiz_id = ?
                ORDER BY q.id ASC";
        
        $questions = $this->query($sql, [$resultId, $result['quiz_id']])->fetchAll();
        
        // Process options
        foreach ($questions as &$question) {
            $options = [];
            if (!empty($question['options'])) {
                $optionPairs = explode('||', $question['options']);
                foreach ($optionPairs as $pair) {
                    list($id, $text, $isCorrect) = explode(':', $pair, 3);
                    $options[] = [
                        'id' => $id,
                        'option_text' => $text,
                        'is_correct' => (bool)$isCorrect,
                        'is_selected' => ($question['selected_option_id'] == $id)
                    ];
                }
            }
            $question['options'] = $options;
            unset($question['options_raw']);
            
            // For manual grading
            if ($question['is_manually_graded'] === null) {
                $question['is_manually_graded'] = ($question['question_type'] === 'short_answer');
            }
        }
        
        $result['questions'] = $questions;
        
        // Calculate statistics
        $result['percentage'] = $result['total_questions'] > 0 
            ? round(($result['correct_answers'] / $result['total_questions']) * 100, 2) 
            : 0;
            
        $result['grade'] = $this->calculateGrade($result['percentage']);
        
        return $result;
    }
    
    // Get results for a quiz (for teachers)
    public function getQuizResults($quizId, $teacherId = null) {
        $params = [$quizId];
        $sql = "SELECT r.*, s.name as student_name, s.student_id,
                (SELECT COUNT(*) FROM questions WHERE quiz_id = r.quiz_id) as total_questions,
                (SELECT COUNT(*) FROM student_answers sa 
                 WHERE sa.result_id = r.id AND sa.is_correct = 1) as correct_answers
                FROM {$this->table} r
                JOIN students s ON r.student_id = s.id";
        
        if ($teacherId !== null) {
            $sql .= " JOIN quizzes q ON r.quiz_id = q.id AND q.teacher_id = ?";
            $params[] = $teacherId;
        }
        
        $sql .= " WHERE r.quiz_id = ?
                 ORDER BY r.score DESC, s.name";
        
        $results = $this->query($sql, $params)->fetchAll();
        
        // Calculate additional statistics
        foreach ($results as &$result) {
            $result['percentage'] = $result['total_questions'] > 0 
                ? round(($result['correct_answers'] / $result['total_questions']) * 100, 2) 
                : 0;
                
            $result['grade'] = $this->calculateGrade($result['percentage']);
            
            // Get number of questions that need manual grading
            $sql = "SELECT COUNT(*) as count 
                    FROM student_answers sa
                    JOIN questions q ON sa.question_id = q.id
                    WHERE sa.result_id = ? 
                    AND q.question_type = 'short_answer'
                    AND sa.is_correct IS NULL";
                    
            $needsGrading = $this->query($sql, [$result['id']])->fetch();
            $result['needs_grading'] = (int)$needsGrading['count'];
        }
        
        return $results;
    }
    
    // Grade a student's answer (for short answers)
    public function gradeAnswer($resultId, $questionId, $isCorrect, $teacherId) {
        $this->beginTransaction();
        
        try {
            // Verify the teacher has permission to grade this answer
            $sql = "SELECT sa.*, q.quiz_id, q.points, qz.teacher_id
                    FROM student_answers sa
                    JOIN questions q ON sa.question_id = q.id
                    JOIN quizzes qz ON q.quiz_id = qz.id
                    WHERE sa.result_id = ? AND sa.question_id = ? AND qz.teacher_id = ?";
                    
            $answer = $this->query($sql, [$resultId, $questionId, $teacherId])->fetch();
            
            if (!$answer) {
                throw new Exception('Answer not found or access denied', 404);
            }
            
            // Update the answer
            $points = $isCorrect ? $answer['points'] : 0;
            
            $this->query(
                "UPDATE student_answers 
                 SET is_correct = ?, points_earned = ?, is_manually_graded = 1
                 WHERE result_id = ? AND question_id = ?",
                [$isCorrect ? 1 : 0, $points, $resultId, $questionId]
            );
            
            // Update the result summary
            $this->updateResultScore($resultId);
            
            $this->commit();
            
            return [
                'success' => true,
                'points_earned' => $points,
                'is_correct' => (bool)$isCorrect
            ];
            
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }
    
    // Update the result's score based on all answers
    private function updateResultScore($resultId) {
        $sql = "UPDATE results r
                SET score = (
                    SELECT COALESCE(SUM(sa.points_earned), 0)
                    FROM student_answers sa
                    WHERE sa.result_id = r.id
                ),
                correct_answers = (
                    SELECT COUNT(*)
                    FROM student_answers sa
                    WHERE sa.result_id = r.id AND sa.is_correct = 1
                )
                WHERE r.id = ?";
                
        $this->query($sql, [$resultId]);
    }
    
    // Calculate grade based on percentage
    private function calculateGrade($percentage) {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 60) return 'D';
        return 'F';
    }
}
