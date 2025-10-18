import 'package:flutter/material.dart';
import 'dart:async';
import '../services/api_service.dart';
import '../services/notification_service.dart';
import '../models/order_model.dart';
import '../models/dashboard_model.dart';

class OrderProvider extends ChangeNotifier {
  DashboardData? _dashboardData;
  List<Order> _activeOrders = [];
  List<Order> _availableOrders = [];
  bool _isLoading = false;
  String? _error;
  Timer? _timeoutCheckTimer;

  DashboardData? get dashboardData => _dashboardData;
  List<Order> get activeOrders => _activeOrders;
  List<Order> get availableOrders => _availableOrders;
  bool get isLoading => _isLoading;
  String? get error => _error;

  /// Dashboard verilerini yükle
  Future<void> loadDashboard() async {
    _setLoading(true);
    _error = null;

    try {
      final response = await ApiService.get('/mobile/kurye/dashboard.php');
      
      if (response['success']) {
        _dashboardData = DashboardData.fromJson(response['data']);
        _activeOrders = _dashboardData!.activeOrders;
        _availableOrders = _dashboardData!.availableOrders;
        
        // Otomatik durum kontrolü (sadece sipariş durumu değiştiğinde)
        // _checkAndUpdateCourierStatus();
        
        // Timeout kontrolü başlat
        _startTimeoutCheck();
      } else {
        _error = response['error']['message'] ?? 'Dashboard yüklenemedi';
      }
    } catch (e) {
      _error = 'Bağlantı hatası: ${e.toString()}';
    }

    _setLoading(false);
  }

  /// Kurye durumunu otomatik kontrol et ve güncelle
  void _checkAndUpdateCourierStatus() {
    if (_dashboardData?.kuryeStatus == null) return;
    
    const busyThreshold = 3; // Meşgul olma eşiği
    const autoAvailableThreshold = 2; // Otomatik müsait olma eşiği
    
    final activeOrdersCount = _activeOrders.length;
    final currentStatus = _dashboardData!.kuryeStatus;
    
    // Sadece sipariş sayısına göre otomatik durum değişimi
    // Manuel durum değişikliklerini override etme
    
    // Meşgul eşiğine ulaştıysa meşgul yap (sadece müsaitken)
    if (activeOrdersCount >= busyThreshold && currentStatus.isAvailable) {
      _updateCourierStatusSilently(isOnline: true, isAvailable: false);
    }
    // Otomatik müsait eşiğinin altındaysa müsait yap (sadece meşgulken)
    else if (activeOrdersCount <= autoAvailableThreshold && !currentStatus.isAvailable) {
      _updateCourierStatusSilently(isOnline: true, isAvailable: true);
    }
  }
  
  /// Kurye durumunu sessizce güncelle (UI'da loading göstermeden)
  Future<void> _updateCourierStatusSilently({
    bool? isOnline,
    bool? isAvailable,
  }) async {
    try {
      final requestData = <String, dynamic>{};
      if (isOnline != null) requestData['is_online'] = isOnline;
      if (isAvailable != null) requestData['is_available'] = isAvailable;

      await ApiService.post('/mobile/kurye/update-status.php', requestData);
      
      // Dashboard'ı sessizce yenile
      await _refreshDashboard();
    } catch (e) {
      // Sessizce hata yok say
    }
  }

  /// Dashboard'ı yenile (loading durumunu değiştirmeden)
  Future<void> _refreshDashboard() async {
    try {
      final response = await ApiService.get('/mobile/kurye/dashboard.php');
      
      if (response['success']) {
        _dashboardData = DashboardData.fromJson(response['data']);
        _activeOrders = _dashboardData!.activeOrders;
        _availableOrders = _dashboardData!.availableOrders;
        
        // Otomatik durum kontrolü (sadece sipariş durumu değiştiğinde)
        // _checkAndUpdateCourierStatus();
      }
    } catch (e) {
      // Hata durumunda sessizce devam et
    }
  }

  /// Maksimum sipariş sayısına ulaşılıp ulaşılmadığını kontrol et
  bool get canAcceptMoreOrders {
    const maxOrdersPerCourier = 5; // Maksimum sipariş sayısı 5'e çıkarıldı
    return _activeOrders.length < maxOrdersPerCourier;
  }

  /// Siparişi kabul et
  Future<bool> acceptOrder(int orderId, {double? lat, double? lng}) async {
    _setLoading(true);
    _error = null;

    // Maksimum sipariş sayısı kontrolü
    if (!canAcceptMoreOrders) {
      _error = 'Maksimum sipariş sayısına ulaştınız (${_activeOrders.length} sipariş). Önce mevcut siparişlerinizi tamamlayın.';
      _setLoading(false);
      return false;
    }

    try {
      final requestData = {
        'order_id': orderId,
        if (lat != null && lng != null)
          'current_location': {
            'latitude': lat,
            'longitude': lng,
          },
      };

      final response = await ApiService.post('/mobile/kurye/accept-order.php', requestData);
      
      if (response['success']) {
        // Siparişi available'dan kaldır ve active'e ekle
        final acceptedOrder = Order.fromJson(response['order']);
        _availableOrders.removeWhere((order) => order.id == orderId);
        _activeOrders.add(acceptedOrder);
        
        // Dashboard'ı yenile (loading durumunu koruyarak)
        await _refreshDashboard();
        
        _setLoading(false);
        return true;
      } else {
        // Hata durumunda da siparişi available'dan kaldır (başka kurye kabul etmiş olabilir)
        _availableOrders.removeWhere((order) => order.id == orderId);
        
        _error = response['error']['message'] ?? 'Sipariş kabul edilemedi';
        _setLoading(false);
        return false;
      }
    } catch (e) {
      _error = 'Bağlantı hatası: ${e.toString()}';
      _setLoading(false);
      return false;
    }
  }

  /// Sipariş durumunu güncelle
  Future<bool> updateOrderStatus(int orderId, String status, {
    double? lat,
    double? lng,
    String? notes,
    String? photo,
  }) async {
    _setLoading(true);
    _error = null;

    try {
      final requestData = {
        'order_id': orderId,
        'status': status,
        if (lat != null && lng != null)
          'location': {
            'latitude': lat,
            'longitude': lng,
          },
        if (notes != null) 'notes': notes,
        if (photo != null) 'photo': photo,
      };

      final response = await ApiService.post('/mobile/kurye/update-order-status.php', requestData);
      
      if (response['success']) {
        // Local listeyi güncelle
        final orderIndex = _activeOrders.indexWhere((order) => order.id == orderId);
        if (orderIndex != -1) {
          _activeOrders[orderIndex] = _activeOrders[orderIndex].copyWith(
            status: status,
          );
          
          // Eğer sipariş tamamlandı veya iptal edildiyse aktif listeden kaldır
          if (status == 'delivered' || status == 'cancelled') {
            _activeOrders.removeAt(orderIndex);
          }
        }
        
        // Dashboard'ı yenile (loading durumunu koruyarak)
        await _refreshDashboard();
        
        _setLoading(false);
        return true;
      } else {
        _error = response['error']['message'] ?? 'Sipariş durumu güncellenemedi';
        _setLoading(false);
        return false;
      }
    } catch (e) {
      _error = 'Bağlantı hatası: ${e.toString()}';
      _setLoading(false);
      return false;
    }
  }

  /// Sipariş detayını al
  Future<Order?> getOrderDetail(int orderId) async {
    try {
      final response = await ApiService.get('/mobile/kurye/order/$orderId.php');
      
      if (response['success']) {
        return Order.fromJson(response['order']);
      }
      return null;
    } catch (e) {
      _error = 'Sipariş detayı yüklenemedi: ${e.toString()}';
      return null;
    }
  }

  /// Hata mesajını temizle
  void clearError() {
    _error = null;
    notifyListeners();
  }

  /// Loading durumunu ayarla
  void _setLoading(bool loading) {
    _isLoading = loading;
    notifyListeners();
  }

  /// Verileri temizle
  void clear() {
    _dashboardData = null;
    _activeOrders = [];
    _availableOrders = [];
    _error = null;
    _isLoading = false;
    notifyListeners();
  }

  /// Belirli bir siparişi aktif listeden bul
  Order? findActiveOrder(int orderId) {
    try {
      return _activeOrders.firstWhere((order) => order.id == orderId);
    } catch (e) {
      return null;
    }
  }

  /// Belirli bir siparişi mevcut listeden bul
  Order? findAvailableOrder(int orderId) {
    try {
      return _availableOrders.firstWhere((order) => order.id == orderId);
    } catch (e) {
      return null;
    }
  }

  /// Kurye durumunu güncelle
  Future<bool> updateCourierStatus({
    bool? isOnline,
    bool? isAvailable,
  }) async {
    _setLoading(true);
    _error = null;

    try {
      final requestData = <String, dynamic>{};
      if (isOnline != null) requestData['is_online'] = isOnline;
      if (isAvailable != null) requestData['is_available'] = isAvailable;

      final response = await ApiService.post('/mobile/kurye/update-status.php', requestData);
      
      if (response['success']) {
        _setLoading(false);
        return true;
      } else {
        _error = response['error']['message'] ?? 'Durum güncellenemedi';
        _setLoading(false);
        return false;
      }
    } catch (e) {
      _error = 'Bağlantı hatası: ${e.toString()}';
      _setLoading(false);
      return false;
    }
  }

  /// Teslimat geçmişini yükle
  Future<List<Order>?> loadDeliveryHistory({
    int page = 1,
    int limit = 20,
    String? dateFrom,
    String? dateTo,
    String? status,
  }) async {
    _setLoading(true);
    _error = null;

    try {
      final queryParams = <String, String>{
        'page': page.toString(),
        'limit': limit.toString(),
      };
      
      if (dateFrom != null) queryParams['date_from'] = dateFrom;
      if (dateTo != null) queryParams['date_to'] = dateTo;
      if (status != null) queryParams['status'] = status;

      final queryString = queryParams.entries
          .map((e) => '${e.key}=${Uri.encodeComponent(e.value)}')
          .join('&');

      final response = await ApiService.get('/mobile/kurye/delivery-history.php?$queryString');
      
      if (response['success']) {
        final ordersData = response['data']['orders'] as List;
        final orders = ordersData.map((json) => Order.fromJson(json)).toList();
        
        _setLoading(false);
        return orders;
      } else {
        _error = response['error']['message'] ?? 'Teslimat geçmişi yüklenemedi';
        _setLoading(false);
        return null;
      }
    } catch (e) {
      _error = 'Bağlantı hatası: ${e.toString()}';
      _setLoading(false);
      return null;
    }
  }

  /// Kazanç bilgilerini yükle
  Future<Map<String, dynamic>?> loadEarnings() async {
    _setLoading(true);
    _error = null;

    try {
      final response = await ApiService.get('/mobile/kurye/earnings.php');
      
      if (response['success']) {
        _setLoading(false);
        return response['data'];
      } else {
        _error = response['error']['message'] ?? 'Kazanç bilgileri yüklenemedi';
        _setLoading(false);
        return null;
      }
    } catch (e) {
      _error = 'Bağlantı hatası: ${e.toString()}';
      _setLoading(false);
      return null;
    }
  }

  /// Test bildirimi gönder
  Future<bool> sendTestNotification() async {
    _setLoading(true);
    _error = null;

    try {
      final response = await ApiService.post('/mobile/kurye/test-notification.php', {});
      
      if (response['success']) {
        _setLoading(false);
        return true;
      } else {
        _error = response['error']['message'] ?? 'Test bildirimi gönderilemedi';
        _setLoading(false);
        return false;
      }
    } catch (e) {
      _error = 'Bağlantı hatası: ${e.toString()}';
      _setLoading(false);
      return false;
    }
  }

  /// Timeout kontrolü başlat
  void _startTimeoutCheck() {
    _timeoutCheckTimer?.cancel();
    _timeoutCheckTimer = Timer.periodic(const Duration(minutes: 1), (timer) {
      _checkOrderTimeouts();
    });
  }

  /// Sipariş timeout'larını kontrol et
  void _checkOrderTimeouts() {
    final now = DateTime.now();
    
    for (final order in _activeOrders) {
      if (order.estimatedReadyMinutes != null && order.isPreparationTimeExpired) {
        // Süre dolmuş sipariş için bildirim gönder
        _sendTimeoutNotification(order);
      }
    }
  }

  /// Timeout bildirimi gönder
  void _sendTimeoutNotification(Order order) {
    // Local notification gönder
    NotificationService.showLocalNotification(
      title: 'Süre Doldu!',
      body: 'Sipariş #${order.orderNumber} - ${order.restaurant.name} hazır olmalıydı.',
      data: {
        'type': 'timeout',
        'order_id': order.id.toString(),
        'order_number': order.orderNumber,
      },
    );
  }

  /// Bildirim sonrası dashboard'ı yenile
  Future<void> refreshAfterNotification() async {
    debugPrint('Refreshing dashboard after notification');
    await loadDashboard();
  }

  /// Provider'ı temizle
  @override
  void dispose() {
    _timeoutCheckTimer?.cancel();
    super.dispose();
  }
}
