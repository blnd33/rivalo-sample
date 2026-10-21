import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../catalog/data/catalog.dart';
import '../../teams/data/teams.dart';
import '../../competition/data/competition.dart';
import '../../engagement/data/engagement.dart';
import '../../engagement/widgets/engagement_widgets.dart';
import '../../tournaments/data/tournaments_repository.dart';
import 'guest_profile_view.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    if (ref.watch(isGuestProvider)) return const GuestProfileView();
    final me = ref.watch(meProvider);
    if (me == null) return const SizedBox.shrink();

    final sports = ref.watch(sportsProvider).value ?? const <Sport>[];
    final myDisciplines = [
      for (final s in sports)
        for (final d in s.disciplines)
          if (me.disciplineIds.contains(d.id)) (sport: s, discipline: d),
    ];
    final teams = ref.watch(myTeamsProvider);
    final activeEntries = ref.watch(myRegistrationsProvider).value?.where((e) => e.isActive).length;
    final points = ref.watch(myPointsProvider).value?.total;

    return RLargeTitlePage(
      title: l10n.profileTitle,
      onRefresh: () async {
        ref.invalidate(myTeamsProvider);
        ref.invalidate(myRegistrationsProvider);
        ref.invalidate(myPointsProvider);
        ref.invalidate(myBadgesProvider);
        await ref.read(sessionProvider.notifier).refreshMe().catchError((_) {});
      },
      trailing: RIconButton(icon: CupertinoIcons.gear, semanticLabel: l10n.profileSettings, onPressed: () => context.push(Routes.settings)),
      slivers: [
        SliverList.list(children: [
          RFadeIn(
            child: _ProfileHeader(
              name: me.name,
              username: me.username,
              city: me.city,
              avatarUrl: me.avatarUrl,
              seed: me.id,
              bannerSport: myDisciplines.isEmpty ? null : myDisciplines.first.sport.icon,
              stats: [
                (value: teams.value?.length.toString() ?? '–', label: l10n.profileStatTeams),
                (value: '${myDisciplines.length}', label: l10n.profileStatDisciplines),
                (value: activeEntries?.toString() ?? '–', label: l10n.profileStatEntries),
                // Real total from approved results; a dash while loading, never a guess.
                (value: points?.toString() ?? '–', label: l10n.profileStatPoints),
              ],
            ),
          ),
          const SizedBox(height: RSpace.md),
          Row(children: [
            Expanded(child: RButton.secondary(label: l10n.profileEdit, compact: true, onPressed: () => context.push(Routes.editProfile))),
            const SizedBox(width: RSpace.sm),
            Expanded(child: RButton.secondary(label: l10n.profileViewPublic, compact: true, onPressed: () => context.push(Routes.player(me.username)))),
          ]),

          RSectionHeader(l10n.profileSports, trailing: RButton.tertiary(label: l10n.actionEdit, onPressed: () => context.push(Routes.profileSports))),
          if (myDisciplines.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: RSpace.xs),
              child: Text(l10n.profileNoSports, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
            )
          else
            Wrap(
              spacing: RSpace.sm,
              runSpacing: RSpace.sm,
              children: [
                for (final item in myDisciplines)
                  Chip(
                    avatar: SportGlyph(item.sport.icon, size: 16, color: RColors.accent),
                    label: Text(item.discipline.name),
                    backgroundColor: RColors.surface,
                    side: BorderSide(color: RColors.accent.withValues(alpha: 0.35)),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(RRadius.pill)),
                    labelStyle: RType.footnote.copyWith(fontWeight: FontWeight.w600),
                  ),
              ],
            ),

          RSectionHeader(l10n.profileTeams),
          RAsyncView<List<Team>>(
            value: teams,
            onRetry: () => ref.invalidate(myTeamsProvider),
            loading: RLoadingList(count: 1, semanticLabel: l10n.loading),
            data: (list) => list.isEmpty
                ? Padding(
                    padding: const EdgeInsets.symmetric(horizontal: RSpace.xs),
                    child: Text(l10n.teamsEmptyBody, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
                  )
                : RGroupedSection(children: [
                    for (final t in list)
                      RListRow(
                        title: t.name,
                        subtitle: t.sportName,
                        leading: RAvatar(name: t.name, imageUrl: t.logoUrl, size: RSize.avatarSm),
                        onTap: () => context.push(Routes.team(t.slug)),
                      ),
                  ]),
          ),

          RSectionHeader(l10n.profilePoints),
          RGroupedSection(children: [
            RListRow(
              title: l10n.pointsHistoryTitle,
              leading: const Icon(CupertinoIcons.chart_bar_fill),
              value: points?.toString(),
              onTap: () => context.push(Routes.pointsHistory),
            ),
          ]),
          if (ref.watch(appConfigProvider).value?.storeEnabled ?? false)
            RGroupedSection(children: [
              RListRow(title: l10n.ordersTitle, leading: const Icon(CupertinoIcons.bag), onTap: () => context.push(Routes.orders)),
              if (ref.watch(appConfigProvider).value?.marketplaceEnabled ?? false)
                RListRow(title: l10n.sellTitle, leading: const Icon(CupertinoIcons.building_2_fill), onTap: () => context.push(Routes.selling)),
            ]),
          RSectionHeader(l10n.profileBadges, trailing: RButton.tertiary(label: l10n.actionSeeAll, onPressed: () => context.push(Routes.badges))),
          RAsyncView<List<PlayerBadge>>(
            value: ref.watch(myBadgesProvider),
            onRetry: () => ref.invalidate(myBadgesProvider),
            loading: RLoadingList(count: 1, semanticLabel: l10n.loading),
            data: (badges) => badges.any((b) => b.earned)
                ? BadgeGrid(badges: badges.where((b) => b.earned).take(6).toList(), showLocked: false)
                : RNotice(message: l10n.profileBadgesEmpty, icon: CupertinoIcons.rosette, color: RColors.textSecondary),
          ),
        ]),
      ],
    );
  }
}

/// Banner (sport artwork) with the avatar overlapping it, identity, and a row of real counts.
class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({
    required this.name,
    required this.username,
    required this.city,
    required this.avatarUrl,
    required this.seed,
    required this.bannerSport,
    required this.stats,
  });

  final String name;
  final String username;
  final String? city;
  final String? avatarUrl;
  final int seed;
  final String? bannerSport;
  final List<({String value, String label})> stats;

  @override
  Widget build(BuildContext context) {
    return RCard(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              SizedBox(height: 104, child: RCover(imageUrl: null, sportIcon: bannerSport, seed: seed)),
              PositionedDirectional(
                start: RSpace.lg,
                bottom: -40,
                child: Container(
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(color: RColors.surface, shape: BoxShape.circle),
                  child: RAvatar(name: name, imageUrl: avatarUrl, size: RSize.avatarLg, ring: true),
                ),
              ),
            ],
          ),
          const SizedBox(height: 40), // room for the overlapping avatar
          Padding(
            padding: const EdgeInsets.fromLTRB(RSpace.lg, RSpace.md, RSpace.lg, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: RType.title2, maxLines: 2, overflow: TextOverflow.ellipsis),
                const SizedBox(height: RSpace.xxs),
                Text('@$username', style: RType.subheadline.copyWith(color: RColors.textSecondary)),
                if (city != null) ...[
                  const SizedBox(height: RSpace.xxs),
                  Row(children: [
                    const Icon(CupertinoIcons.location_solid, size: 14, color: RColors.textTertiary),
                    const SizedBox(width: RSpace.xs),
                    Flexible(child: Text(city!, style: RType.footnote.copyWith(color: RColors.textTertiary))),
                  ]),
                ],
              ],
            ),
          ),
          const SizedBox(height: RSpace.lg),
          Container(
            decoration: const BoxDecoration(border: Border(top: BorderSide(color: RColors.border, width: 0.5))),
            padding: const EdgeInsets.symmetric(vertical: RSpace.md),
            child: Row(
              children: [
                for (final (i, s) in stats.indexed) ...[
                  if (i > 0) Container(width: 0.5, height: 32, color: RColors.border),
                  Expanded(
                    child: MergeSemantics(
                      child: Column(children: [
                        Text(s.value, style: RType.title3.copyWith(color: RColors.accent)),
                        const SizedBox(height: RSpace.xxs),
                        Text(s.label, style: RType.caption1.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center, maxLines: 2),
                      ]),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
