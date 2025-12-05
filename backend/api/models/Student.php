<?php
require_once __DIR__ . '/Model.php';

class Student extends Model {
    protected $table = 'students';
    protected $fillable = ['student_id', 'name', 'class_id'];
    
    // Student login/verification
    public function verifyStudent($studentId, $classId) {
        $sql = "SELECT s.*, c.name as class_name, c.section 
                FROM {$this->table} s
                JOIN classes c ON s.class_id = c.id
                WHERE s.student_id = ? AND s.class_id = ?";
                
        $student = $this->query($sql, [$studentId, $classId])->fetch();
        
        if (!$student) {
            throw new Exception('Student not found in this class', 404);
        }
        
        // Generate a temporary token for verification
        $token = bin2hex(random_bytes(16));
        
        // In a real application, you might want to store this token with an expiration
        // For simplicity, we'll just return it here
        
        return [
            'student' => [
                'id' => $student['id'],
                'student_id' => $student['student_id'],
                'name' => $student['name'],
                'class_name' => $student['class_name'],
                'section' => $student['section']
            ],
            'verification_token' => $token
        ];
    }
    
    // Get student's quiz results
    public function getQuizResults($studentId, $quizId = null) {
        $params = [$studentId];
        $sql = "SELECT r.*, q.title as quiz_title, q.room_code,
                (SELECT COUNT(*) FROM questions WHERE quiz_id = r.quiz_id) as total_questions,
                (SELECT COUNT(*) FROM student_answers sa 
                 WHERE sa.result_id = r.id AND sa.is_correct = 1) as correct_answers
                FROM results r
                JOIN quizzes q ON r.quiz_id = q.id
                WHERE r.student_id = ?";
        
        if ($quizId) {
            $sql .= " AND r.quiz_id = ?";
            $params[] = $quizId;
        }
        
        $sql .= " ORDER BY r.submitted_at DESC";
        
        $results = $this->query($sql, $params)->fetchAll();
        
        // Calculate percentage and grade for each result
        foreach ($results as &$result) {
            if ($result['total_questions'] > 0) {
                $result['percentage'] = round(($result['correct_answers'] / $result['total_questions']) * 100, 2);
                $result['grade'] = $this->calculateGrade($result['percentage']);
            } else {
                $result['percentage'] = 0;
                $result['grade'] = 'N/A';
            }
        }
        
        return $results;
    }
    
    // Submit a quiz
    public function submitQuiz($studentId, $quizId, $answers) {
        $this->beginTransaction();
        
        try {
            $quizModel = new Quiz();
            $questionModel = new Question();
            $resultModel = new Result();
            $violationModel = new Violation();
            
            // Check if student can submit the quiz
            $quizModel->canStudentTakeQuiz($quizId, $studentId);
            
            // Check if student has already submitted
            $existingResult = $resultModel->getStudentQuizResult($studentId, $quizId);
            if ($existingResult && $existingResult['status'] !== 'in_progress') {
                throw new Exception('You have already submitted this quiz', 400);
            }
            
            // Get all questions for the quiz
            $questions = $questionModel->getQuestionsByQuiz($quizId);
            $totalQuestions = count($questions);
            $correctAnswers = 0;
            $pointsEarned = 0;
            
            // Create or update the result record
            $resultId = $existingResult ? $existingResult['id'] : $resultModel->create([
                'student_id' => $studentId,
                'quiz_id' => $quizId,
                'total_questions' => $totalQuestions,
                'questions_answered' => 0,
                'correct_answers' => 0,
                'score' => 0,
                'status' => 'submitted',
                'submitted_at' => date('Y-m-d H:i:s')
            ]);
            
            // Process each answer
            foreach ($answers as $answer) {
                $questionId = $answer['question_id'];
                $selectedOptionId = $answer['selected_option_id'] ?? null;
                $answerText = $answer['answer_text'] ?? null;
                $question = null;
                
                // Find the question
                foreach ($questions as $q) {
                    if ($q['id'] == $questionId) {
                        $question = $q;
                        break;
                    }
                }
                
                if (!$question) {
                    continue; // Skip invalid questions
                }
                
                $isCorrect = false;
                $points = 0;
                
                // Check the answer based on question type
                if ($question['question_type'] === 'mcq' || $question['question_type'] === 'true_false') {
                    // For MCQs and True/False, check if the selected option is correct
                    if ($selectedOptionId) {
                        $optionModel = new Option();
                        $isCorrect = $optionModel->isCorrectAnswer($selectedOptionId, $questionId);
                        if ($isCorrect) {
                            $points = $question['points'];
                            $correctAnswers++;
                            $pointsEarned += $points;
                        }
                    }
                } else {
                    // For short answers, mark for manual grading
                    $isCorrect = null; // Null means needs manual grading
                    $points = 0; // Will be updated after manual grading
                }
                
                // Save the student's answer
                $answerData = [
                    'result_id' => $resultId,
                    'student_id' => $studentId,
                    'question_id' => $questionId,
                    'selected_option_id' => $selectedOptionId,
                    'answer_text' => $answerText,
                    'is_correct' => $isCorrect,
                    'points_earned' => $points,
                    'is_manually_graded' => ($question['question_type'] === 'short_answer')
                ];
                
                $answerModel = new StudentAnswer();
                $answerModel->create($answerData);
            }
            
            // Update the result
            $resultModel->update($resultId, [
                'questions_answered' => count($answers),
                'correct_answers' => $correctAnswers,
                'score' => $pointsEarned,
                'status' => 'submitted',
                'submitted_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->commit();
            
            return [
                'success' => true,
                'result_id' => $resultId,
                'total_questions' => $totalQuestions,
                'questions_answered' => count($answers),
                'correct_answers' => $correctAnswers,
                'score' => $pointsEarned
            ];
            
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }
    
    // Record a violation (tab switch, etc.)
    public function recordViolation($studentId, $quizId, $violationType) {
        $violationModel = new Violation();
        $resultModel = new Result();
        
        // Get or create the result record
        $result = $resultModel->getStudentQuizResult($studentId, $quizId);
        
        if (!$result) {
            // Create a new result record if it doesn't exist
            $resultId = $resultModel->create([
                'student_id' => $studentId,
                'quiz_id' => $quizId,
                'status' => 'in_progress'
            ]);
            $result = $resultModel->find($resultId);
        }
        
        // Get the current violation count
        $violationCount = $violationModel->getViolationCount($result['id']);
        $newCount = $violationCount + 1;
        
        // Record the violation
        $violationModel->create([
            'result_id' => $result['id'],
            'student_id' => $studentId,
            'quiz_id' => $quizId,
            'violation_type' => $violationType,
            'violation_count' => $newCount
        ]);
        
        // Check if student should be kicked (3 violations)
        if ($newCount >= 3) {
            $this->kickStudent($studentId, $quizId, 'Exceeded maximum allowed violations');
            return [
                'action' => 'kicked',
                'message' => 'You have been removed from the quiz due to multiple violations',
                'violation_count' => $newCount
            ];
        }
        
        return [
            'action' => 'warning',
            'message' => 'Warning: ' . $this->getViolationMessage($violationType, $newCount),
            'violation_count' => $newCount
        ];
    }
    
    // Kick a student from a quiz
    private function kickStudent($studentId, $quizId, $reason) {
        $resultModel = new Result();
        $kickedModel = new KickedStudent();
        
        // Get the result record
        $result = $resultModel->getStudentQuizResult($studentId, $quizId);
        
        if ($result) {
            // Update the result status
            $resultModel->update($result['id'], [
                'status' => 'kicked'
            ]);
            
            // Record in kicked_students table
            $kickedModel->create([
                'result_id' => $result['id'],
                'student_id' => $studentId,
                'quiz_id' => $quizId,
                'reason' => $reason,
                'violation_count' => 3 // Max violations
            ]);
        }
    }
    
    // Helper method to get violation message
    private function getViolationMessage($violationType, $count) {
        $messages = [
            'tab_switch' => 'Switching tabs is not allowed',
            'page_leave' => 'Leaving the page is not allowed',
            'minimize' => 'Minimizing the window is not allowed',
            'other' => 'Violation of exam rules'
        ];
        
        $message = $messages[$violationType] ?? 'Violation of exam rules';
        $remaining = 3 - $count;
        
        return "$message. You have $remaining warnings left before being removed from the exam.";
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
