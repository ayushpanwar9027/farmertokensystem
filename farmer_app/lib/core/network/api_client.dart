import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/foundation.dart';
import '../constants/api_constants.dart';
import '../storage/secure_storage.dart';
import 'api_response.dart';

class ApiClient {
  static final ApiClient _instance = ApiClient._internal();
  factory ApiClient() => _instance;
  ApiClient._internal();

  final http.Client _client = http.Client();

  Future<Map<String, String>> _buildHeaders() async {
    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    final token = await SecureStorage.getAccessToken();
    if (token != null) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  Future<ApiResponse<dynamic>> get(
    String path, {
    Map<String, String>? queryParams,
  }) async {
    try {
      final uri = Uri.parse('${ApiConstants.baseUrl}$path')
          .replace(queryParameters: queryParams);
      final headers = await _buildHeaders();
      final response = await _client
          .get(uri, headers: headers)
          .timeout(const Duration(seconds: 30));
      return _handleResponse(response);
    } catch (e) {
      if (e is http.ClientException) {
        return ApiResponse.error('No internet connection', 'NETWORK_ERROR');
      }
      return ApiResponse.error('Request failed: $e', 'UNKNOWN_ERROR');
    }
  }

  Future<ApiResponse<dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    try {
      final uri = Uri.parse('${ApiConstants.baseUrl}$path');
      final headers = await _buildHeaders();
      final response = await _client
          .post(uri, headers: headers, body: body != null ? jsonEncode(body) : null)
          .timeout(const Duration(seconds: 30));
      return _handleResponse(response);
    } catch (e) {
      return ApiResponse.error('Request failed: $e', 'UNKNOWN_ERROR');
    }
  }

  Future<ApiResponse<dynamic>> patch(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    try {
      final uri = Uri.parse('${ApiConstants.baseUrl}$path');
      final headers = await _buildHeaders();
      final response = await _client
          .patch(uri, headers: headers, body: body != null ? jsonEncode(body) : null)
          .timeout(const Duration(seconds: 30));
      return _handleResponse(response);
    } catch (e) {
      return ApiResponse.error('Request failed: $e', 'UNKNOWN_ERROR');
    }
  }

  Future<ApiResponse<dynamic>> _handleResponse(http.Response response) async {
    final body = jsonDecode(response.body);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return ApiResponse.success(body['data'], body['meta']);
    }

    if (response.statusCode == 401) {
      final refreshed = await _tryRefreshToken();
      if (refreshed) {
        return ApiResponse.error('SESSION_REFRESHED', 'SESSION_REFRESHED',
            statusCode: 401);
      }
      return ApiResponse.error(
        body['error']?['message'] ?? 'Session expired',
        body['error']?['code'] ?? 'SESSION_EXPIRED',
        statusCode: 401,
      );
    }

    if (response.statusCode == 503) {
      return ApiResponse.error(
        body['error']?['message'] ?? 'System under maintenance',
        'MAINTENANCE',
        statusCode: 503,
      );
    }

    return ApiResponse.error(
      body['error']?['message'] ?? 'Request failed',
      body['error']?['code'] ?? 'API_ERROR',
      statusCode: response.statusCode,
    );
  }

  Future<bool> _tryRefreshToken() async {
    try {
      final refreshToken = await SecureStorage.getRefreshToken();
      if (refreshToken == null) return false;

      final uri = Uri.parse('${ApiConstants.baseUrl}${ApiConstants.refresh}');
      final response = await _client
          .post(
            uri,
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'refresh_token': refreshToken}),
          )
          .timeout(const Duration(seconds: 15));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final tokens = data['data'];
        await SecureStorage.saveTokens(
          accessToken: tokens['access_token'],
          refreshToken: tokens['refresh_token'],
        );
        return true;
      }
      return false;
    } catch (e) {
      if (kDebugMode) print('Refresh token failed: $e');
      return false;
    }
  }

  Future<ApiResponse<dynamic>> authenticatedGet(
    String path, {
    Map<String, String>? queryParams,
  }) async {
    var response = await get(path, queryParams: queryParams);
    if (response.errorCode == 'SESSION_REFRESHED') {
      response = await get(path, queryParams: queryParams);
    }
    return response;
  }

  Future<ApiResponse<dynamic>> authenticatedPost(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    var response = await post(path, body: body);
    if (response.errorCode == 'SESSION_REFRESHED') {
      response = await post(path, body: body);
    }
    return response;
  }

  Future<ApiResponse<dynamic>> authenticatedPatch(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    var response = await patch(path, body: body);
    if (response.errorCode == 'SESSION_REFRESHED') {
      response = await patch(path, body: body);
    }
    return response;
  }
}
