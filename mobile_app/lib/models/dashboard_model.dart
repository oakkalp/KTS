import 'order_model.dart';

class DashboardData {
  final DashboardStats stats;
  final KuryeStatus kuryeStatus;
  final List<Order> activeOrders;
  final List<Order> availableOrders;

  const DashboardData({
    required this.stats,
    required this.kuryeStatus,
    required this.activeOrders,
    required this.availableOrders,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    return DashboardData(
      stats: DashboardStats.fromJson(json['stats'] ?? {}),
      kuryeStatus: KuryeStatus.fromJson(json['kurye_status'] ?? {}),
      activeOrders: (json['active_orders'] as List? ?? [])
          .map((order) => Order.fromJson(order as Map<String, dynamic>))
          .toList(),
      availableOrders: (json['available_orders'] as List? ?? [])
          .map((order) => Order.fromJson(order as Map<String, dynamic>))
          .toList(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'stats': stats.toJson(),
      'kurye_status': kuryeStatus.toJson(),
      'active_orders': activeOrders.map((order) => order.toJson()).toList(),
      'available_orders': availableOrders.map((order) => order.toJson()).toList(),
    };
  }

  DashboardData copyWith({
    DashboardStats? stats,
    KuryeStatus? kuryeStatus,
    List<Order>? activeOrders,
    List<Order>? availableOrders,
  }) {
    return DashboardData(
      stats: stats ?? this.stats,
      kuryeStatus: kuryeStatus ?? this.kuryeStatus,
      activeOrders: activeOrders ?? this.activeOrders,
      availableOrders: availableOrders ?? this.availableOrders,
    );
  }

  @override
  String toString() {
    return 'DashboardData(activeOrders: ${activeOrders.length}, availableOrders: ${availableOrders.length})';
  }
}

class DashboardStats {
  final int todayDeliveries;
  final double todayEarnings;
  final double todayGrossEarnings;
  final int activeOrders;
  final int totalDeliveries;
  final double totalEarnings;
  final double? rating;

  const DashboardStats({
    required this.todayDeliveries,
    required this.todayEarnings,
    required this.todayGrossEarnings,
    required this.activeOrders,
    required this.totalDeliveries,
    required this.totalEarnings,
    this.rating,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    return DashboardStats(
      todayDeliveries: json['today_deliveries'] as int? ?? 0,
      todayEarnings: (json['today_earnings'] as num?)?.toDouble() ?? 0.0,
      todayGrossEarnings: (json['today_gross_earnings'] as num?)?.toDouble() ?? 0.0,
      activeOrders: json['active_orders'] as int? ?? 0,
      totalDeliveries: json['total_deliveries'] as int? ?? 0,
      totalEarnings: (json['total_earnings'] as num?)?.toDouble() ?? 0.0,
      rating: json['rating'] != null ? (json['rating'] as num).toDouble() : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'today_deliveries': todayDeliveries,
      'today_earnings': todayEarnings,
      'today_gross_earnings': todayGrossEarnings,
      'active_orders': activeOrders,
      'total_deliveries': totalDeliveries,
      'total_earnings': totalEarnings,
      'rating': rating,
    };
  }

  String get ratingDisplay {
    if (rating == null) return 'Yeni';
    return '${rating!.toStringAsFixed(1)} ⭐';
  }

  String get earningsDisplay {
    return '${todayEarnings.toStringAsFixed(2)} ₺';
  }

  String get averageEarningPerDelivery {
    if (todayDeliveries == 0) return '0 ₺';
    return '${(todayEarnings / todayDeliveries).toStringAsFixed(2)} ₺';
  }

  @override
  String toString() {
    return 'DashboardStats(todayDeliveries: $todayDeliveries, todayEarnings: $todayEarnings, totalDeliveries: $totalDeliveries, totalEarnings: $totalEarnings)';
  }
}

class KuryeStatus {
  final bool isOnline;
  final bool isAvailable;
  final String vehicleType;
  final DateTime? lastLocationUpdate;

  const KuryeStatus({
    required this.isOnline,
    required this.isAvailable,
    required this.vehicleType,
    this.lastLocationUpdate,
  });

  factory KuryeStatus.fromJson(Map<String, dynamic> json) {
    return KuryeStatus(
      isOnline: json['is_online'] as bool? ?? false,
      isAvailable: json['is_available'] as bool? ?? false,
      vehicleType: json['vehicle_type'] as String? ?? 'motorcycle',
      lastLocationUpdate: json['last_location_update'] != null
          ? DateTime.parse(json['last_location_update'])
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'is_online': isOnline,
      'is_available': isAvailable,
      'vehicle_type': vehicleType,
      'last_location_update': lastLocationUpdate?.toIso8601String(),
    };
  }

  KuryeStatus copyWith({
    bool? isOnline,
    bool? isAvailable,
    String? vehicleType,
    DateTime? lastLocationUpdate,
  }) {
    return KuryeStatus(
      isOnline: isOnline ?? this.isOnline,
      isAvailable: isAvailable ?? this.isAvailable,
      vehicleType: vehicleType ?? this.vehicleType,
      lastLocationUpdate: lastLocationUpdate ?? this.lastLocationUpdate,
    );
  }

  String get statusDisplay {
    if (!isOnline) return 'Çevrimdışı';
    if (!isAvailable) return 'Meşgul';
    return 'Müsait';
  }

  String get statusIcon {
    if (!isOnline) return '🔴';
    if (!isAvailable) return '🟡';
    return '🟢';
  }

  String get vehicleIcon {
    switch (vehicleType) {
      case 'motorcycle':
        return '🏍️';
      case 'bicycle':
        return '🚲';
      case 'car':
        return '🚗';
      case 'walking':
        return '🚶';
      default:
        return '🏍️';
    }
  }

  String get vehicleDisplay {
    switch (vehicleType) {
      case 'motorcycle':
        return 'Motosiklet';
      case 'bicycle':
        return 'Bisiklet';
      case 'car':
        return 'Araba';
      case 'walking':
        return 'Yaya';
      default:
        return vehicleType;
    }
  }

  @override
  String toString() {
    return 'KuryeStatus(isOnline: $isOnline, isAvailable: $isAvailable, vehicleType: $vehicleType)';
  }
}
