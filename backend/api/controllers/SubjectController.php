<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Subject.php';
require_once __DIR__ . '/../models/ClassModel.php';

class SubjectController extends BaseController {

    public function getAllSubjects() {
        try {
            $subject = new Subject();
            $result = $subject->all([], 'created_at DESC');
            
            foreach ($result as &$sub) {
                $classes = $subject->getClasses($sub['id']);
                $sub['class_count'] = count($classes);
                $sub['class_ids'] = $classes;
            }

            return $this->success($result);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getSubject($id) {
        try {
            $subject = new Subject();
            $result = $subject->find($id);
            if (!$result) {
                return $this->error('Subject not found', 404);
            }
            $result['class_ids'] = $subject->getClasses($id);
            return $this->success($result);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function createSubject($data) {
        try {
            $this->validateRequiredFields($data, ['name', 'code']);

            $subject = new Subject();
            $id = $subject->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? ''
            ]);

            if ($id) {
                if (isset($data['class_ids']) && is_array($data['class_ids'])) {
                     $subject->assignToClasses($id, $data['class_ids']);
                }
                return $this->success(['message' => 'Subject created', 'id' => $id], 201);
            }
            return $this->error('Failed to create subject', 500);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                 return $this->error('Subject code already exists', 400);
            }
            return $this->error($e->getMessage());
        }
    }

    public function updateSubject($id, $data) {
        try {
            $subject = new Subject();
            if ($subject->update($id, [
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? ''
            ])) {
                if (isset($data['class_ids']) && is_array($data['class_ids'])) {
                     $subject->assignToClasses($id, $data['class_ids']);
                }
                return $this->success(['message' => 'Subject updated']);
            }
            return $this->error('Failed to update subject');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                 return $this->error('Subject code already exists', 400);
            }
            return $this->error($e->getMessage());
        }
    }

    public function deleteSubject($id) {
        try {
            $subject = new Subject();
            if ($subject->delete($id)) {
                return $this->success(['message' => 'Subject deleted']);
            }
            return $this->error('Failed to delete subject');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function getClassSubjects($classId) {
        try {
            $subject = new Subject();
            $result = $subject->getByClass($classId);
            return $this->success($result);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
?>
