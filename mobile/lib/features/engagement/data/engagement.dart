import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';

/// One inbox message. Text arrives already written in the user's language.
class InboxMessage {
  const InboxMessage({required this.id, required this.type, required this.title, required this.read, this.body, this.linkKind, this.linkRef, this.createdAt});

  final int id;
  final String type;
  final String title;
  final String? body;
  final String? linkKind;
  final String? linkRef;
  final bool read;
  final DateTime? createdAt;

  InboxMessage copyWith({bool? read}) =>
      InboxMessage(id: id, type: type, title: title, body: body, linkKind: linkKind, linkRef: linkRef, read: read ?? this.read, createdAt: createdAt);

  factory InboxMessage.fromJson(Json j) {
    final link = j.objOrNull('link');
    return InboxMessage(
      id: j.integer('id'),
      type: j.str('type'),
      title: j.str('title'),
      body: j.strOrNull('body'),
      linkKind: link?.strOrNull('kind'),
      linkRef: link?.strOrNull('ref'),
      read: j.boolean('read'),
      createdAt: j.date('created_at'),
    );
  }
}

class InboxPage {
  const InboxPage({required this.messages, required this.unread});

  final List<InboxMessage> messages;
  final int unread;
}

/// A badge with my progress toward it.
class PlayerBadge {
  const PlayerBadge({required this.key, required this.name, required this.description, required this.icon, required this.earned, this.awardedAt, this.current, this.target});

  final String key;
  final String name;
  final String description;
  final String icon;
  final bool earned;
  final DateTime? awardedAt;
  final int? current;
  final int? target;

  double get progress => (target == null || target == 0) ? (earned ? 1 : 0) : (current ?? 0) / target!;

  factory PlayerBadge.fromJson(Json j) {
    final p = j.objOrNull('progress');
    return PlayerBadge(
      key: j.str('key'),
      name: j.str('name'),
      description: j.str('description'),
      icon: j.str('icon', 'rosette'),
      earned: j['earned'] == null ? true : j.boolean('earned'),
      awardedAt: j.date('awarded_at'),
      current: p?.intOrNull('current'),
      target: p?.intOrNull('target'),
    );
  }
}

class EngagementRepository {
  EngagementRepository(this._api);

  final ApiClient _api;

  Future<InboxPage> inbox() async {
    final json = await _api.get('/me/notifications');
    return InboxPage(messages: json.list('data', InboxMessage.fromJson), unread: json.obj('meta').integer('unread'));
  }

  Future<int> unreadCount() async => (await _api.get('/me/notifications/unread-count')).obj('data').integer('unread');

  Future<void> markRead(int id) => _api.post('/me/notifications/$id/read');

  Future<void> markAllRead() => _api.post('/me/notifications/read-all');

  Future<List<PlayerBadge>> myBadges() async => (await _api.get('/me/achievements')).list('data', PlayerBadge.fromJson);

  /// Register this phone for push notifications (called by the push service once it has a token).
  Future<void> registerDevice(String token, String platform, {String? appVersion}) =>
      _api.post('/me/devices', body: {'token': token, 'platform': platform, 'app_version': ?appVersion});
}

final engagementRepositoryProvider = Provider((ref) => EngagementRepository(ref.watch(apiClientProvider)));

final notificationsProvider = FutureProvider.autoDispose<InboxPage>((ref) => ref.watch(engagementRepositoryProvider).inbox());

final unreadNotificationsProvider = FutureProvider.autoDispose<int>((ref) => ref.watch(engagementRepositoryProvider).unreadCount());

final myBadgesProvider = FutureProvider.autoDispose<List<PlayerBadge>>((ref) => ref.watch(engagementRepositoryProvider).myBadges());
