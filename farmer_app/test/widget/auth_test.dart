import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:farmer_app/providers/auth_provider.dart';
import 'package:farmer_app/providers/locale_provider.dart';
import 'package:farmer_app/services/auth_service.dart';
import 'package:farmer_app/core/network/api_response.dart';
import 'package:farmer_app/features/auth/screens/login_screen.dart';
import 'package:farmer_app/features/auth/screens/twofa_screen.dart';
import 'package:farmer_app/l10n/app_localizations.dart';
import 'mock_storage.dart';

class FakeAuthService extends AuthService {
  bool passwordOk = true;
  bool requires2fa = false;
  bool failNextVerify = false;
  String wrongOtpError = '';
  bool enable2faOnLogin = false;
  int resendCooldown = 60;
  Map<String, dynamic>? pending2faData;

  @override
  Future<ApiResponse<dynamic>> login({
    required String mobile,
    required String password,
    required String deviceId,
    bool? rememberMe,
  }) async {
    if (!passwordOk) {
      return ApiResponse.error('Invalid credentials', 'INVALID_CREDENTIALS',
          statusCode: 401);
    }
    if (requires2fa || pending2faData != null) {
      pending2faData = {
        'two_factor_required': true,
        'verification_id': '2fa_test123',
        'resend_after': resendCooldown,
        'expires_in': 300,
      };
      return ApiResponse.success(pending2faData!, {
        'status_code': 202
      }) as dynamic;
    }
    return ApiResponse.success({
      'access_token': 'access_jwt',
      'refresh_token': 'refresh_jwt',
      'user': {
        'id': 1,
        'name': 'Ramesh Kumar',
        'mobile': mobile,
        'role': 'FARMER',
        'status': 'APPROVED',
        'two_factor_enabled': enable2faOnLogin,
      },
    });
  }

  @override
  Future<ApiResponse<dynamic>> verify2fa({
    required String verificationId,
    required String otp,
    required String deviceId,
  }) async {
    if (failNextVerify) {
      failNextVerify = false;
      return ApiResponse.error(
        'Invalid OTP',
        'INVALID_2FA',
        statusCode: 401,
      );
    }
    if (wrongOtpError.isNotEmpty) {
      return ApiResponse.error(
        wrongOtpError == 'INVALID_2FA' ? 'Invalid OTP' : 'OTP expired',
        wrongOtpError,
        statusCode: 401,
      );
    }
    return ApiResponse.success({
      'access_token': 'access_jwt',
      'refresh_token': 'refresh_jwt',
      'user': {
        'id': 1,
        'name': 'Ramesh Kumar',
        'mobile': '9876543210',
        'role': 'FARMER',
        'status': 'APPROVED',
        'two_factor_enabled': true,
      },
    });
  }

  @override
  Future<ApiResponse<dynamic>> resend2fa(String verificationId) async {
    return ApiResponse.success({'resend_after': 60});
  }

  @override
  Future<ApiResponse<dynamic>> getProfile() async {
    return ApiResponse.success({
      'id': 1,
      'name': 'Ramesh Kumar',
      'mobile': '9876543210',
      'role': 'FARMER',
      'status': 'APPROVED',
      'two_factor_enabled': enable2faOnLogin,
    });
  }
}

Future<Map<String, dynamic>> _loadArb(String languageCode) async {
  final jsonString = await rootBundle
      .loadString('lib/l10n/app_$languageCode.arb');
  return json.decode(jsonString) as Map<String, dynamic>;
}

class _Delegate extends LocalizationsDelegate<AppLocalizations> {
  const _Delegate();

  @override
  bool isSupported(Locale locale) => ['en', 'hi'].contains(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async {
    final map = await _loadArb(locale.languageCode);
    final al = AppLocalizations(locale);
    al.loadFromMap(map);
    return al;
  }

  @override
  bool shouldReload(covariant LocalizationsDelegate<AppLocalizations> old) =>
      false;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  MockSecureStorage.install();

  group('AuthProvider 2FA state machine', () {
    test('login happy path (no 2FA) -> authenticated', () async {
      final auth = AuthProvider(authService: FakeAuthService());
      final ok = await auth.login('9876543210', 'password123');
      expect(ok, isTrue);
      expect(auth.isAuthenticated, isTrue);
      expect(auth.isPending2fa, isFalse);
      expect(auth.user?.name, 'Ramesh Kumar');
    });

    test('login 202 TWO_FA_REQUIRED -> pending2fa state', () async {
      final fake = FakeAuthService()..requires2fa = true;
      final auth = AuthProvider(authService: fake);
      final ok = await auth.login('9876543210', 'password123');
      expect(ok, isFalse);
      expect(auth.isPending2fa, isTrue);
      expect(auth.pending2faVerificationId, '2fa_test123');
    });

    test('verify2fa wrong OTP -> error', () async {
      final fake = FakeAuthService()..requires2fa = true;
      fake.wrongOtpError = 'INVALID_2FA';
      final auth = AuthProvider(authService: fake);
      await auth.login('9876543210', 'password123');
      final ok = await auth.verify2fa('000000');
      expect(ok, isFalse);
      expect(auth.error, isNotNull);
      expect(auth.isPending2fa, isTrue);
    });

    test('verify2fa correct OTP -> authenticated', () async {
      final fake = FakeAuthService()..requires2fa = true;
      final auth = AuthProvider(authService: fake);
      await auth.login('9876543210', 'password123');
      final ok = await auth.verify2fa('482913');
      expect(ok, isTrue);
      expect(auth.isAuthenticated, isTrue);
      expect(auth.isPending2fa, isFalse);
    });

    test('resend2fa updates cooldown', () async {
      final auth = AuthProvider(authService: FakeAuthService());
      auth.setManual2faPending('2fa_test', '9876543210');
      expect(auth.resendAfter, 0);
      final ok = await auth.resend2fa();
      expect(ok, isTrue);
      expect(auth.resendAfter, 60);
    });

    test('decrementResendCooldown counts down and notifies', () async {
      final auth = AuthProvider(authService: FakeAuthService());
      auth.setManual2faPending('2fa_test', '9876543210');
      await auth.resend2fa();
      auth.decrementResendCooldown();
      expect(auth.resendAfter, 59);
    });

    test('disabled user (two_factor_enabled false) skips step', () async {
      final auth = AuthProvider(
        authService: FakeAuthService()
          ..enable2faOnLogin = false,
      );
      final ok = await auth.login('9876543210', 'password123');
      expect(ok, isTrue);
      expect(auth.isAuthenticated, isTrue);
    });

    test('logout clears auth state', () async {
      final auth = AuthProvider(authService: FakeAuthService());
      await auth.login('9876543210', 'password123');
      await auth.logout();
      expect(auth.isAuthenticated, isFalse);
      expect(auth.user, isNull);
    });
  });

  group('Widget: login -> 2FA routing', () {
    testWidgets('full flow: login -> 2FA -> wrong OTP error -> correct OTP home',
        (tester) async {
      final fake = FakeAuthService()
        ..requires2fa = true
        ..failNextVerify = true;
      final auth = AuthProvider(authService: fake);
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider<AuthProvider>.value(value: auth),
          ChangeNotifierProvider<LocaleProvider>.value(
            value: LocaleProvider('en'),
          ),
        ],
        child: MaterialApp(
          localizationsDelegates: const [_Delegate()],
          supportedLocales: const [Locale('en'), Locale('hi')],
          locale: const Locale('en'),
          home: const LoginScreen(),
          routes: {
            '/twofa': (_) => const TwoFAScreen(),
            '/home': (_) => const Scaffold(body: Center(child: Text('Home'))),
          },
        ),
      ));
      await tester.pumpAndSettle();

      await tester.enterText(
          find.byType(TextField).first, '9876543210');
      await tester.enterText(
          find.byType(TextField).at(1), 'password123');
      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      expect(find.text('Verify Login OTP'), findsOneWidget);

      final otpFields =
          tester.widgetList(find.byType(TextField)).toList();
      for (var i = 0; i < otpFields.length && i < 6; i++) {
        await tester.enterText(find.byType(TextField).at(i), '0');
        await tester.pump();
      }
      for (var i = 0; i < 5; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }

      expect(auth.error, isNotNull);
      expect(find.text('Invalid OTP. Please try again.'),
          findsOneWidget);

      for (var i = 0; i < otpFields.length && i < 6; i++) {
        await tester.enterText(find.byType(TextField).at(i), '4');
      }
      for (var i = 0; i < 5; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }

      expect(auth.isAuthenticated, isTrue);
      expect(find.text('Home'), findsOneWidget);
    });
  });
}