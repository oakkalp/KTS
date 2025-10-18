import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/order_provider.dart';
import '../config/theme.dart';

class EarningsScreen extends StatefulWidget {
  const EarningsScreen({super.key});

  @override
  State<EarningsScreen> createState() => _EarningsScreenState();
}

class _EarningsScreenState extends State<EarningsScreen> {
  Map<String, dynamic>? _earningsData;
  bool _isLoading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadEarnings();
  }

  Future<void> _loadEarnings() async {
    if (_isLoading) return;

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final orderProvider = Provider.of<OrderProvider>(context, listen: false);
      final earningsData = await orderProvider.loadEarnings();
      
      if (earningsData != null) {
        setState(() {
          _earningsData = earningsData;
        });
      } else {
        setState(() {
          _error = orderProvider.error ?? 'Kazanç bilgileri yüklenemedi';
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Hata: ${e.toString()}';
      });
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kazançlarım'),
        backgroundColor: Colors.orange,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadEarnings,
          ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _earningsData == null) {
      return const Center(
        child: CircularProgressIndicator(),
      );
    }

    if (_error != null && _earningsData == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.error_outline,
              size: 64,
              color: Colors.red[300],
            ),
            const SizedBox(height: 16),
            Text(
              'Hata',
              style: TextStyle(
                fontSize: 18,
                color: Colors.red[600],
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _error!,
              style: TextStyle(
                fontSize: 14,
                color: Colors.red[500],
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadEarnings,
              child: const Text('Tekrar Dene'),
            ),
          ],
        ),
      );
    }

    if (_earningsData == null) {
      return const Center(
        child: Text('Kazanç verisi bulunamadı'),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadEarnings,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Bugünkü kazanç kartı
          _buildEarningsCard(
            title: 'Bugünkü Kazanç',
            icon: Icons.today,
            color: Colors.green,
            earnings: _earningsData!['today'],
          ),
          
          const SizedBox(height: 16),
          
          // Bu haftaki kazanç kartı
          _buildEarningsCard(
            title: 'Bu Haftaki Kazanç',
            icon: Icons.date_range,
            color: Colors.blue,
            earnings: _earningsData!['week'],
          ),
          
          const SizedBox(height: 16),
          
          // Bu ayki kazanç kartı
          _buildEarningsCard(
            title: 'Bu Ayki Kazanç',
            icon: Icons.calendar_month,
            color: Colors.purple,
            earnings: _earningsData!['month'],
          ),
          
          const SizedBox(height: 16),
          
          // Toplam kazanç kartı
          _buildEarningsCard(
            title: 'Toplam Kazanç',
            icon: Icons.trending_up,
            color: Colors.orange,
            earnings: _earningsData!['total'],
          ),
          
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildEarningsCard({
    required String title,
    required IconData icon,
    required Color color,
    required Map<String, dynamic> earnings,
  }) {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
      ),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: LinearGradient(
            colors: [color, color.withOpacity(0.7)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  icon,
                  color: Colors.white,
                  size: 24,
                ),
                const SizedBox(width: 8),
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              '${earnings['net_earnings'].toStringAsFixed(2)} ₺',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 32,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${earnings['deliveries']} teslimat',
              style: TextStyle(
                color: Colors.white.withOpacity(0.8),
                fontSize: 14,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
