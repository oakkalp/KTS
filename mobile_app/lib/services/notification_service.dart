import 'package:flutter/material.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'dart:io';
import 'api_service.dart';

class NotificationService {
  static final FirebaseMessaging _firebaseMessaging = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _localNotifications = 
      FlutterLocalNotificationsPlugin();
  
  static final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();
  
  static String? _fcmToken;
  static bool _isInitialized = false;

  /// Notification service'i başlat
  static Future<void> initialize() async {
    if (_isInitialized) return;

    try {
      // Local notifications'ı başlat
      await _initializeLocalNotifications();
      
      // Firebase messaging'i başlat
      await _initializeFirebaseMessaging();
      
      _isInitialized = true;
      debugPrint('NotificationService initialized successfully');
    } catch (e) {
      debugPrint('NotificationService initialization error: $e');
    }
  }

  /// Local notifications'ı başlat
  static Future<void> _initializeLocalNotifications() async {
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher');

    const DarwinInitializationSettings initializationSettingsIOS =
        DarwinInitializationSettings(
      requestAlertPermission: false,
      requestBadgePermission: false,
      requestSoundPermission: false,
    );

    const InitializationSettings initializationSettings =
        InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );

    await _localNotifications.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: _onNotificationTapped,
    );
  }

  /// Firebase messaging'i başlat
  static Future<void> _initializeFirebaseMessaging() async {
    // İzin iste
    await _requestNotificationPermissions();

    // FCM token'ı al
    _fcmToken = await _firebaseMessaging.getToken();
    debugPrint('FCM Token: $_fcmToken');
    debugPrint('FCM Token Length: ${_fcmToken?.length}');
    debugPrint('FCM Token Type: ${_fcmToken.runtimeType}');

    // Token değişikliklerini dinle
    _firebaseMessaging.onTokenRefresh.listen((token) {
      _fcmToken = token;
      _sendTokenToServer(); // FCM token güncelleme etkinleştirildi
      debugPrint('FCM Token refreshed: $token');
    });

    // Foreground mesajları dinle
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // Background'dan açılan mesajları dinle
    FirebaseMessaging.onMessageOpenedApp.listen(_handleMessageOpenedApp);
    
    // Uygulama kapalıyken gelen mesajları dinle
    FirebaseMessaging.instance.getInitialMessage().then((message) {
      if (message != null) {
        _handleMessageOpenedApp(message);
      }
    });

    // Uygulama kapalıyken gelen mesajları kontrol et
    final initialMessage = await _firebaseMessaging.getInitialMessage();
    if (initialMessage != null) {
      _handleMessageOpenedApp(initialMessage);
    }

    // Token'ı sunucuya gönder
    if (_fcmToken != null) {
      await _sendTokenToServer();
    }
  }

  /// Notification izinlerini iste
  static Future<void> _requestNotificationPermissions() async {
    // Firebase messaging izni
    final NotificationSettings settings = await _firebaseMessaging.requestPermission(
      alert: true,
      announcement: false,
      badge: true,
      carPlay: false,
      criticalAlert: false,
      provisional: false,
      sound: true,
    );

    // Android için ek izinler
    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      debugPrint('User granted permission');
      
      // Android 13+ için POST_NOTIFICATIONS izni
      await Permission.notification.request();
    } else {
      debugPrint('User declined or has not accepted permission');
    }
  }

  /// FCM token'ı sunucuya gönder (basit versiyon)
  static Future<void> sendTokenToServer() async {
    await _sendTokenToServer();
  }

  /// FCM token'ı sunucuya gönder (private method)
  static Future<void> _sendTokenToServer() async {
    if (_fcmToken == null) {
      debugPrint('FCM token is null, skipping registration');
      return;
    }

    try {
      debugPrint('FCM token registration started: $_fcmToken');
      
      // SharedPreferences'dan user token'ı al
      final prefs = await SharedPreferences.getInstance();
      final userToken = prefs.getString('user_token');
      
      debugPrint('User token from SharedPreferences: $userToken');
      
      if (userToken == null) {
        debugPrint('User not authenticated, FCM token will be sent after login');
        // Token'ı local olarak kaydet, login sonrası gönderilecek
        await prefs.setString('fcm_token_pending', _fcmToken!);
        return;
      }

      // API service'e token'ı geçici olarak ayarla
      ApiService.setAuthToken(userToken);
      debugPrint('Auth token set for API service');

      final response = await ApiService.post('/notification/update-token.php', {
        'device_token': _fcmToken,
        'device_type': Platform.isAndroid ? 'android' : 'ios',
        'app_version': '1.0.0',
        'device_info': {
          'platform': Platform.operatingSystem,
          'version': Platform.operatingSystemVersion,
        },
      });

      debugPrint('FCM token API response: $response');

      if (response['success']) {
        debugPrint('FCM token registered successfully');
        // Başarılı olduysa pending token'ı temizle
        await prefs.remove('fcm_token_pending');
      } else {
        debugPrint('FCM token registration failed: ${response['error']['message']}');
      }
    } catch (e) {
      debugPrint('FCM token registration error: $e');
    }
  }

  /// Foreground mesajları işle
  static Future<void> _handleForegroundMessage(RemoteMessage message) async {
    debugPrint('Foreground message received: ${message.messageId}');
    
    // Local notification göster
    await _showLocalNotification(
      title: message.notification?.title ?? 'Bildirim',
      body: message.notification?.body ?? '',
      data: message.data,
    );
  }

  /// Background'dan açılan mesajları işle
  static void _handleMessageOpenedApp(RemoteMessage message) {
    debugPrint('Message opened app: ${message.messageId}');
    debugPrint('Message data: ${message.data}');
    
    // Mesaj verilerine göre sayfa yönlendirmesi yap
    _handleNotificationNavigation(message.data);
  }

  /// Bildirim tıklanınca yönlendirme yap
  static void _handleNotificationNavigation(Map<String, dynamic> data) {
    debugPrint('Handling notification navigation with data: $data');
    
    final type = data['type'];
    
    if (navigatorKey.currentState != null) {
      switch (type) {
        case 'order_assigned':
          debugPrint('Order assigned notification - navigating to order detail');
          // Sipariş detay sayfasına yönlendir
          final orderId = data['order_id'];
          if (orderId != null) {
            navigatorKey.currentState!.pushNamedAndRemoveUntil(
              '/dashboard', 
              (route) => false,
            );
            // Dashboard yüklendikten sonra sipariş detayına git
            Future.delayed(const Duration(milliseconds: 500), () {
              navigatorKey.currentState!.pushNamed('/order-detail', arguments: {'orderId': orderId});
            });
          } else {
            // Order ID yoksa dashboard'a git
            navigatorKey.currentState!.pushNamedAndRemoveUntil(
              '/dashboard', 
              (route) => false,
            );
          }
          break;
        case 'new_order':
          debugPrint('New order notification - navigating to available orders');
          // Yeni siparişler sayfasına yönlendir
          navigatorKey.currentState!.pushNamed('/available-orders');
          break;
        case 'order_timeout':
          debugPrint('Order timeout notification - navigating to active orders');
          // Aktif siparişler sayfasına yönlendir
          navigatorKey.currentState!.pushNamed('/active-orders');
          break;
        default:
          debugPrint('Unknown notification type: $type');
          // Varsayılan olarak dashboard'a yönlendir
          navigatorKey.currentState!.pushNamedAndRemoveUntil(
            '/dashboard', 
            (route) => false,
          );
          break;
      }
    } else {
      debugPrint('Navigator key is null, cannot navigate');
    }
  }

  /// Local notification göster
  static Future<void> _showLocalNotification({
    required String title,
    required String body,
    Map<String, dynamic>? data,
  }) async {
    try {
      const AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
        'kurye_app_channel',
        'Kurye Bildirimleri',
        channelDescription: 'Sipariş ve teslimat bildirimleri',
        importance: Importance.high,
        priority: Priority.high,
        showWhen: true,
        icon: '@mipmap/ic_launcher',
      );

      const DarwinNotificationDetails iosDetails = DarwinNotificationDetails(
        presentAlert: true,
        presentBadge: true,
        presentSound: true,
      );

      const NotificationDetails platformDetails = NotificationDetails(
        android: androidDetails,
        iOS: iosDetails,
      );

      await _localNotifications.show(
        DateTime.now().millisecondsSinceEpoch.remainder(100000),
        title,
        body,
        platformDetails,
        payload: data != null ? json.encode(data) : null,
      );
    } catch (e) {
      debugPrint('Local notification error: $e');
    }
  }

  /// Notification'a tıklandığında çalışır
  static void _onNotificationTapped(NotificationResponse response) {
    debugPrint('Notification tapped: ${response.payload}');
    
    if (response.payload != null) {
      try {
        final data = json.decode(response.payload!) as Map<String, dynamic>;
        _handleNotificationNavigation(data);
      } catch (e) {
        debugPrint('Notification payload parse error: $e');
      }
    }
  }


  /// Test bildirimi gönder
  static Future<void> sendTestNotification() async {
    await _showLocalNotification(
      title: 'Test Bildirimi',
      body: 'Bu bir test bildirimidir.',
      data: {'type': 'test'},
    );
  }

  /// Public local notification göster
  static Future<void> showLocalNotification({
    required String title,
    required String body,
    Map<String, dynamic>? data,
  }) async {
    await _showLocalNotification(
      title: title,
      body: body,
      data: data,
    );
  }

  /// Belirli bir notification channel'ı oluştur
  static Future<void> createNotificationChannel({
    required String id,
    required String name,
    required String description,
    Importance importance = Importance.high,
  }) async {
    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      'kurye_app_channel',
      'Kurye Bildirimleri',
      description: 'Sipariş ve teslimat bildirimleri',
      importance: Importance.high,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
  }

  /// Tüm bildirimleri temizle
  static Future<void> clearAllNotifications() async {
    await _localNotifications.cancelAll();
  }

  /// Belirli bir bildirimi iptal et
  static Future<void> cancelNotification(int id) async {
    await _localNotifications.cancel(id);
  }

  /// FCM token'ı al
  static String? get fcmToken => _fcmToken;

  /// Bildirim izni durumunu kontrol et
  static Future<bool> hasNotificationPermission() async {
    final settings = await _firebaseMessaging.getNotificationSettings();
    return settings.authorizationStatus == AuthorizationStatus.authorized;
  }

  /// Bildirim ayarlarını aç
  static Future<void> openNotificationSettings() async {
    await openAppSettings();
  }

  /// Service'i temizle
  static void dispose() {
    _isInitialized = false;
    _fcmToken = null;
  }
}
