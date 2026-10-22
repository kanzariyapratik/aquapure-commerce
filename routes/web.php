<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\Store\{CatalogController,CartController,CheckoutController,EnquiryController};
use App\Http\Controllers\Admin\{DashboardController,ProductController,OrderController};
Route::get('/',[CatalogController::class,'home'])->name('home');
Route::get('/products',[CatalogController::class,'index'])->name('products.index');
Route::get('/products/{slug}',[CatalogController::class,'show'])->name('products.show');
Route::middleware('auth')->group(function () {
 Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
 Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
 Route::patch('/cart/{id}', [CartController::class, 'update'])->name('cart.update');
});
Route::get('/wholesale',[EnquiryController::class,'index'])->name('wholesale');
Route::post('/wholesale',[EnquiryController::class,'store'])->name('wholesale.store');
Route::middleware('guest')->group(function(){Route::get('/login',[AuthController::class,'loginPage'])->name('login');Route::post('/login',[AuthController::class,'login'])->middleware('throttle:20,1,login-ip:');Route::get('/register',[AuthController::class,'registerPage'])->name('register');Route::post('/register',[AuthController::class,'register'])->middleware('throttle:5,1,register-ip:');});
Route::middleware('guest')->group(function () {
 Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
 Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->middleware('throttle:20,1,two-factor-ip:')->name('two-factor.verify');
 Route::delete('/two-factor-challenge', [TwoFactorController::class, 'cancelChallenge'])->name('two-factor.cancel');
});
Route::middleware('auth')->group(function () {
 Route::get('/account/security', [TwoFactorController::class, 'settings'])->name('security');
 Route::post('/account/two-factor', [TwoFactorController::class, 'begin'])->middleware('throttle:5,1,two-factor-settings:')->name('two-factor.begin');
 Route::post('/account/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:5,1,two-factor-settings:')->name('two-factor.confirm');
 Route::post('/account/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:5,1,two-factor-settings:')->name('two-factor.recovery-codes');
 Route::delete('/account/two-factor', [TwoFactorController::class, 'disable'])->middleware('throttle:5,1,two-factor-settings:')->name('two-factor.disable');
});
Route::get('/admin/security', [TwoFactorController::class, 'settings'])->middleware(['auth', \App\Http\Middleware\EnsureAdmin::class])->name('admin.security');
Route::middleware('guest')->group(function () {
 Route::get('/forgot-password', [PasswordController::class, 'forgot'])->name('password.request');
 Route::post('/forgot-password', [PasswordController::class, 'sendResetLink'])->middleware('throttle:5,1,password-email:')->name('password.email');
 Route::get('/reset-password/{token}', [PasswordController::class, 'resetPage'])->name('password.reset');
 Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1,password-reset:')->name('password.store');
});
Route::middleware('auth')->group(function () {
 Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.edit');
 Route::put('/account/password', [PasswordController::class, 'update'])->middleware('throttle:5,1,password-change:')->name('password.update');
});
Route::middleware(['auth', \App\Http\Middleware\EnsureAdmin::class])->prefix('admin')->name('admin.')->group(function () {
 Route::get('/password', [PasswordController::class, 'adminEdit'])->name('password.edit');
 Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:5,1,password-change:')->name('password.update');
});
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function(){Route::get('/checkout',[CheckoutController::class,'index'])->name('checkout');Route::post('/checkout',[CheckoutController::class,'store'])->name('checkout.store');Route::get('/orders',[CheckoutController::class,'mine'])->name('orders.index');Route::get('/orders/{order}',[CheckoutController::class,'show'])->name('orders.show');Route::post('/orders/{order}/verify-payment',[CheckoutController::class,'pay'])->name('orders.pay');});
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function(){
 Route::middleware(\App\Http\Middleware\EnsureAdmin::class)->group(function(){Route::get('/',[DashboardController::class,'index'])->name('dashboard');Route::get('/products',[ProductController::class,'index'])->name('products');Route::post('/products',[ProductController::class,'store']);Route::post('/products/{product}',[ProductController::class,'update']);Route::post('/products/{product}/stock',[ProductController::class,'adjust']);Route::delete('/products/{product}',[ProductController::class,'destroy']);Route::get('/orders',[OrderController::class,'index'])->name('orders');Route::patch('/orders/{order}',[OrderController::class,'update']);Route::get('/enquiries',[OrderController::class,'enquiries'])->name('enquiries');Route::patch('/enquiries/{enquiry}',[OrderController::class,'enquiryUpdate']);});});
