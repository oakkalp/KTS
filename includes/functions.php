<?php
/**
 * Kurye Full System - Helper Functions
 * Yardımcı fonksiyonlar
 */

// XSS koruması
function sanitize($input) {
    if ($input === null) return '';
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// SQL injection koruması için input temizleme
function clean($input) {
    if ($input === null) return '';
    return trim(strip_tags($input));
}

// Şifre hashleme
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Şifre doğrulama
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Rastgele string oluştur
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Sipariş numarası oluştur
function generateOrderNumber() {
    return 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Mesafe hesaplama (Haversine formülü)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    
    return $earth_radius * $c;
}

// Teslimat süresi tahmini
function estimateDeliveryTime($distance, $vehicle_type = 'motosiklet') {
    $speeds = [
        'yürüyerek' => 5,      // km/h
        'bisiklet' => 15,      // km/h
        'motosiklet' => 30,    // km/h
        'araba' => 25          // km/h (şehir içi ortalama)
    ];
    
    $speed = $speeds[$vehicle_type] ?? $speeds['motosiklet'];
    $time_hours = $distance / $speed;
    $time_minutes = $time_hours * 60;
    
    // Hazırlık süresi ekle
    $preparation_time = 15; // dakika
    
    return ceil($time_minutes + $preparation_time);
}

// Tarih formatla
function formatDate($date, $format = 'd.m.Y H:i') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// Para formatla
function formatMoney($amount, $currency = '₺') {
    if ($amount === null || $amount === '') $amount = 0;
    return number_format((float)$amount, 2, ',', '.') . ' ' . $currency;
}

// Telefon numarası formatla
function formatPhone($phone) {
    if ($phone === null || $phone === '') return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 11 && substr($phone, 0, 1) == '0') {
        return substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7, 2) . ' ' . substr($phone, 9, 2);
    }
    return $phone;
}

// Email doğrulama
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Telefon numarası doğrulama (Türkiye)
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(05)[0-9]{9}$/', $phone) || preg_match('/^(5)[0-9]{9}$/', $phone);
}

// Dosya yükleme
function uploadFile($file, $directory = 'general', $allowed_types = null) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Geçersiz dosya parametresi');
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Dosya çok büyük (php.ini)',
            UPLOAD_ERR_FORM_SIZE => 'Dosya çok büyük (form)',
            UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi',
            UPLOAD_ERR_NO_FILE => 'Dosya yüklenmedi',
            UPLOAD_ERR_NO_TMP_DIR => 'Geçici dizin bulunamadı',
            UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı',
            UPLOAD_ERR_EXTENSION => 'PHP uzantısı dosya yüklemeyi durdurdu'
        ];
        throw new Exception($errors[$file['error']] ?? 'Bilinmeyen yükleme hatası');
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Dosya boyutu çok büyük. Maksimum: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    $allowed_types = $allowed_types ?? ALLOWED_IMAGE_TYPES;
    $extension = array_search($mime_type, [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ]);
    
    if ($extension === false || !in_array($extension, $allowed_types)) {
        throw new Exception('Geçersiz dosya türü');
    }
    
    $upload_dir = UPLOADS_PATH . '/' . $directory;
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = generateRandomString(20) . '.' . $extension;
    $filepath = $upload_dir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Dosya taşınamadı');
    }
    
    return $directory . '/' . $filename;
}

// Resim boyutlandır
function resizeImage($source, $destination, $max_width = 800, $max_height = 600, $quality = 85) {
    $info = getimagesize($source);
    if (!$info) return false;
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    // Oranı koru
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = $width * $ratio;
    $new_height = $height * $ratio;
    
    // Kaynak resmi oluştur
    switch ($mime) {
        case 'image/jpeg':
            $source_image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $source_image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    // Yeni resim oluştur
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // PNG ve GIF için şeffaflığı koru
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
    }
    
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Kaydet
    $result = false;
    switch ($mime) {
        case 'image/jpeg':
            $result = imagejpeg($new_image, $destination, $quality);
            break;
        case 'image/png':
            $result = imagepng($new_image, $destination);
            break;
        case 'image/gif':
            $result = imagegif($new_image, $destination);
            break;
    }
    
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    return $result;
}

// Push notification gönder (Firebase Cloud Messaging API v1)
function sendPushNotification($device_tokens, $title, $message, $data = []) {
    if (!is_array($device_tokens)) {
        $device_tokens = [$device_tokens];
    }
    
    // Boş token'ları filtrele
    $device_tokens = array_filter($device_tokens);
    if (empty($device_tokens)) {
        writeLog("No valid device tokens provided", 'WARNING');
        return false;
    }
    
    // Firebase Cloud Messaging API v1 kullan
    $access_token = getFirebaseAccessToken();
    if (!$access_token) {
        writeLog("Failed to get Firebase access token", 'ERROR');
        return false;
    }
    
    $success_count = 0;
    $errors = [];
    
    // Data alanındaki tüm değerleri string'e dönüştür (FCM v1 gereksinimi)
    $string_data = [];
    foreach ($data as $key => $value) {
        $string_data[$key] = (string)$value;
    }
    
    // Her token için ayrı mesaj gönder (API v1 gereksinimi)
    foreach ($device_tokens as $token) {
        $message_data = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $message
                ],
                'data' => $string_data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'default'
                    ]
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10'
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1
                        ]
                    ]
                ]
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/messages:send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message_data));
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200) {
            $success_count++;
        } else {
            $errors[] = "Token: " . substr($token, 0, 20) . "... HTTP: $http_code Response: $result";
        }
        
        writeLog("FCM v1 notification sent to token " . substr($token, 0, 20) . "... HTTP: $http_code", 'INFO', 'notifications.log');
    }
    
    $total_tokens = count($device_tokens);
    writeLog("FCM v1 notification summary: $success_count/$total_tokens successful", 'INFO', 'notifications.log');
    
    if (!empty($errors)) {
        writeLog("FCM v1 errors: " . implode('; ', $errors), 'WARNING', 'notifications.log');
    }
    
    return $success_count > 0;
}

// Firebase Access Token al (JWT ile)
function getFirebaseAccessToken() {
    $private_key = FIREBASE_PRIVATE_KEY;
    $client_email = FIREBASE_CLIENT_EMAIL;
    
    if (empty($private_key) || empty($client_email)) {
        writeLog("Firebase credentials not configured", 'ERROR');
        return false;
    }
    
    // JWT header
    $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
    
    // JWT payload
    $now = time();
    $payload = json_encode([
        'iss' => $client_email,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600, // 1 saat
        'iat' => $now
    ]);
    
    // Base64 encode
    $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    // Signature
    $signature = '';
    $signing_input = $base64_header . '.' . $base64_payload;
    
    if (!openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
        writeLog("Failed to sign JWT", 'ERROR');
        return false;
    }
    
    $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $signing_input . '.' . $base64_signature;
    
    // Access token al
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response = json_decode($result, true);
        if (isset($response['access_token'])) {
            return $response['access_token'];
        }
    }
    
    writeLog("Failed to get access token. HTTP: $http_code Response: $result", 'ERROR');
    return false;
}

// SMS gönder
function sendSMS($phone, $message) {
    if (empty(SMS_API_KEY)) {
        writeLog("SMS API Key not configured", 'WARNING');
        return false;
    }
    
    // SMS API entegrasyonu burada yapılacak
    // Örnek: Turkcell, Vodafone, Netgsm vb.
    
    writeLog("SMS sent to {$phone}: {$message}", 'INFO', 'sms.log');
    return true;
}

// Email gönder
function sendEmail($to, $subject, $message, $is_html = true) {
    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
        'Reply-To: ' . MAIL_FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    if ($is_html) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    $result = mail($to, $subject, $message, implode("\r\n", $headers));
    
    writeLog("Email sent to {$to}: {$subject} - Result: " . ($result ? 'Success' : 'Failed'), 'INFO', 'email.log');
    
    return $result;
}

// Sistem ayarı al
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM sistem_ayarlari");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            writeLog("Error loading settings: " . $e->getMessage(), 'ERROR');
            $settings = [];
        }
    }
    
    return $settings[$key] ?? $default;
}

// Sistem ayarı kaydet
function setSetting($key, $value, $type = 'string', $description = '') {
    try {
        $db = getDB();
        $stmt = $db->query(
            "INSERT INTO sistem_ayarlari (setting_key, setting_value, setting_type, description) 
             VALUES (?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?, description = ?",
            [$key, $value, $type, $description, $value, $type, $description]
        );
        return true;
    } catch (Exception $e) {
        writeLog("Error saving setting {$key}: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Kullanıcı bilgilerini al
function getUserInfo($user_id) {
    static $cache = [];
    
    if (isset($cache[$user_id])) {
        return $cache[$user_id];
    }
    
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM users WHERE id = ?", [$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            unset($user['password']); // Şifreyi kaldır
            $cache[$user_id] = $user;
        }
        
        return $user;
    } catch (Exception $e) {
        writeLog("Error getting user info for ID {$user_id}: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// API rate limiting
function checkRateLimit($user_id, $endpoint) {
    try {
        $db = getDB();
        $since = date('Y-m-d H:i:s', strtotime('-1 minute'));
        
        $stmt = $db->query(
            "SELECT COUNT(*) as count FROM api_logs 
             WHERE user_id = ? AND endpoint = ? AND created_at > ?",
            [$user_id, $endpoint, $since]
        );
        
        $count = $stmt->fetch()['count'];
        
        return $count < API_RATE_LIMIT;
    } catch (Exception $e) {
        writeLog("Error checking rate limit: " . $e->getMessage(), 'ERROR');
        return true; // Hata durumunda geçiş ver
    }
}

// API log kaydet
function logApiCall($user_id, $endpoint, $method, $request_data, $response_data, $status_code, $response_time) {
    try {
        $db = getDB();
        $db->query(
            "INSERT INTO api_logs (user_id, endpoint, method, request_data, response_data, status_code, response_time, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $user_id,
                $endpoint,
                $method,
                json_encode($request_data),
                json_encode($response_data),
                $status_code,
                $response_time,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]
        );
    } catch (Exception $e) {
        writeLog("Error logging API call: " . $e->getMessage(), 'ERROR');
    }
}

// Mekan ID'sini al
function getMekanId() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id FROM mekanlar WHERE user_id = ?", [getUserId()]);
        $result = $stmt->fetch();
        return $result['id'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}

// Kurye ID'sini al
function getKuryeId() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id FROM kuryeler WHERE user_id = ?", [getUserId()]);
        $result = $stmt->fetch();
        return $result['id'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}

// Kurye olup olmadığını kontrol et
function isKurye() {
    return getUserType() === 'kurye';
}

?>
