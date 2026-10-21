import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/news.dart';

/// A full article: a large photo header that stretches on pull and collapses into the bar,
/// then the headline, meta line, summary and body.
class NewsArticleScreen extends ConsumerWidget {
  const NewsArticleScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final article = ref.watch(newsArticleProvider(slug));

    return article.when(
      skipLoadingOnRefresh: true,
      loading: () => RDetailPage(title: l10n.newsArticleTitle, child: RLoadingList(count: 2, semanticLabel: l10n.loading)),
      error: (e, _) => RDetailPage(
        title: l10n.newsArticleTitle,
        child: RErrorState(
          title: isOffline(e) ? l10n.errorOfflineTitle : l10n.errorGenericTitle,
          message: errorMessage(context, e),
          retryLabel: l10n.actionRetry,
          offline: isOffline(e),
          onRetry: () => ref.invalidate(newsArticleProvider(slug)),
        ),
      ),
      data: (a) => RHeroPage(
        title: a.title,
        header: RCover(imageUrl: a.coverUrl, sportIcon: a.sportIcon, seed: a.id, scrim: RCoverScrim.strong, semanticLabel: a.coverUrl == null ? null : a.title),
        onRefresh: () async {
          ref.invalidate(newsArticleProvider(slug));
          await ref.read(newsArticleProvider(slug).future).catchError((_) => a);
        },
        child: _Body(article: a),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.article});

  final NewsArticle article;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final a = article;
    final meta = [formatPublished(context, a.publishedAt), l10n.newsReadingTime(a.readingMinutes), ?a.sportName].where((s) => s.isNotEmpty).join(' · ');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        RFadeIn(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(spacing: RSpace.sm, runSpacing: RSpace.sm, children: [
                RStatusPill(label: newsCategoryLabel(context, a.category), tone: RTone.accent),
                if (a.isSampleData) RSampleBadge(label: l10n.sampleData),
              ]),
              const SizedBox(height: RSpace.md),
              Semantics(header: true, child: Text(a.title, style: RType.title1)),
              const SizedBox(height: RSpace.sm),
              Text(meta, style: RType.footnote.copyWith(color: RColors.textSecondary)),
            ],
          ),
        ),
        if (a.summary != null) ...[
          const SizedBox(height: RSpace.xl),
          RFadeIn(
            index: 1,
            child: Container(
              padding: const EdgeInsetsDirectional.only(start: RSpace.md),
              decoration: const BoxDecoration(border: BorderDirectional(start: BorderSide(color: RColors.accent, width: 3))),
              child: Text(a.summary!, style: RType.title3.copyWith(fontWeight: FontWeight.w500, color: RColors.textPrimary)),
            ),
          ),
        ],
        const SizedBox(height: RSpace.xl),
        for (final (i, p) in a.paragraphs.indexed)
          RFadeIn(
            index: i + 2,
            child: Padding(
              padding: const EdgeInsets.only(bottom: RSpace.lg),
              child: SelectableText(p, style: RType.body.copyWith(height: 1.5, color: const Color(0xFFE3E7EF))),
            ),
          ),
        if (a.tournamentSlug != null) ...[
          const SizedBox(height: RSpace.md),
          RCard(
            highlight: true,
            onTap: () => context.push(Routes.tournament(a.tournamentSlug!)),
            child: Row(children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: RColors.accent.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(RRadius.sm)),
                alignment: Alignment.center,
                child: a.sportIcon != null
                    ? SportGlyph(a.sportIcon, size: 24, color: RColors.accent)
                    : const Icon(CupertinoIcons.flag_fill, color: RColors.accent),
              ),
              const SizedBox(width: RSpace.md),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(l10n.newsOpenTournament, style: RType.caption1.copyWith(color: RColors.accent, fontWeight: FontWeight.w700)),
                  const SizedBox(height: RSpace.xxs),
                  Text(a.tournamentName ?? '', style: RType.headline, maxLines: 2, overflow: TextOverflow.ellipsis),
                ]),
              ),
              const Icon(CupertinoIcons.chevron_forward, size: 17, color: RColors.textTertiary),
            ]),
          ),
        ],
      ],
    );
  }
}
