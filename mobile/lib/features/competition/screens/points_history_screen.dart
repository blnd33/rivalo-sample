import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/competition.dart';

/// Every point I have, with where it came from. Corrections appear as their own negative rows.
class PointsHistoryScreen extends ConsumerWidget {
  const PointsHistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return RDetailPage(
      title: l10n.pointsHistoryTitle,
      child: RAsyncView<MyPoints>(
        value: ref.watch(myPointsProvider),
        onRetry: () => ref.invalidate(myPointsProvider),
        data: (p) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            RFadeIn(
              child: Container(
                padding: const EdgeInsets.all(RSpace.xl),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(RRadius.xl),
                  border: Border.all(color: RColors.accent.withValues(alpha: 0.35)),
                  gradient: LinearGradient(colors: [RColors.accent.withValues(alpha: 0.12), RColors.surface]),
                ),
                child: Column(children: [
                  Text(l10n.pointsTotal, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
                  const SizedBox(height: RSpace.xs),
                  Text('${p.total}', style: RType.largeTitle.copyWith(color: RColors.accent, fontSize: 48)),
                ]),
              ),
            ),
            const SizedBox(height: RSpace.xl),
            if (p.history.isEmpty)
              REmptyState(icon: CupertinoIcons.chart_bar, title: l10n.rankingsEmptyTitle, message: l10n.pointsHistoryEmpty)
            else
              RGroupedSection(children: [
                for (final a in p.history)
                  RListRow(
                    title: _awardLabel(context, a.awardType),
                    subtitle: [a.tournamentName, formatDate(context, a.createdAt)].whereType<String>().join(' · '),
                    leading: Icon(_awardIcon(a.awardType), color: a.points < 0 ? RColors.warning : RColors.accent),
                    trailing: Text(a.points > 0 ? '+${a.points}' : '${a.points}',
                        style: RType.headline.copyWith(color: a.points < 0 ? RColors.warning : RColors.accent)),
                    onTap: a.tournamentSlug == null ? null : () => context.push(Routes.tournament(a.tournamentSlug!)),
                    showChevron: false,
                  ),
              ]),
          ],
        ),
      ),
    );
  }
}

String _awardLabel(BuildContext context, String type) {
  final l = context.l10n;
  return switch (type) {
    'match_win' => l.awardMatchWin,
    'match_draw' => l.awardMatchDraw,
    'match_loss' => l.awardMatchLoss,
    'participation' => l.awardParticipation,
    'place_1' => l.awardPlace(1),
    'place_2' => l.awardPlace(2),
    'place_3' => l.awardPlace(3),
    _ => l.awardReversal,
  };
}

IconData _awardIcon(String type) => switch (type) {
      'match_win' => CupertinoIcons.checkmark_seal_fill,
      'match_draw' => CupertinoIcons.equal_circle_fill,
      'participation' => CupertinoIcons.person_crop_circle_fill_badge_checkmark,
      'place_1' || 'place_2' || 'place_3' => CupertinoIcons.rosette,
      'reversal' => CupertinoIcons.arrow_uturn_left_circle_fill,
      _ => CupertinoIcons.circle_fill,
    };
