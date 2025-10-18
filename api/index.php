<?php
// api/index.php

// Temel ayarları ve veritabanı bağlantısını dahil et
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Gelen isteğin yolunu (path) al
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Projenin alt dizinde çalışıp çalışmadığını kontrol et
$base_path = dirname($script_name);
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
}

// API yolunu (request path) ayıkla
$request_path = str_replace($base_path, '', $request_uri);
$request_path = parse_url($request_path, PHP_URL_PATH);
$request_path = trim($request_path, '/');

// Güvenlik için, yolun sadece izin verilen karakterleri içerdiğinden emin ol
// Bu, directory traversal gibi saldırıları önlemeye yardımcı olur.
if (preg_match('/[^a-zA-Z0-9_\/\-]/ ', $request_path)) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['success' => false, 'error' => ['code' => 'BAD_REQUEST', 'message' => 'Invalid characters in request path']]);
    exit;
}

// İstenen endpoint dosyasının yolunu oluştur
// Örnek: /auth/login isteği -> /auth/login.php dosyasına yönlenir
$endpoint_file = __DIR__ . '/' . $request_path . '.php';

// Endpoint dosyası var mı diye kontrol et
if (file_exists($endpoint_file)) {
    // CORS (Cross-Origin Resource Sharing) başlıkları
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    // OPTIONS isteği için ön kontrol (preflight)
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    // Endpoint dosyasını çalıştır
    require_once $endpoint_file;
} else {
    // Dosya bulunamazsa 404 Not Found hatası döndür
    header("HTTP/1.1 404 Not Found");
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        'success' => false, 
        'error' => [
            'code' => 'NOT_FOUND', 
            'message' => 'Endpoint not found.',
            'requested_path' => $request_path,
            'resolved_file' => $endpoint_file
        ]
    ]);
}