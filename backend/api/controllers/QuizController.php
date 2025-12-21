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
    
    public function createQuiz($teacherId, $data) {
        try {
            $this->validateRequiredFields($data, ['title']);
            
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
            
            return $this->success(['id' => $quizId, 'room_code' => $roomCode], 'Quiz created successfully', 201);
            
        } catch (Exception $e) {
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
        
        $allowed = $quizModel->getAllowedStudents($quizId);
        $quiz['allowed_students'] = $allowed; // Return full list
        $quiz['allowed_students_count'] = count($allowed);
        
        $totalPoints = 0;
        foreach ($questions as $q) {
            $totalPoints += $q['points'];
        }
        $quiz['total_points'] = $totalPoints;
        
        // Add allowed classes
        $quiz['allowed_classes'] = $quizModel->getAllowedClasses($quizId);
        
        return $this->success($quiz);
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
        return $this->success($results);
    }

    public function uploadMaterial($teacherId, $quizId, $files) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }
            
            if (!isset($files['material']) || $files['material']['error'] !== UPLOAD_ERR_OK) {
                return $this->error('No file uploaded or upload error', 400);
            }
            
            $file = $files['material'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            
            // Validate extension (allow PDF, DOC, DOCX, PPT, PPTX, TXT, IMG)
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];
            if (!in_array(strtolower($extension), $allowed)) {
                return $this->error('Invalid file type', 400);
            }
            
            $filename = 'material_' . $quizId . '_' . time() . '.' . $extension;
            $uploadDir = __DIR__ . '/../../uploads/materials/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Update DB
                $db = getDBConnection();
                $stmt = $db->prepare("UPDATE quizzes SET material_url = ? WHERE id = ?");
                $webPath = '/uploads/materials/' . $filename;
                $stmt->execute([$webPath, $quizId]);
                
                return $this->success(['url' => $webPath], 'Material uploaded successfully');
            } else {
                return $this->error('Failed to move uploaded file', 500);
            }
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
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

    public function getWaitingStudents($teacherId, $quizId) {
        try {
            $quizModel = new Quiz();
            $quiz = $quizModel->find($quizId);
            if (!$quiz || $quiz['teacher_id'] != $teacherId) {
                return $this->error('Quiz not found', 404);
            }

            $db = getDBConnection();
            $stmt = $db->prepare("
                SELECT s.student_id, s.name, r.status 
                FROM students s
                JOIN results r ON s.id = r.student_id
                WHERE r.quiz_id = ? AND r.status IN ('waiting', 'in_progress', 'submitted')
                ORDER BY r.status DESC, s.name ASC
            ");
            $stmt->execute([$quizId]);
            $students = $stmt->fetchAll();

            $stmt = $db->prepare("SELECT * FROM invalid_entries WHERE quiz_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$quizId]);
            $invalid = $stmt->fetchAll();

            return $this->success([
                'participants' => $students,
                'invalid_entries' => $invalid
            ]);
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
}
