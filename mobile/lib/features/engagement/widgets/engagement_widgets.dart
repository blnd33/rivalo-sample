import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/engagement.dart';

IconData badgeIcon(String key) => switch (key) {
      'flag' => CupertinoIcons.flag_fill,
      'qrcode' => CupertinoIcons.qrcode,
      'checkmark_seal' => CupertinoIcons.checkmark_seal_fill,
      'flame' => CupertinoIcons.flame_fill,
      'repeat' => CupertinoIcons.repeat,
      'trophy' => Icons.emoji_events_rounded,
      'star' => CupertinoIcons.star_fill,
      'bolt' => CupertinoIcons.bolt_fill,
      'heart' => CupertinoIcons.heart_fill,
      _ => CupertinoIcons.rosette,
    };

/// Bell in the navigation bar with an unread count. Opens the inbox.
class InboxBellButton extends ConsumerWidget {
  const InboxBellButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unread = ref.watch(unreadNotificationsProvider).value ?? 0;
    final l10n = context.l10n;
    return Semantics(
      label: unread > 0 ? '${l10n.inboxNotificationsTitle}, ${l10n.inboxUnread(unread)}' : l10n.inboxNotificationsTitle,
      button: true,
      excludeSemantics: true,
      child: RPressable(
        onPressed: () async {
          await context.push(Routes.inbox);
          ref.invalidate(unreadNotificationsProvider);
        },
        pressedScale: 0.9,
        child: SizedBox(
          width: 44,
          height: 44,
          child: Stack(
            alignment: Alignment.center,
            clipBehavior: Clip.none,
            children: [
              Icon(unread > 0 ? CupertinoIcons.bell_fill : CupertinoIcons.bell, color: RColors.accent, size: 24),
              if (unread > 0)
                PositionedDirectional(
                  top: 6,
                  end: 4,
                  child: AnimatedScale(
                    scale: 1,
                    duration: RMotion.of(context, RMotion.base),
                    child: Container(
                      constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      decoration: BoxDecoration(color: RColors.error, borderRadius: BorderRadius.circular(RRadius.pill), border: Border.all(color: RColors.background, width: 1.5)),
                      alignment: Alignment.center,
                      child: Text(unread > 99 ? '99+' : '$unread', style: RType.caption2.copyWith(color: Colors.white, fontWeight: FontWeight.w800)),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Badge collection: earned badges glow; locked ones are grey with a progress ring.
class BadgeGrid extends StatelessWidget {
  const BadgeGrid({super.key, required this.badges, this.showLocked = true});

  final List<PlayerBadge> badges;
  final bool showLocked;

  @override
  Widget build(BuildContext context) {
    final shown = showLocked ? badges : badges.where((b) => b.earned).toList();
    return LayoutBuilder(
      builder: (context, c) {
        final columns = c.maxWidth >= 520 ? 5 : 3;
        const gap = RSpace.md;
        final width = (c.maxWidth - gap * (columns - 1)) / columns;
        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final (i, b) in shown.indexed)
              SizedBox(width: width, child: RFadeIn(index: i, child: BadgeTile(badge: b))),
          ],
        );
      },
    );
  }
}

class BadgeTile extends StatelessWidget {
  const BadgeTile({super.key, required this.badge});

  final PlayerBadge badge;

  @override
  Widget build(BuildContext context) {
    final b = badge;
    final l10n = context.l10n;
    final color = b.earned ? RColors.gold : RColors.textTertiary;
    final status = b.earned ? l10n.badgeEarned : (b.target != null ? l10n.badgeProgress(b.current ?? 0, b.target!) : l10n.badgeLocked);

    return RPressable(
      onPressed: () => _showDetails(context, b),
      semanticLabel: '${b.name}. $status',
      pressedScale: 0.94,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: RSpace.md, horizontal: RSpace.xs),
        decoration: BoxDecoration(
          color: RColors.surface,
          borderRadius: BorderRadius.circular(RRadius.lg),
          border: Border.all(color: b.earned ? RColors.gold.withValues(alpha: 0.45) : RColors.border),
        ),
        child: ExcludeSemantics(
          child: Column(
            children: [
              SizedBox(
                width: 60,
                height: 60,
                child: Stack(alignment: Alignment.center, children: [
                  if (!b.earned)
                    SizedBox.expand(
                      child: CircularProgressIndicator(
                        value: b.progress.clamp(0, 1),
                        strokeWidth: 3,
                        backgroundColor: RColors.surfaceRaised,
                        color: RColors.accent,
                      ),
                    ),
                  Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: b.earned
                          ? const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFFFFE08A), RColors.gold, Color(0xFFB8860B)])
                          : null,
                      color: b.earned ? null : RColors.surfaceRaised,
                      boxShadow: b.earned ? [BoxShadow(color: RColors.gold.withValues(alpha: 0.35), blurRadius: 16)] : null,
                    ),
                    alignment: Alignment.center,
                    child: Icon(badgeIcon(b.icon), color: b.earned ? RColors.onAccent : color, size: 24),
                  ),
                ]),
              ),
              const SizedBox(height: RSpace.sm),
              Text(b.name, style: RType.footnote.copyWith(fontWeight: FontWeight.w700, color: b.earned ? RColors.textPrimary : RColors.textSecondary),
                  textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 2),
              Text(status, style: RType.caption2.copyWith(color: b.earned ? RColors.gold : RColors.textTertiary), textAlign: TextAlign.center, maxLines: 1),
            ],
          ),
        ),
      ),
    );
  }

  void _showDetails(BuildContext context, PlayerBadge b) {
    final l10n = context.l10n;
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: RColors.background,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(RRadius.sheet))),
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(RSpace.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(badgeIcon(b.icon), size: 56, color: b.earned ? RColors.gold : RColors.textTertiary),
              const SizedBox(height: RSpace.md),
              Text(b.name, style: RType.title2, textAlign: TextAlign.center),
              const SizedBox(height: RSpace.sm),
              Text(b.description, style: RType.body.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
              const SizedBox(height: RSpace.md),
              if (b.earned && b.awardedAt != null)
                Text(l10n.badgeEarnedOn(formatDate(context, b.awardedAt)), style: RType.footnote.copyWith(color: RColors.gold))
              else if (b.target != null) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(RRadius.pill),
                  child: LinearProgressIndicator(value: b.progress.clamp(0, 1), minHeight: 8, backgroundColor: RColors.surfaceRaised, color: RColors.accent),
                ),
                const SizedBox(height: RSpace.xs),
                Text(l10n.badgeProgress(b.current ?? 0, b.target!), style: RType.footnote.copyWith(color: RColors.textSecondary)),
              ],
              const SizedBox(height: RSpace.md),
            ],
          ),
        ),
      ),
    );
  }
}
