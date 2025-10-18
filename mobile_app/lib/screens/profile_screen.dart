import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:permission_handler/permission_handler.dart';
import '../providers/auth_provider.dart';
import '../providers/order_provider.dart';
import '../config/theme.dart';
import '../widgets/kurye_status_widget.dart';
import '../services/notification_service.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profil'),
        backgroundColor: Colors.orange,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _handleLogout,
          ),
        ],
      ),
      body: Consumer2<AuthProvider, OrderProvider>(
        builder: (context, authProvider, orderProvider, child) {
          final user = authProvider.user;
          final stats = orderProvider.dashboardData?.stats;
          
          if (user == null) {
            return const Center(
              child: CircularProgressIndicator(),
            );
          }

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Profil kartı
              Card(
                elevation: 4,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      // Avatar
                      CircleAvatar(
                        radius: 40,
                        backgroundColor: Colors.orange,
                        child: Text(
                          user.fullName.isNotEmpty ? user.fullName[0].toUpperCase() : 'K',
                          style: const TextStyle(
                            fontSize: 32,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      
                      // Kullanıcı bilgileri
                      Text(
                        user.fullName,
                        style: const TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        user.phone,
                        style: TextStyle(
                          fontSize: 16,
                          color: Colors.grey[600],
                        ),
                      ),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.orange.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          'Kurye #${user.kuryeId ?? user.id}',
                          style: const TextStyle(
                            color: Colors.orange,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              
              const SizedBox(height: 16),
              
              // Kurye durumu
              const KuryeStatusWidget(),
              
              const SizedBox(height: 16),
              
              // İstatistikler
              if (stats != null) ...[
                Card(
                  elevation: 2,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'İstatistikler',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 16),
                        
                        // Toplam teslimat
                        _buildStatRow(
                          Icons.local_shipping,
                          'Toplam Teslimat',
                          '${stats.totalDeliveries}',
                          Colors.blue,
                        ),
                        
                        const SizedBox(height: 12),
                        
                        // Toplam kazanç
                        _buildStatRow(
                          Icons.account_balance_wallet,
                          'Toplam Kazanç',
                          '${stats.totalEarnings.toStringAsFixed(2)} ₺',
                          Colors.green,
                        ),
                        
                        const SizedBox(height: 12),
                        
                        // Bugünkü teslimat
                        _buildStatRow(
                          Icons.today,
                          'Bugünkü Teslimat',
                          '${stats.todayDeliveries}',
                          Colors.orange,
                        ),
                        
                        const SizedBox(height: 12),
                        
                        // Bugünkü kazanç
                        _buildStatRow(
                          Icons.trending_up,
                          'Bugünkü Kazanç',
                          '${stats.todayGrossEarnings.toStringAsFixed(2)} ₺',
                          Colors.purple,
                        ),
                      ],
                    ),
                  ),
                ),
                
                const SizedBox(height: 16),
              ],
              
              // Ayarlar
              Card(
                elevation: 2,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  children: [
                    _buildMenuTile(
                      Icons.notifications,
                      'Bildirimler',
                      'Bildirim ayarları',
                      () async {
                        final hasPermission = await NotificationService.hasNotificationPermission();
                        if (hasPermission) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Bildirim izni verilmiş'),
                              backgroundColor: Colors.green,
                            ),
                          );
                        } else {
                          await NotificationService.openNotificationSettings();
                        }
                      },
                    ),
                    const Divider(height: 1),
                    _buildMenuTile(
                      Icons.location_on,
                      'Konum Ayarları',
                      'Konum izinleri',
                      () async {
                        final status = await Permission.location.status;
                        if (status.isGranted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Konum izni verilmiş'),
                              backgroundColor: Colors.green,
                            ),
                          );
                        } else {
                          await Permission.location.request();
                        }
                      },
                    ),
                    const Divider(height: 1),
                    _buildMenuTile(
                      Icons.help_outline,
                      'Yardım',
                      'Sık sorulan sorular',
                      () {
                        showDialog(
                          context: context,
                          builder: (context) => AlertDialog(
                            title: const Text('Yardım'),
                            content: const Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Sık Sorulan Sorular:', style: TextStyle(fontWeight: FontWeight.bold)),
                                SizedBox(height: 8),
                                Text('• Sipariş nasıl kabul edilir?'),
                                Text('• Konum güncellemesi nasıl yapılır?'),
                                Text('• Bildirimler gelmiyor ne yapmalıyım?'),
                                Text('• Kazançlarım nasıl hesaplanır?'),
                                SizedBox(height: 8),
                                Text('Daha fazla yardım için admin ile iletişime geçin.'),
                              ],
                            ),
                            actions: [
                              TextButton(
                                onPressed: () => Navigator.of(context).pop(),
                                child: const Text('Tamam'),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1),
                    _buildMenuTile(
                      Icons.info_outline,
                      'Hakkında',
                      'Uygulama bilgileri',
                      () {
                        showDialog(
                          context: context,
                          builder: (context) => AlertDialog(
                            title: const Text('Hakkında'),
                            content: const Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Kurye Full System', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                                SizedBox(height: 8),
                                Text('Versiyon: 1.0.0'),
                                Text('Geliştirici: Kurye Team'),
                                SizedBox(height: 8),
                                Text('Bu uygulama kuryelerin sipariş yönetimi ve teslimat süreçlerini kolaylaştırmak için geliştirilmiştir.'),
                              ],
                            ),
                            actions: [
                              TextButton(
                                onPressed: () => Navigator.of(context).pop(),
                                child: const Text('Tamam'),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1),
                    _buildMenuTile(
                      Icons.notifications_active,
                      'Test Bildirimi',
                      'Bildirim sistemini test et',
                      () async {
                        final orderProvider = Provider.of<OrderProvider>(context, listen: false);
                        final success = await orderProvider.sendTestNotification();
                        
                        if (success) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Test bildirimi gönderildi!'),
                              backgroundColor: Colors.green,
                            ),
                          );
                        } else {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(orderProvider.error ?? 'Test bildirimi gönderilemedi'),
                              backgroundColor: Colors.red,
                            ),
                          );
                        }
                      },
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildStatRow(IconData icon, String title, String value, Color color) {
    return Row(
      children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
      ],
    );
  }

  Widget _buildMenuTile(IconData icon, String title, String subtitle, VoidCallback onTap) {
    return ListTile(
      leading: Icon(icon, color: Colors.orange),
      title: Text(title),
      subtitle: Text(subtitle),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
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
          ElevatedButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
            ),
            child: const Text('Çıkış Yap'),
          ),
        ],
      ),
    );

    if (shouldLogout == true) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      
      // Loading göster
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(),
        ),
      );
      
      try {
        await authProvider.logout();
        
        // Loading'i kapat
        if (mounted) {
          Navigator.of(context).pop();
        }
        
        // Login sayfasına yönlendir - tüm stack'i temizle
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil('/login', (route) => false);
        }
      } catch (e) {
        // Loading'i kapat
        if (mounted) {
          Navigator.of(context).pop();
        }
        
        // Hata mesajı göster
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Çıkış yapılırken hata oluştu: ${e.toString()}'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    }
  }
}
