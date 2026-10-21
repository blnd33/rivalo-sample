import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/config/env.dart';
import '../features/auth/screens/forgot_password_screen.dart';
import '../features/auth/screens/login_screen.dart';
import '../features/auth/screens/register_screen.dart';
import '../features/auth/screens/splash_screen.dart';
import '../features/auth/screens/verify_email_screen.dart';
import '../features/auth/screens/welcome_screen.dart';
import '../features/auth/state/session_controller.dart';
import '../features/competition/screens/match_screen.dart';
import '../features/competition/screens/points_history_screen.dart';
import '../features/dev/design_gallery_screen.dart';
import '../features/engagement/screens/badges_screen.dart';
import '../features/engagement/screens/notifications_screen.dart';
import '../features/home/home_screen.dart';
import '../features/news/news_screen.dart';
import '../features/news/screens/news_article_screen.dart';
import '../features/onboarding/sport_selection_screen.dart';
import '../features/profile/screens/change_password_screen.dart';
import '../features/profile/screens/delete_account_screen.dart';
import '../features/profile/screens/devices_screen.dart';
import '../features/profile/screens/edit_profile_screen.dart';
import '../features/profile/screens/language_screen.dart';
import '../features/profile/screens/notification_settings_screen.dart';
import '../features/profile/screens/privacy_screen.dart';
import '../features/profile/screens/profile_screen.dart';
import '../features/profile/screens/public_profile_screen.dart';
import '../features/profile/screens/settings_screen.dart';
import '../features/rankings/rankings_screen.dart';
import '../features/safety/screens/blocked_players_screen.dart';
import '../features/safety/screens/policy_screen.dart';
import '../features/catalog/data/catalog.dart';
import '../features/store/screens/bag_screen.dart';
import '../features/store/screens/checkout_screen.dart';
import '../features/store/screens/orders_screen.dart';
import '../features/store/screens/product_screen.dart';
import '../features/store/screens/shop_screen.dart';
import '../features/selling/screens/selling_screen.dart';
import '../features/store/screens/store_screen.dart';
import '../features/teams/screens/create_team_screen.dart';
import '../features/teams/screens/invitations_screen.dart';
import '../features/teams/screens/team_detail_screen.dart';
import '../features/tournaments/screens/check_in_pass_screen.dart';
import '../features/tournaments/screens/compete_screen.dart';
import '../features/tournaments/screens/register_entry_screen.dart';
import '../features/tournaments/screens/tournament_detail_screen.dart';
import 'shell.dart';

/// Route paths in one place, so screens never hand-build URLs.
abstract final class Routes {
  static const splash = '/splash';
  static const welcome = '/welcome';
  static const login = '/login';
  static const register = '/register';
  static const forgotPassword = '/forgot-password';
  static const verifyEmail = '/verify-email';
  static const onboarding = '/onboarding';

  static const home = '/home';
  static const compete = '/compete';
  static const rankings = '/rankings';
  static const news = '/news';
  static const store = '/store';
  static const profile = '/profile';

  static String newsArticle(String slug) => '/news/$slug';
  static String competeSport(int sportId) => '/compete?sport=$sportId';
  static String tournament(String slug) => '/compete/t/$slug';
  static String registerEntry(String slug) => '/compete/t/$slug/register';
  static String team(String slug) => '/compete/teams/$slug';
  static const createTeam = '/compete/teams/new';
  static const invitations = '/compete/invitations';
  static String player(String username) => '/u/$username';
  static String checkInPass(int entryId) => '/check-in/$entryId';
  static String match(int id) => '/match/$id';
  static const inbox = '/inbox';
  static String policy(String slug) => '/legal/$slug';
  static String product(String slug) => '/store/p/$slug';
  static String shop(String slug) => '/store/s/$slug';
  static const selling = '/profile/selling';
  static const sellApply = '/profile/selling/apply';
  static const bag = '/store/bag';
  static const checkout = '/store/checkout';
  static const orders = '/store/orders';
  static String order(String number) => '/store/orders/$number';
  static String orderPlaced(String number) => '/store/orders/$number?placed=1';

  static const editProfile = '/profile/edit';
  static const profileSports = '/profile/sports';
  static const pointsHistory = '/profile/points';
  static const badges = '/profile/badges';
  static const settings = '/profile/settings';
  static const privacy = '/profile/settings/privacy';
  static const notifications = '/profile/settings/notifications';
  static const language = '/profile/settings/language';
  static const devices = '/profile/settings/devices';
  static const password = '/profile/settings/password';
  static const deleteAccount = '/profile/settings/delete';
  static const blocked = '/profile/settings/blocked';
  static const designGallery = '/profile/settings/design';

  static const _signedOutOnly = {welcome, login, register, forgotPassword};

  /// Public, read-only screens a guest may open. Everything else needs an account.
  static bool guestCanOpen(String path) {
    if ((_signedOutOnly.contains(path) && path != welcome) || path == profile || path == rankings || path == news || path == home) return true;
    if (path.startsWith('/u/') || path.startsWith('/news/') || path.startsWith('/match/') || path.startsWith('/legal/')) return true;
    if (path == compete || path == store || path.startsWith('/store/p/') || path.startsWith('/store/s/')) return true;
    if (path.startsWith('/compete/t/')) return !path.endsWith('/register');
    if (path.startsWith('/compete/teams/')) return path != createTeam;
    return false;
  }
}

final _rootKey = GlobalKey<NavigatorState>(debugLabel: 'root');

/// Bridges Riverpod session changes into go_router's refresh mechanism.
class _SessionListenable extends ChangeNotifier {
  _SessionListenable(Ref ref) {
    ref.listen(sessionProvider, (_, _) => notifyListeners());
  }
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _SessionListenable(ref);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    navigatorKey: _rootKey,
    initialLocation: Routes.splash,
    debugLogDiagnostics: kDebugMode,
    refreshListenable: refresh,
    redirect: (context, state) {
      final session = ref.read(sessionProvider);
      final path = state.matchedLocation;

      // The store exists only while the server's store_enabled flag is on.
      if (path.startsWith(Routes.store) && !(ref.read(appConfigProvider).value?.storeEnabled ?? false)) return Routes.news;
      // Sellers' shop pages exist only while the marketplace is switched on too.
      if (path.startsWith('/store/s/') && !(ref.read(appConfigProvider).value?.marketplaceEnabled ?? false)) return Routes.store;

      return switch (session) {
        SessionRestoring() => path == Routes.splash ? null : Routes.splash,
        // Policies are readable before signing up (linked from the registration form).
        SignedOut() => (Routes._signedOutOnly.contains(path) || path.startsWith('/legal/')) ? null : Routes.welcome,
        // Guests browse public screens. Account-only deep links fall back to Home, where every
        // action that needs an account explains how to create one.
        GuestSession() => (path == Routes.splash || path == Routes.welcome || !Routes.guestCanOpen(path)) ? Routes.home : null,
        // A brand-new account lands on the email check; everyone else goes Home.
        SignedIn(:final me) when path == Routes.register => me.emailVerified ? Routes.home : Routes.verifyEmail,
        SignedIn() => (path == Routes.splash || Routes._signedOutOnly.contains(path)) ? Routes.home : null,
      };
    },
    routes: [
      GoRoute(path: Routes.splash, builder: (_, _) => const SplashScreen()),
      GoRoute(path: Routes.welcome, builder: (_, _) => const WelcomeScreen()),
      GoRoute(path: Routes.login, builder: (_, _) => const LoginScreen()),
      GoRoute(path: Routes.register, builder: (_, _) => const RegisterScreen()),
      GoRoute(path: Routes.forgotPassword, builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(path: Routes.verifyEmail, builder: (_, _) => const VerifyEmailScreen()),
      GoRoute(path: Routes.onboarding, builder: (_, _) => const SportSelectionScreen(onboarding: true)),
      GoRoute(
        path: '/check-in/:entryId',
        pageBuilder: (_, s) => MaterialPage(
          fullscreenDialog: true,
          child: CheckInPassScreen(entryId: int.parse(s.pathParameters['entryId']!)),
        ),
      ),
      GoRoute(path: Routes.inbox, parentNavigatorKey: _rootKey, builder: (_, _) => const NotificationsScreen()),
      GoRoute(path: '/legal/:slug', parentNavigatorKey: _rootKey, builder: (_, s) => PolicyScreen(slug: s.pathParameters['slug']!)),
      GoRoute(
        path: '/match/:id',
        parentNavigatorKey: _rootKey,
        builder: (_, s) => MatchScreen(matchId: int.parse(s.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/u/:username',
        parentNavigatorKey: _rootKey,
        builder: (_, s) => PublicProfileScreen(username: s.pathParameters['username']!),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => AppShell(navigationShell: shell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: Routes.home, builder: (_, _) => const HomeScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: Routes.compete,
              builder: (_, s) => CompeteScreen(
                key: ValueKey(s.uri.query),
                initialSegment: s.uri.queryParameters['tab'],
                initialSportId: int.tryParse(s.uri.queryParameters['sport'] ?? ''),
              ),
              routes: [
                GoRoute(
                  path: 't/:slug',
                  builder: (_, s) => TournamentDetailScreen(slug: s.pathParameters['slug']!),
                  routes: [
                    GoRoute(
                      path: 'register',
                      parentNavigatorKey: _rootKey,
                      pageBuilder: (_, s) => MaterialPage(fullscreenDialog: true, child: RegisterEntryScreen(slug: s.pathParameters['slug']!)),
                    ),
                  ],
                ),
                GoRoute(
                  path: 'teams/new',
                  parentNavigatorKey: _rootKey,
                  pageBuilder: (_, s) => MaterialPage(
                    fullscreenDialog: true,
                    child: CreateTeamScreen(initialSportId: int.tryParse(s.uri.queryParameters['sport'] ?? '')),
                  ),
                ),
                GoRoute(path: 'teams/:slug', builder: (_, s) => TeamDetailScreen(slug: s.pathParameters['slug']!)),
                GoRoute(path: 'invitations', builder: (_, _) => const InvitationsScreen()),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: Routes.rankings, builder: (_, _) => const RankingsScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: Routes.news,
              builder: (_, _) => const NewsScreen(),
              routes: [
                GoRoute(path: ':slug', builder: (_, s) => NewsArticleScreen(slug: s.pathParameters['slug']!)),
              ],
            ),
          ]),
          // Store tab (shown instead of News while store_enabled is on). News stays reachable from Home.
          StatefulShellBranch(routes: [
            GoRoute(
              path: Routes.store,
              builder: (_, _) => const StoreScreen(),
              routes: [
                GoRoute(path: 'p/:slug', builder: (_, s) => ProductScreen(slug: s.pathParameters['slug']!)),
                GoRoute(path: 's/:slug', builder: (_, s) => ShopScreen(slug: s.pathParameters['slug']!)),
                GoRoute(path: 'bag', builder: (_, _) => const BagScreen()),
                GoRoute(
                  path: 'checkout',
                  parentNavigatorKey: _rootKey,
                  pageBuilder: (_, _) => const MaterialPage(fullscreenDialog: true, child: CheckoutScreen()),
                ),
                GoRoute(
                  path: 'orders',
                  builder: (_, _) => const OrdersScreen(),
                  routes: [
                    GoRoute(
                      path: ':number',
                      builder: (_, s) => OrderScreen(number: s.pathParameters['number']!, justPlaced: s.uri.queryParameters['placed'] == '1'),
                    ),
                  ],
                ),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: Routes.profile,
              builder: (_, _) => const ProfileScreen(),
              routes: [
                GoRoute(path: 'edit', builder: (_, _) => const EditProfileScreen()),
                GoRoute(path: 'sports', builder: (_, _) => const SportSelectionScreen(onboarding: false)),
                GoRoute(path: 'points', builder: (_, _) => const PointsHistoryScreen()),
                GoRoute(path: 'badges', builder: (_, _) => const BadgesScreen()),
                GoRoute(
                  path: 'selling',
                  builder: (_, _) => const SellingScreen(),
                  routes: [
                    GoRoute(
                      path: 'apply',
                      parentNavigatorKey: _rootKey,
                      pageBuilder: (_, _) => const MaterialPage(fullscreenDialog: true, child: SellApplyScreen()),
                    ),
                  ],
                ),
                GoRoute(
                  path: 'settings',
                  builder: (_, _) => const SettingsScreen(),
                  routes: [
                    GoRoute(path: 'privacy', builder: (_, _) => const PrivacyScreen()),
                    GoRoute(path: 'notifications', builder: (_, _) => const NotificationSettingsScreen()),
                    GoRoute(path: 'language', builder: (_, _) => const LanguageScreen()),
                    GoRoute(path: 'devices', builder: (_, _) => const DevicesScreen()),
                    GoRoute(path: 'password', builder: (_, _) => const ChangePasswordScreen()),
                    GoRoute(path: 'delete', builder: (_, _) => const DeleteAccountScreen()),
                    GoRoute(path: 'blocked', builder: (_, _) => const BlockedPlayersScreen()),
                    if (Env.showDevTools) GoRoute(path: 'design', builder: (_, _) => const DesignGalleryScreen()),
                  ],
                ),
              ],
            ),
          ]),
        ],
      ),
    ],
  );
});
