import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';

/// A published news article. The list endpoint omits [body]; the detail endpoint includes it.
class NewsArticle {
  const NewsArticle({
    required this.id,
    required this.slug,
    required this.title,
    required this.category,
    required this.isFeatured,
    required this.readingMinutes,
    required this.isSampleData,
    this.summary,
    this.body,
    this.coverUrl,
    this.publishedAt,
    this.sportId,
    this.sportName,
    this.sportIcon,
    this.tournamentSlug,
    this.tournamentName,
  });

  final int id;
  final String slug;
  final String title;
  final String? summary;
  final String? body;
  final String? coverUrl;
  final String category;
  final bool isFeatured;
  final DateTime? publishedAt;
  final int readingMinutes;
  final int? sportId;
  final String? sportName;
  final String? sportIcon;
  final String? tournamentSlug;
  final String? tournamentName;
  final bool isSampleData;

  /// Body split into paragraphs on blank lines (editors write plain text).
  List<String> get paragraphs => (body ?? '').split(RegExp(r'\n\s*\n')).map((p) => p.trim()).where((p) => p.isNotEmpty).toList();

  factory NewsArticle.fromJson(Json j) {
    final sport = j.objOrNull('sport');
    final tournament = j.objOrNull('tournament');
    return NewsArticle(
      id: j.integer('id'),
      slug: j.str('slug'),
      title: j.str('title'),
      summary: j.strOrNull('summary'),
      body: j.strOrNull('body'),
      coverUrl: j.strOrNull('cover_url'),
      category: j.str('category', 'announcement'),
      isFeatured: j.boolean('is_featured'),
      publishedAt: j.date('published_at'),
      readingMinutes: j.integer('reading_minutes', 1),
      sportId: sport?.intOrNull('id'),
      sportName: sport?.strOrNull('name'),
      sportIcon: sport?.strOrNull('icon'),
      tournamentSlug: tournament?.strOrNull('slug'),
      tournamentName: tournament?.strOrNull('name'),
      isSampleData: j.boolean('is_sample_data'),
    );
  }
}

class NewsRepository {
  NewsRepository(this._api);

  final ApiClient _api;

  Future<List<NewsArticle>> list({String? category, bool featured = false, int perPage = 20}) async {
    final json = await _api.get('/news', query: {'category': category, 'featured': featured ? 1 : null, 'per_page': perPage});
    return json.list('data', NewsArticle.fromJson);
  }

  Future<NewsArticle> detail(String slug) async {
    final json = await _api.get('/news/$slug');
    return NewsArticle.fromJson(json.obj('data'));
  }
}

final newsRepositoryProvider = Provider((ref) => NewsRepository(ref.watch(apiClientProvider)));

/// Articles for the photo slider on Home and News.
final featuredNewsProvider = FutureProvider.autoDispose<List<NewsArticle>>((ref) {
  return ref.watch(newsRepositoryProvider).list(featured: true, perPage: 6);
});

/// News feed, optionally filtered by category (null = all).
final newsListProvider = FutureProvider.autoDispose.family<List<NewsArticle>, String?>((ref, category) {
  return ref.watch(newsRepositoryProvider).list(category: category);
});

final newsArticleProvider = FutureProvider.autoDispose.family<NewsArticle, String>((ref, slug) {
  return ref.watch(newsRepositoryProvider).detail(slug);
});
