class QueueEntry {
  final int id;
  final int bookingId;
  final String token;
  final int position;
  final String status;
  final String? calledAt;
  final String? completedAt;

  QueueEntry({
    required this.id,
    required this.bookingId,
    required this.token,
    required this.position,
    required this.status,
    this.calledAt,
    this.completedAt,
  });

  factory QueueEntry.fromJson(Map<String, dynamic> json) => QueueEntry(
        id: json['id'] ?? 0,
        bookingId: json['booking_id'] ?? 0,
        token: json['token'] ?? '',
        position: json['position'] ?? 0,
        status: json['status'] ?? '',
        calledAt: json['called_at'],
        completedAt: json['completed_at'],
      );
}

class QueueStatus {
  final QueueEntry? myEntry;
  final int farmersAhead;
  final String estimatedWait;
  final String currentToken;
  final List<QueueEntry> queue;

  QueueStatus({
    this.myEntry,
    required this.farmersAhead,
    required this.estimatedWait,
    required this.currentToken,
    this.queue = const [],
  });

  factory QueueStatus.fromJson(Map<String, dynamic> json) => QueueStatus(
        myEntry: json['my_entry'] != null
            ? QueueEntry.fromJson(json['my_entry'])
            : null,
        farmersAhead: json['farmers_ahead'] ?? 0,
        estimatedWait: json['estimated_wait'] ?? '--',
        currentToken: json['current_token'] ?? '--',
        queue: json['queue'] != null
            ? (json['queue'] as List)
                .map((e) => QueueEntry.fromJson(e))
                .toList()
            : [],
      );
}
