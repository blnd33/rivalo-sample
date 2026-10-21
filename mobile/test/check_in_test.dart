import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:rivalo/core/design/theme.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/core/providers.dart';
import 'package:rivalo/features/auth/data/me.dart';
import 'package:rivalo/features/auth/state/session_controller.dart';
import 'package:rivalo/features/tournaments/data/check_in_pass.dart';
import 'package:rivalo/features/tournaments/data/tournament_models.dart';
import 'package:rivalo/features/tournaments/data/tournaments_repository.dart';
import 'package:rivalo/features/tournaments/screens/check_in_pass_screen.dart';
import 'package:rivalo/features/tournaments/screens/tournament_detail_screen.dart';

CheckInPass _pass({bool fromCache = false, String status = 'not_checked_in'}) => CheckInPass.fromJson({
      'code': '42-AbCdEfGhIjKlMnOp',
      'qr_payload': 'http://localhost:8000/admin/check-in?code=42-AbCdEfGhIjKlMnOp',
      'member': {'username': 'sample_player01', 'display_name': '[SAMPLE] Player 1'},
      'entry': {'id': 7, 'display_name': '[SAMPLE] Player 1'},
      'tournament': {'name': '[SAMPLE] 9-Ball Night', 'starts_at': '2026-11-01T17:00:00Z', 'venue_name': '[SAMPLE] Venue'},
      'check_in_status': status,
      'payment_status': 'paid',
    }, fromCache: fromCache);

class _SignedIn extends SessionController {
  @override
  SessionState build() => const SignedIn(Me(
        id: 1,
        username: 'sample_player01',
        email: 'p@sample.rivalo.test',
        emailVerified: true,
        locale: 'en',
        status: 'active',
        privacy: {},
        notificationPreferences: {},
        disciplineIds: [],
        isStaff: false,
      ));
}

Widget _wrap(Widget child, List overrides) => ProviderScope(
      overrides: [...overrides.cast()],
      child: MaterialApp(
        theme: RivaloTheme.dark(),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: child,
      ),
    );

void main() {
  testWidgets('check-in pass shows a scannable QR code with an accessible label and typed fallback', (tester) async {
    await tester.pumpWidget(_wrap(const CheckInPassScreen(entryId: 7), [
      checkInPassProvider.overrideWith((ref, id) async => _pass()),
    ]));
    await tester.pumpAndSettle();

    expect(find.byType(QrImageView), findsOneWidget);
    expect(_pass().qrPayload, contains('/admin/check-in?code=42-'));
    expect(find.bySemanticsLabel('Check-in QR code for [SAMPLE] Player 1'), findsOneWidget);
    expect(find.text('Code 42-AbCdEfGhIjKlMnOp'), findsOneWidget);
    expect(find.textContaining('Staff confirm your identity'), findsOneWidget);
  });

  testWidgets('offline copy is labelled', (tester) async {
    await tester.pumpWidget(_wrap(const CheckInPassScreen(entryId: 7), [
      checkInPassProvider.overrideWith((ref, id) async => _pass(fromCache: true)),
    ]));
    await tester.pumpAndSettle();

    expect(find.textContaining('copy saved on this device'), findsOneWidget);
  });

  testWidgets('registration card offers the code before check-in and lists receipts', (tester) async {
    final tournament = Tournament.fromJson({
      'slug': 'sample-cup',
      'name': '[SAMPLE] Cup',
      'status': 'registration_closed',
      'sport': {'id': 1, 'name': 'Cue sports'},
      'discipline': {'id': 2, 'name': '9-ball'},
      'registration_type': 'individual',
      'capacity': {'max_entrants': 8, 'reserved': 1, 'places_left': 7},
      'entry_fee': {'amount_minor': 25000, 'currency': 'IQD', 'formatted': '25,000 IQD', 'is_paid': true},
    });
    final entry = TournamentEntry.fromJson({
      'id': 7,
      'entry_type': 'individual',
      'display_name': '[SAMPLE] Player 1',
      'registration_status': 'reserved',
      'payment_status': 'paid',
      'check_in_status': 'not_checked_in',
      'eligibility_status': 'pending',
      'can_manage': true,
      'members': [],
      'payments': [
        {'kind': 'payment', 'formatted': '25,000 IQD', 'reference': 'RV-3-0001', 'recorded_at': '2026-10-17T10:00:00Z'},
      ],
    });

    await tester.pumpWidget(_wrap(const TournamentDetailScreen(slug: 'sample-cup'), [
      sessionProvider.overrideWith(_SignedIn.new),
      connectivityProvider.overrideWith((ref) => Stream.value(true)),
      tournamentDetailProvider.overrideWith((ref, slug) async => tournament),
      myEntryProvider.overrideWith((ref, slug) async => entry),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('Show check-in code'), findsOneWidget);
    expect(find.text('Paid 25,000 IQD · Receipt RV-3-0001'), findsOneWidget);
  });
}
