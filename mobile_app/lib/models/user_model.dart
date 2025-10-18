import 'dart:convert';

class User {
  final int id;
  final String username;
  final String userType;
  final String fullName;
  final String email;
  final String phone;
  final String? lastLogin;
  
  // Kurye specific fields
  final int? kuryeId;
  final String? licensePlate;
  final String? vehicleType;
  final UserStats? stats;
  
  // Mekan specific fields
  final int? mekanId;
  final String? mekanName;
  final String? address;
  final String? cuisineType;
  final bool? isOpen;
  final String? openingHours;

  const User({
    required this.id,
    required this.username,
    required this.userType,
    required this.fullName,
    required this.email,
    required this.phone,
    this.lastLogin,
    this.kuryeId,
    this.licensePlate,
    this.vehicleType,
    this.stats,
    this.mekanId,
    this.mekanName,
    this.address,
    this.cuisineType,
    this.isOpen,
    this.openingHours,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int,
      username: json['username'] as String,
      userType: json['user_type'] as String,
      fullName: json['full_name'] as String,
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      lastLogin: json['last_login'] as String?,
      kuryeId: json['kurye_id'] as int?,
      licensePlate: json['license_plate'] as String?,
      vehicleType: json['vehicle_type'] as String?,
      stats: json['stats'] != null ? UserStats.fromJson(json['stats']) : null,
      mekanId: json['mekan_id'] as int?,
      mekanName: json['mekan_name'] as String?,
      address: json['address'] as String?,
      cuisineType: json['cuisine_type'] as String?,
      isOpen: json['is_open'] as bool?,
      openingHours: json['opening_hours'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'username': username,
      'user_type': userType,
      'full_name': fullName,
      'email': email,
      'phone': phone,
      'last_login': lastLogin,
      if (kuryeId != null) 'kurye_id': kuryeId,
      if (licensePlate != null) 'license_plate': licensePlate,
      if (vehicleType != null) 'vehicle_type': vehicleType,
      if (stats != null) 'stats': stats!.toJson(),
      if (mekanId != null) 'mekan_id': mekanId,
      if (mekanName != null) 'mekan_name': mekanName,
      if (address != null) 'address': address,
      if (cuisineType != null) 'cuisine_type': cuisineType,
      if (isOpen != null) 'is_open': isOpen,
      if (openingHours != null) 'opening_hours': openingHours,
    };
  }

  User copyWith({
    int? id,
    String? username,
    String? userType,
    String? fullName,
    String? email,
    String? phone,
    String? lastLogin,
    int? kuryeId,
    String? licensePlate,
    String? vehicleType,
    UserStats? stats,
    int? mekanId,
    String? mekanName,
    String? address,
    String? cuisineType,
    bool? isOpen,
    String? openingHours,
  }) {
    return User(
      id: id ?? this.id,
      username: username ?? this.username,
      userType: userType ?? this.userType,
      fullName: fullName ?? this.fullName,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      lastLogin: lastLogin ?? this.lastLogin,
      kuryeId: kuryeId ?? this.kuryeId,
      licensePlate: licensePlate ?? this.licensePlate,
      vehicleType: vehicleType ?? this.vehicleType,
      stats: stats ?? this.stats,
      mekanId: mekanId ?? this.mekanId,
      mekanName: mekanName ?? this.mekanName,
      address: address ?? this.address,
      cuisineType: cuisineType ?? this.cuisineType,
      isOpen: isOpen ?? this.isOpen,
      openingHours: openingHours ?? this.openingHours,
    );
  }

  bool get isKurye => userType == 'kurye';
  bool get isMekan => userType == 'mekan';
  bool get isAdmin => userType == 'admin';

  String get displayName => fullName.isNotEmpty ? fullName : username;
  
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

  @override
  String toString() {
    return 'User(id: $id, username: $username, userType: $userType, fullName: $fullName)';
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is User && other.id == id && other.username == username;
  }

  @override
  int get hashCode => id.hashCode ^ username.hashCode;
}

class UserStats {
  final int totalDeliveries;
  final double? rating;
  final double totalEarnings;

  const UserStats({
    required this.totalDeliveries,
    this.rating,
    required this.totalEarnings,
  });

  factory UserStats.fromJson(Map<String, dynamic> json) {
    return UserStats(
      totalDeliveries: json['total_deliveries'] as int? ?? 0,
      rating: json['rating'] != null ? (json['rating'] as num).toDouble() : null,
      totalEarnings: (json['total_earnings'] as num?)?.toDouble() ?? 0.0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'total_deliveries': totalDeliveries,
      'rating': rating,
      'total_earnings': totalEarnings,
    };
  }

  String get ratingDisplay {
    if (rating == null) return 'Değerlendirme yok';
    return '${rating!.toStringAsFixed(1)} ⭐';
  }

  @override
  String toString() {
    return 'UserStats(totalDeliveries: $totalDeliveries, rating: $rating, totalEarnings: $totalEarnings)';
  }
}
