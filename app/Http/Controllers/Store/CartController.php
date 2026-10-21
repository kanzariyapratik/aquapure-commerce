<?php
namespace App\Http\Controllers\Store;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
class CartController {
 public function index(){return Inertia::render('Store/Cart',['items'=>$this->items()]);}
 public function items():array {$cart=session('cart',[]);$products=Product::whereIn('id',array_keys($cart))->where('active',true)->get();return $products->map(fn($p)=>['product'=>$p,'quantity'=>min($cart[$p->id]['quantity'],$p->stock)])->filter(fn($i)=>$i['quantity']>0)->values()->all();}
 public function add(Request $r){$d=$r->validate(['product_id'=>'required|exists:products,id','quantity'=>'required|integer|min:1|max:10000']);$p=Product::where('active',true)->findOrFail($d['product_id']);$cart=session('cart',[]);$qty=($cart[$p->id]['quantity']??0)+$d['quantity'];if($qty>$p->stock)return back()->withErrors(['quantity'=>'Insufficient stock']);$cart[$p->id]=['quantity'=>$qty];session(['cart'=>$cart]);return back()->with('success','Added to cart');}
 public function update(Request $r,int $id){$d=$r->validate(['quantity'=>'required|integer|min:0|max:10000']);$cart=session('cart',[]);$p=Product::findOrFail($id);if($d['quantity']>$p->stock)return back()->withErrors(['quantity'=>'Insufficient stock']);if($d['quantity']===0)unset($cart[$id]);else $cart[$id]=['quantity'=>$d['quantity']];session(['cart'=>$cart]);return back();}
}
