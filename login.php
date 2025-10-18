<?php
/**
 * Kurye Full System - Giriş Sayfası
 * Tüm kullanıcı tiplerinin giriş yaptığı sayfa
 */

require_once 'config/config.php';

// Zaten giriş yapmışsa yönlendir
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error_message = '';
$success_message = '';

// Form gönderildiğinde
if ($_POST) {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // CSRF token kontrolü
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Güvenlik hatası. Lütfen sayfayı yenileyin.';
    }
    elseif (empty($username) || empty($password)) {
        $error_message = 'Kullanıcı adı ve şifre gereklidir.';
    }
    else {
        try {
            $db = getDB();
            
            // Kullanıcıyı bul
            $stmt = $db->query(
                "SELECT u.*, 
                        CASE 
                            WHEN u.user_type = 'mekan' THEN m.mekan_name
                            WHEN u.user_type = 'kurye' THEN k.license_plate
                            ELSE NULL 
                        END as additional_info
                 FROM users u 
                 LEFT JOIN mekanlar m ON u.id = m.user_id 
                 LEFT JOIN kuryeler k ON u.id = k.user_id 
                 WHERE u.username = ? AND u.status = 'active'", 
                [$username]
            );
            
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Giriş başarılı
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['additional_info'] = $user['additional_info'];
                
                // Son giriş zamanını güncelle
                $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                
                // Beni hatırla özelliği
                if ($remember_me) {
                    $token = generateRandomString(32);
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 gün
                    // Token'ı veritabanında sakla (güvenlik için)
                }
                
                // Log kaydet
                writeLog("User login: {$username} ({$user['user_type']})", 'INFO', 'auth.log');
                
                // Kullanıcı tipine göre yönlendir
                switch ($user['user_type']) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'mekan':
                        header('Location: mekan/dashboard.php');
                        break;
                    case 'kurye':
                        header('Location: kurye/dashboard.php');
                        break;
                }
                exit;
                
            } else {
                $error_message = 'Kullanıcı adı veya şifre hatalı.';
                writeLog("Failed login attempt: {$username}", 'WARNING', 'auth.log');
            }
            
        } catch (Exception $e) {
            writeLog("Login error: " . $e->getMessage(), 'ERROR');
            $error_message = 'Giriş sırasında bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Giriş</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .user-type-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .user-type-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
        }
        .demo-credentials {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 0 10px 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="login-container">
                    <!-- Header -->
                    <div class="login-header text-center py-4">
                        <h2 class="mb-0">
                            <i class="fas fa-motorcycle me-2"></i>
                            <?= APP_NAME ?>
                        </h2>
                        <p class="mb-0 opacity-75">Sisteme Giriş Yapın</p>
                    </div>
                    
                    <div class="row g-0">
                        <!-- Login Form -->
                        <div class="col-lg-6 p-5">
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= sanitize($error_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success_message): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= sanitize($success_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <div class="mb-4">
                                    <label for="username" class="form-label fw-semibold">
                                        <i class="fas fa-user me-2 text-primary"></i>
                                        Kullanıcı Adı
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           value="<?= sanitize($_POST['username'] ?? '') ?>"
                                           required 
                                           autofocus>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-2 text-primary"></i>
                                        Şifre
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               required>
                                        <button type="button" 
                                                class="btn btn-sm position-absolute end-0 top-50 translate-middle-y me-2" 
                                                onclick="togglePassword()">
                                            <i class="fas fa-eye" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               id="remember_me" 
                                               name="remember_me">
                                        <label class="form-check-label" for="remember_me">
                                            Beni Hatırla (30 gün)
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-login btn-primary w-100 fw-semibold">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Giriş Yap
                                </button>
                            </form>
                            
                            <div class="text-center mt-4">
                                <a href="index.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Ana Sayfaya Dön
                                </a>
                            </div>
                        </div>
                        
                        <!-- User Types & Demo Info -->
                        <div class="col-lg-6 p-5 bg-light">
                            <h5 class="mb-4 text-center">Kullanıcı Tipleri</h5>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <div class="card user-type-card border-0 shadow-sm">
                                        <div class="card-body text-center py-3">
                                            <i class="fas fa-user-shield fa-2x text-primary mb-2"></i>
                                            <h6 class="mb-1">Admin</h6>
                                            <small class="text-muted">Sistem Yöneticisi</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="card user-type-card border-0 shadow-sm">
                                        <div class="card-body text-center py-3">
                                            <i class="fas fa-store fa-2x text-success mb-2"></i>
                                            <h6 class="mb-1">Mekan</h6>
                                            <small class="text-muted">Restoran / Mağaza</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="card user-type-card border-0 shadow-sm">
                                        <div class="card-body text-center py-3">
                                            <i class="fas fa-motorcycle fa-2x text-warning mb-2"></i>
                                            <h6 class="mb-1">Kurye</h6>
                                            <small class="text-muted">Teslimat Görevlisi</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Demo Credentials -->
                            <div class="demo-credentials p-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Test Hesapları
                                </h6>
                                
                                <div class="mb-3">
                                    <strong class="text-primary">Admin:</strong>
                                    <br><small>Kullanıcı: admin</small>
                                    <br><small>Şifre: password</small>
                                </div>
                                
                                <div class="mb-3">
                                    <strong class="text-success">Mekan:</strong>
                                    <br><small>Kullanıcı: test_mekan</small>
                                    <br><small>Şifre: password</small>
                                </div>
                                
                                <div class="mb-0">
                                    <strong class="text-warning">Kurye:</strong>
                                    <br><small>Kullanıcı: test_kurye</small>
                                    <br><small>Şifre: password</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Auto-fill demo credentials
        document.querySelectorAll('.user-type-card').forEach(card => {
            card.addEventListener('click', function() {
                const type = this.querySelector('h6').textContent.toLowerCase();
                const usernameInput = document.getElementById('username');
                const passwordInput = document.getElementById('password');
                
                switch(type) {
                    case 'admin':
                        usernameInput.value = 'admin';
                        passwordInput.value = 'password';
                        break;
                    case 'mekan':
                        usernameInput.value = 'test_mekan';
                        passwordInput.value = 'password';
                        break;
                    case 'kurye':
                        usernameInput.value = 'test_kurye';
                        passwordInput.value = 'password';
                        break;
                }
                
                // Focus password input
                passwordInput.focus();
            });
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Lütfen kullanıcı adı ve şifrenizi girin.');
                return false;
            }
        });
    </script>
</body>
</html>
