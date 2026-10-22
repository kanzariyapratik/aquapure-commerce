<?php
namespace App\Http\Middleware;
use Inertia\Middleware;
use Illuminate\Http\Request;
class HandleInertiaRequests extends Middleware {
 protected $rootView='app';
 public function share(Request $request): array {return array_merge(parent::share($request), [
 'auth'=>['user'=>$request->user()?->only('id','name','email','role')],
 'flash'=>['success'=>fn()=> $request->session()->get('success'),'error'=>fn()=> $request->session()->get('error')],
 'cartCount'=>fn()=> $request->user() ? array_sum(array_column(session('cart',[]),'quantity')) : 0,
 'csrf'=>csrf_token(),
 ]);}
}
