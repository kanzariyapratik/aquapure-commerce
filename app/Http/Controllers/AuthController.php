<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
class AuthController {
 public function loginPage(){return Inertia::render('Auth/Login');}
 public function registerPage(){return Inertia::render('Auth/Register');}
 public function login(Request $r){$data=$r->validate(['email'=>'required|email','password'=>'required|string']);if(!Auth::attempt($data))return back()->withErrors(['email'=>'Invalid credentials']);$r->session()->regenerate();return redirect()->intended('/');}
 public function register(Request $r){$d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|unique:users,email','password'=>'required|min:8|confirmed']);$u=User::create($d+['role'=>'customer']);Auth::login($u);$r->session()->regenerate();return redirect('/');}
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/');}
}
