import 'package:flutter/cupertino.dart';

import '../../core/design/design_system.dart';
import '../../core/ui/ui_helpers.dart';

/// Developer-only catalog of design tokens and components in every state.
/// Strings here are sample labels for designers, intentionally not localized.
class DesignGalleryScreen extends StatelessWidget {
  const DesignGalleryScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return RDetailPage(
      title: context.l10n.galleryTitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const RNotice(message: 'Development preview. All content on this screen is sample data.', icon: CupertinoIcons.lab_flask, color: RColors.warning),
          const RSectionHeader('Wordmark'),
          const RCard(child: Center(child: RivaloWordmark(height: 40, showTagline: true))),

          const RSectionHeader('Brand colors'),
          const Wrap(spacing: RSpace.sm, runSpacing: RSpace.sm, children: [
            _Swatch('Background', RColors.background),
            _Swatch('Surface', RColors.surface),
            _Swatch('Raised', RColors.surfaceRaised),
            _Swatch('Accent', RColors.accent),
            _Swatch('Text', RColors.textPrimary),
            _Swatch('Text 2', RColors.textSecondary),
            _Swatch('Border', RColors.border),
          ]),
          const RSectionHeader('Status & podium'),
          const Wrap(spacing: RSpace.sm, runSpacing: RSpace.sm, children: [
            _Swatch('Success', RColors.success),
            _Swatch('Warning', RColors.warning),
            _Swatch('Error', RColors.error),
            _Swatch('Info', RColors.info),
            _Swatch('1st', RColors.gold),
            _Swatch('2nd', RColors.silver),
            _Swatch('3rd', RColors.bronze),
          ]),

          const RSectionHeader('Type scale (Dynamic Type)'),
          RCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final (name, style) in [
                  ('Large title 34', RType.largeTitle),
                  ('Title 1 28', RType.title1),
                  ('Title 2 22', RType.title2),
                  ('Title 3 20', RType.title3),
                  ('Headline 17', RType.headline),
                  ('Body 17', RType.body),
                  ('Callout 16', RType.callout),
                  ('Subheadline 15', RType.subheadline),
                  ('Footnote 13', RType.footnote),
                  ('Caption 12', RType.caption1),
                ])
                  Padding(padding: const EdgeInsets.symmetric(vertical: 2), child: Text(name, style: style)),
              ],
            ),
          ),

          const RSectionHeader('Buttons'),
          RButton(label: 'Primary action', onPressed: () {}),
          const SizedBox(height: RSpace.sm),
          RButton.secondary(label: 'Secondary', onPressed: () {}),
          const SizedBox(height: RSpace.sm),
          RButton.destructive(label: 'Destructive', onPressed: () {}),
          const SizedBox(height: RSpace.sm),
          const RButton(label: 'Loading', onPressed: null, loading: true),
          const SizedBox(height: RSpace.sm),
          const RButton(label: 'Disabled', onPressed: null),
          Row(children: [RButton.tertiary(label: 'Tertiary', onPressed: () {}), RIconButton(icon: CupertinoIcons.heart, semanticLabel: 'Sample icon button', onPressed: () {})]),

          const RSectionHeader('Status pills'),
          const Wrap(spacing: RSpace.sm, runSpacing: RSpace.sm, children: [
            RStatusPill(label: 'Reserved', tone: RTone.success),
            RStatusPill(label: 'Pay at venue', tone: RTone.warning),
            RStatusPill(label: 'No-show', tone: RTone.error),
            RStatusPill(label: 'Published', tone: RTone.info),
            RStatusPill(label: 'Registration open', tone: RTone.accent),
            RStatusPill(label: 'Completed', tone: RTone.neutral),
            RSampleBadge(label: 'Sample data'),
          ]),

          const RSectionHeader('Sports glyphs'),
          const Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
            SportGlyph('football', size: 32, semanticLabel: 'Football'),
            SportGlyph('padel', size: 32, semanticLabel: 'Padel'),
            SportGlyph('cue', size: 32, semanticLabel: 'Cue sports'),
            SportGlyph('controller', size: 32, semanticLabel: 'E-sports'),
          ]),

          const RSectionHeader('Inputs'),
          const RTextField(label: 'Label', hint: 'Placeholder', helper: 'Helper text explains the field.'),
          const SizedBox(height: RSpace.lg),
          const RTextField(label: 'With error', errorText: 'Error message sits right under the field.'),

          const RSectionHeader('Grouped list'),
          RGroupedSection(
            footer: 'Footer text explains the section.',
            children: [
              RListRow(title: 'Navigation row', leading: const Icon(CupertinoIcons.gear), onTap: () {}),
              const RListRow(title: 'Value row', value: 'Value', leading: Icon(CupertinoIcons.globe)),
              RSwitchRow(title: 'Switch row', subtitle: 'Adaptive switch', value: true, onChanged: (_) {}),
              RListRow(title: 'Destructive row', destructive: true, onTap: () {}),
            ],
          ),

          const RSectionHeader('Avatars'),
          const Row(children: [
            RAvatar(name: 'Sample Player', size: RSize.avatarSm),
            SizedBox(width: RSpace.md),
            RAvatar(name: 'Sample Player'),
            SizedBox(width: RSpace.md),
            RAvatar(name: 'Sample Player', size: RSize.avatarLg, ring: true),
          ]),

          const RSectionHeader('States'),
          const RLoadingList(count: 1, semanticLabel: 'Loading sample'),
          const ROfflineBanner(message: 'Offline banner sample.'),
          const REmptyState(icon: CupertinoIcons.flag, title: 'Empty state', message: 'Explains what is missing and what to do next.'),
          RErrorState(title: 'Error state', message: 'Human-readable message with a retry.', retryLabel: 'Try again', onRetry: () {}),
        ],
      ),
    );
  }
}

class _Swatch extends StatelessWidget {
  const _Swatch(this.name, this.color);

  final String name;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final hex = '#${color.toARGB32().toRadixString(16).substring(2).toUpperCase()}';
    return SizedBox(
      width: 96,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(height: 48, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(RRadius.sm), border: Border.all(color: RColors.borderStrong))),
          const SizedBox(height: RSpace.xs),
          Text(name, style: RType.caption1),
          Text(hex, style: RType.caption2.copyWith(color: RColors.textSecondary)),
        ],
      ),
    );
  }
}
