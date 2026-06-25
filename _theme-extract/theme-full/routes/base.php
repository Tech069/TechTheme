<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

Route::get('/api/public/eggs', [\Pterodactyl\Http\Controllers\Api\PublicEggController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->middleware('throttle:30,1')
    ->name('api.public.eggs');

Route::get('/theme/hyperv2', [Base\HyperV2ThemePublicController::class, 'show'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->middleware('throttle:60,1');

Route::get('/language/available', [Base\LanguageController::class, 'available'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class]);
Route::patch('/language', [Base\LanguageController::class, 'set'])
    ->name('language.set');

Route::get('/referral/{code}', [Pterodactyl\Http\Controllers\Auth\ReferralController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class]);

Route::get('/status', [Base\PublicStatusPageController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->middleware('throttle:30,1')
    ->name('public.status');

Route::get('/public/stats', [Base\PublicStatsController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->middleware('throttle:30,1')
    ->name('public.stats');

Route::get('/health', [Base\HealthController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->middleware('throttle:10,1')
    ->name('health');

Route::get('/docs/{path?}', [Base\DocumentationController::class, 'show'])
    ->where('path', '.*')
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class]);

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon|health)).+');
