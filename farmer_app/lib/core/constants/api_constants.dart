class ApiConstants {
  static String get baseUrl => '$_envApiBaseUrl/api/v1';

  static String get _envApiBaseUrl => const String.fromEnvironment(
    'API_BASE',
    defaultValue: 'https://testing.deepjyotimicrofinance.com',
  );

  static const String register = '/auth/register';
  static const String verifyOtp = '/auth/verify-otp';
  static const String resendOtp = '/auth/resend-otp';
  static const String completeRegistration = '/auth/complete-registration';
  static const String login = '/auth/login';
  static const String verify2fa = '/auth/verify-2fa';
  static const String resend2fa = '/auth/resend-2fa';
  static const String enable2fa = '/auth/2fa/enable';
  static const String enable2faConfirm = '/auth/2fa/enable/confirm';
  static const String disable2fa = '/auth/2fa/disable';
  static const String registerDevice = '/auth/devices';
  static const String me = '/auth/me';
  static const String refresh = '/auth/refresh';
  static const String logout = '/auth/logout';
  static const String centres = '/centres';
  static const String slots = '/slots';
  static const String crops = '/crops';
  static const String bookings = '/bookings';
  static const String tokens = '/tokens';
  static const String queue = '/queue';
  static const String procurements = '/my/procurements';
  static const String payments = '/my/payments';
  static const String notifications = '/notifications';
  static const String translations = '/translations';
}
