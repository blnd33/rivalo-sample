import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rivalo/app/app.dart';
import 'package:rivalo/core/l10n/fallback_localizations.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/core/providers.dart';
import 'package:rivalo/core/ui/ui_helpers.dart';
import 'package:rivalo/features/auth/state/session_controller.dart';
import 'package:rivalo/features/catalog/data/catalog.dart';
import 'package:rivalo/features/news/data/news.dart';
import 'package:rivalo/features/tournaments/data/tournament_models.dart';
import 'package:rivalo/features/tournaments/data/tournaments_repository.dart';

class _GuestSession extends SessionController {
  @override
  SessionState build() => const GuestSession();
}

class _FixedLocale extends LocaleController {
  _FixedLocale(this.code);

  final String code;

  @override
  Locale? build() => Locale(code);
}

Tournament _tournament() => Tournament.fromJson({
      'slug': 'sample-cup',
      'name': '[SAMPLE] Cup',
      'status': 'registration_open',
      'sport': {'id': 1, 'name': 'Padel'},
      'discipline': {'id': 2, 'name': 'Padel doubles'},
      'registration_type': 'pair',
      'starts_at': '2026-11-01T17:00:00Z',
      'capacity': {'max_entrants': 8, 'reserved': 1, 'places_left': 7},
      'entry_fee': {'formatted': 'Free', 'is_paid': false},
      'is_sample_data': true,
    });

Widget _app(String locale) => ProviderScope(
      overrides: [
        sessionProvider.overrideWith(_GuestSession.new),
        localeProvider.overrideWith(() => _FixedLocale(locale)),
        connectivityProvider.overrideWith((ref) => Stream.value(true)),
        tournamentListProvider.overrideWith((ref, query) async => [_tournament()]),
        sportsProvider.overrideWith((ref) async => const []),
        appConfigProvider.overrideWith((ref) async => const AppConfig(features: {})),
        featuredNewsProvider.overrideWith((ref) async => const []),
        newsListProvider.overrideWith((ref, category) async => const []),
      ],
      child: const RivaloApp(),
    );

void main() {
  test('app supports English, Arabic and Kurdish (Kurmanji)', () {
    final codes = AppLocalizations.supportedLocales.map((l) => l.languageCode).toSet();
    expect(codes, {'en', 'ar', 'kmr'});
  });

  test('a phone set to Kurdish gets Kurmanji; unsupported languages get English', () {
    const supported = AppLocalizations.supportedLocales;
    expect(resolveAppLocale(const Locale('ku'), supported).languageCode, 'kmr');
    expect(resolveAppLocale(const Locale('ku', 'IQ'), supported).languageCode, 'kmr');
    expect(resolveAppLocale(const Locale('ar', 'IQ'), supported).languageCode, 'ar');
    expect(resolveAppLocale(const Locale('fr'), supported).languageCode, 'en');
  });

  testWidgets('Kurdish (Kurmanji) renders left-to-right with Kurdish labels and numeric dates', (tester) async {
    await tester.pumpWidget(_app('kmr'));
    await tester.pumpAndSettle();

    expect(find.text('Bi xêr hatî RIVALO'), findsWidgets);
    expect(find.text('Destpêk'), findsOneWidget); // Home tab
    expect(find.text('Rêzbendî'), findsOneWidget); // Rankings tab
    expect(find.text('Tu wek mêvan digerî'), findsOneWidget);

    final context = tester.element(find.text('Destpêk'));
    expect(Directionality.of(context), TextDirection.ltr);
    // Material widgets still work (English fallback for system strings).
    expect(MaterialLocalizations.of(context), isNotNull);
    expect(formatDateTime(context, DateTime(2026, 11, 1, 20)), '01.11.2026 · 20:00');
  });

  testWidgets('Arabic renders right-to-left', (tester) async {
    await tester.pumpWidget(_app('ar'));
    await tester.pumpAndSettle();

    expect(find.text('الرئيسية'), findsOneWidget);
    final context = tester.element(find.text('الرئيسية'));
    expect(Directionality.of(context), TextDirection.rtl);
  });
}
