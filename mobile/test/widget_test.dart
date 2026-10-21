import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';
import 'package:rivalo/app/app.dart';
import 'package:rivalo/core/design/design_system.dart';
import 'package:rivalo/core/network/api_exception.dart';
import 'package:rivalo/core/providers.dart';
import 'package:rivalo/features/auth/data/me.dart';
import 'package:rivalo/features/auth/state/session_controller.dart';
import 'package:rivalo/features/catalog/data/catalog.dart';
import 'package:rivalo/features/news/data/news.dart';
import 'package:rivalo/features/teams/data/teams.dart';
import 'package:rivalo/features/tournaments/data/tournament_models.dart';
import 'package:rivalo/features/tournaments/data/tournaments_repository.dart';

class _FakeSession extends SessionController {
  _FakeSession(this.initial);

  final SessionState initial;

  @override
  SessionState build() => initial;
}

const _me = Me(
  id: 1,
  username: 'sample_player01',
  displayName: '[SAMPLE] Player 1',
  email: 'sample_player01@sample.rivalo.test',
  emailVerified: true,
  locale: 'en',
  status: 'active',
  privacy: {},
  notificationPreferences: {},
  disciplineIds: [1],
  isStaff: false,
);

List<Override> _signedInOverrides({List<Tournament> tournaments = const []}) => [
      sessionProvider.overrideWith(() => _FakeSession(const SignedIn(_me))),
      connectivityProvider.overrideWith((ref) => Stream.value(true)),
      tournamentListProvider.overrideWith((ref, query) async => tournaments),
      myRegistrationsProvider.overrideWith((ref) async => const []),
      inboxProvider.overrideWith((ref) async => const Inbox(invitations: [], nominations: [])),
      myTeamsProvider.overrideWith((ref) async => const []),
      sportsProvider.overrideWith((ref) async => const []),
      appConfigProvider.overrideWith((ref) async => const AppConfig(features: {})),
      featuredNewsProvider.overrideWith((ref) async => const []),
      newsListProvider.overrideWith((ref, category) async => const []),
    ];

Tournament _sampleTournament() => Tournament.fromJson({
      'id': 7,
      'slug': 'sample-padel-open',
      'name': '[SAMPLE] Padel Doubles Open',
      'status': 'registration_open',
      'sport': {'id': 2, 'name': 'Padel'},
      'discipline': {'id': 5, 'name': 'Padel doubles'},
      'registration_type': 'pair',
      'format': 'single_elimination',
      'registration_is_open': true,
      'starts_at': '2026-11-01T17:00:00+03:00',
      'capacity': {'min_entrants': 4, 'max_entrants': 8, 'reserved': 3, 'waitlisted': 0, 'places_left': 5},
      'entry_fee': {'amount_minor': 0, 'currency': 'IQD', 'formatted': 'Free', 'is_paid': false},
      'ranking_eligible': false,
      'is_sample_data': true,
    });

void main() {
  group('App shell and routing', () {
    testWidgets('signed-out users land on the welcome screen', (tester) async {
      await tester.pumpWidget(ProviderScope(
        overrides: [sessionProvider.overrideWith(() => _FakeSession(const SignedOut()))],
        child: const RivaloApp(),
      ));
      await tester.pumpAndSettle();

      expect(find.text('Your game. Your rival.'), findsOneWidget);
      expect(find.text('Create account'), findsOneWidget);
      expect(find.bySemanticsLabel(RegExp('RIVALO')), findsWidgets);
    });

    testWidgets('signed-in users see Home with five tabs and News instead of a dead Store tab', (tester) async {
      await tester.pumpWidget(ProviderScope(overrides: _signedInOverrides(), child: const RivaloApp()));
      await tester.pumpAndSettle();

      expect(find.text('Hi, [SAMPLE] Player 1'), findsWidgets);
      for (final tab in ['Home', 'Compete', 'Rankings', 'News', 'Profile']) {
        expect(find.text(tab), findsWidgets);
      }
      expect(find.text('Store'), findsNothing);
    });

    testWidgets('sample tournaments are labeled as sample data', (tester) async {
      await tester.pumpWidget(ProviderScope(overrides: _signedInOverrides(tournaments: [_sampleTournament()]), child: const RivaloApp()));
      await tester.pumpAndSettle();

      expect(find.text('[SAMPLE] Padel Doubles Open'), findsOneWidget);
      expect(find.text('Sample data'), findsOneWidget);
      expect(find.text('5 places left'), findsOneWidget);
    });

    testWidgets('Rankings is an honest placeholder with no fabricated standings', (tester) async {
      await tester.pumpWidget(ProviderScope(overrides: _signedInOverrides(), child: const RivaloApp()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Rankings').last);
      await tester.pumpAndSettle();

      // No points yet: the podium is empty and says so; nothing is invented.
      expect(find.text('?'), findsNWidgets(3));
      expect(find.bySemanticsLabel('Awaiting approved results'), findsOneWidget);
    });
  });

  group('Guest mode', () {
    List<Override> guestOverrides() => [
          sessionProvider.overrideWith(() => _FakeSession(const SignedOut())),
          connectivityProvider.overrideWith((ref) => Stream.value(true)),
          tournamentListProvider.overrideWith((ref, query) async => [_sampleTournament()]),
          tournamentDetailProvider.overrideWith((ref, slug) async => _sampleTournament()),
          sportsProvider.overrideWith((ref) async => const []),
          appConfigProvider.overrideWith((ref) async => const AppConfig(features: {})),
          featuredNewsProvider.overrideWith((ref) async => const []),
          newsListProvider.overrideWith((ref, category) async => const []),
          // Guests must never trigger authenticated calls.
          myRegistrationsProvider.overrideWith((ref) async => throw StateError('guest called /me/registrations')),
          inboxProvider.overrideWith((ref) async => throw StateError('guest called /me/invitations')),
          myTeamsProvider.overrideWith((ref) async => throw StateError('guest called /me/teams')),
          myEntryProvider.overrideWith((ref, slug) async => throw StateError('guest called my-entry')),
        ];

    Future<void> enterAsGuest(WidgetTester tester) async {
      await tester.pumpWidget(ProviderScope(overrides: guestOverrides(), child: const RivaloApp()));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Browse as guest'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Browse as guest'));
      await tester.pumpAndSettle();
    }

    testWidgets('guests can browse Home and Rankings without an account', (tester) async {
      await enterAsGuest(tester);

      expect(find.text('Welcome to RIVALO'), findsWidgets);
      expect(find.text("You're browsing as a guest"), findsOneWidget);
      expect(find.text('[SAMPLE] Padel Doubles Open'), findsOneWidget);

      await tester.tap(find.text('Rankings').last);
      await tester.pumpAndSettle();
      expect(find.bySemanticsLabel('Awaiting approved results'), findsOneWidget);
    });

    testWidgets('registering as a guest asks to create an account', (tester) async {
      await enterAsGuest(tester);

      // Home now leads with news and sports; the tournament rail sits further down.
      await tester.ensureVisible(find.text('[SAMPLE] Padel Doubles Open'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('[SAMPLE] Padel Doubles Open'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Create an account to register'));
      await tester.pumpAndSettle();

      expect(find.text('Create a free account'), findsOneWidget);
      expect(find.text('You need an account to register for tournaments.'), findsOneWidget);
      expect(find.text('Not now'), findsOneWidget);
    });

    testWidgets('guest profile tab offers sign-up and a way back', (tester) async {
      await enterAsGuest(tester);

      await tester.tap(find.text('Profile').last);
      await tester.pumpAndSettle();
      expect(find.text('Register for tournaments'), findsOneWidget);

      await tester.scrollUntilVisible(find.text('Back to welcome screen'), 200);
      await tester.tap(find.text('Back to welcome screen'));
      await tester.pumpAndSettle();
      expect(find.text('Your game. Your rival.'), findsOneWidget);
    });
  });

  group('Design system', () {
    Widget wrap(Widget child) => MaterialApp(theme: RivaloTheme.dark(), home: Scaffold(body: Center(child: child)));

    testWidgets('status pills always show a text label', (tester) async {
      await tester.pumpWidget(wrap(const RStatusPill(label: 'Reserved', tone: RTone.success)));
      expect(find.text('Reserved'), findsOneWidget);
      expect(find.byType(Icon), findsOneWidget);
    });

    testWidgets('loading buttons ignore taps', (tester) async {
      var taps = 0;
      await tester.pumpWidget(wrap(RButton(label: 'Register', loading: true, onPressed: () => taps++)));
      await tester.tap(find.byType(RButton));
      expect(taps, 0);
    });

    testWidgets('buttons meet the 44pt minimum touch target', (tester) async {
      await tester.pumpWidget(wrap(RIconButton(icon: Icons.add, semanticLabel: 'Add', onPressed: () {})));
      final size = tester.getSize(find.byType(RPressable));
      expect(size.width, greaterThanOrEqualTo(44));
      expect(size.height, greaterThanOrEqualTo(44));
    });
  });

  group('Models and errors', () {
    test('tournament JSON maps capacity and in-person fee', () {
      final t = Tournament.fromJson({
        'slug': 'x',
        'capacity': {'max_entrants': 8, 'reserved': 8, 'places_left': 0, 'waitlisted': 2},
        'entry_fee': {'amount_minor': 25000, 'currency': 'IQD', 'formatted': '25,000 IQD', 'is_paid': true},
      });
      expect(t.isFull, isTrue);
      expect(t.entryFeeMinor, 25000);
      expect(t.isPaid, isTrue);
    });

    test('validation errors are exposed per field', () {
      final e = ApiException.fromDio(DioException(
        requestOptions: RequestOptions(path: '/auth/register'),
        response: Response(
          requestOptions: RequestOptions(path: '/auth/register'),
          statusCode: 422,
          data: {
            'message': 'The username has already been taken.',
            'errors': {
              'username': ['The username has already been taken.'],
            },
          },
        ),
      ));
      expect(e.isValidation, isTrue);
      expect(e.fieldError('username'), 'The username has already been taken.');
    });

    test('connection failures are reported as offline', () {
      final e = ApiException.fromDio(DioException(requestOptions: RequestOptions(path: '/me'), type: DioExceptionType.connectionError));
      expect(e.isOffline, isTrue);
    });
  });
}
