import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:rivalo/core/design/design_system.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/features/competition/data/competition.dart';
import 'package:rivalo/features/competition/screens/match_screen.dart';
import 'package:rivalo/features/competition/widgets/fixtures_view.dart';

Map<String, dynamic> _match(int id, int round, int position, String a, String? b, {String status = 'ready', int? winner, int? s1, int? s2, String? label, int? mySlot}) => {
      'id': id,
      'round': round,
      'position': position,
      'label': label,
      'status': status,
      'slots': [
        {'slot': 1, 'entry_id': id * 10 + 1, 'name': a, 'is_winner': winner == 1},
        {'slot': 2, 'entry_id': b == null ? null : id * 10 + 2, 'name': b, 'is_winner': winner == 2},
      ],
      'my_slot': mySlot,
      'result': winner == null ? null : {'id': id * 100, 'outcome': 'normal', 'score_line': '$s1 – $s2', 'scores': {'slot1': s1, 'slot2': s2}, 'winner_slot': winner, 'is_draw': false},
      'can_submit': mySlot != null && status == 'ready',
      'can_dispute': false,
    };

Fixtures _knockout() => Fixtures.fromJson({
      'stages': [
        {
          'id': 1,
          'type': 'single_elimination',
          'name': 'Knockout',
          'status': 'published',
          'groups': [],
          'rounds': [
            {
              'round': 1,
              'label': 'Semi-final',
              'matches': [
                _match(1, 1, 1, 'Ana', 'Ben', status: 'completed', winner: 1, s1: 5, s2: 2, label: 'Semi-final'),
                _match(2, 1, 2, 'Cy', 'Dee', mySlot: 2, label: 'Semi-final'),
              ],
            },
            {
              'round': 2,
              'label': 'Final',
              'matches': [
                _match(3, 2, 1, 'Ana', null, status: 'pending', label: 'Final'),
                _match(4, 2, 2, 'Ben', null, status: 'pending', label: 'Third place'),
              ],
            },
          ],
        },
      ],
      'placements_final': false,
      'placements': [],
      'points': {'ranking_eligible': true, 'rule_set': {'name': 'Pilot table', 'version': 1}},
    });

class _FakeRepo extends Fake implements CompetitionRepository {

  Map<String, Object?>? sent;

  @override
  Future<FixtureMatch> submitResult(int matchId, {required String outcome, int? slot1, int? slot2, List<List<int>> sets = const [], int? winnerSlot, String? note}) async {
    sent = {'match': matchId, 'outcome': outcome, 'slot1': slot1, 'slot2': slot2, 'winner': winnerSlot};
    return FixtureMatch.fromJson(_match(matchId, 1, 2, 'Cy', 'Dee', mySlot: 2));
  }
}

Widget _app(Widget home, List overrides) => ProviderScope(
      overrides: [...overrides.cast()],
      child: MaterialApp.router(
        theme: RivaloTheme.dark(),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => home)]),
      ),
    );

void main() {
  testWidgets('knockout bracket shows rounds, scores, winners, my match and the third-place match', (tester) async {
    tester.view.physicalSize = const Size(1170, 3000);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(_app(
      const Scaffold(body: SingleChildScrollView(child: FixturesSection(slug: 'cup'))),
      [fixturesProvider.overrideWith((ref, slug) async => _knockout())],
    ));
    await tester.pumpAndSettle();

    expect(find.byType(BracketView), findsOneWidget);
    expect(find.text('SEMI-FINAL'), findsOneWidget);
    expect(find.text('FINAL'), findsOneWidget);
    expect(find.text('THIRD PLACE'), findsOneWidget);
    expect(find.text('5'), findsOneWidget);
    expect(find.text('To be decided'), findsNWidgets(2));
    expect(find.textContaining('Pilot table'), findsOneWidget);
    // Accessible summary of a finished match.
    expect(find.bySemanticsLabel(RegExp(r'Semi-final, Ana 5, Ben 2, Final')), findsOneWidget);
  });

  testWidgets('a player submits a result from the match screen', (tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final repo = _FakeRepo();

    await tester.pumpWidget(_app(const MatchScreen(matchId: 2), [
      matchProvider.overrideWith((ref, id) async => FixtureMatch.fromJson(_match(2, 1, 2, 'Cy', 'Dee', mySlot: 2))),
      competitionRepositoryProvider.overrideWithValue(repo),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('You'), findsOneWidget);
    await tester.tap(find.text('Submit result'));
    await tester.pumpAndSettle();

    // Dee (second side) wins 2-1 using the steppers.
    final plus = find.bySemanticsLabel('+');
    for (final i in [0, 1, 1]) {
      await tester.tap(plus.at(i));
      await tester.pump(const Duration(milliseconds: 500));
    }
    await tester.tap(find.widgetWithText(RButton, 'Submit result').last);
    await tester.pumpAndSettle();

    expect(repo.sent, {'match': 2, 'outcome': 'normal', 'slot1': 1, 'slot2': 2, 'winner': null});
    expect(find.text('Result sent to the referee.'), findsOneWidget);
  });
}
