import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/utils/json.dart';

class Discipline {
  const Discipline({
    required this.id,
    required this.sportId,
    required this.name,
    required this.registrationTypes,
    required this.teamSizeMin,
    required this.teamSizeMax,
    this.gameTitle,
    this.gameVersion,
    this.platform,
    this.region,
    this.competitionMode,
  });

  final int id;
  final int sportId;
  final String name;
  final List<String> registrationTypes;
  final int teamSizeMin;
  final int teamSizeMax;
  final String? gameTitle;
  final String? gameVersion;
  final String? platform;
  final String? region;
  final String? competitionMode;

  /// Short e-sports detail line, e.g. "PC · EU · Online". Empty for physical sports.
  String get esportsDetail => [gameVersion, platform, region, competitionMode].whereType<String>().where((s) => s.isNotEmpty).join(' · ');

  factory Discipline.fromJson(Json j) => Discipline(
        id: j.integer('id'),
        sportId: j.integer('sport_id'),
        name: j.str('name'),
        registrationTypes: (j['registration_types'] as List? ?? const []).map((e) => '$e').toList(),
        teamSizeMin: j.integer('team_size_min', 1),
        teamSizeMax: j.integer('team_size_max', 1),
        gameTitle: j.strOrNull('game_title'),
        gameVersion: j.strOrNull('game_version'),
        platform: j.strOrNull('platform'),
        region: j.strOrNull('region'),
        competitionMode: j.strOrNull('competition_mode'),
      );
}

class Sport {
  const Sport({required this.id, required this.slug, required this.name, required this.kind, required this.supportsTeams, required this.disciplines, this.icon, this.teamMemberLimit});

  final int id;
  final String slug;
  final String name;
  final String kind;
  final String? icon;
  final bool supportsTeams;
  final int? teamMemberLimit;
  final List<Discipline> disciplines;

  factory Sport.fromJson(Json j) => Sport(
        id: j.integer('id'),
        slug: j.str('slug'),
        name: j.str('name'),
        kind: j.str('kind'),
        icon: j.strOrNull('icon'),
        supportsTeams: j.boolean('supports_teams'),
        teamMemberLimit: j.intOrNull('team_member_limit'),
        disciplines: j.list('disciplines', Discipline.fromJson),
      );
}

/// Active sports with their active disciplines (cached for the session).
final sportsProvider = FutureProvider<List<Sport>>((ref) async {
  final json = await ref.watch(apiClientProvider).get('/catalog/sports');
  return json.list('data', Sport.fromJson);
});

/// Public app configuration (feature flags the app may read, support contact).
class AppConfig {
  const AppConfig({required this.features, this.supportEmail});

  final Map<String, bool> features;
  final String? supportEmail;

  bool get storeEnabled => features['store_enabled'] ?? false;

  /// Approved outside sellers (Milestone 9). Only meaningful while the store is on.
  bool get marketplaceEnabled => storeEnabled && (features['marketplace_enabled'] ?? false);

  factory AppConfig.fromJson(Json j) => AppConfig(features: j.boolMap('features'), supportEmail: j.strOrNull('support_contact'));
}

final appConfigProvider = FutureProvider<AppConfig>((ref) async {
  return AppConfig.fromJson(await ref.watch(apiClientProvider).get('/config'));
});
