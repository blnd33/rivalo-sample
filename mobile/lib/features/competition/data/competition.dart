import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';

// ------------------------------------------------------------------ Fixtures

class MatchSlot {
  const MatchSlot({required this.slot, required this.isWinner, this.entryId, this.name});

  final int slot;
  final int? entryId;
  final String? name;
  final bool isWinner;

  factory MatchSlot.fromJson(Json j) => MatchSlot(
        slot: j.integer('slot'),
        entryId: j.intOrNull('entry_id'),
        name: j.strOrNull('name'),
        isWinner: j.boolean('is_winner'),
      );
}

class MatchResultView {
  const MatchResultView({required this.id, required this.outcome, required this.scoreLine, required this.isDraw, this.winnerSlot, this.slot1, this.slot2, this.sets = const []});

  final int id;
  final String outcome;
  final String scoreLine;
  final int? winnerSlot;
  final bool isDraw;
  final int? slot1;
  final int? slot2;
  final List<List<int>> sets;

  factory MatchResultView.fromJson(Json j) {
    final scores = j.objOrNull('scores') ?? const {};
    final rawSets = scores['sets'];
    return MatchResultView(
      id: j.integer('id'),
      outcome: j.str('outcome'),
      scoreLine: j.str('score_line'),
      winnerSlot: j.intOrNull('winner_slot'),
      isDraw: j.boolean('is_draw'),
      slot1: scores.intOrNull('slot1'),
      slot2: scores.intOrNull('slot2'),
      sets: rawSets is List ? rawSets.whereType<List>().map((s) => s.map((v) => v is int ? v : int.tryParse('$v') ?? 0).toList()).toList() : const [],
    );
  }
}

class FixtureMatch {
  const FixtureMatch({
    required this.id,
    required this.round,
    required this.position,
    required this.status,
    required this.slots,
    required this.canSubmit,
    required this.canDispute,
    this.label,
    this.group,
    this.scheduledAt,
    this.location,
    this.mySlot,
    this.result,
    this.pendingScoreLine,
    this.pendingByMe = false,
    this.openDispute = false,
    this.disputeWindowHours,
    this.tournamentSlug,
    this.tournamentName,
  });

  final int id;
  final int round;
  final int position;
  final String? label;
  final String? group;

  /// pending | ready | completed | cancelled | bye
  final String status;
  final DateTime? scheduledAt;
  final String? location;
  final List<MatchSlot> slots;
  final int? mySlot;
  final MatchResultView? result;
  final String? pendingScoreLine;
  final bool pendingByMe;
  final bool canSubmit;
  final bool canDispute;
  final bool openDispute;
  final int? disputeWindowHours;
  final String? tournamentSlug;
  final String? tournamentName;

  MatchSlot slot(int n) => slots.firstWhere((s) => s.slot == n, orElse: () => MatchSlot(slot: n, isWinner: false));
  bool get isMine => mySlot != null;
  bool get isFinished => status == 'completed' || status == 'cancelled' || status == 'bye';

  factory FixtureMatch.fromJson(Json j) {
    final pending = j.objOrNull('pending_submission');
    final tournament = j.objOrNull('tournament');
    return FixtureMatch(
      id: j.integer('id'),
      round: j.integer('round'),
      position: j.integer('position'),
      label: j.strOrNull('label'),
      group: j.strOrNull('group'),
      status: j.str('status', 'pending'),
      scheduledAt: j.date('scheduled_at'),
      location: j.strOrNull('location'),
      slots: j.list('slots', MatchSlot.fromJson),
      mySlot: j.intOrNull('my_slot'),
      result: j.objOrNull('result') == null ? null : MatchResultView.fromJson(j.obj('result')),
      pendingScoreLine: pending?.strOrNull('score_line'),
      pendingByMe: pending?.boolean('by_my_side') ?? false,
      canSubmit: j.boolean('can_submit'),
      canDispute: j.boolean('can_dispute'),
      openDispute: j.boolean('open_dispute'),
      disputeWindowHours: j.intOrNull('dispute_window_hours'),
      tournamentSlug: tournament?.strOrNull('slug'),
      tournamentName: tournament?.strOrNull('name'),
    );
  }
}

class StandingRow {
  const StandingRow({required this.rank, required this.entryId, required this.name, required this.played, required this.won, required this.drawn, required this.lost, required this.diff, required this.points, required this.isMine});

  final int rank;
  final int entryId;
  final String name;
  final int played;
  final int won;
  final int drawn;
  final int lost;
  final int diff;
  final int points;
  final bool isMine;

  factory StandingRow.fromJson(Json j) => StandingRow(
        rank: j.integer('rank'),
        entryId: j.integer('entry_id'),
        name: j.str('name'),
        played: j.integer('played'),
        won: j.integer('won'),
        drawn: j.integer('drawn'),
        lost: j.integer('lost'),
        diff: j.integer('diff'),
        points: j.integer('points'),
        isMine: j.boolean('is_mine'),
      );
}

class FixtureRound {
  const FixtureRound({required this.round, required this.matches, this.label});

  final int round;
  final String? label;
  final List<FixtureMatch> matches;

  factory FixtureRound.fromJson(Json j) => FixtureRound(round: j.integer('round'), label: j.strOrNull('label'), matches: j.list('matches', FixtureMatch.fromJson));
}

class FixtureGroup {
  const FixtureGroup({required this.name, required this.standings, required this.matches});

  final String name;
  final List<StandingRow> standings;
  final List<FixtureMatch> matches;

  factory FixtureGroup.fromJson(Json j) =>
      FixtureGroup(name: j.str('name'), standings: j.list('standings', StandingRow.fromJson), matches: j.list('matches', FixtureMatch.fromJson));
}

class FixtureStage {
  const FixtureStage({required this.id, required this.type, required this.name, required this.status, required this.rounds, required this.groups, this.standings, this.advancePerGroup});

  final int id;

  /// single_elimination | round_robin | groups | knockout
  final String type;
  final String name;
  final String status;
  final List<FixtureRound> rounds;
  final List<FixtureGroup> groups;
  final List<StandingRow>? standings;
  final int? advancePerGroup;

  bool get isBracket => type == 'single_elimination' || type == 'knockout';

  factory FixtureStage.fromJson(Json j) => FixtureStage(
        id: j.integer('id'),
        type: j.str('type'),
        name: j.str('name'),
        status: j.str('status'),
        rounds: j.list('rounds', FixtureRound.fromJson),
        groups: j.list('groups', FixtureGroup.fromJson),
        standings: j['standings'] is List ? j.list('standings', StandingRow.fromJson) : null,
        advancePerGroup: j.intOrNull('advance_per_group'),
      );
}

class Placement {
  const Placement({required this.place, required this.name, required this.isMine});

  final int place;
  final String name;
  final bool isMine;

  factory Placement.fromJson(Json j) => Placement(place: j.integer('place'), name: j.str('name'), isMine: j.boolean('is_mine'));
}

class Fixtures {
  const Fixtures({required this.stages, required this.placementsFinal, required this.placements, required this.rankingEligible, this.pointsTable});

  final List<FixtureStage> stages;
  final bool placementsFinal;
  final List<Placement> placements;
  final bool rankingEligible;
  final String? pointsTable;

  bool get isEmpty => stages.isEmpty;

  /// Every match across stages and groups (for "my matches").
  Iterable<FixtureMatch> get allMatches => stages.expand((s) => [...s.rounds.expand((r) => r.matches), ...s.groups.expand((g) => g.matches)]);

  factory Fixtures.fromJson(Json j) {
    final points = j.obj('points');
    final set = points.objOrNull('rule_set');
    return Fixtures(
      stages: j.list('stages', FixtureStage.fromJson),
      placementsFinal: j.boolean('placements_final'),
      placements: j.list('placements', Placement.fromJson),
      rankingEligible: points.boolean('ranking_eligible'),
      pointsTable: set == null ? null : '${set.str('name')} · v${set.integer('version', 1)}',
    );
  }
}

// ------------------------------------------------------------------ Leaderboards and points

class LeaderboardRow {
  const LeaderboardRow({required this.rank, required this.username, required this.name, required this.points, required this.wins, required this.tournaments, required this.isMe, this.avatarUrl});

  final int rank;
  final String username;
  final String name;
  final String? avatarUrl;
  final int points;
  final int wins;
  final int tournaments;
  final bool isMe;

  factory LeaderboardRow.fromJson(Json j) {
    final u = j.obj('user');
    return LeaderboardRow(
      rank: j.integer('rank'),
      username: u.str('username'),
      name: u.str('name'),
      avatarUrl: u.strOrNull('avatar_url'),
      points: j.integer('points'),
      wins: j.integer('wins'),
      tournaments: j.integer('tournaments'),
      isMe: j.boolean('is_me'),
    );
  }
}

class Leaderboard {
  const Leaderboard({required this.rows, required this.hasPublishedRules});

  final List<LeaderboardRow> rows;
  final bool hasPublishedRules;
}

class Season {
  const Season({required this.id, required this.name, required this.status});

  final int id;
  final String name;
  final String status;

  factory Season.fromJson(Json j) => Season(id: j.integer('id'), name: j.str('name'), status: j.str('status'));
}

class PointsAward {
  const PointsAward({required this.id, required this.points, required this.awardType, this.reason, this.tournamentSlug, this.tournamentName, this.createdAt});

  final int id;
  final int points;
  final String awardType;
  final String? reason;
  final String? tournamentSlug;
  final String? tournamentName;
  final DateTime? createdAt;

  factory PointsAward.fromJson(Json j) {
    final t = j.objOrNull('tournament');
    return PointsAward(
      id: j.integer('id'),
      points: j.integer('points'),
      awardType: j.str('award_type'),
      reason: j.strOrNull('reason'),
      tournamentSlug: t?.strOrNull('slug'),
      tournamentName: t?.strOrNull('name'),
      createdAt: j.date('created_at'),
    );
  }
}

class MyPoints {
  const MyPoints({required this.total, required this.history});

  final int total;
  final List<PointsAward> history;
}

// ------------------------------------------------------------------ Repository and providers

class CompetitionRepository {
  CompetitionRepository(this._api);

  final ApiClient _api;

  Future<Fixtures> fixtures(String slug) async => Fixtures.fromJson((await _api.get('/tournaments/$slug/fixtures')).obj('data'));

  Future<FixtureMatch> match(int id) async => FixtureMatch.fromJson((await _api.get('/matches/$id')).obj('data'));

  Future<FixtureMatch> submitResult(int matchId, {required String outcome, int? slot1, int? slot2, List<List<int>> sets = const [], int? winnerSlot, String? note}) async {
    final json = await _api.post('/matches/$matchId/results', body: {
      'outcome': outcome,
      'slot1': ?slot1,
      'slot2': ?slot2,
      if (sets.isNotEmpty) 'sets': sets,
      'winner_slot': ?winnerSlot,
      'note': ?note,
    });
    return FixtureMatch.fromJson(json.obj('data'));
  }

  Future<void> dispute(int resultId, String reason) async {
    await _api.post('/match-results/$resultId/disputes', body: {'reason': reason});
  }

  Future<Leaderboard> leaderboard(int disciplineId, int? seasonId) async {
    final json = await _api.get('/leaderboards', query: {'discipline_id': disciplineId, 'season_id': seasonId});
    return Leaderboard(rows: json.list('data', LeaderboardRow.fromJson), hasPublishedRules: json.obj('meta').boolean('has_published_rules'));
  }

  Future<List<Season>> seasons() async => (await _api.get('/seasons')).list('data', Season.fromJson);

  Future<MyPoints> myPoints() async {
    final data = (await _api.get('/me/points')).obj('data');
    return MyPoints(total: data.integer('total'), history: data.list('history', PointsAward.fromJson));
  }
}

final competitionRepositoryProvider = Provider((ref) => CompetitionRepository(ref.watch(apiClientProvider)));

final fixturesProvider = FutureProvider.autoDispose.family<Fixtures, String>((ref, slug) => ref.watch(competitionRepositoryProvider).fixtures(slug));

final matchProvider = FutureProvider.autoDispose.family<FixtureMatch, int>((ref, id) => ref.watch(competitionRepositoryProvider).match(id));

typedef LeaderboardQuery = ({int disciplineId, int? seasonId});

final leaderboardProvider = FutureProvider.autoDispose.family<Leaderboard, LeaderboardQuery>(
  (ref, q) => ref.watch(competitionRepositoryProvider).leaderboard(q.disciplineId, q.seasonId),
);

final seasonsProvider = FutureProvider<List<Season>>((ref) => ref.watch(competitionRepositoryProvider).seasons());

final myPointsProvider = FutureProvider.autoDispose<MyPoints>((ref) => ref.watch(competitionRepositoryProvider).myPoints());
