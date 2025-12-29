<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Set headers for CORS and JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

$apiIndex = array_search('api', $uri);
if ($apiIndex !== false) {
    $routes = array_slice($uri, $apiIndex + 1);
} else {
    $routes = $uri;
}

$routePath = implode('/', $routes);

// --- Router Logic ---

$response = [];
$statusCode = 200;

try {
    // --- Public Routes ---
    
    if ($routePath === 'teachers/login' && $method === 'POST') {
        require_once __DIR__ . '/controllers/TeacherController.php';
        $controller = new TeacherController();
        $response = $controller->login(getJsonInput());
        
    } elseif ($routePath === 'teachers/register' && $method === 'POST') {
        require_once __DIR__ . '/controllers/TeacherController.php';
        $controller = new TeacherController();
        $response = $controller->register(getJsonInput());

    } elseif ($routePath === 'teachers/reset-password' && $method === 'POST') {
        require_once __DIR__ . '/controllers/TeacherController.php';
        $controller = new TeacherController();
        $response = $controller->resetPassword(getJsonInput());

    // --- Student Routes ---
    
    } elseif ($routePath === 'student/enter' && $method === 'POST') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->enterExam(getJsonInput());
        
    } elseif ($routePath === 'student/start' && $method === 'POST') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->startExam(getJsonInput());
        
    } elseif (preg_match('/^student\/quiz\/(\d+)\/status$/', $routePath, $matches) && $method === 'GET') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->getQuizStatus($matches[1]);
        
    } elseif (preg_match('/^student\/exam\/(\d+)\/questions$/', $routePath, $matches) && $method === 'GET') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->getExamQuestions($matches[1]);
        
    } elseif ($routePath === 'student/answer' && $method === 'POST') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->submitAnswer(getJsonInput());
        
    } elseif ($routePath === 'student/finish' && $method === 'POST') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->finishExam(getJsonInput());
        
    } elseif (preg_match('/^student\/results\/(\d+)$/', $routePath, $matches) && $method === 'GET') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->getResult($matches[1]);
        
    } elseif (preg_match('/^student\/results\/(\d+)\/status$/', $routePath, $matches) && $method === 'POST') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->updateResultStatus($matches[1], getJsonInput());

    } elseif (preg_match('/^student\/results\/(\d+)\/status_check$/', $routePath, $matches) && $method === 'GET') {
        require_once __DIR__ . '/controllers/StudentController.php';
        $controller = new StudentController();
        $response = $controller->getAttemptStatus($matches[1]);

    // --- Protected Teacher Routes ---
    
    } else {
        $auth = AuthMiddleware::authenticate('teacher');
        $teacherId = $auth->id;
        
        if ($routePath === 'quizzes') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
             if ($method === 'GET') {
                $response = $controller->getQuizzes($teacherId);
             } elseif ($method === 'POST') {
                $response = $controller->createQuiz($teacherId, getJsonInput());
             }
             
        } elseif (preg_match('/^quizzes\/(\d+)$/', $routePath, $matches)) {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
             if ($method === 'GET') {
                $response = $controller->getQuiz($teacherId, $matches[1]);
             } elseif ($method === 'PUT') {
                $response = $controller->updateQuiz($teacherId, $matches[1], getJsonInput());
             } elseif ($method === 'DELETE') {
                $response = $controller->deleteQuiz($teacherId, $matches[1]);
             }
            
        } elseif (preg_match('/^quizzes\/(\d+)\/questions$/', $routePath, $matches)) {
            if ($method === 'POST') {
                require_once __DIR__ . '/controllers/QuizController.php';
                $controller = new QuizController();
                $response = $controller->addQuestion($teacherId, $matches[1], getJsonInput());
            } elseif ($method === 'GET') {
                require_once __DIR__ . '/controllers/QuestionController.php';
                $controller = new QuestionController();
                $response = $controller->getQuestions($teacherId, $matches[1]);
            }

        } elseif (preg_match('/^questions\/(\d+)$/', $routePath, $matches)) {
             require_once __DIR__ . '/controllers/QuestionController.php';
             $controller = new QuestionController();
             if ($method === 'PUT') {
                 $response = $controller->updateQuestion($teacherId, $matches[1], getJsonInput());
             } elseif ($method === 'DELETE') {
                 $response = $controller->deleteQuestion($teacherId, $matches[1]);
             }
            
        } elseif (preg_match('/^quizzes\/(\d+)\/roster$/', $routePath, $matches) && $method === 'POST') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
            $response = $controller->uploadRoster($teacherId, $matches[1], $_FILES);
            
        } elseif (preg_match('/^quizzes\/(\d+)\/results$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
            $response = $controller->getResults($teacherId, $matches[1]);
            
            

        } elseif (preg_match('/^quizzes\/(\d+)\/status$/', $routePath, $matches) && $method === 'POST') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
            $response = $controller->setStatus($teacherId, $matches[1], getJsonInput());

        } elseif (preg_match('/^results\/(\d+)\/control$/', $routePath, $matches) && $method === 'POST') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
            $response = $controller->controlStudent($teacherId, $matches[1], getJsonInput());

        } elseif (preg_match('/^quizzes\/(\d+)\/adjust-time$/', $routePath, $matches) && $method === 'POST') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
            $response = $controller->adjustTime($teacherId, $matches[1], getJsonInput());

        } elseif (preg_match('/^quizzes\/(\d+)\/monitoring$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
            $response = $controller->getLiveProgress($teacherId, $matches[1]);
            

        } elseif (preg_match('/^quizzes\/(\d+)\/classes$/', $routePath, $matches) && $method === 'POST') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
            $response = $controller->setQuizClasses($teacherId, $matches[1], getJsonInput());

        } elseif ($routePath === 'classes/import' && $method === 'POST') {
            require_once __DIR__ . '/controllers/ClassController.php';
            $controller = new ClassController();
            $response = $controller->importGlobal($teacherId, getJsonInput());

        } elseif ($routePath === 'classes' && $method === 'GET') {
            require_once __DIR__ . '/controllers/ClassController.php';
            $controller = new ClassController();
            $response = $controller->getClasses($teacherId);

        } elseif ($routePath === 'classes' && $method === 'POST') {
            require_once __DIR__ . '/controllers/ClassController.php';
            $controller = new ClassController();
            $response = $controller->createClass($teacherId, getJsonInput());

        } elseif (preg_match('/^classes\/(\d+)$/', $routePath, $matches)) {
            require_once __DIR__ . '/controllers/ClassController.php';
            $controller = new ClassController();
            if ($method === 'GET') {
                $response = $controller->getClassDetails($teacherId, $matches[1]);
            } elseif ($method === 'PUT') {
                $response = $controller->updateClass($teacherId, $matches[1], getJsonInput());
            }

        } elseif (preg_match('/^classes\/(\d+)\/students$/', $routePath, $matches) && $method === 'POST') {
            require_once __DIR__ . '/controllers/ClassController.php';
            $controller = new ClassController();
            $response = $controller->uploadStudents($teacherId, $matches[1], getJsonInput());

        } elseif (preg_match('/^classes\/(\d+)\/quizzes$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();
            $response = $controller->getQuizzesByClass($teacherId, $matches[1]);

        } elseif ($routePath === 'students/check' && $method === 'GET') {
            require_once __DIR__ . '/controllers/StudentController.php';
            $controller = new StudentController();
            $response = $controller->checkStudentsExist($teacherId);

        } elseif (preg_match('/^classes\/(\d+)\/students$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/ClassController.php';
            $controller = new ClassController();
            $response = $controller->getClassDetails($teacherId, $matches[1]);


        } elseif ($routePath === 'teacher/dashboard' && $method === 'GET') {
            require_once __DIR__ . '/controllers/TeacherController.php';
            $controller = new TeacherController();
            $response = $controller->getDashboard($teacherId);

        // --- NEW RESULT ROUTES ---
        } elseif (preg_match('/^teachers\/results\/(\d+)\/(\d+)$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/ResultController.php';
            $controller = new ResultController();
            $response = $controller->getClassQuizResults($teacherId, $matches[1], $matches[2]); // classId, quizId

        } elseif (preg_match('/^teachers\/results\/(\d+)$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/ResultController.php';
            $controller = new ResultController();
            $response = $controller->getStudentResult($teacherId, $matches[1]); // resultId

        } elseif (preg_match('/^teachers\/results\/(\d+)\/grade$/', $routePath, $matches) && $method === 'POST') {
            require_once __DIR__ . '/controllers/ResultController.php';
            $controller = new ResultController();
            $response = $controller->saveGrade($teacherId, $matches[1]); // resultId
            
        } elseif (preg_match('/^submissions\/(\d+)$/', $routePath, $matches)) {
             require_once __DIR__ . '/controllers/SubmissionController.php';
             $controller = new SubmissionController();
             if ($method === 'GET') {
                 $response = $controller->getSubmission($teacherId, $matches[1]);
             } elseif ($method === 'PUT') {
                 $response = $controller->updateSubmission($teacherId, $matches[1], getJsonInput());
             } elseif ($method === 'DELETE') {
                 $response = $controller->deleteSubmission($teacherId, $matches[1]);
             }

        } elseif (preg_match('/^classes\/(\d+)\/export$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/ExportController.php';
            $controller = new ExportController();
            $controller->exportClassGradebook($teacherId, $matches[1]);
            
        } else {
            throw new Exception('Route not found: ' . $routePath, 404);
        }
    }

} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    if ($statusCode < 100 || $statusCode > 599) $statusCode = 500;
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Final response delivery
// If a controller already set a status code via http_response_code(), we should respect it
// unless we are in the catch block which has a specific $statusCode.
$currentCode = http_response_code();
if ($statusCode === 200 && $currentCode !== 200) {
    // Controller set a specific code, keep it
} else {
    http_response_code($statusCode);
}

echo json_encode($response);

function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
