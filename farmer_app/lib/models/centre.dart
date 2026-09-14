class Centre {
  final int id;
  final String name;
  final String code;
  final String? address;
  final String? contactPhone;
  final String? workingHoursStart;
  final String? workingHoursEnd;
  final List<String>? workingDays;
  final int? dailyCapacity;
  final String status;
  final CentreDistrict? district;
  final CentreManager? manager;

  Centre({
    required this.id,
    required this.name,
    required this.code,
    this.address,
    this.contactPhone,
    this.workingHoursStart,
    this.workingHoursEnd,
    this.workingDays,
    this.dailyCapacity,
    required this.status,
    this.district,
    this.manager,
  });

  factory Centre.fromJson(Map<String, dynamic> json) => Centre(
        id: json['id'] ?? 0,
        name: json['name'] ?? '',
        code: json['code'] ?? '',
        address: json['address'],
        contactPhone: json['contact_phone'],
        workingHoursStart: json['working_hours_start'],
        workingHoursEnd: json['working_hours_end'],
        workingDays: json['working_days'] != null
            ? List<String>.from(json['working_days'])
            : null,
        dailyCapacity: json['daily_capacity'],
        status: json['status'] ?? 'ACTIVE',
        district: json['district'] != null
            ? CentreDistrict.fromJson(json['district'])
            : null,
        manager: json['manager'] != null
            ? CentreManager.fromJson(json['manager'])
            : null,
      );
}

class CentreDistrict {
  final int id;
  final String name;
  CentreDistrict({required this.id, required this.name});
  factory CentreDistrict.fromJson(Map<String, dynamic> json) =>
      CentreDistrict(id: json['id'] ?? 0, name: json['name'] ?? '');
}

class CentreManager {
  final int id;
  final String name;
  CentreManager({required this.id, required this.name});
  factory CentreManager.fromJson(Map<String, dynamic> json) =>
      CentreManager(id: json['id'] ?? 0, name: json['name'] ?? '');
}
