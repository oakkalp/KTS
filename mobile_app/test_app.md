# Mobil Uygulama Test Rehberi

## 🚀 Kurulum Adımları

### 1. Flutter SDK Kurulumu
```bash
# Flutter SDK'yı indirin ve PATH'e ekleyin
# https://docs.flutter.dev/get-started/install/windows

# Kurulumu kontrol edin
flutter doctor
```

### 2. Dependencies Kurulumu
```bash
# Proje dizininde
cd mobile_app
flutter pub get
```

### 3. Android/iOS Setup
```bash
# Android için
flutter doctor --android-licenses

# iOS için (macOS'ta)
sudo xcode-select --switch /Applications/Xcode.app/Contents/Developer
sudo xcodebuild -runFirstLaunch
```

## 🧪 Test Adımları

### 1. Kod Analizi
```bash
flutter analyze
```

### 2. Unit Tests
```bash
flutter test
```

### 3. Debug Build
```bash
# Android
flutter run -d android

# iOS
flutter run -d ios

# Web (test için)
flutter run -d web-server --web-port 8080
```

### 4. Release Build
```bash
# Android APK
flutter build apk --release

# iOS
flutter build ios --release
```

## 🔧 Geliştirme Notları

### API Konfigürasyonu
`lib/config/app_config.dart` dosyasında:
- `baseUrl` değerini kendi sunucunuza göre güncelleyin
- Emulator için: `http://10.0.2.2/kuryefullsistem/api`
- Fiziksel cihaz için: `http://[IP_ADRESINIZ]/kuryefullsistem/api`

### Firebase Setup
1. Firebase Console'da yeni proje oluşturun
2. Android/iOS app ekleyin
3. `google-services.json` (Android) ve `GoogleService-Info.plist` (iOS) dosyalarını ekleyin

### Test Kullanıcıları
```
Kurye: testkurye / 123456
Mekan: testmekan / 123456
```

## 📱 Test Senaryoları

### 1. Giriş Testi
- [x] Splash screen görüntülenir
- [x] Login formu çalışır
- [x] Hatalı giriş durumunda error mesajı
- [x] Başarılı girişte dashboard'a yönlendirme

### 2. Dashboard Testi
- [x] İstatistikler yüklenir
- [x] Kurye durumu görüntülenir
- [x] Aktif siparişler listelenir
- [x] Yeni siparişler listelenir
- [x] Pull-to-refresh çalışır

### 3. Sipariş Testi
- [ ] Yeni sipariş kabul edilir
- [ ] Sipariş durumu güncellenir
- [ ] Konum güncellemesi çalışır
- [ ] Push notification alınır

### 4. Performans Testi
- [ ] Uygulama 3 saniyede açılır
- [ ] API çağrıları 5 saniyede tamamlanır
- [ ] Memory kullanımı 100MB altında
- [ ] Battery drain normal seviyede

## 🐛 Bilinen Sorunlar

### 1. Firebase Initialization
- Firebase projesi kurulmamışsa notification servisi hata verir
- Çözüm: Firebase projesini kurun veya notification service'i devre dışı bırakın

### 2. Location Permissions
- Android 6.0+ için runtime permission gerekli
- Çözüm: Uygulamada permission handler kullanılıyor

### 3. Network Security
- Android 9+ HTTP trafiği varsayılan olarak bloklu
- Çözüm: `android/app/src/main/res/xml/network_security_config.xml` eklenmiş

## 📊 Test Sonuçları

| Test | Durum | Notlar |
|------|-------|--------|
| Build | ✅ | Başarılı |
| Login | ✅ | Test kullanıcısı ile |
| Dashboard | ✅ | Mock data ile |
| API Calls | ⏳ | Backend kurulumu gerekli |
| Notifications | ⏳ | Firebase kurulumu gerekli |
| Location | ⏳ | Cihaz testi gerekli |

## 🔄 Sonraki Adımlar

1. **Firebase Kurulumu**: Push notification için
2. **Real Device Testing**: GPS ve notification testleri
3. **Performance Optimization**: Memory ve battery kullanımı
4. **UI/UX Improvements**: Design feedback'lere göre
5. **Integration Testing**: Backend ile tam entegrasyon

## 📞 Destek

Sorun yaşarsanız:
1. `flutter doctor` çıktısını kontrol edin
2. `flutter clean && flutter pub get` deneyin
3. Android Studio/Xcode log'larını inceleyin
4. GitHub Issues'da benzer sorunları arayın
