<?php
require_once __DIR__ . '/backend/api/config/config.php';
require_once __DIR__ . '/backend/api/controllers/ExportController.php';

// Mock authentication/context
$teacherId = 1; // Assuming teacher 1 exists

try {
    $db = getDBConnection();
    // Get a valid class ID
    $stmt = $db->prepare("SELECT id FROM classes WHERE teacher_id = ? LIMIT 1");
    $stmt->execute([$teacherId]);
    $classId = $stmt->fetchColumn();

    if (!$classId) {
        die("No class found for teacher 1.\n");
    }

    echo "Testing Export for Class ID: $classId\n";

    // Simulate Controller Call
    $controller = new ExportController();
    $controller->exportClassGradebook($teacherId, $classId);

} catch (Exception $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
}
