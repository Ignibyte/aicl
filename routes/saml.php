<?php

declare(strict_types=1);

use Aicl\Http\Controllers\SocialAuthController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    // SP Metadata endpoint (public, no auth required)
    // IdP administrators use this to register the SP
    Route::get('/auth/saml2/metadata', [SocialAuthController::class, 'samlMetadata'])
        ->name('saml.metadata');

    // SP-initiated SAML redirect — sends AuthnRequest to IdP
    Route::get('/auth/saml2/redirect', [SocialAuthController::class, 'samlRedirect'])
        ->name('saml.redirect');

    // SAML Assertion Consumer Service (ACS) — receives POST from IdP
    // CSRF must be exempt since IdP posts directly to this URL
    Route::post('/auth/saml2/callback', [SocialAuthController::class, 'samlCallback'])
        ->withoutMiddleware([PreventRequestForgery::class])
        ->name('saml.callback');
});
