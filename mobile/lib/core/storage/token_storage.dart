import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Stores the Sanctum access token in the iOS Keychain / Android Keystore-backed storage.
/// Never use SharedPreferences for credentials.
class TokenStorage {
  TokenStorage([FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;
  static const _key = 'rivalo.access_token';

  String? _cached;

  String? get current => _cached;

  Future<String?> read() async {
    _cached ??= await _storage.read(key: _key);
    return _cached;
  }

  Future<void> write(String token) async {
    _cached = token;
    await _storage.write(key: _key, value: token);
  }

  Future<void> clear() async {
    _cached = null;
    await _storage.delete(key: _key);
  }
}
