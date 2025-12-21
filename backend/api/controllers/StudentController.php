<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/Question.php';

class StudentController extends BaseController {

    // 1. Verify and enter room
    public function enterExam($data) {
        try {
            $this->validateRequiredFields($data, ['room_code', 'student_id']);
            
            $roomCode = strtoupper(trim($data['room_code']));
            $studentIdStr = trim($data['student_id']);
            
            // Find quiz
            $quizModel = new Quiz();
            $quiz = $quizModel->findByRoomCode($roomCode);
            
            if (!$quiz) {
                return $this->error('Invalid room code', 404);
            }

            // Auto-finish check
            if ($quiz['status'] === 'started' && !empty($quiz['start_time'])) {
                $duration = (int)($quiz['duration_minutes'] ?? 30);
                $endTime = strtotime($quiz['start_time'] . " +{$duration} minutes");
                if (time() > $endTime) {
                    $quizModel->update($quiz['id'], ['status' => 'finished']);
                    $quiz['status'] = 'finished';
                }
            }
            
            if ($quiz['status'] !== 'active' && $quiz['status'] !== 'started') {
                $this->logInvalidEntry($quiz['id'], $studentIdStr);
                return $this->error('Exam is not yet active or has already finished.', 403);
            }
            
            // Find Student record (find by string ID)
            $studentModel = new Student();
            $student = $studentModel->findByStudentId($studentIdStr);
            
            if (!$student) {
                return $this->error('Student ID not found in system', 404); 
            }

            // Verify Class Access
            $allowedClasses = $quizModel->getAllowedClasses($quiz['id']);
            if (!empty($allowedClasses)) {
                $isAllowed = false;
                $allowedClassNames = [];
                foreach ($allowedClasses as $class) {
                    $allowedClassNames[] = $class['name'] . ($class['section'] ? " ({$class['section']})" : "");
                    if ($student['class_id'] == $class['id']) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    return $this->error('Access Denied: You are not in the allowed class for this quiz. Allowed: ' . implode(', ', $allowedClassNames), 403);
                }
            }

            // Create/Update result record with 'waiting' status if just joining
            $resultModel = new Result();
            $existing = $resultModel->findByStudentAndQuiz($student['id'], $quiz['id']);
            
            if (!$existing) {
                $resultModel->create([
                    'student_id' => $student['id'],
                    'quiz_id' => $quiz['id'],
                    'status' => 'waiting',
                    'started_at' => null
                ]);
            }

            return $this->success([
                'student_name' => $student['name'],
                'student_id' => $student['student_id'], // string ID
                'quiz_title' => $quiz['title'],
                'quiz_id' => $quiz['id'],
                'quiz_status' => $quiz['status'],
                'student_db_id' => $student['id'],
                'time_limit' => $quiz['duration_minutes'],
                'start_time' => $quiz['start_time'],
                'server_time' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    private function logInvalidEntry($quizId, $studentIdAttempt) {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO invalid_entries (quiz_id, student_id_attempt) VALUES (?, ?)");
        $stmt->execute([$quizId, $studentIdAttempt]);
    }
    
    public function getQuizStatus($quizId) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz) return $this->error('Quiz not found', 404);

            // Auto-finish check
            if ($quiz['status'] === 'started' && !empty($quiz['start_time'])) {
                $duration = (int)($quiz['duration_minutes'] ?? 30);
                $endTime = strtotime($quiz['start_time'] . " +{$duration} minutes");
                if (time() > $endTime) {
                    $quizModel->update($quizId, ['status' => 'finished']);
                    $quiz['status'] = 'finished';
                }
            }
            
            return $this->success([
                'status' => $quiz['status'],
                'start_time' => $quiz['start_time'],
                'server_time' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // 2. Start Exam (Create Result record)
    public function startExam($data) {
        try {
            $this->validateRequiredFields($data, ['quiz_id', 'student_db_id']);
            
            $resultModel = new Result();
            $existing = $resultModel->findByStudentAndQuiz($data['student_db_id'], $data['quiz_id']);
            
            if ($existing) {
                if ($existing['status'] === 'submitted') {
                    return $this->error('You have already submitted this exam', 403);
                }
                // Resuming
                return $this->success(['result_id' => $existing['id'], 'status' => 'resumed']);
            }
            
            // Create new result
            $resultId = $resultModel->create([
                'student_id' => $data['student_db_id'],
                'quiz_id' => $data['quiz_id'],
                'status' => 'in_progress',
                'started_at' => date('Y-m-d H:i:s')
            ]);
            
            return $this->success(['result_id' => $resultId, 'status' => 'started']);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // 3. Submit Answer
    public function submitAnswer($data) {
        try {
            $this->validateRequiredFields($data, ['result_id', 'question_id', 'answer']);
            $timeTaken = $data['time_taken'] ?? 0;
            
            $resultModel = new Result();
            $success = $resultModel->saveAnswer($data['result_id'], $data['question_id'], $data['answer'], $timeTaken);
            
            if ($success) {
                return $this->success(['saved' => true]);
            } else {
                return $this->error('Failed to save answer', 500);
            }
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // 4. Finish Exam
    public function finishExam($data) {
         try {
            $this->validateRequiredFields($data, ['result_id']);
            
            $resultModel = new Result();
            $resultModel->submit($data['result_id']);
            
            return $this->success(['status' => 'submitted', 'result_id' => $data['result_id']]);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }   
    }
    
    // 5. Get Questions
    public function getExamQuestions($quizId) {
         try {
            $questionModel = new Question();
            $questions = $questionModel->getByQuizId($quizId);
            
            // Remove correct_answer from response for security!
            foreach ($questions as &$q) {
                unset($q['correct_answer']);
            }
            
            return $this->success($questions);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    public function getResult($resultId) {
        try {
            $resultModel = new Result();
            $result = $resultModel->find($resultId);
            
            if (!$result) {
                return $this->error('Result not found', 404);
            }
            
            $summary = $resultModel->calculateSummary($resultId);
            
            return $this->success([
                'score' => $result['score'],
                'total_points' => $result['total_points'],
                'status' => $result['status'],
                'total_questions' => $summary['total_questions'],
                'correct_answers' => $summary['correct_answers']
            ]);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    public function updateResultStatus($resultId, $data) {
        try {
            $this->validateRequiredFields($data, ['status']);
            $resultModel = new Result();
            $resultModel->update($resultId, ['status' => $data['status']]);
            return $this->success(['status' => $data['status']]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
