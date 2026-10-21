<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProfileResource;
use App\Http\Resources\SportResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\FeatureFlags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only public data: app config, sports catalog, public profiles, user lookup.
 */
class PublicController extends Controller
{
    /** App configuration the mobile client needs. Contains no secrets. */
    public function config(): JsonResponse
    {
        return response()->json([
            'app' => ['name' => 'RIVALO', 'tagline' => 'Play. Win. Rise.'],
            'features' => FeatureFlags::public(),
            'supported_locales' => User::SUPPORTED_LOCALES,
            'support_contact' => config('rivalo.support_email'),
            'api_version' => 'v1',
        ]);
    }

    public function sports()
    {
        $sports = Sport::query()
            ->active()
            ->orderBy('sort_order')
            ->with(['disciplines' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn (Sport $s) => $s->disciplines->isNotEmpty())
            ->values();

        return SportResource::collection($sports);
    }

    public function profile(string $username): PublicProfileResource
    {
        $user = User::where('username', mb_strtolower($username))
            ->whereIn('status', [UserStatus::Active->value, UserStatus::PendingDeletion->value])
            ->firstOrFail();

        return PublicProfileResource::make($user);
    }

    /** Username search for invitations. Returns public summaries only. */
    public function searchUsers(Request $request)
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:30']]);
        $q = mb_strtolower($data['q']);

        $users = User::query()
            ->where('status', UserStatus::Active->value)
            ->where('username', 'like', addcslashes($q, '%_\\').'%')
            ->whereKeyNot($request->user()->id)
            ->whereNotIn('id', UserBlock::hiddenFrom($request->user()->id))
            ->orderBy('username')
            ->limit(10)
            ->get();

        return UserSummaryResource::collection($users);
    }
}
