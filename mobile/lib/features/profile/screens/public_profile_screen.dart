import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../engagement/widgets/engagement_widgets.dart';
import '../../safety/data/safety.dart';
import '../../safety/widgets/safety_actions.dart';
import '../data/profile_repository.dart';

/// Another player's public profile. Shows only what their privacy settings allow.
class PublicProfileScreen extends ConsumerWidget {
  const PublicProfileScreen({super.key, required this.username});

  final String username;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final profile = ref.watch(publicProfileProvider(username)).value;
    return RDetailPage(
      title: l10n.publicProfileTitle,
      actions: [
        if (profile != null && !profile.isSelf)
          SafetyMenuButton(
            target: ReportPlayer(username: profile.username, name: profile.displayName ?? profile.username),
            blocked: profile.blockedByMe,
            onChanged: () => ref.invalidate(publicProfileProvider(username)),
          ),
      ],
      child: RAsyncView<PublicProfile>(
        value: ref.watch(publicProfileProvider(username)),
        onRetry: () => ref.invalidate(publicProfileProvider(username)),
        data: (p) {
          final name = p.displayName ?? p.username;
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(child: RAvatar(name: name, imageUrl: p.avatarUrl, size: 96)),
              const SizedBox(height: RSpace.lg),
              Semantics(header: true, child: Text(name, style: RType.title1, textAlign: TextAlign.center)),
              Text('@${p.username}', style: RType.subheadline.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
              if (p.city != null) Text(p.city!, style: RType.footnote.copyWith(color: RColors.textTertiary), textAlign: TextAlign.center),
              if (p.blockedByMe) ...[
                const SizedBox(height: RSpace.md),
                RNotice(message: l10n.blockedNotice, icon: CupertinoIcons.hand_raised, color: RColors.textSecondary),
              ],
              if (p.memberSince != null) ...[
                const SizedBox(height: RSpace.xs),
                Text(l10n.profileMemberSince(p.memberSince!), style: RType.caption1.copyWith(color: RColors.textTertiary), textAlign: TextAlign.center),
              ],

              RSectionHeader(l10n.profileSports),
              if (p.disciplines.isEmpty)
                Text(l10n.profileNoSports, style: RType.subheadline.copyWith(color: RColors.textSecondary))
              else
                Wrap(spacing: RSpace.sm, runSpacing: RSpace.sm, children: [
                  for (final d in p.disciplines) RStatusPill(label: d, tone: RTone.neutral, icon: CupertinoIcons.sportscourt),
                ]),

              RSectionHeader(l10n.profileTeams),
              if (p.teams == null)
                Text(l10n.profileHidden, style: RType.subheadline.copyWith(color: RColors.textSecondary))
              else if (p.teams!.isEmpty)
                Text(l10n.teamsEmptyTitle, style: RType.subheadline.copyWith(color: RColors.textSecondary))
              else
                RGroupedSection(children: [
                  for (final t in p.teams!)
                    RListRow(
                      title: t.name,
                      subtitle: '${t.sport} · ${teamRoleLabel(context, t.role)}',
                      leading: RAvatar(name: t.name, imageUrl: t.logoUrl, size: RSize.avatarSm),
                      onTap: () => context.push(Routes.team(t.slug)),
                    ),
                ]),

              RSectionHeader(l10n.profileHistory),
              if (p.history == null)
                Text(l10n.profileHidden, style: RType.subheadline.copyWith(color: RColors.textSecondary))
              else if (p.history!.isEmpty)
                Text(l10n.profileHistoryEmpty, style: RType.subheadline.copyWith(color: RColors.textSecondary))
              else
                RGroupedSection(children: [
                  for (final h in p.history!)
                    RListRow(title: h.name, subtitle: '${h.discipline} · ${formatDate(context, h.endedAt)}', onTap: () => context.push(Routes.tournament(h.slug))),
                ]),

              RSectionHeader(l10n.profilePoints),
              if (p.points != null && p.points! > 0)
                RGroupedSection(children: [RListRow(title: l10n.pointsTotal, leading: const Icon(CupertinoIcons.chart_bar_fill), value: '${p.points}')])
              else
                RNotice(message: p.points == null ? l10n.profileHidden : l10n.profilePointsEmpty, icon: CupertinoIcons.chart_bar, color: RColors.textSecondary),
              RSectionHeader(l10n.profileBadges),
              if (p.badges != null && p.badges!.isNotEmpty)
                BadgeGrid(badges: p.badges!, showLocked: false)
              else
                RNotice(message: p.badges == null ? l10n.profileHidden : l10n.profileBadgesEmpty, icon: CupertinoIcons.rosette, color: RColors.textSecondary),
            ],
          );
        },
      ),
    );
  }
}
