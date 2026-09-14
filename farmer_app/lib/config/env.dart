class AppConfig {
  static const String _defaultBaseUrl = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'https://testing.deepjyotimicrofinance.com',
  );

  static String get apiBaseUrl => _defaultBaseUrl;

  static bool get isProduction =>
      apiBaseUrl.startsWith('https://') &&
      !apiBaseUrl.contains('localhost');

  static const String oneSignalAppId = String.fromEnvironment(
    'ONESIGNAL_APP_ID',
    defaultValue: 'your-onesignal-app-id',
  );

  static const String appName = 'Kisan';
}
