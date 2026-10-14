<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Store screens
|--------------------------------------------------------------------------
|
| The store hangs from /mamayaya, with the same routes as the Astro project:
|
|   /mamayaya                       Listing (menu)
|   /mamayaya/{slug}                Product page with options (and the store's 404)
|   /mamayaya/carrito               Current order
|   /mamayaya/datos                 Contact and address
|   /mamayaya/pago                  Tip, method and total
|   /mamayaya/pago/transferencia    Destination account and detail
|   /mamayaya/pago/efectivo         Pickup location and detail
|   /mamayaya/recibido              Acknowledgement: steps, payment info and WhatsApp
|
| All eight are migrated and registered here. {slug} ALWAYS goes
| last: it's a one-segment wildcard and the fixed routes have to be declared
| first to win over it. That's why the slugs `carrito`, `datos`, `pago` and
| `recibido` are reserved.
|
*/

Route::get('/mamayaya', [MenuController::class, 'index'])->name('menu');
Route::get('/mamayaya/carrito', [CartController::class, 'show'])->name('cart');
Route::get('/mamayaya/datos', [DeliveryController::class, 'show'])->name('delivery');
Route::get('/mamayaya/pago', [PaymentController::class, 'summary'])->name('payment');
Route::get('/mamayaya/pago/transferencia', [PaymentController::class, 'transfer'])->name('payment.transfer');
Route::get('/mamayaya/pago/efectivo', [PaymentController::class, 'cash'])->name('payment.cash');
Route::get('/mamayaya/recibido', [ConfirmationController::class, 'show'])->name('confirmation');

/*
|--------------------------------------------------------------------------
| Old addresses
|--------------------------------------------------------------------------
|
| The root serves no page: it goes through the front door, /mamayaya. The rest
| are the ones that really existed when the screens lived at the root, listed
| one by one on purpose, without a wildcard: each line gets deleted the day no
| one has it bookmarked anymore, and a wildcard would hide the 404 of any
| made-up URL.
|
| /mamayaya/confirmado is where Mercado Pago payments created before
| 2026-10-09 come back to, since the gateway has it stored. Delete it when none
| are left.
|
*/

$legacy = [
    '/' => '/mamayaya',
    '/carrito' => '/mamayaya/carrito',
    '/datos' => '/mamayaya/datos',
    '/pago' => '/mamayaya/pago',
    '/pago/transferencia' => '/mamayaya/pago/transferencia',
    '/pago/efectivo' => '/mamayaya/pago/efectivo',
    '/recibido' => '/mamayaya/recibido',
    '/confirmado' => '/mamayaya/recibido',
    '/mamayaya/confirmado' => '/mamayaya/recibido',
];

foreach ($legacy as $from => $to) {
    Route::get($from, LegacyRedirectController::class)->defaults('to', $to);
}

/*
|--------------------------------------------------------------------------
| The product page, and the store's 404
|--------------------------------------------------------------------------
|
| ALWAYS LAST: a one-segment wildcard that would otherwise swallow the fixed
| routes above (/mamayaya/confirmado among them). Anything under /mamayaya that
| isn't a screen of its own lands here and leaves through its "not found".
|
*/

Route::get('/mamayaya/{slug}', [ProductController::class, 'show'])->name('product');
