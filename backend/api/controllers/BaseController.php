<?php
// This is the base for all our "Managers" (Controllers).
// I put the common logic for sending JSON responses here so I don't repeat myself.
class BaseController {
    
    // When everything goes right, I use this to send a happy message back to the frontend.
    protected function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }
    
    // When something breaks, I use this to send a "Sad" message back.
    protected function error($message = 'An error occurred', $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ];
    }
    
    // This is like a "Bouncer" for our data. 
    // It checks if the frontend sent all the fields we need (like username and password).
    protected function validateRequiredFields($data, $requiredFields) {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || ($data[$field] === '' && $data[$field] !== 0 && $data[$field] !== '0')) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            // If something is missing, we stop everything and throw an error.
            throw new Exception('Missing required fields: ' . implode(', ', $missing), 400);
        }
    }
}
