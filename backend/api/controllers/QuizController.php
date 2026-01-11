<?php
// This is the "General Manager" for everything Quiz-related.
// It handles creating quizzes, adding questions, and even live monitoring.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/Question.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/User.php';

class QuizController extends BaseController {
    
    // I made this to generate those cool 6-digit codes like "A1B2C3".
    // It keeps guessing until it finds one that isn't already being used.
    private function generateRoomCode() {
        $db = getDBConnection();
        do {
            $roomCode = '';
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            for ($i = 0; $i < 6; $i++) {
                $roomCode .= $characters[rand(0, strlen($characters) - 1)];
            }
            $stmt = $db->prepare("SELECT id FROM quizzes WHERE room_code = ?");
            $stmt->execute([$roomCode]);
        } while ($stmt->fetch());
        return $roomCode;
    }

    // Fetches all quizzes for a specific teacher.
    public function getQuizzes($teacherId) {
        $quiz = new Quiz();
        $quizzes = $quiz->all(['teacher_id' => $teacherId], 'created_at DESC');
        return $this->success($quizzes);
    }

    // Filters quizzes by class. Helpful for the "Results by Class" view.
    public function getQuizzesByClass($teacherId, $classId) {
        try {
            $quizModel = new Quiz();
            $quizzes = $quizModel->getByClassId($classId);
            
            // Security: Only show quizzes that belong to THIS teacher.
            $filtered = array_filter($quizzes, function($q) use ($teacherId) {
                return $q['teacher_id'] == $teacherId;
            });
            
            return $this->success(array_values($filtered));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // Deletes a quiz for good.
    public function deleteQuiz($teacherId, $quizId) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId); 
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                throw new Exception('Quiz not found or unauthorized', 404);
            }

            if ($quizModel->deleteQuiz($quizId)) {
                 return $this->success(['message' => 'Quiz deleted successfully']);
            } else {
                 throw new Exception('Failed to delete quiz');
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // The "Start Here" function for creating a new quiz.
    public function createQuiz($teacherId, $data) {
        $db = getDBConnection();
        try {
            $this->validateRequiredFields($data, ['title']);
            
            $db->beginTransaction();
            
            // 1. Generate that unique room code.
            $roomCode = $this->generateRoomCode();
            
            // 2. Create the quiz record.
            $quiz = new Quiz();
            $quizId = $quiz->create([
                'teacher_id' => $teacherId,
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'subject_id' => $data['subject_id'] ?? null,
                'room_code' => $roomCode,
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'timer_type' => $data['timer_type'] ?? 'exam', 
                'status' => 'draft'
            ]);

            // 3. If the teacher picked some classes, link them to the quiz.
            if (!empty($data['class_ids']) && is_array($data['class_ids'])) {
                $stmt = $db->prepare("INSERT INTO quiz_classes (quiz_id, class_id) VALUES (?, ?)");
                foreach ($data['class_ids'] as $classId) {
                    $stmt->execute([$quizId, $classId]);
                }
            }
            
            $db->commit();
            return $this->success(['id' => $quizId, 'room_code' => $roomCode], 'Quiz created successfully', 201);
            
        } catch (Exception $e) {
            $db->rollBack();
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // Gets everything about a quiz: the title, the questions, and who is allowed to take it.
    public function getQuiz($teacherId, $quizId) {
        $quizModel = new Quiz();
        $quiz = $quizModel->find($quizId);
        
        if (!$quiz || $quiz['teacher_id'] != $teacherId) {
            return $this->error('Quiz not found', 404);
        }

        // AUTO-FINISH LOGIC: 
        // If the quiz is "Started" but the time is up, we automatically mark it as "Finished".
        if ($quiz['status'] === 'started' && !empty($quiz['start_time'])) {
            $duration = (int)($quiz['duration_minutes'] ?? 30);
            $endTime = strtotime($quiz['start_time'] . " +{$duration} minutes");
            if (time() > $endTime) {
                $quizModel->update($quizId, ['status' => 'finished']);
                $quiz['status'] = 'finished';
            }
        }
        
        // Grab the questions.
        $questionModel = new Question();
        $questions = $questionModel->getByQuizId($quizId);
        $quiz['questions'] = $questions;
        
        // Calculate total points.
        $totalPoints = 0;
        foreach ($questions as $q) {
            $totalPoints += $q['points'] ?? 0;
        }
        $quiz['total_points'] = $totalPoints;
        
        // Find which classes are allowed.
        $allowedClasses = $quizModel->getAllowedClasses($quizId);
        $quiz['allowed_classes'] = $allowedClasses;
        
        // Count how many students are invited.
        $totalClassStudents = 0;
        foreach ($allowedClasses as $class) {
            $totalClassStudents += (int)($class['student_count'] ?? 0);
        }
        
        if ($totalClassStudents > 0) {
            $quiz['allowed_students_count'] = $totalClassStudents;
        } else {
            $allowed = $quizModel->getAllowedStudents($quizId);
            $quiz['allowed_students'] = $allowed;
            $quiz['allowed_students_count'] = count($allowed);
        }
        
        // Server time helps the React frontend sync the countdown timer perfectly.
        $quiz['server_time'] = date('Y-m-d H:i:s');
        
        return $this->success($quiz);
    }

    // Simple update for titles or descriptions.
    public function updateQuiz($teacherId, $quizId, $data) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found or unauthorized', 404);
            }
            $allowed = ['title', 'description', 'duration_minutes', 'timer_type', 'status', 'room_code', 'subject_id'];
            $updateData = [];
            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            if (empty($updateData)) {
                return $this->error('No valid fields to update', 400);
            }
            $quizModel->update($quizId, $updateData);
            return $this->success(['message' => 'Quiz updated']);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // Change which classes can see this quiz.
    public function setQuizClasses($teacherId, $quizId, $data) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }

            $this->validateRequiredFields($data, ['class_ids']);
            $classIds = $data['class_ids'];

            $db = getDBConnection();
            $db->beginTransaction();

            // Clear the old list and add the new one.
            $stmt = $db->prepare("DELETE FROM quiz_classes WHERE quiz_id = ?");
            $stmt->execute([$quizId]);

            if (!empty($classIds)) {
                $stmt = $db->prepare("INSERT INTO quiz_classes (quiz_id, class_id) VALUES (?, ?)");
                foreach ($classIds as $classId) {
                    $stmt->execute([$quizId, $classId]);
                }
            }

            $db->commit();
            return $this->success(['quiz_id' => $quizId, 'class_ids' => $classIds], 'Quiz classes updated');
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // Adding a question to the quiz.
    public function addQuestion($teacherId, $quizId, $data) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }

            $this->validateRequiredFields($data, ['question_text', 'question_type', 'correct_answer']);
            
            $question = new Question();
            $options = isset($data['options']) ? json_encode($data['options']) : null;
            
            $questionId = $question->create([
                'quiz_id' => $quizId,
                'question_text' => $data['question_text'],
                'question_type' => $data['question_type'],
                'options' => $options,
                'correct_answer' => $data['correct_answer'],
                'points' => $data['points'] ?? 1,
                'time_limit_seconds' => $data['time_limit_seconds'] ?? null
            ]);
            
            return $this->success(['question_id' => $questionId], 'Question added successfully', 201);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // Process a CSV file to add students to the quiz "Roster".
    public function uploadRoster($teacherId, $quizId, $files) {
        try {
            $studentModel = new Student();
            $quizModel = new Quiz();
            
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }
            
            if (!isset($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
                return $this->error('No file uploaded or upload error', 400);
            }
            
            $file = $files['file']['tmp_name'];
            $handle = fopen($file, "r");
            if ($handle === FALSE) {
                return $this->error('Could not open CSV file', 500);
            }

            $addedCount = 0;
            $row = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                if ($row === 1) {
                     if (strtolower(trim($data[0])) === 'student_id' || strtolower(trim($data[1])) === 'name') {
                         continue;
                     }
                }
                
                if (count($data) < 2) continue;
                
                $studentIdStr = trim($data[0]);
                $fullName = trim($data[1]);
                
                if (empty($studentIdStr) || empty($fullName)) continue;
                
                // Name splitter logic.
                $parts = explode(' ', $fullName, 2);
                $firstName = $parts[0];
                $lastName = $parts[1] ?? '';

                $userModel = new User();
                $student = $userModel->findByUserId($studentIdStr);
                $studentDbId = null;

                if ($student) {
                    $userModel->updateUser($student['id'], [
                        'first_name' => $firstName,
                        'last_name' => $lastName
                    ]);
                    $studentDbId = $student['id'];
                } else {
                    $studentDbId = $userModel->createUser([
                        'user_id' => $studentIdStr,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'user_type' => 'Student',
                        'status' => 'Active'
                    ]);
                }

                if ($quizModel->addAllowedStudent($quizId, $studentDbId)) {
                    $addedCount++;
                }
            }
            
            fclose($handle);
            
            return $this->success(['added' => $addedCount], 'Roster processed successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // Gets the final results after the quiz is over.
    public function getResults($teacherId, $quizId) {
        $quizModel = new Quiz();
        $quiz = $quizModel->find($quizId);
        
        if (!$quiz || $quiz['teacher_id'] != $teacherId) {
             return $this->error('Quiz not found', 404);
        }
        
        $resultModel = new Result();
        $results = $resultModel->getByQuizId($quizId);
        
        return $this->success($results);
    }


    // This activates or starts the quiz for students.
    public function setStatus($teacherId, $quizId, $data) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }

            $this->validateRequiredFields($data, ['status']);
            $newStatus = $data['status'];
            
            $allowed = ['draft', 'active', 'started', 'finished'];
            if (!in_array($newStatus, $allowed)) {
                return $this->error('Invalid status', 400);
            }

            // Security: We won't let a teacher start a quiz if it doesn't have at least one question!
            if ($newStatus === 'active' || $newStatus === 'started') {
                $questionModel = new Question();
                $questionsCount = count($questionModel->getByQuizId($quizId));
                
                $classesCount = count($quizModel->getAllowedClasses($quizId));

                if ($questionsCount === 0) {
                    return $this->error('Cannot ' . $newStatus . ' quiz without questions. Please add at least one question.', 400);
                }
                if ($classesCount === 0) {
                    return $this->error('Cannot ' . $newStatus . ' quiz without assigned classes. Please select at least one class.', 400);
                }
            }

            $updateData = ['status' => $newStatus];
            if ($newStatus === 'started') {
                $updateData['start_time'] = date('Y-m-d H:i:s');
            }

            $quizModel->update($quizId, $updateData);
            
            return $this->success(['status' => $newStatus], 'Quiz status updated');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // Fetches real-time stats for the "Live Board".
    public function getLiveProgress($teacherId, $quizId) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }

            $resultModel = new Result();
            $stats = $resultModel->getLiveStats($quizId);
            
            return $this->success($stats);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // This is a life-saver! It allows teachers to add more time while the quiz is running.
    public function adjustTime($teacherId, $quizId, $data) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }

            if ($quiz['status'] !== 'started') {
                return $this->error('Can only adjust time for a started quiz', 400);
            }

            $this->validateRequiredFields($data, ['adjustment']);
            $adjustment = (int)$data['adjustment']; // in minutes

            $newDuration = (int)$quiz['duration_minutes'] + $adjustment;
            if ($newDuration < 1) {
                $newDuration = 1; 
            }

            $quizModel->update($quizId, ['duration_minutes' => $newDuration]);
            
            return $this->success([
                'new_duration' => $newDuration,
                'adjustment_applied' => $adjustment
            ], 'Time adjusted successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // Special controls to Pause, Resume, or Block a specific student.
    public function controlStudent($teacherId, $resultId, $data) {
        try {
            $this->validateRequiredFields($data, ['action']);
            $action = $data['action']; // 'pause', 'resume', 'block'
            
            $resultModel = new Result();
            $result = $resultModel->find($resultId);
            
            if (!$result) {
                return $this->error('Result not found', 404);
            }
            
            $quizModel = new Quiz();
            $quiz = $quizModel->find($result['quiz_id']);
            
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Unauthorized', 403);
            }
            
            $updateData = [];
            if ($action === 'pause') {
                $updateData['is_paused'] = 1;
            } elseif ($action === 'resume') {
                $updateData['is_paused'] = 0;
            } elseif ($action === 'block') {
                $updateData['is_blocked'] = 1;
            } else {
                return $this->error('Invalid action', 400);
            }
            
            if ($resultModel->update($resultId, $updateData)) {
                return $this->success(['action' => $action], 'Student control updated');
            } else {
                throw new Exception('Failed to update student control');
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
