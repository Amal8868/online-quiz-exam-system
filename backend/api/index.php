<?php
// This is the "Main Switchboard" of our Backend. 
// Every single request from the frontend (mobile or web) comes here first.
// It decides which "Manager" (Controller) should handle the request.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// STEP 1: Setting up the Rules of the Road (CORS and JSON).
// We tell the browser: "It's okay to talk to me, and I'll talk back in JSON format."
header('Content-Type: application/json');
$allowedOrigins = ['http://localhost:3000', 'http://localhost:3001', 'http://127.0.0.1:3000', 'http://127.0.0.1:3001'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// STEP 2: Start the Session.
// This is like giving the user a "Visitor Badge" so we remember who they are as they move around.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// STEP 3: "Oh No!" Handler.
// If something goes really wrong (like a typo in the code), this catches the error so the app doesn't just go blank.
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $error['message']]);
        exit;
    }
});

// STEP 4: Figuring out where the user wants to go.
// We look at the URL and split it into parts to understand the "Route".
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

$apiIndex = array_search('api', $uri);
if ($apiIndex !== false) {
    $routes = array_slice($uri, $apiIndex + 1);
} else {
    $routes = $uri;
}

$routes = array_values(array_filter($routes, function($segment) {
    return $segment !== 'index.php';
}));

$routePath = implode('/', $routes);

$response = [];
$statusCode = 200;

try {
    // --- PART 1: PUBLIC ROUTES ---
    // These are routes anyone can access (like Login or Signup).
    
    if (($routePath === 'teachers/login' || $routePath === 'auth/login') && $method === 'POST') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        $response = $controller->login(getJsonInput());
        
    } elseif ($routePath === 'teachers/register' && $method === 'POST') {
        require_once __DIR__ . '/controllers/TeacherController.php';
        $controller = new TeacherController();
        $response = $controller->register(getJsonInput());

    } elseif ($routePath === 'auth/me' && $method === 'GET') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        $response = $controller->me();

    } elseif ($routePath === 'teachers/reset-password' && $method === 'POST') {
        require_once __DIR__ . '/controllers/TeacherController.php';
        $controller = new TeacherController();
        $response = $controller->resetPassword(getJsonInput());
        
    } elseif ($routePath === 'auth/logout' && $method === 'POST') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        $response = $controller->logout();

    } elseif ($routePath === 'auth/update-profile' && $method === 'POST') {
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        $response = $controller->updateProfile($_POST, $_FILES);

    // --- PART 2: STUDENT ROUTES ---
    // These are used by students on the mobile app to take exams.
    
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

    // --- PART 3: ADMIN ROUTES (PROTECTED) ---
    // These require an "Admin" badge (JWT token).
    
    } elseif (strpos($routePath, 'admin/') === 0) {
        $auth = AuthMiddleware::authenticate('Admin');
        $adminId = $auth->id;
        
        require_once __DIR__ . '/controllers/AdminController.php';
        $controller = new AdminController();

        if ($routePath === 'admin/stats' && $method === 'GET') {
            $response = $controller->getStats();
            
        } elseif ($routePath === 'admin/reports' && $method === 'GET') {
            $response = $controller->getReports();
            
        } elseif ($routePath === 'admin/users' && $method === 'POST') {
            $data = array_merge($_POST, getJsonInput());
            $response = $controller->createUser($data, $_FILES);
            
        } elseif ($routePath === 'admin/users' && $method === 'GET') {
            $type = $_GET['type'] ?? null;
            $response = $controller->getUsers($type);

        } elseif (preg_match('/^admin\/users\/(\d+)$/', $routePath, $matches) && ($method === 'PUT' || $method === 'POST')) {
            $data = array_merge($_POST, getJsonInput());
            $response = $controller->updateUser($matches[1], $data, $_FILES);
            
        } elseif (preg_match('/^admin\/users\/(\d+)$/', $routePath, $matches) && $method === 'DELETE') {
            $response = $controller->deleteUser($matches[1]);
            
        } elseif ($routePath === 'admin/classes' && $method === 'GET') {
            $response = $controller->getClasses();

        } elseif ($routePath === 'admin/classes' && $method === 'POST') {
            $response = $controller->createClass(getJsonInput());

        } elseif (preg_match('/^admin\/classes\/(\d+)\/assign-teacher$/', $routePath, $matches) && $method === 'POST') {
            $response = $controller->assignTeacher($matches[1], getJsonInput());

        } elseif (preg_match('/^admin\/classes\/(\d+)\/assign-student$/', $routePath, $matches) && $method === 'POST') {
            $response = $controller->assignStudent($matches[1], getJsonInput());

        } elseif (preg_match('/^admin\/classes\/(\d+)$/', $routePath, $matches) && $method === 'GET') {
            $response = $controller->getClassDetails($matches[1]);

        } elseif (preg_match('/^admin\/classes\/(\d+)$/', $routePath, $matches) && $method === 'PUT') {
            $response = $controller->updateClass($matches[1], getJsonInput());

        } elseif (preg_match('/^admin\/classes\/(\d+)$/', $routePath, $matches) && $method === 'DELETE') {
            $response = $controller->deleteClass($matches[1]);

        // SUBJECT MANAGEMENT ROUTES
        } elseif ($routePath === 'admin/subjects' && $method === 'GET') {
            require_once __DIR__ . '/controllers/SubjectController.php';
            $controller = new SubjectController();
            $response = $controller->getAllSubjects();

        } elseif ($routePath === 'admin/subjects' && $method === 'POST') {
            require_once __DIR__ . '/controllers/SubjectController.php';
            $controller = new SubjectController();
            $response = $controller->createSubject(getJsonInput());

        } elseif (preg_match('/^admin\/subjects\/(\d+)$/', $routePath, $matches)) {
            require_once __DIR__ . '/controllers/SubjectController.php';
            $controller = new SubjectController();
            if ($method === 'GET') {
                $response = $controller->getSubject($matches[1]);
            } elseif ($method === 'PUT') {
                $response = $controller->updateSubject($matches[1], getJsonInput());
            } elseif ($method === 'DELETE') {
                $response = $controller->deleteSubject($matches[1]);
            }

        } else {
            throw new Exception('Admin Route not found: ' . $routePath, 404);
        }

    // --- PART 4: TEACHER ROUTES (PROTECTED) ---
    // These require a "Teacher" badge (JWT token).
    
    } else {
        // This 'authenticate' function checks if the teacher is logged in.
        $auth = AuthMiddleware::authenticate('Teacher');
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

        } elseif (preg_match('/^classes\/(\d+)\/subjects$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/SubjectController.php';
            $controller = new SubjectController();
            $response = $controller->getClassSubjects($matches[1]);

        } elseif ($routePath === 'students/check' && $method === 'GET') {
            require_once __DIR__ . '/controllers/StudentController.php';
            $controller = new StudentController();
            $response = $controller->checkStudentsExist($teacherId);

        } elseif ($routePath === 'teacher/dashboard' && $method === 'GET') {
            require_once __DIR__ . '/controllers/TeacherController.php';
            $controller = new TeacherController();
            $response = $controller->getDashboard($teacherId);

        } elseif ($routePath === 'teachers/change-password' && $method === 'POST') {
            require_once __DIR__ . '/controllers/TeacherController.php';
            $controller = new TeacherController();
            $response = $controller->changePassword($teacherId, getJsonInput());

        } elseif (preg_match('/^teachers\/results\/(\d+)\/(\d+)$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/ResultController.php';
            $controller = new ResultController();
            $response = $controller->getClassQuizResults($teacherId, $matches[1], $matches[2]);

        } elseif (preg_match('/^teachers\/results\/(\d+)$/', $routePath, $matches) && $method === 'GET') {
            require_once __DIR__ . '/controllers/ResultController.php';
            $controller = new ResultController();
            $response = $controller->getStudentResult($teacherId, $matches[1]);

        } elseif (preg_match('/^teachers\/results\/(\d+)\/grade$/', $routePath, $matches) && $method === 'POST') {
            require_once __DIR__ . '/controllers/ResultController.php';
            $controller = new ResultController();
            $response = $controller->saveGrade($teacherId, $matches[1]);
            
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
    // If anything breaks inside the 'try' block, we catch it here and send a nice error message.
    $statusCode = $e->getCode() ?: 500;
    if ($statusCode < 100 || $statusCode > 599) $statusCode = 500;
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// STEP 5: Delivery!
// We send the JSON data back to the frontend.
$currentCode = http_response_code();
if ($statusCode === 200 && $currentCode !== 200) {
} else {
    http_response_code($statusCode);
}

echo json_encode($response);

// HELPER: Grabs JSON data from the request body.
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
