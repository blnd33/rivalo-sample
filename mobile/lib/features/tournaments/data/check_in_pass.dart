import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';

/// A player's personal check-in pass for one tournament entry. The QR payload is a signed link
/// that only staff assigned to the tournament can open.
class CheckInPass {
  const CheckInPass({
    required this.entryId,
    required this.code,
    required this.qrPayload,
    required this.username,
    required this.entryName,
    required this.tournamentName,
    required this.checkInStatus,
    required this.paymentStatus,
    this.displayName,
    this.startsAt,
    this.checkInClosesAt,
    this.venueName,
    this.fromCache = false,
  });

  final int entryId;
  final String code;
  final String qrPayload;
  final String username;
  final String? displayName;
  final String entryName;
  final String tournamentName;
  final DateTime? startsAt;
  final DateTime? checkInClosesAt;
  final String? venueName;
  final String checkInStatus;
  final String paymentStatus;

  /// Shown from the device copy because the network was unavailable.
  final bool fromCache;

  factory CheckInPass.fromJson(Json j, {bool fromCache = false}) {
    final member = j.obj('member');
    final entry = j.obj('entry');
    final tournament = j.obj('tournament');
    return CheckInPass(
      entryId: entry.integer('id'),
      code: j.str('code'),
      qrPayload: j.str('qr_payload'),
      username: member.str('username'),
      displayName: member.strOrNull('display_name'),
      entryName: entry.str('display_name'),
      tournamentName: tournament.str('name'),
      startsAt: tournament.date('starts_at'),
      checkInClosesAt: tournament.date('check_in_closes_at'),
      venueName: tournament.strOrNull('venue_name'),
      checkInStatus: j.str('check_in_status'),
      paymentStatus: j.str('payment_status'),
      fromCache: fromCache,
    );
  }
}

/// Loads the pass from the API and keeps a copy on the device, so the QR code still shows at a
/// venue with poor signal. The code is not secret on its own: staff must be signed in (with MFA)
/// and assigned to the tournament to open it, and they compare the roster photos.
class CheckInPassRepository {
  CheckInPassRepository(this._ref, [FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  final Ref _ref;
  final FlutterSecureStorage _storage;

  String _key(int entryId) => 'rivalo.checkin.$entryId';

  Future<CheckInPass> load(int entryId) async {
    try {
      final json = await _ref.read(apiClientProvider).get('/registrations/$entryId/check-in-code');
      final data = json.obj('data');
      await _storage.write(key: _key(entryId), value: jsonEncode(data));
      return CheckInPass.fromJson(data);
    } on ApiException catch (e) {
      if (!e.isOffline) {
        // The server refused (e.g. entry cancelled): never show a stale copy.
        await _storage.delete(key: _key(entryId));
        rethrow;
      }
      final cached = await _storage.read(key: _key(entryId));
      if (cached == null) rethrow;
      return CheckInPass.fromJson(Map<String, dynamic>.from(jsonDecode(cached) as Map), fromCache: true);
    }
  }
}

final checkInPassRepositoryProvider = Provider((ref) => CheckInPassRepository(ref));

final checkInPassProvider = FutureProvider.autoDispose.family<CheckInPass, int>((ref, entryId) {
  return ref.watch(checkInPassRepositoryProvider).load(entryId);
});
