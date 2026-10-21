import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../teams/data/teams.dart';
import '../data/tournament_models.dart';
import '../data/tournaments_repository.dart';

/// Registration flow, presented modally. Individual: one confirmation.
/// Pair/team: pick a team you captain, then choose starters and substitutes from accepted members.
class RegisterEntryScreen extends ConsumerStatefulWidget {
  const RegisterEntryScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<RegisterEntryScreen> createState() => _RegisterEntryScreenState();
}

class _RegisterEntryScreenState extends ConsumerState<RegisterEntryScreen> {
  Team? _team;
  final Set<int> _players = {};
  final Set<int> _subs = {};
  bool _busy = false;
  String? _error;

  Future<void> _submit(Tournament t) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final entry = await ref.read(tournamentsRepositoryProvider).register(
            t.slug,
            teamId: _team?.id,
            playerIds: _players.toList(),
            substituteIds: _subs.toList(),
          );
      HapticFeedback.mediumImpact();
      ref.invalidate(myEntryProvider(t.slug));
      ref.invalidate(tournamentDetailProvider(t.slug));
      ref.invalidate(myRegistrationsProvider);
      if (!mounted) return;
      showToast(context, entry.registrationStatus == 'waitlisted' ? context.l10n.registerSuccessWaitlisted : context.l10n.registerSuccessReserved);
      context.pop();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  bool _canSubmit(Tournament t) {
    if (!t.isTeamBased) return true;
    return _team != null && _players.length >= t.rosterMin && _players.length <= t.rosterMax && _subs.length <= t.substitutesMax;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final detail = ref.watch(tournamentDetailProvider(widget.slug));

    return detail.when(
      loading: () => RDetailPage(title: l10n.registerTitle, child: RLoadingList(count: 2, semanticLabel: l10n.loading)),
      error: (e, _) => RDetailPage(
        title: l10n.registerTitle,
        child: RErrorState(title: l10n.errorGenericTitle, message: errorMessage(context, e), retryLabel: l10n.actionRetry, onRetry: () => ref.invalidate(tournamentDetailProvider(widget.slug))),
      ),
      data: (t) => RDetailPage(
        title: l10n.registerTitle,
        bottomBar: RButton(
          label: t.isFull ? l10n.detailJoinWaitlist : l10n.registerConfirm,
          onPressed: _canSubmit(t) ? () => _submit(t) : null,
          loading: _busy,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(t.name, style: RType.title2),
            const SizedBox(height: RSpace.xs),
            Text('${t.disciplineName} · ${formatDateTime(context, t.startsAt)}', style: RType.subheadline.copyWith(color: RColors.textSecondary)),
            const SizedBox(height: RSpace.lg),
            if (_error != null) ...[
              RNotice(message: _error!, icon: CupertinoIcons.exclamationmark_circle, color: RColors.error),
              const SizedBox(height: RSpace.lg),
            ],
            if (t.isPaid) ...[RNotice(message: l10n.detailPaymentNotice, icon: CupertinoIcons.money_dollar_circle), const SizedBox(height: RSpace.lg)],
            if (!t.isTeamBased)
              RCard(child: Text(l10n.registerIndividualBody(t.name), style: RType.body))
            else
              _TeamPicker(
                tournament: t,
                selected: _team,
                players: _players,
                subs: _subs,
                onTeam: (team) => setState(() {
                  _team = team;
                  _players.clear();
                  _subs.clear();
                }),
                onToggle: (id, asSub) => setState(() {
                  HapticFeedback.selectionClick();
                  final target = asSub ? _subs : _players;
                  final other = asSub ? _players : _subs;
                  other.remove(id);
                  target.contains(id) ? target.remove(id) : target.add(id);
                }),
              ),
          ],
        ),
      ),
    );
  }
}

class _TeamPicker extends ConsumerWidget {
  const _TeamPicker({required this.tournament, required this.selected, required this.players, required this.subs, required this.onTeam, required this.onToggle});

  final Tournament tournament;
  final Team? selected;
  final Set<int> players;
  final Set<int> subs;
  final ValueChanged<Team> onTeam;
  final void Function(int userId, bool asSub) onToggle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final t = tournament;

    return RAsyncView<List<Team>>(
      value: ref.watch(myTeamsProvider),
      onRetry: () => ref.invalidate(myTeamsProvider),
      data: (teams) {
        final eligible = teams.where((team) => team.isCaptain && team.sportId == t.sportId).toList();
        if (eligible.isEmpty) {
          return REmptyState(
            icon: CupertinoIcons.person_3,
            title: l10n.registerNoTeamsTitle,
            message: l10n.registerNoTeamsBody(t.sportName),
            actionLabel: l10n.registerCreateTeam,
            onAction: () => context.push('${Routes.createTeam}?sport=${t.sportId}'),
          );
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            RGroupedSection(
              header: l10n.registerChooseTeam,
              children: [
                for (final team in eligible)
                  RListRow(
                    title: team.name,
                    subtitle: l10n.teamMembersCount(team.members.length, team.teamMemberLimit ?? team.members.length),
                    leading: RAvatar(name: team.name, imageUrl: team.logoUrl, size: RSize.avatarSm),
                    trailing: selected?.id == team.id ? const Icon(CupertinoIcons.checkmark_alt, color: RColors.accent) : null,
                    showChevron: false,
                    onTap: () => onTeam(team),
                  ),
              ],
            ),
            if (selected != null) ...[
              RGroupedSection(
                header: '${l10n.registerChoosePlayers} · ${t.rosterMin == t.rosterMax ? l10n.registerPlayersExact(t.rosterMax) : l10n.registerPlayersRange(t.rosterMin, t.rosterMax)}',
                footer: '${l10n.registerSelectedCount(players.length)} · ${l10n.registerOnlyAccepted}',
                children: [
                  for (final m in selected!.members)
                    _MemberCheckRow(member: m, checked: players.contains(m.user.id), onTap: () => onToggle(m.user.id, false)),
                ],
              ),
              if (t.substitutesMax > 0)
                RGroupedSection(
                  header: l10n.registerSubstitutes(t.substitutesMax),
                  footer: l10n.registerSelectedCount(subs.length),
                  children: [
                    for (final m in selected!.members.where((m) => !players.contains(m.user.id)))
                      _MemberCheckRow(member: m, checked: subs.contains(m.user.id), onTap: () => onToggle(m.user.id, true)),
                  ],
                ),
              const SizedBox(height: RSpace.md),
              Text(l10n.teamRosterHistoryNote, style: RType.footnote.copyWith(color: RColors.textSecondary)),
            ],
          ],
        );
      },
    );
  }
}

class _MemberCheckRow extends StatelessWidget {
  const _MemberCheckRow({required this.member, required this.checked, required this.onTap});

  final TeamMember member;
  final bool checked;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      checked: checked,
      child: RListRow(
        title: member.user.name,
        subtitle: '@${member.user.username} · ${teamRoleLabel(context, member.role)}',
        leading: RAvatar(name: member.user.name, imageUrl: member.user.avatarUrl, size: RSize.avatarSm),
        trailing: Icon(checked ? CupertinoIcons.checkmark_circle_fill : CupertinoIcons.circle, color: checked ? RColors.accent : RColors.textTertiary),
        showChevron: false,
        onTap: onTap,
      ),
    );
  }
}
