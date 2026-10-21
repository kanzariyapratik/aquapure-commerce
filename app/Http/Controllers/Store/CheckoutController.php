<?php
namespace App\Http\Controllers\Store;
use App\Models\Order;
use App\Services\{OrderService,RazorpayService};
use Illuminate\Http\Request;
use Inertia\Inertia;
class CheckoutController {
 public function index(CartController $cart){return Inertia::render('Store/Checkout',['items'=>$cart->items(),'razorpayKey'=>config('services.razorpay.key')]);}
 public function store(Request $r,CartController $cart,OrderService $orders,RazorpayService $gateway){$d=$r->validate(['customer_name'=>'required|string|max:150','email'=>'required|email','phone'=>'required|string|max:20','shipping_address'=>'required|string|max:1000','city'=>'required|string|max:100','state'=>'required|string|max:100','pincode'=>'required|string|max:12','type'=>'required|in:retail,wholesale']);$entries=session('cart',[]);$order=$orders->create($r->user(),$d,$entries);try{$remote=$gateway->createOrder((int)round($order->total*100),$order->order_number);$order->update(['payment_provider_order_id'=>$remote['id']]);}catch(\Throwable $e){$orders->cancel($order);report($e);return back()->withErrors(['payment'=>'Could not initialize payment. Please try again.']);}return redirect()->route('orders.show',$order);}
 public function pay(Request $r,Order $order,OrderService $orders,RazorpayService $gateway){abort_unless($order->user_id===$r->user()->id,403);$d=$r->validate(['razorpay_payment_id'=>'required|string','razorpay_order_id'=>'required|string','razorpay_signature'=>'required|string']);abort_unless($order->payment_provider_order_id===$d['razorpay_order_id']&&$gateway->verify($d['razorpay_order_id'],$d['razorpay_payment_id'],$d['razorpay_signature']),422,'Invalid payment signature');$orders->markPaid($order,$d['razorpay_payment_id']);session()->forget('cart');return redirect()->route('orders.show',$order)->with('success','Payment successful');}
 public function show(Request $r,Order $order){abort_unless($order->user_id===$r->user()->id||$r->user()->role==='admin',403);return Inertia::render('Store/Order',['order'=>$order->load('items'),'razorpayKey'=>config('services.razorpay.key')]);}
 public function mine(Request $r){return Inertia::render('Store/Orders',['orders'=>Order::where('user_id',$r->user()->id)->latest()->paginate(15)]);}
}
