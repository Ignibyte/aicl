<?php

declare(strict_types=1);

use Aicl\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:10,1'])->group(function (): void {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect');

    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback');
});
