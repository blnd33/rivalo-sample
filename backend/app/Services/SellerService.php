<?php

namespace App\Services;

use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use App\Enums\RoleName;
use App\Enums\SellerApplicationStatus;
use App\Enums\SellerStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\User;
use App\Support\Audit;
use App\Support\FeatureFlags;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Approved outside sellers (Milestone 9): applications from players, staff approval with the
 * terms of the signed agreement, and suspending or closing a shop.
 *
 * Nothing is assumed about a seller: the commission comes from the agreement staff enter at
 * approval (there is no default rate), and staff must confirm what they checked about the seller.
 * Staff never review their own application or change their own shop.
 */
class SellerService
{
    public function __construct(private readonly Notifier $notifier) {}

    public static function marketplaceOpen(): bool
    {
        return FeatureFlags::enabled(FeatureFlags::STORE) && FeatureFlags::enabled(FeatureFlags::MARKETPLACE);
    }

    public function assertMarketplaceOpen(): void
    {
        if (! self::marketplaceOpen()) {
            throw DomainRuleException::make('marketplace_closed', 'Selling on RIVALO is not open yet.', 404);
        }
    }

    // ---------------------------------------------------------------- Applications (players)

    /**
     * @param  array{business_name: string, contact_phone: string, city: string, categories: list<string>, description: string, link?: ?string}  $data
     */
    public function apply(User $user, array $data): SellerApplication
    {
        $this->assertMarketplaceOpen();
        if (! $user->isActive()) {
            throw DomainRuleException::make('account_unavailable', 'Your account cannot apply to sell.', 403);
        }
        if (! $user->hasVerifiedEmail()) {
            throw DomainRuleException::make('email_unverified', 'Verify your email address before applying.', 403);
        }
        $categories = array_values(array_unique(array_filter($data['categories'] ?? [], fn ($c) => ProductCategory::tryFrom((string) $c) !== null)));
        if ($categories === []) {
            throw DomainRuleException::make('categories_required', 'Choose what you would sell.');
        }

        return DB::transaction(function () use ($user, $data, $categories) {
            User::whereKey($user->id)->lockForUpdate()->first();
            if (Seller::where('user_id', $user->id)->exists()) {
                throw DomainRuleException::make('already_seller', 'You already have a shop on RIVALO.', 409);
            }
            if (SellerApplication::where('user_id', $user->id)->where('status', SellerApplicationStatus::Pending->value)->exists()) {
                throw DomainRuleException::make('application_pending', 'Your application is already being reviewed.', 409);
            }

            $application = SellerApplication::create([
                'user_id' => $user->id,
                'business_name' => trim($data['business_name']),
                'contact_phone' => trim($data['contact_phone']),
                'city' => trim($data['city']),
                'categories' => implode(',', $categories),
                'description' => trim($data['description']),
                'link' => filled($data['link'] ?? null) ? trim($data['link']) : null,
                'status' => SellerApplicationStatus::Pending,
            ]);
            Audit::record('seller.application_submitted', $application, actorId: $user->id);

            return $application;
        });
    }

    public function withdraw(SellerApplication $application, User $user): SellerApplication
    {
        if ($application->user_id !== $user->id) {
            throw DomainRuleException::make('not_found', 'Not found.', 404);
        }

        return DB::transaction(function () use ($application, $user) {
            $application = SellerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== SellerApplicationStatus::Pending) {
                throw DomainRuleException::make('application_closed', 'This application was already decided.', 409);
            }
            $application->update(['status' => SellerApplicationStatus::Withdrawn]);
            Audit::record('seller.application_withdrawn', $application, actorId: $user->id);

            return $application;
        });
    }

    // ---------------------------------------------------------------- Decisions (staff)

    private function assertStaff(User $staff, ?int $subjectUserId = null): void
    {
        if (! $staff->can('sellers.manage')) {
            throw DomainRuleException::make('forbidden', 'Only staff who manage sellers can do this.', 403);
        }
        if ($subjectUserId !== null && $subjectUserId === $staff->id) {
            throw DomainRuleException::make('conflict_of_interest', 'You cannot review or change your own shop. Ask another staff member.', 403);
        }
    }

    /**
     * @param  array{name: string, slug?: ?string, commission_bps: int, agreement_reference: string, verification_note: string, identity_checked: bool}  $data
     */
    public function approve(SellerApplication $application, User $staff, array $data): Seller
    {
        $this->assertStaff($staff, $application->user_id);
        if (($data['identity_checked'] ?? false) !== true) {
            throw DomainRuleException::make('verification_required', 'Confirm that you checked who the seller is before approving.');
        }
        $bps = (int) $data['commission_bps'];
        if ($bps < 0 || $bps > 10000) {
            throw DomainRuleException::make('invalid_commission', 'The commission must be between 0 % and 100 %.');
        }
        if (mb_strlen(trim((string) $data['agreement_reference'])) < 3) {
            throw DomainRuleException::make('agreement_required', 'Enter the reference of the signed seller agreement.');
        }
        if (mb_strlen(trim((string) $data['verification_note'])) < 10) {
            throw DomainRuleException::make('verification_required', 'Describe what you checked (for example the ID or business registration you saw).');
        }

        $seller = DB::transaction(function () use ($application, $staff, $data, $bps) {
            $application = SellerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== SellerApplicationStatus::Pending) {
                throw DomainRuleException::make('application_closed', 'This application was already decided.', 409);
            }
            $owner = User::whereKey($application->user_id)->lockForUpdate()->firstOrFail();
            if (! $owner->isActive()) {
                throw DomainRuleException::make('account_unavailable', 'The applicant\'s account is not active.', 409);
            }
            if (Seller::where('user_id', $owner->id)->exists()) {
                throw DomainRuleException::make('already_seller', 'This person already has a shop.', 409);
            }

            $name = trim($data['name']);
            $slug = $this->uniqueSlug(filled($data['slug'] ?? null) ? $data['slug'] : $name);
            $seller = Seller::create([
                'user_id' => $owner->id,
                'slug' => $slug,
                'name' => $name,
                'contact_phone' => $application->contact_phone,
                'contact_email' => $owner->email,
                'status' => SellerStatus::Active,
                'commission_bps' => $bps,
                'agreement_reference' => trim($data['agreement_reference']),
                'verification_note' => trim($data['verification_note']),
                'approved_by_id' => $staff->id,
                'approved_at' => now(),
            ]);
            $application->update([
                'status' => SellerApplicationStatus::Approved,
                'reviewed_by_id' => $staff->id,
                'reviewed_at' => now(),
                'seller_id' => $seller->id,
            ]);
            $owner->assignRole(RoleName::Seller->value);
            Audit::record('seller.approved', $seller, new: ['commission_bps' => $bps, 'agreement_reference' => $seller->agreement_reference], actorId: $staff->id);

            return $seller;
        });
        $this->notifier->send([$seller->user_id], 'seller_application_approved', ['shop' => $seller->name], ['seller', null], "seller_approved:{$seller->id}");

        return $seller;
    }

    public function reject(SellerApplication $application, User $staff, string $reason): SellerApplication
    {
        $this->assertStaff($staff, $application->user_id);
        if (mb_strlen(trim($reason)) < 3) {
            throw DomainRuleException::make('reason_required', 'A reason is required.');
        }

        $application = DB::transaction(function () use ($application, $staff, $reason) {
            $application = SellerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== SellerApplicationStatus::Pending) {
                throw DomainRuleException::make('application_closed', 'This application was already decided.', 409);
            }
            $application->update([
                'status' => SellerApplicationStatus::Rejected,
                'decision_note' => mb_substr(trim($reason), 0, 500),
                'reviewed_by_id' => $staff->id,
                'reviewed_at' => now(),
            ]);
            Audit::record('seller.application_rejected', $application, reason: $reason, actorId: $staff->id);

            return $application;
        });
        $this->notifier->send([$application->user_id], 'seller_application_rejected', ['reason' => $application->decision_note], ['seller', null], "seller_rejected:{$application->id}");

        return $application;
    }

    // ---------------------------------------------------------------- Shop status (staff)

    /** The shop's listings leave the store at once. Statements stay readable in the Seller Hub. */
    public function suspend(Seller $seller, User $staff, string $reason): Seller
    {
        return $this->changeStatus($seller, $staff, [SellerStatus::Active], SellerStatus::Suspended, $reason, 'seller_suspended');
    }

    public function reinstate(Seller $seller, User $staff): Seller
    {
        return $this->changeStatus($seller, $staff, [SellerStatus::Suspended], SellerStatus::Active, null, 'seller_reinstated');
    }

    /** Closing archives every listing. Unsold stock is handed back with "Returned to seller". */
    public function close(Seller $seller, ?User $staff, string $reason): Seller
    {
        return $this->changeStatus($seller, $staff, [SellerStatus::Active, SellerStatus::Suspended], SellerStatus::Closed, $reason, 'seller_closed');
    }

    private function changeStatus(Seller $seller, ?User $staff, array $from, SellerStatus $to, ?string $reason, string $message): Seller
    {
        if ($staff) {
            $this->assertStaff($staff, $seller->user_id);
        }
        if ($to !== SellerStatus::Active && mb_strlen(trim((string) $reason)) < 3) {
            throw DomainRuleException::make('reason_required', 'A reason is required.');
        }

        $seller = DB::transaction(function () use ($seller, $staff, $from, $to, $reason) {
            $seller = Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            if (! in_array($seller->status, $from, true)) {
                throw DomainRuleException::make('invalid_seller_transition', 'This shop is :status.', 409, ['status' => $seller->status->label()]);
            }
            $old = $seller->status;
            $seller->update(['status' => $to, 'status_reason' => $reason !== null ? mb_substr(trim($reason), 0, 500) : null]);
            if ($to === SellerStatus::Closed) {
                Product::where('seller_id', $seller->id)->where('status', '!=', ProductStatus::Archived->value)->update(['status' => ProductStatus::Archived->value]);
            }
            Audit::record('seller.status_changed', $seller, ['status' => $old->value], ['status' => $to->value], $reason, $staff?->id);

            return $seller;
        });
        if ($staff) {
            $this->notifier->send([$seller->user_id], $message, ['shop' => $seller->name, 'reason' => $seller->status_reason], ['seller', null]);
        }

        return $seller;
    }

    /** New orders use the new rate; lines already ordered keep the rate agreed at the time. */
    public function changeCommission(Seller $seller, User $staff, int $bps, string $note): Seller
    {
        $this->assertStaff($staff, $seller->user_id);
        if ($bps < 0 || $bps > 10000) {
            throw DomainRuleException::make('invalid_commission', 'The commission must be between 0 % and 100 %.');
        }
        if (mb_strlen(trim($note)) < 3) {
            throw DomainRuleException::make('agreement_required', 'Enter the reference of the signed seller agreement.');
        }

        return DB::transaction(function () use ($seller, $staff, $bps, $note) {
            $seller = Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $old = $seller->commission_bps;
            if ($old === $bps) {
                return $seller;
            }
            $seller->update(['commission_bps' => $bps, 'agreement_reference' => mb_substr(trim($note), 0, 120)]);
            Audit::record('seller.commission_changed', $seller, ['commission_bps' => $old], ['commission_bps' => $bps], $note, $staff->id);

            return $seller;
        });
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::limit(Str::slug($source), 60, '') ?: 'shop';
        $slug = $base;
        for ($i = 2; Seller::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
