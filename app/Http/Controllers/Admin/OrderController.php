<?php
namespace App\Http\Controllers\Admin;
use App\Models\{Order,BulkEnquiry};
use Illuminate\Http\Request;
use Inertia\Inertia;
class OrderController {
 public function index(){return Inertia::render('Admin/Orders',['orders'=>Order::with('items')->latest()->paginate(30)]);}
 public function update(Request $r,Order $order){$d=$r->validate(['status'=>'required|in:pending,processing,packed,shipped,delivered']);abort_if($order->status==='cancelled',422);$order->update($d);return back()->with('success','Order updated');}
 public function enquiries(){return Inertia::render('Admin/Enquiries',['enquiries'=>BulkEnquiry::latest()->paginate(30)]);}
 public function enquiryUpdate(Request $r,BulkEnquiry $enquiry){$enquiry->update($r->validate(['status'=>'required|in:new,contacted,quoted,closed']));return back();}
}
