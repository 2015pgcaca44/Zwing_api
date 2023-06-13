<?php

use App\Rating;
use App\Vendor;
use App\VendorDetails;
use App\Store;
use Illuminate\Support\Facades\Artisan;
use App\MOPClient;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Oauth\OauthClient;
use App\CashTransactionLog;
use App\CashTransaction;
use App\CashPoint;
use Illuminate\Http\Request;
use App\Vendor\VendorRoleUserMapping;
use App\Invoice;
use App\Organisation;
use App\CashRegister;
use App\Model\Client\ClientVendorMapping;
use App\ExchnageRateHeader;
use App\ExchangeRateDetail;
use App\Country;
use App\VendorSetting;
// use App\CashPoint;

if (!function_exists('getGlobalConsoleURL')) {
    function getGlobalConsoleURL() {
        if (app()->environment('local')) {
          return 'http://localhost:8008/zwingp/console';
         } elseif (app()->environment('test')) {
            return 'https://test.console.gozwing.com';
        } elseif (app()->environment('staging')) {
            return 'https://staging.console.gozwing.com';
        } elseif (app()->environment('production')) {
            return 'https://console.gozwing.com';
        } elseif(app()->environment('development')){
            return 'https://dev.console.gozwing.com';
        }
    }
}

if (!function_exists('getBatchExpireDate')) {
    function getBatchExpireDate($param) {
    	$calList = ['DAY' => 'day', 'WEEK' => 'day', 'MON' => 'months', 'YEAR' => 'years'];
    	$validty = $param['validty'];
    	if($param['type'] == 'WEEK') {
    		$validty = $param['validty'] * 7;
    	}
    	$expireDate = date('Y-m-d' ,strtotime('+'.$validty.' '.$calList[$param['type']], strtotime($param['mfg_date'])));
    	return $expireDate;
    }
} 

if (!function_exists('emptyCheker')) {
    function emptyCheker($val){
       return empty($val) ? '-' : $val;
    }
} 

if (!function_exists('getCashTranscationType')) {
    function getCashTranscationType($v_id, $id, $via) {
    	JobdynamicConnection($v_id);
        $cashPoint = CashPoint::select('cash_point_name','cash_point_type')->where([ 'v_id' => $v_id, 'id' => $id ])->first();
        if($cashPoint->cash_point_type == 'Store-Cash') {
            $cashPoint->type = 'SCP'.$via;
        } elseif ($cashPoint->cash_point_type == 'Terminal') {
            $cashPoint->type = 'TCP'.$via;
        } elseif ($cashPoint->cash_point_type == 'Third-Party') {
            $cashPoint->type = 'TPCASH'.$via;
        } 
        return $cashPoint;
    }
} 

if (!function_exists('pendingDaySettlementDay')) {
    function pendingDaySettlementDay($lastDaySettlmentDate){
        $current_date  =  date('Y-m-d');
	    $ldsd          =   date_create($lastDaySettlmentDate);
	    $cdate         =   date_create($current_date);
	    $diff          =   date_diff($ldsd,$cdate);
	    return (int)$diff->format("%a");
    }
} 

if (!function_exists('getStringBetween')) {
    function getStringBetween($string, $start, $end){
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }
} 

if (!function_exists('getFullQuery')) {
    function getFullQuery($query) {
        $rowquery = $query->toSql();
        $rowquery = str_replace('?', "'?'", $rowquery);  
        $bindList = $query->getBindings(); 
        $rowQuery = str_replace_array('?', $bindList, $rowquery);
        return $rowQuery;
   }
}

if (!function_exists('getRoundValue')) {
    function getRoundValue($value) {
    	$data = [];
		$whole = (int) $value;
		$frac  = $value - $whole;
		if(round($frac, 2) <= 0.49) {
			// $data['sign'] = '-';
			$data = -round($frac, 2);
		} else {
			// $data['sign'] = '+';
			$data = 1 - $frac;
			$data = round($data, 2);
		}
		// $data['value'] = round($frac, 2);
		return $data;
    }
}

if (!function_exists('jsonToArray')) {
    function jsonToArray($arr, $unique = false) {
        $data = [];
        foreach ($arr as $key => $value) {
            $data[] = json_decode($value);
        }
        if($unique) {
            return collect($data)->flatten()->unique()->toArray();
        } else {
            return collect($data)->flatten()->toArray();
        }
    }
}

if (!function_exists('generateSkuCode')) {
    function generateSkuCode($v_id) {

        JobdynamicConnection($v_id);
        
        $sku_code = '';
        $vendor = Organisation::select('id','vendor_code')->where('id',$v_id)->first();
        $vendorDetail = VendorDetails::where('v_id', $vendor->id)->first();

        if($vendorDetail){
            $mapping = \App\Model\ZwingRegionCountryMapping::select('region_id')->where('country_id', $vendorDetail->country)->first();
            $reqion = \App\Model\ZwingRegion::select('code')->where('id', $mapping->region_id)->first();
            $sku_code =  $reqion->code.$vendor->vendor_code;

            $sku = VendorSkuDetails::select('sku_code')->where('v_id', $v_id)->whereNotNull('sku_code')->withTrashed()->orderByDesc('sku_code')->first();
            $sku_inc = '000001';
            if($sku){
                if($sku->sku_code != '' && $sku->sku_code != null){
                    $sku_inc = substr( (string)$sku->sku_code , -6);
                    $sku_inc = (int)$sku_inc +1;
                    $sku_inc = sprintf("%06d",$sku_inc);

                }
            }

            $sku_code .=  $sku_inc;
            return (int)$sku_code;
        }

        return null;
    }
} 


if (!function_exists('generateBatchCode')) {
    function generateBatchCode($v_id) {

        JobdynamicConnection($v_id);
        
        
        $batch_code = '';
        $vendor = Organisation::select('id','vendor_code')->where('id',$v_id)->first();

        if($vendor){

        	$batch_code = time();
            $batch = App\Model\Stock\Batch::select('batch_code')->where('v_id', $v_id)->orderByDesc('id')->first();
            $batch_inc = '001';
            if($batch){
                if($batch->batch_code != '' && $batch->batch_code != null){
                    $batch_inc = substr( (string)$batch->batch_code , -3);
                    $batch_inc = (int)$batch_inc +1;
                    $batch_inc = sprintf("%03d",$batch_inc);
                }
            }

            $batch_code .=  $batch_inc;
            return (int)$batch_code;
        }

        return null;
    }
}

if (!function_exists('generateSerialCode')) {
    function generateSerialCode($v_id) {

        JobdynamicConnection($v_id);
        
        $serial_code = '';
        $vendor = Organisation::select('id','vendor_code')->where('id',$v_id)->first();

        if($vendor){

        	$serial_code = time();
            $serial = App\Model\Stock\Serial::select('serial_code')->where('v_id', $v_id)->orderByDesc('id')->first();
            $serial_inc = '001';
            if($serial){
                if($serial->serial_code != '' && $serial->serial_code != null){
                    $serial_inc = substr( (string)$serial->serial_code , -3);
                    $serial_inc = (int)$serial_inc +1;
                    $serial_inc = sprintf("%03d",$serial_inc);
                }
            }

            $serial_code .=  $serial_inc;
            return (int)$serial_code;
        }

        return null;
    }
}

function oauthUser(Request $request){
	//dd($request->all());
	$token = $request->bearerToken();
	  $oauth=OauthClient::where('token', $token)->first();
	  if(!$oauth){
        $clientAuth = ClientVendorMapping::where('token', $token)->first();
        
        $oauth=OauthClient::where('id', $clientAuth->client_id)->first(); 

	  }

	return $oauth;

}

function getUomUnitPrice($barcode, $price, $v_id)
{
	$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
	$item = null;
	if($bar){
		$item = VendorSku::select('item_id','uom_conversion_id')->where('vendor_sku_detail_id', $bar->vendor_sku_detail_id)->where('v_id', $v_id)->first();
	}
	$numLength = [ '1' => 1, '2' => 10, '3' => 100, '4' => 1000 ];
	$num = is_decimal($item->uom->factor);
	if($num) {
		$length = explode(".", $item->uom->factor);
		$length = strlen($length[0]) + strlen($length[1]);
		$unit_price = $price / $numLength[$length];
		return $unit_price;
	} else {
		return $price;
	}
}

function current_date_in_string(){
	$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];

	return $current_date;
}

function advice_no_generate($vendor_id, $trans_from = 'ANDROID_VENDOR')
{
	$application = [
		'ANDROID' => '1', 'ANDROID_VENDOR' => '2',  'ANDROID_KIOSK' => '3',
		'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6',
		'VENDOR_PANEL' => '7' ,'THIRD_PARTY_APP' => '8',
	];
	$advice_no = 'ADV';
	$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
	//$vendor = DB::table('stores')->where('store_id',$store_id)->select('store_code')->first();
	$last_advice = DB::table('advice')->where('v_id', $vendor_id)->orderBy('id', 'desc')->first();

	$inc_id = '00001';
	if ($last_advice) {
		$last_advice_no = $last_advice->advice_no;
		$exists_date = substr($last_advice_no, 5, 3);
		$inc_id = substr($last_advice_no, 11, 5);
		$inc_id++;
		$inc_id = sprintf('%05d', $inc_id++);
	}
	$grnno = $advice_no . $application[$trans_from] . $vendor_id . $current_date . $inc_id;
	return $grnno;
}

function is_decimal( $val )
{
	return is_numeric( $val ) && floor( $val ) != $val;
}

function isValueChecker($value) 
{
	if($value != null && !empty($value) && $value != '') {
		return true;
	} else {
		return false;
	}
}

function adjustmentRemark($qty,$track){

	if($qty){
		$str = "This product has been $qty qty $track";
		return $str;
	}else{
		return false;
	}
}

function discountApportionOnItems($itemLists, $discount, $column)
{
	//dd($discount);
	$applicableItems = json_decode($discount->item_list);
	$allOrderItems = $itemLists->whereIn('cart_id', $applicableItems);

 


	$couponDiscountPer = getPercentageOfDiscount($allOrderItems->sum('total'), $discount->amount);

	 
	foreach ($allOrderItems as $key => $item) {
		// echo 'Before Discount : '.$item->$column;
		$item->$column = round($item->total * $couponDiscountPer / 100, 2);
		$item->total = $item->total - $item->$column;
		//echo 'After Discount : '.$item->$column;
	}
 


	// Calculate all item Discount & match to Bill level Discount

	$totalItemdiscount = $allOrderItems->sum($column);
	if ($discount->amount == $totalItemdiscount) {
		// echo 'Cool : -'.$totalItemdiscount;
	} elseif ($totalItemdiscount > $discount->amount) {
		$highestLPDAmt = $allOrderItems->sortByDesc($column)->first();
		$diffAmt = round($totalItemdiscount - $discount->amount, 2);
		$allOrderItems = $allOrderItems->map(function ($item, $key) use ($highestLPDAmt, $diffAmt, $column) {
			if ($item['id'] == $highestLPDAmt['id']) {
				$item[$column] = $item[$column] - $diffAmt;
				$item['total'] = $item['total'] + $diffAmt;
			} 
			return $item;
		});
		// echo 'Grater Then : -'.$allOrderItems->sum($column);
	} elseif ($totalItemdiscount < $discount->amount) {
		$lowestLPDAmt = $allOrderItems->sortBy($column)->first();
		$diffAmt = round($discount->amount - $totalItemdiscount, 2);
		$allOrderItems = $allOrderItems->map(function ($item, $key) use ($lowestLPDAmt, $diffAmt, $column) {
			if ($item['id'] == $lowestLPDAmt['id']) {
				$item[$column] = $item[$column] + $diffAmt;
				$item['total'] = $item['total'] - $diffAmt;
			} 
			return $item;
		});
		// echo 'Less Then : -'.$allOrderItems->sum('lpdiscount');
	}

//echo count($itemLists);die;
	return $itemLists;
}

function getMOPInfo($method, $client_id = 0, $filed)
{
	if ($client_id == 0) {
		return $method;
	} else {
		$mopclient = MOPClient::where('third_party_client_id', $client_id)->where('mop_code', $method)->first();
		if (empty($mopclient)) {
			return $method;
		} else {
			return $mopclient->mopName->$filed;
		}
	}
}

function getPercentageOfDiscount($total, $discount)
{
	// Equation: $discount/$total = P%
	$per = $discount / $total;
	// Convert decimal to percent
	$per = $per * 100;
	return $per;
}

function getDiscountOfPercentage($total,$percentage){
	$amount = ($percentage / 100) * $total;
	return $amount;
}

function isDataExists($db, $array)
{
	return DB::table($db)->where($array)->first();
}

function getDatabaseName($v_id){

	$dbName = env('DB_DATABASE');
	$vendor = $dbName.'.vendor';

	$result = DB::select( DB::raw("SELECT db_name,db_type FROM $vendor WHERE id = $v_id ") );

	if(isset($result[0]->db_type) ){
		if($result[0]->db_type == 'MULTITON'){
			$dbName = $result[0]->db_name;
		}

	}else{
		return null;
	}

	return $dbName;
}

function dynamicConnection($db)
{

	DB::disconnect();
	config(["database.connections.dynamic" => [
		"driver"     => "mysql",
		"host"      => env('DB_HOST'),
		"port"      =>  env('DB_PORT'),
		"database"  => $db,
		"username"  => env('DB_USERNAME'),
		"password"  => env('DB_PASSWORD'),
		'charset' => 'utf8mb4',
		'collation' => 'utf8mb4_unicode_ci',
		'prefix' => '',
		'strict' => false,
		'options'   => [PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                 \PDO::ATTR_EMULATE_PREPARES => true
            ]
	] ]);
	// Vendor::where('mobile', $request->mobile)->update([ 'api_token' => $checkVendorDBExists->api_token ]);
	config(['database.default' => 'dynamic']);
	Artisan::call('cache:clear');
	DB::reconnect();
	// $checkVendorDBExists = Vendor::where('mobile', $mobile)->first();
 //    if (!empty($checkVendorDBExists)) {
 //        if (!empty($checkVendorDBExists->vendor->db_name)) {

 //            $userExists = Vendor::where('mobile', $mobile)->first();
 //            if (empty($userExists)) {
 //             Vendor::create($checkVendorDBExists->toArray());
 //            }
 //        }
 //    }
}


function dynamicConnectionNew($params)
{
   DB::disconnect();
   config(["database.connections.dynamic" => [
        "driver"    => "mysql",
        "host"      => $params['host'],
        "port"      => $params['port'],
        "database"  => $params['db_name'],
        "username"  => $params['username'],
        "password"  => $params['password'],
        'strict'    => false,
        'engine'    => null,
        'charset' => 'utf8mb4',
		'collation' => 'utf8mb4_unicode_ci',
		'prefix' => '',
		'options'   => [PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                 \PDO::ATTR_EMULATE_PREPARES => true
            ]
    ] ]);
    // Vendor::where('mobile', $request->mobile)->update([ 'api_token' => $checkVendorDBExists->api_token ]);
    config(['database.default' => 'dynamic']);
	Artisan::call('cache:clear');
	DB::reconnect();

}


function JobdynamicConnection($v_id)
{

  // $organisation=Organisation::where('id',$v_id)->first();
  $organisation= new Organisation; 
  $organisation = $organisation->setConnection('mysql')->where('id',$v_id)->first();

  if($organisation){
  if($organisation->db_type == 'MULTITON'){	
  	
   DB::disconnect();
   config(["database.connections.dynamic" => [
        "driver"    => "mysql",
        "host"      => $organisation->connection->host,
        "port"      => $organisation->connection->port,
        "database"  => $organisation->db_name,
        "username"  => $organisation->connection->username,
        "password"  => $organisation->connection->password,
        'strict'    => false,
        'engine'    => null,
        'charset' => 'utf8mb4',
		'collation' => 'utf8mb4_unicode_ci',
		'prefix' => ''
    ] ]);
    // Vendor::where('mobile', $request->mobile)->update([ 'api_token' => $checkVendorDBExists->api_token ]);
    config(['database.default' => 'dynamic']);
	Artisan::call('cache:clear');
	DB::reconnect();

	$databaseName  = $databaseName = \DB::connection()->getDatabaseName();

	if($databaseName != $organisation->db_name){
		config(["database.connections.dynamic" => [
        "driver"    => "mysql",
        "host"      => $organisation->connection->host,
        "port"      => $organisation->connection->port,
        "database"  => $organisation->db_name,
        "username"  => $organisation->connection->username,
        "password"  => $organisation->connection->password,
        'strict'    => false,
        'engine'    => null,
        'charset' => 'utf8mb4',
		'collation' => 'utf8mb4_unicode_ci',
		'prefix' => ''
    ] ]);
    // Vendor::where('mobile', $request->mobile)->update([ 'api_token' => $checkVendorDBExists->api_token ]);
    config(['database.default' => 'dynamic']);
	Artisan::call('cache:clear');
	DB::reconnect();
	
	}


   }
  }
}


/*function dynamicConnection($db)
{
	
	config(['database.timezone' => 'dynamic']);
	Artisan::call('cache:clear');
	DB::reconnect();
	
}*/


function getTimeZone($v_id,$store_id=''){
	$details  = VendorDetails::where('v_id',$v_id)->first();
	$timezoneutc =array();
	if($details){
		if(isset($details->timezone)){
			$timezoneutc['utf_time'] =  $details->timezone->utc_offset_value;
			$timezoneutc['timezone'] =  $details->timezone->zone_name;
		}
	}
	if($store_id != ''){
		$store        = Store::find($store_id); 	
		if(isset($store->timezone)){
			$timezoneutc['utf_time'] =  $store->timezone->utc_offset_value;
			$timezoneutc['timezone'] =  $store->timezone->zone_name;
		}
	}
	return $timezoneutc;
}//End of getTimeZone

function isValueExists($value, $key, $type = 'str')
{
	if (empty($value)) {
		if ($type == 'str') {
			return '';
		} elseif ($type == 'num') {
			return 0;
		}
	} else {
		return $value->$key;
	}
}

function isObjectExists($object, $key)
{
	if(property_exists($object, $key)) {
		return $object->key;
	} else {
		return '';
	}
}

function recursiveFind(array $haystack, $needle)
{
	$iterator  = new RecursiveArrayIterator($haystack);
	$recursive = new RecursiveIteratorIterator(
		$iterator,
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($recursive as $key => $value) {
		if ($key === $needle) {
			return $value;
		}
	}
}

function get_vendor_id_from_vu_id($id) {
	return Vendor::find($id);
}

function removeElementWithValue($array, $key, $value) {
	foreach ($array as $subKey => $subArray) {
		if ($subArray->$key == $value) {
			unset($array[$subKey]);
		}
	}
	return $array;
}

function get_invoice_no($order_id) {
	$invocie = DB::table('invoices')->where('ref_order_id', $order_id)->first();
	if(empty($invocie)) {
		return '';
	} else {
		return $invocie->invoice_id;
	}
}

function get_vendor_column_name($id, $vid, $sid) {
	$v_column = DB::table('vendor_api_column')->select('name')->where('v_id', $vid)->where('store_id', $sid)->where('vapic_id', $id)->first();
	return $v_column->name;
}

function format_number($amount) {
	if ($amount === 0) {
		return '0.00';
	} elseif ($amount === null) {
		return null;
	} else {
		$amount = sprintf("%.2f", $amount);
		return $amount;
	}

}

function generateRandomString($length = 10) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}

function get_api_column_name($id) {
	$v_column = DB::table('api_columns')->select('Name')->where('api_id', $id)->first();
	return $v_column->Name;
}

function store_logo_link() {
	//return 'http://zwing.in/vendor/vendorstuff/store/logo/';
	return env('APP_URL') . '/vendorstuff/store/logo/';
}

function product_image_link() {
	//return 'http://zwing.in/vendor/images/product/';
	return env('APP_URL') . '/images/product/';
}

function image_path() {
	return env('APP_URL') . '/images/';
}

function store_random_number($number) {
	if (strlen($number) == 1) {$random = rand(111111, 999999) . $number . rand(11111, 99999);}
	if (strlen($number) == 2) {$random = rand(11111, 99999) . $number . rand(11111, 99999);}
	if (strlen($number) == 3) {$random = rand(11111, 99999) . $number . rand(1111, 9999);}
	if (strlen($number) == 4) {$random = rand(1111, 9999) . $number . rand(1111, 9999);}
	if (strlen($number) == 5) {$random = rand(1111, 9999) . $number . rand(111, 999);}
	return $random;
}

function order_id_random_number($number) {
	if (strlen($number) == 1) {$random = rand(111, 999) . $number . rand(11, 99);}
	if (strlen($number) == 2) {$random = rand(11, 99) . $number . rand(11, 99);}
	if (strlen($number) == 3) {$random = rand(11, 99) . $number . rand(1, 9);}
	if (strlen($number) == 4) {$random = rand(1, 9) . $number . rand(1, 9);}
	if (strlen($number) == 5) {$random = rand(1, 9) . $number;}
	return $random;
}

function store_uniquneid($randomletter) {
	$count = DB::table('testing_table')->where('order_id', $randomletter)->count();
	if (!empty($count)) {
		store_uniquneid($randomletter);
	}
	return $randomletter;
}

function order_id_generate($store_id, $user_id, $trans_from) {

	// $stores = DB::table('stores')->where('store_id', $store_id)->select('store_code','v_id')->first();

	//    $docsetting   = DB::table('doc_type_sett')->where('v_id',$stores->v_id)->first();
	//    if(!empty($docsetting )){

	//        $invoiceformats = DB::table('doc_type_sett')
 //            ->join('doc_type_sett_detail', 'doc_type_sett.id', '=', 'doc_type_sett_detail.doc_sett_id')
 //            ->join('doc_type_sec', 'doc_type_sett_detail.doc_sec_id', '=', 'doc_type_sec.id')
 //            ->select('doc_type_sett.v_id', 'doc_type_sec.name', 'doc_type_sec.label', 'doc_type_sec.type','doc_type_sett_detail.is_active','doc_type_sett_detail.no_of_char','doc_type_sett_detail.value','doc_type_sett_detail.date_format')
 //             ->where('doc_type_sett.v_id', $v_id)->get();
 //       dd($nvoicefromats);

	//    }else{
	/*$exists = DB::table('orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();
		$order_id = 'OD'.store_random_number($store_id).order_id_random_number($exists);
		$order_exists = DB::table('orders')->where('order_id', $order_id)->count();
		if(!empty($order_exists)) {
			order_id_generate($store_id, $user_id);
	*/

			$application = [
				'ANDROID' => '1', 'ANDROID_VENDOR' => '2', 'ANDROID_KIOSK' => '3',
				'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6',
				'CLOUD_TAB_WEB' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9' 
			];
			$order_id = 'O';
			$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	//$date = date();
			$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
			$stores = DB::table('stores')->where('store_id', $store_id)->select('store_code')->first();
			$last_order = DB::table('orders')->where('store_id', $store_id)->select('order_id')->orderBy('od_id', 'desc')->first();

			$inc_id = '00001';
			if (!empty($last_order)) {
				$last_order_id = $last_order->order_id;
				$exists_date = substr($last_order_id, 8, 3);
				if ($exists_date == $current_date) {
					$inc_id = substr($last_order_id, 11, 5);
					$inc_id++;
					$inc_id = sprintf('%05d', $inc_id++);
				}
			}

			$exists = DB::table('orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();

			$order_id = $order_id . $application[$trans_from] . $stores->store_code . $current_date . $inc_id;

			return $order_id;
   //}
		}

	//gv order doc no
	function gv_order_id_generate($store_id, $user_id, $trans_from) {


			$application = [
				'ANDROID' => '1', 'ANDROID_VENDOR' => '2', 'ANDROID_KIOSK' => '3',
				'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6',
				'CLOUD_TAB_WEB' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9' 
			];
			$order_id = 'GO';
			$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	//$date = date();
			$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
			$stores = DB::table('stores')->where('store_id', $store_id)->select('store_code')->first();
			$last_order = DB::table('gv_order')->where('store_id', $store_id)->select('gv_order_doc_no')->orderBy('gv_order_id', 'desc')->first();

			$inc_id = '00001';
			if (!empty($last_order)) {
				$last_order_id = $last_order->gv_order_doc_no;
				$exists_date = substr($last_order_id, 9, 3);
				if ($exists_date == $current_date) {
					$inc_id = substr($last_order_id, 12, 5);
					$inc_id++;
					$inc_id = sprintf('%05d', $inc_id++);
				}
			}

			$exists = DB::table('gv_order')->where('store_id', $store_id)->where('customer_id', $user_id)->count();

			$order_id = $order_id . $application[$trans_from] . $stores->store_code . $current_date . $inc_id;

			return $order_id;
   
		}	

		function invoice_id_generate($store_id, $user_id,$trans_from,$invoice_seq,$udidtoken ='',$seq_id='') {

			//dd($invoice_seq);
			/*$exists = DB::table('orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();
				$order_id = 'OD'.store_random_number($store_id).order_id_random_number($exists);
				$order_exists = DB::table('orders')->where('order_id', $order_id)->count();
				if(!empty($order_exists)) {
					order_id_generate($store_id, $user_id);
			*/
		

			$stores = DB::table('stores')->where('store_id', $store_id)->select('store_code','v_id')->first();

			$docsetting   = DB::table('doc_type_sett')->where('v_id',$stores->v_id)->where('status', '!=', '1')->first();
			if(!empty($docsetting )){

				$invoiceformats = DB::table('doc_type_sett')
				->join('doc_type_sett_detail', 'doc_type_sett.id', '=', 'doc_type_sett_detail.doc_sett_id')
				->select('doc_type_sett.v_id', 'doc_type_sett_detail.is_active','doc_type_sett_detail.no_of_char','doc_type_sett_detail.value','doc_type_sett_detail.date_format','doc_type_sett_detail.doc_sec_id')
				->where('doc_type_sett.v_id', $stores->v_id)
				->where('doc_type_sett_detail.is_active','1')->get();
				// dd($invoiceformats);
				$order_id = '';
				$terminal_id = get_terminal_id($store_id,$stores->v_id,$udidtoken);
				$date_format_type = ''; 
				$date_formats='';    
				foreach ($invoiceformats as $key => $invoiceformat) {

					if($invoiceformat->doc_sec_id=='2'){
						$value = get_store_short_code($store_id,$stores->v_id);

					}elseif($invoiceformat->doc_sec_id=='3'){
						$value = get_tag_terminal_code($store_id,$stores->v_id,$udidtoken);
					}elseif($invoiceformat->doc_sec_id=='4'&& $invoiceformat->value=="DAILY"){
						$date_format_type = $invoiceformat->value; 
						$date_formats =$invoiceformat->date_format;
						$value=dateFormat($date_formats);
					}elseif($invoiceformat->doc_sec_id=='4'&& $invoiceformat->value=="MONTHLY"){
						$date_format_type = $invoiceformat->value;
						$date_formats =$invoiceformat->date_format;
						$value=monthdateFormat($date_formats);
					}elseif($invoiceformat->doc_sec_id=='4'&& $invoiceformat->value=="YEARLY"){						
						$date_format_type = $invoiceformat->value;
						$date_formats =$invoiceformat->date_format;
						$value= yearlyInvoiceFormat($date_formats);
					}elseif($invoiceformat->doc_sec_id=='5'){
						if(!empty($invoice_seq)){
                           $value = $invoice_seq;
						}else{
						$value= oreder_id_increment($store_id,$invoiceformat->value,$trans_from,$terminal_id,$date_format_type,$date_formats);
					   }
					}
					else{
						$value = $invoiceformat->value; 
					}
					$order_id .= $value;
					//dd($order_id);
				}
				if(!empty($seq_id)){
					return oreder_id_increment($store_id,$invoiceformat->value,$trans_from,$terminal_id,$date_format_type,$date_formats);
				}
				return $order_id;
			}else{
				$application = [
					'ANDROID' => '1', 'ANDROID_VENDOR' => '2', 'ANDROID_KIOSK' => '3',
					'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6', 
					'CLOUD_TAB_WEB' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9' 
				];
				$order_id = 'Z';
				$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
				//$date = date();
				$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
				$stores = DB::table('stores')->where('store_id', $store_id)->select(['store_code','v_id'])->first();
				$last_order = DB::table('invoices')->where('store_id', $store_id)->select('invoice_id')->orderBy('id', 'desc')->first();

				$inc_id = '00001';
				if($stores->v_id == 7){
					if ($last_order) {

						//Z2001001J4Q00001
						//Z19-20-000001

						$inc_id = '000001';
						$current_year  = date('y');

						$next_year     = date('y',strtotime('+1 year'));
						$current_month = date('m');


						if($current_month > 4){

							$prefix = $current_year.'-'.$next_year;
						}elseif($current_month < 4){
							$prefix = $previous_year.'-'.$current_year;
						}


						$last_order_id = $last_order->invoice_id;
						$exists_date = substr($last_order_id, 1, 5);

						if ($exists_date == $prefix) {
							$inc_id = substr($last_order_id, 7, 6);
							$inc_id++;
							$inc_id = sprintf('%06d', $inc_id++);
						}
					}

					$exists = DB::table('invoices')->where('store_id', $store_id)->where('user_id', $user_id)->count();

					$order_id = $order_id.$prefix.'-'. $inc_id; 
				}else{
					if ($last_order) {
						$last_order_id = $last_order->invoice_id;
						$exists_date = substr($last_order_id, 8, 3);

						if ($exists_date == $current_date) {
							$inc_id = substr($last_order_id, 11, 5);
							$inc_id++;
							$inc_id = sprintf('%05d', $inc_id++);
						}
					}

					$exists = DB::table('invoices')->where('store_id', $store_id)->where('user_id', $user_id)->count();

					$order_id = $order_id . $application[$trans_from] . $stores->store_code . $current_date . $inc_id;
				}
				if(!empty($seq_id)){
					return $inc_id;
				}
				return $order_id;
			}
		}

		function custom_order_id_generate($params) {
	/*$exists = DB::table('orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();
		$order_id = 'OD'.store_random_number($store_id).order_id_random_number($exists);
		$order_exists = DB::table('orders')->where('order_id', $order_id)->count();
		if(!empty($order_exists)) {
			order_id_generate($store_id, $user_id);
	*/
			$store_id = $params['store_id'];
			$user_id = $params['user_id'];
			$trans_from = $params['trans_from'];

			$application = [
				'ANDROID' => '1', 'ANDROID_VENDOR' => '2', 'ANDROID_KIOSK' => '3',
				'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6' ,
				'CLOUD_TAB_WEB' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9' 
			];
			$order_id = 'G';
			$device_code = '';
			$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	//$date = date();
			$stores = DB::table('stores')->where('store_id', $store_id)->select('store_code')->first();
			$inc_id = '00001';
			$store_code = $stores->store_code;
			$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
			$last_order = DB::table('orders')->where('trans_from', $trans_from)->where('store_id', $store_id)->select('order_id')->orderBy('od_id', 'desc')->first();

			if ($last_order) {
				$last_order_id = $last_order->order_id;
				$exists_date = substr($last_order_id, 8, 3);

				if ($exists_date == $current_date) {
					$inc_id = substr($last_order_id, 11, 5);
					$inc_id++;
					$inc_id = sprintf('%05d', $inc_id++);
				}
			}

			if ($trans_from != 'ANDROID' && $trans_from != 'IOS') {
				if (isset($params['udid'])) {
					$device = DB::table('device_storage as ds')
					->join('device_vendor_user as dvu', 'dvu.device_id', 'ds.id')
					->where('ds.udid', $request->udid)
					->where('dvu.v_id', $stores->v_id)
					->select('dvu.device_code', 'ds.id')
					->first();

					$device_code = $device->device_code;
					$current_date = $characters[date('y')] . $characters[date('m')];
					$last_order = DB::table('orders')->where('trans_from', $trans_from)->where('store_id', $store_id)->select('order_id', 'custom_order_id')->where('device_id', $device->id)->orderBy('od_id', 'desc')->first();
					$store_code = substr($stores->store_code, 1, 2);

					if ($last_order) {
						$last_order_id = $last_order->custom_order_id;
						$exists_date = substr($last_order_id, 9, 2);

						if ($exists_date == $current_date) {
							$inc_id = substr($last_order_id, 11, 5);
							$inc_id++;
							$inc_id = sprintf('%05d', $inc_id++);
						}
					}
				}
			}

			$exists = DB::table('orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();
			$order_id = $order_id . $application[$trans_from] . $store_code . $device_code . $current_date . $inc_id;

			return $order_id;	
		}

		function custom_invoice_id_generate($params) {
	/*$exists = DB::table('orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();
		$order_id = 'OD'.store_random_number($store_id).order_id_random_number($exists);
		$order_exists = DB::table('orders')->where('order_id', $order_id)->count();
		if(!empty($order_exists)) {
			order_id_generate($store_id, $user_id);
	*/
			$store_id = $params['store_id'];
			$user_id = $params['user_id'];
			$trans_from = $params['trans_from'];

			$application = [
				'ANDROID' => '1', 'ANDROID_VENDOR' => '2', 'ANDROID_KIOSK' => '3',
				'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6',
				'CLOUD_TAB_WEB' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9' 
			];
			$order_id = 'G';
			$device_code = '';
			$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	//$date = date();
			$stores = DB::table('stores')->where('store_id', $store_id)->select('store_code')->first();
			$inc_id = '00001';
			$store_code = $stores->store_code;
			$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
			$last_order = DB::table('invoices')->where('trans_from', $trans_from)->where('store_id', $store_id)->select('invoice_id')->orderBy('id', 'desc')->first();

			if ($last_order) {
				$last_order_id = $last_order->invoice_id;
				$exists_date = substr($last_order_id, 8, 3);

				if ($exists_date == $current_date) {
					$inc_id = substr($last_order_id, 11, 5);
					$inc_id++;
					$inc_id = sprintf('%05d', $inc_id++);
				}
			}

			if ($trans_from != 'ANDROID' && $trans_from != 'IOS') {
				if (isset($params['udid'])) {
					$device = DB::table('device_storage as ds')
					->join('device_vendor_user as dvu', 'dvu.device_id', 'ds.id')
					->where('ds.udid', $request->udid)
					->where('dvu.v_id', $stores->v_id)
					->select('dvu.device_code', 'ds.id')
					->first();

					$device_code = $device->device_code;
					$current_date = $characters[date('y')] . $characters[date('m')];
					$last_order = DB::table('invoices')->where('trans_from', $trans_from)->where('store_id', $store_id)->select('invoice_id', 'custom_order_id')->where('device_id', $device->id)->orderBy('id', 'desc')->first();
					$store_code = substr($stores->store_code, 1, 2);

					if ($last_order) {
						$last_order_id = $last_order->custom_order_id;
						$exists_date = substr($last_order_id, 9, 2);

						if ($exists_date == $current_date) {
							$inc_id = substr($last_order_id, 11, 5);
							$inc_id++;
							$inc_id = sprintf('%05d', $inc_id++);
						}
					}
				}
			}

			$exists = DB::table('invoices')->where('store_id', $store_id)->where('user_id', $user_id)->count();

			$order_id = $order_id . $application[$trans_from] . $store_code . $device_code . $current_date . $inc_id;

			return $order_id;
		}

		function grn_no_generate($vendor_id,$trans_from){
			$application = array(
				'ANDROID' => '1' , 'ANDROID_VENDOR' => '2' ,  'ANDROID_KIOSK' => '3' , 
				'IOS' => '4' , 'IOS_VENDOR' => '5' , 'IOS_KIOSK' => '6' ,
				'CLOUD_TAB_WEB' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9', 'VENDOR_PANEL' => '10'   );
			$tf =  trim($trans_from);
			$grnno = 'GRN';
			$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
			$current_date = $characters[date('y')].$characters[date('m')].$characters[date('d')];  
	//$vendor = DB::table('stores')->where('store_id',$store_id)->select('store_code')->first();
			$last_grn = DB::table('grn')->where('v_id', $vendor_id)->orderBy('id','desc')->first();

			$inc_id = '00001';
			if($last_grn){      
				$last_grn_no = $last_grn->grn_no;
				$exists_date = substr($last_grn_no ,5 ,3);
				$inc_id = substr($last_grn_no ,11 , 5);
				$inc_id++;
				$inc_id = sprintf('%05d', $inc_id++);  
			}
			$grnno = $grnno.$application[$tf].$vendor_id.$current_date.$inc_id;
			return $grnno;
}//End of grn_no_generate

function isWithInTime($start, $end, $time) {
	if (($time >= $start) && ($time <= $end)) {
		return 'OPEN NOW';
	} else {
		return 'CLOSED';
	}
}

function store_rating($store_id, $v_id) {
	$first = Rating::where('Store_ID', $store_id)->where('V_ID', $v_id)->where('Star', 1)->count();
	$second = Rating::where('Store_ID', $store_id)->where('V_ID', $v_id)->where('Star', 2)->count();
	$third = Rating::where('Store_ID', $store_id)->where('V_ID', $v_id)->where('Star', 3)->count();
	$fourth = Rating::where('Store_ID', $store_id)->where('V_ID', $v_id)->where('Star', 4)->count();
	$fifth = Rating::where('Store_ID', $store_id)->where('V_ID', $v_id)->where('Star', 5)->count();
	$star = 1 * $first + 2 * $second + 3 * $third + 4 * $fourth + 5 * $fifth;
	$total = $first + $second + $third + $fourth + $fifth;
	// $average = $star / $total;
	if (empty($star) && empty($total)) {
		return '0.0';
	} else {
		return number_format($star / $total, 1);
	}
}

function get_store_db_name($params) {

	$store_db = null;
	if (isset($params['store_id'])) {
		$store = DB::table('stores')->select('store_db_name')->where('store_id', $params['store_id'])->first();
		if ($store) {
			$store_db = $store->store_db_name;
		}
	}

	return $store_db;
}

function manyavarCategory($barcode) {
	// echo $barcode;
	$data = [];
	$item = DB::table('manyavar.invitem')->select('CNAME2', 'GRPCODE', 'INVARTICLE_CODE')->where('ICODE', $barcode)->first();

	// dd($item);
	$group = DB::table('manyavar.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME')->where('GRPCODE', $item->GRPCODE)->first();
	$article = DB::table('manyavar.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
	$division = DB::table('manyavar.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV1GRPNAME)->first();
	$section = DB::table('manyavar.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV2GRPNAME)->first();
	$data = [
		'category' => $item->CNAME2,
		'article' => $article->NAME,
		'department' => $group->GRPNAME,
		'division' => $division->GRPNAME,
		'section' => $section->GRPNAME,
	];
	return $data;
}

function vmartCategory($barcode) {
	// echo $barcode;
	$data = [];
	$item = DB::table('vmart.invitem')->select('CNAME2', 'GRPCODE', 'INVARTICLE_CODE')->where('ICODE', $barcode)->first();

	// dd($item);
	$group = DB::table('vmart.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME')->where('GRPCODE', $item->GRPCODE)->first();
	$article = DB::table('vmart.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
	$division = DB::table('vmart.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV1GRPNAME)->first();
	$section = DB::table('vmart.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV2GRPNAME)->first();
	$data = [
		'category' => $item->CNAME2,
		'article' => $article->NAME,
		'department' => $group->GRPNAME,
		'division' => $division->GRPNAME,
		'section' => $section->GRPNAME,
	];
	return $data;
}

function ginesysCategory($barcode) {
	// echo $barcode;
	$data = [];
	$item = DB::table('ginesys_demo.invitem')->select('CNAME2', 'GRPCODE', 'INVARTICLE_CODE')->where('ICODE', $barcode)->first();

	// dd($item);
	$group = DB::table('ginesys_demo.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME')->where('GRPCODE', $item->GRPCODE)->first();
	$article = DB::table('ginesys_demo.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
	$division = DB::table('ginesys_demo.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV1GRPNAME)->first();
	$section = DB::table('ginesys_demo.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV2GRPNAME)->first();
	$data = [
		'category' => $item->CNAME2,
		'article' => $article->NAME,
		'department' => $group->GRPNAME,
		'division' => $division->GRPNAME,
		'section' => $section->GRPNAME,
	];
	return $data;
}

function crimsouneclubCategory($barcode) {
	$data = [];
	$item = DB::table('crimsouneclub.invitem')->select('CNAME2', 'GRPCODE', 'INVARTICLE_CODE')->where('ICODE', $barcode)->first();

	//dd($item);
	$group = DB::table('crimsouneclub.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME')->where('GRPCODE', $item->GRPCODE)->first();
	$article = DB::table('crimsouneclub.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
	$division = DB::table('crimsouneclub.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV1GRPNAME)->first();
	$section = DB::table('crimsouneclub.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV2GRPNAME)->first();
	$data = [
		'category' => $item->CNAME2,
		'article' => $article->NAME,
		'department' => $group->GRPNAME,
		'division' => $division->GRPNAME,
		'section' => $section->GRPNAME,
	];
	return $data;
}

function anandaCategory($barcode) {
	$data = [];
	$item = DB::table('ananda.invitem')->select('CNAME2', 'GRPCODE', 'INVARTICLE_CODE')->where('ICODE', $barcode)->first();

	//dd($item);
	$group = DB::table('ananda.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME')->where('GRPCODE', $item->GRPCODE)->first();
	$article = DB::table('ananda.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
	$division = DB::table('ananda.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV1GRPNAME)->first();
	$section = DB::table('ananda.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV2GRPNAME)->first();
	$data = [
		'category' => $item->CNAME2,
		'article' => $article->NAME,
		'department' => $group->GRPNAME,
		'division' => $division->GRPNAME,
		'section' => $section->GRPNAME,
	];
	return $data;
}

function vformat_and_string($value) {
	return (string) sprintf('%0.2f', $value);
}

function numberTowords($number, $type = '') {

	$words = array('zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty', 30 => 'thirty', 40 => 'fourty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety', 100 => 'hundred', 1000 => 'thousand');

	$number_in_words = '';
	if (is_numeric($number)) {
		$number = (int) round($number);
		if ($number < 0) {
			$number = -$number;
			$number_in_words = 'minus ';
		}
		if ($number >= 1000) {
			$number_in_words = $number_in_words.numberToWords(floor($number / 1000))." ".$words[1000];
			$hundreds = $number % 1000;
			$tens     = $hundreds % 100;
			if($hundreds >= 100) {
                if ($hundreds == 100) {
                    $hundreds = "one hundred";
                    $number_in_words = $number_in_words." ".$hundreds;
                } else {
                    $number_in_words = $number_in_words." ".numberToWords($hundreds);
                }
                // dd($number_in_words);
			}
   //          if($tens) {
			// 	$number_in_words = $number_in_words." and ".numberToWords($tens);
			// }
		} elseif ($number >= 100) {
            $tens = $number % 100;
                if ($number == 100) {
                    $number = $words[100];
                    $number_in_words = "One" .$number_in_words." ".$number;
                } else {
                    $number_in_words = $number_in_words.numberToWords(floor($number / 100))." ".$words[100];
                }
			
			if ($tens) {
				$number_in_words = $number_in_words." and ".numberToWords($tens);
			}
		} elseif ($number > 20) {
			$number_in_words = $number_in_words." ".$words[10 * floor($number / 10)];
			$units = $number % 10;
			if ($units) {
				$number_in_words = $number_in_words.numberToWords($units);
			}

		} else {
			$number_in_words = $number_in_words." ".$words[$number];
		}
		if ($type == 'vc') {
			return 'columns'.$number_in_words;
		} else {
			return trim($number_in_words);
		}
	}
	return 'None';
	
}


function numberTowords_new($num)
{

	$ones = array(
	0 =>"ZERO",
	1 => "ONE",
	2 => "TWO",
	3 => "THREE",
	4 => "FOUR",
	5 => "FIVE",
	6 => "SIX",
	7 => "SEVEN",
	8 => "EIGHT",
	9 => "NINE",
	10 => "TEN",
	11 => "ELEVEN",
	12 => "TWELVE",
	13 => "THIRTEEN",
	14 => "FOURTEEN",
	15 => "FIFTEEN",
	16 => "SIXTEEN",
	17 => "SEVENTEEN",
	18 => "EIGHTEEN",
	19 => "NINETEEN",
	"014" => "FOURTEEN"
	);
	$tens = array( 
	0 => "ZERO",
	1 => "TEN",
	2 => "TWENTY",
	3 => "THIRTY", 
	4 => "FORTY", 
	5 => "FIFTY", 
	6 => "SIXTY", 
	7 => "SEVENTY", 
	8 => "EIGHTY", 
	9 => "NINETY" 
	); 
	$hundreds = array( 
	"HUNDRED", 
	"THOUSAND", 
	"MILLION", 
	"BILLION", 
	"TRILLION", 
	"QUARDRILLION" 
	); /*limit t quadrillion */
	$num = number_format($num,2,".",","); 
	$num_arr = explode(".",$num); 
	$wholenum = $num_arr[0]; 
	$decnum = $num_arr[1]; 
	$whole_arr = array_reverse(explode(",",$wholenum)); 
	krsort($whole_arr,1); 
	$rettxt = ""; 
	foreach($whole_arr as $key => $i){
		
	while(substr($i,0,1)=="0")
			$i=substr($i,1,5);
	if($i < 20){ 
	/* echo "getting:".$i; */
	$rettxt .= isset($ones[$i])?$ones[$i]:''; 
	}elseif($i < 100){ 
	if(substr($i,0,1)!="0")  $rettxt .= $tens[substr($i,0,1)]; 
	if(substr($i,1,1)!="0") $rettxt .= " ".$ones[substr($i,1,1)]; 
	}else{ 
	if(substr($i,0,1)!="0") $rettxt .= $ones[substr($i,0,1)]." ".$hundreds[0]; 
	if(substr($i,1,1)!="0")$rettxt .= " ".$tens[substr($i,1,1)]; 
	if(substr($i,2,1)!="0")$rettxt .= " ".$ones[substr($i,2,1)]; 
	} 
	if($key > 0){ 
	$rettxt .= " ".$hundreds[$key]." "; 
	}
	} 
	if($decnum > 0){
	$rettxt .= " and ";
	if($decnum < 20){
	$rettxt .= $ones[$decnum];
	}elseif($decnum < 100){
	$rettxt .= $tens[substr($decnum,0,1)];
	$rettxt .= " ".$ones[substr($decnum,1,1)];
	}
	}
	return $rettxt;
}

function generateThirdPartyUserId($v_id, $store_id) {
	$inr=1;
	$make_id = date('d').date('m').date('Y').$v_id.$store_id.'Z'.$inr;
	$last_id = DB::table('order_extra')->where('v_id',$v_id)->where('store_id', $store_id)->select('usersession')->orderBy('ex_id', 'desc')->first();
	if(!empty($last_id->usersession)){
		$idGenerate = explode('Z', $last_id->usersession);
		$get_id = $idGenerate[1]+1;
		$make_id = $idGenerate[0].'Z'.$get_id;
	}
	return $make_id;
}

function get_email_triggers($params) {

	$store_id = $params['store_id'];
	$v_id = $params['v_id'];
	$email_trigger_code = $params['email_trigger_code'];

	$email_triggers = DB::table('email_events as ee')
	->join('email_triggers as et', 'et.email_event_id', 'ee.id')
	->select('et.to_mail', 'et.cc', 'et.bcc')
	->where('ee.code', $email_trigger_code)->where('v_id', $v_id)->get();
	$email = $email_triggers->where('store_id', $store_id)->first();
	if ($email) {
		$to = explode(',', $email->to_mail);
		$cc = explode(',', $email->cc);
		$bcc = explode(',', $email->bcc);
	} else {
		$to = explode(',', $email_triggers->first()->to_mail);
		$cc = explode(',', $email_triggers->first()->cc);
		$bcc = explode(',', $email_triggers->first()->bcc);
	}

	$to = array_filter($to);
	$cc = array_filter($cc);
	$bcc = array_filter($bcc);

	return ['to' => $to, 'cc' => $cc, 'bcc' => $bcc];
}

// function get_product_name($id,$sid,$vid)
// {
//  $v_column = DB::table(' zwv_inventory'.$v_id.$sid)->select('Name')->where('api_id', $id)->first();
//  return $v_column->Name;
// }

/*b2b order order id */

function b2b_order_id_generate($store_id, $user_id, $trans_from) {
	

	$application = [
		'ANDROID' => '1', 'ANDROID_VENDOR' => '2', 'ANDROID_KIOSK' => '3',
		'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6',
		'VENDOR_PANEL' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9' 
	];
	$order_id = 'O';
	$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	//$date = date();
	$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
	$stores = DB::table('stores')->where('store_id', $store_id)->select('store_code')->first();
	$last_order = DB::table('b2b_orders')->where('store_id', $store_id)->select('order_id')->orderBy('od_id', 'desc')->first();

	$inc_id = '00001';
	if (!empty($last_order)) {
		$last_order_id = $last_order->order_id;
		$exists_date = substr($last_order_id, 8, 3);
		if ($exists_date == $current_date) {
			$inc_id = substr($last_order_id, 11, 5);
			$inc_id++;
			$inc_id = sprintf('%05d', $inc_id++);
		}
	}

	$exists = DB::table('b2b_orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();

	$order_id = $order_id . $application[$trans_from] . $stores->store_code . $current_date . $inc_id;

	return $order_id;
}

/* b2b custom order id*/

function b2b_custom_order_id_generate($params) {
	/*$exists = DB::table('orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();
		$order_id = 'OD'.store_random_number($store_id).order_id_random_number($exists);
		$order_exists = DB::table('orders')->where('order_id', $order_id)->count();
		if(!empty($order_exists)) {
			order_id_generate($store_id, $user_id);
	*/
			$store_id = $params['store_id'];
			$user_id = $params['user_id'];
			$trans_from = $params['trans_from'];

			$application = [
				'ANDROID' => '1', 'ANDROID_VENDOR' => '2', 'ANDROID_KIOSK' => '3',
				'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6' ,
				'VENDOR_PANEL' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9' 
			];
			$order_id = 'G';
			$device_code = '';
			$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	//$date = date();
			$stores = DB::table('stores')->where('store_id', $store_id)->select('store_code')->first();
			$inc_id = '00001';
			$store_code = $stores->store_code;
			$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
			$last_order = DB::table('b2b_orders')->where('trans_from', $trans_from)->where('store_id', $store_id)->select('order_id')->orderBy('od_id', 'desc')->first();

			if ($last_order) {
				$last_order_id = $last_order->order_id;
				$exists_date = substr($last_order_id, 8, 3);

				if ($exists_date == $current_date) {
					$inc_id = substr($last_order_id, 11, 5);
					$inc_id++;
					$inc_id = sprintf('%05d', $inc_id++);
				}
			}

			if ($trans_from != 'ANDROID' && $trans_from != 'IOS') {
				if (isset($params['udid'])) {
					$device = DB::table('device_storage as ds')
					->join('device_vendor_user as dvu', 'dvu.device_id', 'ds.id')
					->where('ds.udid', $request->udid)
					->where('dvu.v_id', $stores->v_id)
					->select('dvu.device_code', 'ds.id')
					->first();

					$device_code = $device->device_code;
					$current_date = $characters[date('y')] . $characters[date('m')];
					$last_order = DB::table('b2b_orders')->where('trans_from', $trans_from)->where('store_id', $store_id)->select('order_id', 'custom_order_id')->where('device_id', $device->id)->orderBy('od_id', 'desc')->first();
					$store_code = substr($stores->store_code, 1, 2);

					if ($last_order) {
						$last_order_id = $last_order->custom_order_id;
						$exists_date = substr($last_order_id, 9, 2);

						if ($exists_date == $current_date) {
							$inc_id = substr($last_order_id, 11, 5);
							$inc_id++;
							$inc_id = sprintf('%05d', $inc_id++);
						}
					}
				}
			}

			$exists = DB::table('b2b_orders')->where('store_id', $store_id)->where('user_id', $user_id)->count();

			$order_id = $order_id . $application[$trans_from] . $store_code . $device_code . $current_date . $inc_id;

			return $order_id;
		}


		function encrypt_decrypt($action, $string) {
			$output = false;
			$encrypt_method = "AES-256-CBC";
			$secret_key = 'zwingudid';
			$secret_iv = 'zwing123';
	// hash
			$key = hash('sha256', $secret_key);

	// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
			$iv = substr(hash('sha256', $secret_iv), 0, 16);
			if ( $action == 'encrypt' ) {
				$output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
				$output = base64_encode($output);
			} else if( $action == 'decrypt' ) {
				$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
			}
			return $output;
		}

		function get_tag_terminal_code($store_id,$v_id,$udidtoken){
			$tag = CashRegister::select('terminal_code')->where('store_id', $store_id)->where('v_id', $v_id)->where('udidtoken',$udidtoken)->first();
            if($tag){
			return $tag->terminal_code;
           }else{
            return response()->json(['status' => 'fail', 'message' => 'This device is already registered with an other store license key.'], 200);
			}

		}

		function get_store_short_code($store_id,$v_id){

			$s_code = DB::table('stores')->select('short_code')->where('store_id', $store_id)->where('v_id', $v_id)->first();
			return $s_code->short_code;
		}
		function randomchar(){

			$length =1;
			$char = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
			return $char; 
		}
		function dateFormat($date_formats){
			$date_format = $date_formats;
			$format =explode('/', $date_format);
			switch ($format) {	      
				case ($format[0]=='n' && $format[1]=='y' && $format[2]=='d'):
				return  monthformate(date('n')).date('y').date('d');
				break;    
				case ($format[0]=='n' && $format[1]=='d' && $format[2]=='y'):
				return  monthformate(date('n')).date('d').date('y');
				break;           
				case ($format[0]=='y' && $format[1]=='n' && $format[2]=='d'):
				return date('y'). monthformate(date('n')).date('d');
				break; 
				case ($format[0]=='y' && $format[1]=='d' && $format[2]=='n'):
				return date('y').date('d'). monthformate(date('n'));
				break;
				case ($format[0]=='d' && $format[1]=='n' && $format[2]=='y'):
				return date('d').monthformate(date('n')). date('y');
				break; 
				case ($format[0]=='d' && $format[1]=='y' && $format[2]=='n'):
				return date('d').date('y').monthformate(date('n'));
				break;
				default:
				return date('y');      
			 }
    }

         function monthdateFormat($date_formats){
			$date_format = $date_formats;
			$format =explode('/', $date_format);
			switch ($format) {
				case ($format[0]=='y' && $format[1]=='n'):
				return  date('y').monthformate(date('n'));
				break;
				case ($format[0]=='n' && $format[1]=='y'):
				return  monthformate(date('n')).date('y');
				break;
			}
		}

		function oreder_id_increment($store_id,$inc_id,$trans_from,$terminal_id,$date_format_type,$date_formats){
           //dd($date_format_type); 
		   $current_date = date('Y-m-d');
           $current_month = date('m');
           $current_year  = date('Y');
		   $inc_id;
		   $orderIdlength = strlen($inc_id);
		   $currentlength=0;
		   $increment='';
		   $last_order =Invoice::where('store_id', $store_id)
					            ->where('terminal_id',$terminal_id)
								->orderBy('id','desc')
								->first();
            
		    if(!empty($last_order) && $date_format_type=="DAILY" && $last_order->date ==$current_date){
				$n  = strlen($inc_id);
			    $current_id = substr($last_order->invoice_id,-$n);
			    $inc=++$current_id;
				$increment =str_pad($inc,$n,"0",STR_PAD_LEFT);
				$currentlength =strlen($increment);
		    }elseif(!empty($last_order) && $date_format_type=="MONTHLY" && $last_order->month ==$current_month){
				$n  = strlen($inc_id);
			    $current_id = substr($last_order->invoice_id,-$n);
			    $inc=++$current_id;
				$increment =str_pad($inc,$n,"0",STR_PAD_LEFT);
				$currentlength =strlen($increment);
		    }elseif(!empty($last_order) && $date_format_type=="YEARLY" && $date_formats==1 && $last_order->year ==$current_year){
				$n  = strlen($inc_id);
			    $current_id = substr($last_order->invoice_id,-$n);
			    $inc=++$current_id;
				$increment =str_pad($inc,$n,"0",STR_PAD_LEFT);
				$currentlength =strlen($increment);
			}elseif(!empty($last_order) && $date_format_type=="YEARLY" && $date_formats==2 && $last_order->financial_year==getFinancialYear()){
				$n  = strlen($inc_id);
			    $current_id = substr($last_order->invoice_id,-$n);
			    $inc=++$current_id;
				$increment =str_pad($inc,$n,"0",STR_PAD_LEFT);
				$currentlength =strlen($increment);	
			}elseif(!empty($last_order) && $date_format_type==""){
				$n  = strlen($inc_id);
			    $current_id = substr($last_order->invoice_id,-$n);
			    $inc=++$current_id;
				$increment =str_pad($inc,$n,"0",STR_PAD_LEFT);
				$currentlength =strlen($increment);
			}
             if(!empty($last_order) && strlen($last_order->invoice_sequence)!=$orderIdlength){
               $inc_order_id=$inc_id;
            }elseif($currentlength==$orderIdlength){
              $inc_order_id=$increment;                
             }elseif(empty($last_order)){
              $inc_order_id=$inc_id;
             }else{
              $number = 1;
              $inc_order_id=str_pad($number,$orderIdlength, "0", STR_PAD_LEFT);
             }
			return $inc_order_id;

		}
		function cashPointAvailableCash($v_id,$store_id,$cash_point_id){

			$availableCash=CashTransactionLog::where('v_id',$v_id)
			->where('store_id',$store_id)
			->where('cash_point_id',$cash_point_id)
			->sum('amount'); 
			return $availableCash;                 
		}

		function getRoleId($vu_id){
			$role = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first();
			return $role->role_id;
		}

		function storeCashPoint($store_id,$v_id){
			$storeCashPoint=   CashPoint::where('store_id',$store_id)
			->where('v_id',$v_id)
			->where('cash_point_type_id','2')
			->first();  
			return $storeCashPoint;
		}       


		function offline_invoice_id_generate($params) {

			$v_id = $params['v_id'];
			$trans_from    = $params['trans_from'];
			$store_id      = $params['store_id'];
			$udidtoken     = $params['udidtoken'];
			$terminal_id = get_terminal_id($store_id,$v_id,$udidtoken);
			$docsetting    = DB::table('doc_type_sett')->where('v_id',$v_id)->where('status', '!=', '1')->first();
			if(!empty($docsetting )){
				$invoiceformats = DB::table('doc_type_sett')
				->join('doc_type_sett_detail', 'doc_type_sett.id', '=', 'doc_type_sett_detail.doc_sett_id')
				->select('doc_type_sett.v_id', 'doc_type_sett_detail.is_active','doc_type_sett_detail.no_of_char','doc_type_sett_detail.value','doc_type_sett_detail.date_format','doc_type_sett_detail.doc_sec_id')
				->where('doc_type_sett.v_id', $v_id)->get();
				$value = []; 
				$date_format_type = '';
				$date_formats='';    
				foreach ($invoiceformats as $key => $invoiceformat) {
					if($invoiceformat->doc_sec_id=='1'){
						$value['prefix'] = ['name'=>$invoiceformat->value,
						'status'=>$invoiceformat->is_active
					];
				}    
				elseif($invoiceformat->doc_sec_id=='2'){
					$value['short_code'] = ['name'=>get_store_short_code($store_id,$v_id),
					'status'=>$invoiceformat->is_active,
				];

			}elseif($invoiceformat->doc_sec_id=='3'){
				$value['terminal_code'] = ['name'=> get_tag_terminal_code($store_id,$v_id,$udidtoken),
				'status'=>$invoiceformat->is_active,
			];       
			
		}
		elseif($invoiceformat->doc_sec_id=='4'&& $invoiceformat->value=="DAILY"){
			$date_format_type = $invoiceformat->value;
			$date_formats =$invoiceformat->date_format;
			//dd($date_formats);
			$value['date_format']= ['name'=>dailyDateformate($date_formats),
			'status'=>$invoiceformat->is_active];
		}elseif($invoiceformat->doc_sec_id=='4'&& $invoiceformat->value=="MONTHLY"){
			$date_format_type = $invoiceformat->value;
			$date_formats =$invoiceformat->date_format;
			$value['date_format']= ['name'=>monthlyDateformate($date_formats),
			'status'=>$invoiceformat->is_active];
		}elseif($invoiceformat->doc_sec_id=='4'&& $invoiceformat->value=="YEARLY"){
			$date_format_type = $invoiceformat->value;
			$date_formats =$invoiceformat->date_format;
			$value['date_format']= ['name'=>yearlyDateformate($date_formats),
			'status'=>$invoiceformat->is_active];
		}elseif($invoiceformat->doc_sec_id=='5'){
			$value['order_id']= ['name'=>oreder_id_increment($store_id,$invoiceformat->value,$trans_from,$terminal_id,$date_format_type,$date_formats),
			'status'=>$invoiceformat->is_active];
		}

	}           
	return $value;
}
}

function yearlyDateformate($data){
	 $date_formats    =$data;
	$data = array(
                   'year'=>[
	                       'name'=>yearlyInvoiceFormat($date_formats),
	                       'ordersequence'=>'1',
	                       'reset'=>'1'
                           ],
                   );
	return $data;
} 

function monthlyDateformate($data){
	$format =explode('/', $data);
   //dd($format);
	switch ($format) {

		case ($format[0]=='y' && $format[1]=='n'):
		return   $data = array(
								 'month'=>['name'=>monthformate(date('n')),
								           'month_list'=>monthList(),
								           'ordersequence'=>'2',
								           'reset' =>'1',
							              ],
								 'year'=> [
									     'name'=>date('y'),
									     'ordersequence'=>'1',
									     'reset'=>'0'
								          ],
                               );
		break;
		case ($format[0]=='n' && $format[1]=='y'):
		return  $data = array(
								'month'=>['name'=>monthformate(date('n')),
								           'month_list'=>monthList(),
										   'ordersequence'=>'1',
										   'reset' =>'1',
							             ],
								'year'=>[
									     'name'=>date('y'),
									     'ordersequence'=>'2',
									     'reset'=>'0'
								        ],
                               );      
	}
	
}

function monthformate($data){
	switch ($data) {
		case ($data=='10'):
		return  'A';
		break;
		case ($data=='11'):
		return  'B';
		case ($data=='12'):
		return  'C';
		default:
		return $data;      
	}

}

function  dailyDateformate($data)
{
	$format =explode('/', $data);
   //dd($format);
	switch ($format) {
		case ($format[0]=='d' && $format[1]=='y' && $format[2]=='n'):
		return $data = array('date'=>['name'=>date('d'),
			                          'ordersequence'=>'1',
			                          'reset' =>'1',
		                              ],
							'month'=>['name'=>monthformate(date('n')),
							          'month_list'=>monthList(),
									  'ordersequence'=>'3',
									  'reset' =>'0',
						             ],
							'year'=>[
									'name'=>date('y'),
									'ordersequence'=>'2',
									'reset'=>'0'
							        ],
                           );
		break;
		case ($format[0]=='d' && $format[1]=='n' && $format[2]=='y'):
		return $data = array('date'=>['name'=>date('d'),
									   'ordersequence'=>'1',
									   'reset' =>'1',
		                               ],
							'month'=>['name'=>monthformate(date('n')),
							           'month_list'=>monthList(),
									   'ordersequence'=>'2',
									   'reset' =>'0',
	                                  ],
							 'year'=>[
										'name'=>date('y'),
										'ordersequence'=>'3',
										'reset'=>'0'
							          ],
                           );
		break; 

		case ($format[0]=='n' && $format[1]=='d' && $format[2]=='y'):
		return $data = array( 'date'=>['name'=>date('d'),
									   'ordersequence'=>'2',
									   'reset' =>'1',
		                               ],
							   'month'=>['name'=>monthformate(date('n')),
							              'month_list'=>monthList(),
										  'ordersequence'=>'1',
										  'reset' =>'0',
	                                    ],
								'year'=>[
											'name'=>date('y'),
											'ordersequence'=>'3',
											'reset'=>'0'
								         ],
                             );
		break;
		case ($format[0]=='n' && $format[1]=='y' && $format[2]=='d'):
		return $data = array(  'date'=>['name'=>date('d'),
										'ordersequence'=>'3',
										'reset' =>'1',
									    ],
								'month'=>['name'=>monthformate(date('n')),
								           'month_list'=>monthList(),
										   'ordersequence'=>'1',
										   'reset' =>'0',
	                                     ],
								'year'=>[
										'name'=>date('y'),
										'ordersequence'=>'2',
										'reset'=>'0'
									    ],
                           );
		break;

		case ($format[0]=='y' && $format[1]=='n' && $format[2]=='d'):
		return $data = array( 'date'=>['name'=>date('d'),
									    'ordersequence'=>'3',
									    'reset' =>'1',
		                               ],
								'month'=>['name'=>monthformate(date('n')),
								           'month_list'=>monthList(),
										   'ordersequence'=>'2',
										   'reset' =>'0',
							              ],
								'year'=>[
										  'name'=>date('y'),
										  'ordersequence'=>'1',
										  'reset'=>'0'
								         ],
                           );
		break;
		case ($format[0]=='y' && $format[1]=='d' && $format[2]=='n'):
		return $data = array( 'date'=>['name'=>date('d'),
										'ordersequence'=>'2',
										'reset' =>'1',
		                               ],
								'month'=>['name'=>monthformate(date('n')),
								           'month_list'=>monthList(),
										   'ordersequence'=>'3',
										   'reset' =>'0',
							             ],
								'year'=>[
										  'name'=>date('y'),
										  'ordersequence'=>'1',
										  'reset'=>'0'
								        ],
                                );
	 break;                                                  
	 }

} 


  function offline_oreder_id($store_id,$inc_id,$trans_from,$terminal_id){
   $inc_id ;
   $invoice =Invoice::where('store_id', $store_id)
            ->where('terminal_id',$terminal_id)
			->select('invoice_id')
			->orderBy('id','desc')
			->first();
   if(!empty($invoice)){
    $n  = strlen($inc_id);
    $inc_id = substr($invoice->invoice_id,-$n);
   }			
   return $inc_id;
  }

  function get_terminal_id($store_id,$v_id,$udidtoken){
			$tag = CashRegister::select('id')->where('store_id', $store_id)->where('v_id', $v_id)->where('udidtoken',$udidtoken)->first();
			if($tag){
			 return $tag->id;	
			}else{
             return response()->json(['status' => 'fail', 'message' => 'This device is already registered with another store license key.'], 200);
			}
			
		} 
 function monthList(){

 	return  array(['name'=>'1',
 		           'value'=>'1'
 		           ],
 		           ['name'=>'2',
 		           'value'=>'2'
 		           ],
 		           ['name'=>'3',
 		           'value'=>'3'
 		           ],
 		           ['name'=>'4',
 		           'value'=>'4'
 		           ],
 		           ['name'=>'5',
 		           'value'=>'5'
 		           ],
 		           ['name'=>'6',
 		           'value'=>'6'
 		           ],
 		           ['name'=>'7',
 		           'value'=>'7'
 		           ],
 		           ['name'=>'8',
 		           'value'=>'8'
 		           ],
 		           ['name'=>'9',
 		           'value'=>'9'
 		           ],
 		           ['name'=>'10',
 		            'value'=>'A'
 		           ],
 		            ['name'=>'11',
 		             'value'=>'B'
 		            ],
                    ['name'=>'12',
 		             'value'=>'C'
 		            ],
                 );
               
 }

  function yearlyInvoiceFormat($date_formats){
         
          $date_format = $date_formats;
          $date='';
          if($date_format=='1'){
            $date  = date('y');
         }else if($date_format=='2'){
            $current= date('n')>3?date('y'):(date('y')-1);
            $next=  date('n')>3?(date('y')+1):date('y');
            $date =  $current.$next;
         }

     return $date;
  }


  function generateDocNo($store_id) 
  {
              $store =Store::select('short_code')      
                            ->where('store_id',$store_id)
                            ->first();
               $c_date =date('dmy');
     $number =  'CT'.$store->short_code.$c_date.docIncrementNo($store_id);
     return $number;
  }

 function docIncrementNo($store_id){
   $inc_no = '0001';
   $currentdate = date('Y-m-d');
   $lastTranscation=CashTransaction::where('store_id',$store_id)
                   ->orderBy('id','DESC')
                   ->first();     
  if(!empty($lastTranscation) && $lastTranscation->date==$currentdate)
  {
    $n  = strlen($inc_no);
          $current_id = substr($lastTranscation->doc_no,-$n);
          $inc=++$current_id;
        $inc_no =str_pad($inc,$n,"0",STR_PAD_LEFT);
  }else{
   $inc_no = '0001';
  }
  return $inc_no;
}

function getFinancialYear(){

$current= date('n')>3?date('y'):(date('y')-1);
$next=  date('n')>3?(date('y')+1):date('y');
return $date =  $current.$next;

}

function getExchangeRate($v_id,$source_currency,$target_currency,$amount){
	$current_date      =  date('Y-m-d');

	if($source_currency==$target_currency){

     return  ['status'=>'success','amount'=>round($amount,2)];

	}else{
	 
	$exchangeRateDetail=ExchnageRateHeader::join('exchange_rate_details','exchange_rate_details.cc_hd_id','exchnage_rate_headers.id')
	                     ->select('exchange_rate_details.source_currency','exchange_rate_details.target_currency','exchange_rate_details.exchange_rate')
	                      ->where('exchnage_rate_headers.status','1')
	                      ->whereDate('effective_date', '<=' ,$current_date)
	                      ->whereDate('valid_upto', '>=' ,$current_date)
	                      ->where('v_id',$v_id)
	                      ->orderBY('exchnage_rate_headers.id','desc')
	                      ->first();
	             
	if($exchangeRateDetail){

		    if($exchangeRateDetail->target_currency==$target_currency && $exchangeRateDetail->source_currency==$source_currency){

		     	$currentRate     = (float)$amount*(float)$exchangeRateDetail->exchange_rate;
		     	 return  ['status'=>'success','amount'=>round($currentRate,2)];

		    }elseif($exchangeRateDetail->target_currency=!$target_currency && $exchangeRateDetail->source_currency==$source_currency){
		 
		     	$currentRate     = (float)$amount/(float)$exchangeRateDetail->exchange_rate;
		     	 return  ['status'=>'success','amount'=>round($currentRate,2)];
		    }	
	    

	}else{
	 	     $error_msg = 'Exchange rate is not set or expired';
	  return [ 'status' => 'error' , 'message' => $error_msg ]; 
	} 
	}                    
}

function getExchangeRateScToTc($v_id,$amount){
	//dd($v_id);
	$current_date      =  date('Y-m-d');
	$exchangeRateDetail=ExchnageRateHeader::join('exchange_rate_details','exchange_rate_details.cc_hd_id','exchnage_rate_headers.id')
	                     ->select('exchange_rate_details.source_currency','exchange_rate_details.target_currency','exchange_rate_details.exchange_rate')
	                      ->where('exchnage_rate_headers.status','1')
	                      ->whereDate('effective_date', '<=' ,$current_date)
	                      ->whereDate('valid_upto', '>=' ,$current_date)
	                      ->where('v_id',$v_id)
	                      ->orderBY('exchnage_rate_headers.id','desc')
	                      ->first();
 //dd($exchangeRateDetail);                     
	if($exchangeRateDetail){

	       $clientDetails   = ClientVendorMapping::where('v_id',$v_id)->first();


	    if($clientDetails && $clientDetails->source_currency!=null){

		     if($exchangeRateDetail->target_currency!=$clientDetails->source_currency){

		     	$currentRate     =(float)$amount/(float)$exchangeRateDetail->exchange_rate;
		     	 return  ['status'=>'success','amount'=>round($currentRate,2)];

		     }else{

		     	return ['status'=>'success','amount'=>$amount];
		     }	

	    }else{
	      	 $error_msg = 'Source Currency is not set';
	        return [ 'status' =>'error' , 'message' => $error_msg ];	
	    }
	}else{
		$error_msg = 'Exchange rate is not set or expaired';
		return [ 'status' =>'error','message' => $error_msg ];
	 
	 }                     
}

function getStoreAndClientCurrency($v_id,$store_id){

  $clientDetails   = ClientVendorMapping::where('v_id',$v_id)->first();
  $clientCurrency  = null;
  $storeCurrency   = null;

  if($clientDetails && $clientDetails->source_currency!=null){
     $clientCurrency  =  $clientDetails->source_currency;
  }else{
               $error_msg = 'Client Currency is not set';
	        return [ 'status' =>'error' , 'message' => $error_msg ];

  }

   $stores=Store::where(['v_id'=>$v_id,'store_id'=>$store_id])->first();

   if($stores){

      $country = $stores->country;
     
   }else{ 

       $vendors =  VendorDetails::where('v_id',$v_id)->first();

       if($vendors && $vendors->country!=null || $vendors && $vendors->country!=''){
          $country  =  $vendors->country;
       }else{
          $country  =  101;
       }
   }

    $country   = Country::where('id',$country)->first();
        if($country){

        	 $storeCurrency = $country->currency_code;
        }else{  
             $error_msg = 'Store Country does not found ';
	        return [ 'status' =>'error' , 'message' => $error_msg ];
        }
    
   return [ 'status'=>'success',
	        'client_currency'=>$clientCurrency,
	        'store_currency'=>$storeCurrency
          ];
       
} 

function removeSpecialChar($collect){
	if(!empty($collect)){
		foreach ($collect as $key => $value) {
		 $collect->$key =preg_replace('/[^ a-zA-Z0-9-_\.]/', '', $value);
		}
		return $collect;
	}else{
		return $collect;
	}
}   		         

function formatDate($date)
{	
	$dater = '';
	if($date){
		$dater = date("d-m-y h:i:s A",strtotime($date));
	}
	return $dater;
}

//genrate supplier code
function supplierCodeGenerator($v_id){
    $inc = 'SUP'.$v_id.'0001';
    $check = DB::table('supplier')->where('v_id',$v_id)->whereNotNull('code')->orderBY('id','desc')->first();
    if($check){
            $inc = $check->code;
            
            $inc++;
    }
    return $inc;
}

//genrate gift voucher invoice id       
 function invoice_id_generate_for_gv($store_id, $user_id,$trans_from,$invoice_seq,$udidtoken ='',$seq_id='') {

 	$application = [
					'ANDROID' => '1', 'ANDROID_VENDOR' => '2', 'ANDROID_KIOSK' => '3',
					'IOS' => '4', 'IOS_VENDOR' => '5', 'IOS_KIOSK' => '6', 
					'CLOUD_TAB_WEB' => '7','CLOUD_TAB'=> '8' ,'CLOUD_TAB_ANDROID' =>'9' 
				];
				$order_id = 'G';
				$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	//$date = date();
				$current_date = $characters[date('y')] . $characters[date('m')] . $characters[date('d')];
				$stores = DB::table('stores')->where('store_id', $store_id)->select(['store_code','v_id'])->first();
				$last_order = DB::table('gv_invoices')->where('store_id', $store_id)->select('invoice_id')->orderBy('id', 'desc')->first();

				$inc_id = '00001';
				if($stores->v_id == 7){
					if ($last_order) {
					//Z2001001J4Q00001
						$inc_id = '000001';
						$current_year  = date('y');

						$next_year     = date('y',strtotime('+1 year'));
						$current_month = date('m');


						if($current_month > 4){

							$prefix = $current_year.'-'.$next_year;
						}elseif($current_month < 4){
							$prefix = $previous_year.'-'.$current_year;
						}


						$last_order_id = $last_order->invoice_id;
						$exists_date = substr($last_order_id, 1, 5);

						if ($exists_date == $prefix) {
							$inc_id = substr($last_order_id, 7, 6);
							$inc_id++;
							$inc_id = sprintf('%06d', $inc_id++);
						}
					}

					$exists = DB::table('gv_invoices')->where('store_id', $store_id)->where('customer_id', $user_id)->count();

					$order_id = $order_id.$prefix.'-'. $inc_id; 
				}else{
					if ($last_order) {
						$last_order_id = $last_order->invoice_id;
						$exists_date = substr($last_order_id, 8, 3);

						if ($exists_date == $current_date) {
							$inc_id = substr($last_order_id, 11, 5);
							$inc_id++;
							$inc_id = sprintf('%05d', $inc_id++);
						}
					}

					$exists = DB::table('gv_invoices')->where('store_id', $store_id)->where('customer_id', $user_id)->count();

					$order_id = $order_id . $application[$trans_from] . $stores->store_code . $current_date . $inc_id;
				}
				if(!empty($seq_id)){
					return $inc_id;
				}
				return $order_id;

 }
 function getSettingsForInventory($store_id,$vu_id,$v_id,$settings_for)
    {

        $store_id=empty($store_id)?'':$store_id;
        $role_id='';
        $role_id = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first();
        if($role_id){
        	$role_id=$role_id->role_id;
        }
        $setting_param = [ 'v_id' => $v_id, 'store_id' => $store_id, 'name' => 'stock', 'user_id' => $vu_id, 'role_id' =>$role_id ];
        $setting_data= getSetting($setting_param);

        $setting  = json_decode($setting_data[0]->settings);
        $settingsEnable= $setting->negative_stock_billing->status;
        $negativeAllowed='';
        if($settingsEnable==1){
            if($settings_for=='stock_point_transfer'){
                $negativeAllowed=$setting->negative_stock_billing->options[0]->stock_point_transfer->value;
            }elseif($settings_for=='store_transfer'){
                $negativeAllowed=$setting->negative_stock_billing->options[0]->store_transfer->value;
            }elseif($settings_for=='grt'){
                $negativeAllowed=$setting->negative_stock_billing->options[0]->grt->value;
            }elseif($settings_for=='inventory_adjustment'){
                $negativeAllowed=$setting->negative_stock_billing->options[0]->inventory_adjustment->value;
            }
            
        }
       
        return $negativeAllowed;
        
    }

    function getSetting($params)
	{
		//dd($params);
		$v_id = $params['v_id'];
		$store_id = $params['store_id'];
		$name = $params['name'];
		$user_id = $params['user_id'];
		$role_id = $params['role_id'];

		$settings = VendorSetting::select('id', 'name', 'settings','updated_at', 'store_id')->where('v_id', $v_id);
		if ($name != '') {
			$settings = $settings->where('name', $name);
		}

		$settings = $settings->orderBy('updated_at','desc')->get();
		if($user_id){ 
			$userIdExists = $settings->where('user_id', $user_id );

			if($userIdExists->count() > 0){
				$settings = $userIdExists;
			}else{ 
				$roleIdExists = $settings->where('role_id', $role_id); 
				if($roleIdExists->count() > 0){
					$settings = $roleIdExists;
				}else{ 
					$storeIdExists = $settings->where('store_id', $store_id); 
					if($storeIdExists->count() > 0){
						$settings = $storeIdExists;
					}
				}
			}
		}
			
		
		if ($settings->isEmpty()) {
			return null;
		} else {
			return $settings;
		}
	}

	//
	function getIsIntegartionAttribute($v_id)
    {
        $vendor = Organisation::select('client_id')->where('id',$v_id)->first();
        return empty($vendor->client_id) ? false : true;
    }
                       
    function convertState($name) { 
   $states = array(
      array('name'=>'Andaman and Nicobar Islands', 'sname'=>'AN'),
      array('name'=>'Andhra Pradesh', 'sname'=>'AP'),
      array('name'=>'Arunachal Pradesh', 'sname'=>'AR'),
      array('name'=>'Assam', 'sname'=>'ASM'),
      array('name'=>'Bihar', 'sname'=>'BH'),
      array('name'=>'Chandigarh', 'sname'=>'CH'),
      array('name'=>'Chattisgarh', 'sname'=>'CT'),
      array('name'=>'Daman and Diu', 'sname'=>'DD'),
      array('name'=>'Delhi', 'sname'=>'DL'),
      array('name'=>'Dadra and Nagar Haveli', 'sname'=>'DN'),
      array('name'=>'Gujarat', 'sname'=>'GJ'),
      array('name'=>'GOA', 'sname'=>'GOA'),
      array('name'=>'Himachal Pradesh', 'sname'=>'HP'),
      array('name'=>'Haryana', 'sname'=>'HR'),
      array('name'=>'Jharkhand', 'sname'=>'JH'),
      array('name'=>'Jammu and Kashmir', 'sname'=>'JK'),
      array('name'=>'Karnataka', 'sname'=>'KA'),
      array('name'=>'Kerala', 'sname'=>'KER'),
      array('name'=>'Lakshadweep', 'sname'=>'LD'),
      array('name'=>'Meghalaya', 'sname'=>'ME'),
      array('name'=>'Maharashtra', 'sname'=>'MH'),
      array('name'=>'Mizoram', 'sname'=>'MI'),
      array('name'=>'Manipur', 'sname'=>'MN'),
      array('name'=>'Madhya Pradesh', 'sname'=>'MP'),
      array('name'=>'Nagaland', 'sname'=>'ML'),
      array('name'=>'Odisha', 'sname'=>'OR'),
      array('name'=>'Punjab', 'sname'=>'PB'),
      array('name'=>'Puducherry', 'sname'=>'PDN'),
      array('name'=>'Rajasthan', 'sname'=>'RJ'),
      array('name'=>'Sikkim', 'sname'=>'SK'),
      array('name'=>'Telangana', 'sname'=>'TLG'),
      array('name'=>'Tamil Nadu', 'sname'=>'TN'),
      array('name'=>'Tripura', 'sname'=>'TR'),
      array('name'=>'Uttar Pradesh', 'sname'=>'UP'),
      array('name'=>'Uttarakhand', 'sname'=>'UTR'),
      array('name'=>'West Bengal', 'sname'=>'WB')
   );

   $return = false;   
   $strlen = strlen($name);

   foreach ($states as $state) :
      if ($strlen < 2) {
         return false;
      } else if ($strlen == 2) {
         if (strtolower($state['sname']) == strtolower($name)) {
            $return = $state['name'];
            break;
         }   
      } else {
         if (strtolower($state['name']) == strtolower($name)) {
            $return = strtoupper($state['sname']);
            break;
         }         
      }
   endforeach;
   
   return $return;
}
?>