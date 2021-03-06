<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\AuthController as APIAuthController;
use \App\Http\Controllers\API\CartController;
use \App\Http\Controllers\API\OrderController;
use \App\Http\Controllers\API\WishlistController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/detail', [ProductController::class, 'product_detail']);
Route::get('/products/recommendation', [ProductController::class, 'getRecommendation']);
Route::get('/products/categories', [ProductController::class, 'index_categories']);


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::prefix('wishlist')->name('wishlist.')->group(static function(){
            Route::get('add/{id}', [WishlistController::class,'add_to_wishlist']);
            Route::get('all', [WishlistController::class,'index_wishlist']);
            Route::get('remove/{id}',  [WishlistController::class,'remove']);
    });
    Route::get('/wishlist/exist/{id}', [ProductController::class, 'is_product_in_wishlist']);

    Route::get('/profile', [APIAuthController::class, 'profile']);
    Route::post('/order/create', [OrderController::class, 'create_order']);
    Route::get('/order/all', [OrderController::class, 'all_order']);
    Route::get('/order/detail/{id}', [OrderController::class, 'order_by_id']);
    Route::get('/order/pay/{id}', [OrderController::class, 'pay_order']);

//    Route::put('/update/profile', [APIAuthController::class, 'update']);
    Route::post('/logout', [APIAuthController::class, 'logout']);
    Route::get('/cart/all', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'add_to_cart']);
    Route::put('/cart/scan/{id}', [CartController::class, 'scan_product']);
    Route::put('/cart/unscan/{id}', [CartController::class, 'remove_product']);
    Route::delete('/cart/delete/{id}', [CartController::class, 'remove']);
});
//API route for register new user
Route::post('/register', [APIAuthController::class, 'register']);
//API route for login user
Route::post('/login', [APIAuthController::class, 'login']);
Route::put('/verify/otp', [APIAuthController::class, 'verify_otp']);
Route::put('/resend/otp', [APIAuthController::class, 'resend']);
