<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\api\HomeController;
use App\Http\Controllers\Api\UmkmController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OngkirController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShipperController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KecamatanController;
use App\Http\Controllers\Api\ProductViewController;
use App\Http\Controllers\Api\UmkmDashboardController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    Route::get('/user/stats', [DashboardController::class, 'userStatistics']);

    Route::apiResource('products', ProductController::class)->except(['index', 'show']);

    // Route::post('/orders/check-ongkir', [OrderController::class, 'checkOngkir']);

    Route::prefix('profile')->group(function () {
        Route::get('/umkm', [UmkmController::class, 'myUmkm']);
        Route::put('/umkm', [UmkmController::class, 'updateUmkm']);

        Route::get('/customer', [CustomerController::class, 'myProfile']);
        Route::put('/customer', [CustomerController::class, 'updateProfile']);
        Route::post('/customer', [CustomerController::class, 'updateProfile']);
    });

    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);
        Route::get('/dashboard/charts', [DashboardController::class, 'chartsData']);
        Route::get('/dashboard/quick-stats', [DashboardController::class, 'quickStats']);

        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::get('/categories/statistics', [CategoryController::class, 'statistics']);
        Route::get('/categories/admin-list', [CategoryController::class, 'adminList']);
        Route::get('/categories/{category}/detail', [CategoryController::class, 'categoryDetail']);

        Route::apiResource('shippers', ShipperController::class)->except(['index', 'show']);
        Route::get('/shippers/statistics', [ShipperController::class, 'statistics']);
        Route::get('/shippers/admin-list', [ShipperController::class, 'adminList']);
        Route::get('/shippers/{shipper}/detail', [ShipperController::class, 'shipperDetail']);

        Route::apiResource('kecamatans', KecamatanController::class)->except(['index', 'show']);
        Route::get('/kecamatans/statistics', [KecamatanController::class, 'statistics']);
        Route::get('/kecamatans/admin-list', [KecamatanController::class, 'adminList']);
        Route::get('/kecamatans/{kecamatan}/detail', [KecamatanController::class, 'kecamatanDetail']);

        Route::get('/umkms/admin-list', [UmkmController::class, 'adminUmkm']);
        Route::get('/umkms/statistics', [UmkmController::class, 'umkmStatistics']);
        Route::get('/umkms/pending', [UmkmController::class, 'pendingUmkms']);
        Route::put('/umkms/{umkm}/approve', [UmkmController::class, 'approve']);
        Route::put('/umkms/{umkm}/reject', [UmkmController::class, 'reject']);

        Route::get('/orders', [OrderController::class, 'index']);

        Route::get('/orders/statistics', [OrderController::class, 'statistics']);
        Route::get('/orders/admin-list', [OrderController::class, 'adminList']);
        Route::get('/orders/{order}/detail', [OrderController::class, 'orderDetail']);
        Route::put('/orders/{order}/update-status', [OrderController::class, 'updateOrderStatus']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/statistics', [CustomerController::class, 'statistics']);

        Route::get('/products/statistics', [ProductController::class, 'statistics']);
        Route::get('/products/admin-list', [ProductController::class, 'adminList']);
        Route::get('/products/categories', [ProductController::class, 'categories']);

        Route::get('/ratings', [RatingController::class, 'index']);
        Route::get('/ratings/statistics', [RatingController::class, 'statistics']);
        Route::get('/ratings/admin-list', [RatingController::class, 'adminList']);
        Route::get('/ratings/{rating}/detail', [RatingController::class, 'ratingDetail']);
        Route::put('/ratings/{rating}/approval', [RatingController::class, 'updateApproval']);
        Route::delete('/ratings/{rating}', [RatingController::class, 'deleteRating']);

        Route::get('/analytics/overview', [UmkmController::class, 'adminAnalytics']);
    });

    Route::put('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus']);

    Route::prefix('umkm')->group(function () {
        Route::get('/umkm', [UmkmController::class, 'myUmkm']);
        Route::post('/umkm', [UmkmController::class, 'updateUmkm']);
        Route::get('/my-products', [UmkmController::class, 'myProducts']);
        Route::get('/orders', [UmkmController::class, 'myOrders']);
        Route::get('/orders/statistics', [UmkmController::class, 'orderStatistics']);
        Route::get('/orders/{order}', [UmkmController::class, 'umkmOrderDetail']);
        Route::put('/orders/{order}/update-status', [UmkmController::class, 'updateOrderStatus']);
        Route::put('/orders/{order}/update-shipper', [UmkmController::class, 'updateOrderShipper']);
        Route::get('/analytics', [UmkmController::class, 'analytics']);

        Route::get('/dashboard', [UmkmDashboardController::class, 'dashboardData']);
        Route::get('/dashboard/monthly-revenue', [UmkmDashboardController::class, 'monthlyRevenue']);
        Route::get('/dashboard/recent-orders', [UmkmDashboardController::class, 'recentOrders']);
        Route::get('/dashboard/top-products', [UmkmDashboardController::class, 'topProducts']);
        Route::get('/dashboard/recent-reviews', [UmkmDashboardController::class, 'recentReviews']);
        Route::get('/dashboard/today-stats', [UmkmDashboardController::class, 'todayStats']);
    });

    Route::prefix('analytics')->group(function () {
        Route::get('/product-views', [ProductViewController::class, 'index']);
        Route::get('/product-views/{product}', [ProductViewController::class, 'productViews']);
        Route::get('/views-analytics', [ProductViewController::class, 'analytics']);
    });

    Route::prefix('customer')->group(function() {
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/add', [CartController::class, 'addToCart']);
        Route::put('/cart/{cartItem}', [CartController::class, 'updateCart']);
        Route::delete('/cart/{cartItem}', [CartController::class, 'removeFromCart']);
        Route::delete('/cart', [CartController::class, 'clearCart']);
        Route::post('/cart/calculate-shipping', [CartController::class, 'calculateShipping']);
        Route::post('/cart/bulk-update', [CartController::class, 'bulkUpdate']);
        Route::post('/cart/remove-selected', [CartController::class, 'removeSelected']);

        Route::get('/orders', [OrderController::class, 'myOrders']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/check-ongkir', [OrderController::class, 'checkOngkir']);
        Route::post('/orders/validate-stock', [OrderController::class, 'validateStock']);
        Route::put('/orders/{order}/cancel', [OrderController::class, 'cancelOrder']);

        Route::post('/orders/{order}/rate', [RatingController::class, 'rateOrder']);
        Route::get('/ratings', [RatingController::class, 'myRatings']);
    });
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/new', [ProductController::class, 'new']);
Route::get('/products/popular', [ProductController::class, 'popular']);
Route::get('/products/most-viewed', [ProductController::class, 'mostViewed']);
Route::get('/products/by-umkm/{umkmId}', [ProductController::class, 'byUmkm']);
Route::get('/products/by-category/{categoryId}', [ProductController::class, 'byCategory']);
// Route::post('/products/{product}/view', [ProductController::class, 'trackView']);

Route::get('/products/{product}/detail', [ProductController::class, 'showWithDetails']);
Route::get('/products/{product}/recommended', [ProductController::class, 'recommendedProducts']);
Route::get('/products/{product}/reviews', [ProductController::class, 'reviews']);

Route::get('/search/products', [SearchController::class, 'searchProducts']);
Route::get('/search/umkms', [SearchController::class, 'searchUmkms']);
Route::get('/search/categories', [SearchController::class, 'getCategories']);
Route::get('/search/kecamatans', [SearchController::class, 'getKecamatans']);
Route::get('/search/products/count', [SearchController::class, 'productCount']);
Route::get('/search/umkms/count', [SearchController::class, 'umkmCount']);

Route::get('/umkms/count', [UmkmController::class, 'umkmCount']);
Route::get('/products/count', [ProductController::class, 'productCount']);

// Route::get('/umkms/{product}/products', [ProductController::class, 'reviews']);
Route::get('/umkms/{umkm}/reviews', [UmkmController::class, 'reviews']);
Route::get('/categories/by-umkm/{umkmId}', [CategoryController::class, 'byUmkm']);

Route::get('/home', [HomeController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/umkms', [UmkmController::class, 'index']);
Route::get('/umkms/{umkm}', [UmkmController::class, 'show']);
Route::get('/shippers', [ShipperController::class, 'index']);
Route::get('/kecamatans', [KecamatanController::class, 'index']);
Route::get('/kecamatans/{kecamatan}', [KecamatanController::class, 'show']);
Route::post('/calculate-ongkir', [OngkirController::class, 'calculateOngkirByUmkmCustomer']);
