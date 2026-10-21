import 'package:dio/dio.dart';

/// Typed API failure. `message` is safe to show to the user.
class ApiException implements Exception {
  ApiException({
    required this.message,
    this.code,
    this.statusCode,
    this.fieldErrors = const {},
    this.isOffline = false,
  });

  final String message;

  /// Server business-rule code, e.g. `registration_closed`, `already_registered`.
  final String? code;
  final int? statusCode;
  final Map<String, List<String>> fieldErrors;
  final bool isOffline;

  bool get isUnauthorized => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isNotFound => statusCode == 404;
  bool get isValidation => statusCode == 422 && fieldErrors.isNotEmpty;

  String? fieldError(String field) => fieldErrors[field]?.first;

  factory ApiException.fromDio(DioException e) {
    final offline = e.type == DioExceptionType.connectionError ||
        e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout;
    if (offline) {
      return ApiException(message: 'offline', isOffline: true);
    }

    final response = e.response;
    final data = response?.data;
    final status = response?.statusCode;

    if (data is Map) {
      final errors = <String, List<String>>{};
      final raw = data['errors'];
      if (raw is Map) {
        raw.forEach((key, value) {
          if (value is List) errors['$key'] = value.map((v) => '$v').toList();
        });
      }
      return ApiException(
        message: (data['message'] as String?)?.trim().isNotEmpty == true ? data['message'] as String : 'unknown',
        code: data['code'] as String?,
        statusCode: status,
        fieldErrors: errors,
      );
    }

    return ApiException(message: status == 429 ? 'rate_limited' : 'unknown', statusCode: status);
  }

  @override
  String toString() => 'ApiException($statusCode, $code, $message)';
}
