<?php
require_once __DIR__ . '/backend/api/config/config.php';

try {
    $db = getDBConnection();
    echo "Starting migration...\n";

    // 1. Create/Update Classes Table
    echo "Ensuring classes table and columns exist...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        section VARCHAR(50) DEFAULT '',
        academic_year VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
    )");

    // Add missing columns if table existed but was incomplete
    $classColumns = $db->query("SHOW COLUMNS FROM classes")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('teacher_id', $classColumns)) {
        echo "Adding teacher_id to classes...\n";
        $db->exec("ALTER TABLE classes ADD COLUMN teacher_id INT NOT NULL AFTER id");
        $db->exec("ALTER TABLE classes ADD FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE");
    }
    
    if (!in_array('section', $classColumns)) {
        echo "Adding section to classes...\n";
        $db->exec("ALTER TABLE classes ADD COLUMN section VARCHAR(50) DEFAULT '' AFTER name");
    }
    
    if (!in_array('academic_year', $classColumns)) {
        echo "Adding academic_year to classes...\n";
        $db->exec("ALTER TABLE classes ADD COLUMN academic_year VARCHAR(20) NOT NULL AFTER section");
    }

    // 2. Update Students Table
    echo "Updating students table...\n";
    // Check if column exists first
    $columns = $db->query("SHOW COLUMNS FROM students LIKE 'class_id'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE students ADD COLUMN class_id INT DEFAULT NULL");
        $db->exec("ALTER TABLE students ADD FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL");
    }

    // 3. Create Quiz Classes Junction
    echo "Creating quiz_classes table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS quiz_classes (
        quiz_id INT NOT NULL,
        class_id INT NOT NULL,
        PRIMARY KEY (quiz_id, class_id),
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    )");

    // 4. Create Index
    echo "Creating index...\n";
    try {
        $db->exec("CREATE INDEX idx_student_class ON students(student_id, class_id)");
    } catch (Exception $e) {
        // Index might already exist
        echo "Index already exists or failed: " . $e->getMessage() . "\n";
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
