<?php
// This is the "Admin Control Center". 
// I built this to handle all the "big boss" stuff like managing users and classes.
// It's like the principal's office for the digital school!
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/ClassModel.php';

class AdminController extends BaseController {
    
    // --- USER MANAGEMENT SECTION ---

    // 1. "New User" - This function creates a new person in our system. 
    // It could be a student, a teacher, or even another admin.
    public function createUser($data, $files = []) {
        try {
            // First, make sure the vital parts aren't missing!
            $this->validateRequiredFields($data, ['first_name', 'last_name', 'user_type']);

            // If they uploaded a profile picture, we need to save it properly in our 'uploads' folder.
            if (!empty($files['profile_pic']) && $files['profile_pic']['error'] === UPLOAD_ERR_OK) {
                try {
                    $uploadDir = __DIR__ . '/../../uploads/avatars/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    // We give the file a unique name so people don't accidentally overwrite each other's photos.
                    $extension = pathinfo($files['profile_pic']['name'], PATHINFO_EXTENSION);
                    $filename = 'user_temp_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
                    $targetPath = $uploadDir . $filename;

                    if (move_uploaded_file($files['profile_pic']['tmp_name'], $targetPath)) {
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];
                        $data['profile_pic'] = "$protocol://$host/online-quiz-exam-system/backend/uploads/avatars/$filename";
                    }
                } catch (Exception $e) {
                    // If the photo fails, we still create the user but without the photo.
                }
            }

            $userModel = new User();

            // Check if the names are valid (no numbers allowed!) and other rules.
            $validation = $this->validateUserData($data, $userModel);
            if ($validation !== true) return $validation;
            
            // If the admin didn't provide a User ID (like STU-101), we generate one automatically.
            if (empty($data['user_id'])) {
                $data['user_id'] = $this->generateUserId($data['user_type']);
            } else {
                 // But if they did provide one, we must make sure it's not already taken.
                 if ($userModel->findByUserId($data['user_id'])) {
                    return $this->error('User ID already exists', 409);
                }
            }

            // Cleanup: If fields are blank, we turn them into NULLs so they don't mess up the database.
            $optionalUniqueFields = ['username', 'email', 'phone', 'password'];
            foreach ($optionalUniqueFields as $field) {
                if (isset($data[$field]) && trim($data[$field]) === '') {
                    $data[$field] = null;
                }
            }

            // --- Magic Auto-Generation for Teachers & Students ---
            // To save time, we can auto-generate usernames and temporary passwords.
            $generatedCredentials = null;
            if ($data['user_type'] === 'Teacher' || $data['user_type'] === 'Student') {
                if (empty($data['username'])) {
                    $data['username'] = $userModel->generateSequentialUsername($data['user_type']);
                }
                
                $tempPassword = null;
                if (empty($data['password']) || trim($data['password']) === '') {
                    $tempPassword = $userModel->generateTempPassword();
                    $data['password'] = $tempPassword;
                }
                
                // We keep track of what we generated so we can show it to the admin later.
                if ($tempPassword || isset($data['username'])) {
                    $generatedCredentials = [
                        'username' => $data['username'],
                        'password' => $tempPassword
                    ];
                }
            }

            // Finally, save the new user to the database!
            $userId = $userModel->createUser($data);
            
            $responseData = [
                'id' => $userId, 
                'user_id' => $data['user_id'],
                'profile_pic' => $data['profile_pic'] ?? null
            ];

            // If we auto-made a password, send it back so the admin can give it to the student/teacher.
            if ($generatedCredentials) {
                $responseData['generated'] = $generatedCredentials;
            }

            return $this->success($responseData, 'User created successfully', 201);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    // 2. "Who's in the system?" - Fetches a list of all users.
    public function getUsers($type = null) {
        try {
            $userModel = new User();
            $users = $userModel->getAllUsers($type);
            return $this->success($users);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 3. "Update Profile" - Handles changes to a user's information.
    public function updateUser($id, $data, $files = []) {
        try {
            // Handle profile picture update if a new file was uploaded.
            if (!empty($files['profile_pic']) && $files['profile_pic']['error'] === UPLOAD_ERR_OK) {
                try {
                    $uploadDir = __DIR__ . '/../../uploads/avatars/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $extension = pathinfo($files['profile_pic']['name'], PATHINFO_EXTENSION);
                    $filename = 'user_' . $id . '_' . time() . '.' . $extension;
                    $targetPath = $uploadDir . $filename;

                    if (move_uploaded_file($files['profile_pic']['tmp_name'], $targetPath)) {
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];
                        $data['profile_pic'] = "$protocol://$host/online-quiz-exam-system/backend/uploads/avatars/$filename";
                    }
                } catch (Exception $e) {
                }
            }

            $userModel = new User();

            // Run our validation checks again to make sure the new data is safe.
            $validation = $this->validateUserData($data, $userModel, true, $id);
            if ($validation !== true) return $validation;

            $allowedFields = ['first_name', 'last_name', 'username', 'email', 'phone', 'gender', 'user_type', 'status', 'password', 'user_id', 'profile_pic'];
            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (empty($updateData)) {
                return $this->error('No valid fields to update', 400);
            }
            
            $userModel->updateUser($id, $updateData);
            return $this->success(null, 'User updated successfully');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return $this->error('Username or Email already exists', 409);
            }
            return $this->error($e->getMessage(), 500);
        }
    }

    // 4. "Remove User" - Deletes a user permanently.
    public function deleteUser($userId) {
        try {
            $userModel = new User();
            $user = $userModel->find($userId);
            
            if (!$user) {
                return $this->error('User not found', 404);
            }

            // Clean up the legacy students table if the user exists there.
            if ($user['user_type'] === 'Student') {
                $db = getDBConnection();
                $stmt = $db->prepare("DELETE FROM students WHERE student_id = ?");
                $stmt->execute([$user['user_id']]);
            }

            $userModel->delete($userId);
            return $this->success(null, 'User deleted successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    // --- HELPER SECTION (Internal Logic) ---

    // This is the "Safety Brain" - it contains all the validation rules.
    private function validateUserData($data, $userModel, $isUpdate = false, $userId = null) {
        // Names shouldn't have numbers in them.
        if ((isset($data['first_name']) && preg_match('/[0-9]/', $data['first_name'])) || 
            (isset($data['last_name']) && preg_match('/[0-9]/', $data['last_name']))) {
            return $this->error('Names cannot contain numbers', 400);
        }

        // Email validation: make sure it's a real format and not already taken.
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->error('Please provide a valid email address', 400);
            }
            $existing = $userModel->findByEmail($data['email']);
            if ($existing && (!$isUpdate || $existing['id'] != $userId)) {
                return $this->error('Email already exists', 409);
            }
        }

        // Somali Phone Number Check (+252...).
        if (!empty($data['phone'])) {
            $phone = preg_replace('/\s+/', '', $data['phone']);
            if (!preg_match('/^(0)?(61|62|68|77)\d{7}$/', $phone)) {
                return $this->error('Invalid phone number format', 400);
            }
            // Check for duplicate phone
            $existingPhone = $userModel->findByPhone($data['phone']);
            if ($existingPhone && (!$isUpdate || $existingPhone['id'] != $userId)) {
                return $this->error('Phone number already exists', 409);
            }
        }

        return true;
    }

    private function generateUserId($type) {
        $userModel = new User();
        return $userModel->generateNextUserId($type);
    }

    // --- CLASS MANAGEMENT SECTION ---

    // 1. "List Classes" - Fetches all classes in the system.
    public function getClasses() {
        try {
            $classModel = new ClassModel();
            $classes = $classModel->getAllClasses();
            return $this->success($classes);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 2. "New Class" - Creates a new section like "Grade 9A".
    public function createClass($data) {
        try {
            $this->validateRequiredFields($data, ['name', 'academic_year']);
            
            $classModel = new ClassModel();
            $teacherId = $data['teacher_id'] ?? null;
            
            $classId = $classModel->createClass(
                $teacherId,
                $data['name'],
                $data['section'] ?? '',
                $data['academic_year']
            );
            
            return $this->success(['class_id' => $classId], 'Class created successfully', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 3. "Assign Teacher" - Links a teacher to a class they are responsible for.
    public function assignTeacher($classId, $data) {
        try {
            if (empty($data['teacher_id'])) {
                return $this->error('Teacher ID is required', 400);
            }
            
            $classModel = new ClassModel();
            $classModel->assignTeacher($classId, $data['teacher_id']);
            
            return $this->success(null, 'Teacher assigned successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 4. "Class Details" - Shows which students are in this class.
    public function getClassDetails($classId) {
        try {
            $classModel = new ClassModel();
            $class = $classModel->getClassWithStudents($classId, null);
            return $this->success($class);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 5. "Enrol Student" - Adds a student to a class list.
    public function assignStudent($classId, $data) {
        try {
            if (empty($data['student_id'])) {
                return $this->error('Student ID is required', 400);
            }
            
            $classModel = new ClassModel();
            $classModel->assignStudentToClass($classId, $data['student_id']);
            
            return $this->success(null, 'Student assigned successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 6. "Edit Class" - Updates class info.
    public function updateClass($classId, $data) {
        try {
            $classModel = new ClassModel();
            $allowed = ['name', 'section', 'academic_year'];
            $updateData = array_intersect_key($data, array_flip($allowed));

            if (empty($updateData)) {
                return $this->error('No valid fields to update', 400);
            }
            $classModel->update($classId, $updateData);
            return $this->success(null, 'Class updated successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 7. "Remove Class" - Deletes a class permanently.
    public function deleteClass($classId) {
        try {
            $classModel = new ClassModel();
            $classModel->delete($classId);
            return $this->success(null, 'Class deleted successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // --- DASHBOARD STATS ---

    // 8. "Admin Summary" - Gets total counts for the admin dashboard. 
    public function getStats() {
        try {
            $classModel = new ClassModel();
            $userModel = new User();
            
            return [
                'success' => true,
                'data' => [
                    'users' => $userModel->countAll(),
                    'classes' => $classModel->countAll()
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
