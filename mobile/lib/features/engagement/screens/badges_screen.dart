import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/cupertino.dart';

import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/engagement.dart';
import '../widgets/engagement_widgets.dart';

/// All badges: earned ones first, then what's left with progress.
class BadgesScreen extends ConsumerWidget {
  const BadgesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return RDetailPage(
      title: l10n.profileBadges,
      child: RAsyncView<List<PlayerBadge>>(
        value: ref.watch(myBadgesProvider),
        onRetry: () => ref.invalidate(myBadgesProvider),
        data: (all) {
          final earned = all.where((b) => b.earned).length;
          final sorted = [...all]..sort((a, b) => (b.earned ? 1 : 0).compareTo(a.earned ? 1 : 0));
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(l10n.badgesEarnedCount(earned, all.length), style: RType.title3, textAlign: TextAlign.center),
              const SizedBox(height: RSpace.xs),
              Text(l10n.badgesExplainer, style: RType.footnote.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
              const SizedBox(height: RSpace.xl),
              if (all.isEmpty)
                REmptyState(icon: CupertinoIcons.rosette, title: l10n.profileBadges, message: l10n.profileBadgesEmpty)
              else
                BadgeGrid(badges: sorted),
            ],
          );
        },
      ),
    );
  }
}
