<?php
/**
 * Kurye Full System - Sistem Test Scripti
 * Kurulumun doğru çalışıp çalışmadığını kontrol eder
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🚀 Kurye Full System - Sistem Testi</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
.info { color: #17a2b8; font-weight: bold; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background-color: #f2f2f2; }
.status-ok { background-color: #d4edda; }
.status-error { background-color: #f8d7da; }
.status-warning { background-color: #fff3cd; }
</style>";

$tests = [];
$overall_status = true;

// Test 1: PHP Version
$php_version = phpversion();
$php_ok = version_compare($php_version, '7.4', '>=');
$tests[] = [
    'test' => 'PHP Version',
    'expected' => '7.4+',
    'actual' => $php_version,
    'status' => $php_ok,
    'message' => $php_ok ? 'OK' : 'PHP 7.4 veya üzeri gerekli'
];
if (!$php_ok) $overall_status = false;

// Test 2: Required PHP Extensions
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $tests[] = [
        'test' => "PHP Extension: $ext",
        'expected' => 'Yüklü',
        'actual' => $loaded ? 'Yüklü' : 'Yüklü değil',
        'status' => $loaded,
        'message' => $loaded ? 'OK' : 'Gerekli extension yüklü değil'
    ];
    if (!$loaded) $overall_status = false;
}

// Test 3: Config Files
$config_files = [
    'config/config.php' => 'Ana konfigürasyon',
    'config/database.php' => 'Veritabanı konfigürasyonu',
    'includes/functions.php' => 'Yardımcı fonksiyonlar'
];

foreach ($config_files as $file => $desc) {
    $exists = file_exists($file);
    $tests[] = [
        'test' => "Config File: $desc",
        'expected' => 'Mevcut',
        'actual' => $exists ? 'Mevcut' : 'Eksik',
        'status' => $exists,
        'message' => $exists ? 'OK' : 'Dosya bulunamadı'
    ];
    if (!$exists) $overall_status = false;
}

// Test 4: Database Connection
try {
    require_once 'config/config.php';
    $db = getDB();
    $db_ok = true;
    $db_message = 'Bağlantı başarılı';
} catch (Exception $e) {
    $db_ok = false;
    $db_message = $e->getMessage();
    $overall_status = false;
}

$tests[] = [
    'test' => 'MySQL Bağlantısı',
    'expected' => 'Başarılı',
    'actual' => $db_ok ? 'Başarılı' : 'Başarısız',
    'status' => $db_ok,
    'message' => $db_message
];

// Test 5: Database Tables
if ($db_ok) {
    $required_tables = ['users', 'mekanlar', 'kuryeler', 'siparisler', 'sistem_ayarlari', 'api_logs'];
    
    foreach ($required_tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetch()['COUNT(*)'];
            $table_ok = true;
            $table_message = "OK ($count kayıt)";
        } catch (Exception $e) {
            $table_ok = false;
            $table_message = 'Tablo bulunamadı';
            $overall_status = false;
        }
        
        $tests[] = [
            'test' => "Tablo: $table",
            'expected' => 'Mevcut',
            'actual' => $table_ok ? 'Mevcut' : 'Eksik',
            'status' => $table_ok,
            'message' => $table_message
        ];
    }
}

// Test 6: Directory Permissions
$directories = [
    'logs/' => 'Log dosyaları',
    'uploads/' => 'Yüklenen dosyalar'
];

foreach ($directories as $dir => $desc) {
    $exists = is_dir($dir);
    $writable = $exists && is_writable($dir);
    
    $tests[] = [
        'test' => "Dizin: $desc",
        'expected' => 'Yazılabilir',
        'actual' => $exists ? ($writable ? 'Yazılabilir' : 'Salt okunur') : 'Mevcut değil',
        'status' => $writable,
        'message' => $writable ? 'OK' : ($exists ? 'Yazma izni yok' : 'Dizin mevcut değil')
    ];
    
    if (!$writable && $exists) {
        // Dizin izinlerini düzeltmeye çalış
        @chmod($dir, 0755);
    }
}

// Test 7: API Endpoints
if ($db_ok) {
    $api_endpoints = [
        'api/auth/login.php' => 'Login API',
        'api/kurye/update-location.php' => 'Konum güncelleme API',
        'api/kurye/toggle-status.php' => 'Durum değiştirme API',
        'api/notification/update-token.php' => 'Token güncelleme API'
    ];
    
    foreach ($api_endpoints as $endpoint => $desc) {
        $exists = file_exists($endpoint);
        $tests[] = [
            'test' => "API: $desc",
            'expected' => 'Mevcut',
            'actual' => $exists ? 'Mevcut' : 'Eksik',
            'status' => $exists,
            'message' => $exists ? 'OK' : 'Dosya bulunamadı'
        ];
        if (!$exists) $overall_status = false;
    }
}

// Test 8: Panel Files
$panel_files = [
    'index.php' => 'Ana sayfa',
    'login.php' => 'Login sayfası',
    'admin/dashboard.php' => 'Admin paneli',
    'mekan/dashboard.php' => 'Mekan paneli',
    'kurye/dashboard.php' => 'Kurye paneli'
];

foreach ($panel_files as $file => $desc) {
    $exists = file_exists($file);
    $tests[] = [
        'test' => "Panel: $desc",
        'expected' => 'Mevcut',
        'actual' => $exists ? 'Mevcut' : 'Eksik',
        'status' => $exists,
        'message' => $exists ? 'OK' : 'Dosya bulunamadı'
    ];
    if (!$exists) $overall_status = false;
}

// Test Results Table
echo "<h2>📋 Test Sonuçları</h2>";
echo "<table>";
echo "<tr><th>Test</th><th>Beklenen</th><th>Gerçek</th><th>Durum</th><th>Mesaj</th></tr>";

foreach ($tests as $test) {
    $status_class = $test['status'] ? 'status-ok' : 'status-error';
    $status_text = $test['status'] ? '✅ Başarılı' : '❌ Başarısız';
    
    echo "<tr class='$status_class'>";
    echo "<td><strong>{$test['test']}</strong></td>";
    echo "<td>{$test['expected']}</td>";
    echo "<td>{$test['actual']}</td>";
    echo "<td>$status_text</td>";
    echo "<td>{$test['message']}</td>";
    echo "</tr>";
}

echo "</table>";

// Overall Status
echo "<h2>🎯 Genel Durum</h2>";
if ($overall_status) {
    echo "<div class='success'>✅ Tüm testler başarılı! Sistem kullanıma hazır.</div>";
    
    echo "<h3>🔗 Erişim Linkleri</h3>";
    echo "<ul>";
    echo "<li><a href='index.php' target='_blank'>Ana Sayfa</a></li>";
    echo "<li><a href='login.php' target='_blank'>Giriş Sayfası</a></li>";
    echo "<li><a href='admin/dashboard.php' target='_blank'>Admin Paneli</a></li>";
    echo "<li><a href='mekan/dashboard.php' target='_blank'>Mekan Paneli</a></li>";
    echo "<li><a href='kurye/dashboard.php' target='_blank'>Kurye Paneli</a></li>";
    echo "<li><a href='api/' target='_blank'>API Dokümantasyonu</a></li>";
    echo "</ul>";
    
    echo "<h3>🔑 Test Hesapları</h3>";
    echo "<table>";
    echo "<tr><th>Kullanıcı Tipi</th><th>Kullanıcı Adı</th><th>Şifre</th><th>Panel</th></tr>";
    echo "<tr><td>Admin</td><td>admin</td><td>password</td><td><a href='admin/dashboard.php'>Admin Panel</a></td></tr>";
    echo "<tr><td>Test Mekan</td><td>test_mekan</td><td>password</td><td><a href='mekan/dashboard.php'>Mekan Panel</a></td></tr>";
    echo "<tr><td>Test Kurye</td><td>test_kurye</td><td>password</td><td><a href='kurye/dashboard.php'>Kurye Panel</a></td></tr>";
    echo "</table>";
    
} else {
    echo "<div class='error'>❌ Bazı testler başarısız! Lütfen hataları düzeltin.</div>";
    
    echo "<h3>🔧 Sorun Giderme</h3>";
    echo "<ul>";
    echo "<li><strong>MySQL Bağlantı Hatası:</strong> XAMPP MySQL servisinin çalıştığından emin olun</li>";
    echo "<li><strong>Tablo Bulunamadı:</strong> <code>php simple_install.php</code> komutunu çalıştırın</li>";
    echo "<li><strong>Dosya Bulunamadı:</strong> Tüm dosyaların doğru yerde olduğundan emin olun</li>";
    echo "<li><strong>İzin Hatası:</strong> Dizin izinlerini kontrol edin (chmod 755)</li>";
    echo "</ul>";
}

// System Information
echo "<h2>ℹ️ Sistem Bilgileri</h2>";
echo "<table>";
echo "<tr><th>Özellik</th><th>Değer</th></tr>";
echo "<tr><td>PHP Version</td><td>" . phpversion() . "</td></tr>";
echo "<tr><td>Server Software</td><td>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Bilinmiyor') . "</td></tr>";
echo "<tr><td>Document Root</td><td>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'Bilinmiyor') . "</td></tr>";
echo "<tr><td>Current Directory</td><td>" . __DIR__ . "</td></tr>";
echo "<tr><td>Server Time</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
echo "<tr><td>Memory Limit</td><td>" . ini_get('memory_limit') . "</td></tr>";
echo "<tr><td>Max Execution Time</td><td>" . ini_get('max_execution_time') . " saniye</td></tr>";
echo "<tr><td>Upload Max Filesize</td><td>" . ini_get('upload_max_filesize') . "</td></tr>";
echo "</table>";

echo "<hr>";
echo "<p><small>Test tamamlandı: " . date('Y-m-d H:i:s') . "</small></p>";
?>
