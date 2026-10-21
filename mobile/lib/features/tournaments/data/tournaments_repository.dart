import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';
import 'tournament_models.dart';

class TournamentsRepository {
  TournamentsRepository(this._api);

  final ApiClient _api;

  Future<List<Tournament>> list({String scope = 'upcoming', int? sportId}) async {
    final json = await _api.get('/tournaments', query: {'scope': scope, 'sport_id': sportId, 'per_page': 50});
    return json.list('data', Tournament.fromJson);
  }

  Future<Tournament> detail(String slug) async {
    final json = await _api.get('/tournaments/$slug');
    return Tournament.fromJson(json.obj('data'));
  }

  Future<TournamentEntry?> myEntry(String slug) async {
    final json = await _api.get('/tournaments/$slug/my-entry');
    final data = json.objOrNull('data');
    return data == null ? null : TournamentEntry.fromJson(data);
  }

  Future<List<TournamentEntry>> myRegistrations() async {
    final json = await _api.get('/me/registrations');
    return json.list('data', TournamentEntry.fromJson);
  }

  Future<TournamentEntry> register(String slug, {int? teamId, List<int> playerIds = const [], List<int> substituteIds = const []}) async {
    final json = await _api.post('/tournaments/$slug/registrations', body: {
      'team_id': ?teamId,
      if (playerIds.isNotEmpty) 'player_ids': playerIds,
      if (substituteIds.isNotEmpty) 'substitute_ids': substituteIds,
    });
    return TournamentEntry.fromJson(json.obj('data'));
  }

  Future<TournamentEntry> cancel(int entryId, {String? reason}) async {
    final json = await _api.post('/registrations/$entryId/cancel', body: {'reason': ?reason});
    return TournamentEntry.fromJson(json.obj('data'));
  }
}

final tournamentsRepositoryProvider = Provider((ref) => TournamentsRepository(ref.watch(apiClientProvider)));

typedef TournamentQuery = ({String scope, int? sportId});

final tournamentListProvider = FutureProvider.autoDispose.family<List<Tournament>, TournamentQuery>((ref, q) {
  return ref.watch(tournamentsRepositoryProvider).list(scope: q.scope, sportId: q.sportId);
});

final tournamentDetailProvider = FutureProvider.autoDispose.family<Tournament, String>((ref, slug) {
  return ref.watch(tournamentsRepositoryProvider).detail(slug);
});

final myEntryProvider = FutureProvider.autoDispose.family<TournamentEntry?, String>((ref, slug) {
  return ref.watch(tournamentsRepositoryProvider).myEntry(slug);
});

final myRegistrationsProvider = FutureProvider.autoDispose<List<TournamentEntry>>((ref) {
  return ref.watch(tournamentsRepositoryProvider).myRegistrations();
});
