<?php

namespace App\Filament\Widgets;

use App\Console\Commands\LaunchCheck;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\ResultStatus;
use App\Enums\SellerApplicationStatus;
use App\Enums\SellerStatus;
use App\Enums\StaffRole;
use App\Filament\Pages\StoreReport;
use App\Filament\Pages\TournamentFixtures;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\PolicyPages\PageResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\SellerApplications\SellerApplicationResource;
use App\Filament\Resources\Sellers\SellerResource;
use App\Models\MatchResult;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Report;
use App\Models\ResultDispute;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\Tournament;
use App\Models\User;
use App\Services\StoreService;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard to-do list for the signed-in staff member. Each row is a real count from the
 * database and appears only if that person is allowed to act on it.
 */
class NeedsAttention extends Widget
{
    protected string $view = 'filament.widgets.needs-attention';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    /** @return list<array{label: string, detail: ?string, count: int, url: ?string, tone: string}> */
    public function items(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user->can('moderation.manage')) {
            $open = Report::open()->count();
            if ($open > 0) {
                $oldest = Report::open()->min('created_at');
                $items[] = $this->item(__('Open reports'), __('Oldest received :time', ['time' => Carbon::parse($oldest)->diffForHumans()]), $open, ReportResource::getUrl(), 'danger');
            }
        }

        foreach ($this->reviewableTournaments($user) as $t) {
            $pending = MatchResult::where('status', ResultStatus::Submitted->value)->whereHas('match', fn ($q) => $q->where('tournament_id', $t->id))->count();
            $disputes = ResultDispute::where('status', DisputeStatus::Open->value)->whereHas('match', fn ($q) => $q->where('tournament_id', $t->id))->count();
            if ($pending > 0) {
                $items[] = $this->item(__('Results waiting for approval'), $t->name, $pending, TournamentFixtures::getUrl(['tournament' => $t->id]), 'warning');
            }
            if ($disputes > 0) {
                $items[] = $this->item(__('Open result disputes'), $t->name, $disputes, TournamentFixtures::getUrl(['tournament' => $t->id]), 'danger');
            }
        }

        if ($user->can('store.manage')) {
            $new = Order::where('status', OrderStatus::Placed->value)->count();
            if ($new > 0) {
                $items[] = $this->item(__('New orders to confirm'), __('Customers are waiting for confirmation.'), $new, OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'placed']]]), 'warning');
            }
            $waiting = Order::whereIn('status', [OrderStatus::Ready->value, OrderStatus::OutForDelivery->value])->where('ready_at', '<', now()->subDays(7))->count();
            if ($waiting > 0) {
                $items[] = $this->item(__('Orders not collected for over a week'), __('Contact the customer, or cancel to put the items back in stock.'), $waiting, OrderResource::getUrl('index'), 'warning');
            }
            $low = ProductVariant::where('is_active', true)->where('stock', '<=', 3)->whereHas('product', fn ($q) => $q->onSale())->count();
            if ($low > 0) {
                $items[] = $this->item(__('Sizes low or sold out'), __('3 or fewer left.'), $low, StoreReport::getUrl(), 'info');
            }
        }

        // Marketplace (Milestone 9)
        if ($user->can('sellers.manage')) {
            $applications = SellerApplication::where('status', SellerApplicationStatus::Pending->value)->count();
            if ($applications > 0) {
                $items[] = $this->item(__('Seller applications to review'), __('Players who asked to sell on RIVALO.'), $applications, SellerApplicationResource::getUrl(), 'warning');
            }
            $listings = Product::where('status', ProductStatus::PendingReview->value)->count();
            if ($listings > 0) {
                $items[] = $this->item(__('Seller products to review'), __('Check name, photos and price before they go on sale.'), $listings, ProductResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending_review']]]), 'warning');
            }
            $held = Seller::where('status', '!=', SellerStatus::Active->value)
                ->whereHas('products.variants', fn ($q) => $q->where('stock', '>', 0))->count();
            if ($held > 0) {
                $items[] = $this->item(__('Paused or closed shops with stock here'), __('Hand the items back with "Returned to seller" when they collect them.'), $held, SellerResource::getUrl(), 'info');
            }
        }
        if ($user->can('finance.manage')) {
            $currency = StoreService::currency();
            $owed = Seller::whereHas('ledger', fn ($q) => $q->where('currency', $currency))
                ->withSum(['ledger as owed' => fn ($q) => $q->where('currency', $currency)], 'amount_minor')
                ->get()->filter(fn (Seller $s) => (int) $s->owed > 0)->count();
            if ($owed > 0) {
                $items[] = $this->item(__('Sellers waiting for a payout'), __('RIVALO owes them for completed sales. Record each payout after you pay.'), $owed, SellerResource::getUrl(), 'info');
            }
        }

        if ($user->can('content.manage')) {
            $pages = Page::whereIn('slug', [Page::TERMS, Page::PRIVACY, Page::GUIDELINES])->get();
            $unpublished = $pages->reject->isPublished()->count() + (3 - $pages->count());
            if ($unpublished > 0) {
                $items[] = $this->item(__('Policy pages not published'), __('Players cannot read them in the app yet.'), $unpublished, PageResource::getUrl(), 'danger');
            }
            $pending = $pages->filter(fn (Page $p) => $p->isPublished() && $p->legalStatus() === 'pending')->count();
            if ($pending > 0) {
                $items[] = $this->item(__('Policy pages pending legal review'), __('Shown in the app as a draft. Needed before public launch (D6).'), $pending, PageResource::getUrl(), 'warning');
            }
        }

        if ($user->can('feature_flags.manage')) {
            $beat = Cache::get(LaunchCheck::HEARTBEAT_KEY);
            if (app()->isProduction() && (! $beat || Carbon::parse($beat)->lt(now()->subMinutes(10)))) {
                $items[] = $this->item(__('Scheduler is not running'), __('Reminders and scheduled announcements are not being sent.'), 1, null, 'danger');
            }
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            if ($failed > 0) {
                $items[] = $this->item(__('Failed background jobs'), __('Some emails or phone notifications were not sent. Run php artisan queue:failed on the server.'), $failed, null, 'danger');
            }
        }

        return $items;
    }

    /** @return iterable<Tournament> tournaments with results this person may approve */
    private function reviewableTournaments(User $user): iterable
    {
        $ids = MatchResult::where('status', ResultStatus::Submitted->value)
            ->join('matches', 'matches.id', '=', 'match_results.match_id')
            ->pluck('matches.tournament_id')
            ->merge(ResultDispute::where('status', DisputeStatus::Open->value)->join('matches', 'matches.id', '=', 'result_disputes.match_id')->pluck('matches.tournament_id'))
            ->unique();
        if ($ids->isEmpty()) {
            return [];
        }

        return Tournament::whereIn('id', $ids)
            ->when(! $user->can('tournaments.manage_all'), fn ($q) => $q->whereHas('staff', fn ($s) => $s->where('user_id', $user->id)->where('role', StaffRole::Referee->value)))
            ->when(! $user->can('tournaments.manage_all') && ! $user->can('results.review_assigned'), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('starts_at')
            ->get(['id', 'name']);
    }

    private function item(string $label, ?string $detail, int $count, ?string $url, string $tone): array
    {
        return compact('label', 'detail', 'count', 'url', 'tone');
    }
}
