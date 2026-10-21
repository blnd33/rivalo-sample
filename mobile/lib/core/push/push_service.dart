import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/engagement/data/engagement.dart';

/// Where the phone's push token comes from.
///
/// The app currently ships WITHOUT a push SDK, because phone delivery needs a Firebase project
/// owned by RIVALO (google-services.json / GoogleService-Info.plist and an APNs key). Until that
/// exists, [NoPushTokenSource] is used: the inbox works fully, phones just don't buzz.
///
/// To enable: add `firebase_core` + `firebase_messaging`, create a `FirebasePushTokenSource`
/// that asks for permission and returns `FirebaseMessaging.instance.getToken()`, and override
/// [pushTokenSourceProvider] in `main.dart`. See docs/07-DEPLOYMENT-AND-PILOT.md.
abstract interface class PushTokenSource {
  /// Platform name sent to the server: ios | android | web.
  String get platform;

  /// Asks for permission if needed; returns null when push is unavailable or declined.
  Future<String?> token();
}

class NoPushTokenSource implements PushTokenSource {
  const NoPushTokenSource();

  @override
  String get platform => kIsWeb ? 'web' : (defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android');

  @override
  Future<String?> token() async => null;
}

final pushTokenSourceProvider = Provider<PushTokenSource>((ref) => const NoPushTokenSource());

/// Registers this phone with the server once per signed-in session (no-op without a token).
final pushRegistrationProvider = FutureProvider.autoDispose<bool>((ref) async {
  final source = ref.watch(pushTokenSourceProvider);
  final token = await source.token();
  if (token == null) return false;
  await ref.watch(engagementRepositoryProvider).registerDevice(token, source.platform);
  return true;
});
