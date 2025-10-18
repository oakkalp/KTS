import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import 'dart:async';
import '../services/api_service.dart';

class LocationProvider extends ChangeNotifier {
  Position? _currentPosition;
  bool _isTracking = false;
  bool _isLoading = false;
  String? _error;
  StreamSubscription<Position>? _positionStreamSubscription;
  Timer? _locationUpdateTimer;

  Position? get currentPosition => _currentPosition;
  bool get isTracking => _isTracking;
  bool get isLoading => _isLoading;
  String? get error => _error;
  
  double? get latitude => _currentPosition?.latitude;
  double? get longitude => _currentPosition?.longitude;

  /// Konum izinlerini kontrol et ve iste
  Future<bool> checkAndRequestPermissions() async {
    _setLoading(true);
    
    try {
      // Konum servisi açık mı kontrol et
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        _error = 'Konum servisi kapalı. Lütfen açın.';
        _setLoading(false);
        return false;
      }

      // İzin durumunu kontrol et
      LocationPermission permission = await Geolocator.checkPermission();
      
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          _error = 'Konum izni reddedildi';
          _setLoading(false);
          return false;
        }
      }
      
      if (permission == LocationPermission.deniedForever) {
        _error = 'Konum izni kalıcı olarak reddedildi. Ayarlardan açın.';
        _setLoading(false);
        return false;
      }

      _error = null;
      _setLoading(false);
      return true;
    } catch (e) {
      _error = 'İzin kontrolü hatası: ${e.toString()}';
      _setLoading(false);
      return false;
    }
  }

  /// Mevcut konumu al
  Future<bool> getCurrentLocation() async {
    final hasPermission = await checkAndRequestPermissions();
    if (!hasPermission) return false;

    _setLoading(true);
    
    try {
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
        timeLimit: const Duration(seconds: 10),
      );
      
      _currentPosition = position;
      _error = null;
      _setLoading(false);
      
      // Konumu sunucuya gönder
      await _sendLocationToServer();
      
      return true;
    } catch (e) {
      _error = 'Konum alınamadı: ${e.toString()}';
      _setLoading(false);
      return false;
    }
  }

  /// Konum takibini başlat
  Future<bool> startLocationTracking() async {
    if (_isTracking) return true;
    
    final hasPermission = await checkAndRequestPermissions();
    if (!hasPermission) return false;

    try {
      const LocationSettings locationSettings = LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10, // 10 metre değişim
      );

      _positionStreamSubscription = Geolocator.getPositionStream(
        locationSettings: locationSettings,
      ).listen(
        (Position position) {
          _currentPosition = position;
          notifyListeners();
          
          // Konumu sunucuya gönder (throttle ile)
          if (_locationUpdateTimer == null) {
            _scheduleLocationUpdate();
          }
        },
        onError: (error) {
          _error = 'Konum takip hatası: ${error.toString()}';
          notifyListeners();
        },
      );

      _isTracking = true;
      _error = null;
      notifyListeners();
      
      return true;
    } catch (e) {
      _error = 'Konum takibi başlatılamadı: ${e.toString()}';
      notifyListeners();
      return false;
    }
  }

  /// Konum takibini durdur
  void stopLocationTracking() {
    _positionStreamSubscription?.cancel();
    _positionStreamSubscription = null;
    _locationUpdateTimer?.cancel();
    _locationUpdateTimer = null;
    _isTracking = false;
    notifyListeners();
  }

  /// Konum güncelleme zamanlayıcısı (30 saniyede bir - PHP script ile aynı)
  void _scheduleLocationUpdate() {
    _locationUpdateTimer?.cancel();
    _locationUpdateTimer = Timer(const Duration(seconds: 30), () {
      _sendLocationToServer();
      // Timer bitince yeniden başlat
      _locationUpdateTimer = null;
      if (_isTracking) {
        _scheduleLocationUpdate();
      }
    });
  }

  /// Konumu sunucuya gönder
  Future<void> _sendLocationToServer() async {
    if (_currentPosition == null) return;

    try {
      debugPrint('Konum sunucuya gönderiliyor: ${_currentPosition!.latitude}, ${_currentPosition!.longitude}');
      
      final response = await ApiService.post('/mobile/kurye/update-location.php', {
        'latitude': _currentPosition!.latitude,
        'longitude': _currentPosition!.longitude,
        'accuracy': _currentPosition!.accuracy,
        'speed': _currentPosition!.speed,
        'heading': _currentPosition!.heading,
        'timestamp': DateTime.now().toIso8601String(),
      });

      if (response['success']) {
        debugPrint('Konum başarıyla güncellendi: ${response['data']['updated_at']}');
      } else {
        debugPrint('Konum gönderme hatası: ${response['error']['message']}');
      }
    } catch (e) {
      debugPrint('Konum gönderme hatası: $e');
    }
  }

  /// İki nokta arasındaki mesafeyi hesapla (metre)
  double? calculateDistance(double lat1, double lng1, double lat2, double lng2) {
    try {
      return Geolocator.distanceBetween(lat1, lng1, lat2, lng2);
    } catch (e) {
      return null;
    }
  }

  /// Mevcut konumdan hedefe mesafe
  double? getDistanceToTarget(double targetLat, double targetLng) {
    if (_currentPosition == null) return null;
    
    return calculateDistance(
      _currentPosition!.latitude,
      _currentPosition!.longitude,
      targetLat,
      targetLng,
    );
  }

  /// Test konumu ayarla (geliştirme için)
  void setTestLocation(double lat, double lng) {
    _currentPosition = Position(
      latitude: lat,
      longitude: lng,
      timestamp: DateTime.now(),
      accuracy: 10.0,
      altitude: 0.0,
      altitudeAccuracy: 0.0,
      heading: 0.0,
      headingAccuracy: 0.0, // YENİ EKLENEN SATIR
      speed: 0.0,
      speedAccuracy: 0.0,
    );
    
    _error = null;
    notifyListeners();
    
    // Test konumunu sunucuya gönder
    _sendLocationToServer();
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

  /// Provider'ı temizle
  @override
  void dispose() {
    stopLocationTracking();
    super.dispose();
  }
}
