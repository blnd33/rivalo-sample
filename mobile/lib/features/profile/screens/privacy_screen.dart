import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../data/profile_repository.dart';

/// Toggles save immediately (optimistic), reverting on failure.
class PrivacyScreen extends ConsumerStatefulWidget {
  const PrivacyScreen({super.key});

  @override
  ConsumerState<PrivacyScreen> createState() => _PrivacyScreenState();
}

class _PrivacyScreenState extends ConsumerState<PrivacyScreen> {
  late Map<String, bool> _values = {...?ref.read(meProvider)?.privacy};

  Future<void> _set(String key, bool value) async {
    final previous = _values[key] ?? false;
    setState(() => _values = {..._values, key: value});
    try {
      final me = await ref.read(profileRepositoryProvider).setPrivacy({key: value});
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
    Widget row(String key, String title, [String? subtitle]) =>
        RSwitchRow(title: title, subtitle: subtitle, value: _values[key] ?? false, onChanged: (v) => _set(key, v));

    return RDetailPage(
      title: l10n.privacyTitle,
      child: RGroupedSection(
        footer: l10n.privacyFooter,
        children: [
          row('show_display_name', l10n.privacyShowName, l10n.privacyShowNameSub),
          row('show_city', l10n.privacyShowCity),
          row('show_teams', l10n.privacyShowTeams),
          row('show_tournament_history', l10n.privacyShowHistory),
        ],
      ),
    );
  }
}
