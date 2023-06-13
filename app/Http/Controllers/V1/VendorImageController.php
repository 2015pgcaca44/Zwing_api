<?php
namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\VendorImage;
use DB;
use Auth;

class VendorImageController extends Controller
{
	public $List_Logo 			= 1;
	public $List_Background 	= 2;
	public $Order_List_Logo 	= 3;
	public $Detail_Header_Logo 	= 4;
	public $Bill_Logo 			= 5;
	public $Cost_List_Logo 		= 6;
	public $Store_Banner_Image 	= 7;
	public $Restaurant_Image 	= 8;

	public function __construct()
	{
		$this->middleware('auth');
	}	

	public function getImage($vid,$type){
		if($vid){
			$image    = VendorImage::where('v_id', $vid)->where('type',$type)->where('status',1)->where('deleted',0)->first();
			if($image){
				return $image->path;
			}else{
				return '';
			}
		}else{
			return '';
		}
	}

	public function getItemImage($barcode,$database){
		if($barcode){
			$image   = DB::table($database.'.price_master')->where('ITEM', $barcode)->select('IMAGE')->first();
			if($image){
				return $image->IMAGE;
			}else{
				return '';
			}
		}else{
				return '';
		}
	}
}

?>