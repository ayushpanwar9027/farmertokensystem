import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../services/booking_service.dart';
import '../../../models/crop.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';

class BookCropsScreen extends StatefulWidget {
  const BookCropsScreen({super.key});

  @override
  State<BookCropsScreen> createState() => _BookCropsScreenState();
}

class _BookCropsScreenState extends State<BookCropsScreen> {
  late int _centreId;
  late String _centreName;
  late String _date;
  late int _slotId;
  late String _slotLabel;

  List<Crop> _crops = [];
  final Map<int, TextEditingController> _qtyControllers = {};
  bool _loading = true;
  bool _error = false;
  bool _argsLoaded = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadCrops());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_argsLoaded) return;
    _argsLoaded = true;
    final args = ModalRoute.of(context)!.settings.arguments as Map<String, dynamic>;
    _centreId = args['centreId'] as int;
    _centreName = args['centreName'] as String;
    _date = args['date'] as String;
    _slotId = args['slotId'] as int;
    _slotLabel = args['slotLabel'] as String;
  }

  @override
  void dispose() {
    for (final c in _qtyControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _loadCrops() async {
    setState(() {
      _loading = true;
      _error = false;
    });
    try {
      final bookingService = context.read<BookingService>();
      final response = await bookingService.getCrops();
      if (!mounted) return;
      if (response.success && response.data != null) {
        final crops = (response.data as List)
            .map((e) => Crop.fromJson(e))
            .toList();
        setState(() {
          _crops = crops;
          _qtyControllers.clear();
          for (final c in crops) {
            _qtyControllers[c.id] = TextEditingController();
          }
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

  int get _selectedCount {
    int count = 0;
    for (final c in _crops) {
      final qty = double.tryParse(_qtyControllers[c.id]?.text.trim() ?? '');
      if (qty != null && qty > 0) count++;
    }
    return count;
  }

  void _reviewBooking() {
    final selected = <Map<String, dynamic>>[];
    for (final c in _crops) {
      final qty = double.tryParse(_qtyControllers[c.id]?.text.trim() ?? '');
      if (qty != null && qty > 0) {
        selected.add({
          'crop_id': c.id,
          'crop_name': c.name,
          'quantity_kg': qty,
        });
      }
    }

    Navigator.pushNamed(
      context,
      RouteNames.bookingReview,
      arguments: {
        'centreId': _centreId,
        'centreName': _centreName,
        'date': _date,
        'slotId': _slotId,
        'slotLabel': _slotLabel,
        'crops': selected,
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    return Scaffold(
      appBar: AppBar(title: Text(t('book_crops_title'))),
      body: _buildBody(t),
      bottomNavigationBar: _buildReviewBar(t),
    );
  }

  Widget _buildBody(String Function(String) t) {
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
              onPressed: _loadCrops,
              icon: const Icon(Icons.refresh),
              label: Text(t('retry')),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        Container(
          width: double.infinity,
          color: Theme.of(context).colorScheme.primaryContainer,
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _centreName,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 4),
              Text(
                '$_date  •  $_slotLabel',
                style: TextStyle(
                    fontSize: 13, color: Colors.grey.shade700),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: _crops.isEmpty
              ? Center(child: Text(t('book_crops_empty')))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _crops.length,
                  itemBuilder: (context, index) {
                    final crop = _crops[index];
                    return _CropQtyCard(
                      crop: crop,
                      controller: _qtyControllers[crop.id]!,
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _buildReviewBar(String Function(String) t) {
    final count = _selectedCount;
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: ElevatedButton(
          onPressed: count > 0 ? _reviewBooking : null,
          style: ElevatedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 16),
          ),
          child: Text('${t('book_crops_review')} ($count)'),
        ),
      ),
    );
  }
}

class _CropQtyCard extends StatelessWidget {
  final Crop crop;
  final TextEditingController controller;

  const _CropQtyCard({required this.crop, required this.controller});

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    return Card(
      elevation: 1,
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: Theme.of(context)
                    .colorScheme
                    .primary
                    .withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.eco, size: 22),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    crop.name,
                    style: const TextStyle(
                        fontSize: 15, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    crop.category ?? '',
                    style: TextStyle(
                        fontSize: 12, color: Colors.grey.shade600),
                  ),
                  if (crop.mspPrice != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      '${t('book_crops_msp')}: ₹${crop.mspPrice}',
                      style: const TextStyle(
                          fontSize: 12,
                          color: Colors.green,
                          fontWeight: FontWeight.w500),
                    ),
                  ],
                ],
              ),
            ),
            SizedBox(
              width: 90,
              child: TextField(
                controller: controller,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                textAlign: TextAlign.center,
                decoration: InputDecoration(
                  isDense: true,
                  hintText: t('book_crops_qty_hint'),
                  suffixText: 'kg',
                  border: const OutlineInputBorder(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}