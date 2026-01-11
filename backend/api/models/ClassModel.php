<?php
// This is where "Classes" are born! 
// I used this model to organize students into groups like "Grade 10A".
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/User.php';

class ClassModel extends Model {
    protected $table = 'classes';
    protected $fillable = ['name', 'section', 'academic_year', 'teacher_id'];
    
    // Creating a new class is a big deal, so I used a "transaction". 
    // It means if any part fails, the whole thing cancels so we don't end up with half-made classes.
    public function createClass($teacherId, $name, $section = '', $academicYear) {
        $this->beginTransaction();
        try {
            // 1. Create the class entry.
            $classId = $this->create([
                'name' => $name,
                'section' => $section,
                'academic_year' => $academicYear,
                'teacher_id' => $teacherId
            ]);
            
            // 2. We also need to link the teacher to this class in the mapping table.
            if ($teacherId) {
                $this->assignTeacher($classId, $teacherId);
            }
            
            $this->commit(); // Save everything!
            return $classId;
        } catch (Exception $e) {
            $this->rollBack(); // Uh oh, something broke. Undo everything!
            throw $e;
        }
    }
    
    // This fetches all classes for the admin to see, 
    // and I added some fancy sub-queries to count students and teachers on the fly.
    public function getAllClasses() {
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) as student_count,
                (SELECT GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') 
                 FROM class_teachers ct 
                 JOIN users u ON ct.teacher_id = u.id 
                 WHERE ct.class_id = c.id) as teachers
                FROM {$this->table} c
                ORDER BY c.academic_year DESC, c.name, c.section";
        return $this->query($sql)->fetchAll();
    }
    
    // Linking a teacher to a class. I used "INSERT IGNORE" so we don't get errors if they are already linked.
    public function assignTeacher($classId, $teacherId) {
        $sql = "INSERT IGNORE INTO class_teachers (class_id, teacher_id) VALUES (?, ?)";
        $this->query($sql, [$classId, $teacherId]);
    }
    
    // Useful for the teacher's dashboard to see only their own classes.
    public function getTeacherClasses($teacherId) {
        $sql = "SELECT DISTINCT c.*, 
                (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) as student_count,
                (SELECT COUNT(*) FROM quiz_classes qc WHERE qc.class_id = c.id) as quiz_count
                FROM {$this->table} c
                LEFT JOIN class_teachers ct ON c.id = ct.class_id
                WHERE ct.teacher_id = ? OR c.teacher_id = ?
                ORDER BY c.academic_year DESC, c.name, c.section";
                
        return $this->query($sql, [$teacherId, $teacherId])->fetchAll();
    }
    
    // When a teacher clicks a class, they want to see the list of students in it.
    public function getClassWithStudents($classId, $teacherId = null) {
        // First, we check if this teacher actually has permission to see this class.
        if ($teacherId && !$this->isTeacherOfClass($classId, $teacherId)) {
            throw new Exception('Class not found or access denied', 404);
        }
        
        $class = $this->find($classId);
        
        // We join the 'users' table with 'class_students' mapping table to find everyone.
        $sql = "SELECT u.id, u.user_id as student_id, CONCAT(u.first_name, ' ', u.last_name) as name, u.status,
                (SELECT COUNT(*) FROM results r WHERE r.student_user_id = u.id) as quiz_count
                FROM users u
                JOIN class_students cs ON u.id = cs.student_id
                WHERE cs.class_id = ? AND u.user_type = 'Student'
                ORDER BY u.first_name, u.last_name";
                
        $students = $this->query($sql, [$classId])->fetchAll();
        
        return [
            'class' => $class,
            'students' => $students
        ];
    }
    
    private function isTeacherOfClass($classId, $teacherId) {
        $sql = "SELECT 1 FROM class_teachers WHERE class_id = ? AND teacher_id = ?";
        return $this->query($sql, [$classId, $teacherId])->fetchColumn();
    }
    
    // This is a life-saver! Instead of adding students one by one, 
    // a teacher can import a whole list at once.
    public function addStudentsToClass($classId, $teacherId, $studentsData) {
        $this->beginTransaction();
        
        try {
            if (!$this->isTeacherOfClass($classId, $teacherId)) {
                throw new Exception('Class not found or access denied', 404);
            }
            
            $userModel = new User();
            $addedCount = 0;
            $updatedCount = 0;
            $errors = [];
            
            foreach ($studentsData as $index => $studentData) {
                try {
                    if (empty($studentData['student_id']) || empty($studentData['name'])) {
                        throw new Exception('Student ID and name are required');
                    }
                    
                    $studentId = $studentData['student_id'];
                    $fullName = $studentData['name'];
                    
                    // Simple name splitter logic.
                    $parts = explode(' ', $fullName, 2);
                    $firstName = $parts[0];
                    $lastName = $parts[1] ?? '';
                    
                    // Does this student already exist?
                    $student = $userModel->findByUserId($studentId);
                    $dbUserId = null;
                    
                    if ($student) {
                        // If they exist, we just update their name.
                        $userModel->updateUser($student['id'], [
                            'first_name' => $firstName,
                            'last_name' => $lastName
                        ]);
                        $dbUserId = $student['id'];
                        $updatedCount++;
                    } else {
                        // If they are new, we create a fresh account for them.
                        $dbUserId = $userModel->createUser([
                            'user_id' => $studentId,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'user_type' => 'Student',
                            'status' => 'Active'
                        ]);
                        $addedCount++;
                    }
                    
                    // Link the student's ID to the class.
                    $this->assignStudentToClass($classId, $dbUserId);
                    
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
    
    public function assignStudentToClass($classId, $studentUserId) {
        $sql = "INSERT IGNORE INTO class_students (class_id, student_id) VALUES (?, ?)";
        $this->query($sql, [$classId, $studentUserId]);
    }

    // This is the "God Mode" import. It can create new classes on the fly 
    // while importing students from a CSV.
    public function importGlobalRoster($teacherId, $studentsData) {
        $this->beginTransaction();
        try {
            $classCache = []; // I used this to remember class IDs so I don't look them up every row.
            $addedCount = 0;
            $updatedCount = 0;
            $classCreatedCount = 0;
            $errors = [];

            $userModel = new User();

            foreach ($studentsData as $index => $row) {
                try {
                    $studentId = $row['student_id'] ?? '';
                    $fullName = $row['name'] ?? '';
                    $className = $row['class'] ?? 'Default Class';
                    $section = $row['section'] ?? '';
                    $academicYear = $row['academic_year'] ?? date('Y');

                    if (empty($studentId) || empty($fullName)) {
                        throw new Exception('Missing ID or Name');
                    }
                    
                    $parts = explode(' ', $fullName, 2);
                    $firstName = $parts[0];
                    $lastName = $parts[1] ?? '';

                    // Check if we already found/created this class in this session.
                    $cacheKey = $className . '|' . $section;
                    if (!isset($classCache[$cacheKey])) {
                        $sql = "SELECT c.id FROM classes c 
                                JOIN class_teachers ct ON c.id = ct.class_id 
                                WHERE ct.teacher_id = ? AND c.name = ? AND c.section = ?";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([$teacherId, $className, $section]);
                        $class = $stmt->fetch();

                        if ($class) {
                            $classCache[$cacheKey] = $class['id'];
                        } else {
                            // If the class doesn't exist, we just make it!
                            $newClassId = $this->createClass($teacherId, $className, $section, $academicYear);
                            $classCache[$cacheKey] = $newClassId;
                            $classCreatedCount++;
                        }
                    }

                    $actualClassId = $classCache[$cacheKey];

                    // Handle student creation/update just like before.
                    $student = $userModel->findByUserId($studentId);
                    $dbUserId = null;

                    if ($student) {
                        $userModel->updateUser($student['id'], [
                           'first_name' => $firstName,
                           'last_name' => $lastName
                        ]);
                        $dbUserId = $student['id'];
                        $updatedCount++;
                    } else {
                        $dbUserId = $userModel->createUser([
                            'user_id' => $studentId,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'user_type' => 'Student'
                        ]);
                        $addedCount++;
                    }
                    
                    $this->assignStudentToClass($actualClassId, $dbUserId);

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

    public function countAll() {
        return (int)$this->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }
    public function getUsageStats() {
        $sql = "SELECT c.id, c.name, c.section,
                (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) as student_count,
                (SELECT COUNT(*) FROM quiz_classes qc WHERE qc.class_id = c.id) as quiz_count
                FROM {$this->table} c
                ORDER BY student_count DESC, quiz_count DESC";
        return $this->query($sql)->fetchAll();
    }
}
