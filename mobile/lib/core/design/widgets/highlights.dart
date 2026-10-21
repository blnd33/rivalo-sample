import 'package:flutter/cupertino.dart';

import '../tokens.dart';
import 'pressable.dart';
import 'sport_glyph.dart';

/// App Store-style section title: bold, with an optional "See all" action.
class RSectionTitle extends StatelessWidget {
  const RSectionTitle(this.title, {super.key, this.actionLabel, this.onAction, this.subtitle});

  final String title;
  final String? subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(RSpace.xxs, RSpace.xxxl, RSpace.xxs, RSpace.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Semantics(header: true, child: Text(title, style: RType.title2)),
                if (subtitle != null) ...[
                  const SizedBox(height: RSpace.xxs),
                  Text(subtitle!, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
                ],
              ],
            ),
          ),
          if (actionLabel != null && onAction != null)
            RPressable(
              onPressed: onAction,
              minSize: RSize.minTouchTarget,
              child: Padding(
                padding: const EdgeInsetsDirectional.only(start: RSpace.md),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(actionLabel!, style: RType.subheadline.copyWith(color: RColors.accent, fontWeight: FontWeight.w600)),
                    const SizedBox(width: 2),
                    const Icon(CupertinoIcons.chevron_forward, size: 14, color: RColors.accent),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// How full a tournament is: animated bar plus "3 of 8" and a places-left note.
/// Lime while places remain, amber when nearly full, and amber with a full bar when full.
class RCapacityBar extends StatelessWidget {
  const RCapacityBar({super.key, required this.taken, required this.capacity, required this.label, this.trailing, this.compact = false});

  final int taken;
  final int capacity;
  final String label;
  final String? trailing;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ratio = capacity <= 0 ? 0.0 : (taken / capacity).clamp(0.0, 1.0);
    final color = ratio >= 1 ? RColors.warning : (ratio >= 0.75 ? RColors.warning : RColors.accent);

    return MergeSemantics(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text(label, style: (compact ? RType.caption1 : RType.footnote).copyWith(color: RColors.textSecondary))),
              if (trailing != null)
                Text(trailing!, style: (compact ? RType.caption1 : RType.footnote).copyWith(color: color, fontWeight: FontWeight.w700)),
            ],
          ),
          SizedBox(height: compact ? RSpace.xs : RSpace.sm),
          ExcludeSemantics(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(RRadius.pill),
              child: SizedBox(
                height: compact ? 5 : 8,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    const ColoredBox(color: RColors.surfaceRaised),
                    TweenAnimationBuilder<double>(
                      tween: Tween(begin: 0, end: ratio),
                      duration: RMotion.of(context, const Duration(milliseconds: 900)),
                      curve: RMotion.easeOut,
                      builder: (context, value, _) => FractionallySizedBox(
                        alignment: AlignmentDirectional.centerStart,
                        widthFactor: value,
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(RRadius.pill),
                            gradient: LinearGradient(colors: [color.withValues(alpha: 0.7), color]),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// A compact stat: icon, big value, small label (e.g. "8 · Places", "Free · Entry").
class RStatTile extends StatelessWidget {
  const RStatTile({super.key, required this.icon, required this.value, required this.label, this.color = RColors.textPrimary});

  final IconData icon;
  final String value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return MergeSemantics(
      child: Container(
        padding: const EdgeInsets.all(RSpace.md),
        decoration: BoxDecoration(
          color: RColors.surface,
          borderRadius: BorderRadius.circular(RRadius.md),
          border: Border.all(color: RColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ExcludeSemantics(child: Icon(icon, size: RSize.iconMd, color: RColors.accent)),
            const SizedBox(height: RSpace.sm),
            Text(value, style: RType.headline.copyWith(color: color), maxLines: 2, overflow: TextOverflow.ellipsis),
            const SizedBox(height: RSpace.xxs),
            Text(label, style: RType.caption1.copyWith(color: RColors.textSecondary), maxLines: 1, overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
}

/// Round sport shortcut with a glowing glyph and its name underneath.
class RSportBubble extends StatelessWidget {
  const RSportBubble({super.key, required this.icon, required this.label, required this.onTap, this.selected = false});

  final String? icon;
  final String label;
  final VoidCallback onTap;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    return RPressable(
      onPressed: onTap,
      semanticLabel: label,
      pressedScale: 0.92,
      child: SizedBox(
        width: 76,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedContainer(
              duration: RMotion.of(context, RMotion.base),
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: selected ? [RColors.accent, const Color(0xFF8FD400)] : [RColors.surfaceRaised, RColors.surface],
                ),
                border: Border.all(color: selected ? RColors.accent : RColors.border),
                boxShadow: [BoxShadow(color: RColors.accent.withValues(alpha: selected ? 0.35 : 0.0), blurRadius: 18)],
              ),
              alignment: Alignment.center,
              child: SportGlyph(icon, size: 30, color: selected ? RColors.onAccent : RColors.accent),
            ),
            const SizedBox(height: RSpace.xs + 2),
            ExcludeSemantics(
              child: Text(label, style: RType.caption1.copyWith(color: RColors.textPrimary), maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
            ),
          ],
        ),
      ),
    );
  }
}

/// Small frosted label laid over photos (category, status, countdown).
class RGlassChip extends StatelessWidget {
  const RGlassChip({super.key, required this.label, this.icon, this.color = RColors.textPrimary, this.background});

  final String label;
  final IconData? icon;
  final Color color;
  final Color? background;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: RSpace.sm + 2, vertical: RSpace.xs + 1),
      decoration: BoxDecoration(
        color: background ?? const Color(0x990B1020),
        borderRadius: BorderRadius.circular(RRadius.pill),
        border: Border.all(color: const Color(0x33FFFFFF), width: 0.5),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 13, color: color), const SizedBox(width: RSpace.xs)],
          Flexible(
            child: Text(label, style: RType.caption1.copyWith(color: color, fontWeight: FontWeight.w700), maxLines: 1, overflow: TextOverflow.ellipsis),
          ),
        ],
      ),
    );
  }
}
