<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Set headers for CORS and JSON response
header('Content-Type: application/json');

// Get the request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

// Remove 'backend/api' from the URI path if present
// Adjust this logic based on your actual server configuration
// If the server root is 'backend', then 'api' might be the first segment
if (($key = array_search('api', $uri)) !== false) {
    $uri = array_slice($uri, $key + 1);
}

// Get request data
$request = [];
if ($method === 'POST' || $method === 'PUT') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $request = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $request = $_POST;
    }
}

// Route the request
$route = implode('/', $uri);
$response = [];
$statusCode = 200;

try {
    switch ($route) {
        case 'teachers/register':
            if ($method === 'POST') {
                require_once __DIR__ . '/controllers/TeacherController.php';
                $controller = new TeacherController();
                $response = $controller->register($request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'teachers/login':
            if ($method === 'POST') {
                require_once __DIR__ . '/controllers/TeacherController.php';
                $controller = new TeacherController();
                $response = $controller->login($request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        // Add other routes here as needed, mirroring backend/public/index.php
        // For now, focusing on Teacher Register
            
        default:
            // Fallback to check if it matches other patterns or return 404
            // For safety, let's include the main router logic if we want full parity
            // But for this specific task, ensuring register works is key.
            // Let's copy the essential parts from public/index.php but adjusted for path
            
             // Teacher Dashboard
            if ($route === 'teacher/dashboard') {
                require_once __DIR__ . '/middleware/AuthMiddleware.php';
                $auth = AuthMiddleware::authenticate('teacher');
                
                require_once __DIR__ . '/controllers/TeacherController.php';
                $controller = new TeacherController();
                
                if ($method === 'GET') {
                    $response = $controller->getDashboardStats($auth->id);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
            }
            
            throw new Exception('Not Found: ' . $route, 404);
    }
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $statusCode
    ];
}

// Set the HTTP response code
http_response_code($statusCode);

// Output the response as JSON
echo json_encode($response);
