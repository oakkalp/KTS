<?php
/**
 * Mekan Sidebar Component
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" style="min-height: 100vh; background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
    <div class="position-sticky pt-3">
        <div class="text-center text-white mb-4">
            <h4><i class="fas fa-store me-2"></i><?= APP_NAME ?></h4>
            <small>Mekan Panel</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'siparisler.php' ? 'active' : '' ?>" href="siparisler.php">
                    <i class="fas fa-shopping-bag me-2"></i>
                    Siparişler
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'yeni-siparis.php' ? 'active' : '' ?>" href="yeni-siparis.php">
                    <i class="fas fa-plus me-2"></i>
                    Yeni Sipariş
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'raporlar.php' ? 'active' : '' ?>" href="raporlar.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Raporlar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'getir-siparisler.php' ? 'active' : '' ?>" href="getir-siparisler.php">
                    <i class="fas fa-store me-2"></i>
                    GetirYemek
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'profil.php' ? 'active' : '' ?>" href="profil.php">
                    <i class="fas fa-user me-2"></i>
                    Profil
                </a>
            </li>
        </ul>
        
        <hr class="text-white-50">
        
        <div class="text-white-50 small px-3">
            <?php
            // Mekan bilgilerini al
            try {
                $db = getDB();
                $stmt = $db->query("SELECT mekan_name FROM mekanlar WHERE user_id = ?", [getUserId()]);
                $mekan = $stmt->fetch();
                $mekan_name = $mekan['mekan_name'] ?? 'Mekan';
            } catch (Exception $e) {
                $mekan_name = 'Mekan';
            }
            ?>
            <div class="mb-2">
                <i class="fas fa-store me-2"></i>
                <?= sanitize($mekan_name) ?>
            </div>
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
