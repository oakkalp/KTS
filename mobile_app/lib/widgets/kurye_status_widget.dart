import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/dashboard_model.dart';
import '../providers/order_provider.dart';
import '../providers/location_provider.dart';
import '../config/theme.dart';

class KuryeStatusWidget extends StatelessWidget {
  const KuryeStatusWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<OrderProvider>(
      builder: (context, orderProvider, child) {
        // Dashboard'dan güncel durumu al
        final currentStatus = orderProvider.dashboardData?.kuryeStatus;
        
        if (currentStatus == null) {
          return const Center(
            child: CircularProgressIndicator(),
          );
        }
        
        return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        gradient: _getStatusGradient(currentStatus),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: _getStatusColor(currentStatus).withOpacity(0.3),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Card(
        color: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Row(
            children: [
              // Status ikonu
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(30),
                ),
                child: Center(
                  child: Text(
                    currentStatus.statusIcon,
                    style: const TextStyle(fontSize: 24),
                  ),
                ),
              ),
              
              const SizedBox(width: 16),
              
              // Status bilgileri
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Ana durum
                    Text(
                      currentStatus.statusDisplay,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    
                    const SizedBox(height: 4),
                    
                    // Araç tipi
                    Row(
                      children: [
                        Text(
                          currentStatus.vehicleIcon,
                          style: const TextStyle(fontSize: 16),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          currentStatus.vehicleDisplay,
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.9),
                            fontSize: 14,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                    
                    const SizedBox(height: 4),
                    
                    // Son konum güncellemesi (tıklanabilir)
                    if (currentStatus.lastLocationUpdate != null)
                      GestureDetector(
                        onTap: () => _openNavigation(context),
                        child: Row(
                          children: [
                            Icon(
                              Icons.location_on,
                              size: 14,
                              color: Colors.white.withOpacity(0.7),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              'Son konum: ${_getTimeAgo(currentStatus.lastLocationUpdate!)}',
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.7),
                                fontSize: 12,
                                decoration: TextDecoration.underline,
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      Text(
                        'Konum bilgisi yok',
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.7),
                          fontSize: 12,
                        ),
                      ),
                  ],
                ),
              ),
              
              // Durum değiştirme butonu
              Container(
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: IconButton(
                  onPressed: () => _showStatusDialog(context),
                  icon: const Icon(
                    Icons.settings,
                    color: Colors.white,
                    size: 20,
                  ),
                  tooltip: 'Durum Ayarları',
                ),
              ),
            ],
          ),
        ),
      ),
    );
      },
    );
  }

  Color _getStatusColor(KuryeStatus status) {
    if (!status.isOnline) return AppTheme.errorColor;
    if (!status.isAvailable) return Colors.orange;
    return AppTheme.successColor;
  }

  LinearGradient _getStatusGradient(KuryeStatus status) {
    if (!status.isOnline) {
      return const LinearGradient(
        colors: [Color(0xFFFF5252), Color(0xFFD32F2F)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      );
    }
    if (!status.isAvailable) {
      return const LinearGradient(
        colors: [Color(0xFFFF9800), Color(0xFFF57C00)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      );
    }
    return const LinearGradient(
      colors: [Color(0xFF4CAF50), Color(0xFF388E3C)],
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
    );
  }

  String _getTimeAgo(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inMinutes < 1) {
      return 'Az önce';
    } else if (difference.inMinutes < 60) {
      return '${difference.inMinutes} dk önce';
    } else if (difference.inHours < 24) {
      return '${difference.inHours} sa önce';
    } else {
      return '${difference.inDays} gün önce';
    }
  }

  void _showStatusDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Durum Ayarları'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.circle, color: Colors.green),
              title: const Text('Çevrimiçi ve Müsait'),
              subtitle: const Text('Yeni siparişler alabilirsiniz'),
              onTap: () async {
                Navigator.of(context).pop();
                await _updateStatus(context, isOnline: true, isAvailable: true);
              },
            ),
            ListTile(
              leading: const Icon(Icons.circle, color: Colors.orange),
              title: const Text('Çevrimiçi ama Meşgul'),
              subtitle: const Text('Yeni sipariş almayın'),
              onTap: () async {
                Navigator.of(context).pop();
                await _updateStatus(context, isOnline: true, isAvailable: false);
              },
            ),
            ListTile(
              leading: const Icon(Icons.circle, color: Colors.red),
              title: const Text('Çevrimdışı'),
              subtitle: const Text('Tamamen pasif duruma geçin'),
              onTap: () async {
                Navigator.of(context).pop();
                await _updateStatus(context, isOnline: false, isAvailable: false);
              },
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('İptal'),
          ),
        ],
      ),
    );
  }

  Future<void> _updateStatus(BuildContext context, {required bool isOnline, required bool isAvailable}) async {
    final orderProvider = Provider.of<OrderProvider>(context, listen: false);
    
    try {
      // Kurye durumunu güncelle
      final success = await orderProvider.updateCourierStatus(
        isOnline: isOnline,
        isAvailable: isAvailable,
      );
      
      if (success) {
        // Başarı mesajı göster
        if (context.mounted) {
          String statusText = '';
          if (isOnline && isAvailable) {
            statusText = 'Çevrimiçi ve Müsait';
          } else if (isOnline && !isAvailable) {
            statusText = 'Çevrimiçi ama Meşgul';
          } else {
            statusText = 'Çevrimdışı';
          }
          
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Durum güncellendi: $statusText'),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 2),
            ),
          );
        }
      } else {
        // Hata mesajı göster
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(orderProvider.error ?? 'Durum güncellenemedi'),
              backgroundColor: Colors.red,
              duration: const Duration(seconds: 3),
            ),
          );
        }
      }
    } catch (e) {
      // Hata mesajı göster
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Durum güncellenirken hata oluştu: ${e.toString()}'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  /// Navigasyon uygulamasını aç
  void _openNavigation(BuildContext context) {
    final locationProvider = Provider.of<LocationProvider>(context, listen: false);
    final currentPosition = locationProvider.currentPosition;
    
    if (currentPosition == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Konum bilgisi bulunamadı'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    // Google Maps URL'si oluştur
    final lat = currentPosition.latitude;
    final lng = currentPosition.longitude;
    final googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=$lat,$lng';
    
    // URL'yi aç
    _launchUrl(googleMapsUrl);
  }

  /// URL'yi aç
  Future<void> _launchUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }
}
