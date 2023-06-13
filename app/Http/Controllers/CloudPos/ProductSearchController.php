<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use App\Model\Items\VendorSku;

class ProductSearchController extends Controller
{
	public function __construct()
	{
		// $this->middleware('auth');
	}


	public function search(Request $request){

		$v_id 		 = $request->v_id;
		$store_id 	 = $request->store_id;
		$search_term = trim($request->search_term);
		$product 	 = [];
		$timestamp   = date('Y-m-d');

		if(!empty($search_term)){

			
			$product = VendorSku::select('vendor_sku_flat_table.name','vendor_sku_flat_table.barcode','vendor_sku_flat_table.selling_uom_type as weight','item_prices.mrp as mrp','vendor_sku_flat_table.cat_name_1 as category','vendor_sku_flat_table.brand_name as brand','vendor_sku_flat_table.variant_combi as variant','vendor_sku_flat_table.item_id as item_id')
			->join('vendor_sku_detail_barcodes','vendor_sku_detail_barcodes.vendor_sku_detail_id','vendor_sku_flat_table.vendor_sku_detail_id')
			->leftJoin('stock_current_status','stock_current_status.item_id','vendor_sku_flat_table.item_id')


			->leftJoin('vendor_item_price_mapping',function($query) use($v_id){
				$query->on('vendor_item_price_mapping.item_id','vendor_sku_flat_table.item_id');
				$query->on('vendor_item_price_mapping.sku_code','vendor_sku_flat_table.sku_code');
			})
			->leftJoin('price_book',function($query) use($store_id){
				$query->on('price_book.id','vendor_item_price_mapping.price_book_id');
				$query->where('price_book.status','1');
			})
			->leftJoin('item_prices','item_prices.id','vendor_item_price_mapping.item_price_id')

			->where('vendor_sku_flat_table.v_id', $v_id)
			->where('stock_current_status.stop_billing', 0);

			## Advanced Search Flag
			if($request->has('advanced_search') && $request->advanced_search == 1){
				$product = $this->advancedSearch($request, $product);
			}else{

				$product = $product->where(function($query) use($search_term){
					$query->where('vendor_sku_flat_table.name','LIKE','%'.$search_term.'%')
					->orWhere('vendor_sku_detail_barcodes.barcode', 'LIKE', '%'.$search_term.'%');
				});
			}

			
			$product = $product->groupBy('vendor_sku_detail_barcodes.vendor_sku_detail_id')
						->limit(10)->get();
		}

		
		if(count($product) >0){
			foreach ($product as $price) {
				$price->name   = utf8_decode($price->name);
				$getprice  = DB::table('vendor_item_price_mapping')
				->join('price_book','price_book.id','vendor_item_price_mapping.price_book_id')
				->join('item_prices','item_prices.id','vendor_item_price_mapping.item_price_id')
				->where('vendor_item_price_mapping.v_id',$v_id)
				->where('vendor_item_price_mapping.store_id',$store_id)
				->where('vendor_item_price_mapping.item_id',$price->item_id)
				->where('price_book.status','1')
				->whereDate('effective_date','<=',$timestamp)
				->whereDate('valid_to','>=',$timestamp)
				->whereNull('price_book.deleted_at')
				->orderBy('price_book.effective_date','desc')->first();

				if(!empty($getprice)){
					$price->mrp =  	$getprice->special_price;
				} 

			}
			$message = 'Get Product Search';
		}else{
			$message = 'No Data Found';
		}

		return response()->json(['status' => 'get_product_search', 'message' => $message, 'data' => $product, 'product_image_link' => product_image_link()], 200);
	}//End function

	public function fieldSearch(Request $request){
		$v_id 		 = $request->v_id;
		$dbName = getDatabaseName($v_id);

		$results = DB::select( DB::raw("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE where TABLE_SCHEMA ='$dbName' and TABLE_NAME = 'vendor_sku_flat_table' and  COLUMN_NAME not in ('id','v_id','vendor_sku_detail_id', 'variant_combi', 'sku' , 'uom_conversion_id', 'brand_id' , 'purchase_uom_id' ,'selling_uom_id' , 'purchase_uom_type','selling_uom_type','has_batch' ,'has_serial','tax_group_id' ,'created_at','updated_at') ") );

		$data = [];

		foreach($results as $result){

			$value = str_replace('a_' , 'Attribute: ' , $result->COLUMN_NAME); 
			$value = str_replace('va_' , 'Variant Att: ' , $result->COLUMN_NAME); 
			$value = str_replace('cat_name_' , 'Category ' , $result->COLUMN_NAME); 
			
			$data[$result->COLUMN_NAME] = $value; 
		}

		return $data;
	}


	public function advancedSearch(Request $request, $product)
	{
		$search_term = trim($request->search_term);

		if(strpos($search_term, '@') !== false){
			$search_term = explode(',', $search_term);
		}

			
		$product = $product->where(function($query) use($search_term){
			if(is_array($search_term)){

				foreach ($search_term as $key => $search) {
					$search = explode(':',$search);
					$column_name = 'vendor_sku_flat_table.'.str_replace('@','',$search[0]);
					if($key ==0){
						$query->where($column_name,'LIKE','%'.$search[1].'%');
					}else{
						$query->orWhere($column_name,'LIKE','%'.$search[1].'%');
					}
				}
			}else{
				$query->where('vendor_sku_flat_table.name','LIKE','%'.$search_term.'%')
				->orWhere('vendor_sku_detail_barcodes.barcode', 'LIKE', '%'.$search_term.'%');
			}
		});


		return $product;
		
	}

}