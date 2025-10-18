<?php
/**
 * Kurye Full System - Mobile API Documentation
 * Mobil uygulama için özel API endpoint'leri
 */

require_once '../../config/config.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile API Documentation - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .api-endpoint {
            border-left: 4px solid #28a745;
            background: #f8f9fa;
        }
        .method-badge {
            font-family: monospace;
            font-weight: bold;
        }
        .method-get { background: #28a745; }
        .method-post { background: #007bff; }
        .method-put { background: #ffc107; color: #000; }
        .method-delete { background: #dc3545; }
        pre {
            background: #2d3748;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
        }
        .response-example {
            background: #1a202c;
            color: #e2e8f0;
        }
        .app-section {
            border: 2px solid #28a745;
            border-radius: 10px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <div class="text-center mb-5">
                    <h1><i class="fas fa-mobile-alt me-2 text-success"></i><?= SITE_NAME ?> Mobile API</h1>
                    <p class="lead">Mobil Uygulama API Dokümantasyonu</p>
                    <p class="text-muted">Base URL: <code><?= BASE_URL ?>/api/mobile</code></p>
                </div>
                
                <!-- Mobil Uygulama Türleri -->
                <div class="row mb-5">
                    <div class="col-md-4">
                        <div class="app-section p-4 text-center h-100">
                            <i class="fas fa-motorcycle fa-3x text-success mb-3"></i>
                            <h4>Kurye Uygulaması</h4>
                            <p class="text-muted">Siparişleri kabul etme, konum takibi, teslimat yönetimi</p>
                            <div class="badge bg-success">React Native / Flutter</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="app-section p-4 text-center h-100">
                            <i class="fas fa-users fa-3x text-primary mb-3"></i>
                            <h4>Müşteri Uygulaması</h4>
                            <p class="text-muted">Sipariş verme, takip, ödeme işlemleri</p>
                            <div class="badge bg-primary">React Native / Flutter</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="app-section p-4 text-center h-100">
                            <i class="fas fa-store fa-3x text-warning mb-3"></i>
                            <h4>Mekan Uygulaması</h4>
                            <p class="text-muted">Sipariş yönetimi, kurye atama, raporlar</p>
                            <div class="badge bg-warning text-dark">React Native / Flutter</div>
                        </div>
                    </div>
                </div>

                <!-- Authentication -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h3><i class="fas fa-key me-2"></i>Mobil Authentication</h3>
                    </div>
                    <div class="card-body">
                        <p>Mobil uygulamalar için JWT tabanlı authentication sistemi. Token'lar 30 gün geçerlidir.</p>
                        
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-post me-2">POST</span>
                                <code>/mobile/auth/login</code>
                            </div>
                            <p class="mb-2">Mobil uygulama girişi</p>
                            
                            <h6>Request Body:</h6>
                            <pre><code>{
  "username": "kurye1",
  "password": "password123",
  "device_info": {
    "device_id": "unique_device_id",
    "device_name": "Samsung Galaxy S21",
    "app_version": "1.0.0",
    "platform": "android",
    "fcm_token": "fcm_registration_token"
  }
}</code></pre>
                            
                            <h6>Response:</h6>
                            <pre class="response-example"><code>{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "refresh_token": "rt_abc123...",
  "user": {
    "id": 1,
    "username": "kurye1",
    "user_type": "kurye",
    "full_name": "Test Kurye",
    "phone": "05551234567",
    "kurye_id": 1,
    "license_plate": "34ABC123",
    "vehicle_type": "motorcycle"
  },
  "expires_at": "2024-02-01T12:00:00Z"
}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Kurye Mobil API -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h3><i class="fas fa-motorcycle me-2"></i>Kurye Mobil API</h3>
                    </div>
                    <div class="card-body">
                        
                        <!-- Dashboard -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-get me-2">GET</span>
                                <code>/mobile/kurye/dashboard</code>
                            </div>
                            <p class="mb-2">Kurye dashboard verileri</p>
                            
                            <h6>Response:</h6>
                            <pre class="response-example"><code>{
  "success": true,
  "data": {
    "stats": {
      "today_deliveries": 5,
      "today_earnings": 170.00,
      "active_orders": 2,
      "rating": 4.8
    },
    "active_orders": [
      {
        "id": 123,
        "order_number": "ORD20240101001",
        "status": "ready",
        "customer": {
          "name": "Ahmet Yılmaz",
          "phone": "05551234567",
          "address": "Atatürk Cad. No:15 Konak/İzmir"
        },
        "restaurant": {
          "name": "Pizza Palace",
          "address": "İnönü Mah. 1453 Sok. No:7",
          "phone": "02321234567"
        },
        "total_amount": 85.50,
        "delivery_fee": 40.00,
        "net_earning": 34.00,
        "payment_method": "cash",
        "estimated_time": 25,
        "created_at": "2024-01-01T14:30:00Z"
      }
    ],
    "available_orders": [
      {
        "id": 124,
        "order_number": "ORD20240101002",
        "restaurant": {
          "name": "Burger King",
          "address": "Cumhuriyet Mah. 123 Sok.",
          "distance": 1.2
        },
        "delivery_fee": 40.00,
        "net_earning": 34.00,
        "estimated_time": 20,
        "expires_at": "2024-01-01T15:00:00Z"
      }
    ]
  }
}</code></pre>
                        </div>

                        <!-- Sipariş Kabul -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-post me-2">POST</span>
                                <code>/mobile/kurye/accept-order</code>
                            </div>
                            <p class="mb-2">Siparişi kabul et</p>
                            
                            <h6>Request Body:</h6>
                            <pre><code>{
  "order_id": 123,
  "current_location": {
    "latitude": 38.4192,
    "longitude": 27.1287
  }
}</code></pre>
                        </div>

                        <!-- Konum Güncelleme -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-post me-2">POST</span>
                                <code>/mobile/kurye/location</code>
                            </div>
                            <p class="mb-2">Gerçek zamanlı konum güncelleme</p>
                            
                            <h6>Request Body:</h6>
                            <pre><code>{
  "latitude": 38.4192,
  "longitude": 27.1287,
  "accuracy": 10.5,
  "speed": 25.0,
  "heading": 180.0,
  "timestamp": "2024-01-01T14:30:00Z"
}</code></pre>
                        </div>

                        <!-- Sipariş Durumu Güncelleme -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-put me-2">PUT</span>
                                <code>/mobile/kurye/order-status</code>
                            </div>
                            <p class="mb-2">Sipariş durumunu güncelle</p>
                            
                            <h6>Request Body:</h6>
                            <pre><code>{
  "order_id": 123,
  "status": "picked_up", // "picked_up", "delivering", "delivered"
  "location": {
    "latitude": 38.4192,
    "longitude": 27.1287
  },
  "notes": "Sipariş alındı",
  "photo": "base64_encoded_image" // Opsiyonel
}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Müşteri Mobil API -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="fas fa-users me-2"></i>Müşteri Mobil API</h3>
                    </div>
                    <div class="card-body">
                        
                        <!-- Mekan Listesi -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-get me-2">GET</span>
                                <code>/mobile/customer/restaurants</code>
                            </div>
                            <p class="mb-2">Yakındaki mekanları listele</p>
                            
                            <h6>Query Parameters:</h6>
                            <ul>
                                <li><code>lat</code> - Enlem</li>
                                <li><code>lng</code> - Boylam</li>
                                <li><code>radius</code> - Arama yarıçapı (km)</li>
                            </ul>
                        </div>

                        <!-- Sipariş Verme -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-post me-2">POST</span>
                                <code>/mobile/customer/order</code>
                            </div>
                            <p class="mb-2">Yeni sipariş ver</p>
                            
                            <h6>Request Body:</h6>
                            <pre><code>{
  "restaurant_id": 1,
  "items": [
    {
      "name": "Margherita Pizza",
      "quantity": 2,
      "price": 35.00,
      "notes": "Az baharatlı"
    }
  ],
  "delivery_address": {
    "title": "Ev",
    "address": "Atatürk Cad. No:15 Konak/İzmir",
    "latitude": 38.4192,
    "longitude": 27.1287,
    "notes": "2. kat, zil çalın"
  },
  "payment_method": "cash",
  "notes": "Hızlı teslimat lütfen"
}</code></pre>
                        </div>

                        <!-- Sipariş Takibi -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-get me-2">GET</span>
                                <code>/mobile/customer/order/{order_id}/track</code>
                            </div>
                            <p class="mb-2">Sipariş takip bilgileri</p>
                            
                            <h6>Response:</h6>
                            <pre class="response-example"><code>{
  "success": true,
  "order": {
    "id": 123,
    "status": "delivering",
    "estimated_arrival": "2024-01-01T15:15:00Z",
    "courier": {
      "name": "Test Kurye",
      "phone": "05559876543",
      "rating": 4.8,
      "location": {
        "latitude": 38.4200,
        "longitude": 27.1290
      }
    },
    "timeline": [
      {
        "status": "pending",
        "timestamp": "2024-01-01T14:30:00Z",
        "message": "Sipariş alındı"
      },
      {
        "status": "accepted",
        "timestamp": "2024-01-01T14:32:00Z",
        "message": "Kurye atandı"
      }
    ]
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Mekan Mobil API -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h3><i class="fas fa-store me-2"></i>Mekan Mobil API</h3>
                    </div>
                    <div class="card-body">
                        
                        <!-- Sipariş Yönetimi -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-get me-2">GET</span>
                                <code>/mobile/restaurant/orders</code>
                            </div>
                            <p class="mb-2">Mekan siparişlerini listele</p>
                            
                            <h6>Query Parameters:</h6>
                            <ul>
                                <li><code>status</code> - pending, preparing, ready, completed</li>
                                <li><code>date</code> - YYYY-MM-DD formatında tarih</li>
                            </ul>
                        </div>

                        <!-- Sipariş Durumu Güncelleme -->
                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-put me-2">PUT</span>
                                <code>/mobile/restaurant/order-status</code>
                            </div>
                            <p class="mb-2">Sipariş durumunu güncelle</p>
                            
                            <h6>Request Body:</h6>
                            <pre><code>{
  "order_id": 123,
  "status": "ready", // "preparing", "ready", "cancelled"
  "estimated_time": 15 // dakika
}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Push Notifications -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h3><i class="fas fa-bell me-2"></i>Push Notifications</h3>
                    </div>
                    <div class="card-body">
                        <p>Firebase Cloud Messaging (FCM) kullanılarak gerçek zamanlı bildirimler</p>
                        
                        <h6>Bildirim Türleri:</h6>
                        <ul>
                            <li><strong>Kurye:</strong> Yeni sipariş, sipariş iptali, ödeme bildirimi</li>
                            <li><strong>Müşteri:</strong> Sipariş durumu, kurye konumu, teslimat</li>
                            <li><strong>Mekan:</strong> Yeni sipariş, kurye atama, ödeme</li>
                        </ul>

                        <div class="api-endpoint p-3 rounded mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge method-badge method-post me-2">POST</span>
                                <code>/mobile/notification/register</code>
                            </div>
                            <p class="mb-2">FCM token kaydet</p>
                            
                            <h6>Request Body:</h6>
                            <pre><code>{
  "fcm_token": "firebase_token_here",
  "device_info": {
    "platform": "android",
    "version": "13",
    "model": "Samsung Galaxy S21"
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- WebSocket Real-time -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h3><i class="fas fa-bolt me-2"></i>WebSocket Real-time</h3>
                    </div>
                    <div class="card-body">
                        <p>Gerçek zamanlı güncellemeler için WebSocket bağlantısı</p>
                        
                        <h6>WebSocket URL:</h6>
                        <pre><code>wss://<?= $_SERVER['HTTP_HOST'] ?>/ws</code></pre>
                        
                        <h6>Real-time Events:</h6>
                        <ul>
                            <li><code>order_status_changed</code> - Sipariş durumu değişikliği</li>
                            <li><code>courier_location_update</code> - Kurye konum güncellemesi</li>
                            <li><code>new_order_assigned</code> - Yeni sipariş atama</li>
                            <li><code>delivery_completed</code> - Teslimat tamamlandı</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
