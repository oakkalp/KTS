import 'dart:async';
import 'dart:convert';
import 'dart:isolate';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:geolocator/geolocator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;

class BackgroundService {
  static const String _isolateName = 'background_service';
  static const String _channelName = 'background_service_channel';
  
  /// Background service'i başlat
  static Future<void> initialize() async {
    final service = FlutterBackgroundService();
    
    // Android için ayarlar
    await service.configure(
      androidConfiguration: AndroidConfiguration(
        onStart: onStart,
        autoStart: true,
        isForegroundMode: true,
        notificationChannelId: 'kurye_background_service',
        initialNotificationTitle: 'Kurye Sistemi',
        initialNotificationContent: 'Konum takibi aktif',
        foregroundServiceNotificationId: 888,
      ),
      iosConfiguration: IosConfiguration(
        autoStart: true,
        onForeground: onStart,
        onBackground: onIosBackground,
      ),
    );
  }
  
  /// Background service'i başlat
  static Future<void> start() async {
    final service = FlutterBackgroundService();
    bool isRunning = await service.isRunning();
    
    if (!isRunning) {
      await service.startService();
    }
  }
  
  /// Background service'i durdur
  static Future<void> stop() async {
    final service = FlutterBackgroundService();
    service.invoke('stop');
  }
  
  /// Background service durumunu kontrol et
  static Future<bool> isRunning() async {
    final service = FlutterBackgroundService();
    return await service.isRunning();
  }
}

/// Android için background service başlatma
@pragma('vm:entry-point')
void onStart(ServiceInstance service) async {
  // Service başlatıldığında çalışacak kod
  DartPluginRegistrant.ensureInitialized();
  
  // Notification göster
  if (service is AndroidServiceInstance) {
    service.setForegroundNotificationInfo(
      title: "Kurye Sistemi",
      content: "Konum takibi aktif",
    );
  }
  
  // Timer ile periyodik konum güncelleme
  Timer.periodic(const Duration(minutes: 1), (timer) async {
    if (service is AndroidServiceInstance) {
      if (await service.isForegroundService()) {
        await _updateLocationInBackground();
      }
    }
  });
}

/// iOS için background service
@pragma('vm:entry-point')
Future<bool> onIosBackground(ServiceInstance service) async {
  WidgetsFlutterBinding.ensureInitialized();
  DartPluginRegistrant.ensureInitialized();
  
  return true;
}

/// Background'da konum güncelleme
Future<void> _updateLocationInBackground() async {
  try {
    // Konum izni kontrolü
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      return;
    }
    
    // Konum servisi kontrolü
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      return;
    }
    
    // Mevcut konumu al
    Position position = await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
    
    // SharedPreferences'dan token'ı al
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('user_token');
    
    if (token == null) {
      return;
    }
    
    // Sunucuya konum gönder
    final response = await http.post(
      Uri.parse('http://10.0.2.2/kuryefullsistem/api/mobile/kurye/update-location.php'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: json.encode({
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy': position.accuracy,
        'speed': position.speed,
        'heading': position.heading,
        'timestamp': DateTime.now().toIso8601String(),
      }),
    );
    
    if (response.statusCode == 200) {
      print('Background location updated: ${position.latitude}, ${position.longitude}');
    }
    
  } catch (e) {
    print('Background location update error: $e');
  }
}
