import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import '../tokens.dart';
import 'pressable.dart';

/// Rounded card on the slate surface with a restrained 1px border.
class RCard extends StatelessWidget {
  const RCard({super.key, required this.child, this.onTap, this.padding = const EdgeInsets.all(RSpace.lg), this.semanticLabel, this.highlight = false});

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  final String? semanticLabel;

  /// Lime border for the one item that needs attention (e.g. "your entry").
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final card = Container(
      clipBehavior: Clip.antiAlias,
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: RColors.surface,
        borderRadius: BorderRadius.circular(RRadius.lg),
        border: Border.all(color: highlight ? RColors.accent.withValues(alpha: 0.6) : RColors.border),
      ),
      child: child,
    );

    if (onTap == null) return card;
    return RPressable(onPressed: onTap, semanticLabel: semanticLabel, pressedOpacity: 0.85, pressedScale: 0.97, child: card);
  }
}

/// Uppercase footnote header above a group, as in iOS grouped lists.
class RSectionHeader extends StatelessWidget {
  const RSectionHeader(this.title, {super.key, this.trailing, this.padding});

  final String title;
  final Widget? trailing;
  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding ?? const EdgeInsets.fromLTRB(RSpace.xs, RSpace.xxl, RSpace.xs, RSpace.sm),
      child: Row(
        children: [
          Expanded(
            child: Semantics(
              header: true,
              child: Text(
                title.toUpperCase(),
                style: RType.footnote.copyWith(color: RColors.textSecondary, fontWeight: FontWeight.w600, letterSpacing: 0.6),
              ),
            ),
          ),
          ?trailing,
        ],
      ),
    );
  }
}

/// iOS-style grouped inset list section (Settings app pattern).
class RGroupedSection extends StatelessWidget {
  const RGroupedSection({super.key, this.header, this.footer, required this.children});

  final String? header;
  final String? footer;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    for (var i = 0; i < children.length; i++) {
      rows.add(children[i]);
      if (i < children.length - 1) {
        rows.add(const Padding(padding: EdgeInsetsDirectional.only(start: RSpace.lg), child: Divider(height: 0.5)));
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (header != null) RSectionHeader(header!),
        ClipRRect(
          borderRadius: BorderRadius.circular(RRadius.md),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: RColors.surface,
              borderRadius: BorderRadius.circular(RRadius.md),
              border: Border.all(color: RColors.border, width: 0.5),
            ),
            child: Column(children: rows),
          ),
        ),
        if (footer != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(RSpace.xs, RSpace.sm, RSpace.xs, 0),
            child: Text(footer!, style: RType.footnote.copyWith(color: RColors.textSecondary)),
          ),
      ],
    );
  }
}

/// A row inside [RGroupedSection]: leading icon, title/subtitle, trailing value or control, chevron.
class RListRow extends StatelessWidget {
  const RListRow({
    super.key,
    required this.title,
    this.subtitle,
    this.leading,
    this.trailing,
    this.value,
    this.onTap,
    this.destructive = false,
    this.showChevron,
  });

  final String title;
  final String? subtitle;
  final Widget? leading;
  final Widget? trailing;
  final String? value;
  final VoidCallback? onTap;
  final bool destructive;
  final bool? showChevron;

  @override
  Widget build(BuildContext context) {
    final chevron = showChevron ?? (onTap != null && trailing == null);

    final row = ConstrainedBox(
      constraints: const BoxConstraints(minHeight: RSize.minTouchTarget + 4),
      child: Padding(
        padding: const EdgeInsetsDirectional.fromSTEB(RSpace.lg, RSpace.md, RSpace.md, RSpace.md),
        child: Row(
          children: [
            if (leading != null) ...[
              IconTheme.merge(data: const IconThemeData(size: RSize.iconMd, color: RColors.textSecondary), child: ExcludeSemantics(child: leading!)),
              const SizedBox(width: RSpace.md),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: RType.body.copyWith(color: destructive ? RColors.error : RColors.textPrimary)),
                  if (subtitle != null) ...[
                    const SizedBox(height: RSpace.xxs),
                    Text(subtitle!, style: RType.footnote.copyWith(color: RColors.textSecondary)),
                  ],
                ],
              ),
            ),
            if (value != null) ...[
              const SizedBox(width: RSpace.sm),
              Flexible(
                child: Text(value!, style: RType.body.copyWith(color: RColors.textSecondary), textAlign: TextAlign.end, overflow: TextOverflow.ellipsis),
              ),
            ],
            if (trailing != null) ...[const SizedBox(width: RSpace.sm), trailing!],
            if (chevron) ...[
              const SizedBox(width: RSpace.xs),
              const ExcludeSemantics(child: Icon(CupertinoIcons.chevron_forward, size: 17, color: RColors.textTertiary)),
            ],
          ],
        ),
      ),
    );

    if (onTap == null) return MergeSemantics(child: row);
    return RPressable(onPressed: onTap, pressedOpacity: 0.6, child: row);
  }
}

/// Small key/value pair used on detail screens ("Starts", "Fri 14 Nov, 17:00").
class RInfoItem extends StatelessWidget {
  const RInfoItem({super.key, required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return MergeSemantics(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: RSize.iconMd, color: RColors.textSecondary),
          const SizedBox(width: RSpace.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: RType.footnote.copyWith(color: RColors.textSecondary)),
                const SizedBox(height: RSpace.xxs),
                Text(value, style: RType.body),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
