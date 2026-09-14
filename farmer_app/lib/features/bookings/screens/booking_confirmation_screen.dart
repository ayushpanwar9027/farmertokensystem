import 'package:flutter/material.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';

class BookingConfirmationScreen extends StatelessWidget {
  const BookingConfirmationScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final args = ModalRoute.of(context)!.settings.arguments as Map;
    final bookingId = args['booking_id'] as int;
    final token = args['token'] as String;
    final centreName = args['centre_name'] as String;
    final date = args['date'] as String;
    final slotLabel = args['slot_label'] as String;

    final t = AppLocalizations.of(context).translate;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 88,
                height: 88,
                decoration: BoxDecoration(
                  color: Colors.green.shade100,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle,
                  size: 56,
                  color: Colors.green,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                t('booking_success'),
                style: const TextStyle(
                    fontSize: 24, fontWeight: FontWeight.bold),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                t('booking_success_subtitle'),
                style: TextStyle(fontSize: 14, color: Colors.grey.shade600),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              Card(
                elevation: 2,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: SizedBox(
                    width: double.infinity,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _TokenLine(
                          label: t('booking_confirmed_token'),
                          value: token,
                          bold: true,
                          valueColor: Colors.green.shade800,
                        ),
                        const Divider(height: 20),
                        _TokenLine(
                          label: t('booking_confirmed_centre'),
                          value: centreName,
                        ),
                        _TokenLine(
                          label: t('booking_confirmed_date'),
                          value: date,
                        ),
                        _TokenLine(
                          label: t('booking_confirmed_slot'),
                          value: slotLabel,
                        ),
                        _TokenLine(
                          label: t('booking_confirmed_id'),
                          value: '#$bookingId',
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 24),
              const Spacer(),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.pushNamed(
                      context,
                      RouteNames.liveQueue,
                      arguments: {
                        'bookingId': bookingId,
                        'date': date,
                      },
                    );
                  },
                  icon: const Icon(Icons.queue),
                  label: Text(t('booking_view_queue')),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: () {
                    Navigator.pushNamedAndRemoveUntil(
                        context, RouteNames.home, (route) => false);
                  },
                  child: Text(t('booking_back_home')),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TokenLine extends StatelessWidget {
  final String label;
  final String value;
  final bool bold;
  final Color? valueColor;

  const _TokenLine({
    required this.label,
    required this.value,
    this.bold = false,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              style: TextStyle(
                fontSize: 14,
                fontWeight: bold ? FontWeight.bold : FontWeight.w600,
                color: valueColor,
              ),
              textAlign: TextAlign.right,
            ),
          ),
        ],
      ),
    );
  }
}