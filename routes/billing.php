<?php

use App\Http\Controllers\Admin\Billing as Admin;
use App\Http\Controllers\Billing as Student;
use App\Http\Controllers\Billing\CinetPayController;
use App\Http\Controllers\Billing\PayPalController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Membre 6 : abonnements, tokens, administration des offres
| À inclure dans routes/api.php :  require __DIR__ . '/billing.php';
| (Toutes ces routes sont préfixées par /api automatiquement.)
|--------------------------------------------------------------------------
*/

// Catalogue public
Route::get('/plans', [Student\PlanController::class, 'index']);

// Étudiant connecté
Route::middleware('auth:sanctum')->prefix('billing')->group(function () {
    Route::get('/subscription', [Student\SubscriptionController::class, 'current']);
    Route::get('/subscriptions', [Student\SubscriptionController::class, 'history']);
    Route::post('/checkout', [CinetPayController::class, 'checkout']);
    Route::post('/paypal/checkout', [PayPalController::class, 'checkout']);
    Route::post('/paypal/capture', [PayPalController::class, 'capture']);
    Route::post('/subscription/cancel', [Student\SubscriptionController::class, 'cancel']);

    Route::get('/tokens/balance', [Student\TokenController::class, 'balance']);
    Route::get('/tokens/history', [Student\TokenController::class, 'history']);
    Route::get('/tokens/by-report', [Student\TokenController::class, 'byReport']);
});

Route::post('/billing/cinetpay/notify', [CinetPayController::class, 'notify'])->middleware('throttle:60,1');

// Administrateur
Route::middleware(['auth:sanctum', AdminOnly::class])->prefix('admin/billing')->group(function () {
    Route::apiResource('plans', Admin\PlanController::class)->parameters(['plans' => 'plan']);

    Route::get('/subscriptions', [Admin\SubscriptionController::class, 'index']);
    Route::get('/subscriptions/{subscription}', [Admin\SubscriptionController::class, 'show']);
    Route::post('/subscriptions/{subscription}/activate', [Admin\SubscriptionController::class, 'activate']);
    Route::post('/subscriptions/{subscription}/suspend', [Admin\SubscriptionController::class, 'suspend']);
    Route::post('/subscriptions/{subscription}/reactivate', [Admin\SubscriptionController::class, 'reactivate']);
    Route::post('/subscriptions/{subscription}/cancel', [Admin\SubscriptionController::class, 'cancel']);

    Route::post('/users/{user}/subscribe', [Admin\SubscriptionController::class, 'assign']);
    Route::get('/users/{user}/tokens', [Admin\TokenController::class, 'show']);
    Route::post('/users/{user}/tokens/credit', [Admin\TokenController::class, 'credit']);
    Route::post('/users/{user}/tokens/adjust', [Admin\TokenController::class, 'adjust']);

    Route::get('/stats', [Admin\StatsController::class, 'index']);
});
