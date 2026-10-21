import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';

class UserSummary {
  const UserSummary({required this.id, required this.username, required this.name, this.avatarUrl});

  final int id;
  final String username;
  final String name;
  final String? avatarUrl;

  factory UserSummary.fromJson(Json j) => UserSummary(
        id: j.integer('id'),
        username: j.str('username'),
        name: j.str('name'),
        avatarUrl: j.strOrNull('avatar_url'),
      );
}

class TeamMember {
  const TeamMember({required this.user, required this.role, this.joinedAt});

  final UserSummary user;
  final String role; // captain | member | substitute
  final DateTime? joinedAt;

  factory TeamMember.fromJson(Json j) => TeamMember(user: UserSummary.fromJson(j.obj('user')), role: j.str('role'), joinedAt: j.date('joined_at'));
}

class PendingInvite {
  const PendingInvite({required this.id, required this.user, required this.role, this.expiresAt});

  final int id;
  final UserSummary user;
  final String role;
  final DateTime? expiresAt;

  factory PendingInvite.fromJson(Json j) =>
      PendingInvite(id: j.integer('id'), user: UserSummary.fromJson(j.obj('user')), role: j.str('role'), expiresAt: j.date('expires_at'));
}

class Team {
  const Team({
    required this.id,
    required this.slug,
    required this.name,
    required this.status,
    required this.sportId,
    required this.sportName,
    required this.members,
    required this.isCaptain,
    required this.isMember,
    required this.pendingInvites,
    this.logoUrl,
    this.teamMemberLimit,
    this.captain,
    this.pendingTransferTo,
  });

  final int id;
  final String slug;
  final String name;
  final String? logoUrl;
  final String status;
  final int sportId;
  final String sportName;
  final int? teamMemberLimit;
  final UserSummary? captain;
  final List<TeamMember> members;
  final bool isCaptain;
  final bool isMember;
  final List<PendingInvite> pendingInvites;
  final UserSummary? pendingTransferTo;

  bool get isFull => teamMemberLimit != null && members.length >= teamMemberLimit!;

  factory Team.fromJson(Json j) {
    final sport = j.obj('sport');
    final viewer = j.obj('viewer');
    final transfer = j.objOrNull('pending_captain_transfer');
    return Team(
      id: j.integer('id'),
      slug: j.str('slug'),
      name: j.str('name'),
      logoUrl: j.strOrNull('logo_url'),
      status: j.str('status'),
      sportId: sport.integer('id'),
      sportName: sport.str('name'),
      teamMemberLimit: sport.intOrNull('team_member_limit'),
      captain: j.objOrNull('captain') == null ? null : UserSummary.fromJson(j.obj('captain')),
      members: j.list('members', TeamMember.fromJson),
      isCaptain: viewer.boolean('is_captain'),
      isMember: viewer.boolean('is_member'),
      pendingInvites: j.list('pending_invitations', PendingInvite.fromJson),
      pendingTransferTo: transfer == null ? null : UserSummary.fromJson(transfer.obj('to')),
    );
  }
}

class TeamInvitation {
  const TeamInvitation({required this.id, required this.role, required this.teamSlug, required this.teamName, required this.sportName, required this.invitedBy, this.expiresAt});

  final int id;
  final String role;
  final String teamSlug;
  final String teamName;
  final String sportName;
  final UserSummary invitedBy;
  final DateTime? expiresAt;

  factory TeamInvitation.fromJson(Json j) {
    final team = j.obj('team');
    return TeamInvitation(
      id: j.integer('id'),
      role: j.str('role'),
      teamSlug: team.str('slug'),
      teamName: team.str('name'),
      sportName: team.str('sport'),
      invitedBy: UserSummary.fromJson(j.obj('invited_by')),
      expiresAt: j.date('expires_at'),
    );
  }
}

class CaptainNomination {
  const CaptainNomination({required this.id, required this.teamSlug, required this.teamName, required this.from});

  final int id;
  final String teamSlug;
  final String teamName;
  final UserSummary from;

  factory CaptainNomination.fromJson(Json j) => CaptainNomination(
        id: j.integer('id'),
        teamSlug: j.obj('team').str('slug'),
        teamName: j.obj('team').str('name'),
        from: UserSummary.fromJson(j.obj('from')),
      );
}

class Inbox {
  const Inbox({required this.invitations, required this.nominations});

  final List<TeamInvitation> invitations;
  final List<CaptainNomination> nominations;

  int get count => invitations.length + nominations.length;
}

class TeamsRepository {
  TeamsRepository(this._api);

  final ApiClient _api;

  Future<List<Team>> myTeams() async => (await _api.get('/me/teams')).list('data', Team.fromJson);

  Future<Team> team(String slug) async => Team.fromJson((await _api.get('/teams/$slug')).obj('data'));

  Future<Team> create({required int sportId, required String name}) async =>
      Team.fromJson((await _api.post('/teams', body: {'sport_id': sportId, 'name': name})).obj('data'));

  Future<Team> rename(String slug, String name) async => Team.fromJson((await _api.patch('/teams/$slug', body: {'name': name})).obj('data'));

  Future<void> invite(String slug, String username, {String role = 'member'}) => _api.post('/teams/$slug/invitations', body: {'username': username, 'role': role});

  Future<void> revokeInvite(String slug, int invitationId) => _api.delete('/teams/$slug/invitations/$invitationId');

  Future<void> accept(int invitationId) => _api.post('/invitations/$invitationId/accept');

  Future<void> decline(int invitationId) => _api.post('/invitations/$invitationId/decline');

  Future<void> leave(String slug) => _api.post('/teams/$slug/leave');

  Future<void> removeMember(String slug, int userId) => _api.delete('/teams/$slug/members/$userId');

  Future<void> setMemberRole(String slug, int userId, String role) => _api.patch('/teams/$slug/members/$userId', body: {'role': role});

  Future<void> nominateCaptain(String slug, String username) => _api.post('/teams/$slug/captain-transfer', body: {'username': username});

  Future<void> respondNomination(int transferId, {required bool accept}) => _api.post('/captain-transfers/$transferId/respond', body: {'accept': accept});

  Future<void> disband(String slug) => _api.post('/teams/$slug/disband');

  Future<Inbox> inbox() async {
    final json = await _api.get('/me/invitations');
    return Inbox(
      invitations: json.list('invitations', TeamInvitation.fromJson),
      nominations: json.list('captain_transfers', CaptainNomination.fromJson),
    );
  }

  Future<List<UserSummary>> searchUsers(String query) async => (await _api.get('/users', query: {'q': query})).list('data', UserSummary.fromJson);
}

final teamsRepositoryProvider = Provider((ref) => TeamsRepository(ref.watch(apiClientProvider)));

final myTeamsProvider = FutureProvider.autoDispose<List<Team>>((ref) => ref.watch(teamsRepositoryProvider).myTeams());

final teamProvider = FutureProvider.autoDispose.family<Team, String>((ref, slug) => ref.watch(teamsRepositoryProvider).team(slug));

final inboxProvider = FutureProvider.autoDispose<Inbox>((ref) => ref.watch(teamsRepositoryProvider).inbox());
