import '../../../core/utils/json.dart';

/// The signed-in user's own account (private fields included; never shown to others).
class Me {
  const Me({
    required this.id,
    required this.username,
    required this.email,
    required this.emailVerified,
    required this.locale,
    required this.status,
    required this.privacy,
    required this.notificationPreferences,
    required this.disciplineIds,
    required this.isStaff,
    this.displayName,
    this.avatarUrl,
    this.city,
    this.phone,
    this.deletionScheduledFor,
  });

  final int id;
  final String username;
  final String? displayName;
  final String email;
  final bool emailVerified;
  final String? avatarUrl;
  final String? city;
  final String? phone;
  final String locale;
  final String status;
  final Map<String, bool> privacy;
  final Map<String, bool> notificationPreferences;
  final List<int> disciplineIds;
  final bool isStaff;
  final DateTime? deletionScheduledFor;

  String get name => (displayName?.trim().isNotEmpty ?? false) ? displayName! : username;

  bool get deletionPending => deletionScheduledFor != null;

  factory Me.fromJson(Json json) => Me(
        id: json.integer('id'),
        username: json.str('username'),
        displayName: json.strOrNull('display_name'),
        email: json.str('email'),
        emailVerified: json.boolean('email_verified'),
        avatarUrl: json.strOrNull('avatar_url'),
        city: json.strOrNull('city'),
        phone: json.strOrNull('phone'),
        locale: json.str('locale', 'en'),
        status: json.str('status', 'active'),
        privacy: json.boolMap('privacy'),
        notificationPreferences: json.boolMap('notification_preferences'),
        disciplineIds: json.intList('discipline_ids'),
        isStaff: json.boolean('is_staff'),
        deletionScheduledFor: json.date('deletion_scheduled_for'),
      );
}
