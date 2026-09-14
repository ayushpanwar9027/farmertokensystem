import '../core/network/api_client.dart';
import '../core/network/api_response.dart';
import '../core/constants/api_constants.dart';

class PaymentService {
  final _api = ApiClient();

  Future<ApiResponse<dynamic>> getMyPayments() async {
    return _api.authenticatedGet(ApiConstants.payments);
  }

  Future<ApiResponse<dynamic>> getPaymentDetail(int id) async {
    return _api.authenticatedGet('${ApiConstants.payments}/$id');
  }
}
