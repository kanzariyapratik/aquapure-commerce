<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Store\{CatalogController,CartController,CheckoutController,EnquiryController};
use App\Http\Controllers\Admin\{DashboardController,ProductController,OrderController};
Route::get('/',[CatalogController::class,'home'])->name('home');
Route::get('/products',[CatalogController::class,'index'])->name('products.index');
Route::get('/products/{slug}',[CatalogController::class,'show'])->name('products.show');
Route::get('/cart',[CartController::class,'index'])->name('cart.index');
Route::post('/cart',[CartController::class,'add'])->name('cart.add');
Route::patch('/cart/{id}',[CartController::class,'update'])->name('cart.update');
Route::get('/wholesale',[EnquiryController::class,'index'])->name('wholesale');
Route::post('/wholesale',[EnquiryController::class,'store'])->name('wholesale.store');
Route::middleware('guest')->group(function(){Route::get('/login',[AuthController::class,'loginPage'])->name('login');Route::post('/login',[AuthController::class,'login']);Route::get('/register',[AuthController::class,'registerPage'])->name('register');Route::post('/register',[AuthController::class,'register']);});
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function(){Route::get('/checkout',[CheckoutController::class,'index'])->name('checkout');Route::post('/checkout',[CheckoutController::class,'store'])->name('checkout.store');Route::get('/orders',[CheckoutController::class,'mine'])->name('orders.index');Route::get('/orders/{order}',[CheckoutController::class,'show'])->name('orders.show');Route::post('/orders/{order}/verify-payment',[CheckoutController::class,'pay'])->name('orders.pay');});
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function(){
 Route::middleware(\App\Http\Middleware\EnsureAdmin::class)->group(function(){Route::get('/',[DashboardController::class,'index'])->name('dashboard');Route::get('/products',[ProductController::class,'index'])->name('products');Route::post('/products',[ProductController::class,'store']);Route::post('/products/{product}',[ProductController::class,'update']);Route::post('/products/{product}/stock',[ProductController::class,'adjust']);Route::delete('/products/{product}',[ProductController::class,'destroy']);Route::get('/orders',[OrderController::class,'index'])->name('orders');Route::patch('/orders/{order}',[OrderController::class,'update']);Route::get('/enquiries',[OrderController::class,'enquiries'])->name('enquiries');Route::patch('/enquiries/{enquiry}',[OrderController::class,'enquiryUpdate']);});});
