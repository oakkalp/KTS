import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:flutter/foundation.dart';
import '../config/app_config.dart';

// SSL sertifika doğrulamasını devre dışı bırak (sadece development için)
class DevHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) {
    return super.createHttpClient(context)
      ..badCertificateCallback = (X509Certificate cert, String host, int port) => true;
  }
}

class ApiService {
  static String? _authToken;
  static final Map<String, String> _defaultHeaders = {
    'Content-Type': 'application/json; charset=utf-8',
    'Accept': 'application/json',
  };

  /// Auth token'ı ayarla
  static void setAuthToken(String token) {
    _authToken = token;
    print('ApiService: Auth Token set to: $_authToken'); // Debug print
  }

  /// Auth token'ı temizle
  static void clearAuthToken() {
    _authToken = null;
  }

  /// Header'ları hazırla
  static Map<String, String> _getHeaders() {
    final headers = Map<String, String>.from(_defaultHeaders);
    
    if (_authToken != null) {
      headers['Authorization'] = 'Bearer $_authToken';
    }
    
    return headers;
  }

  /// Base URL ile endpoint'i birleştir
  static String _buildUrl(String endpoint) {
    final url = '${AppConfig.baseUrl}$endpoint';
    if (kDebugMode) {
      print('Building URL: $url');
    }
    return url;
  }

  /// HTTP response'unu işle
  static Map<String, dynamic> _handleResponse(http.Response response) {
    if (kDebugMode) {
      print('API Response [${response.statusCode}]: ${response.body}');
    }

    try {
      final Map<String, dynamic> data = json.decode(response.body);
      
      // HTTP status kodu kontrolü
      if (response.statusCode >= 200 && response.statusCode < 300) {
        return data;
      } else {
        // Hata durumu
        return {
          'success': false,
          'error': data['error'] ?? {
            'code': 'HTTP_ERROR',
            'message': 'HTTP ${response.statusCode} hatası'
          }
        };
      }
    } catch (e) {
      return {
        'success': false,
        'error': {
          'code': 'PARSE_ERROR',
          'message': 'Sunucu yanıtı işlenemedi: ${e.toString()}'
        }
      };
    }
  }

  /// GET isteği
  static Future<Map<String, dynamic>> get(String endpoint) async {
    try {
      if (kDebugMode) {
        print('API GET: ${_buildUrl(endpoint)}');
      }

      final response = await http.get(
        Uri.parse(_buildUrl(endpoint)),
        headers: _getHeaders(),
      ).timeout(const Duration(seconds: 30));

      return _handleResponse(response);
    } on SocketException {
      return {
        'success': false,
        'error': {
          'code': 'NETWORK_ERROR',
          'message': 'İnternet bağlantısı yok'
        }
      };
    } on HttpException {
      return {
        'success': false,
        'error': {
          'code': 'HTTP_ERROR',
          'message': 'HTTP hatası oluştu'
        }
      };
    } catch (e) {
      return {
        'success': false,
        'error': {
          'code': 'UNKNOWN_ERROR',
          'message': 'Bilinmeyen hata: ${e.toString()}'
        }
      };
    }
  }

  /// POST isteği
  static Future<Map<String, dynamic>> post(
    String endpoint,
    Map<String, dynamic> data,
  ) async {
    try {
      if (kDebugMode) {
        print('API POST: ${_buildUrl(endpoint)}');
        print('Data: ${json.encode(data)}');
      }

      final response = await http.post(
        Uri.parse(_buildUrl(endpoint)),
        headers: _getHeaders(),
        body: json.encode(data),
      ).timeout(const Duration(seconds: 30));

      return _handleResponse(response);
    } on SocketException {
      return {
        'success': false,
        'error': {
          'code': 'NETWORK_ERROR',
          'message': 'İnternet bağlantısı yok'
        }
      };
    } on HttpException {
      return {
        'success': false,
        'error': {
          'code': 'HTTP_ERROR',
          'message': 'HTTP hatası oluştu'
        }
      };
    } catch (e) {
      return {
        'success': false,
        'error': {
          'code': 'UNKNOWN_ERROR',
          'message': 'Bilinmeyen hata: ${e.toString()}'
        }
      };
    }
  }

  /// PUT isteği
  static Future<Map<String, dynamic>> put(
    String endpoint,
    Map<String, dynamic> data,
  ) async {
    try {
      if (kDebugMode) {
        print('API PUT: ${_buildUrl(endpoint)}');
        print('Data: ${json.encode(data)}');
      }

      final response = await http.put(
        Uri.parse(_buildUrl(endpoint)),
        headers: _getHeaders(),
        body: json.encode(data),
      ).timeout(const Duration(seconds: 30));

      return _handleResponse(response);
    } on SocketException {
      return {
        'success': false,
        'error': {
          'code': 'NETWORK_ERROR',
          'message': 'İnternet bağlantısı yok'
        }
      };
    } on HttpException {
      return {
        'success': false,
        'error': {
          'code': 'HTTP_ERROR',
          'message': 'HTTP hatası oluştu'
        }
      };
    } catch (e) {
      return {
        'success': false,
        'error': {
          'code': 'UNKNOWN_ERROR',
          'message': 'Bilinmeyen hata: ${e.toString()}'
        }
      };
    }
  }

  /// DELETE isteği
  static Future<Map<String, dynamic>> delete(String endpoint) async {
    try {
      if (kDebugMode) {
        print('API DELETE: ${_buildUrl(endpoint)}');
      }

      final response = await http.delete(
        Uri.parse(_buildUrl(endpoint)),
        headers: _getHeaders(),
      ).timeout(const Duration(seconds: 30));

      return _handleResponse(response);
    } on SocketException {
      return {
        'success': false,
        'error': {
          'code': 'NETWORK_ERROR',
          'message': 'İnternet bağlantısı yok'
        }
      };
    } on HttpException {
      return {
        'success': false,
        'error': {
          'code': 'HTTP_ERROR',
          'message': 'HTTP hatası oluştu'
        }
      };
    } catch (e) {
      return {
        'success': false,
        'error': {
          'code': 'UNKNOWN_ERROR',
          'message': 'Bilinmeyen hata: ${e.toString()}'
        }
      };
    }
  }

  /// Login işlemi (özel endpoint)
  static Future<Map<String, dynamic>> login(Map<String, dynamic> credentials) async {
    return await post('/mobile/auth/login.php', credentials);
  }

  /// Dosya upload işlemi
  static Future<Map<String, dynamic>> uploadFile(
    String endpoint,
    String filePath,
    String fieldName,
  ) async {
    try {
      if (kDebugMode) {
        print('API UPLOAD: ${_buildUrl(endpoint)}');
      }

      final request = http.MultipartRequest(
        'POST',
        Uri.parse(_buildUrl(endpoint)),
      );

      // Header'ları ekle
      request.headers.addAll(_getHeaders());

      // Dosyayı ekle
      request.files.add(await http.MultipartFile.fromPath(fieldName, filePath));

      final streamedResponse = await request.send().timeout(const Duration(seconds: 60));
      final response = await http.Response.fromStream(streamedResponse);

      return _handleResponse(response);
    } on SocketException {
      return {
        'success': false,
        'error': {
          'code': 'NETWORK_ERROR',
          'message': 'İnternet bağlantısı yok'
        }
      };
    } catch (e) {
      return {
        'success': false,
        'error': {
          'code': 'UPLOAD_ERROR',
          'message': 'Dosya yükleme hatası: ${e.toString()}'
        }
      };
    }
  }

  /// Bağlantı testi
  static Future<bool> testConnection() async {
    try {
      final response = await get('/mobile/auth/ping');
      return response['success'] == true;
    } catch (e) {
      return false;
    }
  }
}
