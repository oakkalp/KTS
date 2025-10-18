<?php
/**
 * Kurye Full System - Ana Sayfa
 * Kullanıcı tipine göre yönlendirme yapar
 */

require_once 'config/config.php';

// Eğer kullanıcı giriş yapmışsa, tipine göre yönlendir
if (isLoggedIn()) {
    $user_type = getUserType();
    switch ($user_type) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'mekan':
            header('Location: mekan/dashboard.php');
            break;
        case 'kurye':
            header('Location: kurye/dashboard.php');
            break;
        default:
            session_destroy();
            header('Location: login.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Ana Sayfa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .feature-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .login-btn {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            backdrop-filter: blur(10px);
        }
        .login-btn:hover {
            background: white;
            color: #667eea;
        }
        .stats-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: rgba(0,0,0,0.1); position: absolute; width: 100%; z-index: 1000;">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-motorcycle me-2"></i>
                <?= APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="login.php">
                    <i class="fas fa-sign-in-alt me-1"></i>
                    Giriş Yap
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">
                        Hızlı, Güvenli, Profesyonel
                        <span class="d-block text-warning">Kurye Takip Sistemi</span>
                    </h1>
                    <p class="lead mb-4">
                        Yemek Sepeti ve Getir benzeri profesyonel kurye takip sistemi. 
                        Gerçek zamanlı konum takibi, otomatik sipariş yönetimi ve mobil uygulama desteği.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="login.php" class="btn login-btn btn-lg px-4 py-3">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Sisteme Giriş
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4 py-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Özellikler
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="card stats-card text-center p-3">
                                <h3 class="mb-1"><i class="fas fa-motorcycle text-warning"></i></h3>
                                <h4 class="mb-0">Kurye</h4>
                                <p class="mb-0 small">Takip Sistemi</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card stats-card text-center p-3">
                                <h3 class="mb-1"><i class="fas fa-store text-success"></i></h3>
                                <h4 class="mb-0">Mekan</h4>
                                <p class="mb-0 small">Yönetim Paneli</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card stats-card text-center p-3">
                                <h3 class="mb-1"><i class="fas fa-mobile-alt text-info"></i></h3>
                                <h4 class="mb-0">Mobil</h4>
                                <p class="mb-0 small">Uygulama</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card stats-card text-center p-3">
                                <h3 class="mb-1"><i class="fas fa-chart-line text-danger"></i></h3>
                                <h4 class="mb-0">Analiz</h4>
                                <p class="mb-0 small">Raporlama</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <h2 class="display-5 fw-bold mb-3">Sistem Özellikleri</h2>
                    <p class="lead text-muted">Modern teknoloji ile geliştirilmiş profesyonel çözümler</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="card-body">
                            <div class="bg-primary bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-map-marked-alt fa-2x text-white"></i>
                            </div>
                            <h5 class="card-title">Gerçek Zamanlı Takip</h5>
                            <p class="card-text">Kuryelerinizin konumunu gerçek zamanlı olarak takip edin. GPS tabanlı hassas konum bilgisi.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="card-body">
                            <div class="bg-success bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-mobile-alt fa-2x text-white"></i>
                            </div>
                            <h5 class="card-title">Mobil Uygulama</h5>
                            <p class="card-text">Android ve iOS için optimize edilmiş kurye uygulaması. Push notification desteği.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="card-body">
                            <div class="bg-warning bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-bell fa-2x text-white"></i>
                            </div>
                            <h5 class="card-title">Akıllı Bildirimler</h5>
                            <p class="card-text">SMS, email ve push notification ile anlık bildirim sistemi. Otomatik durum güncellemeleri.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="card-body">
                            <div class="bg-info bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-chart-pie fa-2x text-white"></i>
                            </div>
                            <h5 class="card-title">Detaylı Raporlama</h5>
                            <p class="card-text">Kurye performansı, sipariş analizi ve gelir raporları. Grafikli dashboard arayüzü.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="card-body">
                            <div class="bg-danger bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-shield-alt fa-2x text-white"></i>
                            </div>
                            <h5 class="card-title">Güvenli Sistem</h5>
                            <p class="card-text">SSL şifrelemesi, güvenli API endpoints ve kullanıcı yetkilendirme sistemi.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="card-body">
                            <div class="bg-secondary bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-cogs fa-2x text-white"></i>
                            </div>
                            <h5 class="card-title">Kolay Yönetim</h5>
                            <p class="card-text">Kullanıcı dostu admin paneli. Mekan ve kurye yönetimi tek tıkla.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- User Types Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <h2 class="display-5 fw-bold mb-3">Kullanıcı Tipleri</h2>
                    <p class="lead text-muted">Farklı kullanıcı grupları için özel tasarlanmış paneller</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-user-shield fa-3x text-white"></i>
                            </div>
                            <h4 class="card-title text-primary">Admin Panel</h4>
                            <p class="card-text">Sistem yöneticileri için kapsamlı yönetim paneli. Tüm kullanıcıları, siparişleri ve sistem ayarlarını yönetin.</p>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success me-2"></i>Kullanıcı yönetimi</li>
                                <li><i class="fas fa-check text-success me-2"></i>Sistem ayarları</li>
                                <li><i class="fas fa-check text-success me-2"></i>Detaylı raporlar</li>
                                <li><i class="fas fa-check text-success me-2"></i>Analitik dashboard</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-store fa-3x text-white"></i>
                            </div>
                            <h4 class="card-title text-success">Mekan Paneli</h4>
                            <p class="card-text">Restoran ve mağaza sahipleri için sipariş yönetim sistemi. Siparişlerinizi takip edin ve kuryelerinizi yönetin.</p>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success me-2"></i>Sipariş yönetimi</li>
                                <li><i class="fas fa-check text-success me-2"></i>Kurye atama</li>
                                <li><i class="fas fa-check text-success me-2"></i>Durum takibi</li>
                                <li><i class="fas fa-check text-success me-2"></i>Gelir raporları</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-warning bg-gradient rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-motorcycle fa-3x text-white"></i>
                            </div>
                            <h4 class="card-title text-warning">Kurye Paneli</h4>
                            <p class="card-text">Kuryeler için özel tasarlanmış dashboard ve mobil uygulama. Siparişlerinizi alın ve teslimat yapın.</p>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success me-2"></i>Sipariş listesi</li>
                                <li><i class="fas fa-check text-success me-2"></i>GPS navigasyon</li>
                                <li><i class="fas fa-check text-success me-2"></i>Durum güncelleme</li>
                                <li><i class="fas fa-check text-success me-2"></i>Kazanç takibi</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-motorcycle me-2"></i><?= APP_NAME ?></h5>
                    <p class="mb-0">Modern kurye takip ve yönetim sistemi</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Versiyon: <?= APP_VERSION ?></p>
                    <p class="mb-0">&copy; 2024 Tüm hakları saklıdır.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
