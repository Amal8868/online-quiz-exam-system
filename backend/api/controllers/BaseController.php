<?php
class BaseController {
    protected function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }
    
    protected function error($message = 'An error occurred', $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ];
    }
    
    protected function validateRequiredFields($data, $requiredFields) {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing), 400);
        }
    }
}
