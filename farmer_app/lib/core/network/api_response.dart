class ApiResponse<T> {
  final bool success;
  final T? data;
  final String? errorMessage;
  final String? errorCode;
  final int? statusCode;
  final Map<String, dynamic>? meta;

  ApiResponse({
    required this.success,
    this.data,
    this.errorMessage,
    this.errorCode,
    this.statusCode,
    this.meta,
  });

  factory ApiResponse.success(T data, [Map<String, dynamic>? meta]) =>
      ApiResponse(success: true, data: data, meta: meta);

  factory ApiResponse.error(
    String message,
    String code, {
    int? statusCode,
  }) =>
      ApiResponse(
        success: false,
        errorMessage: message,
        errorCode: code,
        statusCode: statusCode,
      );
}
