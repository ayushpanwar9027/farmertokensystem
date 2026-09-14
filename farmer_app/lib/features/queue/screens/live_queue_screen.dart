import 'dart:async';
import 'package:flutter/material.dart';
import '../../../l10n/app_localizations.dart';
import '../../../services/queue_service.dart';
import '../../../core/theme/colors.dart';

class LiveQueueScreen extends StatefulWidget {
  const LiveQueueScreen({super.key});

  @override
  State<LiveQueueScreen> createState() => _LiveQueueScreenState();
}

class _LiveQueueScreenState extends State<LiveQueueScreen> {
  final _queueService = QueueService();
  Timer? _pollTimer;
  bool _isLoading = true;
  int _consecutiveErrors = 0;

  Map? _myEntry;
  int _farmersAhead = 0;
  String _estimatedWait = '--';
  String _currentToken = '--';
  String? _centreName;

  bool _useLiveQueue = false;
  int? _centreId;
  String? _date;
  int? _bookingId;
  bool _argsLoaded = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_argsLoaded) return;
    _argsLoaded = true;
    final args = ModalRoute.of(context)?.settings.arguments as Map? ?? {};
    if (args.containsKey('bookingId')) {
      _useLiveQueue = true;
      _centreId = args['centreId'] as int?;
      _date = args['date'] as String?;
      _bookingId = args['bookingId'] as int?;
    }
    _poll();
    _startPolling();
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) => _poll());
  }

  Future<void> _poll() async {
    try {
      final response = _useLiveQueue && _centreId != null && _date != null && _bookingId != null
          ? await _queueService.getLiveQueue(
              centreId: _centreId!,
              date: _date!,
              bookingId: _bookingId!,
            )
          : await _queueService.getMyQueue();

      if (!mounted) return;

      if (response.success && response.data is Map) {
        final data = response.data as Map;
        setState(() {
          _myEntry = data['my_entry'] is Map ? data['my_entry'] as Map : null;
          _farmersAhead = data['farmers_ahead'] ?? 0;
          _estimatedWait = data['estimated_wait'] ?? '--';
          _currentToken = data['current_token'] ?? '--';
          _centreName = data['centre_name'] as String?;
          _isLoading = false;
          _consecutiveErrors = 0;
        });
      } else {
        setState(() {
          _isLoading = false;
          _consecutiveErrors++;
        });
        if (_consecutiveErrors == 1 && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response.errorMessage ?? AppLocalizations.of(context).translate('error_network')),
              backgroundColor: AppColors.error,
            ),
          );
        }
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _consecutiveErrors++;
      });
    }
  }

  String _statusLabel(String status) {
    final l10n = AppLocalizations.of(context);
    switch (status) {
      case 'waiting':
        return l10n.translate('queue_status_waiting');
      case 'called':
        return l10n.translate('queue_status_called');
      case 'in_progress':
        return l10n.translate('queue_status_in_progress');
      case 'completed':
        return l10n.translate('queue_status_completed');
      case 'skipped':
        return l10n.translate('queue_status_skipped');
      case 'cancelled':
        return l10n.translate('queue_status_cancelled');
      default:
        return status;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.translate('queue_title')),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _poll,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _poll,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_centreName != null) ...[
                    Card(
                      elevation: 1,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          children: [
                            const Icon(Icons.store, color: AppColors.primary),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                _centreName!,
                                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  Card(
                    elevation: 3,
                    color: AppColors.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        children: [
                          Text(
                            l10n.translate('queue_current_token'),
                            style: theme.textTheme.bodyMedium?.copyWith(color: Colors.white),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            _currentToken,
                            style: theme.textTheme.displayMedium?.copyWith(
                              fontWeight: FontWeight.bold,
                              color: Colors.white,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (_myEntry != null) ...[
                    _buildInfoCard(
                      l10n: l10n,
                      theme: theme,
                      icon: Icons.person,
                      title: l10n.translate('queue_your_position'),
                      value: _statusLabel(_myEntry!['status'] ?? ''),
                      color: AppColors.secondary,
                    ),
                    const SizedBox(height: 12),
                  ] else ...[
                    Card(
                      elevation: 1,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          children: [
                            const Icon(Icons.info_outline, color: AppColors.grey),
                            const SizedBox(width: 12),
                            Text(
                              l10n.translate('queue_not_in_queue'),
                              style: theme.textTheme.bodyLarge?.copyWith(color: AppColors.textSecondary),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  _buildInfoCard(
                    l10n: l10n,
                    theme: theme,
                    icon: Icons.people,
                    title: l10n.translate('queue_farmers_ahead'),
                    value: _farmersAhead.toString(),
                    color: _farmersAhead > 0 ? AppColors.warning : AppColors.success,
                  ),
                  const SizedBox(height: 12),
                  _buildInfoCard(
                    l10n: l10n,
                    theme: theme,
                    icon: Icons.schedule,
                    title: l10n.translate('queue_estimated_wait'),
                    value: _estimatedWait,
                    color: AppColors.info,
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildInfoCard({
    required AppLocalizations l10n,
    required ThemeData theme,
    required IconData icon,
    required String title,
    required String value,
    required Color color,
  }) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: color, size: 28),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: theme.textTheme.bodyMedium?.copyWith(color: AppColors.grey),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    value,
                    style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
