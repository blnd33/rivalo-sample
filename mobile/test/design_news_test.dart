import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:rivalo/core/design/design_system.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/features/catalog/data/catalog.dart';
import 'package:rivalo/features/competition/data/competition.dart';
import 'package:rivalo/features/news/data/news.dart';
import 'package:rivalo/features/news/news_screen.dart';
import 'package:rivalo/features/news/screens/news_article_screen.dart';
import 'package:rivalo/features/rankings/rankings_screen.dart';
import 'package:rivalo/features/tournaments/data/tournament_models.dart';
import 'package:rivalo/features/tournaments/widgets/tournament_card.dart';

NewsArticle _article({int id = 1, String slug = 'welcome', bool featured = true, String? tournament}) => NewsArticle.fromJson({
      'id': id,
      'slug': slug,
      'title': '[SAMPLE] Welcome $id',
      'summary': 'Short summary $id',
      'body': 'First paragraph.\n\nSecond paragraph.',
      'category': 'guide',
      'is_featured': featured,
      'published_at': '2026-10-16T12:00:00Z',
      'reading_minutes': 2,
      'sport': {'id': 2, 'name': 'Padel', 'icon': 'padel'},
      'tournament': tournament == null ? null : {'slug': tournament, 'name': '[SAMPLE] Padel Open'},
      'is_sample_data': true,
    });

/// Screens under test use go_router for navigation, so wrap them in a minimal router.
Widget _app(Widget home, {List overrides = const []}) => ProviderScope(
      overrides: [...overrides.cast()],
      child: MaterialApp.router(
        theme: RivaloTheme.dark(),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => home)]),
      ),
    );

final _cueSport = Sport.fromJson({
  'id': 3,
  'slug': 'cue-sports',
  'name': 'Cue sports',
  'kind': 'physical',
  'icon': 'cue',
  'disciplines': [
    {'id': 9, 'sport_id': 3, 'name': '9-ball'},
  ],
});

void main() {
  testWidgets('news tab shows the featured slider, category filters and the feed', (tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(_app(const NewsScreen(), overrides: [
      featuredNewsProvider.overrideWith((ref) async => [_article(), _article(id: 2, slug: 'two')]),
      newsListProvider.overrideWith((ref, category) async => [_article(featured: false, id: 3, slug: 'three')]),
    ]));
    await tester.pumpAndSettle();

    expect(find.byType(RCarousel), findsOneWidget);
    expect(find.text('[SAMPLE] Welcome 1'), findsOneWidget);
    expect(find.bySemanticsLabel(RegExp(r'Featured, 1 / 2')), findsOneWidget);
    expect(find.byType(RPageDots), findsOneWidget);
    expect(find.text('Announcement'), findsOneWidget); // filter chip
    expect(find.text('[SAMPLE] Welcome 3'), findsOneWidget); // feed lead story
    expect(find.text('Sample data'), findsWidgets);

    // Swiping moves to the next slide.
    await tester.drag(find.byType(PageView), const Offset(-300, 0));
    await tester.pumpAndSettle();
    expect(find.bySemanticsLabel(RegExp(r'Featured, 2 / 2')), findsOneWidget);
  });

  testWidgets('empty news feed explains what will appear', (tester) async {
    await tester.pumpWidget(_app(const NewsScreen(), overrides: [
      featuredNewsProvider.overrideWith((ref) async => const []),
      newsListProvider.overrideWith((ref, category) async => const []),
    ]));
    await tester.pumpAndSettle();

    expect(find.byType(RCarousel), findsNothing);
    expect(find.text('No news yet'), findsOneWidget);
  });

  testWidgets('article shows paragraphs, reading time and the linked tournament', (tester) async {
    await tester.pumpWidget(_app(const NewsArticleScreen(slug: 'welcome'), overrides: [
      newsArticleProvider.overrideWith((ref, slug) async => _article(tournament: 'sample-padel-open')),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('[SAMPLE] Welcome 1'), findsWidgets);
    expect(find.text('First paragraph.'), findsOneWidget);
    expect(find.text('Second paragraph.'), findsOneWidget);
    expect(find.textContaining('2 min read'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('View tournament'), 200, scrollable: find.byType(Scrollable).first);
    expect(find.text('[SAMPLE] Padel Open'), findsOneWidget);
  });

  testWidgets('tournament card shows sport artwork, a capacity bar and a countdown', (tester) async {
    final t = Tournament.fromJson({
      'id': 9,
      'slug': 'cup',
      'name': '[SAMPLE] Cup',
      'status': 'registration_open',
      'sport': {'id': 1, 'name': 'Football', 'icon': 'football'},
      'discipline': {'id': 1, 'name': '7v7'},
      'registration_type': 'team',
      'registration_is_open': true,
      'starts_at': DateTime.now().add(const Duration(days: 10)).toIso8601String(),
      'capacity': {'max_entrants': 8, 'reserved': 6, 'places_left': 2},
      'entry_fee': {'formatted': 'Free', 'is_paid': false},
      'is_sample_data': true,
    });
    await tester.pumpWidget(_app(SingleChildScrollView(child: TournamentCard(tournament: t))));
    await tester.pumpAndSettle();

    expect(find.byType(RSportArt), findsOneWidget);
    expect(find.text('6 of 8 places taken'), findsOneWidget);
    expect(find.text('2 places left'), findsOneWidget);
    expect(find.textContaining('Starts in'), findsOneWidget);
    expect(find.bySemanticsLabel(RegExp(r'2 places left')), findsOneWidget);
  });

  testWidgets('rankings podium has empty places and no invented names or points', (tester) async {
    await tester.pumpWidget(_app(const RankingsScreen(), overrides: [
      sportsProvider.overrideWith((ref) async => [_cueSport]),
      seasonsProvider.overrideWith((ref) async => const []),
      leaderboardProvider.overrideWith((ref, q) async => const Leaderboard(rows: [], hasPublishedRules: false)),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('?'), findsNWidgets(3));
    expect(find.bySemanticsLabel('Awaiting approved results'), findsOneWidget);
    expect(find.text('No points yet'), findsOneWidget);
    expect(find.textContaining('No points have been awarded'), findsOneWidget);
    expect(find.text('How rankings work'), findsOneWidget);
  });

  testWidgets('rankings show real leaders on the podium and the rest in a list', (tester) async {
    tester.view.physicalSize = const Size(1170, 3600);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    LeaderboardRow row(int rank, String name, int points, {bool me = false}) => LeaderboardRow(
          rank: rank, username: name.toLowerCase(), name: name, points: points, wins: points ~/ 3, tournaments: 1, isMe: me);

    await tester.pumpWidget(_app(const RankingsScreen(), overrides: [
      sportsProvider.overrideWith((ref) async => [_cueSport]),
      seasonsProvider.overrideWith((ref) async => const [Season(id: 1, name: 'Season 1', status: 'active')]),
      leaderboardProvider.overrideWith((ref, q) async => Leaderboard(
            rows: [row(1, 'Ana', 30), row(2, 'Ben', 21), row(3, 'Cy', 12), row(4, 'Dee', 9, me: true)],
            hasPublishedRules: true,
          )),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('?'), findsNothing);
    expect(find.text('Ana'), findsOneWidget);
    expect(find.text('30 pts'), findsOneWidget);
    expect(find.text('Dee · You'), findsOneWidget);
    expect(find.textContaining('Season 1'), findsOneWidget);
  });

  testWidgets('reduce motion turns entrance animations off', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: MediaQuery(
        data: const MediaQueryData(disableAnimations: true),
        child: const RFadeIn(child: Text('Hello')),
      ),
    ));
    await tester.pump();
    final opacity = tester.widget<Opacity>(find.ancestor(of: find.text('Hello'), matching: find.byType(Opacity)).first);
    expect(opacity.opacity, 1);
  });
}
