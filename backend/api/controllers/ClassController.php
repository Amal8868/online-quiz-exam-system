<?php
// This is the "Classroom Manager". 
// It handles creating classes, adding students to them, and organizing the school data.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/Student.php';

class ClassController extends BaseController {
    // 1. "Show me my classes" - Fetches all groups belonging to the teacher.
    public function getClasses($teacherId) {
        try {
            $classModel = new ClassModel();
            $classes = $classModel->getTeacherClasses($teacherId);
            return $this->success($classes);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    // 2. "New Class" - Creates a new section like "Grade 10 - Section A".
    public function createClass($teacherId, $data) {
        try {
            $this->validateRequiredFields($data, ['name', 'academic_year']);
            
            $classModel = new ClassModel();
            $classId = $classModel->createClass(
                $teacherId,
                $data['name'],
                $data['section'] ?? '',
                $data['academic_year']
            );
            
            return $this->success(['class_id' => $classId], 'Class created successfully', 201);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // 3. "Fix details" - Updates class info if there was a typo in the name or year.
    public function updateClass($teacherId, $classId, $data) {
        try {
            $classModel = new ClassModel();
            $class = $classModel->find($classId);
            
            // SECURITY: Make sure this teacher actually owns the class they are trying to edit!
            if (!$class || $class['teacher_id'] != $teacherId) {
                return $this->error('Class not found or unauthorized', 404);
            }
            
            $allowed = ['name', 'section', 'academic_year'];
            $updateData = [];
            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            if (empty($updateData)) {
                return $this->error('No valid fields to update', 400);
            }
            
            $classModel->update($classId, $updateData);
            return $this->success(['message' => 'Class updated']);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // 4. "Who is in this class?" - Lists all the students enrolled in a specific section.
    public function getClassDetails($teacherId, $classId) {
        try {
            $classModel = new ClassModel();
            $class = $classModel->getClassWithStudents($classId, $teacherId);
            return $this->success($class);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // 5. "Add many students" - This handles importing a list of students specifically into THIS class.
    public function uploadStudents($teacherId, $classId, $data) {
        try {
            $this->validateRequiredFields($data, ['students']);
            
            $classModel = new ClassModel();
            $result = $classModel->addStudentsToClass($classId, $teacherId, $data['students']);
            
            return $this->success($result, 'Students processed successfully');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // 6. "The Master Import" - This is powerful! It looks at a whole CSV, 
    // creates classes on the fly, and puts students in them.
    public function importGlobal($teacherId, $data) {
        try {
            $this->validateRequiredFields($data, ['students']);
            
            $classModel = new ClassModel();
            $result = $classModel->importGlobalRoster($teacherId, $data['students']);
            
            return $this->success($result, 'Global import processed successfully');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
