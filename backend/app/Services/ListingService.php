<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Seller listings (Milestone 9). A seller prepares a listing in the Seller Hub and submits it;
 * staff approve it (it goes on sale) or ask for changes with a note. To change a live listing the
 * seller takes it back to draft, which removes it from the store until it is approved again.
 */
class ListingService
{
    public function __construct(private readonly Notifier $notifier) {}

    // ---------------------------------------------------------------- Seller

    private function ownListing(Product $product, User $user): Seller
    {
        $seller = $product->seller_id ? Seller::find($product->seller_id) : null;
        if (! $seller || $seller->user_id !== $user->id) {
            throw DomainRuleException::make('not_found', 'Not found.', 404);
        }
        if (! $seller->isActive()) {
            throw DomainRuleException::make('seller_inactive', 'Your shop is :status. Contact RIVALO.', 403, ['status' => $seller->status->label()]);
        }

        return $seller;
    }

    public function submit(Product $product, User $user): Product
    {
        $this->ownListing($product, $user);

        return DB::transaction(function () use ($product, $user) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $product->status->sellerEditable()) {
                throw DomainRuleException::make('listing_not_editable', 'This listing is :status.', 409, ['status' => $product->status->label()]);
            }
            if (blank($product->name_translations['en'] ?? null)) {
                throw DomainRuleException::make('listing_incomplete', 'Add an English name.');
            }
            if (! $product->variants()->where('is_active', true)->exists()) {
                throw DomainRuleException::make('listing_incomplete', 'Add at least one size or option.');
            }
            if (! $product->images()->exists()) {
                throw DomainRuleException::make('listing_incomplete', 'Add at least one photo.');
            }
            if ($product->currency !== StoreService::currency()) {
                throw DomainRuleException::make('listing_incomplete', 'Prices must be in :currency.', 422, ['currency' => StoreService::currency()]);
            }
            $product->update(['status' => ProductStatus::PendingReview, 'submitted_at' => now()]);
            Audit::record('listing.submitted', $product, actorId: $user->id);

            return $product;
        });
    }

    /** Back to draft, out of the store (to edit a live listing, or to stop a review). */
    public function withdraw(Product $product, User $user): Product
    {
        $this->ownListing($product, $user);

        return DB::transaction(function () use ($product, $user) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! in_array($product->status, [ProductStatus::PendingReview, ProductStatus::Active], true)) {
                throw DomainRuleException::make('listing_not_editable', 'This listing is :status.', 409, ['status' => $product->status->label()]);
            }
            $old = $product->status;
            $product->update(['status' => ProductStatus::Draft, 'is_featured' => false]);
            Audit::record('listing.withdrawn', $product, ['status' => $old->value], ['status' => 'draft'], actorId: $user->id);

            return $product;
        });
    }

    public function archive(Product $product, User $user): Product
    {
        $this->ownListing($product, $user);

        return DB::transaction(function () use ($product, $user) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $product->status->sellerEditable()) {
                throw DomainRuleException::make('listing_not_editable', 'This listing is :status.', 409, ['status' => $product->status->label()]);
            }
            $product->update(['status' => ProductStatus::Archived]);
            Audit::record('listing.archived', $product, actorId: $user->id);

            return $product;
        });
    }

    // ---------------------------------------------------------------- Staff review

    private function reviewable(Product $product, User $staff): Seller
    {
        if (! $staff->can('sellers.manage')) {
            throw DomainRuleException::make('forbidden', 'Only staff who manage sellers can do this.', 403);
        }
        $seller = $product->seller_id ? Seller::find($product->seller_id) : null;
        if (! $seller) {
            throw DomainRuleException::make('not_a_listing', 'This is an official RIVALO product. Edit its status directly.');
        }
        if ($seller->user_id === $staff->id) {
            throw DomainRuleException::make('conflict_of_interest', 'You cannot review or change your own shop. Ask another staff member.', 403);
        }

        return $seller;
    }

    public function approve(Product $product, User $staff): Product
    {
        $seller = $this->reviewable($product, $staff);

        $product = DB::transaction(function () use ($product, $staff) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($product->status !== ProductStatus::PendingReview) {
                throw DomainRuleException::make('listing_not_pending', 'Only listings waiting for review can be approved.', 409);
            }
            $product->update(['status' => ProductStatus::Active, 'reviewed_at' => now(), 'reviewed_by_id' => $staff->id, 'review_note' => null]);
            Audit::record('listing.approved', $product, actorId: $staff->id);

            return $product;
        });
        $this->notifier->send([$seller->user_id], 'seller_listing_approved', ['product' => $product->name('en')], ['seller', null], "listing_approved:{$product->id}:{$product->reviewed_at?->timestamp}");

        return $product;
    }

    /** Also used to take a live listing down (for example after a customer complaint). */
    public function requestChanges(Product $product, User $staff, string $note): Product
    {
        $seller = $this->reviewable($product, $staff);
        if (mb_strlen(trim($note)) < 3) {
            throw DomainRuleException::make('reason_required', 'A reason is required.');
        }

        $product = DB::transaction(function () use ($product, $staff, $note) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! in_array($product->status, [ProductStatus::PendingReview, ProductStatus::Active], true)) {
                throw DomainRuleException::make('listing_not_pending', 'Only listings waiting for review or on sale can be sent back.', 409);
            }
            $old = $product->status;
            $product->update([
                'status' => ProductStatus::ChangesRequested,
                'review_note' => mb_substr(trim($note), 0, 500),
                'reviewed_at' => now(),
                'reviewed_by_id' => $staff->id,
                'is_featured' => false,
            ]);
            Audit::record('listing.changes_requested', $product, ['status' => $old->value], ['status' => 'changes_requested'], $note, $staff->id);

            return $product;
        });
        $this->notifier->send([$seller->user_id], 'seller_listing_changes_requested', ['product' => $product->name('en'), 'note' => $product->review_note], ['seller', null]);

        return $product;
    }
}
