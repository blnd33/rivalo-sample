import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/teams.dart';

class TeamTile extends StatelessWidget {
  const TeamTile({super.key, required this.team});

  final Team team;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Padding(
      padding: const EdgeInsets.only(bottom: RSpace.md),
      child: RCard(
        onTap: () => context.push(Routes.team(team.slug)),
        child: Row(
          children: [
            RAvatar(name: team.name, imageUrl: team.logoUrl),
            const SizedBox(width: RSpace.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(team.name, style: RType.headline, maxLines: 1, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: RSpace.xxs),
                  Text(
                    team.teamMemberLimit == null
                        ? team.sportName
                        : '${team.sportName} · ${l10n.teamMembersCount(team.members.length, team.teamMemberLimit!)}',
                    style: RType.footnote.copyWith(color: RColors.textSecondary),
                  ),
                ],
              ),
            ),
            if (team.isCaptain) RStatusPill(label: l10n.roleCaptain, tone: RTone.accent),
          ],
        ),
      ),
    );
  }
}
