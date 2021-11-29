<?php

use Illuminate\Support\Facades\Route;
use VueFileManager\Subscription\Support\Webhooks\WebhooksController;
use VueFileManager\Subscription\Domain\Plans\Controllers\PlansController;
use VueFileManager\Subscription\Domain\Plans\Controllers\GetPlansController;
use VueFileManager\Subscription\Domain\Plans\Actions\UpdatePlanFeatureAction;
use VueFileManager\Subscription\Domain\Plans\Controllers\GetPlanSubscribersController;
use VueFileManager\Subscription\Domain\Transactions\Controllers\GetTransactionsController;
use VueFileManager\Subscription\Domain\Subscriptions\Controllers\GetSubscriptionController;
use VueFileManager\Subscription\Domain\Subscriptions\Controllers\SwapSubscriptionController;
use VueFileManager\Subscription\Domain\Transactions\Controllers\GetAllTransactionsController;
use VueFileManager\Subscription\Domain\Subscriptions\Controllers\CancelSubscriptionController;
use VueFileManager\Subscription\Domain\Transactions\Controllers\GetUserTransactionsController;
use VueFileManager\Subscription\Domain\Subscriptions\Controllers\GetAllSubscriptionsController;
use VueFileManager\Subscription\Domain\Subscriptions\Controllers\GetUserSubscriptionController;

// System
Route::group(['prefix' => 'api/subscriptions', 'middleware' => ['api']], function () {
    Route::post('/{driver}/webhooks', WebhooksController::class);
    Route::get('/plans', GetPlansController::class);
});

// User
Route::group(['prefix' => 'api/subscriptions', 'middleware' => ['api', 'auth:sanctum']], function () {
    // Subscription
    Route::post('/swap/{plan}', SwapSubscriptionController::class);
    Route::post('/cancel', CancelSubscriptionController::class);
    Route::get('/detail', GetSubscriptionController::class);

    // Transactions
    Route::get('/transactions', GetTransactionsController::class);
});

// Admin
Route::group(['prefix' => 'api/subscriptions/admin', 'middleware' => ['api', 'auth:sanctum']], function () {
    // Plans
    Route::get('/plans/{plan}/subscribers', GetPlanSubscribersController::class);
    Route::patch('/plans/{plan}/features', UpdatePlanFeatureAction::class);
    Route::apiResource('/plans', PlansController::class);

    // User data
    Route::get('/users/{id}/transactions', GetUserTransactionsController::class);
    Route::get('/users/{id}/subscription', GetUserSubscriptionController::class);

    // Transactions
    Route::get('/transactions', GetAllTransactionsController::class);

    // Subscriptions
    Route::get('/', GetAllSubscriptionsController::class);
});
