import 'package:flutter/cupertino.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/news.dart';

String _meta(BuildContext context, NewsArticle a) =>
    [formatPublished(context, a.publishedAt), context.l10n.newsReadingTime(a.readingMinutes)].where((s) => s.isNotEmpty).join(' · ');

/// Featured slider: swipe through the latest featured stories with big photos.
class NewsCarousel extends StatelessWidget {
  const NewsCarousel({super.key, required this.articles});

  final List<NewsArticle> articles;

  @override
  Widget build(BuildContext context) {
    return RCarousel(
      itemCount: articles.length,
      aspectRatio: 4 / 3.4,
      maxHeight: 440,
      semanticLabel: context.l10n.carouselLabel,
      itemBuilder: (context, i, offset) => _Slide(article: articles[i], offset: offset),
    );
  }
}

class _Slide extends StatelessWidget {
  const _Slide({required this.article, required this.offset});

  final NewsArticle article;
  final double offset;

  @override
  Widget build(BuildContext context) {
    final a = article;
    final l10n = context.l10n;
    return RPressable(
      onPressed: () => context.push(Routes.newsArticle(a.slug)),
      semanticLabel: '${newsCategoryLabel(context, a.category)}. ${a.title}',
      pressedOpacity: 0.9,
      pressedScale: 0.97,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(RRadius.xl),
        child: Stack(
          fit: StackFit.expand,
          children: [
            RCover(imageUrl: a.coverUrl, sportIcon: a.sportIcon, seed: a.id, scrim: RCoverScrim.strong, parallax: offset * 40),
            DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(RRadius.xl),
                border: Border.all(color: const Color(0x22FFFFFF), width: 0.5),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(RSpace.xl),
              child: ExcludeSemantics(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Flexible(child: RGlassChip(label: newsCategoryLabel(context, a.category).toUpperCase(), color: RColors.accent)),
                      if (a.isSampleData) ...[const SizedBox(width: RSpace.sm), Flexible(child: RSampleBadge(label: l10n.sampleData))],
                    ]),
                    const SizedBox(height: RSpace.sm),
                    // Text block sits at the bottom of the photo. The headline is Flexible, so with
                    // very large text it ellipsizes instead of overflowing.
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.end,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Flexible(
                            child: Text(a.title, style: RType.title1.copyWith(height: 1.1), maxLines: 3, overflow: TextOverflow.ellipsis),
                          ),
                          if (a.summary != null) ...[
                            const SizedBox(height: RSpace.sm),
                            Text(a.summary!, style: RType.subheadline.copyWith(color: const Color(0xFFD9DEE8)), maxLines: 2, overflow: TextOverflow.ellipsis),
                          ],
                          const SizedBox(height: RSpace.md),
                          Text(_meta(context, a), style: RType.caption1.copyWith(color: RColors.textSecondary)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Lead story in the feed: wide photo above the headline.
class NewsLeadCard extends StatelessWidget {
  const NewsLeadCard({super.key, required this.article});

  final NewsArticle article;

  @override
  Widget build(BuildContext context) {
    final a = article;
    return Padding(
      padding: const EdgeInsets.only(bottom: RSpace.lg),
      child: RCard(
        padding: EdgeInsets.zero,
        onTap: () => context.push(Routes.newsArticle(a.slug)),
        semanticLabel: '${newsCategoryLabel(context, a.category)}. ${a.title}',
        child: ExcludeSemantics(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AspectRatio(
                aspectRatio: 16 / 9,
                child: Stack(fit: StackFit.expand, children: [
                  RCover(imageUrl: a.coverUrl, sportIcon: a.sportIcon, seed: a.id),
                  if (a.isSampleData)
                    PositionedDirectional(top: RSpace.md, end: RSpace.md, child: RSampleBadge(label: context.l10n.sampleData)),
                ]),
              ),
              Padding(
                padding: const EdgeInsets.all(RSpace.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _Kicker(article: a),
                    const SizedBox(height: RSpace.xs),
                    Text(a.title, style: RType.title3, maxLines: 3, overflow: TextOverflow.ellipsis),
                    if (a.summary != null) ...[
                      const SizedBox(height: RSpace.xs),
                      Text(a.summary!, style: RType.subheadline.copyWith(color: RColors.textSecondary), maxLines: 2, overflow: TextOverflow.ellipsis),
                    ],
                    const SizedBox(height: RSpace.md),
                    Text(_meta(context, a), style: RType.caption1.copyWith(color: RColors.textTertiary)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Compact feed row: thumbnail beside the headline.
class NewsRow extends StatelessWidget {
  const NewsRow({super.key, required this.article});

  final NewsArticle article;

  @override
  Widget build(BuildContext context) {
    final a = article;
    return RPressable(
      onPressed: () => context.push(Routes.newsArticle(a.slug)),
      semanticLabel: '${newsCategoryLabel(context, a.category)}. ${a.title}',
      pressedOpacity: 0.8,
      pressedScale: 0.98,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: RSpace.sm),
        child: ExcludeSemantics(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(RRadius.md),
                child: SizedBox(
                  width: 96,
                  height: 96,
                  child: RCover(imageUrl: a.coverUrl, sportIcon: a.sportIcon, seed: a.id, scrim: RCoverScrim.none),
                ),
              ),
              const SizedBox(width: RSpace.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _Kicker(article: a),
                    const SizedBox(height: RSpace.xxs),
                    Text(a.title, style: RType.headline, maxLines: 2, overflow: TextOverflow.ellipsis),
                    const SizedBox(height: RSpace.xs),
                    Text(_meta(context, a), style: RType.caption1.copyWith(color: RColors.textTertiary)),
                    if (a.isSampleData) ...[const SizedBox(height: RSpace.xs), RSampleBadge(label: context.l10n.sampleData)],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Category (and sport) in small lime capitals above a headline.
class _Kicker extends StatelessWidget {
  const _Kicker({required this.article});

  final NewsArticle article;

  @override
  Widget build(BuildContext context) {
    final parts = [newsCategoryLabel(context, article.category), ?article.sportName];
    return Text(
      parts.join(' · ').toUpperCase(),
      style: RType.caption2.copyWith(color: RColors.accent, letterSpacing: 0.8),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }
}

/// Skeleton for the slider while it loads.
class NewsCarouselSkeleton extends StatelessWidget {
  const NewsCarouselSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: context.l10n.loading,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxHeight: 440),
        child: AspectRatio(
          aspectRatio: 4 / 3.4,
          child: DecoratedBox(
            decoration: BoxDecoration(color: RColors.surface, borderRadius: BorderRadius.circular(RRadius.xl), border: Border.all(color: RColors.border)),
            child: const Padding(
              padding: EdgeInsets.all(RSpace.xl),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.end,
                children: [RSkeleton(width: 90, height: 12), SizedBox(height: RSpace.md), RSkeleton(height: 26), SizedBox(height: RSpace.sm), RSkeleton(width: 200, height: 26)],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Icon for a news category (used in filter chips).
IconData newsCategoryIcon(String? category) => switch (category) {
      'tournament' => CupertinoIcons.flag_fill,
      'results' => CupertinoIcons.chart_bar_fill,
      'community' => CupertinoIcons.person_3_fill,
      'guide' => CupertinoIcons.book_fill,
      'announcement' => CupertinoIcons.speaker_2_fill,
      _ => CupertinoIcons.square_grid_2x2_fill,
    };
