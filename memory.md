# Kurye Full System - Proje Dokümantasyonu

## 📋 Proje Genel Bakış

**Kurye Full System**, Yemek Sepeti ve Getir benzeri bir teslimat sistemi. Restoranlar, kuryeler ve admin paneli içeren tam kapsamlı bir web uygulaması.

### 🎯 Ana Özellikler
- **Admin Panel**: Kurye/mekan yönetimi, finansal raporlar, canlı takip
- **Mekan Panel**: Sipariş oluşturma, ödeme yöntemi seçimi
- **Kurye Panel**: Sipariş kabul etme, konum takibi, teslimat
- **Gerçek Zamanlı Takip**: Google Maps entegrasyonu
- **Finansal Sistem**: Komisyon hesaplama, ödeme/tahsilat

---

## 🗂️ Dosya Yapısı

```
kuryefullsistem/
├── admin/                    # Admin paneli
│   ├── dashboard.php        # Ana dashboard
│   ├── kuryeler.php         # Kurye yönetimi
│   ├── mekanlar.php         # Mekan yönetimi
│   ├── siparisler.php       # Sipariş yönetimi
│   ├── raporlar.php         # Raporlar
│   ├── detayli-rapor.php    # Detaylı raporlar
│   ├── odeme-raporlari.php  # Ödeme raporları
│   ├── map-tracking.php     # Canlı kurye takibi
│   ├── konum-gecmisi.php    # Konum geçmişi
│   ├── fix-database.php     # DB düzeltme
│   ├── setup-settings.php   # Sistem ayarları kurulum
│   └── ajax/               # AJAX endpoint'leri
│       ├── get_odeme_info.php
│       ├── process_odeme.php
│       ├── get_mekan_tahsilat_info.php
│       ├── process_tahsilat.php
│       └── get-address.php
├── mekan/                   # Mekan paneli
│   ├── dashboard.php        # Mekan dashboard
│   ├── yeni-siparis.php     # Sipariş oluşturma
│   ├── siparisler.php       # Sipariş listesi
│   └── raporlar.php         # Mekan raporları
├── kurye/                   # Kurye paneli
│   ├── dashboard.php        # Kurye dashboard
│   ├── siparislerim.php     # Aktif siparişler
│   ├── siparis-detay.php    # Sipariş detayı
│   ├── yeni-siparisler.php  # Yeni siparişler
│   ├── gecmis.php           # Teslimat geçmişi
│   ├── kazanclarim.php      # Kazanç raporu
│   └── profil.php           # Kurye profili
├── api/                     # API endpoint'leri
│   └── kurye/
│       ├── session-update-location.php
│       └── accept-order.php
├── config/                  # Konfigürasyon
│   ├── config.php           # Ana config
│   ├── database.php         # DB bağlantısı
│   └── functions.php        # Yardımcı fonksiyonlar
├── includes/                # Ortak dosyalar
│   ├── functions.php        # Genel fonksiyonlar
│   └── sidebar.php          # Sidebar menü
└── login.php               # Giriş sayfası
```

---

## 🗄️ Veritabanı Yapısı

### Ana Tablolar

#### `users` - Kullanıcılar
```sql
- id (PK)
- username, email, password
- full_name, phone
- user_type (admin/mekan/kurye)
- created_at, last_login
```

#### `kuryeler` - Kurye Bilgileri
```sql
- id (PK), user_id (FK)
- full_name, phone
- vehicle_type (motosiklet/bisiklet/araba/yaya)
- license_plate
- is_online, is_available
- current_latitude, current_longitude
- last_location_update
```

#### `siparisler` - Siparişler
```sql
- id (PK), order_number
- mekan_id (FK), kurye_id (FK)
- customer_name, customer_phone
- customer_address, delivery_address
- customer_latitude, customer_longitude
- order_details (JSON)
- total_amount, delivery_fee
- commission_amount
- status (pending/accepted/preparing/ready/picked_up/delivered/cancelled)
- priority (normal/urgent/express)
- payment_method (nakit/kapida_kart/online_kart)
- preparation_time, expected_pickup_time
- accepted_at, picked_up_at, delivered_at
- notes
```

#### `odemeler` - Ödeme/Tahsilat Kayıtları
```sql
- id (PK)
- user_id (FK), user_type (kurye/mekan)
- odeme_tutari, tahsilat_tutari
- aciklama, tarih
- created_at
```

#### `bakiye` - Devren Borç/Alacak
```sql
- id (PK)
- user_id (FK), user_type (kurye/mekan)
- borc, alacak
- updated_at
```

#### `kurye_konum_gecmisi` - Konum Geçmişi
```sql
- id (PK), kurye_id (FK)
- latitude, longitude, accuracy
- speed, heading, altitude
- siparis_id (FK)
- created_at
```

#### `kurye_konum` - Güncel Konum
```sql
- id (PK), kurye_id (FK)
- latitude, longitude, accuracy
- speed, heading, altitude
- updated_at
```

#### `sistem_ayarlari` - Sistem Ayarları
```sql
- id (PK)
- setting_key (UNIQUE)
- setting_value
- description
- created_at, updated_at
```

---

## 🔧 Ana Fonksiyonlar

### `config/config.php`
- **`getDB()`**: Veritabanı bağlantısı
- **`isLoggedIn()`**: Giriş kontrolü
- **`getUserType()`**: Kullanıcı tipi
- **`getUserId()`**: Kullanıcı ID'si
- **`requireUserType($type)`**: Yetki kontrolü

### `includes/functions.php`
- **`getKuryeId()`**: Kurye ID'sini al
- **`getMekanId()`**: Mekan ID'sini al
- **`isKurye()`**: Kurye kontrolü
- **`isAdmin()`**: Admin kontrolü
- **`getSetting($key, $default)`**: Sistem ayarı al
- **`sanitize($text)`**: Metin temizleme
- **`formatMoney($amount)`**: Para formatı
- **`formatDate($date)`**: Tarih formatı
- **`formatPhone($phone)`**: Telefon formatı
- **`calculateDistance($lat1, $lng1, $lat2, $lng2)`**: Mesafe hesaplama
- **`estimateDeliveryTime($distance, $vehicle_type)`**: Teslimat süresi tahmini

---

## 🎮 Panel İşleyişleri

### Admin Panel (`admin/`)

#### Dashboard (`dashboard.php`)
- **Aylık gelir**: Teslimat ücreti × teslimat sayısı
- **Aktif kuryeler**: Online kurye sayısı
- **Bekleyen siparişler**: Pending durumundaki siparişler
- **En aktif kuryeler**: Teslimat sayısına göre sıralama

#### Kurye Yönetimi (`kuryeler.php`)
- **Kurye listesi**: Online/offline durumu, performans
- **Ödeme sistemi**: Komisyon hesaplama, devren borç/alacak
- **Durum değiştirme**: Online/offline yapma
- **Performans takibi**: Teslimat sayısı, ortalama süre

#### Mekan Yönetimi (`mekanlar.php`)
- **Mekan listesi**: Sipariş sayısı, tahsilat durumu
- **Tahsilat sistemi**: Delivery fee × paket sayısı
- **Devren borç/alacak**: Kısmi tahsilat desteği

#### Canlı Takip (`map-tracking.php`)
- **Google Maps entegrasyonu**: Gerçek zamanlı konum
- **Araç ikonları**: Motosiklet/araba simgeleri
- **Otomatik merkezleme**: Kullanıcı konumuna göre
- **Online/offline durumu**: Renk kodlaması

#### Raporlar (`raporlar.php`, `detayli-rapor.php`)
- **Günlük/haftalık/aylık filtreler**
- **Kurye performansı**: Teslimat süreleri, gecikmeler
- **Mekan analizi**: Sipariş sayıları, gelirler
- **Detaylı sipariş listesi**: Adres, süre, durum

### Mekan Panel (`mekan/`)

#### Sipariş Oluşturma (`yeni-siparis.php`)
- **Müşteri bilgileri**: Ad, telefon, adres
- **Sipariş detayları**: Ürünler, miktarlar, fiyatlar
- **Ödeme yöntemi**: Nakit, kapıda kart, online kart
- **Hazırlık süresi**: Dakika cinsinden
- **Konum bilgisi**: Latitude/longitude

#### Sipariş Takibi (`siparisler.php`)
- **Durum gösterimi**: Bekliyor, hazırlanıyor, hazır, teslim edildi
- **Ödeme yöntemi**: İkonlu gösterim
- **Kurye bilgisi**: Atanmış kurye varsa

### Kurye Panel (`kurye/`)

#### Dashboard (`dashboard.php`)
- **Konum takibi**: GPS entegrasyonu, HTTPS gerekli
- **Online/offline durumu**: Müsaitlik kontrolü
- **Test konumu**: HTTP için fallback
- **Otomatik güncelleme**: 30 saniyede bir

#### Sipariş Yönetimi (`siparislerim.php`)
- **Aktif siparişler**: Accepted, preparing, ready, picked_up
- **Sipariş detayı**: Müşteri, adres, hazırlık süresi
- **Harita linki**: Google Maps entegrasyonu
- **Durum güncelleme**: Al, teslim et, iptal

#### Sipariş Detayı (`siparis-detay.php`)
- **Detaylı bilgiler**: Müşteri, mekan, ödeme yöntemi
- **Hazırlık süresi**: Kuryeye uyarı
- **Harita entegrasyonu**: Adres linki
- **Durum geçmişi**: Kabul, alım, teslimat zamanları

---

## 🔄 İş Akışları

### Sipariş Akışı
1. **Mekan**: Sipariş oluşturur (pending)
2. **Kurye**: Siparişi kabul eder (accepted)
3. **Mekan**: Hazırlık süresi sonunda hazır (ready)
4. **Kurye**: Siparişi alır (picked_up)
5. **Kurye**: Teslim eder (delivered)

### Ödeme Akışı
1. **Admin**: Kurye ödeme modalını açar
2. **Sistem**: Komisyon hesaplar (brüt × %15)
3. **Admin**: Ödeme tutarını girer
4. **Sistem**: Devren borç/alacak hesaplar
5. **Sistem**: Ödeme kaydı oluşturur

### Tahsilat Akışı
1. **Admin**: Mekan tahsilat modalını açar
2. **Sistem**: Delivery fee × paket sayısı hesaplar
3. **Admin**: Tahsilat tutarını girer
4. **Sistem**: Kısmi tahsilat desteği
5. **Sistem**: Tahsilat kaydı oluşturur

---

## 🚀 Kurulum ve Konfigürasyon

### Gereksinimler
- **PHP 8.2+**
- **MySQL 5.7+**
- **Apache/Nginx**
- **HTTPS** (GPS için gerekli)

### Veritabanı Bağlantısı
```php
// config/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kurye_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Google Maps API
```javascript
// map-tracking.php
const GOOGLE_MAPS_API_KEY = 'AIzaSyC-L4E5--L2M9dDvyLmcP-t9G2r84Y8GDY';
```

### HTTPS Kurulumu (XAMPP)
1. `apache/conf/extra/httpd-ssl.conf` düzenle
2. SSL sertifikası oluştur
3. Virtual host ekle
4. Port 443'ü aç

---

## 🐛 Bilinen Sorunlar ve Çözümler

### Database Sütun Eksiklikleri
**Sorun**: `Column not found` hataları
**Çözüm**: `admin/fix-database.php` çalıştır

### Session Problemi (HTTPS)
**Sorun**: HTTPS'de yetki hatası
**Çözüm**: HTTP ile `fix-database.php` çalıştır

### GPS Konum Hatası
**Sorun**: `Only secure origins are allowed`
**Çözüm**: HTTPS kullan veya test konumu

### Sistem Ayarları Eksik
**Sorun**: `getSetting()` fonksiyonu hatası
**Çözüm**: `admin/setup-settings.php` çalıştır

---

## 📊 Finansal Sistem

### Komisyon Hesaplama
```php
$commission_rate = 15.00; // %
$gross_earnings = $delivery_count * $delivery_fee;
$commission_amount = ($gross_earnings * $commission_rate) / 100;
$net_profit = $gross_earnings - $commission_amount;
```

### Devren Borç/Alacak
```php
// Kurye için
$toplam_odeme = $commission_amount + $devren_alacak - $devren_borc;

// Mekan için  
$toplam_tahsilat = $delivery_count * $delivery_fee + $devren_alacak - $devren_borc;
```

### Ödeme Durumları
- **Tam ödeme**: Bakiye sıfırlanır
- **Kısmi ödeme**: Kalan tutar devren borç/alacak
- **Fazla ödeme**: Fazla tutar devren borç/alacak

---

## 🔮 Gelecek Geliştirmeler

### Öncelikli Görevler
1. **Push Notifications**: Firebase entegrasyonu
2. **Mobile API**: RESTful API geliştirme
3. **Flutter App**: Mobil uygulama
4. **Real-time Updates**: WebSocket entegrasyonu
5. **Advanced Analytics**: Detaylı raporlar

### Teknik İyileştirmeler
1. **Caching**: Redis/Memcached
2. **Queue System**: Background jobs
3. **API Rate Limiting**: Güvenlik
4. **Database Optimization**: Index'ler
5. **Error Logging**: Monitöring

---

## 📞 Destek ve İletişim

### Debug Araçları
- **Console Logs**: JavaScript hataları
- **Error Logs**: PHP hataları
- **Database Logs**: SQL sorguları

### Test Senaryoları
1. **Admin**: Ödeme/tahsilat işlemleri
2. **Mekan**: Sipariş oluşturma
3. **Kurye**: Sipariş kabul etme, konum takibi
4. **Raporlar**: Filtreleme, detaylar

### Performans Metrikleri
- **Sayfa yükleme**: < 2 saniye
- **API response**: < 500ms
- **Database queries**: Optimize edilmiş
- **Memory usage**: < 128MB

---

## 📝 Son Güncelleme

**Tarih**: 2024-12-19
**Durum**: Tam çalışır sistem
**Son Değişiklikler**:
- ✅ Sipariş detay sayfası eklendi
- ✅ Ödeme yöntemi seçimi eklendi
- ✅ Harita entegrasyonu tamamlandı
- ✅ Finansal sistem optimize edildi
- ✅ Database schema düzeltildi

**Devam Edilecek**: Mobile API ve Flutter app geliştirme

