<?php
namespace App\Services;
use App\Models\{Order,Product,StockMovement};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class OrderService {
 public function create($user,array $data,array $cart):Order {return DB::transaction(function()use($user,$data,$cart){
  $items=[];$subtotal=0;
  foreach($cart as $id=>$entry){$p=Product::whereKey($id)->where('active',true)->lockForUpdate()->first();$qty=(int)$entry['quantity'];if(!$p||$qty<1||$p->stock<$qty)throw ValidationException::withMessages(['cart'=>'An item is unavailable or insufficient stock']);$price=$data['type']==='wholesale'&&$p->wholesale_price!==null&&$qty>=$p->wholesale_min_qty?$p->wholesale_price:$p->price;$items[]=[$p,$qty,$price];$subtotal+=$qty*$price;}
  if(!$items)throw ValidationException::withMessages(['cart'=>'Cart is empty']);
  $shipping=0;$order=Order::create(array_merge($data,['user_id'=>$user->id,'order_number'=>'AP-'.strtoupper(bin2hex(random_bytes(5))),'subtotal'=>$subtotal,'shipping'=>$shipping,'total'=>$subtotal+$shipping,'status'=>'pending','payment_status'=>'unpaid']));
  foreach($items as [$p,$qty,$price]){$order->items()->create(['product_id'=>$p->id,'name'=>$p->name,'sku'=>$p->sku,'quantity'=>$qty,'unit_price'=>$price,'line_total'=>$qty*$price]);$p->decrement('stock',$qty);StockMovement::create(['product_id'=>$p->id,'user_id'=>$user->id,'change'=>-$qty,'reason'=>'order_reserved','reference'=>$order->order_number]);}
  return $order->load('items');});}
 public function markPaid(Order $order,string $paymentId):void {DB::transaction(function()use($order,$paymentId){$o=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();if($o->payment_status==='paid')return;$o->update(['payment_status'=>'paid','payment_id'=>$paymentId,'status'=>'processing']);});}
 public function cancel(Order $order):void {DB::transaction(function()use($order){$o=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();if($o->payment_status==='paid'||$o->status==='cancelled')return;foreach($o->items as $item){if(!$item->product_id)continue;$p=Product::whereKey($item->product_id)->lockForUpdate()->first();if($p){$p->increment('stock',$item->quantity);StockMovement::create(['product_id'=>$p->id,'change'=>$item->quantity,'reason'=>'order_cancelled','reference'=>$o->order_number]);}}$o->update(['status'=>'cancelled']);});}
}
