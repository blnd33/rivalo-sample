<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\UserNotification;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The signed-in user's inbox, push devices and badges. Users only ever see their own rows. */
class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = UserNotification::where('user_id', $user->id)->latest('id')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (UserNotification $n) => $this->json($n))->values(),
            'meta' => [
                'unread' => UserNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread' => UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count()]]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $n = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $n->read_at ??= now();
        $n->save();

        return response()->json(['data' => $this->json($n)]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['marked' => $count]]);
    }

    /** Register this phone for push notifications. The token is tied to this device's sign-in. */
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', Rule::in(['ios', 'android', 'web'])],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);
        $accessToken = $request->user()->currentAccessToken();

        DeviceToken::updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id,
            'personal_access_token_id' => $accessToken?->getKey(),
            'platform' => $data['platform'],
            'app_version' => $data['app_version'] ?? null,
            'last_seen_at' => now(),
        ]);

        return response()->json(['data' => ['registered' => true]], 201);
    }

    public function unregisterDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        DeviceToken::where('user_id', $request->user()->id)->where('token', $data['token'])->delete();

        return response()->json(['data' => ['registered' => false]]);
    }

    public function achievements(Request $request, AchievementService $achievements): JsonResponse
    {
        return response()->json(['data' => $achievements->progress($request->user())]);
    }

    private function json(UserNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'category' => $n->category,
            'title' => $n->title,
            'body' => $n->body,
            'link' => $n->link_kind ? ['kind' => $n->link_kind, 'ref' => $n->link_ref] : null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
