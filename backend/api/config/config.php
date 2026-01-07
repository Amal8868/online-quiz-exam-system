<?php
// Hey! This is the config file. It's like the "Settings" page for our backend.
// Everything from database names to timezone goes here.

// I turned off 'display_errors' so that if something breaks, PHP doesn't spit out raw code.
// Instead, it stays quiet so our API can return clean JSON messages.
error_reporting(E_ALL);
ini_set('display_errors', 0); 

// Database configuration - This is how we talk to MySQL.
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'amal1234'); // Make sure this matches your XAMPP password!
define('DB_NAME', 'online_quiz_system');

// JWT Secret - This is a secret key for security. Keep it safe!
define('JWT_SECRET', 'your_jwt_secret_key_here');
define('JWT_ALGORITHM', 'HS256');

// App settings - Basic info about where the project is running.
define('APP_NAME', 'Online Quiz System');
define('APP_URL', 'http://localhost'); 
define('FRONTEND_URL', 'http://localhost:3000'); // This is where our React app lives.

// Timezone - Set this so our "created_at" timestamps are correct.
date_default_timezone_set('Asia/Riyadh');

// This function is the "Middleman" that actually connects us to the database.
// I used a "static" variable so we don't accidentally open 100 connections at once.
function getDBConnection() {
    static $conn;
    
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw errors if queries fail.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Give us data as nice arrays.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // If we can't connect, we tell the frontend it's a 500 server error.
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed. Is MySQL running?',
                'error' => $e->getMessage()
            ]);
            exit();
        }
    }
    
    return $conn;
}
