<?php
namespace App\Http\Controllers\Admin;
use App\Models\{Product,Category,StockMovement};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class ProductController {
 public function index(){return Inertia::render('Admin/Products',['products'=>Product::with('category')->latest()->paginate(20),'categories'=>Category::all()]);}
 private function rules(?Product $p=null):array{return ['name'=>'required|string|max:180','sku'=>['required','string','max:100',Rule::unique('products','sku')->ignore($p?->id)],'category_id'=>'nullable|exists:categories,id','description'=>'nullable|string','price'=>'required|numeric|min:0','wholesale_price'=>'nullable|numeric|min:0','wholesale_min_qty'=>'required|integer|min:1','low_stock_threshold'=>'required|integer|min:0','image'=>'nullable|image|max:4096','active'=>'required|boolean'];}
 public function store(Request $r){$d=$r->validate($this->rules());if($r->hasFile('image'))$d['image']=$r->file('image')->store('products','public');$d['slug']=Str::slug($d['name']).'-'.Str::lower(Str::random(5));$d['stock']=0;Product::create($d);return back()->with('success','Product created');}
 public function update(Request $r,Product $product){$d=$r->validate($this->rules($product));if($r->hasFile('image'))$d['image']=$r->file('image')->store('products','public');else unset($d['image']);$product->update($d);return back()->with('success','Product updated');}
 public function adjust(Request $r,Product $product){$d=$r->validate(['change'=>'required|integer|not_in:0','reason'=>'required|string|max:200']);DB::transaction(function()use($r,$product,$d){$p=Product::whereKey($product->id)->lockForUpdate()->firstOrFail();abort_if($p->stock+$d['change']<0,422,'Cannot reduce stock below zero');$p->increment('stock',$d['change']);StockMovement::create(['product_id'=>$p->id,'user_id'=>$r->user()->id,'change'=>$d['change'],'reason'=>$d['reason']]);});return back()->with('success','Stock updated');}
 public function destroy(Product $product){$product->update(['active'=>false]);return back()->with('success','Product archived');}
}
