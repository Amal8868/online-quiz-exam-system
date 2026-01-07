<?php
// This is the "Security Guard" of the application. 
// It handles everything related to logging in, logging out, and making sure users are who they say they are.
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController extends BaseController {
    
    // 1. "Log Me In!" - This verifies the user's email/username and password.
    public function login($data) {
        try {
            $this->validateRequiredFields($data, ['email', 'password']); 
            
            $userModel = new User();
            
            // We search for the user by their email first. 
            // If that fails, we try their username (convenient!).
            $user = $userModel->findByEmail($data['email']);
            if (!$user) {
                $user = $userModel->findByUsername($data['email']); 
            }
            
            if (!$user) {
                return $this->error('Invalid credentials', 401); // 401 means "Who are you?"
            }
            
            // If the account was deactivated by an admin, we stop them here.
            if ($user['status'] !== 'Active') {
                return $this->error('Account is not active', 403);
            }
            
            // Password Check: We compare the typed password with the "secret hash" in the database.
            if (!password_verify($data['password'], $user['password'])) {
                return $this->error('Invalid credentials', 401);
            }
            
            // SESSION: We store their ID in a "Server Session" so the server remembers them 
            // as they move between different pages.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['user_type'];
            $_SESSION['email'] = $user['email'];
            
            // REMEMBER ME: If they checked the box, we give them a 30-day "VIP Cookie".
            if (isset($data['remember_me']) && $data['remember_me'] === true) {
                $cookie_name = "user_login";
                $cookie_value = AuthMiddleware::generateJWT(['id' => $user['id'], 'role' => $user['user_type']], 86400 * 30); 
                setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/"); 
            }
            
            $payload = [
                'id' => $user['id'],
                'role' => $user['user_type'],
                'name' => $user['first_name'] . ' ' . $user['last_name']
            ];
            
            // We generate a JWT token (a specialized digital signature) for the frontend.
            $token = AuthMiddleware::generateJWT($payload);
            
            return $this->success([
                'token' => $token, 
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'email' => $user['email'],
                    'role' => $user['user_type'],
                    'gender' => $user['gender'],
                    'profile_pic' => $user['profile_pic'] ?? null,
                    'first_login' => (bool)$user['first_login']
                ]
            ], 'Login successful');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 2. "New Profile Pic!" - Handles the file upload for the user's avatar.
    public function updateProfile($data, $files) {
        try {
            // First, we verify the user is actually logged in.
            $decoded = AuthMiddleware::authenticate();
            $userId = $decoded->id;

            // Did they actually pick a file?
            if (!isset($files['profile_pic']) || $files['profile_pic']['error'] !== UPLOAD_ERR_OK) {
                return $this->error('No file uploaded or upload error', 400);
            }

            $file = $files['profile_pic'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            // SECURITY: Never trust the file extension alone. We check the 'Mime Type' to ensure it's a real image.
            if (!in_array($mimeType, $allowedTypes)) {
                return $this->error('Invalid file type. Only JPG, PNG, WEBP, and GIF are allowed.', 400);
            }

            // Path to where the avatars are stored on the server.
            $uploadDir = __DIR__ . '/../../uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // We give the file a unique name so it doesn't overwrite someone else's photo.
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $filename;

            // Move the file from 'temporary storage' to our 'official folder'.
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $userModel = new User();
                
                // Build the full web URL for the image so the browser can see it.
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = "$protocol://$host/online-quiz-exam-system/backend/uploads/avatars/$filename";
                
                // Update the database with the new photo link.
                $userModel->updateUser($userId, ['profile_pic' => $baseUrl]);

                return $this->success(['profile_pic' => $baseUrl], 'Profile picture updated successfully');
            } else {
                return $this->error('Failed to save file', 500);
            }

        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // 3. "Who am I?" - This is used when a user refreshes the page. 
    // It checks if they still have a valid session or cookie.
    public function me() {
        try {
            // This checks Header, Cookie, and Session in that order.
            $decoded = AuthMiddleware::authenticate();
            
            $userModel = new User();
            $user = $userModel->find($decoded->id);
            
            if (!$user) {
                return $this->error('User not found', 404);
            }
            
            // We refresh the token just in case the old one was about to expire.
            $payload = [
                'id' => $user['id'],
                'role' => $user['user_type'],
                'name' => $user['first_name'] . ' ' . $user['last_name']
            ];
            $token = AuthMiddleware::generateJWT($payload);

            return $this->success([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'email' => $user['email'],
                    'role' => $user['user_type'],
                    'gender' => $user['gender'],
                    'profile_pic' => $user['profile_pic'] ?? null,
                    'first_login' => (bool)$user['first_login']
                ]
            ], 'Session active');
            
        } catch (Exception $e) {
            return $this->error('No active session', 401);
        }
    }

    // 4. "Bye!" - This wipes out all the session and cookie data.
    public function logout() {
        // Clear out the Server Session.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = array();
        
        // This effectively "Deletes" the session cookie from the user's browser.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        // Also kill the "Remember Me" cookie.
        if (isset($_COOKIE['user_login'])) {
            setcookie('user_login', '', time() - 3600, '/');
        }
        
        return $this->success([], 'Logged out successfully');
    }
}
