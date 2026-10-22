<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
 ->withMiddleware(function (Middleware $middleware) {
     $middleware->web(prepend: [\App\Http\Middleware\AuthenticationSecurity::class], append: [
         \Illuminate\Session\Middleware\AuthenticateSession::class,
         \App\Http\Middleware\EnsureTwoFactorVerified::class,
         \App\Http\Middleware\HandleInertiaRequests::class,
     ]);
     $middleware->redirectUsersTo(fn (\Illuminate\Http\Request $request) => $request->user()?->role === 'admin' ? '/admin' : '/');
 })
 ->withExceptions(function (Exceptions $exceptions) {
     $exceptions->dontFlash(['code', 'recovery_code']);
 })->create();
