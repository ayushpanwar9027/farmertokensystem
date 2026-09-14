import '../core/network/api_client.dart';
import '../core/network/api_response.dart';
import '../core/constants/api_constants.dart';

class BookingService {
  final _api = ApiClient();

  Future<ApiResponse<dynamic>> createBooking(Map<String, dynamic> payload) async {
    return _api.authenticatedPost(ApiConstants.bookings, body: payload);
  }

  Future<ApiResponse<dynamic>> getBookings() async {
    return _api.authenticatedGet(ApiConstants.bookings);
  }

  Future<ApiResponse<dynamic>> getBookingDetail(int id) async {
    return _api.authenticatedGet('${ApiConstants.bookings}/$id');
  }

  Future<ApiResponse<dynamic>> cancelBooking(int id, String reason) async {
    return _api.authenticatedPost('${ApiConstants.bookings}/$id/cancel',
        body: {'reason': reason});
  }

  Future<ApiResponse<dynamic>> getCentres({int? districtId}) async {
    final params = <String, String>{};
    if (districtId != null) params['district_id'] = districtId.toString();
    return _api.authenticatedGet(ApiConstants.centres, queryParams: params);
  }

  Future<ApiResponse<dynamic>> getCentreDetail(int id) async {
    return _api.authenticatedGet('${ApiConstants.centres}/$id');
  }

  Future<ApiResponse<dynamic>> getSlots(int centreId, String date) async {
    return _api.authenticatedGet(ApiConstants.slots, queryParams: {
      'centre_id': centreId.toString(),
      'date': date,
    });
  }

  Future<ApiResponse<dynamic>> getCrops() async {
    return _api.authenticatedGet(ApiConstants.crops);
  }
}
