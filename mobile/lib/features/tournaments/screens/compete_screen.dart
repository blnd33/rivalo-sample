import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/providers.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../auth/widgets/account_required.dart';
import '../../catalog/data/catalog.dart';
import '../../teams/data/teams.dart';
import '../../teams/widgets/team_tile.dart';
import '../data/tournament_models.dart';
import '../data/tournaments_repository.dart';
import '../widgets/tournament_card.dart';

enum _Segment { tournaments, teams }

/// Compete tab: discover tournaments (filter by sport) and manage my teams.
class CompeteScreen extends ConsumerStatefulWidget {
  const CompeteScreen({super.key, this.initialSegment, this.initialSportId});

  final String? initialSegment;
  final int? initialSportId;

  @override
  ConsumerState<CompeteScreen> createState() => _CompeteScreenState();
}

class _CompeteScreenState extends ConsumerState<CompeteScreen> {
  late _Segment _segment = widget.initialSegment == 'teams' ? _Segment.teams : _Segment.tournaments;
  late int? _sportId = widget.initialSportId;

  TournamentQuery get _query => (scope: 'upcoming', sportId: _sportId);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final online = ref.watch(connectivityProvider).value ?? true;

    return RLargeTitlePage(
      title: l10n.competeTitle,
      onRefresh: () async {
        ref.invalidate(tournamentListProvider(_query));
        ref.invalidate(myTeamsProvider);
      },
      trailing: _segment == _Segment.teams
          ? RIconButton(
              icon: CupertinoIcons.add,
              semanticLabel: l10n.teamsCreate,
              onPressed: () => ref.read(isGuestProvider)
                  ? showAccountRequired(context, reason: l10n.accountRequiredTeams)
                  : context.push(Routes.createTeam),
            )
          : null,
      slivers: [
        SliverToBoxAdapter(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (!online) ...[ROfflineBanner(message: l10n.offlineBanner), const SizedBox(height: RSpace.md)],
              SizedBox(
                width: double.infinity,
                child: CupertinoSlidingSegmentedControl<_Segment>(
                  groupValue: _segment,
                  backgroundColor: RColors.surface,
                  thumbColor: RColors.surfaceRaised,
                  padding: const EdgeInsets.all(3),
                  children: {
                    _Segment.tournaments: _SegmentLabel(l10n.competeTournaments),
                    _Segment.teams: _SegmentLabel(l10n.competeMyTeams),
                  },
                  onValueChanged: (v) {
                    if (v == null) return;
                    HapticFeedback.selectionClick();
                    setState(() => _segment = v);
                  },
                ),
              ),
              const SizedBox(height: RSpace.lg),
            ],
          ),
        ),
        if (_segment == _Segment.tournaments) ...[
          SliverToBoxAdapter(child: _SportFilter(selected: _sportId, onChanged: (id) => setState(() => _sportId = id))),
          const SliverToBoxAdapter(child: SizedBox(height: RSpace.lg)),
          SliverToBoxAdapter(child: _TournamentList(query: _query)),
        ] else
          const SliverToBoxAdapter(child: _MyTeams()),
      ],
    );
  }
}

class _SegmentLabel extends StatelessWidget {
  const _SegmentLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: RSpace.sm),
        child: Text(text, style: RType.subheadline.copyWith(fontWeight: FontWeight.w600)),
      );
}

class _SportFilter extends ConsumerWidget {
  const _SportFilter({required this.selected, required this.onChanged});

  final int? selected;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sports = ref.watch(sportsProvider).value ?? const <Sport>[];
    return SizedBox(
      height: RSize.minTouchTarget,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _FilterChip(label: context.l10n.filterAll, selected: selected == null, onTap: () => onChanged(null)),
          for (final s in sports) _FilterChip(label: s.name, icon: s.icon, selected: selected == s.id, onTap: () => onChanged(s.id)),
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.selected, required this.onTap, this.icon});

  final String label;
  final String? icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final fg = selected ? RColors.onAccent : RColors.textPrimary;
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: RSpace.sm),
      child: Semantics(
        selected: selected,
        button: true,
        label: label,
        excludeSemantics: true,
        child: RPressable(
          onPressed: () {
            HapticFeedback.selectionClick();
            onTap();
          },
          isButton: false,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: RSpace.md),
            decoration: BoxDecoration(
              color: selected ? RColors.accent : RColors.surface,
              borderRadius: BorderRadius.circular(RRadius.pill),
              border: Border.all(color: selected ? RColors.accent : RColors.border),
            ),
            child: Row(
              children: [
                if (icon != null) ...[SportGlyph(icon, size: 18, color: fg), const SizedBox(width: RSpace.xs + 2)],
                Text(label, style: RType.subheadline.copyWith(color: fg, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TournamentList extends ConsumerWidget {
  const _TournamentList({required this.query});

  final TournamentQuery query;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return RAsyncView<List<Tournament>>(
      value: ref.watch(tournamentListProvider(query)),
      onRetry: () => ref.invalidate(tournamentListProvider(query)),
      data: (list) => list.isEmpty
          ? REmptyState(icon: CupertinoIcons.flag, title: l10n.tournamentsEmptyTitle, message: l10n.tournamentsEmptyBody)
          : Column(children: [for (final (i, t) in list.indexed) RFadeIn(index: i, child: TournamentCard(tournament: t))]),
    );
  }
}

class _MyTeams extends ConsumerWidget {
  const _MyTeams();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    if (ref.watch(isGuestProvider)) {
      // Guests have no teams; explain what an account unlocks instead of calling the API.
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          REmptyState(icon: CupertinoIcons.person_3, title: l10n.teamsEmptyTitle, message: l10n.accountRequiredTeams),
          const GuestInviteCard(showBenefits: false),
        ],
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        RCard(
          onTap: () => context.push(Routes.invitations),
          child: Row(children: [
            const Icon(CupertinoIcons.tray, color: RColors.accent),
            const SizedBox(width: RSpace.md),
            Expanded(child: Text(l10n.inboxTitle, style: RType.headline)),
            const Icon(CupertinoIcons.chevron_forward, size: 17, color: RColors.textTertiary),
          ]),
        ),
        const SizedBox(height: RSpace.lg),
        RAsyncView<List<Team>>(
          value: ref.watch(myTeamsProvider),
          onRetry: () => ref.invalidate(myTeamsProvider),
          data: (teams) => teams.isEmpty
              ? REmptyState(
                  icon: CupertinoIcons.person_3,
                  title: l10n.teamsEmptyTitle,
                  message: l10n.teamsEmptyBody,
                  actionLabel: l10n.teamsCreate,
                  onAction: () => context.push(Routes.createTeam),
                )
              : Column(children: [for (final t in teams) TeamTile(team: t)]),
        ),
      ],
    );
  }
}
