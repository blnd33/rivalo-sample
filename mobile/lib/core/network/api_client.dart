import 'dart:io';

import 'package:dio/dio.dart';

import '../config/env.dart';
import '../storage/token_storage.dart';
import '../utils/json.dart';
import 'api_exception.dart';

/// Thin typed wrapper around Dio for the RIVALO API (`/api/v1`).
/// Adds the bearer token and locale, and converts every failure into [ApiException].
class ApiClient {
  ApiClient({required TokenStorage tokens, required String Function() locale, void Function()? onUnauthorized, Dio? dio})
      : _tokens = tokens,
        _dio = dio ?? Dio() {
    _dio.options = BaseOptions(
      baseUrl: Env.apiBaseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 20),
      sendTimeout: const Duration(seconds: 30),
      headers: {HttpHeaders.acceptHeader: 'application/json'},
    );
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = _tokens.current;
        if (token != null) options.headers[HttpHeaders.authorizationHeader] = 'Bearer $token';
        options.headers[HttpHeaders.acceptLanguageHeader] = locale();
        handler.next(options);
      },
      onError: (error, handler) {
        if (error.response?.statusCode == 401 && _tokens.current != null) onUnauthorized?.call();
        handler.next(error);
      },
    ));
  }

  final Dio _dio;
  final TokenStorage _tokens;

  Future<Json> get(String path, {Map<String, dynamic>? query}) => _send(() => _dio.get<dynamic>(path, queryParameters: _clean(query)));

  Future<Json> post(String path, {Object? body}) => _send(() => _dio.post<dynamic>(path, data: body));

  Future<Json> put(String path, {Object? body}) => _send(() => _dio.put<dynamic>(path, data: body));

  Future<Json> patch(String path, {Object? body}) => _send(() => _dio.patch<dynamic>(path, data: body));

  Future<Json> delete(String path, {Object? body}) => _send(() => _dio.delete<dynamic>(path, data: body));

  Future<Json> upload(String path, {required String field, required String filePath}) async {
    final form = FormData.fromMap({field: await MultipartFile.fromFile(filePath)});
    return _send(() => _dio.post<dynamic>(path, data: form));
  }

  Future<Json> _send(Future<Response<dynamic>> Function() request) async {
    try {
      final response = await request();
      final data = response.data;
      if (data is Map<String, dynamic>) return data;
      return <String, dynamic>{'data': data};
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Map<String, dynamic>? _clean(Map<String, dynamic>? query) {
    if (query == null) return null;
    return Map.of(query)..removeWhere((_, v) => v == null);
  }
}
