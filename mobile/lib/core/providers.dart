import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'network/api_client.dart';
import 'storage/token_storage.dart';

/// App-wide singletons. Override these in tests.
final tokenStorageProvider = Provider<TokenStorage>((ref) => TokenStorage());

/// Current UI locale code sent to the API as Accept-Language.
final localeProvider = NotifierProvider<LocaleController, Locale?>(LocaleController.new);

class LocaleController extends Notifier<Locale?> {
  @override
  Locale? build() => null; // null = follow the device language

  void set(Locale? locale) => state = locale;
}

/// Raised by the API client when a stored token is rejected; the session listens and signs out.
final unauthorizedSignalProvider = NotifierProvider<UnauthorizedSignal, int>(UnauthorizedSignal.new);

class UnauthorizedSignal extends Notifier<int> {
  @override
  int build() => 0;

  void fire() => state++;
}

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    tokens: ref.watch(tokenStorageProvider),
    locale: () => ref.read(localeProvider)?.languageCode ?? WidgetsBinding.instance.platformDispatcher.locale.languageCode,
    onUnauthorized: () => ref.read(unauthorizedSignalProvider.notifier).fire(),
  );
});

/// `true` while the device reports any network connection. Core screens still render cached
/// data and show an offline banner when `false`.
final connectivityProvider = StreamProvider<bool>((ref) async* {
  final connectivity = Connectivity();
  bool isOnline(List<ConnectivityResult> r) => r.any((c) => c != ConnectivityResult.none);
  yield isOnline(await connectivity.checkConnectivity());
  yield* connectivity.onConnectivityChanged.map(isOnline);
});
