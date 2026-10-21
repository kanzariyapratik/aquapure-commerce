<?php
namespace App\Http\Controllers\Admin;
use App\Models\{Product,Order,BulkEnquiry};
use Inertia\Inertia;
class DashboardController {public function index(){return Inertia::render('Admin/Dashboard',['stats'=>['products'=>Product::count(),'lowStock'=>Product::whereColumn('stock','<=','low_stock_threshold')->count(),'orders'=>Order::count(),'revenue'=>Order::where('payment_status','paid')->sum('total'),'enquiries'=>BulkEnquiry::where('status','new')->count()],'recentOrders'=>Order::latest()->take(6)->get()]);}}
