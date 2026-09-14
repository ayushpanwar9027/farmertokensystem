import '../core/network/api_client.dart';
import '../core/network/api_response.dart';
import '../core/constants/api_constants.dart';

class QueueService {
  final _api = ApiClient();

  Future<ApiResponse<dynamic>> getLiveQueue({
    required int centreId,
    required String date,
    required int bookingId,
  }) async {
    return _api.authenticatedGet('${ApiConstants.queue}/$centreId/$date',
        queryParams: {'booking_id': bookingId.toString()});
  }

  Future<ApiResponse<dynamic>> getMyQueue() async {
    return _api.authenticatedGet('${ApiConstants.queue}/my');
  }
}
