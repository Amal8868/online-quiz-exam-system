<?php
// This is the "Student Help Desk". 
// It handles everything a student does: joining a room, starting the exam, and picking answers.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Question.php';

class StudentController extends BaseController {

    // 1. "Can I come in?" - This verifies the room code and student ID.
    public function enterExam($data) {
        try {
            $this->validateRequiredFields($data, ['room_code', 'student_id']);
            
            $roomCode = strtoupper(trim($data['room_code']));
            $studentIdStr = trim($data['student_id']);
            
            // First, find the quiz they are looking for.
            $quizModel = new Quiz();
            $quiz = $quizModel->findByRoomCode($roomCode);
            
            if (!$quiz) {
                return $this->error('Invalid room code', 404);
            }

            // AUTO-FINISH: If this student is late and the quiz already ended, we mark it finished.
            if ($quiz['status'] === 'started' && !empty($quiz['start_time'])) {
                $duration = (int)($quiz['duration_minutes'] ?? 30);
                $endTime = strtotime($quiz['start_time'] . " +{$duration} minutes");
                if (time() > $endTime) {
                    $quizModel->update($quiz['id'], ['status' => 'finished']);
                    $quiz['status'] = 'finished';
                }
            }
            
            // If the quiz isn't "Started" or "Active", we can't let them in.
            if ($quiz['status'] !== 'active' && $quiz['status'] !== 'started') {
                $this->logInvalidEntry($quiz['id'], $studentIdStr);
                return $this->error('Exam is not yet active or has already finished.', 403);
            }
            
            // Check if the student actually exists in our main users database.
            $userModel = new User();
            $student = $userModel->findByUserId($studentIdStr);
            
            if (!$student || $student['user_type'] !== 'Student') {
                return $this->error('Student ID not found in system', 404); 
            }

            // Check if the account is active
            if ($student['status'] !== 'Active') {
                return $this->error('Your student account is not active.', 403);
            }

            // ROSTER CHECK: Does this student actually have permission to take this quiz?
            $allowedClasses = $quizModel->getAllowedClasses($quiz['id']);
            if (!empty($allowedClasses)) {
                // We check if the student is enrolled in ANY of the allowed classes for this quiz.
                $db = getDBConnection();
                $placeholders = implode(',', array_fill(0, count($allowedClasses), '?'));
                $allowedClassIds = array_map(function($c) { return $c['id']; }, $allowedClasses);
                
                $sql = "SELECT COUNT(*) FROM class_students WHERE student_id = ? AND class_id IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge([$student['id']], $allowedClassIds));
                $isEnrolled = $stmt->fetchColumn() > 0;
                
                if (!$isEnrolled) {
                    return $this->error('Access Denied: You are not in an allowed class for this quiz.', 403);
                }
            }

            // SECURITY: If the teacher blocked them earlier, they can't re-join.
            $resultModel = new Result();
            $existing = $resultModel->findByStudentAndQuiz($student['id'], $quiz['id']);
            
            if ($existing && $existing['is_blocked']) {
                return $this->error('Access Revoked: You have been blocked by the instructor.', 403);
            }

            // If it's their first time joining, we create a 'waiting' record for them.
            if (!$existing) {
                $resultModel->create([
                    'student_id' => $student['id'],
                    'quiz_id' => $quiz['id'],
                    'status' => 'waiting',
                    'started_at' => null
                ]);
            }

            return $this->success([
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'student_id' => $student['user_id'], 
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

    // A simple log to help teachers see who tried to join with a wrong code.
    private function logInvalidEntry($quizId, $studentIdAttempt) {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO invalid_entries (quiz_id, student_id_attempt) VALUES (?, ?)");
        $stmt->execute([$quizId, $studentIdAttempt]);
    }
    
    // Periodically checks if the quiz status changed (e.g., from 'active' to 'started').
    public function getQuizStatus($quizId) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz) return $this->error('Quiz not found', 404);

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
                'duration_minutes' => $quiz['duration_minutes'],
                'server_time' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // 2. "I'm ready!" - This starts the timer and lets the student see questions.
    public function startExam($data) {
        try {
            $this->validateRequiredFields($data, ['quiz_id', 'student_db_id']);
            
            $resultModel = new Result();
            $existing = $resultModel->findByStudentAndQuiz($data['student_db_id'], $data['quiz_id']);
            
            if ($existing) {
                if ($existing['is_blocked']) {
                    return $this->error('Access Revoked: You are blocked.', 403);
                }
                if ($existing['status'] === 'submitted') {
                    return $this->error('You have already submitted this exam.', 403);
                }
                // If they refreshing the page, we just "Resume" where they left off.
                return $this->success(['result_id' => $existing['id'], 'status' => 'resumed']);
            }
            
            // Create the official "Started" record with the current time.
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
    
    // 3. "Saving my answer..." - Sends the chosen option to the server.
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
    
    // 4. "I'm Done!" - Finalizes the attempt.
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
    
    // 5. "Give me the questions" - Randomizes the order so every student gets a different sequence!
    public function getExamQuestions($quizId) {
         try {
            $questionModel = new Question();
            $questions = $questionModel->getByQuizId($quizId);
            
            // ANTI-CHEATING: Shuffle the questions so they aren't in the same order as your neighbor!
            shuffle($questions);
            
            // SECURITY: Never send the correct_answer to the browser! Only the server knows that.
            foreach ($questions as &$q) {
                unset($q['correct_answer']);
            }
            
            return $this->success($questions);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // Fetches the student's result summary (score, correct answers, etc.)
    public function getResult($resultId) {
        try {
            $resultModel = new Result();
            $result = $resultModel->find($resultId);
            
            if (!$result) {
                return $this->error('Result not found', 404);
            }
            
            $summary = $resultModel->calculateSummary($resultId);
            
            $response = [
                'score' => $result['score'],
                'total_points' => $result['total_points'],
                'status' => $result['status'],
                'is_blocked' => (bool)$result['is_blocked'],
                'total_questions' => $summary['total_questions'],
                'correct_answers' => $summary['correct_answers']
            ];

            // If there's a "Short Answer", tell the student it's waiting for manual grading.
            $db = getDBConnection();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM student_answers sa 
                JOIN questions q ON sa.question_id = q.id 
                WHERE sa.result_id = ? AND LOWER(q.question_type) = 'short_answer'
            ");
            $stmt->execute([$resultId]);
            $manualCount = $stmt->fetchColumn();
            
            $response['has_manual_grading'] = $manualCount > 0;
            $response['pending_count'] = $manualCount;
            
            return $this->success($response);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // Checks if the instructor has paused or blocked this specific student attempt.
    public function getAttemptStatus($resultId) {
        try {
            $resultModel = new Result();
            $result = $resultModel->find($resultId);
            if (!$result) return $this->error('Result not found', 404);
            
            return $this->success([
                'is_paused' => (bool)$result['is_paused'],
                'is_blocked' => (bool)$result['is_blocked'],
                'status' => $result['status']
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // Updates the current status (e.g., from 'waiting' to 'in_progress').
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

    // Helpful check to see if a teacher has any students to assign quizzes to.
    public function checkStudentsExist($teacherId) {
        try {
            require_once __DIR__ . '/../models/ClassModel.php';
            $classModel = new ClassModel();
            
            $classes = $classModel->getTeacherClasses($teacherId);
            
            if (empty($classes)) {
                return $this->success(['has_students' => false, 'student_count' => 0]);
            }
            
            $totalStudents = array_reduce($classes, function($carry, $class) {
                return $carry + ($class['student_count'] ?? 0);
            }, 0);
            
            return $this->success([
                'has_students' => $totalStudents > 0,
                'student_count' => $totalStudents
            ]);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
