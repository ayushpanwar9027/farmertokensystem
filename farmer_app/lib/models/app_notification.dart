import 'dart:convert';

class AppNotification {
  final int id;
  final String type;
  final String title;
  final String message;
  final Map<String, dynamic>? data;
  final String channel;
  final bool isRead;
  final String? readAt;
  final String createdAt;

  AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    this.data,
    required this.channel,
    required this.isRead,
    this.readAt,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) =>
      AppNotification(
        id: json['id'] ?? 0,
        type: json['type'] ?? '',
        title: json['title'] ?? '',
        message: json['message'] ?? '',
        data: json['data'] is String
            ? jsonDecode(json['data'])
            : json['data'],
        channel: json['channel'] ?? 'IN_APP',
        isRead: json['is_read'] == true || json['is_read'] == 1,
        readAt: json['read_at'],
        createdAt: json['created_at'] ?? '',
      );
}
