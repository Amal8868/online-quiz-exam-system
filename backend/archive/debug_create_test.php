<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/QuizController.php';

// Mock Data
$teacherId = 1; // Assuming a teacher with ID 1 exists. If not, this might fail foreign key check.
// Check if teacher exists first or create one?
$db = getDBConnection();
$stmt = $db->query("SELECT id FROM teachers LIMIT 1");
$teacher = $stmt->fetch();
if ($teacher) {
    $teacherId = $teacher['id'];
} else {
    echo "No teacher found. Cannot test.\n";
    exit;
}

$data = [
    'title' => 'Test Quiz ' . time(),
    'description' => 'Test Description',
    'duration_minutes' => 30,
    'timer_type' => 'exam'
];

$controller = new QuizController();
$response = $controller->createQuiz($teacherId, $data);

echo "Response:\n";
print_r($response);

// JSON Encode check
echo "JSON Encoded:\n";
echo json_encode($response);
