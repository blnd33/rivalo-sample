<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Policy pages. Editing changes the draft only; players see a version once staff publish it.
 * A legal review can only be recorded by staff with legal.record, for the version that is live,
 * with the reviewer's name. Publishing a new version makes the review pending again.
 */
class PageService
{
    /**
     * @param  array{title_translations: array<string, ?string>, body_translations: array<string, ?string>}  $data
     */
    public function saveDraft(Page $page, User $staff, array $data): Page
    {
        $clean = fn (array $t) => array_filter(
            array_intersect_key(array_map(fn ($v) => is_string($v) ? trim($v) : null, $t), array_flip(Page::LOCALES)),
            fn ($v) => filled($v),
        );
        $titles = $clean($data['title_translations'] ?? []);
        $bodies = $clean($data['body_translations'] ?? []);
        if (blank($titles['en'] ?? null) || blank($bodies['en'] ?? null)) {
            throw DomainRuleException::make('page_english_required', 'The English title and text are required.');
        }

        $page->update(['title_translations' => $titles, 'body_translations' => $bodies, 'updated_by_id' => $staff->id]);
        Audit::record('page.draft_saved', $page, actorId: $staff->id);

        return $page;
    }

    public function publish(Page $page, User $staff, string $note): PageVersion
    {
        $note = trim($note);
        if (mb_strlen($note) < 3) {
            throw DomainRuleException::make('change_note_required', 'Describe what changed in this version.');
        }

        return DB::transaction(function () use ($page, $staff, $note) {
            $page = Page::whereKey($page->id)->lockForUpdate()->firstOrFail();
            if (blank($page->title_translations['en'] ?? null) || blank($page->body_translations['en'] ?? null)) {
                throw DomainRuleException::make('page_english_required', 'The English title and text are required.');
            }
            if ($page->isPublished() && ! $page->hasUnpublishedChanges()) {
                throw DomainRuleException::make('page_unchanged', 'Nothing changed since the last published version.');
            }

            $version = PageVersion::create([
                'page_id' => $page->id,
                'version' => $page->current_version + 1,
                'title_translations' => $page->title_translations,
                'body_translations' => $page->body_translations,
                'change_note' => mb_substr($note, 0, 500),
                'published_by_id' => $staff->id,
                'published_at' => now(),
            ]);
            $page->update(['current_version' => $version->version, 'published_at' => $version->published_at]);

            Audit::record('page.published', $page, new: [
                'version' => $version->version,
                'legal_status' => $page->legalStatus(),
                'sha256' => hash('sha256', json_encode([$version->title_translations, $version->body_translations])),
            ], reason: $note, actorId: $staff->id);

            return $version;
        });
    }

    public function recordLegalReview(Page $page, User $staff, string $reviewer, string $note): Page
    {
        if (! $staff->can('legal.record')) {
            throw DomainRuleException::make('forbidden', 'Only a super admin can record a legal review.', 403);
        }
        if (! $page->requires_legal_review) {
            throw DomainRuleException::make('legal_review_not_required', 'This page does not need a legal review.');
        }
        if (! $page->isPublished()) {
            throw DomainRuleException::make('page_not_published', 'Publish the page first. A review is recorded for a published version.');
        }
        if (mb_strlen(trim($reviewer)) < 3 || mb_strlen(trim($note)) < 3) {
            throw DomainRuleException::make('legal_review_details_required', 'Enter who reviewed it and a note (for example the date of their written approval).');
        }

        $page->update([
            'legal_reviewed_version' => $page->current_version,
            'legal_reviewed_at' => now(),
            'legal_reviewer' => mb_substr(trim($reviewer), 0, 160),
            'legal_review_note' => trim($note),
            'legal_recorded_by_id' => $staff->id,
        ]);
        Audit::record('page.legal_review_recorded', $page, new: ['version' => $page->current_version, 'reviewer' => $page->legal_reviewer], reason: $note, actorId: $staff->id);

        return $page;
    }
}
