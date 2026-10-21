import 'package:flutter/material.dart' hide Badge;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:rivalo/core/design/design_system.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/features/engagement/data/engagement.dart';
import 'package:rivalo/features/engagement/screens/notifications_screen.dart';
import 'package:rivalo/features/engagement/widgets/engagement_widgets.dart';

class _FakeRepo extends Fake implements EngagementRepository {
  final List<int> read = [];
  bool allRead = false;

  @override
  Future<void> markRead(int id) async => read.add(id);

  @override
  Future<void> markAllRead() async => allRead = true;

  @override
  Future<int> unreadCount() async => 0;
}

InboxMessage _msg(int id, String type, String title, {bool read = false, String? kind, String? ref, String? body}) => InboxMessage.fromJson({
      'id': id,
      'type': type,
      'title': title,
      'body': body,
      'link': kind == null ? null : {'kind': kind, 'ref': ref},
      'read': read,
      'created_at': DateTime.now().subtract(Duration(hours: id)).toIso8601String(),
    });

Widget _app(Widget home, List overrides, {List<GoRoute> extra = const []}) => ProviderScope(
      overrides: [...overrides.cast()],
      child: MaterialApp.router(
        theme: RivaloTheme.dark(),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => home), ...extra]),
      ),
    );

void main() {
  testWidgets('inbox lists messages, marks them read on open and follows their link', (tester) async {
    final repo = _FakeRepo();
    await tester.pumpWidget(_app(const NotificationsScreen(), [
      engagementRepositoryProvider.overrideWithValue(repo),
      notificationsProvider.overrideWith((ref) async => InboxPage(unread: 2, messages: [
            _msg(1, 'result_approved', 'Result confirmed', kind: 'match', ref: '7', body: 'Cup: A 2 – 0 B'),
            _msg(2, 'achievement_unlocked', 'New badge: First win', kind: 'achievements'),
            _msg(3, 'announcement', '[SAMPLE] Welcome', read: true, body: 'Hello players'),
          ])),
    ], extra: [
      GoRoute(path: '/match/:id', builder: (_, s) => Scaffold(body: Text('match ${s.pathParameters['id']}'))),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('Notifications'), findsOneWidget);
    expect(find.text('Mark all read'), findsOneWidget);
    expect(find.text('Cup: A 2 – 0 B'), findsOneWidget);
    expect(find.bySemanticsLabel(RegExp(r'^Unread\. Result confirmed')), findsOneWidget);

    await tester.tap(find.text('Result confirmed'));
    await tester.pumpAndSettle();
    expect(repo.read, [1]);
    expect(find.text('match 7'), findsOneWidget);
  });

  testWidgets('announcements without a link open in full, and mark all read works', (tester) async {
    final repo = _FakeRepo();
    await tester.pumpWidget(_app(const NotificationsScreen(), [
      engagementRepositoryProvider.overrideWithValue(repo),
      notificationsProvider.overrideWith((ref) async => InboxPage(unread: 1, messages: [_msg(1, 'announcement', 'Pilot weekend', body: 'Friday at the hall.')])),
    ]));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Pilot weekend'));
    await tester.pumpAndSettle();
    expect(find.text('Friday at the hall.'), findsNWidgets(2)); // row preview + full sheet
    Navigator.of(tester.element(find.text('Pilot weekend').last)).pop();
    await tester.pumpAndSettle();
    expect(find.text('Mark all read'), findsNothing, reason: 'Opening the only unread message cleared the unread state.');
  });

  testWidgets('empty inbox explains what will arrive', (tester) async {
    await tester.pumpWidget(_app(const NotificationsScreen(), [
      engagementRepositoryProvider.overrideWithValue(_FakeRepo()),
      notificationsProvider.overrideWith((ref) async => const InboxPage(unread: 0, messages: [])),
    ]));
    await tester.pumpAndSettle();
    expect(find.text("You're all caught up"), findsOneWidget);
  });

  testWidgets('bell shows the unread count to sight and screen readers', (tester) async {
    await tester.pumpWidget(_app(const Scaffold(body: Center(child: InboxBellButton())), [
      unreadNotificationsProvider.overrideWith((ref) async => 3),
    ]));
    await tester.pumpAndSettle();
    expect(find.text('3'), findsOneWidget);
    expect(find.bySemanticsLabel('Notifications, 3 unread'), findsOneWidget);
  });

  testWidgets('badges show earned ones in gold and locked ones with progress', (tester) async {
    final badges = [
      PlayerBadge.fromJson({'key': 'first_win', 'name': 'First win', 'description': 'Won a match.', 'icon': 'checkmark_seal', 'earned': true, 'awarded_at': '2026-10-18T10:00:00Z'}),
      PlayerBadge.fromJson({'key': 'wins_10', 'name': '10 wins', 'description': 'Won 10 matches.', 'icon': 'flame', 'earned': false, 'progress': {'current': 3, 'target': 10}}),
    ];
    await tester.pumpWidget(_app(Scaffold(body: SingleChildScrollView(child: BadgeGrid(badges: badges))), const []));
    await tester.pumpAndSettle();

    expect(find.text('Earned'), findsOneWidget);
    expect(find.text('3/10'), findsOneWidget);
    expect(find.bySemanticsLabel('10 wins. 3/10'), findsOneWidget);

    await tester.tap(find.text('10 wins'));
    await tester.pumpAndSettle();
    expect(find.text('Won 10 matches.'), findsOneWidget);
    expect(find.byType(LinearProgressIndicator), findsOneWidget);
  });
}
