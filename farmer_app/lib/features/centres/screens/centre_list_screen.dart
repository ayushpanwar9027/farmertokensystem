import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../services/booking_service.dart';
import '../../../models/centre.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';

class CentreListScreen extends StatefulWidget {
  const CentreListScreen({super.key});

  @override
  State<CentreListScreen> createState() => _CentreListScreenState();
}

class _CentreListScreenState extends State<CentreListScreen> {
  List<Centre> _centres = [];
  bool _loading = true;
  bool _error = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadCentres());
  }

  Future<void> _loadCentres() async {
    setState(() {
      _loading = true;
      _error = false;
    });
    try {
      final bookingService = context.read<BookingService>();
      final response = await bookingService.getCentres();
      if (!mounted) return;
      if (response.success && response.data != null) {
        final centres = (response.data as List)
            .map((e) => Centre.fromJson(e))
            .toList();
        setState(() {
          _centres = centres;
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

    return Scaffold(
      appBar: AppBar(title: Text(t('centre_list_title'))),
      body: _buildBody(t),
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
              onPressed: _loadCentres,
              icon: const Icon(Icons.refresh),
              label: Text(t('retry')),
            ),
          ],
        ),
      );
    }

    if (_centres.isEmpty) {
      return Center(
        child: Text(t('centre_list_empty')),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadCentres,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _centres.length,
        itemBuilder: (context, index) {
          final c = _centres[index];
          return _CentreCard(
            centre: c,
            onTap: () {
              Navigator.pushNamed(
                context,
                RouteNames.slotSelection,
                arguments: {
                  'centreId': c.id,
                  'centreName': c.name,
                },
              );
            },
          );
        },
      ),
    );
  }
}

class _CentreCard extends StatelessWidget {
  final Centre centre;
  final VoidCallback onTap;

  const _CentreCard({required this.centre, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 1,
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      centre.name,
                      style: const TextStyle(
                          fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                  ),
                  _StatusChip(status: centre.status),
                ],
              ),
              if (centre.code.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  centre.code,
                  style: TextStyle(
                      fontSize: 12, color: Colors.grey.shade600),
                ),
              ],
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.location_on_outlined,
                      size: 16, color: Colors.grey.shade600),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      centre.address ?? '',
                      style: TextStyle(
                          fontSize: 13, color: Colors.grey.shade700),
                    ),
                  ),
                ],
              ),
              if (centre.district != null) ...[
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.map_outlined,
                        size: 16, color: Colors.grey.shade600),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        centre.district!.name,
                        style: TextStyle(
                            fontSize: 13, color: Colors.grey.shade700),
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.access_time,
                      size: 16, color: Colors.grey.shade600),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      '${centre.workingHoursStart} - ${centre.workingHoursEnd}',
                      style: TextStyle(
                          fontSize: 13, color: Colors.grey.shade700),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Icon(Icons.chevron_right, color: Colors.grey.shade400),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;

  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final isActive = status == 'ACTIVE' || status == 'OPEN';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: isActive ? Colors.green.shade100 : Colors.grey.shade200,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        status,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.bold,
          color: isActive ? Colors.green.shade800 : Colors.grey.shade700,
        ),
      ),
    );
  }
}