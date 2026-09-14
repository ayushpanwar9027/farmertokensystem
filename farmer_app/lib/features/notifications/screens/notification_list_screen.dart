import 'package:flutter/material.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';
import '../../../services/notification_service.dart';
import '../../../models/app_notification.dart';
import '../../../core/theme/colors.dart';

class NotificationListScreen extends StatefulWidget {
  const NotificationListScreen({super.key});

  @override
  State<NotificationListScreen> createState() => _NotificationListScreenState();
}

class _NotificationListScreenState extends State<NotificationListScreen> {
  final _notificationService = NotificationService();
  bool _isLoading = true;
  String? _errorMessage;
  List<AppNotification> _notifications = [];

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  Future<void> _fetch() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await _notificationService.getNotifications();
      if (!mounted) return;

      if (response.success && response.data is List) {
        final list = (response.data as List).map((e) => AppNotification.fromJson(e as Map<String, dynamic>)).toList();
        setState(() {
          _notifications = list;
          _isLoading = false;
        });
      } else {
        setState(() {
          _isLoading = false;
          _errorMessage = response.errorMessage;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  Future<void> _markAllRead() async {
    try {
      await _notificationService.markAllRead();
      if (mounted) {
        setState(() {
          _notifications = _notifications.map((n) => AppNotification(
            id: n.id,
            type: n.type,
            title: n.title,
            message: n.message,
            data: n.data,
            channel: n.channel,
            isRead: true,
            readAt: n.readAt ?? DateTime.now().toIso8601String(),
            createdAt: n.createdAt,
          )).toList();
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.translate('notifications_title')),
        actions: [
          if (_notifications.isNotEmpty)
            TextButton(
              onPressed: _markAllRead,
              child: Text(
                l10n.translate('notifications_mark_all_read'),
                style: theme.textTheme.labelLarge?.copyWith(color: AppColors.primary),
              ),
            ),
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetch),
        ],
      ),
      body: _buildBody(l10n, theme),
    );
  }

  Widget _buildBody(AppLocalizations l10n, ThemeData theme) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: AppColors.error),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: theme.textTheme.bodyLarge),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _fetch,
              child: Text(l10n.translate('common_retry')),
            ),
          ],
        ),
      );
    }

    if (_notifications.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.notifications_none, size: 64, color: AppColors.grey),
            const SizedBox(height: 16),
            Text(l10n.translate('notifications_no_notifications'), style: theme.textTheme.titleMedium),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetch,
      child: ListView.builder(
        padding: const EdgeInsets.all(8),
        itemCount: _notifications.length,
        itemBuilder: (context, index) => _buildNotificationCard(_notifications[index], l10n, theme),
      ),
    );
  }

  Widget _buildNotificationCard(AppNotification n, AppLocalizations l10n, ThemeData theme) {
    final isRead = n.isRead;

    return Card(
      elevation: isRead ? 0 : 1,
      margin: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      color: isRead ? null : AppColors.info.withValues(alpha: 0.04),
      child: ListTile(
        contentPadding: const EdgeInsets.all(12),
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: isRead ? AppColors.grey.withValues(alpha: 0.1) : AppColors.info.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(
            Icons.notifications,
            color: isRead ? AppColors.grey : AppColors.info,
            size: 24,
          ),
        ),
        title: Text(
          n.title,
          style: theme.textTheme.titleSmall?.copyWith(
            fontWeight: isRead ? FontWeight.normal : FontWeight.bold,
          ),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text(
            n.message,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
          ),
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (!isRead)
              Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: AppColors.info,
                  shape: BoxShape.circle,
                ),
              ),
            const SizedBox(height: 4),
            Text(
              n.createdAt,
              style: theme.textTheme.labelSmall?.copyWith(color: AppColors.grey),
            ),
          ],
        ),
        onTap: () {
          Navigator.pushNamed(
            context,
            RouteNames.notificationDetail,
            arguments: {'notification_id': n.id},
          ).then((_) => _fetch());
        },
      ),
    );
  }
}
