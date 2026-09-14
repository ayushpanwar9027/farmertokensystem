import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:farmer_app/core/storage/secure_storage.dart';

class MockSecureStorage {
  static const MethodChannel _channel =
      MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  static final Map<String, String> _store = {};

  static void install() {
    TestWidgetsFlutterBinding.ensureInitialized();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(_channel, (call) async {
      switch (call.method) {
        case 'read':
          return _store[call.arguments['key']];
        case 'write':
          _store[call.arguments['key']] = call.arguments['value'];
          return null;
        case 'delete':
          _store.remove(call.arguments['key']);
          return null;
        case 'readAll':
          return Map<String, String>.from(_store);
        case 'deleteAll':
          _store.clear();
          return null;
        case 'containsKey':
          return _store.containsKey(call.arguments['key']);
      }
      return null;
    });
    SecureStorage.initialize();
  }

  static void clear() => _store.clear();
}