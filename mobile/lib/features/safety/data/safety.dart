import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';
import '../../teams/data/teams.dart';

/// Policy slugs published by the server (Admin → Community → Policy pages).
abstract final class PolicySlugs {
  static const terms = 'terms';
  static const privacy = 'privacy';
  static const guidelines = 'community-guidelines';

  /// Milestone 9: how selling on RIVALO works (draft pending legal review).
  static const sellerTerms = 'seller-terms';
}

/// A published policy text. [legalStatus] is "pending", "reviewed" or "not_required" exactly as
/// recorded by staff; the app never upgrades it.
class PolicyPage {
  const PolicyPage({
    required this.slug,
    required this.title,
    required this.body,
    required this.version,
    required this.translationPending,
    required this.legalStatus,
    this.publishedAt,
  });

  final String slug;
  final String title;
  final String body;
  final int version;
  final DateTime? publishedAt;

  /// Not yet translated into the app language: the English text is shown.
  final bool translationPending;
  final String legalStatus;

  bool get pendingLegalReview => legalStatus == 'pending';

  factory PolicyPage.fromJson(Json j) => PolicyPage(
        slug: j.str('slug'),
        title: j.str('title'),
        body: j.str('body'),
        version: j.integer('version'),
        publishedAt: j.date('published_at'),
        translationPending: j.boolean('translation_pending'),
        legalStatus: j.obj('legal_review').str('status', 'pending'),
      );
}

/// What is being reported.
sealed class ReportTarget {
  const ReportTarget();

  String get type;
  String? get subject;

  /// Reasons the server accepts for this kind of report (App\Enums\ReportSubject::reasons()).
  List<String> get reasons;
}

class ReportPlayer extends ReportTarget {
  const ReportPlayer({required this.username, required this.name});

  final String username;
  final String name;

  @override
  String get type => 'user';
  @override
  String? get subject => username;
  @override
  List<String> get reasons => const ['offensive_name', 'offensive_photo', 'harassment', 'cheating', 'impersonation', 'spam', 'other'];
}

class ReportTeam extends ReportTarget {
  const ReportTeam({required this.slug, required this.name});

  final String slug;
  final String name;

  @override
  String get type => 'team';
  @override
  String? get subject => slug;
  @override
  List<String> get reasons => const ['offensive_name', 'offensive_photo', 'cheating', 'impersonation', 'spam', 'other'];
}

class ReportApp extends ReportTarget {
  const ReportApp();

  @override
  String get type => 'app';
  @override
  String? get subject => null;
  @override
  List<String> get reasons => const ['app_problem', 'suggestion'];
}

class SafetyRepository {
  SafetyRepository(this._api);

  final ApiClient _api;

  Future<PolicyPage> page(String slug) async => PolicyPage.fromJson((await _api.get('/pages/$slug')).obj('data'));

  Future<void> report(ReportTarget target, String reason, {String? details, String? appVersion, String? platform}) => _api.post('/reports', body: {
        'subject_type': target.type,
        'subject': ?target.subject,
        'reason': reason,
        if (details != null && details.trim().isNotEmpty) 'details': details.trim(),
        if (target is ReportApp) 'app_version': ?appVersion,
        if (target is ReportApp) 'platform': ?platform,
      });

  Future<void> block(String username) => _api.post('/users/$username/block');

  Future<void> unblock(String username) => _api.delete('/users/$username/block');

  Future<List<UserSummary>> blocked() async => (await _api.get('/me/blocks')).list('data', UserSummary.fromJson);
}

final safetyRepositoryProvider = Provider((ref) => SafetyRepository(ref.watch(apiClientProvider)));

final policyPageProvider = FutureProvider.autoDispose.family<PolicyPage, String>((ref, slug) => ref.watch(safetyRepositoryProvider).page(slug));

final blockedPlayersProvider = FutureProvider.autoDispose<List<UserSummary>>((ref) => ref.watch(safetyRepositoryProvider).blocked());
