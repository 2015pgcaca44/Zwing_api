<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Http\Traits\V1\VendorFactoryTrait;
use DB;
use App\Offer;
use App\Wishlist;
use App\Scan;
use Auth;

class ProductController extends Controller
{

    use VendorFactoryTrait;

	public function __construct()
	{
		$this->middleware('auth');
	}

    public function product_details(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
 
    }
	
	public function product_search(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }
}
