import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../services/booking_service.dart';
import '../../../models/booking.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';
import '../../bookings/screens/booking_list_screen.dart';
import '../../queue/screens/live_queue_screen.dart';
import '../../settings/screens/settings_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    final pages = <Widget>[
      _DashboardBody(
        onNavigate: (index) => setState(() => _currentIndex = index),
      ),
      const BookingListScreen(showAppBar: false),
      const LiveQueueScreen(),
      const _NotificationsPlaceholder(),
      const SettingsScreen(),
    ];

    return Scaffold(
      body: IndexedStack(index: _currentIndex, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (index) =>
            setState(() => _currentIndex = index),
        destinations: [
          NavigationDestination(
            icon: const Icon(Icons.home_outlined),
            selectedIcon: const Icon(Icons.home),
            label: t('nav_home'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.receipt_long_outlined),
            selectedIcon: const Icon(Icons.receipt_long),
            label: t('nav_bookings'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.queue_outlined),
            selectedIcon: const Icon(Icons.queue),
            label: t('nav_queue'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.notifications_outlined),
            selectedIcon: const Icon(Icons.notifications),
            label: t('nav_notifications'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.settings_outlined),
            selectedIcon: const Icon(Icons.settings),
            label: t('nav_settings'),
          ),
        ],
      ),
    );
  }
}

class _DashboardBody extends StatefulWidget {
  final ValueChanged<int> onNavigate;

  const _DashboardBody({required this.onNavigate});

  @override
  State<_DashboardBody> createState() => _DashboardBodyState();
}

class _DashboardBodyState extends State<_DashboardBody> {
  Booking? _activeBooking;
  bool _loading = true;
  bool _error = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _fetchActiveBooking());
  }

  Future<void> _fetchActiveBooking() async {
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
        final active = bookings.where((b) =>
            b.status == 'CONFIRMED' || b.status == 'PENDING');
        setState(() {
          _activeBooking = active.isNotEmpty ? active.first : null;
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
    final auth = context.watch<AuthProvider>();
    final t = AppLocalizations.of(context).translate;

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: _fetchActiveBooking,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _buildWelcomeCard(auth, t),
            const SizedBox(height: 16),
            Text(
              t('home_quick_actions'),
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            _buildQuickActionsGrid(t),
            const SizedBox(height: 24),
            Text(
              t('home_active_booking'),
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            _buildActiveBookingSection(t),
          ],
        ),
      ),
    );
  }

  Widget _buildWelcomeCard(AuthProvider auth, String Function(String) t) {
    return Card(
      elevation: 2,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          gradient: const LinearGradient(
            colors: [Color(0xFF4CAF50), Color(0xFF2E7D32)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${t('home_welcome')}, ${auth.user?.name ?? ''}!',
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              auth.user?.mobile ?? '',
              style: TextStyle(
                fontSize: 14,
                color: Colors.white.withValues(alpha: 0.9),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              t('home_welcome_subtitle'),
              style: TextStyle(
                fontSize: 13,
                color: Colors.white.withValues(alpha: 0.8),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickActionsGrid(String Function(String) t) {
    final actions = [
      _QuickAction(
        icon: Icons.event_available,
        label: t('home_action_book_slot'),
        onTap: () {
          Navigator.pushNamed(context, RouteNames.centreList);
        },
      ),
      _QuickAction(
        icon: Icons.receipt_long,
        label: t('home_action_my_bookings'),
        onTap: () => widget.onNavigate(1),
      ),
      _QuickAction(
        icon: Icons.queue,
        label: t('home_action_view_queue'),
        onTap: () => widget.onNavigate(2),
      ),
      _QuickAction(
        icon: Icons.notifications,
        label: t('home_action_notifications'),
        onTap: () => widget.onNavigate(3),
      ),
    ];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.4,
      ),
      itemCount: actions.length,
      itemBuilder: (context, index) => actions[index],
    );
  }

  Widget _buildActiveBookingSection(String Function(String) t) {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 32),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (_error) {
      return Column(
        children: [
          Text(
            t('error_loading_data'),
            style: const TextStyle(color: Colors.black54),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: _fetchActiveBooking,
            icon: const Icon(Icons.refresh),
            label: Text(t('retry')),
          ),
        ],
      );
    }

    if (_activeBooking == null) {
      return Card(
        elevation: 1,
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              Icon(Icons.inbox, size: 48, color: Colors.grey.shade400),
              const SizedBox(height: 12),
              Text(
                t('home_no_active_booking'),
                textAlign: TextAlign.center,
                style: const TextStyle(
                    fontSize: 16, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 8),
              Text(
                t('home_no_active_booking_sub'),
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () {
                  Navigator.pushNamed(context, RouteNames.centreList);
                },
                child: Text(t('home_action_book_slot')),
              ),
            ],
          ),
        ),
      );
    }

    final b = _activeBooking!;
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: b.status == 'CONFIRMED'
              ? Colors.green.shade400
              : Colors.orange.shade400,
          width: 1.5,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: b.status == 'CONFIRMED'
                        ? Colors.green.shade100
                        : Colors.orange.shade100,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    b.status,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: b.status == 'CONFIRMED'
                          ? Colors.green.shade800
                          : Colors.orange.shade800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              '${t('booking_token')}: ${b.token}',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            Text(
              b.centreName,
              style: const TextStyle(fontSize: 14),
            ),
            const SizedBox(height: 4),
            Text(
              '${b.date}  •  ${b.slotStart} - ${b.slotEnd}',
              style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () {
                  Navigator.pushNamed(
                    context,
                    RouteNames.bookingDetail,
                    arguments: {'booking_id': b.id},
                  );
                },
                child: Text(t('booking_view_details')),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _QuickAction({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 1,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 32, color: Theme.of(context).colorScheme.primary),
              const SizedBox(height: 8),
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NotificationsPlaceholder extends StatelessWidget {
  const _NotificationsPlaceholder();

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context).translate;

    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            t('notifications_title'),
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  const Icon(Icons.notifications_active, color: Colors.orange),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(t('notifications_empty')),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}