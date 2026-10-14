<?php

namespace Modules\Sales\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Sales\Http\Controllers\RahnPublicController;
use Modules\Sales\Http\Controllers\Storefront\AccountController;
use Modules\Sales\Http\Controllers\Storefront\CartController;
use Modules\Sales\Http\Controllers\Storefront\CheckoutController;
use Modules\Sales\Http\Controllers\Storefront\GeoController;
use Modules\Sales\Http\Controllers\Storefront\PublicStorefrontController;
use Modules\Sales\Http\Controllers\Storefront\StorefrontAdminController;

class SalesServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Sales';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        $this->registerStorefrontRoutes();

        Route::prefix('api/v1/sales')
            ->middleware(['api', 'auth:sanctum', 'module:sales', 'module.permission:sales'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        // Public رهن‌درصد share API — no auth (Marketplace Basalam OAuth callback pattern).
        // Guarantees unauthenticated access even if withoutMiddleware on the group file is ignored.
        Route::middleware(['api', 'throttle:60,1'])->prefix('api/v1/sales/rahn/public')->group(function () {
            Route::get('{token}', [RahnPublicController::class, 'show'])->where('token', '[a-zA-Z0-9]+');
            Route::post('{token}/calculate', [RahnPublicController::class, 'calculate'])->where('token', '[a-zA-Z0-9]+');
            Route::post('{token}/submit', [RahnPublicController::class, 'submit'])->where('token', '[a-zA-Z0-9]+');
        });
    }

    /** Online shop for the builder storefront widgets (same paths as WebinoDashboard). */
    private function registerStorefrontRoutes(): void
    {
        Route::middleware(['api', 'throttle:120,1'])->prefix('api/v1/public')->group(function () {
            Route::get('/catalog', [PublicStorefrontController::class, 'index']);
            Route::get('/catalog/items/{slug}', [PublicStorefrontController::class, 'show']);
            Route::get('/catalog/items/{slug}/linked', [PublicStorefrontController::class, 'linked']);
            Route::get('/catalog/items/{slug}/reviews', [PublicStorefrontController::class, 'reviews']);
            Route::get('/catalog/items/{slug}/questions', [PublicStorefrontController::class, 'questions']);
            Route::post('/catalog/items/{slug}/reviews', [PublicStorefrontController::class, 'storeReview'])->middleware('throttle:5,1,sf-catalog-items-slug-reviews');
            Route::post('/catalog/reviews/{review}/react', [PublicStorefrontController::class, 'react'])->whereNumber('review')->middleware('throttle:30,1,sf-catalog-reviews-review-react');
            Route::post('/catalog/reviews/{review}/report', [PublicStorefrontController::class, 'report'])->whereNumber('review')->middleware('throttle:10,1,sf-catalog-reviews-review-report');
            Route::get('/compare', [PublicStorefrontController::class, 'compare']);
            Route::post('/compare/products/{productId}', [PublicStorefrontController::class, 'compareAdd'])->whereNumber('productId')->middleware('throttle:30,1,sf-compare-products-productId');
            Route::delete('/compare/products/{productId}', [PublicStorefrontController::class, 'compareRemove'])->whereNumber('productId')->middleware('throttle:30,1,sf-compare-products-productId');
            Route::get('/product-stories', [PublicStorefrontController::class, 'stories']);
            Route::get('/geo/states', [GeoController::class, 'states']);
            Route::post('/coupons/preview', [PublicStorefrontController::class, 'previewCoupon'])->middleware('throttle:20,1,sf-coupons-preview');
            Route::post('/orders/track', [PublicStorefrontController::class, 'trackOrder'])->middleware('throttle:10,1,sf-orders-track');
        });

        // Customers: signed in, no staff module permission needed.
        Route::middleware(['api', 'auth:sanctum', 'throttle:60,1'])->prefix('api/v1')->group(function () {
            Route::get('/cart', [CartController::class, 'show']);
            Route::post('/cart/items', [CartController::class, 'addItem']);
            Route::put('/cart/items/{product}', [CartController::class, 'setQuantity'])->whereNumber('product');
            Route::delete('/cart/items/{product}', [CartController::class, 'removeItem'])->whereNumber('product');
            Route::get('/cart/saved-for-later', [CartController::class, 'savedForLater']);
            Route::post('/cart/items/{product}/save-for-later', [CartController::class, 'saveForLater'])->whereNumber('product');
            Route::post('/cart/items/{product}/move-to-cart', [CartController::class, 'moveToCart'])->whereNumber('product');
            Route::post('/shipping/quote', [CheckoutController::class, 'shippingQuote']);
            Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1,sf-checkout');
            Route::get('/payments/checkout-options', [CheckoutController::class, 'paymentOptions']);
            Route::post('/payments/intent', [CheckoutController::class, 'paymentIntent'])->middleware('throttle:10,1,sf-payments-intent');
            Route::get('/account/orders', [AccountController::class, 'orders']);
            Route::get('/account/favorites', [AccountController::class, 'favorites']);
            Route::post('/account/favorites/{product}', [AccountController::class, 'addFavorite'])->whereNumber('product');
            Route::delete('/account/favorites/{product}', [AccountController::class, 'removeFavorite'])->whereNumber('product');
            Route::get('/account/questions', [AccountController::class, 'questions']);
            Route::post('/account/questions', [AccountController::class, 'askQuestion'])->middleware('throttle:10,1,sf-account-questions');
        });

        Route::middleware(['api', 'auth:sanctum', 'module:sales', 'module.permission:sales'])->prefix('api/v1/sales/storefront')->group(function () {
            Route::get('/orders', [StorefrontAdminController::class, 'orders']);
            Route::patch('/orders/{order}', [StorefrontAdminController::class, 'updateOrder'])->whereNumber('order');
            Route::get('/reviews', [StorefrontAdminController::class, 'reviews']);
            Route::patch('/reviews/{review}', [StorefrontAdminController::class, 'updateReview'])->whereNumber('review');
            Route::get('/questions', [StorefrontAdminController::class, 'questions']);
            Route::patch('/questions/{question}', [StorefrontAdminController::class, 'answerQuestion'])->whereNumber('question');
            Route::get('/coupons', [StorefrontAdminController::class, 'coupons']);
            Route::post('/coupons', [StorefrontAdminController::class, 'saveCoupon']);
            Route::put('/coupons/{coupon}', [StorefrontAdminController::class, 'saveCoupon'])->whereNumber('coupon');
            Route::delete('/coupons/{coupon}', [StorefrontAdminController::class, 'destroyCoupon'])->whereNumber('coupon');
        });
    }

    public function register(): void
    {
        //
    }
}
