<?php

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PwaController;
use App\Http\Controllers\Storefront\SeoController;
use App\Http\Controllers\Storefront\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Catalogue
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/{slug}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/collections', [CatalogController::class, 'collections'])->name('collections.index');
Route::get('/collections/{slug}', [CatalogController::class, 'collection'])->name('collections.show');
Route::get('/product/{slug}', [CatalogController::class, 'product'])->name('products.show');
Route::post('/product/{slug}/reviews', [CatalogController::class, 'storeReview'])->middleware('throttle:5,1')->name('products.reviews.store');
Route::get('/search', [CatalogController::class, 'search'])->name('search');

// Editorial
Route::get('/journal', [CatalogController::class, 'journal'])->name('journal.index');
Route::get('/journal/{slug}', [CatalogController::class, 'post'])->name('journal.show');

// Bag & checkout
Route::get('/cart', [CartController::class, 'page'])->name('cart');
Route::get('/cart/items', [CartController::class, 'items'])->name('cart.items');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/items/{key}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{key}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::post('/cart/coupon', [CartController::class, 'coupon'])->name('cart.coupon');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::post('/cart/shipping', [CartController::class, 'shipping'])->name('cart.shipping');
Route::get('/checkout', [CartController::class, 'checkout'])->name('checkout');
Route::post('/checkout', [CartController::class, 'placeOrder'])->middleware('throttle:10,1')->name('checkout.place');
Route::get('/order/{number}/confirmation', [CartController::class, 'confirmation'])->name('orders.confirmation');
Route::match(['get', 'post'], '/track-order', [CatalogController::class, 'track'])->name('track');

// Forms
Route::post('/newsletter', [CartController::class, 'subscribe'])->name('newsletter');
Route::post('/contact', [CatalogController::class, 'contactStore'])->middleware('throttle:5,1')->name('contact.store');

// Customer accounts
Route::middleware('guest')->group(function () {
    Route::get('/login', [AccountController::class, 'loginForm'])->name('login');
    Route::post('/login', [AccountController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AccountController::class, 'registerForm'])->name('register');
    Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [AccountController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AccountController::class, 'forgot'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AccountController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AccountController::class, 'reset'])->name('password.update');
});
Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
    Route::get('/orders/{number}', [AccountController::class, 'order'])->name('order');
    Route::get('/addresses', [AccountController::class, 'addresses'])->name('addresses');
    Route::post('/addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
    Route::put('/addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.update');
});
Route::get('/account/wishlist', [AccountController::class, 'wishlist'])->name('account.wishlist'); // works for guests too (device wishlist)

// Storefront JSON used by the search overlay, quick view, wishlist and recently viewed
Route::get('/api/search', [CatalogController::class, 'searchJson'])->name('api.search');
Route::get('/api/products', [CatalogController::class, 'productsJson'])->name('api.products');
Route::get('/api/products/{slug}', [CatalogController::class, 'productJson'])->name('api.product');

// Installable web app (manifest + offline fallback used by public/sw.js)
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');

// SEO
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// CMS pages — must stay last so it never shadows other routes
Route::get('/{slug}', [CatalogController::class, 'page'])
    ->where('slug', '[a-z0-9-]+')
    ->name('pages.show');
