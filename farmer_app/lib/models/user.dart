class User {
  final int id;
  final String name;
  final String mobile;
  final String role;
  final String status;
  final bool twoFactorEnabled;
  final FarmerProfile? farmer;

  User({
    required this.id,
    required this.name,
    required this.mobile,
    required this.role,
    required this.status,
    this.twoFactorEnabled = false,
    this.farmer,
  });

  factory User.fromJson(Map<String, dynamic> json) => User(
        id: json['id'] ?? 0,
        name: json['name'] ?? '',
        mobile: json['mobile'] ?? '',
        role: json['role'] ?? '',
        status: json['status'] ?? '',
        twoFactorEnabled: json['two_factor_enabled'] == true ||
            json['two_factor_enabled'] == 1,
        farmer: json['farmer'] != null
            ? FarmerProfile.fromJson(json['farmer'])
            : null,
      );

  User copyWith({
    int? id,
    String? name,
    String? mobile,
    String? role,
    String? status,
    bool? twoFactorEnabled,
    FarmerProfile? farmer,
  }) =>
      User(
        id: id ?? this.id,
        name: name ?? this.name,
        mobile: mobile ?? this.mobile,
        role: role ?? this.role,
        status: status ?? this.status,
        twoFactorEnabled: twoFactorEnabled ?? this.twoFactorEnabled,
        farmer: farmer ?? this.farmer,
      );
}

class FarmerProfile {
  final int id;
  final String verificationStatus;
  final String? village;
  final int? districtId;

  FarmerProfile({
    required this.id,
    required this.verificationStatus,
    this.village,
    this.districtId,
  });

  factory FarmerProfile.fromJson(Map<String, dynamic> json) => FarmerProfile(
        id: json['id'] ?? 0,
        verificationStatus: json['verification_status'] ?? '',
        village: json['village'],
        districtId: json['district_id'],
      );
}
