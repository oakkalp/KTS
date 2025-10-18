class AppConfig {
  static const String appName = 'Kurye System';
  static const String appVersion = '1.0.0';
  
  // API Configuration
  static const String baseUrl = 'http://192.168.1.137/kuryefullsistem/api';
  // For emulator testing: 'http://10.0.2.2/kuryefullsistem/api'
  // For localhost testing: 'http://localhost/kuryefullsistem/api'
  // For physical device: 'http://[YOUR_IP]/kuryefullsistem/api'
  // For production: 'https://[YOUR_DOMAIN]/kuryefullsistem/api'
  
  // API Endpoints
  static const String loginEndpoint = '/mobile/auth/login.php';
  static const String dashboardEndpoint = '/mobile/kurye/dashboard.php';
  static const String updateLocationEndpoint = '/mobile/kurye/update-location.php';
  static const String acceptOrderEndpoint = '/mobile/kurye/accept-order.php';
  static const String updateOrderStatusEndpoint = '/mobile/kurye/update-order-status.php';
  static const String updateTokenEndpoint = '/mobile/notification/update-token.php';
  
  // Location Configuration
  static const int locationUpdateIntervalSeconds = 30;
  static const double locationAccuracyThreshold = 100.0; // meters
  
  // App Settings
  static const int maxRetryAttempts = 3;
  static const int timeoutSeconds = 30;
  static const bool enableLogging = true;
  
  // Storage Keys
  static const String authTokenKey = 'auth_token';
  static const String userDataKey = 'user_data';
  static const String isOnlineKey = 'is_online';
  static const String fcmTokenKey = 'fcm_token';
  
  // Notification Settings
  static const String notificationChannelId = 'kurye_notifications';
  static const String notificationChannelName = 'Kurye Bildirimleri';
  static const String notificationChannelDescription = 'Sipariş ve sistem bildirimleri';
  
  // Map Configuration
  static const double defaultLatitude = 41.0082;
  static const double defaultLongitude = 28.9784;
  static const double defaultZoom = 15.0;
  
  // UI Constants
  static const double borderRadius = 12.0;
  static const double cardElevation = 4.0;
  static const double buttonHeight = 48.0;
  
  // Colors
  static const int primaryColorValue = 0xFFFFC107;
  static const int secondaryColorValue = 0xFF03DAC6;
  static const int errorColorValue = 0xFFB00020;
  static const int successColorValue = 0xFF4CAF50;
  
  // Order Status Colors
  static const Map<String, int> orderStatusColors = {
    'pending': 0xFFFFC107,      // Amber
    'accepted': 0xFF2196F3,     // Blue
    'preparing': 0xFF9C27B0,    // Purple
    'ready': 0xFF607D8B,        // Blue Grey
    'picked_up': 0xFF795548,    // Brown
    'delivering': 0xFFFF5722,   // Deep Orange
    'delivered': 0xFF4CAF50,    // Green
    'cancelled': 0xFFF44336,    // Red
  };
  
  // Order Status Texts
  static const Map<String, String> orderStatusTexts = {
    'pending': 'Bekliyor',
    'accepted': 'Kabul Edildi',
    'preparing': 'Hazırlanıyor',
    'ready': 'Hazır',
    'picked_up': 'Alındı',
    'delivering': 'Yolda',
    'delivered': 'Teslim Edildi',
    'cancelled': 'İptal Edildi',
  };
  
  // Vehicle Types
  static const Map<String, String> vehicleTypes = {
    'motosiklet': 'Motosiklet',
    'bisiklet': 'Bisiklet',
    'araba': 'Araba',
    'yürüyerek': 'Yürüyerek',
  };
  
  // Priority Types
  static const Map<String, String> priorityTypes = {
    'normal': 'Normal',
    'urgent': 'Acil',
    'express': 'Ekspres',
  };
  
  // Error Messages
  static const String networkErrorMessage = 'İnternet bağlantınızı kontrol edin';
  static const String serverErrorMessage = 'Sunucu hatası. Lütfen tekrar deneyin';
  static const String authErrorMessage = 'Oturum süreniz dolmuş. Lütfen tekrar giriş yapın';
  static const String locationErrorMessage = 'Konum bilgisi alınamadı';
  static const String permissionErrorMessage = 'İzin verilmedi';
  
  // Success Messages
  static const String loginSuccessMessage = 'Başarıyla giriş yapıldı';
  static const String locationUpdatedMessage = 'Konum güncellendi';
  static const String statusUpdatedMessage = 'Durum güncellendi';
  static const String orderAcceptedMessage = 'Sipariş kabul edildi';
  static const String orderStatusUpdatedMessage = 'Sipariş durumu güncellendi';
}

class ApiEndpoints {
  static String get login => AppConfig.baseUrl + AppConfig.loginEndpoint;
  static String get dashboard => AppConfig.baseUrl + AppConfig.dashboardEndpoint;
  static String get updateLocation => AppConfig.baseUrl + AppConfig.updateLocationEndpoint;
  static String get acceptOrder => AppConfig.baseUrl + AppConfig.acceptOrderEndpoint;
  static String get updateOrderStatus => AppConfig.baseUrl + AppConfig.updateOrderStatusEndpoint;
  static String get updateToken => AppConfig.baseUrl + AppConfig.updateTokenEndpoint;
}
