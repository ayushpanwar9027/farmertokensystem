import 'package:flutter/material.dart';
import '../../../l10n/app_localizations.dart';
import '../../../services/notification_service.dart';
import '../../../models/app_notification.dart';
import '../../../core/theme/colors.dart';

class NotificationDetailScreen extends StatefulWidget {
  const NotificationDetailScreen({super.key});

  @override
  State<NotificationDetailScreen> createState() => _NotificationDetailScreenState();
}

class _NotificationDetailScreenState extends State<NotificationDetailScreen> {
  final _notificationService = NotificationService();
  bool _isLoading = true;
  AppNotification? _notification;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final args = ModalRoute.of(context)?.settings.arguments as Map? ?? {};
    final notificationId = args['notification_id'] as int?;

    if (notificationId == null) {
      setState(() {
        _isLoading = false;
        _error = 'Notification not found';
      });
      return;
    }

    try {
      final response = await _notificationService.getNotifications();
      if (!mounted) return;

      if (response.success && response.data is List) {
        final list = (response.data as List).map((e) => AppNotification.fromJson(e as Map<String, dynamic>)).toList();
        final found = list.where((n) => n.id == notificationId).firstOrNull;
        if (found != null) {
          setState(() {
            _notification = found;
            _isLoading = false;
          });
          if (!found.isRead) {
            _notificationService.markRead(found.id);
          }
        } else {
          setState(() {
            _isLoading = false;
            _error = 'Notification not found';
          });
        }
      } else {
        setState(() {
          _isLoading = false;
          _error = response.errorMessage;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = e.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.translate('notifications_title')),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                      const SizedBox(height: 16),
                      Text(_error!, style: theme.textTheme.bodyLarge),
                    ],
                  ),
                )
              : _notification == null
                  ? Center(child: Text(l10n.translate('common_no_data')))
                  : _buildContent(_notification!, l10n, theme),
    );
  }

  Widget _buildContent(AppNotification n, AppLocalizations l10n, ThemeData theme) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Card(
            elevation: 2,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: AppColors.info.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(Icons.notifications, color: AppColors.info, size: 24),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              n.title,
                              style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 4),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: AppColors.info.withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                n.type,
                                style: theme.textTheme.labelSmall?.copyWith(color: AppColors.info),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      const Icon(Icons.access_time, size: 16, color: AppColors.grey),
                      const SizedBox(width: 8),
                      Text(
                        n.createdAt,
                        style: theme.textTheme.bodySmall?.copyWith(color: AppColors.grey),
                      ),
                    ],
                  ),
                  const Divider(height: 24),
                  Text(
                    n.message,
                    style: theme.textTheme.bodyLarge?.copyWith(height: 1.6),
                  ),
                ],
              ),
            ),
          ),
          if (n.data != null && n.data!.isNotEmpty) ...[
            const SizedBox(height: 16),
            Card(
              elevation: 1,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l10n.translate('procurement_crop_details'),
                      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 12),
                    ...n.data!.entries.map((entry) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            flex: 2,
                            child: Text(
                              entry.key,
                              style: theme.textTheme.bodySmall?.copyWith(color: AppColors.grey, fontWeight: FontWeight.w500),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            flex: 3,
                            child: Text(entry.value?.toString() ?? '', style: theme.textTheme.bodyMedium),
                          ),
                        ],
                      ),
                    )),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
