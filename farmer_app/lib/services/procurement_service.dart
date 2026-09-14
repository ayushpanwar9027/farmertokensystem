import '../core/network/api_client.dart';
import '../core/network/api_response.dart';
import '../core/constants/api_constants.dart';

class ProcurementService {
  final _api = ApiClient();

  Future<ApiResponse<dynamic>> getMyProcurements() async {
    return _api.authenticatedGet(ApiConstants.procurements);
  }

  Future<ApiResponse<dynamic>> getProcurementDetail(int id) async {
    return _api.authenticatedGet('${ApiConstants.procurements}/$id');
  }
}
