# 🚀 Kurye Full System

**Yemek Sepeti ve Getir benzeri profesyonel kurye takip sistemi**

Modern teknoloji ile geliştirilmiş, gerçek zamanlı konum takibi, otomatik sipariş yönetimi ve mobil uygulama desteğine sahip kapsamlı kurye yönetim sistemi.

## ✨ Özellikler

### 🎯 Ana Özellikler
- **Gerçek Zamanlı Konum Takibi**: GPS tabanlı hassas kurye takibi
- **Mobil Uygulama**: Android/iOS için optimize edilmiş kurye uygulaması
- **Push Notification**: Anlık bildirim sistemi
- **Multi-Panel**: Admin, Mekan ve Kurye için ayrı paneller
- **RESTful API**: Mobil uygulama entegrasyonu için kapsamlı API
- **Güvenli Sistem**: JWT token tabanlı kimlik doğrulama

### 👥 Kullanıcı Tipleri
- **👨‍💼 Admin Panel**: Sistem yöneticileri için kapsamlı yönetim
- **🏪 Mekan Panel**: Restoran/mağaza sahipleri için sipariş yönetimi
- **🏍️ Kurye Panel**: Kuryeler için teslimat yönetimi
- **📱 Mobil App**: Kuryeler için Android/iOS uygulaması

## 🛠️ Teknoloji Stack

### Backend
- **PHP 8.x** - Ana backend dili
- **MySQL 8.0** - Veritabanı
- **JWT** - Token tabanlı kimlik doğrulama
- **RESTful API** - Mobil uygulama entegrasyonu

### Frontend
- **HTML5 + CSS3** - Modern web arayüzü
- **JavaScript (ES6+)** - İnteraktif özellikler
- **Bootstrap 5** - Responsive tasarım
- **Chart.js** - Veri görselleştirme

### Mobile
- **Flutter** - Cross-platform mobil uygulama
- **Firebase** - Push notifications
- **Google Maps** - Harita entegrasyonu
- **GPS Tracking** - Konum takibi

## 🚀 Kurulum

### Gereksinimler
- **Web Server**: Apache/Nginx
- **PHP**: 8.0 veya üzeri
- **MySQL**: 8.0 veya üzeri
- **Composer**: Bağımlılık yönetimi için (opsiyonel)

### 1. Projeyi İndirin
```bash
git clone https://github.com/your-username/kuryefullsistem.git
cd kuryefullsistem
```

### 2. Veritabanı Kurulumu
```bash
# XAMPP kullanıyorsanız
php simple_install.php

# Manuel kurulum
mysql -u root -p < database_setup.sql
```

### 3. Konfigürasyon
`config/database.php` dosyasında veritabanı ayarlarınızı güncelleyin:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kurye_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Web Server Ayarları
- **XAMPP**: Projeyi `htdocs/kuryefullsistem` klasörüne kopyalayın
- **Erişim URL**: `http://localhost/kuryefullsistem/`
- **Uzak Erişim**: `http://192.168.1.137/kuryefullsistem/`

## 🔑 Giriş Bilgileri

Kurulum sonrası test hesapları:

| Kullanıcı Tipi | Kullanıcı Adı | Şifre | Panel |
|----------------|---------------|-------|-------|
| Admin | `admin` | `password` | Sistem Yönetimi |
| Test Mekan | `test_mekan` | `password` | Mekan Paneli |
| Test Kurye | `test_kurye` | `password` | Kurye Paneli |

## 📱 Mobil Uygulama

### Flutter Kurulumu
```bash
cd mobile_app
flutter pub get
flutter run
```

### APK Oluşturma
```bash
flutter build apk --release
```

Detaylı mobil uygulama kurulum talimatları için: [Mobile App README](mobile_app/README.md)

## 🌐 API Dokümantasyonu

RESTful API endpoints'lere erişim için:
- **Dokümantasyon**: `http://localhost/kuryefullsistem/api/`
- **Base URL**: `http://192.168.1.137/kuryefullsistem/api`

### Örnek API Kullanımı
```javascript
// Kullanıcı girişi
POST /api/auth/login
{
  "username": "test_kurye",
  "password": "password"
}

// Konum güncelleme
POST /api/kurye/update-location
Authorization: Bearer JWT_TOKEN
{
  "latitude": 41.0082,
  "longitude": 28.9784,
  "accuracy": 10.5
}
```

## 📊 Ekran Görüntüleri

### 📱 Mobil Uygulama Ekranları

#### Ana Ekranlar
![Kurye Uygulaması - Ana Ekran](kurye%20ekran/app1.png)

#### Sipariş Yönetimi
![Sipariş Detayları](kurye%20ekran/app2.png)

#### Konum ve Navigasyon
![Harita ve Konum Takibi](kurye%20ekran/app3.png)

#### Profil ve Ayarlar
![Kurye Profili](kurye%20ekran/app4png.png)

#### Dashboard ve İstatistikler
![Kurye Dashboard](kurye%20ekran/app5.png)

#### Sipariş Geçmişi
![Teslimat Geçmişi](kurye%20ekran/app6.png)

### Web Panelleri
- **Ana Sayfa**: Modern ve kullanıcı dostu arayüz
- **Admin Dashboard**: Kapsamlı sistem yönetimi
- **Mekan Paneli**: Sipariş takibi ve yönetimi
- **Kurye Paneli**: Teslimat yönetimi ve konum takibi

## 🔧 Geliştirme

### Proje Yapısı
```
kuryefullsistem/
├── admin/              # Admin paneli
├── mekan/              # Mekan paneli
├── kurye/              # Kurye paneli
├── api/                # RESTful API endpoints
├── config/             # Konfigürasyon dosyları
├── includes/           # Yardımcı fonksiyonlar
├── assets/             # CSS, JS, resimler
├── mobile_app/         # Flutter mobil uygulama
├── logs/               # Sistem logları
└── uploads/            # Yüklenen dosyalar
```

### API Endpoint'leri
- `POST /api/auth/login` - Kullanıcı girişi
- `POST /api/kurye/update-location` - Konum güncelleme
- `POST /api/kurye/toggle-status` - Online/Offline durum
- `GET /api/kurye/orders` - Sipariş listesi
- `POST /api/kurye/accept-order` - Sipariş kabul etme
- `POST /api/notification/update-token` - FCM token güncelleme

### Veritabanı Tabloları
- `users` - Kullanıcı bilgileri
- `mekanlar` - Restoran/mağaza bilgileri
- `kuryeler` - Kurye bilgileri
- `siparisler` - Sipariş bilgileri
- `kurye_konum_gecmisi` - Konum geçmişi
- `bildirimler` - Bildirim geçmişi
- `sistem_ayarlari` - Sistem konfigürasyonu

## 🔒 Güvenlik

- **JWT Token**: Güvenli API erişimi
- **SQL Injection**: Prepared statements koruması
- **XSS Protection**: Input sanitization
- **CSRF Token**: Form güvenliği
- **Rate Limiting**: API kötüye kullanım koruması
- **Password Hashing**: bcrypt şifreleme

## 📈 Performans

- **Database İndeksleme**: Hızlı sorgular
- **API Rate Limiting**: Sistem koruması
- **Optimized Queries**: Veritabanı performansı
- **Caching**: Hızlı veri erişimi
- **Responsive Design**: Mobil uyumluluk

## 🔄 Güncelleme Geçmişi

### v1.0.0 (2024-01-01)
- ✅ Temel sistem altyapısı
- ✅ Admin, Mekan ve Kurye panelleri
- ✅ RESTful API endpoints
- ✅ JWT tabanlı kimlik doğrulama
- ✅ Gerçek zamanlı konum takibi
- ✅ Push notification hazırlığı
- ✅ Flutter mobil uygulama template'i

## 🤝 Katkıda Bulunma

1. Fork yapın
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Değişikliklerinizi commit edin (`git commit -m 'Add amazing feature'`)
4. Branch'inizi push edin (`git push origin feature/amazing-feature`)
5. Pull Request oluşturun

## 📄 Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakın.

## 📞 İletişim

- **Proje Sahibi**: [Your Name]
- **Email**: your.email@example.com
- **Website**: https://your-website.com

## 🙏 Teşekkürler

Bu projeyi mümkün kılan açık kaynak projelere ve topluluğa teşekkürler:
- [PHP](https://php.net)
- [MySQL](https://mysql.com)
- [Bootstrap](https://getbootstrap.com)
- [Flutter](https://flutter.dev)
- [Firebase](https://firebase.google.com)

---

⭐ Bu projeyi beğendiyseniz yıldız vermeyi unutmayın!

## 📋 Yapılacaklar Listesi

Gelecek sürümler için planlanan özellikler [YAPILACAKLAR.md](YAPILACAKLAR.md) dosyasında detaylandırılmıştır.

### Öncelikli Özellikler
- [ ] Google Maps API entegrasyonu
- [ ] SMS ve Email bildirim sistemi
- [ ] Gelişmiş raporlama modülü
- [ ] Çoklu dil desteği
- [ ] Ödeme sistemi entegrasyonu

### İleri Özellikler
- [ ] AI tabanlı rota optimizasyonu
- [ ] IoT sensör entegrasyonu
- [ ] Blockchain tabanlı güvenlik
- [ ] Machine learning performans analizi
