import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../data/profile_repository.dart';

class DevicesScreen extends ConsumerWidget {
  const DevicesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return RDetailPage(
      title: l10n.devicesTitle,
      child: RAsyncView<List<DeviceSession>>(
        value: ref.watch(sessionsProvider),
        onRetry: () => ref.invalidate(sessionsProvider),
        data: (list) => RGroupedSection(
          children: [
            for (final s in list)
              RListRow(
                title: s.deviceName,
                subtitle: s.isCurrent ? l10n.deviceThis : (s.lastUsedAt == null ? l10n.deviceNeverUsed : l10n.deviceLastUsed(formatDateTime(context, s.lastUsedAt))),
                leading: Icon(s.isCurrent ? CupertinoIcons.device_phone_portrait : CupertinoIcons.device_laptop),
                showChevron: false,
                trailing: RButton.tertiary(
                  label: l10n.deviceSignOut,
                  onPressed: () async {
                    if (s.isCurrent) {
                      if (await confirm(context, title: l10n.settingsSignOut, message: l10n.signOutConfirm, confirmLabel: l10n.settingsSignOut)) {
                        await ref.read(sessionProvider.notifier).logout();
                      }
                      return;
                    }
                    try {
                      await ref.read(profileRepositoryProvider).revokeSession(s.id);
                      ref.invalidate(sessionsProvider);
                    } on ApiException catch (e) {
                      if (context.mounted) showToast(context, errorMessage(context, e));
                    }
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}
