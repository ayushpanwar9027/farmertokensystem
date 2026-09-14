class Payment {
  final int id;
  final int? procurementId;
  final double amount;
  final String method;
  final String reference;
  final String status;
  final String? paidAt;
  final String createdAt;

  Payment({
    required this.id,
    this.procurementId,
    required this.amount,
    required this.method,
    required this.reference,
    required this.status,
    this.paidAt,
    required this.createdAt,
  });

  factory Payment.fromJson(Map<String, dynamic> json) => Payment(
        id: json['id'] ?? 0,
        procurementId: json['procurement_id'],
        amount: (json['amount'] ?? 0).toDouble(),
        method: json['method'] ?? '',
        reference: json['reference'] ?? '',
        status: json['status'] ?? '',
        paidAt: json['paid_at'],
        createdAt: json['created_at'] ?? '',
      );
}
