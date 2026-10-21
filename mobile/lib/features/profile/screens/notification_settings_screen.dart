import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../catalog/data/catalog.dart';
import '../data/profile_repository.dart';

/// Preferences are stored now; push delivery is wired in Milestone 6.
class NotificationSettingsScreen extends ConsumerStatefulWidget {
  const NotificationSettingsScreen({super.key});

  @override
  ConsumerState<NotificationSettingsScreen> createState() => _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState extends ConsumerState<NotificationSettingsScreen> {
  late Map<String, bool> _values = {...?ref.read(meProvider)?.notificationPreferences};

  Future<void> _set(String key, bool value) async {
    final previous = _values[key] ?? false;
    setState(() => _values = {..._values, key: value});
    try {
      final me = await ref.read(profileRepositoryProvider).setNotificationPreferences({key: value});
      ref.read(sessionProvider.notifier).updateMe(me);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _values = {..._values, key: previous});
      showToast(context, errorMessage(context, e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final items = [
      ('new_tournaments', l10n.notifNewTournaments),
      ('team_invitations', l10n.notifTeamInvitations),
      ('registration_updates', l10n.notifRegistration),
      ('check_in_reminders', l10n.notifCheckIn),
      ('match_reminders', l10n.notifMatch),
      ('venue_changes', l10n.notifVenue),
      ('results', l10n.notifResults),
      ('points_and_achievements', l10n.notifPoints),
      ('news', l10n.notifNews),
      if (ref.watch(appConfigProvider).value?.storeEnabled ?? false) ('orders', l10n.notifOrders),
    ];

    return RDetailPage(
      title: l10n.notificationsTitle,
      child: RGroupedSection(
        footer: l10n.notificationsFooter,
        children: [
          for (final (key, title) in items) RSwitchRow(title: title, value: _values[key] ?? false, onChanged: (v) => _set(key, v)),
        ],
      ),
    );
  }
}
