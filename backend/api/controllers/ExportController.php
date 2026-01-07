<?php
// This is the "Report Card Generator". 
// It takes all the scores from the database and turns them into a nice CSV file (Excel) that teachers can download.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/Result.php';
require_once __DIR__ . '/../models/Quiz.php';

class ExportController extends BaseController {
    
    // 1. "Make the Spreadsheet" - This gathers students and their quiz scores for a specific class.
    public function exportClassGradebook($teacherId, $classId) {
        try {
            // STEP 1: Permission check. Only the class teacher should be able to download these scores!
            $classModel = new ClassModel();
            $data = $classModel->getClassWithStudents($classId, $teacherId);
            $class = $data['class'];
            $students = $data['students']; 
            
            // STEP 2: Find all the quizzes that this class was supposed to take.
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
            
            // STEP 3: Create a "Score Map". 
            // It's like a big table where we can quickly look up what "Student A" got on "Quiz B".
            $studentIds = array_column($students, 'id');
            $resultsMap = []; 
            
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
            
            // STEP 4: Start writing the CSV file.
            // We tell the browser: "Hey, I'm sending a file, not a web page!"
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $class['name'] . '_Gradebook.csv"');
            
            $output = fopen('php://output', 'w');
            
            // THE HEADER ROW: [Student Name, Student ID, Quiz 1 (Score), Quiz 2 (Score)...]
            $header = ['Student Name', 'Student ID'];
            foreach ($quizzes as $quiz) {
                $header[] = $quiz['title'] . " (/" . $quiz['total_points'] . ")";
            }
            $header[] = 'Total Score';
            fputcsv($output, $header);
            
            // THE DATA ROWS: One row per student.
            foreach ($students as $student) {
                $row = [
                    $student['name'],
                    $student['student_id']
                ];
                
                $totalScore = 0;
                
                // For every quiz in the header, we look up the student's score in our map.
                foreach ($quizzes as $quiz) {
                    $data = $resultsMap[$student['id']][$quiz['id']] ?? null;
                    if ($data && $data['is_blocked']) {
                        $scoreValue = 'BLOCKED';
                    } else {
                        $scoreValue = $data['score'] ?? '-'; // '-' means they didn't take it.
                    }
                    
                    $row[] = $scoreValue;
                    if (is_numeric($scoreValue)) {
                        $totalScore += $scoreValue;
                    }
                }
                
                $row[] = $totalScore;
                
                // Write this student's row to the file.
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit; // Stop everything else so we only return the file data.
            
        } catch (Exception $e) {
            // If it fails, we log the error so the developer can find it later.
            file_put_contents('C:/xampp/htdocs/online-quiz-exam-system/debug_export_error.txt', $e->getMessage());
            return $this->error($e->getMessage(), 500);
        }
    }
}
