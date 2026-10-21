import 'dart:async';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../safety/data/safety.dart';
import '../../safety/widgets/safety_actions.dart';
import '../data/teams.dart';

class TeamDetailScreen extends ConsumerWidget {
  const TeamDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final team = ref.watch(teamProvider(slug));

    final t = team.value;
    return RDetailPage(
      title: t?.name ?? l10n.competeMyTeams,
      actions: [
        // Members don't report their own team; they talk to their captain or leave.
        if (t != null && !t.isMember) SafetyMenuButton(target: ReportTeam(slug: t.slug, name: t.name)),
      ],
      child: RAsyncView<Team>(
        value: team,
        onRetry: () => ref.invalidate(teamProvider(slug)),
        data: (t) => _TeamBody(team: t),
      ),
    );
  }
}

class _TeamBody extends ConsumerStatefulWidget {
  const _TeamBody({required this.team});

  final Team team;

  @override
  ConsumerState<_TeamBody> createState() => _TeamBodyState();
}

class _TeamBodyState extends ConsumerState<_TeamBody> {
  bool _busy = false;

  TeamsRepository get _repo => ref.read(teamsRepositoryProvider);

  Future<void> _run(Future<void> Function() action, {String? success, bool popAfter = false}) async {
    setState(() => _busy = true);
    try {
      await action();
      ref.invalidate(teamProvider(widget.team.slug));
      ref.invalidate(myTeamsProvider);
      if (!mounted) return;
      if (success != null) showToast(context, success);
      if (popAfter) context.pop();
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _memberActions(TeamMember m) async {
    final l10n = context.l10n;
    final t = widget.team;
    final choice = await showCupertinoModalPopup<String>(
      context: context,
      builder: (ctx) => CupertinoActionSheet(
        title: Text(l10n.teamMemberActions(m.user.name)),
        actions: [
          CupertinoActionSheetAction(
            onPressed: () => Navigator.pop(ctx, 'role'),
            child: Text(m.role == 'substitute' ? l10n.teamMakeMember : l10n.teamMakeSub),
          ),
          CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx, 'captain'), child: Text(l10n.teamTransferCaptain)),
          CupertinoActionSheetAction(isDestructiveAction: true, onPressed: () => Navigator.pop(ctx, 'remove'), child: Text(l10n.teamRemoveMember)),
        ],
        cancelButton: CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx), child: Text(l10n.actionCancel)),
      ),
    );
    if (!mounted || choice == null) return;

    switch (choice) {
      case 'role':
        await _run(() => _repo.setMemberRole(t.slug, m.user.id, m.role == 'substitute' ? 'member' : 'substitute'));
      case 'captain':
        if (await confirm(context, title: l10n.teamTransferCaptain, message: l10n.teamTransferConfirm(m.user.name), confirmLabel: l10n.actionConfirm)) {
          await _run(() => _repo.nominateCaptain(t.slug, m.user.username), success: l10n.teamTransferPending(m.user.name));
        }
      case 'remove':
        if (mounted && await confirm(context, title: l10n.teamRemoveConfirm(m.user.name), confirmLabel: l10n.teamRemoveMember, destructive: true)) {
          await _run(() => _repo.removeMember(t.slug, m.user.id));
        }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final t = widget.team;
    final captainFirst = [...t.members]..sort((a, b) => (a.role == 'captain' ? 0 : 1).compareTo(b.role == 'captain' ? 0 : 1));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            RAvatar(name: t.name, imageUrl: t.logoUrl, size: RSize.avatarLg),
            const SizedBox(width: RSpace.lg),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Semantics(header: true, child: Text(t.name, style: RType.title2)),
                  const SizedBox(height: RSpace.xxs),
                  Text(t.sportName, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
                ],
              ),
            ),
          ],
        ),
        if (t.pendingTransferTo != null) ...[
          const SizedBox(height: RSpace.lg),
          RNotice(message: l10n.teamTransferPending(t.pendingTransferTo!.name), icon: CupertinoIcons.hourglass),
        ],
        RGroupedSection(
          header: t.teamMemberLimit == null ? l10n.teamMembersHeader : '${l10n.teamMembersHeader} · ${l10n.teamMembersCount(t.members.length, t.teamMemberLimit!)}',
          footer: l10n.teamRosterHistoryNote,
          children: [
            for (final m in captainFirst)
              RListRow(
                title: m.user.name,
                subtitle: '@${m.user.username}',
                leading: RAvatar(name: m.user.name, imageUrl: m.user.avatarUrl, size: RSize.avatarSm),
                trailing: RStatusPill(label: teamRoleLabel(context, m.role), tone: m.role == 'captain' ? RTone.accent : RTone.neutral, icon: m.role == 'captain' ? CupertinoIcons.star_fill : CupertinoIcons.person_fill),
                showChevron: false,
                onTap: t.isCaptain && m.role != 'captain' && !_busy ? () => _memberActions(m) : () => context.push(Routes.player(m.user.username)),
              ),
          ],
        ),
        if (t.isCaptain && t.pendingInvites.isNotEmpty)
          RGroupedSection(
            header: l10n.teamInvitesHeader,
            children: [
              for (final inv in t.pendingInvites)
                RListRow(
                  title: inv.user.name,
                  subtitle: '@${inv.user.username} · ${teamRoleLabel(context, inv.role)}',
                  leading: RAvatar(name: inv.user.name, imageUrl: inv.user.avatarUrl, size: RSize.avatarSm),
                  trailing: RButton.tertiary(label: l10n.teamRevoke, onPressed: _busy ? null : () => _run(() => _repo.revokeInvite(t.slug, inv.id))),
                ),
            ],
          ),
        const SizedBox(height: RSpace.xxl),
        if (t.isCaptain) ...[
          if (t.isFull)
            RNotice(message: l10n.teamFull, icon: CupertinoIcons.person_crop_circle_badge_xmark, color: RColors.warning)
          else
            RButton(
              label: l10n.teamInvite,
              icon: CupertinoIcons.person_add,
              onPressed: () async {
                final sent = await showModalBottomSheet<bool>(
                  context: context,
                  isScrollControlled: true,
                  useSafeArea: true,
                  builder: (_) => _InviteSheet(teamSlug: t.slug),
                );
                if (sent == true) ref.invalidate(teamProvider(t.slug));
              },
            ),
          const SizedBox(height: RSpace.md),
          RButton.destructive(
            label: l10n.teamDisband,
            loading: _busy,
            onPressed: () async {
              if (await confirm(context, title: l10n.teamDisband, message: l10n.teamDisbandConfirm(t.name), confirmLabel: l10n.teamDisband, destructive: true)) {
                await _run(() => _repo.disband(t.slug), popAfter: true);
              }
            },
          ),
        ] else if (t.isMember)
          RButton.destructive(
            label: l10n.teamLeave,
            loading: _busy,
            onPressed: () async {
              if (await confirm(context, title: l10n.teamLeave, message: l10n.teamLeaveConfirm(t.name), confirmLabel: l10n.teamLeave, destructive: true)) {
                await _run(() => _repo.leave(t.slug), popAfter: true);
              }
            },
          ),
      ],
    );
  }
}

/// Search by username and invite as member or substitute.
class _InviteSheet extends ConsumerStatefulWidget {
  const _InviteSheet({required this.teamSlug});

  final String teamSlug;

  @override
  ConsumerState<_InviteSheet> createState() => _InviteSheetState();
}

class _InviteSheetState extends ConsumerState<_InviteSheet> {
  final _query = TextEditingController();
  Timer? _debounce;
  List<UserSummary> _results = const [];
  bool _searching = false;
  String _role = 'member';
  String? _sendingTo;

  @override
  void dispose() {
    _debounce?.cancel();
    _query.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      final q = value.trim();
      if (q.length < 2) {
        setState(() => _results = const []);
        return;
      }
      setState(() => _searching = true);
      try {
        final r = await ref.read(teamsRepositoryProvider).searchUsers(q);
        if (mounted && _query.text.trim() == q) setState(() => _results = r);
      } catch (_) {
        // Keep previous results; the next keystroke retries.
      } finally {
        if (mounted) setState(() => _searching = false);
      }
    });
  }

  Future<void> _invite(UserSummary user) async {
    setState(() => _sendingTo = user.username);
    try {
      await ref.read(teamsRepositoryProvider).invite(widget.teamSlug, user.username, role: _role);
      if (!mounted) return;
      showToast(context, context.l10n.teamInviteSent(user.username));
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _sendingTo = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Padding(
      padding: EdgeInsets.fromLTRB(RSpace.lg, 0, RSpace.lg, MediaQuery.viewInsetsOf(context).bottom + RSpace.lg),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Semantics(header: true, child: Text(l10n.teamInviteTitle, style: RType.title3)),
          const SizedBox(height: RSpace.lg),
          RTextField(
            label: l10n.teamInviteSearchLabel,
            hint: l10n.teamInviteSearchHint,
            controller: _query,
            onChanged: _onChanged,
            autofocus: true,
            autocorrect: false,
            suffix: _searching ? const Padding(padding: EdgeInsets.all(14), child: SizedBox.square(dimension: 16, child: CircularProgressIndicator.adaptive(strokeWidth: 2))) : null,
          ),
          const SizedBox(height: RSpace.md),
          Text(l10n.teamInviteAs, style: RType.footnote.copyWith(color: RColors.textSecondary)),
          const SizedBox(height: RSpace.xs),
          CupertinoSlidingSegmentedControl<String>(
            groupValue: _role,
            backgroundColor: RColors.surfaceSunken,
            thumbColor: RColors.surfaceRaised,
            children: {
              'member': Padding(padding: const EdgeInsets.all(RSpace.sm), child: Text(l10n.roleMember)),
              'substitute': Padding(padding: const EdgeInsets.all(RSpace.sm), child: Text(l10n.roleSubstitute)),
            },
            onValueChanged: (v) => setState(() => _role = v ?? 'member'),
          ),
          const SizedBox(height: RSpace.md),
          ConstrainedBox(
            constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.4),
            child: _results.isEmpty
                ? Padding(
                    padding: const EdgeInsets.symmetric(vertical: RSpace.lg),
                    child: Text(_query.text.trim().length >= 2 && !_searching ? l10n.teamInviteNoResults : '', style: RType.footnote.copyWith(color: RColors.textSecondary)),
                  )
                : ListView(
                    shrinkWrap: true,
                    children: [
                      for (final u in _results)
                        RListRow(
                          title: u.name,
                          subtitle: '@${u.username}',
                          leading: RAvatar(name: u.name, imageUrl: u.avatarUrl, size: RSize.avatarSm),
                          trailing: _sendingTo == u.username
                              ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator.adaptive(strokeWidth: 2))
                              : const Icon(CupertinoIcons.paperplane, color: RColors.accent),
                          showChevron: false,
                          onTap: _sendingTo == null ? () => _invite(u) : null,
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}
