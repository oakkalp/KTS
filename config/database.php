<?php
/**
 * Kurye Full System - Database Configuration
 * MySQL bağlantı ayarları
 */

// Veritabanı ayarları
define('DB_HOST', 'localhost'); // Yerel geliştirme için localhost, uzak erişim için 192.168.1.137
define('DB_NAME', 'kurye_system');
define('DB_USER', 'root');
define('DB_PASS', ''); // Boş şifre
define('DB_CHARSET', 'utf8mb4');

// Uzak erişim ayarları (production için)
define('REMOTE_DB_HOST', '192.168.1.137');
define('REMOTE_ACCESS_URL', 'http://192.168.1.137/kuryefullsistem/');

// Ortam tespiti (local mı remote mi)
$is_remote = (
    isset($_SERVER['HTTP_HOST']) && 
    (strpos($_SERVER['HTTP_HOST'], '192.168.1.137') !== false)
);

// Ortama göre host seçimi - uzak erişimde de localhost kullan
$db_host = DB_HOST; // Her zaman localhost kullan

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            global $db_host;
            
            $dsn = "mysql:host=" . $db_host . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Veritabanı bağlantı hatası. Lütfen sistem yöneticisi ile iletişime geçin.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Güvenli sorgu çalıştırma
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . print_r($params, true));
            throw new Exception("Veritabanı sorgu hatası: " . $e->getMessage());
        }
    }
    
    // Son eklenen ID'yi al
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Transaction başlat
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    // Transaction commit
    public function commit() {
        return $this->connection->commit();
    }
    
    // Transaction rollback
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    // Transaction durumunu kontrol et
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
}

// Kolay erişim için global fonksiyon
function getDB() {
    return Database::getInstance();
}

// Bağlantıyı test et
try {
    $db = getDB();
    // Test sorgusu
    $test = $db->query("SELECT 1 as test")->fetch();
    if ($test['test'] !== 1) {
        throw new Exception("Database test failed");
    }
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
}
?>
