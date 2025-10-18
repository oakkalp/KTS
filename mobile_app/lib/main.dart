import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:firebase_core/firebase_core.dart';

import 'config/theme.dart';
import 'config/app_config.dart';
import 'providers/auth_provider.dart';
import 'providers/order_provider.dart';
import 'providers/location_provider.dart';
import 'services/notification_service.dart';
import 'services/api_service.dart';
// import 'services/background_service.dart'; // Geçici olarak kaldırıldı
import 'screens/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // SSL sertifika doğrulamasını devre dışı bırak (sadece development için)
  if (kDebugMode) {
    HttpOverrides.global = DevHttpOverrides();
  }
  
  // Firebase'i başlat
  try {
    await Firebase.initializeApp();
    debugPrint('Firebase initialized successfully');
  } catch (e) {
    debugPrint('Firebase initialization error: $e');
  }
  
  // Notification service'i başlat
  try {
    await NotificationService.initialize();
    debugPrint('NotificationService initialized successfully');
  } catch (e) {
    debugPrint('NotificationService initialization error: $e');
  }
  
  // Background service'i başlat (geçici olarak devre dışı - Android 14+ uyumluluk sorunu)
  // try {
  //   await BackgroundService.initialize();
  //   await BackgroundService.start();
  //   debugPrint('BackgroundService initialized successfully');
  // } catch (e) {
  //   debugPrint('BackgroundService initialization error: $e');
  // }
  
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        // Auth Provider
        ChangeNotifierProvider(
          create: (context) => AuthProvider(),
        ),
        
        // Order Provider
        ChangeNotifierProvider(
          create: (context) => OrderProvider(),
        ),
        
        // Location Provider
        ChangeNotifierProvider(
          create: (context) => LocationProvider(),
        ),
      ],
      child: MaterialApp(
        title: AppConfig.appName,
        debugShowCheckedModeBanner: false,
        
        // Theme
        theme: AppTheme.lightTheme,
        darkTheme: AppTheme.darkTheme,
        themeMode: ThemeMode.system,
        
        // Navigation
        navigatorKey: NotificationService.navigatorKey,
        
        // Home
        home: const SplashScreen(),
        
        // Routes
        routes: {
          '/splash': (context) => const SplashScreen(),
          // TODO: Diğer route'ları ekle
        },
        
        // Error handling
        builder: (context, widget) {
          // Error widget wrapper
          ErrorWidget.builder = (FlutterErrorDetails errorDetails) {
            return _ErrorWidget(errorDetails: errorDetails);
          };
          
          return widget!;
        },
      ),
    );
  }
}

class _ErrorWidget extends StatelessWidget {
  final FlutterErrorDetails errorDetails;

  const _ErrorWidget({required this.errorDetails});

  @override
  Widget build(BuildContext context) {
    return Material(
      child: Container(
        color: Colors.red.shade100,
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(
                  Icons.error_outline,
                  color: Colors.red,
                  size: 64,
                ),
                const SizedBox(height: 16),
                const Text(
                  'Bir hata oluştu',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Colors.red,
                  ),
                ),
                const SizedBox(height: 8),
                if (kDebugMode) ...[
                  const Text(
                    'Hata detayları (sadece geliştirme modunda):',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.grey.shade200,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: SingleChildScrollView(
                      child: Text(
                        errorDetails.toString(),
                        style: const TextStyle(
                          fontSize: 12,
                          fontFamily: 'monospace',
                        ),
                      ),
                    ),
                  ),
                ] else ...[
                  const Text(
                    'Uygulama yeniden başlatılıyor...',
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: () {
                    // Uygulamayı yeniden başlat
                    // Bu production'da daha sofistike olmalı
                    if (Navigator.canPop(context)) {
                      Navigator.of(context).pushNamedAndRemoveUntil(
                        '/',
                        (route) => false,
                      );
                    }
                  },
                  child: const Text('Yeniden Başlat'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}