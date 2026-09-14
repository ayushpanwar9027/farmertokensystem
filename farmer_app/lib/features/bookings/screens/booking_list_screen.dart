import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../services/booking_service.dart';
import '../../../models/booking.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';

class BookingListScreen extends StatefulWidget {
  final bool showAppBar;

  const BookingListScreen({super.key, this.showAppBar = true});

  @override
  State<BookingListScreen> createState() => _BookingListScreenState();
}

class _BookingListScreenState extends State<BookingListScreen> {
  List<Booking> _bookings = [];
  bool _loading = true;
  bool _error = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadBookings());
  }

  Future<void> _loadBookings() async {
    setState(() {
      _loading = true;
      _error = false;
    });
    try {
      final bookingService = context.read<BookingService>();
      final response = await bookingService.getBookings();
      if (!mounted) return;
      if (response.success && response.data != null) {
        final bookings = (response.data as List)
            .map((e) => Booking.fromJson(e))
            .toList();
        setState(() {
          _bookings = bookings;
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

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    final content = _buildContent(t);

    if (widget.showAppBar) {
      return Scaffold(
        appBar: AppBar(title: Text(t('booking_history_title'))),
        body: content,
      );
    }
    return content;
  }

  Widget _buildContent(String Function(String) t) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 48, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(t('error_loading_data')),
            const SizedBox(height: 8),
            ElevatedButton.icon(
              onPressed: _loadBookings,
              icon: const Icon(Icons.refresh),
              label: Text(t('retry')),
            ),
          ],
        ),
      );
    }

    if (_bookings.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.receipt_long, size: 48, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(t('booking_history_empty')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadBookings,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: _bookings.length,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final booking = _bookings[index];
          return _BookingCard(
            booking: booking,
            onTap: () {
              Navigator.pushNamed(
                context,
                RouteNames.bookingDetail,
                arguments: {'booking_id': booking.id},
              );
            },
          );
        },
      ),
    );
  }
}

class _BookingCard extends StatelessWidget {
  final Booking booking;
  final VoidCallback onTap;

  const _BookingCard({required this.booking, required this.onTap});

  MaterialColor get _statusColor {
    switch (booking.status) {
      case 'CONFIRMED':
        return Colors.green;
      case 'PENDING':
        return Colors.orange;
      case 'CANCELLED':
        return Colors.red;
      case 'COMPLETED':
        return Colors.grey;
      default:
        return Colors.blue;
    }
  }

  Color get _statusBgColor => _statusColor.shade100;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 1,
      margin: EdgeInsets.zero,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    booking.token,
                    style: const TextStyle(
                        fontSize: 15, fontWeight: FontWeight.bold),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: _statusBgColor,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      booking.status,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: _statusColor.shade800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.storefront_outlined,
                      size: 16, color: Colors.grey.shade600),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      booking.centreName,
                      style: const TextStyle(fontSize: 14),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.calendar_today_outlined,
                      size: 14, color: Colors.grey.shade600),
                  const SizedBox(width: 6),
                  Text(
                    booking.date,
                    style: TextStyle(
                        fontSize: 13, color: Colors.grey.shade700),
                  ),
                  if (booking.slotStart != null &&
                      booking.slotStart!.isNotEmpty) ...[
                    const SizedBox(width: 12),
                    Icon(Icons.access_time,
                        size: 14, color: Colors.grey.shade600),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        booking.slotEnd != null
                            ? '${booking.slotStart} - ${booking.slotEnd}'
                            : booking.slotStart!,
                        style: TextStyle(
                            fontSize: 13, color: Colors.grey.shade700),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}