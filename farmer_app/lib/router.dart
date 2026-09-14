import 'package:flutter/material.dart';

import 'features/auth/screens/splash_screen.dart';
import 'features/auth/screens/language_selection_screen.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/auth/screens/twofa_screen.dart';
import 'features/auth/screens/register_screen.dart';
import 'features/auth/screens/otp_verification_screen.dart';
import 'features/auth/screens/farmer_details_screen.dart';
import 'features/home/screens/home_screen.dart';
import 'features/centres/screens/centre_list_screen.dart';
import 'features/slots/screens/slot_selection_screen.dart';
import 'features/bookings/screens/book_crops_screen.dart';
import 'features/bookings/screens/booking_review_screen.dart';
import 'features/bookings/screens/booking_confirmation_screen.dart';
import 'features/bookings/screens/booking_list_screen.dart';
import 'features/bookings/screens/booking_detail_screen.dart';
import 'features/queue/screens/live_queue_screen.dart';
import 'features/procurement/screens/procurement_screen.dart';
import 'features/payments/screens/payment_screen.dart';
import 'features/notifications/screens/notification_list_screen.dart';
import 'features/notifications/screens/notification_detail_screen.dart';
import 'features/settings/screens/settings_screen.dart';

class AppRouter {
  static Route<dynamic> generateRoute(RouteSettings settings) {
    switch (settings.name) {
      case '/':
        return MaterialPageRoute(builder: (_) => const SplashScreen());
      case '/language':
        return MaterialPageRoute(builder: (_) => const LanguageSelectionScreen());
      case '/login':
        return MaterialPageRoute(builder: (_) => const LoginScreen());
      case '/twofa':
        return MaterialPageRoute(builder: (_) => const TwoFAScreen());
      case '/register':
        return MaterialPageRoute(builder: (_) => const RegisterScreen());
      case '/otp':
        return MaterialPageRoute(builder: (_) => const OtpVerificationScreen());
      case '/farmer-details':
        return MaterialPageRoute(builder: (_) => const FarmerDetailsScreen());
      case '/home':
        return MaterialPageRoute(builder: (_) => const HomeScreen());
      case '/centres':
        return MaterialPageRoute(builder: (_) => const CentreListScreen());
      case '/bookings/select-slot':
        return MaterialPageRoute(builder: (_) => const SlotSelectionScreen());
      case '/bookings/select-crops':
        return MaterialPageRoute(builder: (_) => const BookCropsScreen());
      case '/bookings/review':
        return MaterialPageRoute(builder: (_) => const BookingReviewScreen());
      case '/bookings/confirm':
        return MaterialPageRoute(builder: (_) => const BookingConfirmationScreen());
      case '/bookings/history':
        return MaterialPageRoute(builder: (_) => const BookingListScreen());
      case '/bookings/detail':
        return MaterialPageRoute(builder: (_) => const BookingDetailScreen());
      case '/queue/live':
        return MaterialPageRoute(builder: (_) => const LiveQueueScreen());
      case '/procurement':
        return MaterialPageRoute(builder: (_) => const ProcurementScreen());
      case '/payment':
        return MaterialPageRoute(builder: (_) => const PaymentScreen());
      case '/notifications':
        return MaterialPageRoute(builder: (_) => const NotificationListScreen());
      case '/notifications/detail':
        return MaterialPageRoute(builder: (_) => const NotificationDetailScreen());
      case '/settings':
        return MaterialPageRoute(builder: (_) => const SettingsScreen());
      default:
        return MaterialPageRoute(
          builder: (_) => Scaffold(
            body: Center(
              child: Text('404 - Page not found: ${settings.name}'),
            ),
          ),
        );
    }
  }
}
