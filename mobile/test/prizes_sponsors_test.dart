import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:rivalo/core/design/design_system.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/features/auth/state/session_controller.dart';
import 'package:rivalo/features/tournaments/data/tournament_models.dart';
import 'package:rivalo/features/tournaments/data/tournaments_repository.dart';
import 'package:rivalo/features/tournaments/screens/tournament_detail_screen.dart';

class _Guest extends SessionController {
  @override
  SessionState build() => const GuestSession();
}

Tournament _tournament() => Tournament.fromJson({
      'id': 3,
      'slug': 'sample-padel-doubles-open',
      'name': '[SAMPLE] Padel Doubles Open',
      'status': 'registration_open',
      'sport': {'id': 2, 'name': 'Padel', 'icon': 'padel'},
      'discipline': {'id': 5, 'name': 'Padel doubles'},
      'registration_type': 'pair',
      'format': 'single_elimination',
      'registration_is_open': true,
      'starts_at': '2026-11-01T17:00:00Z',
      'capacity': {'max_entrants': 8, 'reserved': 1, 'places_left': 7},
      'entry_fee': {'formatted': 'Free', 'is_paid': false},
      'is_sample_data': true,
      'prizes': [
        {'place': 1, 'title': 'Champions', 'label': 'guaranteed', 'amount_formatted': '100,000 IQD', 'in_kind': 'Trophy'},
        {'place': 2, 'label': 'estimated', 'amount_formatted': null},
        {'place': 3, 'label': 'finalized', 'amount_formatted': null, 'in_kind': 'Medals'},
      ],
      'sponsors': [
        {'name': '[SAMPLE] Sponsor A', 'logo_url': null, 'is_sample_data': true},
      ],
    });

void main() {
  testWidgets('tournament page shows prize tiers with honest labels and consenting sponsors', (tester) async {
    tester.view.physicalSize = const Size(1170, 4000);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(ProviderScope(
      overrides: [
        sessionProvider.overrideWith(_Guest.new),
        tournamentDetailProvider.overrideWith((ref, slug) async => _tournament()),
      ],
      child: MaterialApp.router(
        theme: RivaloTheme.dark(),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => const TournamentDetailScreen(slug: 'sample-padel-doubles-open'))]),
      ),
    ));
    await tester.pumpAndSettle();

    final prizes = find.text('Prizes');
    await tester.scrollUntilVisible(prizes, 300, scrollable: find.byType(Scrollable).first);
    expect(find.text('100,000 IQD'), findsOneWidget);
    expect(find.text('Guaranteed'), findsOneWidget);
    expect(find.text('Estimated'), findsOneWidget);
    expect(find.text('Finalized'), findsOneWidget);
    // An undecided amount is announced as such, never invented.
    expect(find.text('To be announced'), findsOneWidget);
    expect(find.text('Medals'), findsOneWidget);
    expect(find.text('Place 2'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('[SAMPLE] Sponsor A'), 300, scrollable: find.byType(Scrollable).first);
    expect(find.text('Sponsors'), findsOneWidget);
  });

  test('prize and sponsor JSON never needs sponsorship amounts', () {
    final t = _tournament();
    expect(t.prizes.length, 3);
    expect(t.prizes[1].amountFormatted, isNull);
    expect(t.sponsors.single.isSampleData, isTrue);
  });
}
