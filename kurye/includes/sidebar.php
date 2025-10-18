<?php
/**
 * Kurye Sidebar Component
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" style="min-height: 100vh; background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
    <div class="position-sticky pt-3">
        <div class="text-center text-white mb-4">
            <h4><i class="fas fa-motorcycle me-2"></i><?= APP_NAME ?></h4>
            <small>Kurye Panel</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'siparislerim.php' ? 'active' : '' ?>" href="siparislerim.php">
                    <i class="fas fa-tasks me-2"></i>
                    Aktif Siparişler
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'yeni-siparisler.php' ? 'active' : '' ?>" href="yeni-siparisler.php">
                    <i class="fas fa-bell me-2"></i>
                    Yeni Siparişler
                    <?php
                    // Yeni sipariş sayısını göster
                    try {
                        $db = getDB();
                        $stmt = $db->query("SELECT COUNT(*) as count FROM siparisler WHERE status = 'pending' AND kurye_id IS NULL");
                        $new_count = $stmt->fetch()['count'];
                        if ($new_count > 0) {
                            echo "<span class='badge bg-danger ms-2'>{$new_count}</span>";
                        }
                    } catch (Exception $e) {
                        // Hata varsa badge gösterme
                    }
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'gecmis.php' ? 'active' : '' ?>" href="gecmis.php">
                    <i class="fas fa-history me-2"></i>
                    Teslimat Geçmişi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'kazanclarim.php' ? 'active' : '' ?>" href="kazanclarim.php">
                    <i class="fas fa-coins me-2"></i>
                    Kazançlarım
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
            // Kurye bilgilerini al
            try {
                $db = getDB();
                $stmt = $db->query("SELECT k.*, u.full_name FROM kuryeler k JOIN users u ON k.user_id = u.id WHERE k.user_id = ?", [getUserId()]);
                $kurye = $stmt->fetch();
                $kurye_name = $kurye['full_name'] ?? 'Kurye';
                $is_online = $kurye['is_online'] ?? 0;
                $vehicle_type = $kurye['vehicle_type'] ?? 'motosiklet';
            } catch (Exception $e) {
                $kurye_name = 'Kurye';
                $is_online = 0;
                $vehicle_type = 'motosiklet';
            }
            ?>
            <div class="mb-2">
                <i class="fas fa-user me-2"></i>
                <?= sanitize($kurye_name) ?>
            </div>
            <div class="mb-2">
                <i class="fas fa-motorcycle me-2"></i>
                <?= ucfirst($vehicle_type) ?>
            </div>
            <div class="mb-2">
                <i class="fas fa-circle me-2 <?= $is_online ? 'text-success' : 'text-danger' ?>"></i>
                <?= $is_online ? 'Online' : 'Offline' ?>
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
