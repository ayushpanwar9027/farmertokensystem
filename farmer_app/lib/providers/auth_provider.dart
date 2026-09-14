import 'package:flutter/foundation.dart';
import '../models/user.dart';
import '../services/auth_service.dart';
import '../services/push_service.dart';
import '../core/storage/secure_storage.dart';

enum AuthStatus { unknown, unauthenticated, authenticated, pending2fa, pendingRegistration }

class AuthProvider extends ChangeNotifier {
  final AuthService _authService;

  AuthProvider({AuthService? authService})
      : _authService = authService ?? AuthService();

  AuthStatus _status = AuthStatus.unknown;
  User? _user;
  bool _isLoading = false;
  String? _error;
  String? _errorCode;
  String? _pending2faVerificationId;
  int _resendAfter = 0;
  String? _registrationToken;
  String? _pending2faMobile;

  AuthStatus get status => _status;
  User? get user => _user;
  bool get isLoading => _isLoading;
  String? get error => _error;
  String? get errorCode => _errorCode;
  String? get pending2faVerificationId => _pending2faVerificationId;
  int get resendAfter => _resendAfter;
  String? get pending2faMobile => _pending2faMobile;

  bool get isAuthenticated => _status == AuthStatus.authenticated;
  bool get isPending2fa => _status == AuthStatus.pending2fa;

  Future<void> checkAuth() async {
    _isLoading = true;
    notifyListeners();
    try {
      final token = await SecureStorage.getAccessToken();
      if (token == null) {
        _status = AuthStatus.unauthenticated;
        return;
      }
      final response = await _authService.getProfile();
      if (response.success) {
        _user = User.fromJson(response.data);
        _status = AuthStatus.authenticated;
        PushService.registerDeviceWithBackend();
      } else {
        await SecureStorage.clearAuth();
        _status = AuthStatus.unauthenticated;
      }
    } catch (e) {
      _status = AuthStatus.unauthenticated;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> login(String mobile, String password, {bool rememberMe = false}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final deviceId = await SecureStorage.getDeviceId();
      final response = await _authService.login(
        mobile: mobile,
        password: password,
        deviceId: deviceId,
        rememberMe: rememberMe,
      );

      if (response.success) {
        if (response.statusCode == 202 ||
            response.data is Map && response.data['two_factor_required'] == true) {
          _pending2faVerificationId = response.data['verification_id'];
          _resendAfter = response.data['resend_after'] ?? 60;
          _pending2faMobile = mobile;
          await SecureStorage.saveVerificationId(_pending2faVerificationId!);
          _status = AuthStatus.pending2fa;
          notifyListeners();
          return false;
        }

        final data = response.data;
        await SecureStorage.saveTokens(
          accessToken: data['access_token'],
          refreshToken: data['refresh_token'],
        );
        _user = User.fromJson(data['user']);
        if (_user?.id != null) await SecureStorage.saveUserId(_user!.id);
        _status = AuthStatus.authenticated;
        PushService.requestPermission().then((_) =>
            PushService.registerDeviceWithBackend());
        notifyListeners();
        return true;
      } else {
        _error = response.errorMessage;
        _errorCode = response.errorCode;
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

  Future<bool> verify2fa(String otp) async {
    if (_pending2faVerificationId == null) {
      _error = '2FA not requested';
      notifyListeners();
      return false;
    }
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final deviceId = await SecureStorage.getDeviceId();
      final response = await _authService.verify2fa(
        verificationId: _pending2faVerificationId!,
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
        if (_user?.id != null) await SecureStorage.saveUserId(_user!.id);
        await SecureStorage.clearVerificationId();
        _pending2faVerificationId = null;
        _status = AuthStatus.authenticated;
        PushService.requestPermission().then((_) =>
            PushService.registerDeviceWithBackend());
        notifyListeners();
        return true;
      }
      _error = response.errorMessage;
      _errorCode = response.errorCode;
      notifyListeners();
      return false;
    } catch (e) {
      _error = '2FA verification failed. Please try again.';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> resend2fa() async {
    if (_pending2faVerificationId == null) return false;
    try {
      final response = await _authService.resend2fa(_pending2faVerificationId!);
      if (response.success) {
        _resendAfter = response.data['resend_after'] ?? 60;
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      return false;
    }
  }

  void decrementResendCooldown() {
    if (_resendAfter > 0) {
      _resendAfter--;
      notifyListeners();
    }
  }

  Future<void> startRegistration(String mobile) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final deviceId = await SecureStorage.getDeviceId();
      final response = await _authService.register(mobile, deviceId);
      if (response.success) {
        _pending2faVerificationId = response.data['verification_id'];
        _resendAfter = response.data['resend_after'] ?? 60;
        _pending2faMobile = mobile;
        await SecureStorage.saveVerificationId(_pending2faVerificationId!);
        _status = AuthStatus.pendingRegistration;
        notifyListeners();
      } else {
        _error = response.errorMessage;
        notifyListeners();
      }
    } catch (e) {
      _error = 'Registration failed. Please try again.';
      notifyListeners();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> verifyRegistrationOtp(String otp) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _authService.verifyOtp(
        _pending2faVerificationId!,
        otp,
      );
      if (response.success) {
        _registrationToken = response.data['registration_token'];
        notifyListeners();
        return true;
      }
      _error = response.errorMessage;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'OTP verification failed.';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> completeRegistration(Map<String, dynamic> data) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _authService.completeRegistration({
        ...data,
        'registration_token': _registrationToken,
      });
      if (response.success) {
        _status = AuthStatus.unauthenticated;
        _registrationToken = null;
        notifyListeners();
        return true;
      }
      _error = response.errorMessage;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Registration completion failed.';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> enable2fa() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _authService.enable2fa();
      if (response.success) {
        _pending2faVerificationId = response.data['verification_id'];
        _resendAfter = response.data['resend_after'] ?? 60;
        notifyListeners();
        return true;
      }
      _error = response.errorMessage;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Failed to enable 2FA.';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> enable2faConfirm(String otp) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _authService.enable2faConfirm(
        _pending2faVerificationId!,
        otp,
      );
      if (response.success) {
        _user = _user?.copyWith(twoFactorEnabled: true);
        _pending2faVerificationId = null;
        notifyListeners();
        return true;
      }
      _error = response.errorMessage;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Failed to confirm 2FA.';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> disable2fa(String otp) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final response = await _authService.disable2fa(otp);
      if (response.success) {
        _user = _user?.copyWith(twoFactorEnabled: false);
        notifyListeners();
        return true;
      }
      _error = response.errorMessage;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Failed to disable 2FA.';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    try {
      final refreshToken = await SecureStorage.getRefreshToken();
      if (refreshToken != null) {
        await _authService.logout(refreshToken);
      }
    } catch (_) {}
    await SecureStorage.clearAuth();
    _user = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void setManual2faPending(String verificationId, String mobile) {
    _pending2faVerificationId = verificationId;
    _pending2faMobile = mobile;
    _status = AuthStatus.pending2fa;
    notifyListeners();
  }
}
