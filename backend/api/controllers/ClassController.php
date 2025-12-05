<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/Student.php';

class ClassController extends BaseController {
    // Get all classes for a teacher
    public function getClasses($teacherId) {
        try {
            $classModel = new ClassModel();
            $classes = $classModel->getTeacherClasses($teacherId);
            return $this->success($classes);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    // Create a new class
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
    
    // Get class details with students
    public function getClassDetails($teacherId, $classId) {
        try {
            $classModel = new ClassModel();
            $class = $classModel->getClassWithStudents($classId, $teacherId);
            return $this->success($class);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
