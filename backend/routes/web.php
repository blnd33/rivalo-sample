<?php

use App\Http\Controllers\Web\AccountLinkController;
use App\Http\Middleware\SetAdminLocale;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'));

// Admin panel and Seller Hub language switch (English / Arabic). Only changes the caller's own preference.
Route::get('/admin-language/{locale}', function (Request $request, string $locale) {
    $request->session()->put(SetAdminLocale::SESSION_KEY, $locale);
    $request->user()?->forceFill(['admin_locale' => $locale])->save();

    $back = url()->previous();

    // Back to the page it was chosen on, in the admin panel or the Seller Hub (Milestone 9).
    $inPanel = str_starts_with($back, url('/admin')) || str_starts_with($back, url('/seller'));

    return redirect()->to($inPanel ? $back : url('/admin'));
})->whereIn('locale', Locales::ADMIN)->middleware('throttle:30,1')->name('admin.language');

// Links opened from emails. They work in any browser without being signed in there.
Route::get('/email/verify/{id}/{hash}', [AccountLinkController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/reset-password/{token}', [AccountLinkController::class, 'showResetForm'])
    ->middleware('throttle:20,1')
    ->name('password.reset');

Route::post('/reset-password', [AccountLinkController::class, 'resetPassword'])
    ->middleware('throttle:6,1')
    ->name('password.update');
