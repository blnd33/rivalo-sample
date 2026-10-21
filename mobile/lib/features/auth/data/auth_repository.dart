import 'dart:io' show Platform;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/storage/token_storage.dart';
import 'me.dart';

class AuthRepository {
  AuthRepository(this._api, this._tokens);

  final ApiClient _api;
  final TokenStorage _tokens;

  /// Label for this device's token, visible in Settings > Signed-in devices.
  static String get deviceName => kIsWeb ? 'Web preview' : (Platform.isIOS ? 'iPhone app' : (Platform.isAndroid ? 'Android app' : 'RIVALO app'));

  Future<Me?> restore() async {
    final token = await _tokens.read();
    if (token == null) return null;
    final json = await _api.get('/me');
    return Me.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<Me> login({required String login, required String password}) async {
    final json = await _api.post('/auth/login', body: {'login': login, 'password': password, 'device_name': deviceName});
    await _tokens.write(json['token'] as String);
    return Me.fromJson(Map<String, dynamic>.from(json['user'] as Map));
  }

  Future<Me> register({
    required String username,
    required String email,
    required String password,
    String? displayName,
    required String locale,
  }) async {
    final json = await _api.post('/auth/register', body: {
      'username': username,
      'email': email,
      'password': password,
      'password_confirmation': password,
      if (displayName != null && displayName.trim().isNotEmpty) 'display_name': displayName.trim(),
      'locale': locale,
      'device_name': deviceName,
    });
    await _tokens.write(json['token'] as String);
    return Me.fromJson(Map<String, dynamic>.from(json['user'] as Map));
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } finally {
      await _tokens.clear();
    }
  }

  Future<void> logoutAll() async {
    try {
      await _api.post('/auth/logout-all');
    } finally {
      await _tokens.clear();
    }
  }

  Future<void> resendVerification() => _api.post('/auth/email/resend');

  Future<void> forgotPassword(String email) => _api.post('/auth/forgot-password', body: {'email': email});

  Future<void> clearLocalSession() => _tokens.clear();
}

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(apiClientProvider), ref.watch(tokenStorageProvider)));
