<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display errors for API to return clean JSON

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'amal1234'); // Set your database password here
define('DB_NAME', 'online_quiz_system');

// JWT Secret Key (generate a strong secret key for production)
define('JWT_SECRET', 'your_jwt_secret_key_here');
define('JWT_ALGORITHM', 'HS256');

// Application settings
define('APP_NAME', 'Online Quiz System');
define('APP_URL', 'http://localhost'); // Update with your actual URL
define('FRONTEND_URL', 'http://localhost:3000'); // React frontend URL

// CORS settings
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Timezone
date_default_timezone_set('Asia/Riyadh');

// Database connection
function getDBConnection() {
    static $conn;
    
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ]);
            exit();
        }
    }
    
    return $conn;
}
