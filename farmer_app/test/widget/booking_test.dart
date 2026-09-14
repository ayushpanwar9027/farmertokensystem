import 'package:flutter_test/flutter_test.dart';
import 'package:farmer_app/models/booking.dart';

void main() {
  group('Booking model', () {
    test('parses from JSON', () {
      final json = {
        'id': 1,
        'token': 'TKN-001',
        'centre_id': 3,
        'centre': {'name': 'APMC Pune'},
        'date': '2026-09-12',
        'slot_start': '09:00',
        'slot_end': '11:00',
        'status': 'CONFIRMED',
        'created_at': '2026-09-10T10:00:00Z',
        'crops': [
          {
            'id': 1,
            'crop_id': 2,
            'crop_name': 'Wheat',
            'quantity_kg': 100,
            'status': 'PENDING',
          }
        ],
      };
      final b = Booking.fromJson(json);
      expect(b.id, 1);
      expect(b.token, 'TKN-001');
      expect(b.centreName, 'APMC Pune');
      expect(b.crops.length, 1);
      expect(b.crops.first.cropName, 'Wheat');
      expect(b.crops.first.quantityKg, 100);
    });

    test('is cancellable only for CONFIRMED/PENDING', () {
      final c = Booking(
        id: 2,
        token: 'T2',
        centreId: 1,
        centreName: 'C',
        date: '2026-09-12',
        status: 'CONFIRMED',
        createdAt: '',
      );
      expect(c.isCancellable, isTrue);
      final done = Booking(
        id: 3,
        token: 'T3',
        centreId: 1,
        centreName: 'C',
        date: '2026-09-12',
        status: 'COMPLETED',
        createdAt: '',
      );
      expect(done.isCancellable, isFalse);
    });

    test('parses booking_crops alternate key', () {
      final json = {
        'id': 5,
        'token': 'T5',
        'centre_id': 1,
        'centre_name': 'C',
        'date': '2026-09-12',
        'status': 'PENDING',
        'booking_crops': [
          {'id': 9, 'crop_id': 4, 'crop_name': 'Rice', 'quantity_kg': 50, 'status': 'PENDING'}
        ],
        'created_at': 'x',
      };
      final b = Booking.fromJson(json);
      expect(b.crops.single.cropName, 'Rice');
    });
  });
}