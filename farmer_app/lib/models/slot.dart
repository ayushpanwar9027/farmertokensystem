class Slot {
  final int id;
  final int centreId;
  final String date;
  final String startTime;
  final String endTime;
  final int capacity;
  final int booked;
  final String status;

  Slot({
    required this.id,
    required this.centreId,
    required this.date,
    required this.startTime,
    required this.endTime,
    required this.capacity,
    required this.booked,
    required this.status,
  });

  int get available => capacity - booked;
  bool get isFull => available <= 0;

  factory Slot.fromJson(Map<String, dynamic> json) => Slot(
        id: json['id'] ?? 0,
        centreId: json['centre_id'] ?? 0,
        date: json['date'] ?? '',
        startTime: json['start_time'] ?? '',
        endTime: json['end_time'] ?? '',
        capacity: json['capacity'] ?? 0,
        booked: json['booked'] ?? 0,
        status: json['status'] ?? 'ACTIVE',
      );
}
