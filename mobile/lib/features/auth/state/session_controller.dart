import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../data/auth_repository.dart';
import '../data/me.dart';

sealed class SessionState {
  const SessionState();
}

class SessionRestoring extends SessionState {
  const SessionRestoring();
}

class SignedOut extends SessionState {
  const SignedOut();
}

/// Browsing without an account: read-only access to public content (tournaments, rankings,
/// news, player profiles). Every action that needs an account asks the guest to sign up.
class GuestSession extends SessionState {
  const GuestSession();
}

class SignedIn extends SessionState {
  const SignedIn(this.me);
  final Me me;
}

/// Single source of truth for "who is signed in". The router redirects from this state.
class SessionController extends Notifier<SessionState> {
  @override
  SessionState build() {
    // Sign out locally when the API rejects our token (expired, revoked elsewhere, suspended).
    ref.listen(unauthorizedSignalProvider, (_, _) => _signOutLocally());
    Future.microtask(_restore);
    return const SessionRestoring();
  }

  AuthRepository get _repo => ref.read(authRepositoryProvider);

  Future<void> _restore() async {
    try {
      final me = await _repo.restore();
      _setSignedIn(me);
    } on ApiException catch (e) {
      if (e.isOffline) {
        // Keep the token; show the signed-out entry point until connectivity returns.
        state = const SignedOut();
      } else {
        await _repo.clearLocalSession();
        state = const SignedOut();
      }
    }
  }

  Future<void> retryRestore() async {
    state = const SessionRestoring();
    await _restore();
  }

  Future<void> login(String login, String password) async {
    _setSignedIn(await _repo.login(login: login, password: password));
  }

  Future<void> register({required String username, required String email, required String password, String? displayName, required String locale}) async {
    _setSignedIn(await _repo.register(username: username, email: email, password: password, displayName: displayName, locale: locale));
  }

  Future<void> logout({bool everywhere = false}) async {
    try {
      everywhere ? await _repo.logoutAll() : await _repo.logout();
    } on ApiException {
      // Token is cleared locally regardless.
    }
    state = const SignedOut();
  }

  /// Look around without an account. Nothing is stored on the server.
  void continueAsGuest() {
    if (state is SignedIn) return;
    state = const GuestSession();
  }

  /// Leave guest mode and return to the welcome screen.
  void exitGuest() {
    if (state is GuestSession) state = const SignedOut();
  }

  /// Replace the cached profile after an edit.
  void updateMe(Me me) => _setSignedIn(me);

  Future<void> refreshMe() async {
    final me = await _repo.restore();
    _setSignedIn(me);
  }

  void _setSignedIn(Me? me) {
    if (me == null) {
      state = const SignedOut();
      return;
    }
    ref.read(localeProvider.notifier).set(Locale(me.locale));
    state = SignedIn(me);
  }

  Future<void> _signOutLocally() async {
    if (state is! SignedIn) return;
    await _repo.clearLocalSession();
    state = const SignedOut();
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);

/// True while browsing as a guest.
final isGuestProvider = Provider<bool>((ref) => ref.watch(sessionProvider) is GuestSession);

/// Convenience: the signed-in user, or null.
final meProvider = Provider<Me?>((ref) {
  final s = ref.watch(sessionProvider);
  return s is SignedIn ? s.me : null;
});
