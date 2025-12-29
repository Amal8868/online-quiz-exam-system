<?php
require_once __DIR__ . '/Model.php';

class Result extends Model {
    protected $table = 'results';
    protected $fillable = ['student_id', 'quiz_id', 'score', 'total_points', 'status', 'started_at', 'submitted_at', 'is_paused', 'is_blocked'];

    public function findByStudentAndQuiz($studentId, $quizId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE student_id = ? AND quiz_id = ?");
        $stmt->execute([$studentId, $quizId]);
        return $stmt->fetch();
    }
    
    public function saveAnswer($resultId, $questionId, $answer, $timeTaken = 0) {
        try {
            // 1. Get correct answer for instant grading
            $stmt = $this->db->prepare("SELECT correct_answer, question_type, points FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$question) return false;
            
            // 1.5 Check if student is paused or blocked
            $stmt = $this->db->prepare("SELECT is_paused, is_blocked FROM results WHERE id = ?");
            $stmt->execute([$resultId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res && ($res['is_paused'] || $res['is_blocked'])) {
                return false; // Silently fail saving if paused/blocked
            }

            $pointsAwarded = 0;
            $correctAns = $question['correct_answer'];
            
            // Default to 0 (Wrong) for auto-graded questions, NULL for manual
            $isCorrect = 0;

            if ($question['question_type'] === 'short_answer') {
                if ($correctAns === 'MANUAL_GRADING') {
                    $isCorrect = null;
                } elseif (trim(strtolower($answer)) === trim(strtolower($correctAns))) {
                    $isCorrect = 1;
                    $pointsAwarded = $question['points'];
                }
            } elseif ($question['question_type'] === 'multiple_selection') {
                // MSQ: exact match of sorted IDs
                $studentIds = explode(',', (string)$answer);
                $correctIds = explode(',', (string)$correctAns);
                sort($studentIds);
                sort($correctIds);
                if ($studentIds === $correctIds) {
                    $isCorrect = 1;
                    $pointsAwarded = $question['points'];
                }
            } else {
                if ((string)$answer === (string)$correctAns) {
                    $isCorrect = 1;
                    $pointsAwarded = $question['points'];
                }
            }

            // 2. Save detailed answer
            $stmt = $this->db->prepare("
                INSERT INTO student_answers (result_id, question_id, student_answer, is_correct, points_awarded, time_taken_seconds, answered_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    student_answer = VALUES(student_answer), 
                    is_correct = VALUES(is_correct),
                    points_awarded = VALUES(points_awarded),
                    time_taken_seconds = VALUES(time_taken_seconds),
                    answered_at = NOW()
            ");
            return $stmt->execute([$resultId, $questionId, $answer, $isCorrect, $pointsAwarded, $timeTaken]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function getLiveStats($quizId) {
        // 1. Get total question count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $totalQuestions = $stmt->fetchColumn();

        // 2. Get questions IDs in order
        $stmt = $this->db->prepare("SELECT id FROM questions WHERE quiz_id = ? ORDER BY id ASC");
        $stmt->execute([$quizId]);
        $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 3. Get student stats
        // We order by status (submitted first?), then correct count, then time
        $sql = "SELECT s.id as student_db_id, s.name, s.student_id as student_display_id,
                r.id as result_id, r.status, r.started_at, r.is_paused, r.is_blocked,
                (SELECT COUNT(*) FROM student_answers sa WHERE sa.result_id = r.id) as answered_count,
                (SELECT COUNT(*) FROM student_answers sa WHERE sa.result_id = r.id AND sa.is_correct = 1) as correct_count,
                (SELECT COUNT(*) FROM student_answers sa WHERE sa.result_id = r.id AND sa.is_correct = 0) as wrong_count,
                (SELECT COALESCE(SUM(sa.time_taken_seconds), 0) FROM student_answers sa WHERE sa.result_id = r.id) as total_time_spent
                FROM results r
                JOIN students s ON r.student_id = s.id
                WHERE r.quiz_id = ?
                ORDER BY 
                    CASE WHEN r.status = 'submitted' THEN 1 ELSE 0 END DESC,
                    correct_count DESC, 
                    total_time_spent ASC, 
                    answered_count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$quizId]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Enrich with percentage and cleanup
        foreach ($stats as &$row) {
            $row['total_questions'] = (int)$totalQuestions;
            $row['percentage'] = $totalQuestions > 0 ? round(($row['correct_count'] / $totalQuestions) * 100, 1) : 0;
            
            // Map status labels
            if ((int)$row['is_blocked'] === 1) {
                $row['status_label'] = 'Blocked';
            } elseif ((int)$row['is_paused'] === 1) {
                $row['status_label'] = 'Paused';
            } elseif ($row['status'] === 'submitted') {
                $row['status_label'] = 'Finished';
            } else {
                $row['status_label'] = ((int)$row['answered_count'] === 0) ? 'Started' : 'In Progress';
            }
        }

        return $stats;
    }
    
    public function submit($resultId) {
        // 1. Calculate Results summary
        $summary = $this->calculateSummary($resultId);
        
        // 2. Update Result record
        return $this->update($resultId, [
            'status' => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
            'score' => $summary['score'],
            'total_points' => $summary['total_points']
        ]);
    }

    public function calculateSummary($resultId) {
        // Fetch Result to get Quiz ID
        $result = $this->find($resultId);
        if (!$result) return ['score' => 0, 'total_points' => 0, 'correct_answers' => 0, 'total_questions' => 0];
        
        $quizId = $result['quiz_id'];
        
        // Fetch Questions
        $stmt = $this->db->prepare("SELECT id, question_type, correct_answer, points FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch Student Answers
        $stmt = $this->db->prepare("SELECT question_id, student_answer FROM student_answers WHERE result_id = ?");
        $stmt->execute([$resultId]);
        $answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [question_id => answer]
        
        $score = 0;
        $totalPossible = 0;
        $correctCount = 0;
        
        foreach ($questions as $q) {
            $totalPossible += $q['points'];
            $qid = $q['id'];
            if (!isset($answers[$qid])) continue;
            
            $studentAns = $answers[$qid];
            $correctAns = $q['correct_answer'];
            $isCorrect = false;

            if ($q['question_type'] === 'short_answer') {
                if (trim(strtolower($studentAns)) === trim(strtolower($correctAns)) && $correctAns !== 'MANUAL_GRADING') {
                    $isCorrect = true;
                }
            } elseif ($q['question_type'] === 'multiple_selection') {
                // MSQ logic: both must match exactly (comma separated IDs)
                $studentIds = explode(',', (string)$studentAns);
                $correctIds = explode(',', (string)$correctAns);
                
                sort($studentIds);
                sort($correctIds);
                
                if ($studentIds === $correctIds) {
                    $isCorrect = true;
                }
            } else {
                if ((string)$studentAns === (string)$correctAns) {
                    $isCorrect = true;
                }
            }

            if ($isCorrect) {
                $score += $q['points'];
                $correctCount++;
            }
        }
        
        return [
            'score' => $score,
            'total_points' => $totalPossible,
            'correct_answers' => $correctCount,
            'total_questions' => count($questions)
        ];
    }

    public function getByQuizId($quizId) {
        $sql = "SELECT r.*, s.name as student_name, s.student_id as student_display_id 
                FROM results r 
                JOIN students s ON r.student_id = s.id 
                WHERE r.quiz_id = ?
                ORDER BY r.submitted_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$quizId]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['student_id'] = $row['student_display_id'];
            unset($row['student_display_id']);
        }
        
        return $results;
    }

    public function getResultsByClassAndQuiz($classId, $quizId) {
        $sql = "SELECT r.id, r.score, r.status, r.total_points, r.submitted_at, r.started_at, r.is_blocked,
                       s.name as student_name, s.student_id as student_display_id,
                       (SELECT COUNT(*) FROM student_answers sa 
                        JOIN questions q ON sa.question_id = q.id 
                        WHERE sa.result_id = r.id 
                        AND q.correct_answer = 'MANUAL_GRADING' 
                        AND sa.points_awarded = 0) as needs_grading
                FROM results r 
                JOIN students s ON r.student_id = s.id 
                WHERE r.quiz_id = ? AND s.class_id = ?
                ORDER BY r.submitted_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$quizId, $classId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStudentResultDetails($resultId) {
        // Get result info
        $result = $this->find($resultId);
        if (!$result) return null;

        // Get student info
        $stmt = $this->db->prepare("SELECT name, student_id FROM students WHERE id = ?");
        $stmt->execute([$result['student_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get answers with question details
        $sql = "SELECT sa.*, q.question_text, q.question_type, q.options, q.correct_answer as correct_answer_text, q.points as max_points
                FROM student_answers sa
                JOIN questions q ON sa.question_id = q.id
                WHERE sa.result_id = ?
                ORDER BY q.id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$resultId]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'result' => $result,
            'student' => $student,
            'answers' => $answers
        ];
    }

    public function updateManualGrade($resultId, $questionId, $points) {
        try {
            // 0. Validate points against max points
            $stmt = $this->db->prepare("SELECT points FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $maxPoints = $stmt->fetchColumn();
            
            if ($points > $maxPoints) {
                throw new Exception("Score ($points) cannot exceed maximum points ($maxPoints)");
            }

            $this->db->beginTransaction();

            // 1. Update the student_answer for the specific question
            $stmt = $this->db->prepare("UPDATE student_answers SET points_awarded = ?, is_correct = ? WHERE result_id = ? AND question_id = ?");
            
            // Determine correctness
            $isCorrect = $points > 0 ? 1 : 0;
            
            $stmt->execute([$points, $isCorrect, $resultId, $questionId]);

            // 2. Recalculate total score for the result
            $stmt = $this->db->prepare("SELECT SUM(points_awarded) FROM student_answers WHERE result_id = ?");
            $stmt->execute([$resultId]);
            $newTotalScore = $stmt->fetchColumn() ?: 0;

            // 3. Update result record
            $this->update($resultId, ['score' => $newTotalScore]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e; // Re-throw to be caught by Controller
        }
    }
}
