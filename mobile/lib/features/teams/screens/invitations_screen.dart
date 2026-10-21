import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/teams.dart';

/// Inbox for team invitations and captaincy nominations addressed to me.
class InvitationsScreen extends ConsumerStatefulWidget {
  const InvitationsScreen({super.key});

  @override
  ConsumerState<InvitationsScreen> createState() => _InvitationsScreenState();
}

class _InvitationsScreenState extends ConsumerState<InvitationsScreen> {
  final Set<int> _busy = {};

  Future<void> _act(int key, Future<void> Function() action, {String? success}) async {
    setState(() => _busy.add(key));
    try {
      await action();
      ref.invalidate(inboxProvider);
      ref.invalidate(myTeamsProvider);
      if (mounted && success != null) showToast(context, success);
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy.remove(key));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final repo = ref.read(teamsRepositoryProvider);

    return RDetailPage(
      title: l10n.inboxTitle,
      child: RAsyncView<Inbox>(
        value: ref.watch(inboxProvider),
        onRetry: () => ref.invalidate(inboxProvider),
        data: (inbox) {
          if (inbox.count == 0) {
            return REmptyState(icon: CupertinoIcons.tray, title: l10n.inboxEmptyTitle, message: l10n.inboxEmptyBody);
          }
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final n in inbox.nominations)
                _InboxCard(
                  title: n.teamName,
                  body: l10n.inboxNominationBody(n.from.name, n.teamName),
                  busy: _busy.contains(-n.id),
                  acceptLabel: l10n.inboxBecomeCaptain,
                  declineLabel: l10n.inboxDecline,
                  onAccept: () => _act(-n.id, () => repo.respondNomination(n.id, accept: true)),
                  onDecline: () => _act(-n.id, () => repo.respondNomination(n.id, accept: false)),
                ),
              for (final i in inbox.invitations)
                _InboxCard(
                  title: i.teamName,
                  body: '${l10n.inboxInvitedBy(i.invitedBy.name, i.sportName)} · ${teamRoleLabel(context, i.role)}',
                  busy: _busy.contains(i.id),
                  acceptLabel: l10n.inboxAccept,
                  declineLabel: l10n.inboxDecline,
                  onAccept: () => _act(i.id, () => repo.accept(i.id), success: l10n.inboxJoined(i.teamName)),
                  onDecline: () => _act(i.id, () => repo.decline(i.id)),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _InboxCard extends StatelessWidget {
  const _InboxCard({
    required this.title,
    required this.body,
    required this.busy,
    required this.acceptLabel,
    required this.declineLabel,
    required this.onAccept,
    required this.onDecline,
  });

  final String title;
  final String body;
  final bool busy;
  final String acceptLabel;
  final String declineLabel;
  final VoidCallback onAccept;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: RSpace.md),
      child: RCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              RAvatar(name: title, size: RSize.avatarSm),
              const SizedBox(width: RSpace.md),
              Expanded(child: Text(title, style: RType.headline)),
            ]),
            const SizedBox(height: RSpace.sm),
            Text(body, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
            const SizedBox(height: RSpace.md),
            Row(children: [
              Expanded(child: RButton.secondary(label: declineLabel, onPressed: busy ? null : onDecline, compact: true)),
              const SizedBox(width: RSpace.sm),
              Expanded(child: RButton(label: acceptLabel, onPressed: onAccept, loading: busy, compact: true)),
            ]),
          ],
        ),
      ),
    );
  }
}
