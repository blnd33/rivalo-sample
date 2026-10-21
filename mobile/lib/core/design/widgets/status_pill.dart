import 'package:flutter/cupertino.dart';

import '../tokens.dart';

enum RTone { success, warning, error, info, neutral, accent }

/// Compact status label. Always text + icon; color is never the only signal.
class RStatusPill extends StatelessWidget {
  const RStatusPill({super.key, required this.label, required this.tone, this.icon});

  final String label;
  final RTone tone;
  final IconData? icon;

  static Color colorFor(RTone tone) => switch (tone) {
        RTone.success => RColors.success,
        RTone.warning => RColors.warning,
        RTone.error => RColors.error,
        RTone.info => RColors.info,
        RTone.neutral => RColors.textSecondary,
        RTone.accent => RColors.accent,
      };

  static IconData defaultIcon(RTone tone) => switch (tone) {
        RTone.success => CupertinoIcons.checkmark_circle_fill,
        RTone.warning => CupertinoIcons.clock_fill,
        RTone.error => CupertinoIcons.xmark_circle_fill,
        RTone.info => CupertinoIcons.info_circle_fill,
        RTone.neutral => CupertinoIcons.minus_circle_fill,
        RTone.accent => CupertinoIcons.bolt_fill,
      };

  @override
  Widget build(BuildContext context) {
    final color = colorFor(tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: RSpace.sm, vertical: RSpace.xs),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(RRadius.pill),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          ExcludeSemantics(child: Icon(icon ?? defaultIcon(tone), size: 13, color: color)),
          const SizedBox(width: RSpace.xs),
          Flexible(
            child: Text(
              label,
              style: RType.caption1.copyWith(color: color, fontWeight: FontWeight.w700),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

/// Labels development sample data so it is never mistaken for real activity.
class RSampleBadge extends StatelessWidget {
  const RSampleBadge({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => RStatusPill(label: label, tone: RTone.warning, icon: CupertinoIcons.lab_flask);
}
