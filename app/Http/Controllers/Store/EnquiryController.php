<?php
namespace App\Http\Controllers\Store;
use App\Models\BulkEnquiry;
use Illuminate\Http\Request;
use Inertia\Inertia;
class EnquiryController {public function index(){return Inertia::render('Store/Wholesale');}public function store(Request $r){BulkEnquiry::create($r->validate(['company'=>'required|string|max:150','contact_name'=>'required|string|max:150','email'=>'required|email','phone'=>'required|string|max:20','requirements'=>'required|string|max:5000']));return back()->with('success','Enquiry submitted. We will contact you soon.');}}
