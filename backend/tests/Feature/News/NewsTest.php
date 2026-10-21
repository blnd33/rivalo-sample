<?php

namespace Tests\Feature\News;

use App\Enums\RoleName;
use App\Enums\TournamentStatus;
use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Filament\Resources\NewsArticles\Pages\CreateNewsArticle;
use App\Models\AuditLog;
use App\Models\NewsArticle;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Livewire\Livewire;
use Tests\TestCase;

class NewsTest extends TestCase
{
    private function article(array $overrides = []): NewsArticle
    {
        $article = new NewsArticle(array_merge([
            'title' => 'Pilot weekend announced',
            'summary' => 'Short summary',
            'body' => 'First paragraph.'."\n\n".'Second paragraph.',
            'category' => 'announcement',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ], $overrides));
        $article->save();

        return $article;
    }

    private function editor(): User
    {
        $user = $this->staff(RoleName::ContentEditor);
        $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        return $user;
    }

    public function test_guests_can_read_published_news(): void
    {
        $article = $this->article(['is_featured' => true, 'sport_id' => $this->sport('padel')->id]);

        $this->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $article->slug)
            ->assertJsonPath('data.0.sport.icon', 'padel')
            ->assertJsonPath('data.0.is_featured', true)
            ->assertJsonMissingPath('data.0.body')
            ->assertJsonMissingPath('data.0.author_id');

        $this->getJson("/api/v1/news/{$article->slug}")
            ->assertOk()
            ->assertJsonPath('data.body', $article->body)
            ->assertJsonPath('data.reading_minutes', 1)
            ->assertJsonMissingPath('data.author');
    }

    public function test_drafts_scheduled_and_archived_news_stay_hidden(): void
    {
        $draft = $this->article(['title' => 'Draft', 'status' => 'draft', 'published_at' => null]);
        $scheduled = $this->article(['title' => 'Scheduled', 'published_at' => now()->addDay()]);
        $archived = $this->article(['title' => 'Archived', 'status' => 'archived']);
        $live = $this->article(['title' => 'Live']);

        $slugs = collect($this->getJson('/api/v1/news')->assertOk()->json('data'))->pluck('slug');
        $this->assertSame([$live->slug], $slugs->all());

        foreach ([$draft, $scheduled, $archived] as $hidden) {
            $this->getJson("/api/v1/news/{$hidden->slug}")->assertNotFound();
        }
    }

    public function test_language_specific_articles_only_reach_that_language(): void
    {
        $all = $this->article(['title' => 'For everyone']);
        $arabic = $this->article(['title' => 'Arabic only', 'locale' => 'ar']);

        $en = collect($this->getJson('/api/v1/news', ['Accept-Language' => 'en'])->json('data'))->pluck('slug');
        $ar = collect($this->getJson('/api/v1/news', ['Accept-Language' => 'ar'])->json('data'))->pluck('slug');

        $this->assertEqualsCanonicalizing([$all->slug], $en->all());
        $this->assertEqualsCanonicalizing([$all->slug, $arabic->slug], $ar->all());
        $this->getJson("/api/v1/news/{$arabic->slug}", ['Accept-Language' => 'en'])->assertNotFound();
    }

    public function test_featured_filter_and_newest_first(): void
    {
        $old = $this->article(['title' => 'Old featured', 'is_featured' => true, 'published_at' => now()->subDays(3)]);
        $new = $this->article(['title' => 'New featured', 'is_featured' => true, 'published_at' => now()->subHour()]);
        $this->article(['title' => 'Not featured']);

        $this->getJson('/api/v1/news?featured=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', $new->slug)
            ->assertJsonPath('data.1.slug', $old->slug);
    }

    public function test_linked_tournament_is_hidden_while_it_is_a_draft(): void
    {
        $draft = $this->tournament(status: TournamentStatus::Draft);
        $public = $this->tournament();

        $hiddenLink = $this->article(['title' => 'About a draft', 'tournament_id' => $draft->id]);
        $visibleLink = $this->article(['title' => 'About a public one', 'tournament_id' => $public->id]);

        $this->getJson("/api/v1/news/{$hiddenLink->slug}")->assertOk()->assertJsonPath('data.tournament', null);
        $this->getJson("/api/v1/news/{$visibleLink->slug}")->assertOk()->assertJsonPath('data.tournament.slug', $public->slug);
    }

    public function test_publishing_without_a_date_publishes_now_and_slugs_are_unique(): void
    {
        $a = $this->article(['title' => 'Same title', 'published_at' => null]);
        $b = $this->article(['title' => 'Same title']);

        $this->assertNotNull($a->published_at);
        $this->assertSame('same-title', $a->slug);
        $this->assertSame('same-title-2', $b->slug);
    }

    public function test_only_content_editors_manage_news_in_admin(): void
    {
        $organizer = $this->staff(RoleName::TournamentOrganizer);
        $organizer->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        $this->actingAs($organizer)->get(NewsArticleResource::getUrl('index'))->assertForbidden();
        $this->actingAs($this->editor())->get(NewsArticleResource::getUrl('index'))->assertOk();
    }

    public function test_editor_creates_an_article_with_author_and_audit(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor);

        Livewire::test(CreateNewsArticle::class)
            ->fillForm([
                'title' => 'Registration opens Friday',
                'body' => 'Details for players.',
                'category' => 'tournament',
                'status' => 'published',
                'is_featured' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = NewsArticle::where('title', 'Registration opens Friday')->firstOrFail();
        $this->assertSame($editor->id, $article->author_id);
        $this->assertNotNull($article->published_at);
        $this->assertTrue(AuditLog::where('action', 'news.created')->where('subject_id', $article->id)->exists());
    }

    public function test_published_articles_cannot_be_deleted(): void
    {
        $editor = $this->editor();

        $this->assertFalse($editor->can('delete', $this->article()));
        $this->assertTrue($editor->can('delete', $this->article(['title' => 'Draft', 'status' => 'draft', 'published_at' => null])));
    }
}
