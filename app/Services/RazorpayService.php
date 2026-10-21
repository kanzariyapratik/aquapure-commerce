<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class RazorpayService {
 public function createOrder(int $paise,string $receipt):array {$key=config('services.razorpay.key');$secret=config('services.razorpay.secret');if(!$key||!$secret)throw new RuntimeException('Razorpay keys are not configured');$response=Http::withBasicAuth($key,$secret)->post('https://api.razorpay.com/v1/orders',['amount'=>$paise,'currency'=>'INR','receipt'=>$receipt]);if(!$response->successful())throw new RuntimeException('Payment gateway could not create an order');return $response->json();}
 public function verify(string $orderId,string $paymentId,string $signature):bool {$expected=hash_hmac('sha256',$orderId.'|'.$paymentId,config('services.razorpay.secret')??'');return hash_equals($expected,$signature);}
}
