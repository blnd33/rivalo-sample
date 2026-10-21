<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompetitionController;
use App\Http\Controllers\Api\V1\InboxController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NewsController;
use App\Http\Controllers\Api\V1\PublicController;
use App\Http\Controllers\Api\V1\SafetyController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RIVALO mobile API, version 1. Base path: /api/v1
|--------------------------------------------------------------------------
| Authentication: "Authorization: Bearer <token>" (Sanctum personal access token, one per device).
| Every write is authorized on the server (policies + services). The app hides nothing for security.
*/

Route::prefix('v1')->middleware('throttle:api')->group(function () {

    // ---------------------------------------------------------------- Public (no token)
    Route::get('config', [PublicController::class, 'config']);
    Route::get('catalog/sports', [PublicController::class, 'sports']);
    Route::get('users/{username}', [PublicController::class, 'profile'])->middleware('auth.optional')->where('username', '[A-Za-z0-9_.]+');
    Route::get('tournaments', [TournamentController::class, 'index'])->middleware('auth.optional');
    Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])->middleware('auth.optional');
    Route::get('tournaments/{tournament}/entries', [TournamentController::class, 'entries']);
    Route::get('teams/{team}', [TeamController::class, 'show'])->middleware('auth.optional');
    Route::get('tournaments/{tournament}/fixtures', [CompetitionController::class, 'fixtures'])->middleware('auth.optional');
    Route::get('matches/{match}', [CompetitionController::class, 'match'])->middleware('auth.optional')->whereNumber('match');
    Route::get('leaderboards', [CompetitionController::class, 'leaderboard'])->middleware('auth.optional');
    Route::get('seasons', [CompetitionController::class, 'seasons']);
    Route::get('news', [NewsController::class, 'index']);
    Route::get('news/{article:slug}', [NewsController::class, 'show']);
    Route::get('pages/{slug}', [SafetyController::class, 'page'])->where('slug', '[a-z0-9-]+');

    // Store (Milestone 8): browsing is public while the store_enabled flag is on.
    Route::get('store/products', [StoreController::class, 'products']);
    Route::get('store/products/{product:slug}', [StoreController::class, 'product']);
    Route::get('store/fulfillment', [StoreController::class, 'fulfillment']);
    // Approved sellers' shop pages (Milestone 9), public while the marketplace is switched on.
    Route::get('store/sellers', [MarketplaceController::class, 'sellers']);
    Route::get('store/sellers/{seller:slug}', [MarketplaceController::class, 'seller']);

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-email');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-email');
    });

    // ---------------------------------------------------------------- Signed in
    Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:auth-email');

        Route::prefix('me')->controller(MeController::class)->group(function () {
            Route::get('/', 'show');
            Route::patch('/', 'update');
            Route::post('avatar', 'uploadAvatar')->middleware('throttle:uploads');
            Route::delete('avatar', 'deleteAvatar');
            Route::put('disciplines', 'updateDisciplines');
            Route::patch('privacy', 'updatePrivacy');
            Route::patch('notification-preferences', 'updateNotificationPreferences');
            Route::put('password', 'updatePassword');
            Route::get('sessions', 'sessions');
            Route::delete('sessions/{tokenId}', 'revokeSession')->whereNumber('tokenId');
            Route::post('deletion-request', 'requestDeletion');
            Route::delete('deletion-request', 'cancelDeletion');
            Route::get('teams', 'teams');
            Route::get('invitations', 'invitations');
            Route::get('registrations', 'registrations');
            Route::get('points', [CompetitionController::class, 'myPoints']);
            Route::get('notifications', [InboxController::class, 'index']);
            Route::get('notifications/unread-count', [InboxController::class, 'unreadCount']);
            Route::post('notifications/read-all', [InboxController::class, 'markAllRead']);
            Route::post('notifications/{id}/read', [InboxController::class, 'markRead'])->whereNumber('id');
            Route::post('devices', [InboxController::class, 'registerDevice'])->middleware('throttle:uploads');
            Route::delete('devices', [InboxController::class, 'unregisterDevice']);
            Route::get('achievements', [InboxController::class, 'achievements']);
        });

        Route::get('users', [PublicController::class, 'searchUsers']);

        // Reports and blocking (Milestone 7). Reporting does not require a verified email.
        Route::post('reports', [SafetyController::class, 'report'])->middleware('throttle:reports');
        Route::get('me/blocks', [SafetyController::class, 'blocks']);

        // Bag and orders (Milestone 8). Placing an order needs a verified email (checked by StoreService).
        Route::get('me/bag', [StoreController::class, 'bag']);
        Route::put('me/bag/items/{variant}', [StoreController::class, 'setBagItem'])->whereNumber('variant');
        Route::delete('me/bag/items/{variant}', [StoreController::class, 'removeBagItem'])->whereNumber('variant');
        Route::post('orders', [StoreController::class, 'checkout'])->middleware('throttle:orders');
        Route::get('me/orders', [StoreController::class, 'orders']);
        Route::get('me/orders/{order:number}', [StoreController::class, 'order']);
        Route::post('me/orders/{order:number}/cancel', [StoreController::class, 'cancel']);

        // Selling on RIVALO (Milestone 9). Applying needs a verified email (checked by SellerService).
        Route::get('me/selling', [MarketplaceController::class, 'status']);
        Route::post('me/seller-application', [MarketplaceController::class, 'apply'])->middleware('throttle:applications');
        Route::post('me/seller-application/withdraw', [MarketplaceController::class, 'withdraw']);
        Route::post('users/{username}/block', [SafetyController::class, 'block'])->where('username', '[A-Za-z0-9_.]+');
        Route::delete('users/{username}/block', [SafetyController::class, 'unblock'])->where('username', '[A-Za-z0-9_.]+');

        // Creating or joining teams and registering requires a verified email.
        Route::middleware('verified')->group(function () {
            Route::post('teams', [TeamController::class, 'store']);
            Route::patch('teams/{team}', [TeamController::class, 'update']);
            Route::post('teams/{team}/logo', [TeamController::class, 'uploadLogo'])->middleware('throttle:uploads');
            Route::post('teams/{team}/invitations', [TeamController::class, 'invite']);
            Route::delete('teams/{team}/invitations/{invitation}', [TeamController::class, 'revokeInvitation'])->scopeBindings();
            Route::post('teams/{team}/leave', [TeamController::class, 'leave']);
            Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
            Route::patch('teams/{team}/members/{user}', [TeamController::class, 'changeMemberRole']);
            Route::post('teams/{team}/captain-transfer', [TeamController::class, 'requestCaptainTransfer']);
            Route::post('teams/{team}/disband', [TeamController::class, 'disband']);

            Route::post('invitations/{invitation}/accept', [TeamController::class, 'acceptInvitation']);
            Route::post('invitations/{invitation}/decline', [TeamController::class, 'declineInvitation']);
            Route::post('captain-transfers/{transfer}/respond', [TeamController::class, 'respondCaptainTransfer']);
            Route::delete('captain-transfers/{transfer}', [TeamController::class, 'cancelCaptainTransfer']);

            Route::get('tournaments/{tournament}/my-entry', [TournamentController::class, 'myEntry']);
            Route::post('tournaments/{tournament}/registrations', [TournamentController::class, 'register']);
            Route::put('registrations/{entry}/roster', [TournamentController::class, 'updateRoster']);
            Route::post('registrations/{entry}/cancel', [TournamentController::class, 'cancel']);
            Route::get('registrations/{entry}/check-in-code', [TournamentController::class, 'checkInCode']);
            Route::post('matches/{match}/results', [CompetitionController::class, 'submitResult'])->whereNumber('match')->middleware('throttle:uploads');
            Route::post('match-results/{result}/disputes', [CompetitionController::class, 'dispute'])->whereNumber('result')->middleware('throttle:uploads');
        });
    });
});
