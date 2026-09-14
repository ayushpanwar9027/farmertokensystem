# Flutter Farmer App Architecture

## Overview

Android-first Flutter application for farmers. Communicates with PHP backend via HTTPS REST APIs. Focus on simplicity, reliability, and offline resilience.

## Tech Stack

| Component | Choice | Version |
|-----------|--------|---------|
| Language | Dart | 3.x |
| Framework | Flutter | 3.x |
| State Management | Provider + ChangeNotifier | 6.x |
| HTTP Client | http (or dio) | 1.x |
| Secure Storage | flutter_secure_storage | 9.x |
| Local Storage | shared_preferences | 2.x |
| Connectivity | connectivity_plus | 5.x |
| QR Code | qr_flutter | 4.x |
| Localization | intl + manual JSON | 0.18.x |
| Date/Time | intl | 0.18.x |
| Device Info | device_info_plus | 9.x |
| Package Info | package_info_plus | 4.x |
| URL Launcher | url_launcher | 6.x |
| Cached Images | cached_network_image | 3.x |

## Principles

1. **Offline-first thinking** - Cache what matters, retry what fails
2. **Minimal state** - Provider/ChangeNotifier, no complex patterns
3. **Repository pattern** - Clean separation of network vs data
4. **Error boundaries** - Every screen handles errors gracefully
5. **Secure tokens** - flutter_secure_storage only, never SharedPreferences for secrets

## Directory Structure

```
lib/
├── main.dart

├── core/
│   ├── constants/
│   │   ├── api_constants.dart
│   │   ├── app_constants.dart
│   │   ├── storage_keys.dart
│   │   └── route_names.dart
│   │
│   ├── theme/
│   │   ├── app_theme.dart
│   │   ├── colors.dart
│   │   └── text_styles.dart
│   │
│   ├── localization/
│   │   ├── app_localizations.dart
│   │   ├── locale_provider.dart
│   │   └── translations/
│   │       ├── en.json
│   │       └── hi.json
│   │
│   ├── network/
│   │   ├── api_client.dart
│   │   ├── interceptors/
│   │   │   ├── auth_interceptor.dart
│   │   │   └── logging_interceptor.dart
│   │   ├── endpoints/
│   │   │   ├── auth_endpoints.dart
│   │   │   ├── booking_endpoints.dart
│   │   │   ├── queue_endpoints.dart
│   │   │   ├── procurement_endpoints.dart
│   │   │   ├── payment_endpoints.dart
│   │   │   ├── centre_endpoints.dart
│   │   │   ├── notification_endpoints.dart
│   │   │   └── profile_endpoints.dart
│   │   └── models/
│   │       ├── api_response.dart
│   │       ├── pagination_response.dart
│   │       └── api_error.dart
│   │
│   ├── storage/
│   │   ├── secure_storage.dart
│   │   └── preferences_storage.dart
│   │
│   └── utils/
│       ├── date_formatter.dart
│       ├── validator.dart
│       ├── formatter.dart
│       └── connectivity_utils.dart
│
├── models/
│   ├── user.dart
│   ├── farmer.dart
│   ├── centre.dart
│   ├── district.dart
│   ├── slot.dart
│   ├── booking.dart
│   ├── booking_crop.dart
│   ├── queue_entry.dart
│   ├── procurement.dart
│   ├── payment.dart
│   ├── notification.dart
│   ├── language.dart
│   └── device_info.dart
│
├── services/
│   ├── auth_service.dart
│   ├── centre_service.dart
│   ├── slot_service.dart
│   ├── booking_service.dart
│   ├── queue_service.dart
│   ├── procurement_service.dart
│   ├── payment_service.dart
│   ├── notification_service.dart
│   └── profile_service.dart
│
├── repositories/
│   ├── auth_repository.dart
│   ├── centre_repository.dart
│   ├── slot_repository.dart
│   ├── booking_repository.dart
│   ├── queue_repository.dart
│   ├── procurement_repository.dart
│   ├── payment_repository.dart
│   ├── notification_repository.dart
│   └── profile_repository.dart
│
├── features/
│   ├── auth/
│   │   ├── screens/
│   │   │   ├── splash_screen.dart
│   │   │   ├── language_selection_screen.dart
│   │   │   ├── login_screen.dart
│   │   │   ├── twofa_screen.dart             # 2FA OTP entry (login step 2)
│   │   │   ├── register_screen.dart
│   │   │   ├── otp_verification_screen.dart
│   │   │   └── farmer_details_screen.dart
│   │   ├── widgets/
│   │   │   ├── mobile_input_field.dart
│   │   │   ├── otp_field.dart
│   │   │   └── password_field.dart
│   │   └── providers/
│   │       └── auth_provider.dart
│   │
│   ├── home/
│   │   ├── screens/
│   │   │   └── home_screen.dart
│   │   ├── widgets/
│   │   │   ├── quick_actions.dart
│   │   │   ├── active_booking_card.dart
│   │   │   ├── recent_notifications.dart
│   │   │   └── welcome_header.dart
│   │   └── providers/
│   │       └── home_provider.dart
│   │
│   ├── profile/
│   │   ├── screens/
│   │   │   ├── profile_screen.dart
│   │   │   └── edit_profile_screen.dart
│   │   ├── widgets/
│   │   │   ├── profile_header.dart
│   │   │   └── profile_info_tile.dart
│   │   └── providers/
│   │       └── profile_provider.dart
│   │
│   ├── centres/
│   │   ├── screens/
│   │   │   ├── centre_list_screen.dart
│   │   │   └── centre_detail_screen.dart
│   │   ├── widgets/
│   │   │   ├── centre_card.dart
│   │   │   ├── centre_search_bar.dart
│   │   │   ├── centre_info_section.dart
│   │   │   └── map_link_button.dart
│   │   └── providers/
│   │       └── centre_provider.dart
│   │
│   ├── slots/
│   │   ├── screens/
│   │   │   ├── date_selection_screen.dart
│   │   │   └── slot_selection_screen.dart
│   │   ├── widgets/
│   │   │   ├── date_picker_card.dart
│   │   │   ├── slot_card.dart
│   │   │   ├── slot_capacity_indicator.dart
│   │   │   └── time_range_display.dart
│   │   └── providers/
│   │       └── slot_provider.dart
│   │
│   ├── bookings/
│   │   ├── screens/
│   │   │   ├── booking_confirmation_screen.dart
│   │   │   ├── booking_detail_screen.dart
│   │   │   ├── booking_history_screen.dart
│   │   │   └── cancellation_screen.dart
│   │   ├── widgets/
│   │   │   ├── booking_card.dart
│   │   │   ├── booking_summary.dart
│   │   │   ├── crop_input_form.dart
│   │   │   └── cancellation_reason_field.dart
│   │   └── providers/
│   │       └── booking_provider.dart
│   │
│   ├── queue/
│   │   ├── screens/
│   │   │   ├── live_queue_screen.dart
│   │   │   └── token_screen.dart
│   │   ├── widgets/
│   │   │   ├── queue_position_widget.dart
│   │   │   ├── estimated_wait_widget.dart
│   │   │   ├── current_token_display.dart
│   │   │   ├── farmers_ahead_widget.dart
│   │   │   ├── status_timeline.dart
│   │   │   └── qr_code_display.dart
│   │   └── providers/
│   │       ├── queue_provider.dart
│   │       └── queue_polling_provider.dart
│   │
│   ├── procurement/
│   │   ├── screens/
│   │   │   └── procurement_status_screen.dart
│   │   ├── widgets/
│   │   │   ├── procurement_card.dart
│   │   │   ├── crop_detail_tile.dart
│   │   │   └── procurement_status_badge.dart
│   │   └── providers/
│   │       └── procurement_provider.dart
│   │
│   ├── payments/
│   │   ├── screens/
│   │   │   └── payment_status_screen.dart
│   │   ├── widgets/
│   │   │   ├── payment_card.dart
│   │   │   └── payment_status_badge.dart
│   │   └── providers/
│   │       └── payment_provider.dart
│   │
│   ├── notifications/
│   │   ├── screens/
│   │   │   ├── notification_list_screen.dart
│   │   │   └── notification_detail_screen.dart
│   │   ├── widgets/
│   │   │   ├── notification_tile.dart
│   │   │   └── notification_badge.dart
│   │   └── providers/
│   │       └── notification_provider.dart
│   │
│   └── support/
│       ├── screens/
│       │   ├── help_screen.dart
│       │   └── contact_screen.dart
│       └── widgets/
│           └── faq_tile.dart
│
├── widgets/
│   ├── common/
│   │   ├── app_button.dart
│   │   ├── app_text_field.dart
│   │   ├── app_dropdown.dart
│   │   ├── loading_indicator.dart
│   │   ├── error_display.dart
│   │   ├── empty_state.dart
│   │   ├── status_badge.dart
│   │   ├── confirmation_dialog.dart
│   │   ├── retry_button.dart
│   │   ├── offline_banner.dart
│   │   └── app_card.dart
│   │
│   ├── layout/
│   │   ├── app_scaffold.dart
│   │   ├── app_app_bar.dart
│   │   ├── app_bottom_nav.dart
│   │   └── section_header.dart
│   │
│   └── feedback/
│       ├── snackbar_helper.dart
│       └── dialog_helper.dart
│
├── providers/
│   ├── auth_provider.dart
│   ├── locale_provider.dart
│   └── connectivity_provider.dart
│
└── app.dart
```

## Core Architecture

### main.dart

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'app.dart';
import 'core/storage/secure_storage.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize secure storage
  await SecureStorage.initialize();

  // Initialize OneSignal push (keys from app config — see 22-notification-system.md)
  await PushService.initialize();

  // Check for stored language preference
  final savedLocale = await PreferencesStorage.getLocale();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => LocaleProvider(savedLocale)),
        ChangeNotifierProvider(create: (_) => ConnectivityProvider()),
      ],
      child: const FarmerApp(),
    ),
  );
}
```

### OneSignal Push (core/services/push_service.dart)

```dart
class PushService {
  static Future<void> initialize() async {
    // ONSIGNAL_APP_ID from app config (hardcoded for now; matches backend config/onesignal.php)
    OneSignal.Debug.setLogLevel(OSLogLevel.warn);
    OneSignal.initialize('ONESIGNAL_APP_ID');

    OneSignal.Notifications.addClickListener((event) {
      // Deep-link: read event.notification.additionalData['event'] → open booking/queue
    });

    // Register the device with the backend so we can target it with push
    final playerId = await OneSignal.User.getOnesignalId();
    // name playerId is available only after prompt/init; send to POST /auth/devices on login
  }

  static Future<void> registerDeviceWithBackend() async {
    final playerId = await OneSignal.User.getOnesignalId();
    if (playerId != null) {
      // sendOnce('/auth/devices', {'onesignal_player_id': playerId}) after login
    }
  }

  static Future<bool> requestPermission() async {
    // Android 13+ runtime permission
    final result = await OneSignal.Notifications.requestPermission(
      true, // fallbackToSettings
    );
    return result;
  }
}
```

> Registration with backend: whenever user logs in (or app resumes), `AuthProvider` calls `PushService.registerDeviceWithBackend()` so stale player ids are refreshed.

### app.dart

```dart
class FarmerApp extends StatelessWidget {
  const FarmerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer2<LocaleProvider, ConnectivityProvider>(
      builder: (context, localeProvider, connectivity, _) {
        return MaterialApp(
          title: 'Farmer Procurement System',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.lightTheme,
          locale: localeProvider.currentLocale,
          supportedLocales: AppLocalizations.supportedLocales,
          initialRoute: RouteNames.splash,
          onGenerateRoute: AppRouter.generateRoute,
          builder: (context, child) {
            return Stack(
              children: [
                child!,
                if (!connectivity.isOnline) const OfflineBanner(),
              ],
            );
          },
        );
      },
    );
  }
}
```

## Network Layer

### API Client

```dart
// core/network/api_client.dart
class ApiClient {
  static final ApiClient _instance = ApiClient._internal();
  factory ApiClient() => _instance;
  ApiClient._internal();

  final http.Client _client = http.Client();
  String _baseUrl = ApiConstants.baseUrl;

  Future<ApiResponse<T>> get<T>(String path, {
    Map<String, String>? queryParams,
    T Function(dynamic)? fromJson,
  }) async {
    try {
      final uri = Uri.parse('$_baseUrl$path').replace(
        queryParameters: queryParams,
      );

      final headers = await _buildHeaders();
      final response = await _client.get(uri, headers: headers).timeout(
        const Duration(seconds: 30),
      );

      return _handleResponse<T>(response, fromJson);
    } on SocketException {
      return ApiResponse.error('No internet connection', 'NETWORK_ERROR');
    } on TimeoutException {
      return ApiResponse.error('Request timed out', 'TIMEOUT');
    } catch (e) {
      return ApiResponse.error('Unexpected error: $e', 'UNKNOWN_ERROR');
    }
  }

  Future<ApiResponse<T>> post<T>(String path, {
    Map<String, dynamic>? body,
    T Function(dynamic)? fromJson,
  }) async {
    try {
      final uri = Uri.parse('$_baseUrl$path');
      final headers = await _buildHeaders();
      final response = await _client.post(
        uri,
        headers: headers,
        body: jsonEncode(body),
      ).timeout(const Duration(seconds: 30));

      return _handleResponse<T>(response, fromJson);
    } on SocketException {
      return ApiResponse.error('No internet connection', 'NETWORK_ERROR');
    } on TimeoutException {
      return ApiResponse.error('Request timed out', 'TIMEOUT');
    } catch (e) {
      return ApiResponse.error('Unexpected error: $e', 'UNKNOWN_ERROR');
    }
  }

  Future<Map<String, String>> _buildHeaders() async {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Accept-Language': await PreferencesStorage.getLocaleCode(),
    };

    // Attach JWT access token
    final token = await SecureStorage.getAccessToken();
    if (token != null) {
      headers['Authorization'] = 'Bearer $token';
    }

    return headers;
  }

  ApiResponse<T> _handleResponse<T>(http.Response response, T Function(dynamic)? fromJson) {
    final body = jsonDecode(response.body);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      final data = fromJson != null ? fromJson(body['data']) : body['data'];
      return ApiResponse.success(data, body['meta']);
    }

    if (response.statusCode == 401) {
      return ApiResponse.error(
        body['error']?['message'] ?? 'Unauthorized',
        'UNAUTHORIZED',
        statusCode: 401,
      );
    }

    return ApiResponse.error(
      body['error']?['message'] ?? 'Request failed',
      body['error']?['code'] ?? 'API_ERROR',
      statusCode: response.statusCode,
      details: body['error']?['details'],
    );
  }
}
```

### Auth Interceptor

```dart
// core/network/interceptors/auth_interceptor.dart
class AuthInterceptor {
  static Future<ApiResponse<T>> withAuthRefresh<T>(
    Future<ApiResponse<T>> Function() apiCall,
    T Function(dynamic)? fromJson,
  ) async {
    var response = await apiCall();

    // If 401, try refresh token once
    if (response.statusCode == 401) {
      final refreshResult = await _tryRefreshToken();
      if (refreshResult) {
        response = await apiCall(); // Retry with new token
      } else {
        // Refresh failed, clear auth, redirect to login
        await SecureStorage.clearAuth();
        return ApiResponse.error('Session expired', 'SESSION_EXPIRED', statusCode: 401);
      }
    }

    return response;
  }

  static Future<bool> _tryRefreshToken() async {
    try {
      final refreshToken = await SecureStorage.getRefreshToken();
      if (refreshToken == null) return false;

      final apiClient = ApiClient();
      final response = await apiClient.post('/auth/refresh', body: {
        'refresh_token': refreshToken,
      });

      if (response.success) {
        final data = response.data as Map<String, dynamic>;
        await SecureStorage.saveTokens(
          accessToken: data['access_token'],
          refreshToken: data['refresh_token'],
        );
        return true;
      }
      return false;
    } catch (e) {
      return false;
    }
  }
}
```

## Storage Layer

### Secure Storage

```dart
// core/storage/secure_storage.dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorage {
  static late FlutterSecureStorage _storage;

  static Future<void> initialize() async {
    _storage = const FlutterSecureStorage(
      aOptions: AndroidOptions(encryptedSharedPreferences: true),
      iOptions: IOSOptions(
        accessibility: KeychainAccessibility.first_unlock_this_device,
      ),
    );
  }

  // Token operations
  static Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _storage.write(key: 'access_token', value: accessToken);
    await _storage.write(key: 'refresh_token', value: refreshToken);
  }

  static Future<String?> getAccessToken() async {
    return await _storage.read(key: 'access_token');
  }

  static Future<String?> getRefreshToken() async {
    return await _storage.read(key: 'refresh_token');
  }

  // Device ID
  static Future<String> getDeviceId() async {
    var deviceId = await _storage.read(key: 'device_id');
    if (deviceId == null) {
      deviceId = _generateDeviceId();
      await _storage.write(key: 'device_id', value: deviceId);
    }
    return deviceId;
  }

  // User data (non-sensitive)
  static Future<void> saveFarmerId(int id) async {
    await _storage.write(key: 'farmer_id', value: id.toString());
  }

  static Future<int?> getFarmerId() async {
    final id = await _storage.read(key: 'farmer_id');
    return id != null ? int.parse(id) : null;
  }

  // Clear all
  static Future<void> clearAuth() async {
    await _storage.delete(key: 'access_token');
    await _storage.delete(key: 'refresh_token');
    await _storage.delete(key: 'farmer_id');
    // Keep device_id and language preference
  }

  static Future<void> clearAll() async {
    await _storage.deleteAll();
  }

  static String _generateDeviceId() {
    return 'fps_${DateTime.now().millisecondsSinceEpoch}_${_randomString(8)}';
  }

  static String _randomString(int length) {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    final random = math.Random();
    return String.fromCharCodes(
      Iterable.generate(length, (_) => chars.codeUnitAt(random.nextInt(chars.length))),
    );
  }
}
```

### Preferences Storage

```dart
// core/storage/preferences_storage.dart
class PreferencesStorage {
  static const _prefs = SharedPreferences.getInstance();

  static Future<void> saveLocale(String locale) async {
    final prefs = await _prefs;
    await prefs.setString('locale', locale);
  }

  static Future<String> getLocaleCode() async {
    final prefs = await _prefs;
    return prefs.getString('locale') ?? 'en';
  }

  static Future<bool> hasSelectedLanguage() async {
    final prefs = await _prefs;
    return prefs.containsKey('locale');
  }

  static Future<void> clearLocale() async {
    final prefs = await _prefs;
    await prefs.remove('locale');
  }
}
```

## State Management

### Auth Provider

```dart
// features/auth/providers/auth_provider.dart
class AuthProvider extends ChangeNotifier {
  final AuthService _authService = AuthService();
  final BookingService _bookingService = BookingService();

  User? _user;
  bool _isLoading = false;
  String? _error;

  User? get user => _user;
  bool get isLoading => _isLoading;
  bool get isAuthenticated => _user != null;
  String? get error => _error;

  // Check authentication on app start
  Future<void> checkAuth() async {
    _isLoading = true;
    notifyListeners();

    try {
      final token = await SecureStorage.getAccessToken();
      if (token == null) {
        _user = null;
        return;
      }

      final response = await _authService.getProfile();
      if (response.success) {
        _user = response.data;
      } else {
        await SecureStorage.clearAuth();
        _user = null;
      }
    } catch (e) {
      _user = null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // Login with mobile + password
  Future<bool> login(String mobile, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final deviceId = await SecureStorage.getDeviceId();
      final response = await _authService.login(
        mobile: mobile,
        password: password,
        deviceId: deviceId,
      );

      if (response.success) {
        // 202 TWO_FA_REQUIRED — save verification_id, open 2FA screen
        if (response.data['two_factor_required'] == true) {
          _pending2fa = TwoFactorChallenge.fromJson(response.data);
          notifyListeners();
          return false; // caller routes to OTP entry (2FA) via state
        }

        final data = response.data;
        await SecureStorage.saveTokens(
          accessToken: data['access_token'],
          refreshToken: data['refresh_token'],
        );
        _user = User.fromJson(data['user']);
        notifyListeners();
        return true;
      } else {
        _error = response.errorMessage;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = 'Login failed. Please try again.';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // Step 2: verify 2FA OTP → complete login, store tokens
  Future<bool> verify2fa(String otp) async {
    if (_pending2fa == null) { _error = '2FA not requested'; notifyListeners(); return false; }
    _isLoading = true; _error = null; notifyListeners();
    try {
      final deviceId = await SecureStorage.getDeviceId();
      final response = await _authService.verify2fa(
        verificationId: _pending2fa!.verificationId,
        otp: otp,
        deviceId: deviceId,
      );
      if (response.success) {
        final data = response.data;
        await SecureStorage.saveTokens(
          accessToken: data['access_token'],
          refreshToken: data['refresh_token'],
        );
        _user = User.fromJson(data['user']);
        _pending2fa = null;
        notifyListeners();
        return true;
      }
      _error = response.errorMessage; notifyListeners(); return false;
    } catch (e) {
      _error = '2FA failed. Please try again.'; notifyListeners(); return false;
    } finally { _isLoading = false; notifyListeners(); }
  }

  // Enable / disable 2FA
  Future<bool> enable2fa(String otp) async => _simple2faCall('/auth/2fa/enable/confirm', otp);
  Future<bool> disable2fa(String otp) async => _simple2faCall('/auth/2fa/disable', otp);

  // Register
  Future<bool> register(Map<String, dynamic> data) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final deviceId = await SecureStorage.getDeviceId();
      final response = await _authService.register({...data, 'device_id': deviceId});

      if (response.success) {
        return true;
      } else {
        _error = response.errorMessage;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = 'Registration failed. Please try again.';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // Logout
  Future<void> logout() async {
    try {
      await _authService.logout();
    } catch (_) {}
    await SecureStorage.clearAuth();
    _user = null;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
```

### Queue Polling Provider

```dart
// features/queue/providers/queue_polling_provider.dart
class QueuePollingProvider extends ChangeNotifier {
  Timer? _timer;
  bool _isPolling = false;

  List<QueueEntry> _queue = [];
  QueueEntry? _currentEntry;
  QueueEntry? _myEntry;
  int _farmersAhead = 0;
  String _estimatedWait = '--';
  String _currentToken = '--';

  List<QueueEntry> get queue => _queue;
  QueueEntry? get currentEntry => _currentEntry;
  QueueEntry? get myEntry => _myEntry;
  int get farmersAhead => _farmersAhead;
  String get estimatedWait => _estimatedWait;
  String get currentToken => _currentToken;
  bool get isPolling => _isPolling;

  final QueueService _queueService = QueueService();

  // Start polling (every 5 seconds)
  void startPolling(int centreId, String date, int bookingId) {
    stopPolling();
    _isPolling = true;
    _poll(centreId, date, bookingId);
    _timer = Timer.periodic(const Duration(seconds: 5), (_) {
      _poll(centreId, date, bookingId);
    });
    notifyListeners();
  }

  // Stop polling
  void stopPolling() {
    _timer?.cancel();
    _timer = null;
    _isPolling = false;
  }

  // Single poll
  Future<void> _poll(int centreId, String date, int bookingId) async {
    try {
      final response = await _queueService.getLiveQueue(
        centreId: centreId,
        date: date,
        bookingId: bookingId,
      );

      if (response.success) {
        final data = response.data as Map<String, dynamic>;
        _queue = (data['queue'] as List).map((e) => QueueEntry.fromJson(e)).toList();
        _currentEntry = data['current'] != null ? QueueEntry.fromJson(data['current']) : null;
        _myEntry = data['my_entry'] != null ? QueueEntry.fromJson(data['my_entry']) : null;
        _farmersAhead = data['farmers_ahead'] ?? 0;
        _estimatedWait = data['estimated_wait'] ?? '--';
        _currentToken = data['current_token'] ?? '--';
        notifyListeners();
      }
    } catch (e) {
      // Silently fail polling, will retry next interval
    }
  }

  @override
  void dispose() {
    stopPolling();
    super.dispose();
  }
}
```

## Screen Patterns

### Base Screen Template

```dart
// Every screen follows this pattern
class CentreListScreen extends StatefulWidget {
  const CentreListScreen({super.key});

  @override
  State<CentreListScreen> createState() => _CentreListScreenState();
}

class _CentreListScreenState extends State<CentreListScreen> {
  late CentreProvider _provider;

  @override
  void initState() {
    super.initState();
    _provider = CentreProvider();
    _provider.loadCentres();
  }

  @override
  void dispose() {
    _provider.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider.value(
      value: _provider,
      child: Scaffold(
        appBar: AppBar(title: Text('Procurement Centres')),
        body: Consumer<CentreProvider>(
          builder: (context, provider, _) {
            // Loading state
            if (provider.isLoading && provider.centres.isEmpty) {
              return const LoadingIndicator();
            }

            // Error state
            if (provider.error != null && provider.centres.isEmpty) {
              return ErrorDisplay(
                message: provider.error!,
                onRetry: () => provider.loadCentres(),
              );
            }

            // Empty state
            if (provider.centres.isEmpty) {
              return EmptyState(
                icon: Icons.store,
                title: 'No Centres Found',
                subtitle: 'No procurement centres available in your area',
              );
            }

            // Data state
            return ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: provider.centres.length,
              itemBuilder: (context, index) {
                final centre = provider.centres[index];
                return CentreCard(
                  centre: centre,
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => CentreDetailScreen(centreId: centre.id),
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
```

### Booking Confirmation Screen

```dart
class BookingConfirmationScreen extends StatelessWidget {
  final Booking booking;

  const BookingConfirmationScreen({super.key, required this.booking});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            children: [
              const SizedBox(height: 20),
              // Success icon
              Icon(Icons.check_circle, size: 80, color: AppColors.success),
              const SizedBox(height: 16),
              Text('Booking Confirmed!',
                style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 8),
              Text('Token: ${booking.token}'),
              const SizedBox(height: 24),

              // Booking summary card
              BookingSummary(booking: booking),

              const SizedBox(height: 24),

              // QR Code
              QrCodeWidget(data: booking.token),

              const SizedBox(height: 24),

              // Action buttons
              AppButton(
                text: 'View Queue',
                onPressed: () => Navigator.pushReplacement(
                  context,
                  MaterialPageRoute(
                    builder: (_) => LiveQueueScreen(booking: booking),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              AppButton(
                text: 'Back to Home',
                isOutlined: true,
                onPressed: () => Navigator.pushNamedAndRemoveUntil(
                  context,
                  RouteNames.home,
                  (route) => false,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

## Localization

### translations/en.json
```json
{
  "app_name": "Farmer Procurement",
  "common": {
    "loading": "Loading...",
    "error": "Something went wrong",
    "retry": "Retry",
    "cancel": "Cancel",
    "confirm": "Confirm",
    "save": "Save",
    "back": "Back",
    "next": "Next",
    "yes": "Yes",
    "no": "No",
    "ok": "OK",
    "search": "Search...",
    "no_data": "No data available",
    "offline": "You are offline"
  },
  "auth": {
    "login": "Login",
    "register": "Register",
    "logout": "Logout",
    "mobile": "Mobile Number",
    "password": "Password",
    "otp": "Enter OTP",
    "forgot_password": "Forgot Password?",
    "login_title": "Welcome Back",
    "register_title": "Create Account",
    "otp_title": "Verify Mobile",
    "twofa_title": "Verify Login OTP",
    "twofa_subtitle": "Enter the 6-digit OTP sent to your mobile",
    "twofa_resend": "Resend OTP",
    "enable_2fa": "Enable Two-Factor Auth",
    "disable_2fa": "Disable Two-Factor Auth",
    "2fa_enabled": "Two-factor authentication is ON",
    "2fa_disabled": "Two-factor authentication is OFF",
    "verification_pending": "Verification Pending",
    "verification_rejected": "Verification Rejected"
  },
  "home": {
    "welcome": "Welcome, {name}",
    "book_slot": "Book Slot",
    "my_bookings": "My Bookings",
    "view_queue": "View Queue",
    "active_booking": "Active Booking",
    "no_active_booking": "No Active Booking"
  },
  "centres": {
    "title": "Procurement Centres",
    "select": "Select a Centre",
    "no_centres": "No Centres Available",
    "working_hours": "Working Hours",
    "capacity": "Daily Capacity",
    "available_slots": "Available Slots"
  },
  "slots": {
    "title": "Select Slot",
    "select_date": "Select Date",
    "select_time": "Select Time Slot",
    "available": "Available",
    "full": "Full",
    "remaining": "{count} slots remaining"
  },
  "booking": {
    "title": "Booking",
    "confirm": "Confirm Booking",
    "success": "Booking Confirmed!",
    "token": "Your Token",
    "add_crop": "Add Crop",
    "crop_name": "Crop Name",
    "quantity_kg": "Quantity (kg)",
    "cancel_booking": "Cancel Booking",
    "cancel_reason": "Reason for cancellation",
    "history": "Booking History",
    "status": {
      "pending": "Pending",
      "confirmed": "Confirmed",
      "cancelled": "Cancelled",
      "completed": "Completed",
      "expired": "Expired"
    }
  },
  "queue": {
    "title": "Live Queue",
    "current_token": "Current Token",
    "your_position": "Your Position",
    "farmers_ahead": "Farmers Ahead",
    "estimated_wait": "Estimated Wait",
    "status": {
      "waiting": "Waiting",
      "called": "Called",
      "in_progress": "In Progress",
      "completed": "Completed",
      "skipped": "Skipped",
      "cancelled": "Cancelled"
    }
  },
  "procurement": {
    "title": "Procurement Status",
    "crop_details": "Crop Details",
    "quantity": "Quantity",
    "quality": "Quality",
    "status": "Status",
    "notes": "Notes",
    "rejection_reason": "Rejection Reason"
  },
  "payment": {
    "title": "Payment Status",
    "amount": "Amount",
    "method": "Payment Method",
    "reference": "Reference",
    "status": {
      "pending": "Pending",
      "processing": "Processing",
      "paid": "Paid",
      "failed": "Failed"
    }
  },
  "notifications": {
    "title": "Notifications",
    "no_notifications": "No notifications yet",
    "mark_read": "Mark as Read"
  },
  "profile": {
    "title": "My Profile",
    "edit": "Edit Profile",
    "name": "Name",
    "mobile": "Mobile",
    "village": "Village",
    "district": "District",
    "farm_size": "Farm Size",
    "crops": "Crops"
  }
}
```

### translations/hi.json
```json
{
  "app_name": "किसान प्रक्रिया",
  "common": {
    "loading": "लोड हो रहा है...",
    "error": "कुछ गलत हो गया",
    "retry": "पुनः प्रयास करें",
    "cancel": "रद्द करें",
    "confirm": "पुष्टि करें",
    "save": "सहेजें",
    "back": "वापस",
    "next": "अगला",
    "yes": "हाँ",
    "no": "नहीं",
    "ok": "ठीक है",
    "search": "खोजें...",
    "no_data": "कोई डेटा उपलब्ध नहीं",
    "offline": "आप ऑफ़लाइन हैं"
  },
"auth": {
    "login": "लॉगिन",
    "register": "पंजीकरण",
    "logout": "लॉग आउट",
    "mobile": "मोबाइल नंबर",
    "password": "पासवर्ड",
    "otp": "OTP दर्ज करें",
    "forgot_password": "पासवर्ड भूल गए?",
    "login_title": "वापसी पर स्वागत",
    "register_title": "खाता बनाएं",
    "otp_title": "मोबाइल सत्यापित करें",
    "twofa_title": "लॉगिन OTP सत्यापित करें",
    "twofa_subtitle": "अपने मोबाइल पर भेजा गया 6-अंकीय OTP दर्ज करें",
    "twofa_resend": "OTP पुनः भेजें",
    "enable_2fa": "दो-कारक प्रमाणीकरण चालू करें",
    "disable_2fa": "दो-कारक प्रमाणीकरण बंद करें",
    "2fa_enabled": "दो-कारक प्रमाणीकरण चालू है",
    "2fa_disabled": "दो-कारक प्रमाणीकरण बंद है",
    "verification_pending": "सत्यापन लंबित",
    "verification_rejected": "सत्यापन अस्वीकृत"
  },
  "home": {
    "welcome": "स्वागत है, {name}",
    "book_slot": "स्लॉट बुक करें",
    "my_bookings": "मेरी बुकिंग",
    "view_queue": "कतार देखें"
  },
  "centres": {
    "title": "प्रक्रिया केंद्र",
    "select": "केंद्र चुनें",
    "no_centres": "कोई केंद्र उपलब्ध नहीं"
  },
  "booking": {
    "title": "बुकिंग",
    "confirm": "बुकिंग की पुष्टि करें",
    "success": "बुकिंग की पुष्टि हो गई!",
    "token": "आपका टोकन",
    "add�ी": "फसल जोड़ें",
    "crop_name": "फसल का नाम",
    "quantity_kg": "मात्रा (किलोग्राम)"
  },
  "queue": {
    "title": "लाइव कतार",
    "current_token": "वर्तमान टोकन",
    "your_position": "आपकी स्थिति",
    "farmers_ahead": "किसान आगे",
    "estimated_wait": "अनुमानित प्रतीक्षा"
  },
  "notifications": {
    "title": "सूचनाएं",
    "no_notifications": "अभी तक कोई सूचना नहीं"
  },
  "profile": {
    "title": "मेरी प्रोफ़ाइल",
    "edit": "प्रोफ़ाइल संपादित करें"
  }
}
```

## Routing

```dart
// core/constants/route_names.dart
class RouteNames {
  static const splash = '/';
  static const languageSelection = '/language';
  static const login = '/login';
  static const twofa = '/twofa';
  static const register = '/register';
  static const otpVerification = '/otp';
  static const farmerDetails = '/farmer-details';
  static const home = '/home';
  static const profile = '/profile';
  static const editProfile = '/profile/edit';
  static const centreList = '/centres';
  static const centreDetail = '/centres/:id';
  static const dateSelection = '/bookings/select-date/:centreId';
  static const slotSelection = '/bookings/select-slot/:centreId/:date';
  static const bookingConfirmation = '/bookings/confirm/:bookingId';
  static const bookingHistory = '/bookings/history';
  static const bookingDetail = '/bookings/:id';
  static const cancellation = '/bookings/:id/cancel';
  static const liveQueue = '/queue/:centreId/:date/:bookingId';
  static const token = '/token/:bookingId';
  static const procurementStatus = '/procurement/:bookingId';
  static const paymentStatus = '/payment/:procurementId';
  static const notifications = '/notifications';
  static const notificationDetail = '/notifications/:id';
  static const help = '/help';
  static const contact = '/contact';
}
```

## Error Handling Strategy

```dart
// Every screen/widget handles these states:

// 1. Loading
if (isLoading) return LoadingIndicator();

// 2. Error
if (error != null) return ErrorDisplay(
  message: error,
  onRetry: () => retry(),
);

// 3. Empty
if (data.isEmpty) return EmptyState(
  icon: Icons.inbox,
  title: 'No Data',
  subtitle: 'Nothing to show here',
);

// 4. Offline
if (!connectivity.isOnline) return OfflineBanner();
// Disable action buttons that require network

// 5. Session expired
if (error.code == 'SESSION_EXPIRED') {
  await auth.logout();
  Navigator.pushReplacementNamed(context, RouteNames.login);
}

// 6. Maintenance mode
if (error.code == 'MAINTENANCE') {
  return MaintenanceScreen(message: error.message);
}
```

## Dependencies Summary (pubspec.yaml)

```yaml
name: farmer_procurement_app
description: Farmer Procurement & Queue Management System
version: 1.0.0+1

environment:
  sdk: '>=3.1.0 <4.0.0'

dependencies:
  flutter:
    sdk: flutter

  # State management
  provider: ^6.1.1

  # Network
  http: ^1.1.0

  # Storage
  flutter_secure_storage: ^9.0.0
  shared_preferences: ^2.2.2

  # UI
  qr_flutter: ^4.1.0
  cached_network_image: ^3.3.0
  shimmer: ^3.0.0

  # Utils
  intl: ^0.18.1
  connectivity_plus: ^5.0.2
  device_info_plus: ^9.1.1
  package_info_plus: ^4.2.0
  url_launcher: ^6.2.1
  uuid: ^4.2.1

  # Push notifications — OneSignal
  onesignal_flutter: ^5.1.1

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^3.0.0

flutter:
  uses-material-design: true
  assets:
    - assets/images/
    - assets/icons/
    - assets/translations/
```

## Build & Run

```bash
# Development
flutter run --debug

# Release APK
flutter build apk --release

# Split APKs (by ABI)
flutter build apk --split-per-abi

# App bundle (for Play Store)
flutter build appbundle --release
```

---

**Next**: [06-api-architecture.md](06-api-architecture.md) for API design principles.