import 'package:flutter/material.dart';
import '../../../l10n/app_localizations.dart';
import '../../../services/procurement_service.dart';
import '../../../models/procurement.dart';
import '../../../core/theme/colors.dart';

class ProcurementScreen extends StatefulWidget {
  const ProcurementScreen({super.key});

  @override
  State<ProcurementScreen> createState() => _ProcurementScreenState();
}

class _ProcurementScreenState extends State<ProcurementScreen> {
  final _procurementService = ProcurementService();
  bool _isLoading = true;
  String? _errorMessage;
  List<Procurement> _procurements = [];

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
      final response = await _procurementService.getMyProcurements();
      if (!mounted) return;

      if (response.success && response.data is List) {
        final list = (response.data as List).map((e) => Procurement.fromJson(e as Map<String, dynamic>)).toList();
        setState(() {
          _procurements = list;
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

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.translate('procurement_title')),
        actions: [
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

    if (_procurements.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.inventory_2_outlined, size: 64, color: AppColors.grey),
            const SizedBox(height: 16),
            Text(l10n.translate('procurement_no_data'), style: theme.textTheme.titleMedium),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetch,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _procurements.length,
        itemBuilder: (context, index) => _buildProcurementTile(_procurements[index], l10n, theme),
      ),
    );
  }

  Widget _buildProcurementTile(Procurement p, AppLocalizations l10n, ThemeData theme) {
    return Card(
      elevation: 1,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 16),
        childrenPadding: const EdgeInsets.all(16),
        title: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    p.centreName ?? l10n.translate('common_no_data'),
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Booking #${p.bookingId}',
                    style: theme.textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
                  ),
                ],
              ),
            ),
            _buildStatusChip(p.status, l10n, theme),
          ],
        ),
        children: p.lines.isEmpty
            ? [
                Padding(
                  padding: const EdgeInsets.all(8),
                  child: Text(l10n.translate('common_no_data'), style: theme.textTheme.bodyMedium?.copyWith(color: AppColors.grey)),
                ),
              ]
            : p.lines.map((line) => _buildLineTile(line, l10n, theme)).toList(),
      ),
    );
  }

  Widget _buildLineTile(ProcurementLine line, AppLocalizations l10n, ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Card(
        elevation: 0,
        color: AppColors.background,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      line.cropName,
                      style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ),
                  _buildStatusChip(line.status, l10n, theme),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  _infoChip(theme, l10n.translate('procurement_quantity'), '${line.quantityKg.toStringAsFixed(1)} kg'),
                  if (line.acceptedQuantity != null) ...[
                    const SizedBox(width: 8),
                    _infoChip(theme, l10n.translate('procurement_crop_details'), '${line.acceptedQuantity!.toStringAsFixed(1)} kg'),
                  ],
                  if (line.quality != null) ...[
                    const SizedBox(width: 8),
                    _infoChip(theme, l10n.translate('procurement_quality'), line.quality!),
                  ],
                ],
              ),
              if (line.rejectionReason != null && line.rejectionReason!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  line.rejectionReason!,
                  style: theme.textTheme.bodySmall?.copyWith(color: AppColors.error),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _infoChip(ThemeData theme, String label, String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: theme.textTheme.labelSmall?.copyWith(color: AppColors.grey)),
          const SizedBox(height: 2),
          Text(value, style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _buildStatusChip(String status, AppLocalizations l10n, ThemeData theme) {
    final s = status.toUpperCase();
    Color color;
    if (s == 'PAID' || s == 'COMPLETED' || s == 'APPROVED') {
      color = AppColors.success;
    } else if (s == 'PENDING' || s == 'PROCESSING') {
      color = AppColors.secondary;
    } else if (s == 'FAILED' || s == 'REJECTED' || s == 'CANCELLED') {
      color = AppColors.error;
    } else {
      color = AppColors.grey;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color),
      ),
      child: Text(
        status.toUpperCase(),
        style: theme.textTheme.labelSmall?.copyWith(color: color, fontWeight: FontWeight.bold),
      ),
    );
  }
}
