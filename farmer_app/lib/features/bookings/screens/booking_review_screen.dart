import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../services/booking_service.dart';
import '../../../models/booking.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';

class BookingReviewScreen extends StatefulWidget {
  const BookingReviewScreen({super.key});

  @override
  State<BookingReviewScreen> createState() => _BookingReviewScreenState();
}

class _BookingReviewScreenState extends State<BookingReviewScreen> {
  late int _centreId;
  late String _centreName;
  late String _date;
  late int _slotId;
  late String _slotLabel;
  late List<Map<String, dynamic>> _crops;

  bool _submitting = false;
  String? _errorMessage;
  bool _argsLoaded = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_argsLoaded) return;
    _argsLoaded = true;
    final args = ModalRoute.of(context)!.settings.arguments as Map;
    _centreId = args['centreId'] as int;
    _centreName = args['centreName'] as String;
    _date = args['date'] as String;
    _slotId = args['slotId'] as int;
    _slotLabel = args['slotLabel'] as String;
    _crops = (args['crops'] as List)
        .map((e) => e as Map<String, dynamic>)
        .toList();
  }

  double get _totalQuantity {
    double total = 0;
    for (final c in _crops) {
      total += (c['quantity_kg'] as num).toDouble();
    }
    return total;
  }

  Future<void> _confirm() async {
    if (_submitting) return;
    setState(() {
      _submitting = true;
      _errorMessage = null;
    });

    final bookingService = context.read<BookingService>();
    final payload = {
      'centre_id': _centreId,
      'date': _date,
      'slot_id': _slotId,
      'crops': _crops,
    };

    final response = await bookingService.createBooking(payload);
    if (!mounted) return;

    if (response.success && response.data != null) {
      Map<String, dynamic> bookingMap;
      if (response.data is Map && (response.data as Map).containsKey('booking')) {
        bookingMap = (response.data as Map)['booking'] as Map<String, dynamic>;
      } else if (response.data is Map) {
        final raw = response.data as Map;
        bookingMap = {
          'id': raw['id'] ?? 0,
          'token': raw['token'] ?? '--',
          'centre_id': raw['centre_id'] ?? _centreId,
          'centre_name': raw['centre_name'] ?? _centreName,
          'date': raw['date'] ?? _date,
          'slot_start': raw['slot_start'] ?? '',
          'slot_end': raw['slot_end'] ?? '',
          'status': raw['status'] ?? 'CONFIRMED',
        };
      } else {
        bookingMap = {'id': 0, 'token': '--'};
      }

      final booking = Booking.fromJson(bookingMap);
      _setSubmitting(false);
      Navigator.pushReplacementNamed(
        context,
        RouteNames.bookingConfirmation,
        arguments: {
          'booking_id': booking.id,
          'token': booking.token.isEmpty ? '--' : booking.token,
          'centre_name': _centreName,
          'date': _date,
          'slot_label': _slotLabel,
        },
      );
    } else {
      final t = AppLocalizations.of(context).translate;
      String message = response.errorMessage ?? t('booking_error');
      if (response.errorCode == 'SLOT_FULL') {
        message = t('booking_slot_full');
      }
      _setSubmitting(false);
      if (!mounted) return;
      setState(() {
        _errorMessage = message;
      });
    }
  }

  void _setSubmitting(bool value) {
    if (mounted) {
      setState(() {
        _submitting = value;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    return Scaffold(
      appBar: AppBar(title: Text(t('booking_review_title'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _SectionCard(
            title: t('booking_review_centre'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _centreName,
                  style: const TextStyle(
                      fontSize: 15, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 4),
                Text(
                  _date,
                  style: TextStyle(fontSize: 13, color: Colors.grey.shade700),
                ),
                const SizedBox(height: 2),
                Text(
                  _slotLabel,
                  style: TextStyle(fontSize: 13, color: Colors.grey.shade700),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          _SectionCard(
            title: t('booking_review_crops'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final crop in _crops)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(crop['crop_name'] as String? ?? ''),
                        ),
                        Text(
                          '${crop['quantity_kg']} kg',
                          style: const TextStyle(
                              fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                const Divider(),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      t('booking_review_total_qty'),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    Text(
                      '${_totalQuantity.toStringAsFixed(2)} kg',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (_errorMessage != null) ...[
            const SizedBox(height: 12),
            Text(
              _errorMessage!,
              style: const TextStyle(color: Colors.red, fontSize: 13),
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: ElevatedButton(
            onPressed: _submitting ? null : _confirm,
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 16),
            ),
            child: _submitting
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(t('booking_review_confirm')),
          ),
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final Widget child;

  const _SectionCard({required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                  fontSize: 15, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            child,
          ],
        ),
      ),
    );
  }
}