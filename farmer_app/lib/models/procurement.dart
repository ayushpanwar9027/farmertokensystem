class Procurement {
  final int id;
  final int bookingId;
  final String? centreName;
  final String status;
  final String? notes;
  final List<ProcurementLine> lines;
  final String createdAt;

  Procurement({
    required this.id,
    required this.bookingId,
    this.centreName,
    required this.status,
    this.notes,
    this.lines = const [],
    required this.createdAt,
  });

  factory Procurement.fromJson(Map<String, dynamic> json) => Procurement(
        id: json['id'] ?? 0,
        bookingId: json['booking_id'] ?? 0,
        centreName: json['centre_name'],
        status: json['status'] ?? '',
        notes: json['notes'],
        lines: json['lines'] != null
            ? (json['lines'] as List)
                .map((l) => ProcurementLine.fromJson(l))
                .toList()
            : json['procurement_lines'] != null
                ? (json['procurement_lines'] as List)
                    .map((l) => ProcurementLine.fromJson(l))
                    .toList()
                : [],
        createdAt: json['created_at'] ?? '',
      );
}

class ProcurementLine {
  final int id;
  final int cropId;
  final String cropName;
  final double quantityKg;
  final double? acceptedQuantity;
  final String? quality;
  final String status;
  final String? rejectionReason;

  ProcurementLine({
    required this.id,
    required this.cropId,
    required this.cropName,
    required this.quantityKg,
    this.acceptedQuantity,
    this.quality,
    required this.status,
    this.rejectionReason,
  });

  factory ProcurementLine.fromJson(Map<String, dynamic> json) =>
      ProcurementLine(
        id: json['id'] ?? 0,
        cropId: json['crop_id'] ?? 0,
        cropName: json['crop_name'] ?? json['crop']?['name'] ?? '',
        quantityKg: (json['quantity_kg'] ?? 0).toDouble(),
        acceptedQuantity: json['accepted_quantity'] != null
            ? (json['accepted_quantity']).toDouble()
            : null,
        quality: json['quality'],
        status: json['status'] ?? '',
        rejectionReason: json['rejection_reason'],
      );
}
