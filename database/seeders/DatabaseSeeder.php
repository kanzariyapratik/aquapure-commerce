<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{User,Category,Product};
use Illuminate\Support\Str;
class DatabaseSeeder extends Seeder {public function run():void {
 $cats=['Ceramic Filter Candles','Water Purifier Accessories','Wholesale Packs'];foreach($cats as $name)Category::firstOrCreate(['slug'=>Str::slug($name)],['name'=>$name]);
 $products=[['Classic Ceramic Candle 7-inch','AP-CC-007',349,249,140,0],['Premium Ceramic Candle 10-inch','AP-CC-010',499,359,95,0],['Silver-Infused Ceramic Candle','AP-SC-010',699,529,60,0],['Gravity Filter Candle Set (2 pcs)','AP-GF-002',799,599,45,0],['Ceramic Candle Mounting Kit','AP-MK-001',179,129,180,1],['Replacement Rubber Washer Pack','AP-RW-004',99,69,220,1],['Wholesale Ceramic Candle Pack (12)','AP-WP-012',3599,2999,30,2],['Industrial Ceramic Filtration Element','AP-IF-020',1499,1199,22,2]];
 foreach($products as [$name,$sku,$price,$wholesale,$stock,$cat])Product::firstOrCreate(['sku'=>$sku],['name'=>$name,'slug'=>Str::slug($name),'description'=>'Sample product listing. Replace specifications, certifications and performance claims with verified factory data.','price'=>$price,'wholesale_price'=>$wholesale,'wholesale_min_qty'=>10,'stock'=>$stock,'category_id'=>Category::where('slug',Str::slug($cats[$cat]))->value('id'),'active'=>true]);
 if(env('APP_ENV')==='local')User::firstOrCreate(['email'=>'admin@aquapure.test'],['name'=>'Demo Admin','password'=>'ChangeMe123!','role'=>'admin']);
 }}
