<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\VendorFactoryTrait;
use Illuminate\Http\Request;
use DB;
use App\Model\Items\VendorSku;

class ProductSearchController extends Controller
{
	use VendorFactoryTrait;

	public function __construct()
	{
		// $this->middleware('auth');
	}


	public function search(Request $request){
		return $this->callMethod($request, __CLASS__, __METHOD__);
	
	}//End function

	public function fieldSearch(Request $request){
		$v_id 		 = $request->v_id;
		$dbName = getDatabaseName($v_id);

		$results = DB::select( DB::raw("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE  TABLE_SCHEMA ='$dbName' and TABLE_NAME = 'vendor_sku_flat_table' and  COLUMN_NAME not in ('id','v_id','vendor_sku_detail_id', 'variant_combi', 'sku' , 'uom_conversion_id', 'brand_id' , 'purchase_uom_id' ,'selling_uom_id' , 'purchase_uom_type','selling_uom_type','has_batch' ,'has_serial','tax_group_id' ,'created_at','updated_at') ") );

		$data = [];

		foreach($results as $result){

			$value = str_replace('va_' , 'Variant Att: ' , $value); 
			$value = str_replace('a_' , 'Attribute: ' , $result->COLUMN_NAME); 
			$value = str_replace('cat_name_' , 'Category ' , $value); 
			$value = ucfirst( str_replace('_' , ' ' , $value) ); 
			
			$data[$result->COLUMN_NAME] = $value; 
		}

		return $data;
	}


	public function advancedSearch(Request $request, $product)
	{
		
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

}