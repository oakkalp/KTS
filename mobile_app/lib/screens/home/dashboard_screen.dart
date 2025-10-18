import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'dart:async';
import '../../providers/auth_provider.dart';
import '../../providers/order_provider.dart';
import '../../providers/location_provider.dart';
import '../../widgets/dashboard_stats_widget.dart';
import '../../widgets/active_orders_widget.dart';
import '../../widgets/available_orders_widget.dart';
import '../../widgets/kurye_status_widget.dart';
import '../auth/login_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen>
    with WidgetsBindingObserver {
  
  Timer? _refreshTimer;
  
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadDashboard();
    _startLocationTracking();
    _startAutoRefresh();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _refreshTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    
    switch (state) {
      case AppLifecycleState.resumed:
        // Uygulama ön plana geldi, verileri yenile
        _loadDashboard();
        break;
      case AppLifecycleState.paused:
        // Uygulama arka plana gitti
        break;
      case AppLifecycleState.inactive:
      case AppLifecycleState.detached:
        break;
    }
  }

  Future<void> _loadDashboard() async {
    final orderProvider = Provider.of<OrderProvider>(context, listen: false);
    await orderProvider.loadDashboard();
  }

  Future<void> _startLocationTracking() async {
    final locationProvider = Provider.of<LocationProvider>(context, listen: false);
    await locationProvider.startLocationTracking();
  }

  void _startAutoRefresh() {
    // Her 30 saniyede bir dashboard'ı yenile
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (timer) {
      if (mounted) {
        _loadDashboard();
      }
    });
  }

  Future<void> _handleLogout() async {
    final shouldLogout = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Çıkış Yap'),
        content: const Text('Çıkış yapmak istediğinizden emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Çıkış Yap'),
          ),
        ],
      ),
    );

    if (shouldLogout == true) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final locationProvider = Provider.of<LocationProvider>(context, listen: false);
      
      // Konum takibini durdur
      locationProvider.stopLocationTracking();
      
      // Çıkış yap
      await authProvider.logout();
      
      if (!mounted) return;

      // Login sayfasına yönlendir
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          // Bildirimler
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () {
              // TODO: Bildirimler sayfası
            },
          ),
          // Profil menüsü
          PopupMenuButton<String>(
            onSelected: (value) {
              switch (value) {
                case 'profile':
                  // TODO: Profil sayfası
                  break;
                case 'settings':
                  // TODO: Ayarlar sayfası
                  break;
                case 'logout':
                  _handleLogout();
                  break;
              }
            },
            itemBuilder: (context) => [
              const PopupMenuItem(
                value: 'profile',
                child: ListTile(
                  leading: Icon(Icons.person_outline),
                  title: Text('Profil'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
              const PopupMenuItem(
                value: 'settings',
                child: ListTile(
                  leading: Icon(Icons.settings_outlined),
                  title: Text('Ayarlar'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem(
                value: 'logout',
                child: ListTile(
                  leading: Icon(Icons.logout, color: Colors.red),
                  title: Text('Çıkış Yap', style: TextStyle(color: Colors.red)),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
            ],
            child: Padding(
              padding: const EdgeInsets.all(8.0),
              child: Consumer<AuthProvider>(
                builder: (context, authProvider, child) {
                  return CircleAvatar(
                    backgroundColor: Colors.white,
                    child: Text(
                      authProvider.user?.fullName.isNotEmpty == true
                          ? authProvider.user!.fullName[0].toUpperCase()
                          : authProvider.user?.username[0].toUpperCase() ?? '?',
                      style: TextStyle(
                        color: Theme.of(context).primaryColor,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadDashboard,
        child: Consumer<OrderProvider>(
          builder: (context, orderProvider, child) {
            if (orderProvider.isLoading && orderProvider.dashboardData == null) {
              return const Center(
                child: CircularProgressIndicator(),
              );
            }

            if (orderProvider.error != null) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.error_outline,
                      size: 64,
                      color: Colors.grey[400],
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Hata',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      orderProvider.error!,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Colors.grey[600],
                      ),
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: _loadDashboard,
                      child: const Text('Tekrar Dene'),
                    ),
                  ],
                ),
              );
            }

            final dashboardData = orderProvider.dashboardData;
            if (dashboardData == null) {
              return const Center(
                child: Text('Veri bulunamadı'),
              );
            }

            return SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Kurye durumu
                  const KuryeStatusWidget(),
                  
                  const SizedBox(height: 16),
                  
                  // İstatistikler
                  DashboardStatsWidget(stats: dashboardData.stats),
                  
                  const SizedBox(height: 24),
                  
                  // Aktif siparişler
                  if (dashboardData.activeOrders.isNotEmpty) ...[
                    Text(
                      'Aktif Siparişlerim (${dashboardData.activeOrders.length})',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    ActiveOrdersWidget(orders: dashboardData.activeOrders),
                    const SizedBox(height: 24),
                  ],
                  
                  // Yeni siparişler
                  if (dashboardData.availableOrders.isNotEmpty) ...[
                    Text(
                      'Yeni Siparişler (${dashboardData.availableOrders.length})',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    AvailableOrdersWidget(orders: dashboardData.availableOrders),
                  ],
                  
                  // Eğer hiç sipariş yoksa
                  if (dashboardData.activeOrders.isEmpty && 
                      dashboardData.availableOrders.isEmpty) ...[
                    Center(
                      child: Column(
                        children: [
                          const SizedBox(height: 40),
                          Icon(
                            Icons.inbox_outlined,
                            size: 64,
                            color: Colors.grey[400],
                          ),
                          const SizedBox(height: 16),
                          Text(
                            'Henüz sipariş yok',
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              color: Colors.grey[600],
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Yeni siparişler geldiğinde burada görünecek',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: Colors.grey[500],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            );
          },
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _loadDashboard,
        child: const Icon(Icons.refresh),
      ),
    );
  }
}
