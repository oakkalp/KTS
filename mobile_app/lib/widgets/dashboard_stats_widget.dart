import 'package:flutter/material.dart';
import '../models/dashboard_model.dart';
import '../config/theme.dart';

class DashboardStatsWidget extends StatelessWidget {
  final DashboardStats stats;

  const DashboardStatsWidget({
    super.key,
    required this.stats,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Üst satır - Ana istatistikler
        Row(
          children: [
            Expanded(
              child: _StatCard(
                title: 'Bugünkü Teslimat',
                value: '${stats.todayDeliveries}',
                subtitle: 'Adet',
                icon: Icons.local_shipping,
                color: AppTheme.primaryColor,
                gradient: AppTheme.primaryGradient,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _StatCard(
                title: 'Bugünkü Kazanç',
                value: '${stats.todayEarnings.toStringAsFixed(0)} ₺',
                subtitle: stats.todayDeliveries > 0 
                    ? 'Ort: ${stats.averageEarningPerDelivery}'
                    : 'Net kazanç',
                icon: Icons.account_balance_wallet,
                color: AppTheme.successColor,
                gradient: AppTheme.successGradient,
              ),
            ),
          ],
        ),
        
        const SizedBox(height: 12),
        
        // Alt satır - Aktif siparişler ve değerlendirme
        Row(
          children: [
            Expanded(
              child: _StatCard(
                title: 'Aktif Sipariş',
                value: '${stats.activeOrders}',
                subtitle: stats.activeOrders > 0 ? 'Devam ediyor' : 'Yok',
                icon: Icons.pending_actions,
                color: stats.activeOrders > 0 ? Colors.orange : Colors.grey,
                gradient: stats.activeOrders > 0 
                    ? const LinearGradient(
                        colors: [Colors.orange, Colors.deepOrange],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      )
                    : const LinearGradient(
                        colors: [Colors.grey, Colors.grey],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _StatCard(
                title: 'Değerlendirme',
                value: stats.rating != null 
                    ? stats.rating!.toStringAsFixed(1)
                    : 'Yeni',
                subtitle: stats.rating != null ? '⭐ Puan' : 'Henüz yok',
                icon: Icons.star,
                color: stats.rating != null && stats.rating! >= 4.0
                    ? Colors.amber
                    : Colors.grey,
                gradient: stats.rating != null && stats.rating! >= 4.0
                    ? const LinearGradient(
                        colors: [Colors.amber, Colors.orange],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      )
                    : const LinearGradient(
                        colors: [Colors.grey, Colors.grey],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  final String title;
  final String value;
  final String subtitle;
  final IconData icon;
  final Color color;
  final LinearGradient gradient;

  const _StatCard({
    required this.title,
    required this.value,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.gradient,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: gradient,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.3),
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
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // İkon ve başlık
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Icon(
                    icon,
                    color: Colors.white.withOpacity(0.8),
                    size: 20,
                  ),
                ],
              ),
              
              const SizedBox(height: 8),
              
              // Ana değer
              Text(
                value,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.bold,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              
              const SizedBox(height: 4),
              
              // Alt açıklama
              Text(
                subtitle,
                style: TextStyle(
                  color: Colors.white.withOpacity(0.8),
                  fontSize: 10,
                  fontWeight: FontWeight.w400,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
