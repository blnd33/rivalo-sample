import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../teams/data/teams.dart';
import '../data/safety.dart';

/// Players you blocked. They are not told. Unblocking makes you visible to each other again.
class BlockedPlayersScreen extends ConsumerWidget {
  const BlockedPlayersScreen({super.key});

  Future<void> _unblock(BuildContext context, WidgetRef ref, UserSummary u) async {
    try {
      await ref.read(safetyRepositoryProvider).unblock(u.username);
      ref.invalidate(blockedPlayersProvider);
      if (context.mounted) showToast(context, context.l10n.unblockedToast(u.name));
    } on ApiException catch (e) {
      if (context.mounted) showToast(context, errorMessage(context, e));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return RDetailPage(
      title: l10n.blockedTitle,
      child: RAsyncView<List<UserSummary>>(
        value: ref.watch(blockedPlayersProvider),
        onRetry: () => ref.invalidate(blockedPlayersProvider),
        data: (users) => users.isEmpty
            ? REmptyState(icon: CupertinoIcons.hand_raised, title: l10n.blockedEmptyTitle, message: l10n.blockedExplainer)
            : RGroupedSection(
                footer: l10n.blockedExplainer,
                children: [
                  for (final u in users)
                    RListRow(
                      title: u.name,
                      subtitle: '@${u.username}',
                      leading: RAvatar(name: u.name, imageUrl: u.avatarUrl, size: RSize.avatarSm),
                      showChevron: false,
                      trailing: TextButton(
                        onPressed: () => _unblock(context, ref, u),
                        child: Text(l10n.unblockButton, style: const TextStyle(color: RColors.accent)),
                      ),
                    ),
                ],
              ),
      ),
    );
  }
}
