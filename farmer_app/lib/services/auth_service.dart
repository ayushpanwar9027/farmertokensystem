import '../core/network/api_client.dart';
import '../core/network/api_response.dart';
import '../core/constants/api_constants.dart';

class AuthService {
  final _api = ApiClient();

  Future<ApiResponse<dynamic>> register(String mobile, String deviceId) async {
    return _api.post(ApiConstants.register, body: {
      'mobile': mobile,
      'device_id': deviceId,
    });
  }

  Future<ApiResponse<dynamic>> verifyOtp(String verificationId, String otp) async {
    return _api.post(ApiConstants.verifyOtp, body: {
      'verification_id': verificationId,
      'otp': otp,
    });
  }

  Future<ApiResponse<dynamic>> resendOtp(String verificationId) async {
    return _api.post(ApiConstants.resendOtp, body: {
      'verification_id': verificationId,
    });
  }

  Future<ApiResponse<dynamic>> completeRegistration(
      Map<String, dynamic> data) async {
    return _api.post(ApiConstants.completeRegistration, body: data);
  }

  Future<ApiResponse<dynamic>> login({
    required String mobile,
    required String password,
    required String deviceId,
    bool rememberMe = false,
  }) async {
    return _api.post(ApiConstants.login, body: {
      'mobile': mobile,
      'password': password,
      'device_id': deviceId,
      'platform': 'android',
      'remember_me': rememberMe,
    });
  }

  Future<ApiResponse<dynamic>> verify2fa({
    required String verificationId,
    required String otp,
    required String deviceId,
  }) async {
    return _api.post(ApiConstants.verify2fa, body: {
      'verification_id': verificationId,
      'otp': otp,
      'device_id': deviceId,
      'platform': 'android',
    });
  }

  Future<ApiResponse<dynamic>> resend2fa(String verificationId) async {
    return _api.post(ApiConstants.resend2fa, body: {
      'verification_id': verificationId,
    });
  }

  Future<ApiResponse<dynamic>> enable2fa() async {
    return _api.authenticatedPost(ApiConstants.enable2fa);
  }

  Future<ApiResponse<dynamic>> enable2faConfirm(
      String verificationId, String otp) async {
    return _api.authenticatedPost(ApiConstants.enable2faConfirm, body: {
      'verification_id': verificationId,
      'otp': otp,
    });
  }

  Future<ApiResponse<dynamic>> disable2fa(String otp) async {
    return _api.authenticatedPost(ApiConstants.disable2fa, body: {
      'otp': otp,
    });
  }

  Future<ApiResponse<dynamic>> getProfile() async {
    return _api.authenticatedGet(ApiConstants.me);
  }

  Future<ApiResponse<dynamic>> logout(String refreshToken) async {
    return _api.authenticatedPost(ApiConstants.logout, body: {
      'refresh_token': refreshToken,
    });
  }
}
