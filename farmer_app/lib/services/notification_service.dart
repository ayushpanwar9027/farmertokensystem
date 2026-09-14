import '../core/network/api_client.dart';
import '../core/network/api_response.dart';
import '../core/constants/api_constants.dart';

class NotificationService {
  final _api = ApiClient();

  Future<ApiResponse<dynamic>> getNotifications({int page = 1}) async {
    return _api.authenticatedGet(ApiConstants.notifications,
        queryParams: {'page': page.toString()});
  }

  Future<ApiResponse<dynamic>> getNotificationDetail(int id) async {
    return _api.authenticatedGet('${ApiConstants.notifications}/$id');
  }

  Future<ApiResponse<dynamic>> markRead(int id) async {
    return _api.authenticatedPatch('${ApiConstants.notifications}/$id/read');
  }

  Future<ApiResponse<dynamic>> markAllRead() async {
    return _api.authenticatedPatch('${ApiConstants.notifications}/read-all');
  }

  Future<ApiResponse<dynamic>> registerDevice(String playerId) async {
    return _api.authenticatedPost(ApiConstants.registerDevice, body: {
      'onesignal_player_id': playerId,
    });
  }
}
