import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';
import '../../engagement/data/engagement.dart';
import '../../auth/data/me.dart';

class PublicTeam {
  const PublicTeam({required this.slug, required this.name, required this.sport, required this.role, this.logoUrl});

  final String slug;
  final String name;
  final String sport;
  final String role;
  final String? logoUrl;

  factory PublicTeam.fromJson(Json j) =>
      PublicTeam(slug: j.str('slug'), name: j.str('name'), sport: j.str('sport'), role: j.str('role'), logoUrl: j.strOrNull('logo_url'));
}

class HistoryItem {
  const HistoryItem({required this.slug, required this.name, required this.discipline, this.endedAt});

  final String slug;
  final String name;
  final String discipline;
  final DateTime? endedAt;

  factory HistoryItem.fromJson(Json j) =>
      HistoryItem(slug: j.str('tournament_slug'), name: j.str('tournament_name'), discipline: j.str('discipline'), endedAt: j.date('ended_at'));
}

/// Public profile: contains only what the player's privacy settings allow.
class PublicProfile {
  const PublicProfile({
    required this.username,
    required this.disciplines,
    required this.teams,
    required this.history,
    this.displayName,
    this.avatarUrl,
    this.city,
    this.memberSince,
    this.badges,
    this.points,
    this.isSelf = false,
    this.blockedByMe = false,
  });

  final String username;
  final String? displayName;
  final String? avatarUrl;
  final String? city;
  final List<String> disciplines;

  /// null when hidden by the player's privacy settings.
  final List<PublicTeam>? teams;
  final List<HistoryItem>? history;
  final String? memberSince;

  /// null when hidden by the player's privacy settings.
  final List<PlayerBadge>? badges;
  final int? points;

  /// About the signed-in viewer only (false for guests).
  final bool isSelf;
  final bool blockedByMe;

  factory PublicProfile.fromJson(Json j) => PublicProfile(
        username: j.str('username'),
        displayName: j.strOrNull('display_name'),
        avatarUrl: j.strOrNull('avatar_url'),
        city: j.strOrNull('city'),
        disciplines: j.list('disciplines', (d) => d.str('name')),
        teams: j['teams'] == null ? null : j.list('teams', PublicTeam.fromJson),
        history: j['tournament_history'] == null ? null : j.list('tournament_history', HistoryItem.fromJson),
        memberSince: j.strOrNull('member_since'),
        badges: j['badges'] is List ? j.list('badges', PlayerBadge.fromJson) : null,
        points: j.objOrNull('points')?.intOrNull('total'),
        isSelf: j.objOrNull('viewer')?.boolean('is_self') ?? false,
        blockedByMe: j.objOrNull('viewer')?.boolean('blocked') ?? false,
      );
}

class DeviceSession {
  const DeviceSession({required this.id, required this.deviceName, required this.isCurrent, this.lastUsedAt});

  final int id;
  final String deviceName;
  final bool isCurrent;
  final DateTime? lastUsedAt;

  factory DeviceSession.fromJson(Json j) =>
      DeviceSession(id: j.integer('id'), deviceName: j.str('device_name'), isCurrent: j.boolean('is_current'), lastUsedAt: j.date('last_used_at'));
}

class ProfileRepository {
  ProfileRepository(this._api);

  final ApiClient _api;

  Me _me(Json json) => Me.fromJson(json.obj('data'));

  Future<Me> update({String? displayName, String? city, String? phone, String? locale}) async => _me(await _api.patch('/me', body: {
        'display_name': ?displayName,
        'city': ?city,
        'phone': ?phone,
        'locale': ?locale,
      }));

  Future<Me> uploadAvatar(String filePath) async => _me(await _api.upload('/me/avatar', field: 'avatar', filePath: filePath));

  Future<Me> removeAvatar() async => _me(await _api.delete('/me/avatar'));

  Future<Me> setDisciplines(List<int> ids) async => _me(await _api.put('/me/disciplines', body: {'discipline_ids': ids}));

  Future<Me> setPrivacy(Map<String, bool> values) async => _me(await _api.patch('/me/privacy', body: values));

  Future<Me> setNotificationPreferences(Map<String, bool> values) async => _me(await _api.patch('/me/notification-preferences', body: values));

  Future<void> changePassword(String current, String next) =>
      _api.put('/me/password', body: {'current_password': current, 'password': next, 'password_confirmation': next});

  Future<List<DeviceSession>> sessions() async => (await _api.get('/me/sessions')).list('data', DeviceSession.fromJson);

  Future<void> revokeSession(int id) => _api.delete('/me/sessions/$id');

  Future<DateTime?> requestDeletion(String password, {String? reason}) async {
    final json = await _api.post('/me/deletion-request', body: {'password': password, 'reason': ?reason});
    return json.date('scheduled_for');
  }

  Future<void> cancelDeletion() => _api.delete('/me/deletion-request');

  Future<PublicProfile> publicProfile(String username) async => PublicProfile.fromJson((await _api.get('/users/$username')).obj('data'));
}

final profileRepositoryProvider = Provider((ref) => ProfileRepository(ref.watch(apiClientProvider)));

final publicProfileProvider = FutureProvider.autoDispose.family<PublicProfile, String>((ref, username) => ref.watch(profileRepositoryProvider).publicProfile(username));

final sessionsProvider = FutureProvider.autoDispose<List<DeviceSession>>((ref) => ref.watch(profileRepositoryProvider).sessions());
