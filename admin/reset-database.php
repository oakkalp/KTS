<?php
/**
 * Kurye Full System - Veritabanı Sıfırlama
 * UYARI: Bu script tüm verileri siler!
 */

require_once '../config/config.php';
requireUserType('admin');

$confirmation = $_POST['confirmation'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirmation === 'SIFIRLA') {
    try {
        $db = getDB();
        
        echo "<h2>🔄 Veritabanı Sıfırlanıyor...</h2>";
        
        // Tabloları temizle (foreign key sırası önemli)
        $tables_to_clear = [
            'kurye_konum_gecmisi',
            'kurye_konum', 
            'siparisler',
            'odemeler',
            'bakiye',
            'api_logs'
        ];
        
        foreach ($tables_to_clear as $table) {
            try {
                $db->query("DELETE FROM {$table}");
                echo "<p>✅ {$table} tablosu temizlendi</p>";
            } catch (Exception $e) {
                echo "<p>⚠️ {$table} tablosu bulunamadı veya zaten boş</p>";
            }
        }
        
        // Auto increment değerlerini sıfırla
        foreach ($tables_to_clear as $table) {
            try {
                $db->query("ALTER TABLE {$table} AUTO_INCREMENT = 1");
            } catch (Exception $e) {
                // Hata önemli değil
            }
        }
        
        // Kuryeler tablosunu güncelle (konum verilerini sıfırla)
        $db->query("
            UPDATE kuryeler SET 
            current_latitude = NULL,
            current_longitude = NULL,
            last_location_update = NULL,
            is_online = 0,
            is_available = 1,
            total_deliveries = 0,
            total_earnings = 0.00
        ");
        echo "<p>✅ Kurye bilgileri sıfırlandı</p>";
        
        // Mekanlar tablosunu güncelle
        $db->query("
            UPDATE mekanlar SET 
            total_orders = 0,
            rating = 0.00
        ");
        echo "<p>✅ Mekan bilgileri sıfırlandı</p>";
        
        // Test verilerini ekle
        echo "<h3>📝 Test Verileri Ekleniyor...</h3>";
        
        // Test siparişi için örnek veri
        $test_orders = [
            [
                'order_number' => 'ORD-' . date('Ymd') . '-001',
                'customer_name' => 'Ahmet Yılmaz',
                'customer_phone' => '05551234567',
                'customer_address' => 'Kadıköy Mah. Test Sokak No:1 Kadıköy/İstanbul',
                'customer_latitude' => 40.9925,
                'customer_longitude' => 29.0185,
                'order_details' => json_encode([
                    'items' => [
                        ['name' => 'Lahmacun', 'quantity' => 2, 'price' => 15.00],
                        ['name' => 'Ayran', 'quantity' => 2, 'price' => 3.00]
                    ],
                    'total' => 36.00
                ]),
                'total_amount' => 36.00,
                'delivery_fee' => 8.00,
                'payment_method' => 'nakit',
                'notes' => 'Test siparişi - Hızlı teslimat'
            ],
            [
                'order_number' => 'ORD-' . date('Ymd') . '-002',
                'customer_name' => 'Fatma Şahin',
                'customer_phone' => '05559876543',
                'customer_address' => 'Beşiktaş Mah. Örnek Cad. No:25 Beşiktaş/İstanbul',
                'customer_latitude' => 41.0422,
                'customer_longitude' => 29.0094,
                'order_details' => json_encode([
                    'items' => [
                        ['name' => 'Pizza Margherita', 'quantity' => 1, 'price' => 45.00],
                        ['name' => 'Kola', 'quantity' => 1, 'price' => 5.00]
                    ],
                    'total' => 50.00
                ]),
                'total_amount' => 50.00,
                'delivery_fee' => 10.00,
                'payment_method' => 'kapida_kart',
                'notes' => 'Test siparişi - Kapıda kart ile ödeme'
            ]
        ];
        
        // Test siparişlerini ekle (sadece mekan varsa)
        $mekan_count = $db->query("SELECT COUNT(*) FROM mekanlar")->fetchColumn();
        if ($mekan_count > 0) {
            $first_mekan = $db->query("SELECT id FROM mekanlar LIMIT 1")->fetchColumn();
            
            foreach ($test_orders as $order) {
                $db->query("
                    INSERT INTO siparisler (
                        order_number, mekan_id, customer_name, customer_phone, 
                        customer_address, customer_latitude, customer_longitude,
                        order_details, total_amount, delivery_fee, payment_method, 
                        status, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ", [
                    $order['order_number'], $first_mekan, $order['customer_name'], 
                    $order['customer_phone'], $order['customer_address'],
                    $order['customer_latitude'], $order['customer_longitude'],
                    $order['order_details'], $order['total_amount'], 
                    $order['delivery_fee'], $order['payment_method'], $order['notes']
                ]);
            }
            echo "<p>✅ " . count($test_orders) . " test siparişi eklendi</p>";
        } else {
            echo "<p>⚠️ Test siparişi eklenemedi - önce mekan oluşturun</p>";
        }
        
        echo "<div class='alert alert-success mt-4'>";
        echo "<h4>🎉 Veritabanı Başarıyla Sıfırlandı!</h4>";
        echo "<p><strong>Test etmek için:</strong></p>";
        echo "<ul>";
        echo "<li>📱 Kurye paneline gidip online olun</li>";
        echo "<li>🏪 Mekan panelinde yeni sipariş oluşturun</li>";
        echo "<li>🚚 Kurye olarak siparişi kabul edin</li>";
        echo "<li>📍 Konum güncellemelerini test edin</li>";
        echo "<li>💰 Admin panelde ödeme/tahsilat yapın</li>";
        echo "</ul>";
        echo "<p><a href='dashboard.php' class='btn btn-primary'>Dashboard'a Git</a></p>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>";
        echo "<h4>❌ Hata Oluştu!</h4>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veritabanı Sıfırla - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Veritabanı Sıfırlama</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-warning me-2"></i>DİKKAT!</h5>
                            <p>Bu işlem aşağıdaki verileri <strong>kalıcı olarak</strong> silecektir:</p>
                            <ul>
                                <li>🛍️ Tüm siparişler</li>
                                <li>💰 Tüm ödeme/tahsilat kayıtları</li>
                                <li>⚖️ Tüm bakiye bilgileri</li>
                                <li>📍 Tüm konum geçmişi</li>
                                <li>📊 API logları</li>
                            </ul>
                            <p><strong>Kullanıcılar ve mekanlar silinmeyecek.</strong></p>
                        </div>
                        
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>Otomatik Test Verileri</h5>
                            <p>Sıfırlama sonrası otomatik olarak eklenecek:</p>
                            <ul>
                                <li>✅ 2 adet test siparişi (pending durumunda)</li>
                                <li>✅ Kurye durumları sıfırlanacak</li>
                                <li>✅ Sistem test edilmeye hazır olacak</li>
                            </ul>
                        </div>
                        
                        <form method="POST" id="resetForm">
                            <div class="mb-3">
                                <label class="form-label">Onay için <strong>"SIFIRLA"</strong> yazın:</label>
                                <input type="text" class="form-control" name="confirmation" id="confirmation" 
                                       placeholder="SIFIRLA" required>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>İptal
                                </a>
                                <button type="submit" class="btn btn-danger" id="resetBtn" disabled>
                                    <i class="fas fa-trash me-2"></i>Veritabanını Sıfırla
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('confirmation').addEventListener('input', function() {
            const value = this.value.trim();
            const button = document.getElementById('resetBtn');
            
            if (value === 'SIFIRLA') {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check me-2"></i>ONAYLANDI - Sıfırla';
            } else {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-trash me-2"></i>Veritabanını Sıfırla';
            }
        });
        
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            if (!confirm('Son uyarı! Tüm veriler silinecek. Emin misiniz?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
