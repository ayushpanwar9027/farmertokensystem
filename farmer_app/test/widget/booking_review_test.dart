import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:farmer_app/core/network/api_response.dart';
import 'package:farmer_app/features/bookings/screens/booking_review_screen.dart';
import 'package:farmer_app/services/booking_service.dart';
import 'mock_storage.dart';
import 'test_helpers.dart';

class FakeBookingService extends BookingService {
  int createCalls = 0;
  Completer<ApiResponse<dynamic>>? pending;

  @override
  Future<ApiResponse<dynamic>> createBooking(Map<String, dynamic> payload) {
    createCalls++;
    pending = Completer<ApiResponse<dynamic>>();
    return pending!.future;
  }
}

Future<void> _pushReviewApp(
  WidgetTester tester,
  FakeBookingService service,
) async {
  await tester.pumpWidget(MultiProvider(
    providers: [
      Provider<BookingService>.value(value: service),
    ],
    child: MaterialApp(
      localizationsDelegates: const [ArbDelegate()],
      supportedLocales: const [Locale('en')],
      locale: const Locale('en'),
      onGenerateRoute: (settings) {
        if (settings.name == '/review') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => const BookingReviewScreen(),
          );
        }
        return null;
      },
      home: Scaffold(
        body: Builder(
          builder: (context) => Center(
            child: TextButton(
              onPressed: () => Navigator.of(context).pushNamed(
                '/review',
                arguments: {
                  'centreId': 3,
                  'centreName': 'APMC Pune',
                  'date': '2026-09-12',
                  'slotId': 11,
                  'slotLabel': '09:00 - 11:00',
                  'crops': [
                    {
                      'crop_name': 'Wheat',
                      'quantity_kg': 100,
                    },
                  ],
                },
              ),
              child: const Text('go'),
            ),
          ),
        ),
      ),
    ),
  ));
  await tester.pump();
  await tester.tap(find.text('go'), warnIfMissed: false);
  await tester.pumpAndSettle();
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  MockSecureStorage.install();

  group('Booking review double-submit guard', () {
    testWidgets('prevents duplicate booking submission', (tester) async {
      final service = FakeBookingService();
      await _pushReviewApp(tester, service);

      expect(find.text('Review Booking'), findsOneWidget);

      await tester.tap(find.text('Confirm Booking'));
      await tester.pump();
      expect(service.createCalls, 1);

      await tester.tap(find.byType(ElevatedButton).first,
          warnIfMissed: false);
      await tester.pump();
      expect(service.createCalls, 1,
          reason: 'button is disabled while submitting');

      service.pending!.complete(
          ApiResponse.error('Something went wrong', 'UNKNOWN'));
      await tester.pumpAndSettle();

      expect(service.createCalls, 1);
      expect(find.text('Something went wrong'), findsOneWidget);
    });
  });
}