<?php
// This is one of the most important models! 
// It handles everything related to a student's attempt at a quiz, including automatic grading.
require_once __DIR__ . '/Model.php';

class Result extends Model {
    protected $table = 'results';
    protected $fillable = ['student_id', 'quiz_id', 'score', 'total_points', 'status', 'started_at', 'submitted_at', 'is_paused', 'is_blocked'];

    // Finds a specific result record using the student and the quiz they are taking.
    public function findByStudentAndQuiz($studentId, $quizId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE student_id = ? AND quiz_id = ?");
        $stmt->execute([$studentId, $quizId]);
        return $stmt->fetch();
    }
    
    // This is the "Brain" of the grading system. 
    // Every time a student clicks an answer, this code runs, checks if it's right, 
    // and updates their score in real-time.
    public function saveAnswer($resultId, $questionId, $answer, $timeTaken = 0) {
        try {
            // 1. First, we get the correct answer and question details from the DB.
            $stmt = $this->db->prepare("SELECT correct_answer, question_type, points FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$question) return false;
            
            // 1.5 Security Check: If the teacher paused or blocked this student, don't save their answer!
            $stmt = $this->db->prepare("SELECT is_paused, is_blocked FROM results WHERE id = ?");
            $stmt->execute([$resultId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res && ($res['is_paused'] || $res['is_blocked'])) {
                return false; 
            }

            $pointsAwarded = 0;
            $correctAns = $question['correct_answer'];
            $isCorrect = 0; // Assume it's wrong until proven right.

            // 2. Here's the grading logic for different question types:
            if ($question['question_type'] === 'short_answer') {
                // If it needs a human to grade it, we set is_correct to NULL (Wait for teacher).
                if ($correctAns === 'MANUAL_GRADING') {
                    $isCorrect = null;
                } elseif (trim(strtolower($answer)) === trim(strtolower($correctAns))) {
                    $isCorrect = 1;
                    $pointsAwarded = $question['points'];
                }
            } elseif ($question['question_type'] === 'multiple_selection') {
                // For "Check all that apply", the student must get the exact set of answers.
                $studentIds = explode(',', (string)$answer);
                $correctIds = explode(',', (string)$correctAns);
                sort($studentIds);
                sort($correctIds);
                if ($studentIds === $correctIds) {
                    $isCorrect = 1;
                    $pointsAwarded = $question['points'];
                }
            } else {
                // Simple Multiple Choice or True/False.
                if ((string)$answer === (string)$correctAns) {
                    $isCorrect = 1;
                    $pointsAwarded = $question['points'];
                }
            }

            // 3. Save the specific answer into the 'student_answers' table.
            // We use ON DUPLICATE KEY UPDATE so if they change their mind, we just update the old answer.
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

    // This is for the "Live Monitoring Board" that teachers see.
    // It calculates how many questions each student has answered and their current score.
    public function getLiveStats($quizId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $totalQuestions = $stmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT id FROM questions WHERE quiz_id = ? ORDER BY id ASC");
        $stmt->execute([$quizId]);
        $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // This giant query joins the results and students tables to get a full progress report.
        $sql = "SELECT u.id as student_db_id, CONCAT(u.first_name, ' ', u.last_name) as name, u.user_id as student_display_id,
                r.id as result_id, r.status, r.started_at, r.is_paused, r.is_blocked,
                (SELECT COUNT(*) FROM student_answers sa WHERE sa.result_id = r.id) as answered_count,
                (SELECT COUNT(*) FROM student_answers sa WHERE sa.result_id = r.id AND sa.is_correct = 1) as correct_count,
                (SELECT COUNT(*) FROM student_answers sa WHERE sa.result_id = r.id AND sa.is_correct = 0) as wrong_count,
                (SELECT COALESCE(SUM(sa.time_taken_seconds), 0) FROM student_answers sa WHERE sa.result_id = r.id) as total_time_spent
                FROM results r
                JOIN users u ON r.student_id = u.id
                WHERE r.quiz_id = ?
                ORDER BY 
                    CASE WHEN r.status = 'submitted' THEN 1 ELSE 0 END DESC,
                    correct_count DESC, 
                    total_time_spent ASC, 
                    answered_count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$quizId]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // We add some extra labels so the React frontend can easily show "Finished" or "Blocked".
        foreach ($stats as &$row) {
            $row['total_questions'] = (int)$totalQuestions;
            $row['percentage'] = $totalQuestions > 0 ? round(($row['correct_count'] / $totalQuestions) * 100, 1) : 0;
            
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
    
    // Finalize the quiz! This runs when the student clicks "Finish" or time runs out.
    public function submit($resultId) {
        // 1. Calculate the final score.
        $summary = $this->calculateSummary($resultId);
        
        // 2. Mark it as 'submitted' and save the final numbers.
        return $this->update($resultId, [
            'status' => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
            'score' => $summary['score'],
            'total_points' => $summary['total_points']
        ]);
    }

    // A helper function to add up all the points awarded for each question.
    public function calculateSummary($resultId) {
        $result = $this->find($resultId);
        if (!$result) return ['score' => 0, 'total_points' => 0, 'correct_answers' => 0, 'total_questions' => 0];
        
        $quizId = $result['quiz_id'];
        
        $stmt = $this->db->prepare("SELECT id, question_type, correct_answer, points FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("SELECT question_id, student_answer FROM student_answers WHERE result_id = ?");
        $stmt->execute([$resultId]);
        $answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); 
        
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

            // Simple grading logic repeated here for the final summary.
            if ($q['question_type'] === 'short_answer') {
                if (trim(strtolower($studentAns)) === trim(strtolower($correctAns)) && $correctAns !== 'MANUAL_GRADING') {
                    $isCorrect = true;
                }
            } elseif ($q['question_type'] === 'multiple_selection') {
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

    // Gets results for a quiz, usually for the teacher's "Results" tab.
    public function getByQuizId($quizId) {
        $sql = "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as student_name, u.user_id as student_display_id 
                FROM results r 
                JOIN users u ON r.student_id = u.id 
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

    // Fetches everyone's scores for a specific class and quiz.
    public function getResultsByClassAndQuiz($classId, $quizId) {
        $sql = "SELECT r.id, r.score, r.status, r.total_points, r.submitted_at, r.started_at, r.is_blocked,
                       CONCAT(u.first_name, ' ', u.last_name) as student_name, u.user_id as student_display_id,
                       (SELECT COUNT(*) FROM student_answers sa 
                        JOIN questions q ON sa.question_id = q.id 
                        WHERE sa.result_id = r.id 
                        AND q.correct_answer = 'MANUAL_GRADING' 
                        AND sa.points_awarded = 0) as needs_grading
                FROM results r 
                JOIN users u ON r.student_id = u.id 
                JOIN class_students cs ON u.id = cs.student_id
                WHERE r.quiz_id = ? AND cs.class_id = ?
                ORDER BY r.submitted_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$quizId, $classId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // This is for the "Grading" page where the teacher sees every answer a specific student gave.
    public function getStudentResultDetails($resultId) {
        $result = $this->find($resultId);
        if (!$result) return null;

        $stmt = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, user_id as student_id FROM users WHERE id = ?");
        $stmt->execute([$result['student_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

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

    // This runs when a teacher manually gives points for an essay or short answer.
    public function updateManualGrade($resultId, $questionId, $points) {
        try {
            $stmt = $this->db->prepare("SELECT points FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $maxPoints = $stmt->fetchColumn();
            
            if ($points > $maxPoints) {
                throw new Exception("Score ($points) cannot exceed maximum points ($maxPoints)");
            }

            $this->db->beginTransaction();

            // 1. Update the awarded points for this specific answer.
            $stmt = $this->db->prepare("UPDATE student_answers SET points_awarded = ?, is_correct = ? WHERE result_id = ? AND question_id = ?");
            $isCorrect = $points > 0 ? 1 : 0;
            $stmt->execute([$points, $isCorrect, $resultId, $questionId]);

            // 2. Recalculate the student's TOTAL score for the entire quiz.
            $stmt = $this->db->prepare("SELECT SUM(points_awarded) FROM student_answers WHERE result_id = ?");
            $stmt->execute([$resultId]);
            $newTotalScore = $stmt->fetchColumn() ?: 0;

            // 3. Update the final record.
            $this->update($resultId, ['score' => $newTotalScore]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e; 
        }
    }

    // --- ANALYTICS SECTION ---

    // This is the "Brain" for the reports page. 
    // It calculates the big numbers like average score and pass rate across the whole system.
    public function getGlobalPerformanceStats() {
        // 1. Average Score (percentage wise)
        // We calculate (score / total_points) * 100 for each result, then average that.
        // We filter out cases where total_points is 0 to avoid division by zero.
        $avgSql = "SELECT AVG((score / total_points) * 100) 
                   FROM {$this->table} 
                   WHERE status = 'submitted' AND total_points > 0";
        $avgScore = (float)$this->query($avgSql)->fetchColumn();

        // 2. Pass Rate (Assuming 50% is passing)
        // Count passed attempts / Total submitted attempts
        $passSql = "SELECT COUNT(*) FROM {$this->table} 
                    WHERE status = 'submitted' AND (score / total_points) >= 0.5 AND total_points > 0";
        $passCount = (int)$this->query($passSql)->fetchColumn();

        $totalSql = "SELECT COUNT(*) FROM {$this->table} WHERE status = 'submitted'";
        $totalAttempts = (int)$this->query($totalSql)->fetchColumn();

        $passRate = $totalAttempts > 0 ? ($passCount / $totalAttempts) * 100 : 0;

        return [
            'avg_score' => round($avgScore, 1),
            'pass_rate' => round($passRate, 1),
            'total_attempts' => $totalAttempts
        ];
    }
}
