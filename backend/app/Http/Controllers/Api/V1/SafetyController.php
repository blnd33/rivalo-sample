<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportReason;
use App\Enums\ReportSubject;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserSummaryResource;
use App\Models\Page;
use App\Models\Team;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reports, blocking and policy pages (Milestone 7).
 * A report's outcome and the reporter's identity are never returned to the reported player.
 */
class SafetyController extends Controller
{
    public function __construct(private readonly ModerationService $moderation) {}

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::enum(ReportSubject::class)],
            'subject' => ['required_unless:subject_type,app', 'nullable', 'string', 'max:80'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1000'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);
        $type = ReportSubject::from($data['subject_type']);

        $subject = match ($type) {
            ReportSubject::User => User::where('username', mb_strtolower($data['subject']))
                ->whereIn('status', [UserStatus::Active->value, UserStatus::PendingDeletion->value, UserStatus::Suspended->value])
                ->firstOrFail(),
            ReportSubject::Team => Team::where('slug', $data['subject'])->firstOrFail(),
            ReportSubject::App => null,
        };

        $report = $this->moderation->report(
            $request->user(), $type, $subject, ReportReason::from($data['reason']), $data['details'] ?? null,
            ['app_version' => $data['app_version'] ?? null, 'platform' => $data['platform'] ?? null],
        );

        // Only the reporter's own receipt: no staff names, no outcome.
        return response()->json(['data' => ['id' => $report->id, 'status' => 'received']], 201);
    }

    public function blocks(Request $request)
    {
        $ids = UserBlock::where('blocker_id', $request->user()->id)->latest('id')->pluck('blocked_id');
        $users = User::whereIn('id', $ids)->get()->sortBy(fn (User $u) => $ids->search($u->id))->values();

        return UserSummaryResource::collection($users);
    }

    public function block(Request $request, string $username): JsonResponse
    {
        $this->moderation->block($request->user(), $this->findUser($username));

        return response()->json(['data' => ['blocked' => true]]);
    }

    public function unblock(Request $request, string $username): JsonResponse
    {
        $this->moderation->unblock($request->user(), $this->findUser($username));

        return response()->json(['data' => ['blocked' => false]]);
    }

    /** Published policy text in the app language (English when not translated yet). */
    public function page(Request $request, string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->where('current_version', '>', 0)->with('currentVersion')->firstOrFail();
        $text = $page->currentVersion->localized(app()->getLocale());

        return response()->json(['data' => [
            'slug' => $page->slug,
            'title' => $text['title'],
            'body' => $text['body'],
            'locale' => $text['locale'],
            'translation_pending' => $text['fallback'],
            'version' => $page->current_version,
            'published_at' => $page->published_at?->toIso8601String(),
            // Never claims approval that was not recorded by staff for this exact version.
            'legal_review' => [
                'required' => $page->requires_legal_review,
                'status' => $page->legalStatus(),
            ],
        ]]);
    }

    private function findUser(string $username): User
    {
        return User::where('username', mb_strtolower($username))
            ->where('status', '!=', UserStatus::Deleted->value)
            ->firstOrFail();
    }
}
