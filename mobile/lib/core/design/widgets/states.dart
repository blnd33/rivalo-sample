import 'package:flutter/cupertino.dart';

import '../tokens.dart';
import 'buttons.dart';

/// Shimmer-free skeleton block (static, so it is calm and respects reduced motion).
class RSkeleton extends StatelessWidget {
  const RSkeleton({super.key, this.height = 16, this.width, this.radius = RRadius.sm});

  final double height;
  final double? width;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      decoration: BoxDecoration(color: RColors.surfaceRaised, borderRadius: BorderRadius.circular(radius)),
    );
  }
}

/// Loading placeholder shaped like a list of cards. Announced once to screen readers.
class RLoadingList extends StatelessWidget {
  const RLoadingList({super.key, this.count = 3, required this.semanticLabel});

  final int count;
  final String semanticLabel;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: semanticLabel,
      liveRegion: true,
      child: ExcludeSemantics(
        child: Column(
          children: List.generate(
            count,
            (_) => Container(
              margin: const EdgeInsets.only(bottom: RSpace.md),
              padding: const EdgeInsets.all(RSpace.lg),
              decoration: BoxDecoration(
                color: RColors.surface,
                borderRadius: BorderRadius.circular(RRadius.lg),
                border: Border.all(color: RColors.border),
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  RSkeleton(width: 120, height: 12),
                  SizedBox(height: RSpace.md),
                  RSkeleton(height: 20),
                  SizedBox(height: RSpace.sm),
                  RSkeleton(width: 180, height: 14),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Purposeful empty state: what is missing and what to do next.
class REmptyState extends StatelessWidget {
  const REmptyState({super.key, required this.icon, required this.title, required this.message, this.actionLabel, this.onAction});

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: RSpace.section, horizontal: RSpace.lg),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(color: RColors.surface, shape: BoxShape.circle, border: Border.all(color: RColors.border)),
            child: ExcludeSemantics(child: Icon(icon, size: RSize.iconXl, color: RColors.textSecondary)),
          ),
          const SizedBox(height: RSpace.lg),
          Semantics(header: true, child: Text(title, style: RType.title3, textAlign: TextAlign.center)),
          const SizedBox(height: RSpace.sm),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 360),
            child: Text(message, style: RType.subheadline.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
          ),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: RSpace.xl),
            RButton.secondary(label: actionLabel!, onPressed: onAction, expand: false),
          ],
        ],
      ),
    );
  }
}

/// Error state with a human message and a retry action.
class RErrorState extends StatelessWidget {
  const RErrorState({super.key, required this.title, required this.message, required this.retryLabel, this.onRetry, this.offline = false});

  final String title;
  final String message;
  final String retryLabel;
  final VoidCallback? onRetry;
  final bool offline;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      child: REmptyState(
        icon: offline ? CupertinoIcons.wifi_slash : CupertinoIcons.exclamationmark_triangle,
        title: title,
        message: message,
        actionLabel: onRetry == null ? null : retryLabel,
        onAction: onRetry,
      ),
    );
  }
}

/// Thin banner shown at the top of content while the device is offline.
class ROfflineBanner extends StatelessWidget {
  const ROfflineBanner({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: RSpace.lg, vertical: RSpace.sm),
        decoration: BoxDecoration(
          color: RColors.warning.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(RRadius.md),
          border: Border.all(color: RColors.warning.withValues(alpha: 0.4)),
        ),
        child: Row(
          children: [
            const ExcludeSemantics(child: Icon(CupertinoIcons.wifi_slash, size: RSize.iconMd, color: RColors.warning)),
            const SizedBox(width: RSpace.sm),
            Expanded(child: Text(message, style: RType.footnote.copyWith(color: RColors.textPrimary))),
          ],
        ),
      ),
    );
  }
}

/// Inline callout for notices (e.g. "In-person payment only", "Coming in a future update").
class RNotice extends StatelessWidget {
  const RNotice({super.key, required this.message, this.icon = CupertinoIcons.info_circle, this.color = RColors.info, this.title});

  final String? title;
  final String message;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return MergeSemantics(
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(RSpace.md),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(RRadius.md),
          border: Border.all(color: color.withValues(alpha: 0.35)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: RSize.iconMd, color: color),
            const SizedBox(width: RSpace.sm),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (title != null) ...[Text(title!, style: RType.subheadline.copyWith(fontWeight: FontWeight.w700)), const SizedBox(height: RSpace.xxs)],
                  Text(message, style: RType.footnote.copyWith(color: RColors.textPrimary)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
