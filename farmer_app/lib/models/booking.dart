import 'booking_crop.dart';

class Booking {
  final int id;
  final String token;
  final int centreId;
  final String centreName;
  final String date;
  final String? slotStart;
  final String? slotEnd;
  final String status;
  final String? cancelReason;
  final String? cancelledAt;
  final List<BookingCrop> crops;
  final String createdAt;

  Booking({
    required this.id,
    required this.token,
    required this.centreId,
    required this.centreName,
    required this.date,
    this.slotStart,
    this.slotEnd,
    required this.status,
    this.cancelReason,
    this.cancelledAt,
    this.crops = const [],
    required this.createdAt,
  });

  factory Booking.fromJson(Map<String, dynamic> json) => Booking(
        id: json['id'] ?? 0,
        token: json['token'] ?? '',
        centreId: json['centre_id'] ?? 0,
        centreName: json['centre_name'] ?? json['centre']?['name'] ?? '',
        date: json['date'] ?? '',
        slotStart: json['slot_start'] ?? json['slot']?['start_time'],
        slotEnd: json['slot_end'] ?? json['slot']?['end_time'],
        status: json['status'] ?? '',
        cancelReason: json['cancel_reason'],
        cancelledAt: json['cancelled_at'],
        crops: json['crops'] != null
            ? (json['crops'] as List)
                .map((c) => BookingCrop.fromJson(c))
                .toList()
            : json['booking_crops'] != null
                ? (json['booking_crops'] as List)
                    .map((c) => BookingCrop.fromJson(c))
                    .toList()
                : [],
        createdAt: json['created_at'] ?? '',
      );

  bool get isCancellable =>
      status == 'CONFIRMED' || status == 'PENDING';
}
