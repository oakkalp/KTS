<?php
/**
 * Kurye Full System - Mali Raporlar ve Muhasebe İşlemleri
 */

require_once '../config/config.php';
requireUserType('admin');

$db = getDB();

// Tarih filtresi
$filter_type = $_GET['filter_type'] ?? 'monthly';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_week = $_GET['week'] ?? date('Y-\WW');
$start_date_custom = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date_custom = $_GET['end_date'] ?? date('Y-m-d');

// Mekan ve kurye filtreleri
$selected_mekan = $_GET['mekan_id'] ?? '';
$selected_kurye = $_GET['kurye_id'] ?? '';
$entity_filter = $_GET['entity_filter'] ?? 'all'; // all, mekan, kurye

// Tarih aralığını belirle
switch ($filter_type) {
    case 'daily':
        $start_date = $selected_date;
        $end_date = $selected_date;
        break;
    case 'weekly':
        $year = substr($selected_week, 0, 4);
        $week = substr($selected_week, -2);
        $start_date = date('Y-m-d', strtotime($year . 'W' . $week));
        $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
        break;
    case 'custom':
        $start_date = $start_date_custom;
        $end_date = $end_date_custom;
        break;
    case 'monthly':
    default:
        $start_date = $selected_month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        break;
}

// Komisyon oranı
$commission_rate = (float)getSetting('commission_rate', 15.00);

// Mekan ve kurye listelerini çek
$mekanlar = $db->query("SELECT m.id, m.mekan_name FROM mekanlar m JOIN users u ON m.user_id = u.id ORDER BY m.mekan_name")->fetchAll();
$kuryeler = $db->query("SELECT k.id, u.full_name FROM kuryeler k JOIN users u ON k.user_id = u.id ORDER BY u.full_name")->fetchAll();

// Kurye ödemeleri
$kurye_params = [$start_date, $end_date];
$kurye_where = "o.user_type = 'kurye' AND o.odeme_tipi = 'odeme' AND DATE(o.created_at) BETWEEN ? AND ?";

if ($selected_kurye) {
    $kurye_where .= " AND k.id = ?";
    $kurye_params[] = (int)$selected_kurye;
}

$kurye_odemeler = $db->query("
    SELECT 
        o.*,
        u.full_name,
        u.username,
        b.bakiye as mevcut_bakiye
    FROM odemeler o
    JOIN users u ON o.user_id = u.id
    JOIN kuryeler k ON u.id = k.user_id
    LEFT JOIN bakiye b ON o.user_id = b.user_id AND b.user_type = 'kurye'
    WHERE {$kurye_where}
    ORDER BY o.created_at DESC
", $kurye_params)->fetchAll();

// Mekan tahsilatları
$mekan_params = [$start_date, $end_date];
$mekan_where = "o.user_type = 'mekan' AND o.odeme_tipi = 'tahsilat' AND DATE(o.created_at) BETWEEN ? AND ?";

if ($selected_mekan) {
    $mekan_where .= " AND m.id = ?";
    $mekan_params[] = (int)$selected_mekan;
}

$mekan_tahsilatlar = $db->query("
    SELECT 
        o.*,
        m.mekan_name,
        u.username,
        b.bakiye as mevcut_bakiye
    FROM odemeler o
    JOIN users u ON o.user_id = u.id
    JOIN mekanlar m ON u.id = m.user_id
    LEFT JOIN bakiye b ON o.user_id = b.user_id AND b.user_type = 'mekan'
    WHERE {$mekan_where}
    ORDER BY o.created_at DESC
", $mekan_params)->fetchAll();

// Genel bakiye durumu
$kurye_bakiye_where = "b.user_type = 'kurye' AND b.bakiye != 0";
$kurye_bakiye_params = [];

if ($selected_kurye) {
    $kurye_bakiye_where .= " AND k.id = ?";
    $kurye_bakiye_params[] = (int)$selected_kurye;
}

$kurye_bakiyeler = $db->query("
    SELECT 
        b.*,
        u.full_name,
        u.username
    FROM bakiye b
    JOIN users u ON b.user_id = u.id
    JOIN kuryeler k ON u.id = k.user_id
    WHERE {$kurye_bakiye_where}
    ORDER BY ABS(b.bakiye) DESC
", $kurye_bakiye_params)->fetchAll();

$mekan_bakiye_where = "b.user_type = 'mekan' AND b.bakiye != 0";
$mekan_bakiye_params = [];

if ($selected_mekan) {
    $mekan_bakiye_where .= " AND m.id = ?";
    $mekan_bakiye_params[] = (int)$selected_mekan;
}

$mekan_bakiyeler = $db->query("
    SELECT 
        b.*,
        m.mekan_name,
        u.username
    FROM bakiye b
    JOIN users u ON b.user_id = u.id
    JOIN mekanlar m ON u.id = m.user_id
    WHERE {$mekan_bakiye_where}
    ORDER BY ABS(b.bakiye) DESC
", $mekan_bakiye_params)->fetchAll();

// Özet hesaplamalar
$toplam_kurye_odeme = array_sum(array_column($kurye_odemeler, 'tutar'));
$toplam_mekan_tahsilat = array_sum(array_column($mekan_tahsilatlar, 'tutar'));

// Kasa durumu (Tahsilat - Ödeme)
$kasa_durumu = $toplam_mekan_tahsilat - $toplam_kurye_odeme;

// Bakiye hesaplamaları
$toplam_kurye_borc = 0;
$toplam_kurye_alacak = 0;
foreach ($kurye_bakiyeler as $bakiye) {
    if ($bakiye['bakiye'] > 0) {
        $toplam_kurye_alacak += $bakiye['bakiye'];
    } else {
        $toplam_kurye_borc += abs($bakiye['bakiye']);
    }
}

$toplam_mekan_borc = 0;
$toplam_mekan_alacak = 0;
foreach ($mekan_bakiyeler as $bakiye) {
    if ($bakiye['bakiye'] > 0) {
        $toplam_mekan_borc += $bakiye['bakiye'];
    } else {
        $toplam_mekan_alacak += abs($bakiye['bakiye']);
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mali Raporlar - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-calculator me-2"></i>Mali Raporlar ve Muhasebe</h1>
                </div>

                <!-- Filtreler -->
                <form method="GET" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Rapor Tipi</label>
                            <select name="filter_type" class="form-select" onchange="toggleDateFields(this.value)">
                                <option value="daily" <?= $filter_type === 'daily' ? 'selected' : '' ?>>Günlük</option>
                                <option value="weekly" <?= $filter_type === 'weekly' ? 'selected' : '' ?>>Haftalık</option>
                                <option value="monthly" <?= $filter_type === 'monthly' ? 'selected' : '' ?>>Aylık</option>
                                <option value="custom" <?= $filter_type === 'custom' ? 'selected' : '' ?>>Tarih Aralığı</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="daily-field" style="<?= $filter_type !== 'daily' ? 'display:none' : '' ?>">
                            <label class="form-label">Tarih</label>
                            <input type="date" name="date" class="form-control" value="<?= sanitize($selected_date) ?>">
                        </div>
                        <div class="col-md-2" id="weekly-field" style="<?= $filter_type !== 'weekly' ? 'display:none' : '' ?>">
                            <label class="form-label">Hafta</label>
                            <input type="week" name="week" class="form-control" value="<?= sanitize($selected_week) ?>">
                        </div>
                        <div class="col-md-2" id="monthly-field" style="<?= $filter_type !== 'monthly' ? 'display:none' : '' ?>">
                            <label class="form-label">Ay</label>
                            <input type="month" name="month" class="form-control" value="<?= sanitize($selected_month) ?>">
                        </div>
                        <div class="col-md-2" id="custom-start-field" style="<?= $filter_type !== 'custom' ? 'display:none' : '' ?>">
                            <label class="form-label">Başlangıç</label>
                            <input type="date" name="start_date" class="form-control" value="<?= sanitize($start_date_custom) ?>">
                        </div>
                        <div class="col-md-2" id="custom-end-field" style="<?= $filter_type !== 'custom' ? 'display:none' : '' ?>">
                            <label class="form-label">Bitiş</label>
                            <input type="date" name="end_date" class="form-control" value="<?= sanitize($end_date_custom) ?>">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-2">
                            <label class="form-label">Filtre Türü</label>
                            <select name="entity_filter" class="form-select" onchange="toggleEntityFields(this.value)">
                                <option value="all" <?= $entity_filter === 'all' ? 'selected' : '' ?>>Tümü</option>
                                <option value="mekan" <?= $entity_filter === 'mekan' ? 'selected' : '' ?>>Mekan</option>
                                <option value="kurye" <?= $entity_filter === 'kurye' ? 'selected' : '' ?>>Kurye</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="mekan-field" style="<?= $entity_filter !== 'mekan' ? 'display:none' : '' ?>">
                            <label class="form-label">Mekan Seç</label>
                            <select name="mekan_id" class="form-select">
                                <option value="">Tüm Mekanlar</option>
                                <?php foreach ($mekanlar as $mekan): ?>
                                    <option value="<?= $mekan['id'] ?>" <?= $selected_mekan == $mekan['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($mekan['mekan_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3" id="kurye-field" style="<?= $entity_filter !== 'kurye' ? 'display:none' : '' ?>">
                            <label class="form-label">Kurye Seç</label>
                            <select name="kurye_id" class="form-select">
                                <option value="">Tüm Kuryeler</option>
                                <?php foreach ($kuryeler as $kurye): ?>
                                    <option value="<?= $kurye['id'] ?>" <?= $selected_kurye == $kurye['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($kurye['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Filtrele
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Özet Kartlar -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-arrow-down me-1"></i>Kurye Ödemeleri</h6>
                                <h4><?= formatMoney($toplam_kurye_odeme) ?></h4>
                                <small><?= count($kurye_odemeler) ?> işlem</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-arrow-up me-1"></i>Mekan Tahsilatları</h6>
                                <h4><?= formatMoney($toplam_mekan_tahsilat) ?></h4>
                                <small><?= count($mekan_tahsilatlar) ?> işlem</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-<?= $kasa_durumu >= 0 ? 'info' : 'secondary' ?>">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-cash-register me-1"></i>Kasa Durumu</h6>
                                <h4><?= formatMoney($kasa_durumu) ?></h4>
                                <small><?= $kasa_durumu >= 0 ? 'Pozitif' : 'Negatif' ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-exclamation-triangle me-1"></i>Bekleyen Alacaklar</h6>
                                <h4><?= formatMoney($toplam_mekan_borc) ?></h4>
                                <small>Mekanlardan tahsil edilecek</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-times-circle me-1"></i>Bekleyen Borçlar</h6>
                                <h4><?= formatMoney($toplam_kurye_alacak) ?></h4>
                                <small>Kuryelere ödenecek</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-dark">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-balance-scale me-1"></i>Net Durum</h6>
                                <h4><?= formatMoney($toplam_mekan_borc - $toplam_kurye_alacak) ?></h4>
                                <small>Alacak - Borç</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kurye Ödemeleri -->
                <?php if ($entity_filter === 'all' || $entity_filter === 'kurye'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-motorcycle me-2"></i>Kurye Ödemeleri (<?= count($kurye_odemeler) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($kurye_odemeler)): ?>
                            <p class="text-muted">Bu dönemde kurye ödemesi bulunmuyor.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tarih</th>
                                            <th>Kurye</th>
                                            <th>Tutar</th>
                                            <th>Açıklama</th>
                                            <th>Mevcut Bakiye</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($kurye_odemeler as $odeme): ?>
                                        <tr>
                                            <td><?= formatDate($odeme['created_at']) ?></td>
                                            <td>
                                                <strong><?= sanitize($odeme['full_name']) ?></strong>
                                                <br><small class="text-muted">@<?= sanitize($odeme['username']) ?></small>
                                            </td>
                                            <td><span class="badge bg-success fs-6"><?= formatMoney($odeme['tutar']) ?></span></td>
                                            <td><?= sanitize($odeme['aciklama']) ?></td>
                                            <td>
                                                <?php 
                                                $bakiye = $odeme['mevcut_bakiye'] ?? 0;
                                                if ($bakiye > 0): ?>
                                                    <span class="badge bg-success">Alacaklı: <?= formatMoney($bakiye) ?></span>
                                                <?php elseif ($bakiye < 0): ?>
                                                    <span class="badge bg-danger">Borçlu: <?= formatMoney(abs($bakiye)) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Temiz</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Mekan Tahsilatları -->
                <?php if ($entity_filter === 'all' || $entity_filter === 'mekan'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-store me-2"></i>Mekan Tahsilatları (<?= count($mekan_tahsilatlar) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($mekan_tahsilatlar)): ?>
                            <p class="text-muted">Bu dönemde mekan tahsilatı bulunmuyor.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tarih</th>
                                            <th>Mekan</th>
                                            <th>Tutar</th>
                                            <th>Açıklama</th>
                                            <th>Mevcut Bakiye</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mekan_tahsilatlar as $tahsilat): ?>
                                        <tr>
                                            <td><?= formatDate($tahsilat['created_at']) ?></td>
                                            <td>
                                                <strong><?= sanitize($tahsilat['mekan_name']) ?></strong>
                                                <br><small class="text-muted">@<?= sanitize($tahsilat['username']) ?></small>
                                            </td>
                                            <td><span class="badge bg-primary fs-6"><?= formatMoney($tahsilat['tutar']) ?></span></td>
                                            <td><?= sanitize($tahsilat['aciklama']) ?></td>
                                            <td>
                                                <?php 
                                                $bakiye = $tahsilat['mevcut_bakiye'] ?? 0;
                                                if ($bakiye > 0): ?>
                                                    <span class="badge bg-danger">Borçlu: <?= formatMoney($bakiye) ?></span>
                                                <?php elseif ($bakiye < 0): ?>
                                                    <span class="badge bg-success">Alacaklı: <?= formatMoney(abs($bakiye)) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Temiz</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bekleyen Bakiyeler -->
                <div class="row">
                    <?php if ($entity_filter === 'all' || $entity_filter === 'kurye'): ?>
                    <div class="col-md-<?= $entity_filter === 'all' ? '6' : '12' ?>">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-motorcycle me-2"></i>Kurye Bakiyeleri</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($kurye_bakiyeler)): ?>
                                    <p class="text-muted">Bekleyen kurye bakiyesi yok.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Kurye</th>
                                                    <th>Borç</th>
                                                    <th>Alacak</th>
                                                    <th>Net</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($kurye_bakiyeler as $bakiye): ?>
                                                <?php $net = $bakiye['bakiye']; ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= sanitize($bakiye['full_name']) ?></strong>
                                                        <br><small>@<?= sanitize($bakiye['username']) ?></small>
                                                    </td>
                                                    <td><?= $net < 0 ? formatMoney(abs($net)) : '-' ?></td>
                                                    <td><?= $net > 0 ? formatMoney($net) : '-' ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $net > 0 ? 'success' : ($net < 0 ? 'danger' : 'secondary') ?>">
                                                            <?= formatMoney(abs($net)) ?> <?= $net > 0 ? 'A' : ($net < 0 ? 'B' : '') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($entity_filter === 'all' || $entity_filter === 'mekan'): ?>
                    <div class="col-md-<?= $entity_filter === 'all' ? '6' : '12' ?>">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-store me-2"></i>Mekan Bakiyeleri</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($mekan_bakiyeler)): ?>
                                    <p class="text-muted">Bekleyen mekan bakiyesi yok.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Mekan</th>
                                                    <th>Borç</th>
                                                    <th>Alacak</th>
                                                    <th>Net</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mekan_bakiyeler as $bakiye): ?>
                                                <?php $net = $bakiye['bakiye']; ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= sanitize($bakiye['mekan_name']) ?></strong>
                                                        <br><small>@<?= sanitize($bakiye['username']) ?></small>
                                                    </td>
                                                    <td><?= $net > 0 ? formatMoney($net) : '-' ?></td>
                                                    <td><?= $net < 0 ? formatMoney(abs($net)) : '-' ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $net > 0 ? 'danger' : ($net < 0 ? 'success' : 'secondary') ?>">
                                                            <?= formatMoney(abs($net)) ?> <?= $net > 0 ? 'B' : ($net < 0 ? 'A' : '') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDateFields(filterType) {
            // Tüm alanları gizle
            document.getElementById('daily-field').style.display = 'none';
            document.getElementById('weekly-field').style.display = 'none';
            document.getElementById('monthly-field').style.display = 'none';
            document.getElementById('custom-start-field').style.display = 'none';
            document.getElementById('custom-end-field').style.display = 'none';
            
            // Seçilen türe göre alanları göster
            switch(filterType) {
                case 'daily':
                    document.getElementById('daily-field').style.display = 'block';
                    break;
                case 'weekly':
                    document.getElementById('weekly-field').style.display = 'block';
                    break;
                case 'monthly':
                    document.getElementById('monthly-field').style.display = 'block';
                    break;
                case 'custom':
                    document.getElementById('custom-start-field').style.display = 'block';
                    document.getElementById('custom-end-field').style.display = 'block';
                    break;
            }
        }

        function toggleEntityFields(entityFilter) {
            // Tüm entity alanlarını gizle
            document.getElementById('mekan-field').style.display = 'none';
            document.getElementById('kurye-field').style.display = 'none';
            
            // Seçilen türe göre alanları göster
            switch(entityFilter) {
                case 'mekan':
                    document.getElementById('mekan-field').style.display = 'block';
                    break;
                case 'kurye':
                    document.getElementById('kurye-field').style.display = 'block';
                    break;
            }
        }
    </script>
</body>
</html>
