<?php

use App\Http\Controllers\Api\CacheInvalidateController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ShopProxyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The store's internal face: /api/*
|--------------------------------------------------------------------------
|
| The screens' JavaScript calls these, never a person. They mirror the routes
| of the ERP they forward to, so the API didn't have to change: the server
| adds the session (X-Cart-Token) and the store key (X-Shop-Key).
|
| `api` group: no Laravel session and no CSRF. What protects the buyer's
| session is its httpOnly cookie with SameSite=Lax, which isn't sent on a
| cross-site POST.
|
*/

Route::get('/health', HealthController::class);

// Called by the BACKEND, not the browser: see CacheInvalidateController.
Route::post('/cache/invalidate', CacheInvalidateController::class);

Route::controller(ShopProxyController::class)->group(function () {
    Route::get('/cart', 'cart');
    Route::delete('/cart', 'clearCart');
    Route::post('/cart/items', 'addItem');
    Route::patch('/cart/items/{item}', 'updateItem');
    Route::delete('/cart/items/{item}', 'removeItem');

    Route::get('/shipping/quote', 'lastQuote');
    Route::post('/shipping/quote', 'quote');

    Route::post('/checkout', 'checkout');
    Route::post('/orders/{id}/payment', 'payment');

    Route::post('/store/phones', 'registerPhone');
});
