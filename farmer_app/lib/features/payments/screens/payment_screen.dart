import 'package:flutter/material.dart';
import '../../../l10n/app_localizations.dart';
import '../../../services/payment_service.dart';
import '../../../models/payment.dart';
import '../../../core/theme/colors.dart';

class PaymentScreen extends StatefulWidget {
  const PaymentScreen({super.key});

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  final _paymentService = PaymentService();
  bool _isLoading = true;
  String? _errorMessage;
  List<Payment> _payments = [];

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
      final response = await _paymentService.getMyPayments();
      if (!mounted) return;

      if (response.success && response.data is List) {
        final list = (response.data as List).map((e) => Payment.fromJson(e as Map<String, dynamic>)).toList();
        setState(() {
          _payments = list;
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
        title: Text(l10n.translate('payment_title')),
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

    if (_payments.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.payment_outlined, size: 64, color: AppColors.grey),
            const SizedBox(height: 16),
            Text(l10n.translate('payment_no_data'), style: theme.textTheme.titleMedium),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetch,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _payments.length,
        itemBuilder: (context, index) => _buildPaymentCard(_payments[index], l10n, theme),
      ),
    );
  }

  Widget _buildPaymentCard(Payment p, AppLocalizations l10n, ThemeData theme) {
    return Card(
      elevation: 2,
      margin: const EdgeInsets.only(bottom: 12),
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
                    color: AppColors.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.account_balance_wallet, color: AppColors.primary, size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '₹${p.amount.toStringAsFixed(2)}',
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: AppColors.primary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        p.method,
                        style: theme.textTheme.bodySmall?.copyWith(color: AppColors.grey),
                      ),
                    ],
                  ),
                ),
                _buildStatusChip(p.status, l10n, theme),
              ],
            ),
            const Divider(height: 24),
            Row(
              children: [
                const Icon(Icons.receipt_long, size: 16, color: AppColors.grey),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '${l10n.translate('payment_reference')}: ${p.reference}',
                    style: theme.textTheme.bodySmall,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.calendar_today, size: 16, color: AppColors.grey),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    p.paidAt ?? p.createdAt,
                    style: theme.textTheme.bodySmall,
                  ),
                ),
              ],
            ),
          ],
        ),
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
