<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/Quiz.php';

class ExportController extends BaseController {
    
    public function exportClassGradebook($teacherId, $classId) {
        try {
            // 1. Verify access
            $classModel = new ClassModel();
            $data = $classModel->getClassWithStudents($classId, $teacherId);
            $class = $data['class'];
            $students = $data['students']; // List of students
            
            // 2. Get all quizzes for this class
            // We need a method to get quizzes assigned to a class. 
            // QuizController might have it, or we query directly.
            // Let's use a raw query here for efficiency or add to Quiz model.
            // Assuming 'quiz_classes' table links quizzes and classes.
            // Assuming 'quiz_classes' table links quizzes and classes.
            $conn = getDBConnection();
            
            $stmt = $conn->prepare("
                SELECT q.id, q.title, 
                (SELECT COALESCE(SUM(points), 0) FROM questions WHERE quiz_id = q.id) as total_points
                FROM quizzes q
                JOIN quiz_classes qc ON q.id = qc.quiz_id
                WHERE qc.class_id = ?
                ORDER BY q.created_at ASC
            ");
            $stmt->execute([$classId]);
            $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3. Get all results for these students and quizzes
            // Efficient approach: Get all results for this class's students
            // or query specifically where quiz_id IN (...)
            
            $studentIds = array_column($students, 'id');
            $resultsMap = []; // [student_id][quiz_id] => score
            
            if (!empty($studentIds)) {
                $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
                $stmt = $conn->prepare("
                    SELECT student_id, quiz_id, score, is_blocked
                    FROM results 
                    WHERE student_id IN ($placeholders)
                ");
                $stmt->execute($studentIds);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $resultsMap[$row['student_id']][$row['quiz_id']] = [
                        'score' => $row['score'],
                        'is_blocked' => (bool)$row['is_blocked']
                    ];
                }
            }
            
            // 4. Generate CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $class['name'] . '_Gradebook.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Header Row: Student Name, Student ID, Quiz 1, Quiz 2, ..., Total
            $header = ['Student Name', 'Student ID'];
            foreach ($quizzes as $quiz) {
                $header[] = $quiz['title'] . " (/" . $quiz['total_points'] . ")";
            }
            $header[] = 'Total Score';
            fputcsv($output, $header);
            
            // Data Rows
            foreach ($students as $student) {
                $row = [
                    $student['name'],
                    $student['student_id']
                ];
                
                $totalScore = 0;
                
                foreach ($quizzes as $quiz) {
                    $data = $resultsMap[$student['id']][$quiz['id']] ?? null;
                    if ($data && $data['is_blocked']) {
                        $scoreValue = 'BLOCKED';
                    } else {
                        $scoreValue = $data['score'] ?? '-';
                    }
                    
                    $row[] = $scoreValue;
                    if (is_numeric($scoreValue)) {
                        $totalScore += $scoreValue;
                    }
                }
                
                $row[] = $totalScore;
                
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit; // Stop execution to return only file
            
        } catch (Exception $e) {
            file_put_contents('C:/xampp/htdocs/online-quiz-exam-system/debug_export_error.txt', $e->getMessage());
            return $this->error($e->getMessage(), 500);
        }
    }
}
