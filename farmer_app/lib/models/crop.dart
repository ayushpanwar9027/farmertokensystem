class Crop {
  final int id;
  final String name;
  final String? category;
  final double? mspPrice;

  Crop({
    required this.id,
    required this.name,
    this.category,
    this.mspPrice,
  });

  factory Crop.fromJson(Map<String, dynamic> json) => Crop(
        id: json['id'] ?? 0,
        name: json['name'] ?? '',
        category: json['category'],
        mspPrice: json['msp_price'] != null
            ? double.tryParse(json['msp_price'].toString())
            : null,
      );
}
