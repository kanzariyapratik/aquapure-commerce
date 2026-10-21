<?php
namespace App\Http\Controllers\Store;
use App\Models\{Product,Category};
use Illuminate\Http\Request;
use Inertia\Inertia;
class CatalogController {
 public function home(){return Inertia::render('Store/Home',['featured'=>Product::where('active',true)->latest()->take(8)->get(),'categories'=>Category::all()]);}
 public function index(Request $r){$q=Product::with('category')->where('active',true);if($r->filled('search'))$q->where('name','like','%'.$r->input('search').'%');if($r->filled('category'))$q->whereHas('category',fn($c)=>$c->where('slug',$r->input('category')));return Inertia::render('Store/Products',['products'=>$q->latest()->paginate(12)->withQueryString(),'categories'=>Category::all(),'filters'=>$r->only('search','category')]);}
 public function show(string $slug){return Inertia::render('Store/Product',['product'=>Product::with('category')->where('slug',$slug)->where('active',true)->firstOrFail()]);}
}
