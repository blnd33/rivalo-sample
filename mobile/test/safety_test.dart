import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:rivalo/core/design/design_system.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/core/network/api_exception.dart';
import 'package:rivalo/features/safety/data/safety.dart';
import 'package:rivalo/features/safety/screens/blocked_players_screen.dart';
import 'package:rivalo/features/safety/screens/policy_screen.dart';
import 'package:rivalo/features/safety/widgets/report_sheet.dart';
import 'package:rivalo/features/teams/data/teams.dart';

class _FakeSafety extends Fake implements SafetyRepository {
  final List<Map<String, Object?>> reports = [];
  final List<String> unblocked = [];

  @override
  Future<void> report(ReportTarget target, String reason, {String? details, String? appVersion, String? platform}) async =>
      reports.add({'type': target.type, 'subject': target.subject, 'reason': reason, 'details': details, 'app_version': appVersion});

  @override
  Future<void> unblock(String username) async => unblocked.add(username);
}

Widget _app(Widget home, List overrides, {String locale = 'en'}) => ProviderScope(
      overrides: [...overrides.cast()],
      child: MaterialApp.router(
        theme: RivaloTheme.dark(),
        locale: Locale(locale),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => home)]),
      ),
    );

class _Opener extends StatelessWidget {
  const _Opener(this.target);

  final ReportTarget target;

  @override
  Widget build(BuildContext context) => Scaffold(
        body: Center(child: TextButton(onPressed: () => showReportSheet(context, target), child: const Text('open'))),
      );
}

PolicyPage _page({String legal = 'pending', bool fallback = false}) => PolicyPage.fromJson({
      'slug': 'terms',
      'title': 'Terms of use',
      'body': 'DRAFT intro paragraph.\n\nYour account\nKeep your password private.\n\nWhat we store\n• Account: username and email.\n• Profile photo',
      'version': 3,
      'published_at': '2026-10-18T10:00:00Z',
      'translation_pending': fallback,
      'legal_review': {'required': true, 'status': legal},
    });

void main() {
  testWidgets('reporting a player sends the chosen reason and thanks the reporter', (tester) async {
    final repo = _FakeSafety();
    await tester.pumpWidget(_app(const _Opener(ReportPlayer(username: 'rude_one', name: 'Rude One')), [safetyRepositoryProvider.overrideWithValue(repo)]));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Report Rude One'), findsOneWidget);
    expect(find.textContaining('We never tell the other player'), findsOneWidget);

    await tester.ensureVisible(find.text('Send report'));
    await tester.tap(find.text('Send report'));
    await tester.pumpAndSettle();
    expect(find.text('Choose what is wrong.'), findsOneWidget, reason: 'A reason is required.');
    expect(repo.reports, isEmpty);

    await tester.tap(find.text('Harassment or threats'));
    await tester.pump();
    await tester.ensureVisible(find.text('Send report'));
    await tester.tap(find.text('Send report'));
    await tester.pumpAndSettle();

    expect(repo.reports.single, containsPair('reason', 'harassment'));
    expect(repo.reports.single, containsPair('subject', 'rude_one'));
    expect(find.text('Thanks for telling us'), findsOneWidget);
  });

  testWidgets('app feedback needs details and carries the app version', (tester) async {
    final repo = _FakeSafety();
    await tester.pumpWidget(_app(const _Opener(ReportApp()), [safetyRepositoryProvider.overrideWithValue(repo)]));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Suggestion'));
    await tester.ensureVisible(find.text('Send'));
    await tester.tap(find.text('Send'));
    await tester.pumpAndSettle();
    expect(find.text('Tell us briefly what happened.'), findsOneWidget);
    expect(repo.reports, isEmpty);

    await tester.enterText(find.byType(TextField), 'Dark bracket lines are hard to see.');
    await tester.ensureVisible(find.text('Send'));
    await tester.tap(find.text('Send'));
    await tester.pumpAndSettle();

    expect(repo.reports.single['type'], 'app');
    expect(repo.reports.single['details'], 'Dark bracket lines are hard to see.');
    expect(repo.reports.single['app_version'], isNotEmpty);
    expect(find.text('Thanks for your feedback'), findsOneWidget);
  });

  testWidgets('policy pages say plainly when they are drafts or not translated', (tester) async {
    await tester.pumpWidget(_app(const PolicyScreen(slug: 'terms'), [policyPageProvider('terms').overrideWith((ref) async => _page(fallback: true))], locale: 'ar'));
    await tester.pumpAndSettle();

    expect(find.text('مسودة: بانتظار المراجعة القانونية'), findsOneWidget);
    expect(find.text('لم تُترجم بعد. يُعرض النص الإنجليزي.'), findsOneWidget);
    expect(find.bySemanticsLabel('Your account'), findsOneWidget, reason: 'Short lines become headings.');
    expect(find.text('Account: username and email.'), findsOneWidget, reason: 'Bullets lose their marker character.');
    final body = tester.widget<Directionality>(find.ancestor(of: find.byType(PolicyBody), matching: find.byType(Directionality)).first);
    expect(body.textDirection, TextDirection.ltr, reason: 'English fallback text keeps left-to-right layout in Arabic.');
  });

  testWidgets('a reviewed policy shows no draft warning', (tester) async {
    await tester.pumpWidget(_app(const PolicyScreen(slug: 'terms'), [policyPageProvider('terms').overrideWith((ref) async => _page(legal: 'reviewed'))]));
    await tester.pumpAndSettle();
    expect(find.text('Draft: pending legal review'), findsNothing);
    expect(find.textContaining('Version 3'), findsOneWidget);
  });

  testWidgets('an unpublished policy explains itself instead of showing an error', (tester) async {
    await tester.pumpWidget(_app(const PolicyScreen(slug: 'privacy'), [
      policyPageProvider('privacy').overrideWith((ref) async => throw ApiException(message: 'Not found', statusCode: 404)),
    ]));
    await tester.pumpAndSettle();
    expect(find.text('Not published yet'), findsOneWidget);
  });

  testWidgets('blocked players can be unblocked', (tester) async {
    final repo = _FakeSafety();
    await tester.pumpWidget(_app(const BlockedPlayersScreen(), [
      safetyRepositoryProvider.overrideWithValue(repo),
      blockedPlayersProvider.overrideWith((ref) async => [UserSummary.fromJson({'id': 4, 'username': 'pest', 'name': 'Pest', 'avatar_url': null})]),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('Pest'), findsOneWidget);
    await tester.tap(find.text('Unblock'));
    await tester.pumpAndSettle();
    expect(repo.unblocked, ['pest']);
  });

  test('every app text exists in Arabic and Kurdish with the same placeholders', () {
    Map<String, dynamic> load(String l) => jsonDecode(File('lib/core/l10n/app_$l.arb').readAsStringSync()) as Map<String, dynamic>;
    final en = load('en');
    // Top-level placeholders only: "{count, plural, =0{None} other{...}}" yields just "count".
    final placeholder = RegExp(r'\{(\w+)\s*[,}]');
    final branch = RegExp(r'(=\d+|zero|one|two|few|many|other)\s*$');
    Set<String> names(String s) => placeholder
        .allMatches(s)
        .where((m) => !branch.hasMatch(s.substring(0, m.start)))
        .map((m) => m.group(1)!)
        .toSet();

    for (final locale in ['ar', 'kmr']) {
      final other = load(locale);
      final missing = [for (final k in en.keys) if (!k.startsWith('@') && !other.containsKey(k)) k];
      expect(missing, isEmpty, reason: '$locale is missing texts (they would show in English).');
      for (final k in en.keys.where((k) => !k.startsWith('@'))) {
        expect(names(other[k] as String), names(en[k] as String), reason: '$locale "$k" placeholders differ from English.');
      }
    }
  });
}
