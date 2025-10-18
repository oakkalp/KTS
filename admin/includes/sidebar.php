<?php
/**
 * Admin Sidebar Component
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" style="min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="position-sticky pt-3">
        <div class="text-center text-white mb-4">
            <h4><i class="fas fa-motorcycle me-2"></i><?= APP_NAME ?></h4>
            <small>Admin Panel</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="fas fa-users me-2"></i>
                    Kullanıcılar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'mekanlar.php' ? 'active' : '' ?>" href="mekanlar.php">
                    <i class="fas fa-store me-2"></i>
                    Mekanlar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'kuryeler.php' ? 'active' : '' ?>" href="kuryeler.php">
                    <i class="fas fa-motorcycle me-2"></i>
                    Kuryeler
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'siparisler.php' ? 'active' : '' ?>" href="siparisler.php">
                    <i class="fas fa-shopping-bag me-2"></i>
                    Siparişler
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'map-tracking.php' ? 'active' : '' ?>" href="map-tracking.php">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    Canlı Takip
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'getir-yonetim.php' ? 'active' : '' ?>" href="getir-yonetim.php">
                    <i class="fas fa-store me-2"></i>
                    GetirYemek
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'konum-gecmisi.php' ? 'active' : '' ?>" href="konum-gecmisi.php">
                    <i class="fas fa-route me-2"></i>
                    Konum Geçmişi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'raporlar.php' ? 'active' : '' ?>" href="raporlar.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Raporlar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'detayli-rapor.php' ? 'active' : '' ?>" href="detayli-rapor.php">
                    <i class="fas fa-chart-line me-2"></i>
                    Detaylı Rapor
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'mali-raporlar.php' ? 'active' : '' ?>" href="mali-raporlar.php">
                    <i class="fas fa-calculator me-2"></i>
                    Mali Raporlar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'ayarlar.php' ? 'active' : '' ?>" href="ayarlar.php">
                    <i class="fas fa-cog me-2"></i>
                    Sistem Ayarları
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'reset-database.php' ? 'active' : '' ?>" href="reset-database.php">
                    <i class="fas fa-database me-2 text-danger"></i>
                    DB Sıfırla
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'api-docs.php' ? 'active' : '' ?>" href="../api/">
                    <i class="fas fa-code me-2"></i>
                    API Dokümantasyonu
                </a>
            </li>
        </ul>
        
        <hr class="text-white-50">
        
        <div class="text-white-50 small px-3">
            <div class="mb-2">
                <i class="fas fa-user me-2"></i>
                <?= sanitize($_SESSION['full_name']) ?>
            </div>
            <div class="mb-2">
                <i class="fas fa-clock me-2"></i>
                <?= date('d.m.Y H:i') ?>
            </div>
            <a href="../logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-sign-out-alt me-2"></i>
                Çıkış Yap
            </a>
        </div>
    </div>
</nav>

<style>
.sidebar .nav-link {
    color: rgba(255,255,255,0.8);
    border-radius: 10px;
    margin: 5px 0;
    transition: all 0.3s ease;
}
.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    color: white;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
}
</style>
