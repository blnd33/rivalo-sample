import 'package:flutter/material.dart';

import '../tokens.dart';
import 'pressable.dart';

enum RButtonVariant {
  /// Lime fill. Use for the single most important action on a screen.
  primary,

  /// Outlined surface button for secondary actions.
  secondary,

  /// Text-only, lime label.
  tertiary,

  /// Destructive (error tone). Always confirm before acting.
  destructive,
}

class RButton extends StatelessWidget {
  const RButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = RButtonVariant.primary,
    this.icon,
    this.loading = false,
    this.expand = true,
    this.compact = false,
  });

  const RButton.secondary({super.key, required this.label, required this.onPressed, this.icon, this.loading = false, this.expand = true, this.compact = false})
      : variant = RButtonVariant.secondary;

  const RButton.tertiary({super.key, required this.label, required this.onPressed, this.icon, this.loading = false, this.expand = false, this.compact = true})
      : variant = RButtonVariant.tertiary;

  const RButton.destructive({super.key, required this.label, required this.onPressed, this.icon, this.loading = false, this.expand = true, this.compact = false})
      : variant = RButtonVariant.destructive;

  final String label;
  final VoidCallback? onPressed;
  final RButtonVariant variant;
  final IconData? icon;
  final bool loading;
  final bool expand;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg, Color? borderColor) = switch (variant) {
      RButtonVariant.primary => (RColors.accent, RColors.onAccent, null),
      RButtonVariant.secondary => (RColors.surface, RColors.textPrimary, RColors.borderStrong),
      RButtonVariant.tertiary => (Colors.transparent, RColors.accent, null),
      RButtonVariant.destructive => (RColors.error.withValues(alpha: 0.12), RColors.error, RColors.error.withValues(alpha: 0.5)),
    };

    final height = compact ? RSize.buttonHeightCompact : RSize.buttonHeight;
    final textStyle = (compact ? RType.subheadline : RType.headline).copyWith(color: fg, fontWeight: FontWeight.w700);

    final content = loading
        ? SizedBox.square(
            dimension: 20,
            child: CircularProgressIndicator.adaptive(strokeWidth: 2.4, valueColor: AlwaysStoppedAnimation(fg)),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[Icon(icon, size: RSize.iconMd, color: fg), const SizedBox(width: RSpace.sm)],
              Flexible(child: Text(label, style: textStyle, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center)),
            ],
          );

    final button = Container(
      height: height,
      width: expand ? double.infinity : null,
      padding: EdgeInsets.symmetric(horizontal: variant == RButtonVariant.tertiary ? RSpace.sm : RSpace.xl),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(RRadius.md),
        border: borderColor == null ? null : Border.all(color: borderColor),
      ),
      child: content,
    );

    return RPressable(
      onPressed: loading ? null : onPressed,
      semanticLabel: loading ? '$label, loading' : null,
      haptic: variant == RButtonVariant.primary,
      child: button,
    );
  }
}

/// Icon-only button with a required accessible label and a 44pt hit target.
class RIconButton extends StatelessWidget {
  const RIconButton({super.key, required this.icon, required this.semanticLabel, required this.onPressed, this.color, this.size = RSize.iconLg});

  final IconData icon;
  final String semanticLabel;
  final VoidCallback? onPressed;
  final Color? color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: semanticLabel,
      excludeFromSemantics: true,
      child: RPressable(
        onPressed: onPressed,
        semanticLabel: semanticLabel,
        // Fixed 44pt square so it never stretches inside text-field suffixes or toolbars.
        child: SizedBox.square(
          dimension: RSize.minTouchTarget,
          child: Center(child: Icon(icon, size: size, color: color ?? RColors.accent)),
        ),
      ),
    );
  }
}
