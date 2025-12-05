<?php
require_once __DIR__ . '/../config/config.php';

class AuthMiddleware {
    public static function authenticate($requiredRole = null) {
        // Get the Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        // Check if the Authorization header exists and starts with 'Bearer '
        if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized - No token provided']);
            exit();
        }
        
        // Extract the token
        $token = substr($authHeader, 7);
        
        try {
            // Decode and verify the token
            $decoded = self::verifyJWT($token);
            
            // Check if the token has expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                throw new Exception('Token has expired');
            }
            
            // Check if the user has the required role
            if ($requiredRole && (!isset($decoded->role) || $decoded->role !== $requiredRole)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden - Insufficient permissions']);
                exit();
            }
            
            // Return the decoded token data
            return $decoded;
            
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized - ' . $e->getMessage()]);
            exit();
        }
    }
    
    public static function generateJWT($data, $expiry = 86400) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $time = time();
        $payload = [
            'iat' => $time,
            'exp' => $time + $expiry,
            'data' => $data
        ];
        
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", JWT_SECRET, true);
        $base64Signature = self::base64UrlEncode($signature);
        
        return "$base64Header.$base64Payload.$base64Signature";
    }
    
    private static function verifyJWT($token) {
        $tokenParts = explode('.', $token);
        
        if (count($tokenParts) !== 3) {
            throw new Exception('Invalid token format');
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $tokenParts;
        
        // Verify the signature
        $signature = self::base64UrlDecode($base64Signature);
        $expectedSignature = hash_hmac('sha256', "$base64Header.$base64Payload", JWT_SECRET, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid token signature');
        }
        
        $payload = json_decode(self::base64UrlDecode($base64Payload));
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid token payload');
        }
        
        return $payload->data;
    }
    
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
