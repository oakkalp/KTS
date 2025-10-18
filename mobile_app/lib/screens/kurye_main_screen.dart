import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/order_provider.dart';
import '../widgets/kurye_status_widget.dart';
import '../services/notification_service.dart';
import 'home/dashboard_screen.dart';
import 'active_orders_screen.dart';
import 'available_orders_screen.dart';
import 'delivery_history_screen.dart';
import 'earnings_screen.dart';
import 'profile_screen.dart';

class KuryeMainScreen extends StatefulWidget {
  const KuryeMainScreen({super.key});

  @override
  State<KuryeMainScreen> createState() => _KuryeMainScreenState();
}

class _KuryeMainScreenState extends State<KuryeMainScreen> {
  int _currentIndex = 0;
  
  final List<Widget> _screens = [
    const DashboardScreen(),
    const ActiveOrdersScreen(),
    const AvailableOrdersScreen(),
    const DeliveryHistoryScreen(),
    const EarningsScreen(),
    const ProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    _setupNotificationHandling();
  }

  void _setupNotificationHandling() {
    // Geçici olarak notification handling devre dışı
    debugPrint('Notification handling disabled temporarily');
    // NotificationService'e navigation callback'i ekle
    // NotificationService.navigatorKey.currentState?.setState(() {
    //   // Notification geldiğinde bu callback çalışacak
    // });
  }

  void _handleNotificationNavigation(Map<String, dynamic> data) {
    final type = data['type'] as String?;
    
    switch (type) {
      case 'new_order':
        // Yeni siparişler sekmesine git (index 2)
        setState(() {
          _currentIndex = 2;
        });
        break;
      case 'payment_received':
        // Kazançlar sekmesine git (index 4)
        setState(() {
          _currentIndex = 4;
        });
        break;
      default:
        // Dashboard'a git (index 0)
        setState(() {
          _currentIndex = 0;
        });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: BottomNavigationBar(
        type: BottomNavigationBarType.fixed,
        currentIndex: _currentIndex,
        onTap: (index) {
          setState(() {
            _currentIndex = index;
          });
        },
        selectedItemColor: Colors.orange,
        unselectedItemColor: Colors.grey,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.local_shipping),
            label: 'Aktif Siparişler',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.add_shopping_cart),
            label: 'Yeni Siparişler',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.history),
            label: 'Teslimat Geçmişi',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.account_balance_wallet),
            label: 'Kazançlarım',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person),
            label: 'Profil',
          ),
        ],
      ),
    );
  }
}
