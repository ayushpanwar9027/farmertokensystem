import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../services/booking_service.dart';
import '../../../models/booking.dart';
import '../../../l10n/app_localizations.dart';

class BookingDetailScreen extends StatefulWidget {
  const BookingDetailScreen({super.key});

  @override
  State<BookingDetailScreen> createState() => _BookingDetailScreenState();
}

class _BookingDetailScreenState extends State<BookingDetailScreen> {
  late int _bookingId;
  Booking? _booking;
  bool _loading = true;
  bool _error = false;
  bool _argsLoaded = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadBooking());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_argsLoaded) return;
    _argsLoaded = true;
    final args = ModalRoute.of(context)!.settings.arguments as Map;
    _bookingId = args['booking_id'] as int;
  }

  Future<void> _loadBooking() async {
    setState(() {
      _loading = true;
      _error = false;
    });
    try {
      final bookingService = context.read<BookingService>();
      final response = await bookingService.getBookingDetail(_bookingId);
      if (!mounted) return;
      if (response.success && response.data != null) {
        setState(() {
          _booking = Booking.fromJson(
              Map<String, dynamic>.from(response.data as Map));
          _loading = false;
        });
      } else {
        setState(() {
          _error = true;
          _loading = false;
        });
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = true;
        _loading = false;
      });
    }
  }

  Future<void> _confirmCancel() async {
    final t = AppLocalizations.of(context).translate;
    final reasonController = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: Text(t('booking_cancel_title')),
          content: TextField(
            controller: reasonController,
            maxLines: 3,
            maxLength: 200,
            decoration: InputDecoration(
              labelText: t('booking_cancel_reason'),
              hintText: t('booking_cancel_reason_hint'),
              border: const OutlineInputBorder(),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: Text(t('cancel')),
            ),
            ElevatedButton(
              onPressed: () {
                final reason = reasonController.text.trim();
                if (reason.isEmpty) return;
                Navigator.of(ctx).pop(reason);
              },
              child: Text(t('booking_cancel_confirm')),
            ),
          ],
        );
      },
    );

    reasonController.dispose();
    if (result == null || result.isEmpty) return;
    if (!mounted) return;

    final bookingService = context.read<BookingService>();
    final current = _booking;
    if (current == null) return;

    final response = await bookingService.cancelBooking(current.id, result);
    if (!mounted) return;

    if (response.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('booking_cancel_success'))),
      );
      _loadBooking();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(response.errorMessage ?? t('booking_error')),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    return Scaffold(
      appBar: AppBar(title: Text(t('booking_detail_title'))),
      body: _buildBody(t),
    );
  }

  Widget _buildBody(String Function(String) t) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error || _booking == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 48, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(t('error_loading_data')),
            const SizedBox(height: 8),
            ElevatedButton.icon(
              onPressed: _loadBooking,
              icon: const Icon(Icons.refresh),
              label: Text(t('retry')),
            ),
          ],
        ),
      );
    }

    final b = _booking!;

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _buildStatusHeader(b, t),
              const SizedBox(height: 16),
              _buildDetailSection(t),
              const SizedBox(height: 16),
              if (b.crops.isNotEmpty) _buildCropsSection(b, t),
              if (b.cancelReason != null && b.cancelReason!.isNotEmpty) ...[
                const SizedBox(height: 16),
                _buildCancellationSection(b, t),
              ],
            ],
          ),
        ),
        if (b.status != 'CANCELLED' && (b.isCancellable))
          _buildCancelBar(t),
      ],
    );
  }

  Widget _buildStatusHeader(Booking b, String Function(String) t) {
    MaterialColor color;
    switch (b.status) {
      case 'CONFIRMED':
        color = Colors.green;
        break;
      case 'PENDING':
        color = Colors.orange;
        break;
      case 'CANCELLED':
        color = Colors.red;
        break;
      case 'COMPLETED':
        color = Colors.grey;
        break;
      default:
        color = Colors.blue;
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.shade100,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(Icons.event, color: color, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(b.token, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                if (b.cancelledAt != null)
                  Text(
                    t('booking_cancelled_at'),
                    style: TextStyle(fontSize: 12, color: color.shade800),
                  ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              b.status,
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: color.shade800,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailSection(String Function(String) t) {
    final b = _booking!;
    return Card(
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(t('booking_detail_info'),
                style: const TextStyle(
                    fontSize: 15, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            _DetailRow(label: t('booking_confirmed_centre'), value: b.centreName),
            _DetailRow(label: t('booking_confirmed_date'), value: b.date),
            _DetailRow(
              label: t('booking_confirmed_slot'),
              value: '${b.slotStart} - ${b.slotEnd}',
            ),
            _DetailRow(label: t('booking_confirmed_token'), value: b.token),
          ],
        ),
      ),
    );
  }

  Widget _buildCropsSection(Booking b, String Function(String) t) {
    return Card(
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(t('booking_review_crops'),
                style: const TextStyle(
                    fontSize: 15, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            Table(
              columnWidths: const {
                0: FlexColumnWidth(2),
                1: FlexColumnWidth(1),
                2: FlexColumnWidth(1),
              },
              children: [
                TableRow(
                  decoration: BoxDecoration(
                    color: Colors.grey.shade100,
                  ),
                  children: [
                    _TableCell(t('booking_crop_name'), header: true),
                    _TableCell(t('booking_crop_qty'), header: true, center: true),
                    _TableCell(t('booking_crop_status'), header: true, center: true),
                  ],
                ),
                for (final crop in b.crops)
                  TableRow(
                    children: [
                      _TableCell(crop.cropName),
                      _TableCell('${crop.quantityKg} kg', center: true),
                      _TableCell(crop.status, center: true),
                    ],
                  ),
              ],
            ),
            if (b.crops.any((c) => (c.quality?.isNotEmpty ?? false) ||
                c.acceptedQuantity != null ||
                (c.notes?.isNotEmpty ?? false)))
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(t('booking_crop_result'),
                        style: const TextStyle(
                            fontSize: 14, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    for (final crop in b.crops)
                      if (crop.quality != null ||
                          crop.acceptedQuantity != null ||
                          crop.notes != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(crop.cropName,
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w600)),
                              const SizedBox(height: 2),
                              if (crop.quality != null && crop.quality!.isNotEmpty)
                                Text(
                                  '${t('booking_crop_quality')}: ${crop.quality}',
                                  style: TextStyle(
                                      fontSize: 12, color: Colors.grey.shade700),
                                ),
                              if (crop.acceptedQuantity != null)
                                Text(
                                  '${t('booking_crop_accepted')}: ${crop.acceptedQuantity} kg',
                                  style: TextStyle(
                                      fontSize: 12, color: Colors.grey.shade700),
                                ),
                              if (crop.notes != null && crop.notes!.isNotEmpty)
                                Text(
                                  '${t('booking_crop_notes')}: ${crop.notes}',
                                  style: TextStyle(
                                      fontSize: 12, color: Colors.grey.shade700),
                                ),
                            ],
                          ),
                        ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildCancellationSection(Booking b, String Function(String) t) {
    return Card(
      elevation: 1,
      color: Colors.red.shade50,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: Colors.red.shade200),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(t('booking_cancelled_info'),
                style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: Colors.red.shade800)),
            const SizedBox(height: 8),
            Text(
              b.cancelReason ?? '',
              style: const TextStyle(fontSize: 13),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCancelBar(String Function(String) t) {
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: _confirmCancel,
            style: OutlinedButton.styleFrom(
              foregroundColor: Colors.red,
              side: BorderSide(color: Colors.red.shade400),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
            icon: const Icon(Icons.cancel_outlined),
            label: Text(t('booking_cancel_button')),
          ),
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;

  const _DetailRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label,
              style: TextStyle(fontSize: 13, color: Colors.grey.shade600)),
          Flexible(
            child: Text(
              value,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              textAlign: TextAlign.right,
            ),
          ),
        ],
      ),
    );
  }
}

class _TableCell extends StatelessWidget {
  final String text;
  final bool header;
  final bool center;

  const _TableCell(this.text, {this.header = false, this.center = false});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
      child: Text(
        text,
        style: TextStyle(
          fontSize: header ? 13 : 13,
          fontWeight: header ? FontWeight.bold : FontWeight.normal,
        ),
        textAlign: center ? TextAlign.center : TextAlign.left,
      ),
    );
  }
}