<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's own account. Contains private fields; only ever returned to the owner.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pendingDeletion = $this->deletionRequests()->whereNull('cancelled_at')->whereNull('completed_at')->first();

        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'avatar_url' => $this->avatarUrl(),
            'city' => $this->city,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'status' => $this->status->value,
            'privacy' => $this->resolvedPrivacy(),
            'notification_preferences' => $this->resolvedNotificationPreferences(),
            'discipline_ids' => $this->disciplines()->pluck('disciplines.id'),
            'is_staff' => $this->isStaff(),
            'deletion_scheduled_for' => $pendingDeletion?->scheduled_for?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
