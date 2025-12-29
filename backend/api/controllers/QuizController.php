<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/Question.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Result.php';

class QuizController extends BaseController {
    
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

    public function getQuizzes($teacherId) {
        $quiz = new Quiz();
        $quizzes = $quiz->all(['teacher_id' => $teacherId], 'created_at DESC');
        return $this->success($quizzes);
    }

    public function getQuizzesByClass($teacherId, $classId) {
        try {
            // Verify class belongs to teacher (optional but recommended)
            // For now, just fetch filtering by teacher_id in the model if needed, 
            // but the class/quiz relationship is already built on teacher data.
            $quizModel = new Quiz();
            $quizzes = $quizModel->getByClassId($classId);
            
            // Filter to ensure only teacher's quizzes are returned if there's any cross-over
            $filtered = array_filter($quizzes, function($q) use ($teacherId) {
                return $q['teacher_id'] == $teacherId;
            });
            
            return $this->success(array_values($filtered));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    public function deleteQuiz($teacherId, $quizId) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId); // Verify ownership
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                throw new Exception('Quiz not found or unauthorized', 404);
            }

            if ($quizModel->deleteQuiz($quizId)) { // Uses the new method
                 return $this->success(['message' => 'Quiz deleted successfully']);
            } else {
                 throw new Exception('Failed to delete quiz');
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function createQuiz($teacherId, $data) {
        $db = getDBConnection();
        try {
            $this->validateRequiredFields($data, ['title']);
            
            $db->beginTransaction();
            
            $roomCode = $this->generateRoomCode();
            
            $quiz = new Quiz();
            $quizId = $quiz->create([
                'teacher_id' => $teacherId,
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'room_code' => $roomCode,
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'timer_type' => $data['timer_type'] ?? 'exam', 
                'status' => 'draft'
            ]);

            // Handle integrated class selection
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
    
    public function getQuiz($teacherId, $quizId) {
        $quizModel = new Quiz();
        $quiz = $quizModel->find($quizId);
        
        if (!$quiz || $quiz['teacher_id'] != $teacherId) {
            return $this->error('Quiz not found', 404);
        }

        // Auto-finish check
        if ($quiz['status'] === 'started' && !empty($quiz['start_time'])) {
            $duration = (int)($quiz['duration_minutes'] ?? 30);
            $endTime = strtotime($quiz['start_time'] . " +{$duration} minutes");
            if (time() > $endTime) {
                $quizModel->update($quizId, ['status' => 'finished']);
                $quiz['status'] = 'finished';
            }
        }
        
        $questionModel = new Question();
        $questions = $questionModel->getByQuizId($quizId);

        $quiz['questions'] = $questions;
        
        $totalPoints = 0;
        foreach ($questions as $q) {
            $totalPoints += $q['points'] ?? 0;
        }
        $quiz['total_points'] = $totalPoints;
        
        // Add allowed classes
        $allowedClasses = $quizModel->getAllowedClasses($quizId);
        $quiz['allowed_classes'] = $allowedClasses;
        
        // Calculate total students from assigned classes if using class-based enrollment
        $totalClassStudents = 0;
        foreach ($allowedClasses as $class) {
            $totalClassStudents += (int)($class['student_count'] ?? 0);
        }
        
        // If we have class-based students, use that as the enrollment count
        // Otherwise fallback to the specific allowed students count (roster)
        if ($totalClassStudents > 0) {
            $quiz['allowed_students_count'] = $totalClassStudents;
        } else {
            $allowed = $quizModel->getAllowedStudents($quizId);
            $quiz['allowed_students'] = $allowed;
            $quiz['allowed_students_count'] = count($allowed);
        }
        
        // Add server time for timer synchronization
        $quiz['server_time'] = date('Y-m-d H:i:s');
        
        return $this->success($quiz);
    }

    // Update quiz details
    public function updateQuiz($teacherId, $quizId, $data) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found or unauthorized', 404);
            }
            // Allowed fields for update
            $allowed = ['title', 'description', 'duration_minutes', 'timer_type', 'status', 'room_code'];
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

            // Clear existing
            $stmt = $db->prepare("DELETE FROM quiz_classes WHERE quiz_id = ?");
            $stmt->execute([$quizId]);

            // Add new
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
                // Skip header row if it contains "student_id" or "name"
                if ($row === 1) {
                     if (strtolower(trim($data[0])) === 'student_id' || strtolower(trim($data[1])) === 'name') {
                         continue;
                     }
                }
                
                // Expecting Col 0: Student ID, Col 1: Name
                // Basic validation: must have at least 2 columns
                if (count($data) < 2) continue;
                
                $studentIdStr = trim($data[0]);
                $name = trim($data[1]);
                
                if (empty($studentIdStr) || empty($name)) continue;
                
                $studentDbId = $studentModel->findOrCreate($studentIdStr, $name);
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


    public function setStatus($teacherId, $quizId, $data) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }

            $this->validateRequiredFields($data, ['status']);
            $newStatus = $data['status'];
            
            // Validate status transitions
            $allowed = ['draft', 'active', 'started', 'finished'];
            if (!in_array($newStatus, $allowed)) {
                return $this->error('Invalid status', 400);
            }

            // Validation for Start/Activate
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
                $newDuration = 1; // Minimum 1 minute
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
