import 'dart:convert';

class Order {
  final int id;
  final String orderNumber;
  final String status;
  final Customer customer;
  final Restaurant restaurant;
  final double totalAmount;
  final double deliveryFee;
  final double netEarning;
  final String paymentMethod;
  final int preparationTime;
  final int? estimatedReadyMinutes;
  final int? estimatedTime;
  final int orderAgeMinutes;
  final String priority;
  final String? notes;
  final DateTime createdAt;
  final DateTime? acceptedAt;
  final DateTime? pickedUpAt;
  final DateTime? deliveredAt;
  final String? expiresAt;
  final String? deliveryPhoto;

  const Order({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.customer,
    required this.restaurant,
    required this.totalAmount,
    required this.deliveryFee,
    required this.netEarning,
    required this.paymentMethod,
    required this.preparationTime,
    this.estimatedReadyMinutes,
    this.estimatedTime,
    required this.orderAgeMinutes,
    required this.priority,
    this.notes,
    required this.createdAt,
    this.acceptedAt,
    this.pickedUpAt,
    this.deliveredAt,
    this.expiresAt,
    this.deliveryPhoto,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'] as int? ?? 0,
      orderNumber: json['order_number'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
      customer: Customer.fromJson(json['customer'] as Map<String, dynamic>? ?? {}),
      restaurant: Restaurant.fromJson(json['restaurant'] as Map<String, dynamic>? ?? {}),
      totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0.0,
      deliveryFee: (json['delivery_fee'] as num?)?.toDouble() ?? 0.0,
      netEarning: (json['net_earning'] as num?)?.toDouble() ?? 0.0,
      paymentMethod: json['payment_method'] as String? ?? 'nakit',
      preparationTime: json['preparation_time'] as int? ?? 0,
      estimatedReadyMinutes: json['estimated_ready_minutes'] as int?,
      estimatedTime: json['estimated_time'] as int?,
      orderAgeMinutes: json['order_age_minutes'] as int? ?? 0,
      priority: json['priority'] as String? ?? 'normal',
      notes: json['notes'] as String?,
      createdAt: _parseDateTime(json['created_at']) ?? DateTime.now(),
      acceptedAt: _parseDateTime(json['accepted_at']),
      pickedUpAt: _parseDateTime(json['picked_up_at']),
      deliveredAt: _parseDateTime(json['delivered_at']),
      expiresAt: json['expires_at'] as String?,
      deliveryPhoto: json['delivery_photo'] as String?,
    );
  }
  
  static DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    if (value is DateTime) return value;
    if (value is String) {
      try {
        return DateTime.parse(value);
      } catch (e) {
        // MySQL datetime formatını dene
        try {
          return DateTime.parse(value.replaceAll(' ', 'T'));
        } catch (e2) {
          print('DateTime parse error: $value');
          return null;
        }
      }
    }
    return null;
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'order_number': orderNumber,
      'status': status,
      'customer': customer.toJson(),
      'restaurant': restaurant.toJson(),
      'total_amount': totalAmount,
      'delivery_fee': deliveryFee,
      'net_earning': netEarning,
      'payment_method': paymentMethod,
      'preparation_time': preparationTime,
      'estimated_ready_minutes': estimatedReadyMinutes,
      'estimated_time': estimatedTime,
      'order_age_minutes': orderAgeMinutes,
      'priority': priority,
      'notes': notes,
      'created_at': createdAt.toIso8601String(),
      'accepted_at': acceptedAt?.toIso8601String(),
      'picked_up_at': pickedUpAt?.toIso8601String(),
      'delivered_at': deliveredAt?.toIso8601String(),
      'expires_at': expiresAt,
      'delivery_photo': deliveryPhoto,
    };
  }

  Order copyWith({
    int? id,
    String? orderNumber,
    String? status,
    Customer? customer,
    Restaurant? restaurant,
    double? totalAmount,
    double? deliveryFee,
    double? netEarning,
    String? paymentMethod,
    int? preparationTime,
    int? estimatedReadyMinutes,
    int? estimatedTime,
    int? orderAgeMinutes,
    String? priority,
    String? notes,
    DateTime? createdAt,
    DateTime? acceptedAt,
    DateTime? pickedUpAt,
    DateTime? deliveredAt,
    String? expiresAt,
    String? deliveryPhoto,
  }) {
    return Order(
      id: id ?? this.id,
      orderNumber: orderNumber ?? this.orderNumber,
      status: status ?? this.status,
      customer: customer ?? this.customer,
      restaurant: restaurant ?? this.restaurant,
      totalAmount: totalAmount ?? this.totalAmount,
      deliveryFee: deliveryFee ?? this.deliveryFee,
      netEarning: netEarning ?? this.netEarning,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      preparationTime: preparationTime ?? this.preparationTime,
      estimatedReadyMinutes: estimatedReadyMinutes ?? this.estimatedReadyMinutes,
      estimatedTime: estimatedTime ?? this.estimatedTime,
      orderAgeMinutes: orderAgeMinutes ?? this.orderAgeMinutes,
      priority: priority ?? this.priority,
      notes: notes ?? this.notes,
      createdAt: createdAt ?? this.createdAt,
      acceptedAt: acceptedAt ?? this.acceptedAt,
      pickedUpAt: pickedUpAt ?? this.pickedUpAt,
      deliveredAt: deliveredAt ?? this.deliveredAt,
      expiresAt: expiresAt ?? this.expiresAt,
      deliveryPhoto: deliveryPhoto ?? this.deliveryPhoto,
    );
  }

  // Status helpers
  bool get isPending => status == 'pending';
  bool get isAccepted => status == 'accepted';
  bool get isPreparing => status == 'preparing';
  bool get isReady => status == 'ready';
  bool get isPickedUp => status == 'picked_up';
  bool get isDelivering => status == 'delivering';
  bool get isDelivered => status == 'delivered';
  bool get isCancelled => status == 'cancelled';

  bool get isActive => ['accepted', 'preparing', 'ready', 'picked_up', 'delivering'].contains(status);
  bool get isCompleted => status == 'delivered';

  // Priority helpers
  bool get isUrgent => priority == 'urgent';
  bool get isExpress => priority == 'express';
  bool get isNormal => priority == 'normal';

  // Payment method helpers
  bool get isCashPayment => paymentMethod == 'cash';
  bool get isOnlinePayment => paymentMethod == 'online';
  bool get isCreditCardPayment => paymentMethod == 'credit_card';
  bool get isDoorCreditCard => paymentMethod == 'credit_card_door';

  String get statusDisplay {
    switch (status) {
      case 'pending':
        return 'Bekliyor';
      case 'accepted':
        return 'Kabul Edildi';
      case 'preparing':
        return 'Hazırlanıyor';
      case 'ready':
        return 'Hazır';
      case 'picked_up':
        return 'Alındı';
      case 'delivering':
        return 'Teslim Ediliyor';
      case 'delivered':
        return 'Teslim Edildi';
      case 'cancelled':
        return 'İptal Edildi';
      default:
        return status;
    }
  }

  String get priorityDisplay {
    switch (priority) {
      case 'urgent':
        return 'ACİL';
      case 'express':
        return 'EKSPRES';
      case 'normal':
        return 'NORMAL';
      default:
        return priority.toUpperCase();
    }
  }

  String get paymentMethodDisplay {
    switch (paymentMethod) {
      case 'cash':
        return 'Nakit';
      case 'online':
        return 'Online';
      case 'credit_card':
        return 'Kredi Kartı';
      case 'credit_card_door':
        return 'Kapıda Kredi Kartı';
      default:
        return paymentMethod;
    }
  }

  String get paymentMethodIcon {
    switch (paymentMethod) {
      case 'cash':
        return '💵';
      case 'online':
        return '💳';
      case 'credit_card':
        return '💳';
      case 'credit_card_door':
        return '💳';
      default:
        return '💰';
    }
  }

  // Zaman hesaplama fonksiyonları
  int get remainingPreparationMinutes {
    if (estimatedReadyMinutes == null) return 0;
    
    final now = DateTime.now();
    final orderTime = createdAt;
    final elapsedMinutes = now.difference(orderTime).inMinutes;
    
    return (estimatedReadyMinutes! - elapsedMinutes).clamp(0, estimatedReadyMinutes!);
  }

  bool get isPreparationTimeExpired {
    return remainingPreparationMinutes <= 0 && estimatedReadyMinutes != null;
  }

  String get preparationTimeDisplay {
    if (estimatedReadyMinutes == null) {
      return 'Süre belirtilmemiş';
    }
    
    if (isPreparationTimeExpired) {
      return 'Süre doldu';
    }
    
    return '$remainingPreparationMinutes dk kaldı';
  }

  String get orderTimeDisplay {
    if (orderAgeMinutes < 1) {
      return 'Az önce';
    } else if (orderAgeMinutes < 60) {
      return '$orderAgeMinutes dk önce';
    } else {
      final hours = (orderAgeMinutes / 60).floor();
      return '$hours sa önce';
    }
  }

  // Estimated times
  String get estimatedPickupTime {
    if (estimatedReadyMinutes != null && estimatedReadyMinutes! > 0) {
      return '${estimatedReadyMinutes} dakika sonra hazır';
    }
    return 'Hazır';
  }

  String get totalEstimatedTime {
    if (estimatedTime != null) {
      return '~${estimatedTime} dakika';
    }
    return 'Bilinmiyor';
  }

  @override
  String toString() {
    return 'Order(id: $id, orderNumber: $orderNumber, status: $status)';
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is Order && other.id == id;
  }

  @override
  int get hashCode => id.hashCode;
}

class Customer {
  final String name;
  final String phone;
  final String address;

  const Customer({
    required this.name,
    required this.phone,
    required this.address,
  });

  factory Customer.fromJson(Map<String, dynamic> json) {
    return Customer(
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      address: json['address'] as String? ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'name': name,
      'phone': phone,
      'address': address,
    };
  }

  @override
  String toString() {
    return 'Customer(name: $name, phone: $phone)';
  }
}

class Restaurant {
  final String name;
  final String address;
  final String? phone;
  final double? distance;

  const Restaurant({
    required this.name,
    required this.address,
    this.phone,
    this.distance,
  });

  factory Restaurant.fromJson(Map<String, dynamic> json) {
    return Restaurant(
      name: json['name'] as String? ?? '',
      address: json['address'] as String? ?? '',
      phone: json['phone'] as String?,
      distance: json['distance'] != null ? (json['distance'] as num).toDouble() : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'name': name,
      'address': address,
      'phone': phone,
      'distance': distance,
    };
  }

  String get distanceDisplay {
    if (distance == null) return '';
    if (distance! < 1) {
      return '${(distance! * 1000).round()}m';
    }
    return '${distance!.toStringAsFixed(1)}km';
  }

  @override
  String toString() {
    return 'Restaurant(name: $name, distance: ${distanceDisplay})';
  }
}
