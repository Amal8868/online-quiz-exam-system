<?php
require_once __DIR__ . '/Model.php';

class ClassModel extends Model {
    protected $table = 'classes';
    protected $fillable = ['teacher_id', 'name', 'section', 'academic_year'];
    
    // Create a new class
    public function createClass($teacherId, $name, $section = '', $academicYear) {
        $classId = $this->create([
            'teacher_id' => $teacherId,
            'name' => $name,
            'section' => $section,
            'academic_year' => $academicYear
        ]);
        
        return $classId;
    }
    
    // Get all classes for a teacher
    public function getTeacherClasses($teacherId) {
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM students WHERE class_id = c.id) as student_count,
                (SELECT COUNT(*) FROM quiz_classes WHERE class_id = c.id) as quiz_count
                FROM {$this->table} c
                WHERE c.teacher_id = ?
                ORDER BY c.academic_year DESC, c.name, c.section";
                
        return $this->query($sql, [$teacherId])->fetchAll();
    }
    
    // Get class details with students
    public function getClassWithStudents($classId, $teacherId) {
        // Verify the teacher owns this class
        $class = $this->find($classId);
        if (!$class || $class['teacher_id'] != $teacherId) {
            throw new Exception('Class not found or access denied', 404);
        }
        
        // Get students in this class
        $sql = "SELECT s.*, 
                (SELECT COUNT(*) FROM results r WHERE r.student_id = s.id) as quiz_count
                FROM students s
                WHERE s.class_id = ?
                ORDER BY s.name";
                
        $students = $this->query($sql, [$classId])->fetchAll();
        
        return [
            'class' => $class,
            'students' => $students
        ];
    }
    
    // Add students to class (bulk import)
    public function addStudentsToClass($classId, $teacherId, $studentsData) {
        $this->beginTransaction();
        
        try {
            // Verify the teacher owns this class
            $class = $this->find($classId);
            if (!$class || $class['teacher_id'] != $teacherId) {
                throw new Exception('Class not found or access denied', 404);
            }
            
            $studentModel = new Student();
            $addedCount = 0;
            $updatedCount = 0;
            $errors = [];
            
            foreach ($studentsData as $index => $studentData) {
                try {
                    // Validate student data
                    if (empty($studentData['student_id']) || empty($studentData['name'])) {
                        throw new Exception('Student ID and name are required');
                    }
                    
                    $studentId = $studentData['student_id'];
                    $name = $studentData['name'];
                    
                    // Check if student already exists in this class
                    $existing = $this->query(
                        "SELECT id FROM students WHERE student_id = ? AND class_id = ?",
                        [$studentId, $classId]
                    )->fetch();
                    
                    if ($existing) {
                        // Update existing student
                        $studentModel->update($existing['id'], [
                            'name' => $name,
                            'class_id' => $classId
                        ]);
                        $updatedCount++;
                    } else {
                        // Add new student
                        $studentModel->create([
                            'student_id' => $studentId,
                            'name' => $name,
                            'class_id' => $classId
                        ]);
                        $addedCount++;
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'student_id' => $studentData['student_id'] ?? 'N/A',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $this->commit();
            
            return [
                'success' => true,
                'added' => $addedCount,
                'updated' => $updatedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }
    // Global Roster Import (Detects and creates classes)
    public function importGlobalRoster($teacherId, $studentsData) {
        $this->beginTransaction();
        try {
            $classCache = []; // name|section -> id
            $addedCount = 0;
            $updatedCount = 0;
            $classCreatedCount = 0;
            $errors = [];

            $studentModel = new Student();

            foreach ($studentsData as $index => $row) {
                try {
                    $studentId = $row['student_id'] ?? '';
                    $name = $row['name'] ?? '';
                    $className = $row['class'] ?? 'Default Class';
                    $section = $row['section'] ?? '';
                    $academicYear = $row['academic_year'] ?? date('Y');

                    if (empty($studentId) || empty($name)) {
                        throw new Exception('Missing ID or Name');
                    }

                    // Handle Class detection/creation
                    $cacheKey = $className . '|' . $section;
                    if (!isset($classCache[$cacheKey])) {
                        // Check DB
                        $stmt = $this->db->prepare("SELECT id FROM classes WHERE teacher_id = ? AND name = ? AND section = ?");
                        $stmt->execute([$teacherId, $className, $section]);
                        $class = $stmt->fetch();

                        if ($class) {
                            $classCache[$cacheKey] = $class['id'];
                        } else {
                            // Create new
                            $newClassId = $this->createClass($teacherId, $className, $section, $academicYear);
                            $classCache[$cacheKey] = $newClassId;
                            $classCreatedCount++;
                        }
                    }

                    $actualClassId = $classCache[$cacheKey];

                    // Check if student exists globally or in class
                    $existing = $this->query("SELECT id FROM students WHERE student_id = ?", [$studentId])->fetch();

                    if ($existing) {
                        $studentModel->update($existing['id'], [
                            'name' => $name,
                            'class_id' => $actualClassId
                        ]);
                        $updatedCount++;
                    } else {
                        $studentModel->create([
                            'student_id' => $studentId,
                            'name' => $name,
                            'class_id' => $actualClassId
                        ]);
                        $addedCount++;
                    }

                } catch (Exception $e) {
                    $errors[] = ["row" => $index + 2, "error" => $e->getMessage()];
                }
            }

            $this->commit();
            return [
                "classes_created" => $classCreatedCount,
                "students_added" => $addedCount,
                "students_updated" => $updatedCount,
                "errors" => $errors
            ];
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
