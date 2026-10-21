import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../auth/widgets/account_required.dart';
import '../data/safety.dart';
import 'report_sheet.dart';

/// "More" button for another player's profile or a team page: Report, and Block/Unblock for
/// players. Guests are asked to create an account first.
class SafetyMenuButton extends ConsumerWidget {
  const SafetyMenuButton({super.key, required this.target, this.blocked, this.onChanged});

  final ReportTarget target;

  /// Players only: whether the signed-in viewer has blocked them (null for teams).
  final bool? blocked;
  final VoidCallback? onChanged;

  Future<void> _open(BuildContext context, WidgetRef ref) async {
    final l10n = context.l10n;
    if (ref.read(isGuestProvider)) {
      await showAccountRequired(context, reason: l10n.reportAccountRequired);
      return;
    }
    final player = target is ReportPlayer ? target as ReportPlayer : null;
    final choice = await showCupertinoModalPopup<String>(
      context: context,
      builder: (ctx) => CupertinoActionSheet(
        actions: [
          CupertinoActionSheetAction(
            isDestructiveAction: true,
            onPressed: () => Navigator.pop(ctx, 'report'),
            child: Text(player != null ? l10n.reportPlayerAction : l10n.reportTeamAction),
          ),
          if (player != null)
            CupertinoActionSheetAction(
              onPressed: () => Navigator.pop(ctx, blocked == true ? 'unblock' : 'block'),
              child: Text(blocked == true ? l10n.unblockAction(player.name) : l10n.blockAction(player.name)),
            ),
        ],
        cancelButton: CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx), child: Text(l10n.actionCancel)),
      ),
    );
    if (!context.mounted || choice == null) return;

    final repo = ref.read(safetyRepositoryProvider);
    try {
      switch (choice) {
        case 'report':
          await showReportSheet(context, target);
        case 'block':
          if (await confirm(context, title: l10n.blockConfirmTitle(player!.name), message: l10n.blockConfirmBody, confirmLabel: l10n.blockButton, destructive: true)) {
            await repo.block(player.username);
            ref.invalidate(blockedPlayersProvider);
            onChanged?.call();
            if (context.mounted) showToast(context, l10n.blockedToast(player.name));
          }
        case 'unblock':
          await repo.unblock(player!.username);
          ref.invalidate(blockedPlayersProvider);
          onChanged?.call();
          if (context.mounted) showToast(context, l10n.unblockedToast(player.name));
      }
    } on ApiException catch (e) {
      if (context.mounted) showToast(context, errorMessage(context, e));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return IconButton(
      tooltip: context.l10n.moreActions,
      icon: const Icon(CupertinoIcons.ellipsis_circle, color: RColors.accent),
      onPressed: () => _open(context, ref),
    );
  }
}
