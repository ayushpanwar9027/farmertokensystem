import 'dart:math';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../constants/storage_keys.dart';

class SecureStorage {
  static late FlutterSecureStorage _storage;

  static Future<void> initialize() async {
    _storage = const FlutterSecureStorage(
      aOptions: AndroidOptions(encryptedSharedPreferences: true),
    );
  }

  static Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _storage.write(key: StorageKeys.accessToken, value: accessToken);
    await _storage.write(key: StorageKeys.refreshToken, value: refreshToken);
  }

  static Future<String?> getAccessToken() async =>
      _storage.read(key: StorageKeys.accessToken);

  static Future<String?> getRefreshToken() async =>
      _storage.read(key: StorageKeys.refreshToken);

  static Future<String> getDeviceId() async {
    var id = await _storage.read(key: StorageKeys.deviceId);
    if (id == null) {
      id = _generateDeviceId();
      await _storage.write(key: StorageKeys.deviceId, value: id);
    }
    return id;
  }

  static Future<void> saveUserId(int id) async {
    await _storage.write(key: StorageKeys.userId, value: id.toString());
  }

  static Future<int?> getUserId() async {
    final v = await _storage.read(key: StorageKeys.userId);
    return v != null ? int.tryParse(v) : null;
  }

  static Future<void> saveVerificationId(String id) async {
    await _storage.write(key: StorageKeys.verificationId, value: id);
  }

  static Future<String?> getVerificationId() async =>
      _storage.read(key: StorageKeys.verificationId);

  static Future<void> clearVerificationId() async {
    await _storage.delete(key: StorageKeys.verificationId);
  }

  static Future<void> clearAuth() async {
    await _storage.delete(key: StorageKeys.accessToken);
    await _storage.delete(key: StorageKeys.refreshToken);
    await _storage.delete(key: StorageKeys.userId);
    await _storage.delete(key: StorageKeys.verificationId);
  }

  static Future<void> clearAll() async => _storage.deleteAll();

  static String _generateDeviceId() {
    final r = Random();
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    final suffix = List.generate(
      12,
      (_) => chars.codeUnitAt(r.nextInt(chars.length)),
    );
    return 'fps_${DateTime.now().millisecondsSinceEpoch}_${String.fromCharCodes(suffix)}';
  }
}
