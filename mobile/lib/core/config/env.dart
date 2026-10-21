import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

/// Build-time configuration. The app contains NO secrets: only the public API base URL.
///
/// Override per build:
///   flutter run --dart-define=API_BASE_URL=https://api.staging.example.com/api/v1
abstract final class Env {
  static const String _apiOverride = String.fromEnvironment('API_BASE_URL');

  /// Shows the developer design-system gallery entry in Settings (debug builds only by default).
  static const bool showDevTools = bool.fromEnvironment('SHOW_DEV_TOOLS', defaultValue: kDebugMode);

  /// Sent with problem reports so staff know which build a player uses. Keep in step with pubspec.yaml.
  static const String appVersion = String.fromEnvironment('APP_VERSION', defaultValue: '1.0.0');

  static String get apiBaseUrl {
    if (_apiOverride.isNotEmpty) return _apiOverride;
    // Local development defaults: the Android emulator reaches the host machine via 10.0.2.2.
    if (!kIsWeb && Platform.isAndroid) return 'http://10.0.2.2:8000/api/v1';
    return 'http://localhost:8000/api/v1';
  }
}
