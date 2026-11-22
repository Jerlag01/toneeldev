<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\Admin\QrPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

Route::get('/voorstellingen', [ProductionController::class, 'index'])->name('productions.index');
Route::get('/voorstellingen/{production}', [ProductionController::class, 'show'])->name('productions.show');

// The bar: QR codes on the tables open /bestel?tafel=A3.
Route::get('/bestel', [ShopController::class, 'index'])->name('shop.index');
Route::post('/bestel', [ShopController::class, 'store'])->middleware('throttle:20,1')->name('shop.store');
Route::get('/bestel/bestelling/{token}', [ShopController::class, 'order'])->name('shop.order');
Route::get('/bestel/bestelling/{token}/status', [ShopController::class, 'status'])->middleware('throttle:60,1')->name('shop.status');
Route::get('/bestel/bestelling/{token}/betalen', [ShopController::class, 'retry'])->middleware('throttle:10,1')->name('shop.retry');

Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('/webhooks/sumup', [PaymentWebhookController::class, 'sumup'])->name('webhooks.sumup');

Route::get('/admin/bar/qr-print', QrPrintController::class)->middleware(['web', 'auth'])->name('admin.qr.print');

// The story moved from / to /story.
Route::permanentRedirect('/verhaal', '/story');

// CMS pages last, so they never shadow the routes above or the admin panel.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|livewire|storage|filament|api|bestel|webhooks|voorstellingen)[a-z0-9-]+$')
    ->name('page');
