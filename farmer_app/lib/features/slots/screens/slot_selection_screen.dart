import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../../services/booking_service.dart';
import '../../../models/slot.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';

class SlotSelectionScreen extends StatefulWidget {
  const SlotSelectionScreen({super.key});

  @override
  State<SlotSelectionScreen> createState() => _SlotSelectionScreenState();
}

class _SlotSelectionScreenState extends State<SlotSelectionScreen> {
  late int _centreId;
  late String _centreName;
  late List<DateTime> _dates;
  late String _selectedDate;
  List<Slot> _slots = [];
  bool _loadingSlots = false;
  String? _errorMessage;
  bool _argsLoaded = false;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _dates = List.generate(
        7, (i) => DateTime(now.year, now.month, now.day + i));
    _selectedDate = DateFormat('yyyy-MM-dd').format(_dates.first);
    WidgetsBinding.instance.addPostFrameCallback((_) => _fetchSlots());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_argsLoaded) return;
    _argsLoaded = true;
    final args =
        ModalRoute.of(context)!.settings.arguments as Map;
    _centreId = args['centreId'] as int;
    _centreName = args['centreName'] as String;
  }

  Future<void> _fetchSlots() async {
    setState(() {
      _loadingSlots = true;
      _errorMessage = null;
    });
    try {
      final bookingService = context.read<BookingService>();
      final response = await bookingService.getSlots(_centreId, _selectedDate);
      if (!mounted) return;
      if (response.success && response.data != null) {
        final slots = (response.data as List)
            .map((e) => Slot.fromJson(e))
            .toList();
        setState(() {
          _slots = slots;
          _loadingSlots = false;
        });
      } else {
        setState(() {
          _errorMessage =
              response.errorMessage ?? 'Failed to load slots';
          _loadingSlots = false;
        });
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Failed to load slots';
        _loadingSlots = false;
      });
    }
  }

  void _selectDate(DateTime date) {
    setState(() {
      _selectedDate = DateFormat('yyyy-MM-dd').format(date);
    });
    _fetchSlots();
  }

  void _selectSlot(Slot slot) {
    Navigator.pushNamed(
      context,
      RouteNames.bookCrops,
      arguments: {
        'centreId': _centreId,
        'centreName': _centreName,
        'date': _selectedDate,
        'slotId': slot.id,
        'slotLabel': '${slot.startTime}-${slot.endTime}',
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    return Scaffold(
      appBar: AppBar(
        title: Text(t('slot_selection_title')),
      ),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            color: Theme.of(context).colorScheme.primaryContainer,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Text(
              _centreName,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          _buildDateSelector(t),
          const Divider(height: 1),
          Expanded(child: _buildSlotSection(t)),
        ],
      ),
    );
  }

  Widget _buildDateSelector(String Function(String) t) {
    return SizedBox(
      height: 92,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        scrollDirection: Axis.horizontal,
        itemCount: _dates.length,
        separatorBuilder: (_, _) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final date = _dates[index];
          final dateStr = DateFormat('yyyy-MM-dd').format(date);
          final isSelected = dateStr == _selectedDate;
          final isToday = index == 0;

          return GestureDetector(
            onTap: () => _selectDate(date),
            child: Container(
              width: 68,
              decoration: BoxDecoration(
                color: isSelected
                    ? Theme.of(context).colorScheme.primary
                    : Colors.grey.shade100,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: isSelected
                      ? Theme.of(context).colorScheme.primary
                      : Colors.grey.shade300,
                ),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    DateFormat('EEE').format(date),
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      color: isSelected
                          ? Colors.white
                          : Colors.grey.shade700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    DateFormat('d').format(date),
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: isSelected
                          ? Colors.white
                          : Theme.of(context).colorScheme.onSurface,
                    ),
                  ),
                  Text(
                    isToday
                        ? t('slot_today')
                        : DateFormat('MMM').format(date),
                    style: TextStyle(
                      fontSize: 11,
                      color: isSelected
                          ? Colors.white.withValues(alpha: 0.9)
                          : Colors.grey.shade600,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildSlotSection(String Function(String) t) {
    if (_loadingSlots) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 48, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(_errorMessage!),
            const SizedBox(height: 8),
            ElevatedButton.icon(
              onPressed: _fetchSlots,
              icon: const Icon(Icons.refresh),
              label: Text(t('retry')),
            ),
          ],
        ),
      );
    }

    if (_slots.isEmpty) {
      return Center(
        child: Text(t('slot_no_slots')),
      );
    }

    return Padding(
      padding: const EdgeInsets.all(16),
      child: GridView.builder(
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.5,
        ),
        itemCount: _slots.length,
        itemBuilder: (context, index) {
          final slot = _slots[index];
          return _SlotCard(
            slot: slot,
            onTap: slot.isFull ? null : () => _selectSlot(slot),
          );
        },
      ),
    );
  }
}

class _SlotCard extends StatelessWidget {
  final Slot slot;
  final VoidCallback? onTap;

  const _SlotCard({required this.slot, this.onTap});

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    return Card(
      elevation: 1,
      color: slot.isFull ? Colors.grey.shade100 : null,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    '${slot.startTime}-${slot.endTime}',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: slot.isFull
                          ? Colors.grey.shade500
                          : Theme.of(context).colorScheme.onSurface,
                    ),
                  ),
                  if (slot.isFull)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.red.shade100,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        t('slot_full'),
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: Colors.red.shade800,
                        ),
                      ),
                    ),
                ],
              ),
              const Spacer(),
              Text(
                '${slot.available} ${t('slot_available')}',
                style: TextStyle(
                  fontSize: 13,
                  color: slot.isFull
                      ? Colors.grey.shade500
                      : Colors.green.shade700,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 8),
              LinearProgressIndicator(
                value: slot.capacity > 0 ? slot.booked / slot.capacity : 0,
                backgroundColor: Colors.grey.shade200,
                color: slot.isFull
                    ? Colors.red
                    : Theme.of(context).colorScheme.primary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}