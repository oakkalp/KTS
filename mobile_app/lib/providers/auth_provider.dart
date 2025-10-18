import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';
import '../services/notification_service.dart';
import '../models/user_model.dart';

class AuthProvider extends ChangeNotifier {
  User? _user;
  String? _token;
  bool _isLoading = false;
  String? _error;

  User? get user => _user;
  String? get token => _token;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isLoggedIn => _user != null && _token != null;

  AuthProvider() {
    _loadUserFromStorage();
  }

  /// Kullanıcı giriş işlemi
  Future<bool> login(String username, String password) async {
    print('AuthProvider: login method called'); // Unconditional print
    _setLoading(true);
    _error = null;

    try {
      final deviceInfo = await _getDeviceInfo();
      
      final response = await ApiService.login({
        'username': username,
        'password': password,
        'device_info': deviceInfo,
      });
      print('Login API Full Response: $response'); // Unconditional print
      print('Login API Success Status: ${response['success']}'); // Unconditional print

      if (response['success']) {
        _token = response['token'];
        if (kDebugMode) { print('Auth Token received: $_token'); }
        _user = User.fromJson(response['user']);
        
        await _saveUserToStorage();
        
        // API service'e token'ı ayarla
        ApiService.setAuthToken(_token!);
        
        // FCM token'ı sunucuya gönder
        try {
          await NotificationService.sendTokenToServer();
          
          // Bekleyen FCM token varsa onu da gönder
          final prefs = await SharedPreferences.getInstance();
          final pendingToken = prefs.getString('fcm_token_pending');
          if (pendingToken != null) {
            if (kDebugMode) print('Sending pending FCM token: $pendingToken');
            await NotificationService.sendTokenToServer();
          }
        } catch (e) {
          if (kDebugMode) print('FCM token send error: $e');
        }
        
        _setLoading(false);
        return true;
      } else {
        _error = response['error']['message'] ?? 'Giriş yapılamadı';
        _setLoading(false);
        return false;
      }
    } catch (e) {
      _error = 'Bağlantı hatası: ${e.toString()}';
      _setLoading(false);
      return false;
    }
  }

  /// Kullanıcı çıkış işlemi
  Future<void> logout() async {
    _setLoading(true);
    _error = null;
    
    try {
      _user = null;
      _token = null;
      
      // Local storage'ı temizle
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('user_token');
      await prefs.remove('user_data');
      
      // API service'den token'ı kaldır
      ApiService.clearAuthToken();
      
      _setLoading(false);
      notifyListeners();
    } catch (e) {
      _error = 'Çıkış yapılırken hata oluştu: ${e.toString()}';
      _setLoading(false);
      notifyListeners();
    }
  }

  /// Kullanıcı bilgilerini güncelle
  void updateUser(User user) {
    _user = user;
    _saveUserToStorage();
    notifyListeners();
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

  /// Kullanıcı verilerini local storage'dan yükle
  Future<void> _loadUserFromStorage() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('user_token');
      final userData = prefs.getString('user_data');
      
      if (token != null && userData != null) {
        _token = token;
        _user = User.fromJson(Map<String, dynamic>.from(
          jsonDecode(userData)
        ));
        
        // API service'e token'ı ayarla
        ApiService.setAuthToken(token);
        
        // Token geçerliliğini kontrol et
        final isValid = await _validateToken();
        if (!isValid) {
          await logout();
        }
      }
    } catch (e) {
      debugPrint('Storage yükleme hatası: $e');
      await logout();
    }
    
    notifyListeners();
  }

  /// Kullanıcı verilerini local storage'a kaydet
  Future<void> _saveUserToStorage() async {
    if (_user != null && _token != null) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('user_token', _token!);
      await prefs.setString('user_data', jsonEncode(_user!.toJson()));
    }
  }

  /// Token geçerliliğini kontrol et
  Future<bool> _validateToken() async {
    try {
      // Basit bir API çağrısı yaparak token'ı test et
      final response = await ApiService.get('/mobile/kurye/dashboard.php');
      return response['success'] == true;
    } catch (e) {
      return false;
    }
  }

  /// Cihaz bilgilerini al
  Future<Map<String, dynamic>> _getDeviceInfo() async {
    // TODO: Device info paketini kullanarak gerçek cihaz bilgilerini al
    return {
      'device_id': 'flutter_test_device',
      'device_name': 'Flutter Test Device',
      'platform': 'android',
      'app_version': '1.0.0',
      'fcm_token': '', // Firebase token buraya gelecek
    };
  }
}
