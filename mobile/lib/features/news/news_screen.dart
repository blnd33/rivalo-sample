import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design/design_system.dart';
import '../../core/providers.dart';
import '../../core/ui/ui_helpers.dart';
import 'data/news.dart';
import 'widgets/news_widgets.dart';

/// News tab: featured photo slider, category filter, then the feed (lead story + rows).
class NewsScreen extends ConsumerStatefulWidget {
  const NewsScreen({super.key});

  @override
  ConsumerState<NewsScreen> createState() => _NewsScreenState();
}

class _NewsScreenState extends ConsumerState<NewsScreen> {
  static const _categories = ['announcement', 'tournament', 'results', 'community', 'guide'];

  String? _category;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final online = ref.watch(connectivityProvider).value ?? true;
    final featured = ref.watch(featuredNewsProvider);
    final feed = ref.watch(newsListProvider(_category));

    return RLargeTitlePage(
      title: l10n.newsTitle,
      onRefresh: () async {
        ref.invalidate(featuredNewsProvider);
        ref.invalidate(newsListProvider(_category));
        await ref.read(newsListProvider(_category).future).catchError((_) => <NewsArticle>[]);
      },
      slivers: [
        SliverList.list(children: [
          if (!online) ...[ROfflineBanner(message: l10n.offlineBanner), const SizedBox(height: RSpace.md)],
          if (_category == null)
            featured.when(
              skipLoadingOnRefresh: true,
              loading: () => const Padding(padding: EdgeInsets.only(bottom: RSpace.xl), child: NewsCarouselSkeleton()),
              error: (_, _) => const SizedBox.shrink(),
              data: (list) => list.isEmpty
                  ? const SizedBox.shrink()
                  : Padding(padding: const EdgeInsets.only(bottom: RSpace.xl), child: RFadeIn(child: NewsCarousel(articles: list))),
            ),
          _CategoryChips(
            categories: _categories,
            selected: _category,
            onChanged: (c) => setState(() => _category = c),
          ),
          const SizedBox(height: RSpace.lg),
          RAsyncView<List<NewsArticle>>(
            value: feed,
            onRetry: () => ref.invalidate(newsListProvider(_category)),
            data: (list) {
              if (list.isEmpty) {
                return REmptyState(icon: CupertinoIcons.news, title: l10n.newsEmptyTitle, message: l10n.newsEmptyBody);
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  RFadeIn(child: NewsLeadCard(article: list.first)),
                  for (var i = 1; i < list.length; i++) ...[
                    RFadeIn(index: i, child: NewsRow(article: list[i])),
                    if (i < list.length - 1) const Divider(height: RSpace.md),
                  ],
                ],
              );
            },
          ),
        ]),
      ],
    );
  }
}

class _CategoryChips extends StatelessWidget {
  const _CategoryChips({required this.categories, required this.selected, required this.onChanged});

  final List<String> categories;
  final String? selected;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final screen = MediaQuery.sizeOf(context).width;
    return LayoutBuilder(
      builder: (context, c) => SizedBox(
        height: RSize.minTouchTarget,
        child: OverflowBox(
          minWidth: screen,
          maxWidth: screen,
          child: ListView(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            padding: EdgeInsets.symmetric(horizontal: (screen - c.maxWidth) / 2),
            children: [
              _Chip(label: context.l10n.filterAll, icon: newsCategoryIcon(null), selected: selected == null, onTap: () => onChanged(null)),
              for (final cat in categories)
                _Chip(label: newsCategoryLabel(context, cat), icon: newsCategoryIcon(cat), selected: selected == cat, onTap: () => onChanged(cat)),
            ],
          ),
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.icon, required this.selected, required this.onTap});

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final fg = selected ? RColors.onAccent : RColors.textPrimary;
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: RSpace.sm),
      child: Semantics(
        selected: selected,
        button: true,
        label: label,
        excludeSemantics: true,
        child: RPressable(
          isButton: false,
          pressedScale: 0.94,
          onPressed: () {
            HapticFeedback.selectionClick();
            onTap();
          },
          child: AnimatedContainer(
            duration: RMotion.of(context, RMotion.base),
            curve: RMotion.standard,
            padding: const EdgeInsets.symmetric(horizontal: RSpace.md),
            decoration: BoxDecoration(
              color: selected ? RColors.accent : RColors.surface,
              borderRadius: BorderRadius.circular(RRadius.pill),
              border: Border.all(color: selected ? RColors.accent : RColors.border),
            ),
            child: Row(children: [
              Icon(icon, size: 15, color: selected ? RColors.onAccent : RColors.accent),
              const SizedBox(width: RSpace.xs + 2),
              Text(label, style: RType.subheadline.copyWith(color: fg, fontWeight: FontWeight.w600)),
            ]),
          ),
        ),
      ),
    );
  }
}
