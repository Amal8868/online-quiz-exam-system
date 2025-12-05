<?php
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/middleware/AuthMiddleware.php';

// Set headers for CORS and JSON response
header('Content-Type: application/json');

// Get the request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

// Remove 'backend/public' from the URI path if present
if (($key = array_search('backend', $uri)) !== false) {
    $uri = array_slice($uri, $key + 1);
}
if (($key = array_search('public', $uri)) !== false) {
    $uri = array_slice($uri, $key + 1);
}

// Define the base path
$basePath = 'api';
$request = [];

// Get request data
if ($method === 'POST' || $method === 'PUT') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $request = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $request = $_POST;
    }
}

// Get query parameters
$queryParams = $_GET;

// Route the request
$route = implode('/', $uri);
$response = [];
$statusCode = 200;

try {
    switch ($route) {
        case 'api/teachers/register':
            if ($method === 'POST') {
                require_once __DIR__ . '/../api/controllers/TeacherController.php';
                $controller = new TeacherController();
                $response = $controller->register($request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'api/teachers/login':
            if ($method === 'POST') {
                require_once __DIR__ . '/../api/controllers/TeacherController.php';
                $controller = new TeacherController();
                $response = $controller->login($request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case 'api/classes':
            require_once __DIR__ . '/../api/middleware/AuthMiddleware.php';
            $auth = AuthMiddleware::authenticate('teacher');
            
            require_once __DIR__ . '/../api/controllers/ClassController.php';
            $controller = new ClassController();
            
            if ($method === 'GET') {
                $response = $controller->getClasses($auth->id);
            } elseif ($method === 'POST') {
                $response = $controller->createClass($auth->id, $request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case (preg_match('/^api\/quizzes(\/\d+)?$/', $route) ? true : false):
            require_once __DIR__ . '/../api/middleware/AuthMiddleware.php';
            $auth = AuthMiddleware::authenticate('teacher');
            
            require_once __DIR__ . '/../api/controllers/QuizController.php';
            $controller = new QuizController();
            
            if ($method === 'GET') {
                if (isset($uri[2]) && is_numeric($uri[2])) {
                    $response = $controller->getQuiz($auth->id, $uri[2]);
                } else {
                    $response = $controller->getQuizzes($auth->id);
                }
            } elseif ($method === 'POST') {
                $response = $controller->createQuiz($auth->id, $request);
            } elseif ($method === 'PUT' && isset($uri[2]) && is_numeric($uri[2])) {
                $response = $controller->updateQuiz($auth->id, $uri[2], $request);
            } elseif ($method === 'DELETE' && isset($uri[2]) && is_numeric($uri[2])) {
                $response = $controller->deleteQuiz($auth->id, $uri[2]);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case (preg_match('/^api\/quizzes\/\d+\/questions$/', $route) ? true : false):
            require_once __DIR__ . '/../api/middleware/AuthMiddleware.php';
            $auth = AuthMiddleware::authenticate('teacher');
            
            require_once __DIR__ . '/../api/controllers/QuestionController.php';
            $controller = new QuestionController();
            
            $quizId = (int)$uri[2];
            
            if ($method === 'GET') {
                $response = $controller->getQuestions($auth->id, $quizId);
            } elseif ($method === 'POST') {
                $response = $controller->addQuestion($auth->id, $quizId, $request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case (preg_match('/^api\/quizzes\/\d+\/results$/', $route) ? true : false):
            require_once __DIR__ . '/../api/middleware/AuthMiddleware.php';
            $auth = AuthMiddleware::authenticate('teacher');
            
            require_once __DIR__ . '/../api/controllers/ResultController.php';
            $controller = new ResultController();
            
            $quizId = (int)$uri[2];
            
            if ($method === 'GET') {
                $response = $controller->getResults($auth->id, $quizId);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        // Student endpoints
        case 'api/student/login':
            if ($method === 'POST') {
                require_once __DIR__ . '/../api/controllers/StudentController.php';
                $controller = new StudentController();
                $response = $controller->login($request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        case 'api/student/verify':
            if ($method === 'POST') {
                require_once __DIR__ . '/../api/controllers/StudentController.php';
                $controller = new StudentController();
                $response = $controller->verifyStudent($request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;
            
        case (preg_match('/^api\/student\/quiz\/\w+$/', $route) ? true : false):
            $roomCode = $uri[3];
            
            if ($method === 'GET') {
                require_once __DIR__ . '/../api/controllers/StudentController.php';
                $controller = new StudentController();
                $response = $controller->getQuizByRoomCode($roomCode);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        case 'api/student/submit':
            if ($method === 'POST') {
                require_once __DIR__ . '/../api/controllers/StudentController.php';
                $controller = new StudentController();
                $response = $controller->submitExam($request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        case 'api/student/violation':
            if ($method === 'POST') {
                require_once __DIR__ . '/../api/controllers/StudentController.php';
                $controller = new StudentController();
                $response = $controller->logViolation($request);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        case (preg_match('/^api\/student\/results\/\d+$/', $route) ? true : false):
            $resultId = (int)$uri[3];
            if ($method === 'GET') {
                require_once __DIR__ . '/../api/controllers/StudentController.php';
                $controller = new StudentController();
                $response = $controller->getResult($resultId);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        // Teacher Dashboard
        case 'api/teacher/dashboard':
            require_once __DIR__ . '/../api/middleware/AuthMiddleware.php';
            $auth = AuthMiddleware::authenticate('teacher');
            
            require_once __DIR__ . '/../api/controllers/TeacherController.php';
            $controller = new TeacherController();
            
            if ($method === 'GET') {
                $response = $controller->getDashboardStats($auth->id);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        // Question Management (Single Question)
        case (preg_match('/^api\/questions\/\d+$/', $route) ? true : false):
            require_once __DIR__ . '/../api/middleware/AuthMiddleware.php';
            $auth = AuthMiddleware::authenticate('teacher');
            
            require_once __DIR__ . '/../api/controllers/QuestionController.php';
            $controller = new QuestionController();
            
            $questionId = (int)$uri[2];
            
            if ($method === 'PUT') {
                $response = $controller->updateQuestion($auth->id, $questionId, $request);
            } elseif ($method === 'DELETE') {
                $response = $controller->deleteQuestion($auth->id, $questionId);
            } else {
                throw new Exception('Method not allowed', 405);
            }
            break;

        default:
            throw new Exception('Not Found', 404);
    }
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    $response = [
        'error' => $e->getMessage(),
        'code' => $statusCode
    ];
}

// Set the HTTP response code
http_response_code($statusCode);

// Output the response as JSON
echo json_encode($response);
