import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../design/tokens.dart';
import '../design/widgets/status_pill.dart';
import '../l10n/generated/app_localizations.dart';
import '../network/api_exception.dart';

extension L10nX on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
}

/// User-facing message for any error thrown by repositories.
String errorMessage(BuildContext context, Object error) {
  final l10n = context.l10n;
  if (error is ApiException) {
    if (error.isOffline) return l10n.errorOfflineMessage;
    if (error.statusCode == 429 || error.message == 'rate_limited') return l10n.errorRateLimited;
    if (error.isNotFound) return l10n.errorNotFound;
    if (error.message != 'unknown') return error.message; // Server messages are written for people.
  }
  return l10n.errorGenericMessage;
}

bool isOffline(Object error) => error is ApiException && error.isOffline;

void showToast(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

/// Adaptive confirmation (Cupertino alert on iOS, Material dialog on Android).
Future<bool> confirm(
  BuildContext context, {
  required String title,
  String? message,
  required String confirmLabel,
  bool destructive = false,
}) async {
  final result = await showAdaptiveDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog.adaptive(
      title: Text(title),
      content: message == null ? null : Text(message),
      actions: [
        _adaptiveAction(ctx, ctx.l10n.actionCancel, () => Navigator.of(ctx).pop(false)),
        _adaptiveAction(ctx, confirmLabel, () => Navigator.of(ctx).pop(true), destructive: destructive, isDefault: !destructive),
      ],
    ),
  );
  return result ?? false;
}

Widget _adaptiveAction(BuildContext context, String label, VoidCallback onPressed, {bool destructive = false, bool isDefault = false}) {
  final platform = Theme.of(context).platform;
  if (platform == TargetPlatform.iOS || platform == TargetPlatform.macOS) {
    return CupertinoDialogAction(onPressed: onPressed, isDestructiveAction: destructive, isDefaultAction: isDefault, child: Text(label));
  }
  return TextButton(
    onPressed: onPressed,
    child: Text(label, style: TextStyle(color: destructive ? RColors.error : RColors.accent, fontWeight: FontWeight.w600)),
  );
}

// ------------------------------------------------------------------ Formatting

/// `intl` has no date names for some app languages (Kurmanji). Those get a numeric format
/// (17.10.2026 · 20:00) instead of guessed month or weekday names.
String? _intlDateLocale(BuildContext context) {
  final tag = Localizations.localeOf(context).toLanguageTag();
  return DateFormat.localeExists(tag) ? tag : null;
}

String formatDateTime(BuildContext context, DateTime? value) {
  if (value == null) return context.l10n.dateTba;
  final locale = _intlDateLocale(context);
  if (locale == null) return DateFormat('dd.MM.yyyy · HH:mm').format(value);
  return DateFormat.MMMEd(locale).add_Hm().format(value);
}

String formatDate(BuildContext context, DateTime? value) {
  if (value == null) return context.l10n.dateTba;
  final locale = _intlDateLocale(context);
  if (locale == null) return DateFormat('dd.MM.yyyy').format(value);
  return DateFormat.yMMMd(locale).format(value);
}

/// Integer minor units to display text, e.g. (25000, IQD) -> "25,000 IQD". Never uses floats for
/// arithmetic: the division happens only when formatting.
String formatMoney(BuildContext context, int minor, String currency) {
  const exponents = {'IQD': 0, 'USD': 2, 'EUR': 2};
  final exp = exponents[currency.toUpperCase()] ?? 0;
  final tag = Localizations.localeOf(context).toLanguageTag();
  final format = NumberFormat.decimalPatternDigits(locale: NumberFormat.localeExists(tag) ? tag : 'en', decimalDigits: exp);
  final major = exp == 0 ? minor : minor / _pow10(exp);
  return '${format.format(major)} ${currency.toUpperCase()}';
}

int _pow10(int e) => e <= 0 ? 1 : 10 * _pow10(e - 1);

// ------------------------------------------------------------------ Status labels

typedef StatusView = ({String label, RTone tone});

StatusView tournamentStatus(BuildContext context, String status) {
  final l = context.l10n;
  return switch (status) {
    'published' => (label: l.statusPublished, tone: RTone.info),
    'registration_open' => (label: l.statusRegistrationOpen, tone: RTone.accent),
    'registration_closed' => (label: l.statusRegistrationClosed, tone: RTone.warning),
    'in_progress' => (label: l.statusInProgress, tone: RTone.success),
    'completed' => (label: l.statusCompleted, tone: RTone.neutral),
    'postponed' => (label: l.statusPostponed, tone: RTone.warning),
    'cancelled' => (label: l.statusCancelled, tone: RTone.error),
    _ => (label: l.statusDraft, tone: RTone.neutral),
  };
}

StatusView registrationStatus(BuildContext context, String status, {int? waitlistPosition}) {
  final l = context.l10n;
  return switch (status) {
    'reserved' => (label: l.regStatusReserved, tone: RTone.success),
    'waitlisted' => (label: waitlistPosition != null ? l.entryWaitlistPosition(waitlistPosition) : l.regStatusWaitlisted, tone: RTone.warning),
    'withdrawn' => (label: l.regStatusWithdrawn, tone: RTone.neutral),
    'rejected' => (label: l.regStatusRejected, tone: RTone.error),
    'no_show' => (label: l.regStatusNoShow, tone: RTone.error),
    _ => (label: l.regStatusCancelled, tone: RTone.neutral),
  };
}

StatusView paymentStatus(BuildContext context, String status) {
  final l = context.l10n;
  return switch (status) {
    'not_required' => (label: l.payNotRequired, tone: RTone.neutral),
    'unpaid' => (label: l.payUnpaid, tone: RTone.warning),
    'paid' => (label: l.payPaid, tone: RTone.success),
    'refunded' => (label: l.payRefunded, tone: RTone.neutral),
    _ => (label: l.payPartiallyRefunded, tone: RTone.neutral),
  };
}

StatusView checkInStatus(BuildContext context, String status) =>
    status == 'checked_in' ? (label: context.l10n.checkInDone, tone: RTone.success) : (label: context.l10n.checkInNot, tone: RTone.neutral);

StatusView eligibilityStatus(BuildContext context, String status) {
  final l = context.l10n;
  return switch (status) {
    'eligible' => (label: l.eligEligible, tone: RTone.success),
    'ineligible' => (label: l.eligIneligible, tone: RTone.error),
    _ => (label: l.eligPending, tone: RTone.warning),
  };
}

String registrationTypeLabel(BuildContext context, String type) => switch (type) {
      'pair' => context.l10n.regPair,
      'team' => context.l10n.regTeam,
      _ => context.l10n.regIndividual,
    };

String formatLabel(BuildContext context, String format) => switch (format) {
      'round_robin' => context.l10n.formatRoundRobin,
      'groups_knockout' => context.l10n.formatGroupsKnockout,
      _ => context.l10n.formatSingleElimination,
    };

String teamRoleLabel(BuildContext context, String role) => switch (role) {
      'captain' => context.l10n.roleCaptain,
      'substitute' => context.l10n.roleSubstitute,
      'player' => context.l10n.rolePlayer,
      _ => context.l10n.roleMember,
    };

/// Maps a sport slug/icon key to the glyph key used by SportGlyph.
String sportIconKey(String? icon) => icon ?? '';

// ------------------------------------------------------------------ Countdowns

/// "3 days", "5 h", "20 min": the time left until [target], rounded down.
String timeUntil(BuildContext context, DateTime target, {DateTime? now}) {
  final l = context.l10n;
  final left = target.difference(now ?? DateTime.now());
  if (left.inDays >= 1) return l.durationDays(left.inDays);
  if (left.inHours >= 1) return l.durationHours(left.inHours);
  return l.durationMinutes(left.inMinutes.clamp(1, 59));
}

/// The most useful countdown for a tournament: registration closing soon, then start time.
/// Returns null when there is nothing time-sensitive to say.
String? tournamentCountdown(BuildContext context, {required String status, DateTime? startsAt, DateTime? registrationClosesAt, required bool registrationIsOpen}) {
  final l = context.l10n;
  final now = DateTime.now();
  if (status == 'in_progress') return l.countdownLive;
  if (registrationIsOpen && registrationClosesAt != null && registrationClosesAt.isAfter(now) && registrationClosesAt.difference(now).inDays < 3) {
    return l.countdownClosesIn(timeUntil(context, registrationClosesAt, now: now));
  }
  if (startsAt != null && startsAt.isAfter(now)) {
    return l.countdownStartsIn(timeUntil(context, startsAt, now: now));
  }
  return null;
}

String newsCategoryLabel(BuildContext context, String category) => switch (category) {
      'tournament' => context.l10n.newsCategoryTournament,
      'results' => context.l10n.newsCategoryResults,
      'community' => context.l10n.newsCategoryCommunity,
      'guide' => context.l10n.newsCategoryGuide,
      _ => context.l10n.newsCategoryAnnouncement,
    };

/// Short relative publish date: "Today", otherwise the date.
String formatPublished(BuildContext context, DateTime? value) {
  if (value == null) return '';
  final now = DateTime.now();
  if (value.year == now.year && value.month == now.month && value.day == now.day) return context.l10n.countdownToday;
  return formatDate(context, value);
}
