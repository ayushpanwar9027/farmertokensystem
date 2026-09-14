import 'package:flutter/foundation.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';
import '../config/env.dart';
import '../core/network/api_client.dart';
import '../core/constants/api_constants.dart';
import '../core/storage/secure_storage.dart';

class PushService {
  static bool _initialized = false;
  static Function(String event, dynamic entityId)? onDeepLink;

  static Future<void> initialize() async {
    if (_initialized) return;
    if (AppConfig.oneSignalAppId == 'your-onesignal-app-id') {
      if (kDebugMode) print('OneSignal: using placeholder app ID');
      return;
    }

    try {
      OneSignal.Debug.setLogLevel(OSLogLevel.warn);
      OneSignal.initialize(AppConfig.oneSignalAppId);

      OneSignal.Notifications.addClickListener((event) {
        final data = event.notification.additionalData;
        if (data != null) {
          final event = data['event'] as String?;
          final entityId = data['entity_id'];
          if (event != null) {
            onDeepLink?.call(event, entityId);
          }
        }
      });

      _initialized = true;
      if (kDebugMode) print('OneSignal initialized');
    } catch (e) {
      if (kDebugMode) print('OneSignal init failed: $e');
    }
  }

  static Future<bool> requestPermission() async {
    if (!_initialized) return false;
    try {
      final result = await OneSignal.Notifications.requestPermission(true);
      return result;
    } catch (e) {
      if (kDebugMode) print('Permission request failed: $e');
      return false;
    }
  }

  static Future<void> registerDeviceWithBackend() async {
    if (!_initialized) return;
    try {
      final subscriptionId = OneSignal.User.pushSubscription.id;
      if (subscriptionId == null || subscriptionId.isEmpty) {
        if (kDebugMode) print('OneSignal: no subscription ID yet');
        return;
      }

      final api = ApiClient();
      final deviceId = await SecureStorage.getDeviceId();
      await api.authenticatedPost(ApiConstants.registerDevice, body: {
        'device_id': deviceId,
        'onesignal_player_id': subscriptionId,
        'platform': 'android',
      });
      if (kDebugMode) print('Device registered with backend');
    } catch (e) {
      if (kDebugMode) print('Device registration failed: $e');
    }
  }
}
