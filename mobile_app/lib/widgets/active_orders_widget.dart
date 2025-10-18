import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/order_model.dart';
import '../providers/order_provider.dart';
import '../config/theme.dart';
import '../config/app_config.dart';

class ActiveOrdersWidget extends StatelessWidget {
  final List<Order> orders;

  const ActiveOrdersWidget({
    super.key,
    required this.orders,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: orders.map((order) => _ActiveOrderCard(order: order)).toList(),
    );
  }
}

class _ActiveOrderCard extends StatelessWidget {
  final Order order;

  const _ActiveOrderCard({required this.order});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: AppTheme.getStatusColor(order.status).withOpacity(0.2),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Card(
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(
            color: AppTheme.getStatusColor(order.status).withOpacity(0.3),
            width: 1,
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Üst kısım - Sipariş no, durum, tutar
              Row(
                children: [
                  // Sipariş numarası
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: AppTheme.primaryColor.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      '#${order.orderNumber}',
                      style: TextStyle(
                        color: AppTheme.primaryColor,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  
                  const Spacer(),
                  
                  // Durum
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: AppTheme.getStatusColor(order.status),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      order.statusDisplay,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  
                  const SizedBox(width: 8),
                  
                  // Tutar
                  Text(
                    '${order.totalAmount.toStringAsFixed(0)} ₺',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: Colors.green,
                    ),
                  ),
                ],
              ),
              
              const SizedBox(height: 12),
              
              // Restoran bilgisi
              Row(
                children: [
                  const Icon(
                    Icons.restaurant,
                    size: 16,
                    color: Colors.orange,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      order.restaurant.name,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (order.restaurant.distance != null) ...[
                    const SizedBox(width: 8),
                    Text(
                      order.restaurant.distanceDisplay,
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[600],
                      ),
                    ),
                  ],
                ],
              ),
              
              const SizedBox(height: 6),
              
              // Restoran adresi
              Padding(
                padding: const EdgeInsets.only(left: 22),
                child: Text(
                  order.restaurant.address,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey[600],
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              
              const SizedBox(height: 12),
              
              // Müşteri bilgisi
              Row(
                children: [
                  const Icon(
                    Icons.person,
                    size: 16,
                    color: Colors.blue,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      order.customer.name,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  // Ödeme yöntemi
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 6,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: _getPaymentColor().withOpacity(0.1),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          order.paymentMethodIcon,
                          style: const TextStyle(fontSize: 10),
                        ),
                        const SizedBox(width: 2),
                        Text(
                          order.paymentMethodDisplay,
                          style: TextStyle(
                            fontSize: 10,
                            color: _getPaymentColor(),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              
              const SizedBox(height: 6),
              
              // Müşteri adresi (tıklanabilir)
              Padding(
                padding: const EdgeInsets.only(left: 22),
                child: GestureDetector(
                  onTap: () => _openCustomerAddressNavigation(order.customer.address),
                  child: Text(
                    order.customer.address,
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.blue[600],
                      decoration: TextDecoration.underline,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
              
              const SizedBox(height: 12),
              
              // Alt kısım - Zaman bilgisi ve butonlar
              Row(
                children: [
                  // Zaman bilgisi
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (order.estimatedReadyMinutes != null && 
                            order.estimatedReadyMinutes! > 0) ...[
                          Row(
                            children: [
                              const Icon(
                                Icons.access_time,
                                size: 14,
                                color: Colors.orange,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                order.estimatedPickupTime,
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: Colors.orange,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ],
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(
                                  Icons.schedule,
                                  size: 14,
                                  color: Colors.grey,
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  order.orderTimeDisplay,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: Colors.grey[600],
                                  ),
                                ),
                              ],
                            ),
                            if (order.estimatedReadyMinutes != null) ...[
                              const SizedBox(height: 2),
                              Row(
                                children: [
                                  Icon(
                                    Icons.restaurant,
                                    size: 12,
                                    color: order.isPreparationTimeExpired 
                                        ? Colors.red[600] 
                                        : Colors.orange[600],
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    order.preparationTimeDisplay,
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: order.isPreparationTimeExpired 
                                          ? Colors.red[600] 
                                          : Colors.orange[600],
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                  
                  // İşlem butonları
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (order.isAccepted || order.isPreparing || order.isReady) ...[
                        // Teslim Al butonu
                        ElevatedButton.icon(
                          onPressed: () async => await _pickupOrder(context),
                          icon: const Icon(Icons.shopping_bag, size: 16),
                          label: const Text('Teslim Al'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.blue,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 6,
                            ),
                            textStyle: const TextStyle(fontSize: 12),
                            minimumSize: Size.zero,
                          ),
                        ),
                      ] else if (order.isPickedUp) ...[
                        // Teslim Et butonu
                        ElevatedButton.icon(
                          onPressed: () async => await _deliverOrder(context),
                          icon: const Icon(Icons.check_circle, size: 16),
                          label: const Text('Teslim Et'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.green,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 6,
                            ),
                            textStyle: const TextStyle(fontSize: 12),
                            minimumSize: Size.zero,
                          ),
                        ),
                      ],
                      
                      const SizedBox(width: 8),
                      
                      // Detay butonu
                      OutlinedButton(
                        onPressed: () => _showOrderDetails(context),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 6,
                          ),
                          textStyle: const TextStyle(fontSize: 12),
                          minimumSize: Size.zero,
                        ),
                        child: const Text('Detay'),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _getPaymentColor() {
    switch (order.paymentMethod) {
      case 'cash':
        return Colors.green;
      case 'online':
        return Colors.blue;
      case 'credit_card':
      case 'credit_card_door':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  Future<void> _pickupOrder(BuildContext context) async {
    final orderProvider = Provider.of<OrderProvider>(context, listen: false);
    
    // Loading göster
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(
        child: CircularProgressIndicator(),
      ),
    );
    
    try {
      // Siparişi teslim al
      final success = await orderProvider.updateOrderStatus(
        order.id,
        'picked_up',
      );
      
      // Loading'i kapat
      if (context.mounted) {
        Navigator.of(context).pop();
      }
      
      if (success) {
        // Başarı mesajı göster
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${order.orderNumber} numaralı sipariş teslim alındı'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        // Hata mesajı göster
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(orderProvider.error ?? 'Sipariş teslim alınamadı'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      // Loading'i kapat
      if (context.mounted) {
        Navigator.of(context).pop();
      }
      
      // Hata mesajı göster
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Hata: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _deliverOrder(BuildContext context) async {
    final orderProvider = Provider.of<OrderProvider>(context, listen: false);
    
    // Loading göster
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(
        child: CircularProgressIndicator(),
      ),
    );
    
    try {
      // Siparişi teslim et
      final success = await orderProvider.updateOrderStatus(
        order.id,
        'delivered',
      );
      
      // Loading'i kapat
      if (context.mounted) {
        Navigator.of(context).pop();
      }
      
      if (success) {
        // Başarı mesajı göster
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${order.orderNumber} numaralı sipariş teslim edildi'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        // Hata mesajı göster
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(orderProvider.error ?? 'Sipariş teslim edilemedi'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      // Loading'i kapat
      if (context.mounted) {
        Navigator.of(context).pop();
      }
      
      // Hata mesajı göster
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Hata: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  void _showOrderDetails(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => DraggableScrollableSheet(
        initialChildSize: 0.7,
        maxChildSize: 0.9,
        minChildSize: 0.5,
        builder: (context, scrollController) => Container(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Başlık
              Row(
                children: [
                  Text(
                    'Sipariş Detayı',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const Spacer(),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              
              const Divider(),
              
              // Sipariş bilgileri
              Expanded(
                child: ListView(
                  controller: scrollController,
                  children: [
                    _DetailRow('Sipariş No', '#${order.orderNumber}'),
                    _DetailRow('Durum', order.statusDisplay),
                    _DetailRow('Toplam Tutar', '${order.totalAmount.toStringAsFixed(2)} ₺'),
                    _DetailRow('Teslimat Ücreti', '${order.deliveryFee.toStringAsFixed(2)} ₺'),
                    _DetailRow('Net Kazanç', '${order.netEarning.toStringAsFixed(2)} ₺'),
                    _DetailRow('Ödeme Yöntemi', order.paymentMethodDisplay),
                    
                    const SizedBox(height: 16),
                    const Text('Restoran', style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    _DetailRow('Ad', order.restaurant.name),
                    _DetailRow('Adres', order.restaurant.address),
                    if (order.restaurant.phone != null)
                      _DetailRow('Telefon', order.restaurant.phone!),
                    
                    const SizedBox(height: 16),
                    const Text('Müşteri', style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    _DetailRow('Ad', order.customer.name),
                    _DetailRow('Telefon', order.customer.phone),
                    _DetailRow('Adres', order.customer.address, isClickable: true),
                    
                    if (order.notes != null) ...[
                      const SizedBox(height: 16),
                      const Text('Notlar', style: TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text(order.notes!),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  final bool isClickable;

  const _DetailRow(this.label, this.value, {this.isClickable = false});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              '$label:',
              style: TextStyle(
                fontWeight: FontWeight.w500,
                color: Colors.grey[600],
              ),
            ),
          ),
          Expanded(
            child: isClickable 
              ? GestureDetector(
                  onTap: () => _openCustomerAddressNavigation(value),
                  child: Text(
                    value,
                    style: TextStyle(
                      fontWeight: FontWeight.w600,
                      color: Colors.blue[600],
                      decoration: TextDecoration.underline,
                    ),
                  ),
                )
              : Text(
                  value,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
          ),
        ],
      ),
    );
  }
}

/// Müşteri adresini haritalarda aç
void _openCustomerAddressNavigation(String address) async {
  // Adresi URL encode et
  final encodedAddress = Uri.encodeComponent(address);
  
  // Google Maps URL'si oluştur
  final googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=$encodedAddress';
  
  // URL'yi aç
  final uri = Uri.parse(googleMapsUrl);
  if (await canLaunchUrl(uri)) {
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
