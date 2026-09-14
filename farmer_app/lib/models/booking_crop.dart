class BookingCrop {
  final int id;
  final int cropId;
  final String cropName;
  final double quantityKg;
  final String status;
  final String? quality;
  final double? acceptedQuantity;
  final String? notes;

  BookingCrop({
    required this.id,
    required this.cropId,
    required this.cropName,
    required this.quantityKg,
    required this.status,
    this.quality,
    this.acceptedQuantity,
    this.notes,
  });

  factory BookingCrop.fromJson(Map<String, dynamic> json) => BookingCrop(
        id: json['id'] ?? 0,
        cropId: json['crop_id'] ?? 0,
        cropName: json['crop_name'] ?? json['crop']?['name'] ?? '',
        quantityKg: (json['quantity_kg'] ?? 0).toDouble(),
        status: json['status'] ?? '',
        quality: json['quality'],
        acceptedQuantity: json['accepted_quantity'] != null
            ? (json['accepted_quantity']).toDouble()
            : null,
        notes: json['notes'],
      );
}
