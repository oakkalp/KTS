<?php
/**
 * Kurye Full System - API Authentication Helper
 * JWT token doğrulama ve yetki kontrolü
 */

/**
 * JWT token'ı doğrula ve kullanıcı bilgilerini döndür
 */
function authenticateJWT() {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($auth_header)) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'MISSING_TOKEN',
                'message' => 'Authorization header gereklidir'
            ]
        ], 401);
    }
    
    // Bearer token'ı al
    if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TOKEN_FORMAT',
                'message' => 'Bearer token formatı gereklidir'
            ]
        ], 401);
    }
    
    $jwt = $matches[1];
    
    try {
    // JWT'yi parçala
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
            throw new Exception('Geçersiz JWT formatı');
        }
        
        list($header, $payload, $signature) = $parts;
    
    // Signature'ı doğrula
        $expected_signature = str_replace(['+', '/', '='], ['-', '_', ''], 
            base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true))
        );
        
        if (!hash_equals($expected_signature, $signature)) {
            throw new Exception('Geçersiz token imzası');
    }
    
    // Payload'ı decode et
        $payload_data = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if (!$payload_data) {
            throw new Exception('Geçersiz token payload');
        }
        
        // Token süresi kontrolü
        if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'TOKEN_EXPIRED',
                'message' => 'Token süresi dolmuş'
            ]
        ], 401);
    }
    
        // Kullanıcı bilgilerini döndür
        return [
            'user_id' => $payload_data['user_id'],
            'username' => $payload_data['username'],
            'user_type' => $payload_data['user_type'],
            'type_id' => $payload_data['type_id'] ?? null,
            'device_id' => $payload_data['device_id'] ?? null,
            'app_type' => $payload_data['app_type'] ?? 'web'
        ];
        
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TOKEN',
                'message' => 'Geçersiz token: ' . $e->getMessage()
            ]
        ], 401);
    }
}

// Rate limiting function is defined in includes/functions.php

// jsonResponse function is defined in config/config.php

/**
 * API log kaydet
 */
function logApiRequest($user_id, $endpoint, $method, $status_code, $response_time = null) {
    try {
        $log_data = [
            'timestamp' => date('c'),
            'user_id' => $user_id,
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $status_code,
            'response_time' => $response_time,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $log_line = json_encode($log_data) . "\n";
        file_put_contents(LOGS_PATH . '/api_requests.log', $log_line, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // Log hatası önemli değil
    }
}

/**
 * CORS headers'ları ayarla
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Input validation
 */
function validateInput($input, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule_set) {
        $value = $input[$field] ?? null;
        
        foreach ($rule_set as $rule) {
            switch ($rule) {
                case 'required':
                    if (empty($value)) {
                        $errors[$field][] = "$field gereklidir";
                    }
                    break;
                case 'email':
                    if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "$field geçerli bir email adresi olmalıdır";
                    }
                    break;
                case 'numeric':
                    if ($value && !is_numeric($value)) {
                        $errors[$field][] = "$field sayısal bir değer olmalıdır";
                    }
                    break;
                case 'phone':
                    if ($value && !preg_match('/^[0-9+\-\s\(\)]{10,}$/', $value)) {
                        $errors[$field][] = "$field geçerli bir telefon numarası olmalıdır";
                    }
                    break;
            }
        }
    }
    
    return $errors;
}

/**
 * Sanitize input
 */
function cleanInput($input) {
    if (is_array($input)) {
        return array_map('cleanInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>