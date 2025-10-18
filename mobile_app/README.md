# Kurye Full System - Mobile App

Bu klasör Flutter ile geliştirilmiş Android/iOS mobil uygulamasını içerir.

## Gereksinimler

- Flutter SDK (3.0+)
- Dart SDK (2.17+)
- Android Studio / VS Code
- Android SDK (API Level 21+)
- Firebase Console hesabı (Push notifications için)

## Kurulum

### 1. Flutter Kurulumu

```bash
# Flutter SDK'yı indirin ve PATH'e ekleyin
# https://flutter.dev/docs/get-started/install

# Kurulumu doğrulayın
flutter doctor
```

### 2. Projeyi Oluşturun

```bash
# Bu dizinde Flutter projesi oluşturun
flutter create kurye_app
cd kurye_app
```

### 3. Bağımlılıkları Ekleyin

`pubspec.yaml` dosyasına aşağıdaki paketleri ekleyin:

```yaml
dependencies:
  flutter:
    sdk: flutter
  
  # HTTP istekleri için
  http: ^0.13.5
  dio: ^5.3.2
  
  # State management
  provider: ^6.0.5
  riverpod: ^2.4.0
  
  # Local storage
  shared_preferences: ^2.2.2
  hive: ^2.2.3
  hive_flutter: ^1.1.0
  
  # Location services
  geolocator: ^9.0.2
  location: ^4.4.0
  
  # Maps
  google_maps_flutter: ^2.5.0
  
  # Push notifications
  firebase_core: ^2.17.0
  firebase_messaging: ^14.7.0
  flutter_local_notifications: ^15.1.1
  
  # UI components
  cupertino_icons: ^1.0.2
  flutter_launcher_icons: ^0.13.1
  
  # Utilities
  intl: ^0.18.1
  url_launcher: ^6.1.14
  permission_handler: ^11.0.1

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^2.0.0
```

### 4. Firebase Konfigürasyonu

1. Firebase Console'da proje oluşturun
2. Android uygulaması ekleyin (package name: com.kuryesystem.app)
3. `google-services.json` dosyasını `android/app/` klasörüne kopyalayın
4. `android/build.gradle` ve `android/app/build.gradle` dosyalarını güncelleyin

### 5. Permissions (Android)

`android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
<uses-permission android:name="android.permission.CALL_PHONE" />
<uses-permission android:name="android.permission.VIBRATE" />
<uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED"/>
<uses-permission android:name="android.permission.WAKE_LOCK" />
```

## Uygulama Yapısı

```
lib/
├── main.dart                 # Ana uygulama giriş noktası
├── config/
│   ├── app_config.dart      # Uygulama konfigürasyonu
│   ├── api_config.dart      # API endpoints
│   └── theme.dart           # Tema ayarları
├── models/
│   ├── user.dart            # Kullanıcı modeli
│   ├── order.dart           # Sipariş modeli
│   └── location.dart        # Konum modeli
├── services/
│   ├── api_service.dart     # HTTP API servisi
│   ├── auth_service.dart    # Kimlik doğrulama
│   ├── location_service.dart # GPS servisi
│   ├── notification_service.dart # Push notifications
│   └── storage_service.dart # Local storage
├── providers/
│   ├── auth_provider.dart   # Kimlik doğrulama state
│   ├── order_provider.dart  # Sipariş state
│   └── location_provider.dart # Konum state
├── screens/
│   ├── splash_screen.dart   # Açılış ekranı
│   ├── login_screen.dart    # Giriş ekranı
│   ├── dashboard_screen.dart # Ana ekran
│   ├── orders_screen.dart   # Siparişler
│   ├── order_detail_screen.dart # Sipariş detayı
│   ├── map_screen.dart      # Harita
│   └── profile_screen.dart  # Profil
├── widgets/
│   ├── custom_button.dart   # Özel butonlar
│   ├── order_card.dart      # Sipariş kartı
│   └── loading_widget.dart  # Yükleme göstergesi
└── utils/
    ├── constants.dart       # Sabitler
    ├── helpers.dart         # Yardımcı fonksiyonlar
    └── validators.dart      # Form validasyonları
```

## Özellikler

### ✅ Temel Özellikler
- [x] Kullanıcı girişi (JWT token)
- [x] Dashboard (sipariş özeti)
- [x] Aktif siparişler listesi
- [x] Sipariş detay görüntüleme
- [x] GPS konum takibi
- [x] Online/Offline durum değiştirme

### ✅ İleri Özellikler
- [x] Push notifications
- [x] Gerçek zamanlı konum güncellemesi
- [x] Google Maps entegrasyonu
- [x] Telefon arama özelliği
- [x] Offline data caching
- [x] Background location tracking

### 🔄 Geliştirme Aşamasında
- [ ] Kamera entegrasyonu (teslimat fotoğrafı)
- [ ] QR kod okuma
- [ ] Ses bildirimleri
- [ ] Multi-language support

## API Konfigürasyonu

`lib/config/api_config.dart`:

```dart
class ApiConfig {
  // Geliştirme ortamı için localhost
  static const String baseUrl = 'http://127.0.0.1/kuryefullsistem/api';
  
  // Uzak erişim için
  // static const String baseUrl = 'http://192.168.1.137/kuryefullsistem/api';
  
  // Endpoints
  static const String login = '/auth/login';
  static const String updateLocation = '/kurye/update-location';
  static const String toggleStatus = '/kurye/toggle-status';
  static const String getOrders = '/kurye/orders';
  static const String acceptOrder = '/kurye/accept-order';
  static const String updateOrderStatus = '/kurye/order-status';
  static const String updateToken = '/notification/update-token';
}
```

## Çalıştırma

```bash
# Bağımlılıkları yükleyin
flutter pub get

# Android emulator veya cihazda çalıştırın
flutter run

# Release build oluşturun
flutter build apk --release
```

## Test

```bash
# Unit testleri çalıştırın
flutter test

# Widget testleri
flutter test test/widget_test.dart

# Integration testleri
flutter drive --target=test_driver/app.dart
```

## Deployment

### Android APK
```bash
flutter build apk --release
# APK dosyası: build/app/outputs/flutter-apk/app-release.apk
```

### Android App Bundle (Google Play Store)
```bash
flutter build appbundle --release
# AAB dosyası: build/app/outputs/bundle/release/app-release.aab
```

## Notlar

- Uygulama sadece kurye kullanıcıları için tasarlanmıştır
- GPS izinleri uygulama açılırken otomatik istenir
- Background location tracking için ek izinler gerekebilir
- Firebase push notification için google-services.json dosyası gereklidir
- API URL'lerini production'da değiştirmeyi unutmayın

## Sorun Giderme

### 1. Location Permission Hatası
```dart
// Konum izinlerini kontrol edin
await Geolocator.requestPermission();
```

### 2. Network Connection Hatası
- Emulator'da localhost yerine 10.0.2.2 kullanın
- Gerçek cihazda IP adresini kullanın (192.168.1.137)

### 3. Firebase Configuration Hatası
- google-services.json dosyasının doğru yerde olduğundan emin olun
- Package name'in Firebase Console'daki ile aynı olduğunu kontrol edin

## Katkıda Bulunma

1. Fork yapın
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Commit yapın (`git commit -m 'Add some amazing feature'`)
4. Push yapın (`git push origin feature/amazing-feature`)
5. Pull Request oluşturun

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır.
