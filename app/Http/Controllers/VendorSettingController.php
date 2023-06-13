<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Payment\VendorMop;
use App\Model\Payment\Mop;
use App\VendorSetting;
use App\Template;
use Auth;
use DB;

class VendorSettingController extends Controller
{
	public function __construct()
	{
		$this->middleware('auth');
	}

	public function save(Request $request)
	{

		$v_id = $request->v_id;
		$name = $request->name; // STORE_WISE | VENDOR_WISE
		$settings = $request->settings;

		$settings = VendorSetting::where('v_id', $v_id)->where('name', $name)->first();

		if ($settings) {

			$settings->type = $type;
			$settings->user_id = $c_id;
			$settings->save();
		} else {

			$settings = new VendorSetting;

			$settings->v_id = $v_id;
			$settings->name = $name;
			$settings->settings = $settings;
			$settings->status = '1';

			$settings->save();
		}
		return response()->json(['status' => 'save', 'message' => 'Vendor setting  Save Successfully'], 200);
	}

	public function get_setting(Request $request)
	{

		$v_id = $request->v_id;

		$settings = VendorSetting::select('name', 'settings')->where('v_id', $v_id);

		if ($request->has('name')) {
			$settings = $settings->where('name', $request->name);
		}

		$settings = $settings->get();


		if ($settings->isEmpty()) {
			return response()->json(['status' => 'fail', 'message' => 'No record found']);
		} else {
			return response()->json(['status' => 'sucess', 'data' => $settings]);
		}
	}

	public function getSetting($params)
	{
		// dd($params);
		//JobdynamicConnection($params['v_id']);
		$templateSettings = "";
		$v_id = $params['v_id'];
		$store_id = $params['store_id'];
		$name = $params['name'];
		$user_id = $params['user_id'];
		$role_id = $params['role_id'];

		$settings = VendorSetting::select('id', 'name', 'settings','updated_at', 'store_id','role_id','user_id')->where('v_id', $v_id);
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
		foreach ($settings as $value) 
		{
			$settingData = json_decode($value,true);
			$settingName = $settingData['name'];

			$setting_id = $settingData['id'];
			
			$template = DB::table('template_details')->select('template_id')->where('vendor_setting_id',$setting_id)->first();
			if(!empty(isset($template)))
			{
				$template_id = $template->template_id;
			}else{
				$template_id = "";
			}
			if(!empty($settingData['user_id']) || $settingData['user_id'] != null){
				$setting_level = "User Lavel";
			}elseif (!empty($settingData['store_id']) || $settingData['store_id'] != null){
				$setting_level = "Store Lavel";
			}elseif (!empty($settingData['role_id']) || $settingData['role_id'] != null){
				$setting_level = "Role Lavel";
			}else{
				$setting_level = "Organization Lavel";
			}
			$templateSettings = '{"template_id":"'.$template_id.'","setting_level":"'.$setting_level.'","setting_id":"'.$setting_id.'"}';
			// $settings = $settingData;
			// $settings['template'] = $templateSettings;
		}
		$settings->template=$templateSettings;
		// checking priority wise setting
		// $userIdExists = VendorSetting::whereuser_id($user_id)->where('name',$name)->exists();
		// if(isset($userIdExists) && $userIdExists == true){
		// 	$settings = $settings->where('user_id',$user_id)->orderBy('updated_at','desc');
		// }else{
		// 	$roleIdExists = VendorSetting::whererole_id($role_id)->where('name',$name)->exists();
		// 	if(isset($roleIdExists) && $roleIdExists == true){
		// 		$settings = $settings->where('role_id',$role_id)->orderBy('updated_at','desc');
		// 	}else{
		// 		$storeIdExists = VendorSetting::wherestore_id($store_id)->where('name',$name)->exists();
		// 		if(isset($storeIdExists) && $storeIdExists == true){
		// 			$settings = $settings->where('store_id',$store_id)->orderBy('updated_at','desc');
		// 		}
		// 	}
		// }

		// if user id exists
		// if (isset($user_id)) {
		// 	$userIdExists = VendorSetting::whereuser_id($user_id)->exists();
		// 	if (isset($userIdExists) && $userIdExists == true) {
		// 		$settings = $settings->where('user_id', $user_id)->orderBy('created_at');
		// 	}
		// }

		// $settings = $settings->get();
		if ($settings->isEmpty()) {
			return null;
		} else {
			return $settings;
		}
	}

	public function getPaymentSetting($params)
	{
		$params['name'] = 'payment';
		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];

		$paymentSettings = json_decode('{"charges":{"status":1,"for":{"card":{"type":"PERCENTAGE","value":1},"netbanking":{"type":"PERCENTAGE","value":1},"wallet ":{"type":"PERCENTAGE","value":1},"emi ":{"type":"PERCENTAGE","value":1}}},"settlement_day":{"default_day":7,"added_day":1},"type":{"ANDROID":[{"name":"razor_pay_online","status": 1},{"name":"ezetap","status": 1},{"name":"cash","status": 1}],"ANDROID_VENDOR":[{"name":"razor_pay_online","status": 1},{"name":"ezetap","status": 1},{"name":"cash","status": 1}],"IOS":[{"name":"razor_pay_online","status": 1},{"name":"ezetap","status": 0},{"name":"cash","status": 1}],"DEFAULT":[{"name":"razor_pay_online","status": 1},{"name":"ezetap","status": 0},{"name":"cash","status": 0}]}}');

		$settings = $this->getSetting($params);
		if ($settings) {
			$template = $settings->template;
			$settings = $settings->first()->settings;
			$paymentSettings = json_decode($settings);
			$paymentSettings->template = $template;
		}

		return $paymentSettings;
	}

	public function getPaymentTypeSetting($params)
	{
		/*Custom payment types*/
		$paymentTypeSetting = Mop::join('vendor_mop_mapping','vendor_mop_mapping.mop_id','mops.id')->where('vendor_mop_mapping.v_id',$params['v_id'])->where('mops.status','1')->where('vendor_mop_mapping.is_deleted','0')->where('vendor_mop_mapping.status','1')->where('vendor_mop_mapping.type','ANDROID');
	
		/*on webpos or mpos showing different keys name that's y mops.name has different alias*/
		$paymentTypeSetting = $paymentTypeSetting->select(DB::raw('mops.code as name,mops.code as mop_name,mops.name as display_text,mops.name as display_name,mops.status as status,IF(mops.is_integrated = "1","ONLINE","OFFLINE") as type'))
		->groupBy('mops.code','mops.name')
		->orderBy('mops.id')
		->get();

		if($params['trans_from'] == 'ANDROID_VENDOR'){
			$paymentStoreSetting =  Mop::join('vendor_mop_mapping','vendor_mop_mapping.mop_id','mops.id')->where('vendor_mop_mapping.v_id',$params['v_id'])->where('mops.status','1')->where('vendor_mop_mapping.is_deleted','0')->where('vendor_mop_mapping.status','1')->Where('store_id',$params['store_id'])->select(DB::raw('mops.code as name,mops.name as display_text,mops.code as mop_name,mops.name as display_name,mops.status as status,IF(mops.is_integrated = "1","ONLINE","OFFLINE") as type'))
		->groupBy('mops.code','mops.name')
		->orderBy('mops.id')
		->get();
		}else{
		$paymentStoreSetting =  Mop::join('vendor_mop_mapping','vendor_mop_mapping.mop_id','mops.id')->where('vendor_mop_mapping.v_id',$params['v_id'])->where('mops.status','1')->where('vendor_mop_mapping.is_deleted','0')->where('vendor_mop_mapping.status','1')->Where('store_id',$params['store_id'])->select(DB::raw('mops.code as name,mops.name as display_text,mops.name as mop_name,mops.name as display_name,mops.status as status,IF(mops.is_integrated = "1","ONLINE","OFFLINE") as type'))
		->groupBy('mops.code','mops.name')
		->orderBy('mops.id')
		->get();
		}

		
		foreach ($paymentStoreSetting as $key => $storeMop) {
			$paymentTypeSetting = $paymentTypeSetting->push($storeMop);
		}

		return $paymentTypeSetting;

	}


	public function getPaymentMultipleMopSetting($params)
	{
		$trans_from = $params['trans_from'];
		$paymentSettings = $this->getPaymentSetting($params);

		$multipleMopSetting = ['status' => '0'];
		if (isset($paymentSettings->multiple_mop)) {
			$multipleMopSettings = $paymentSettings->multiple_mop;

			if (isset($multipleMopSettings->$trans_from)) {
				$multipleMopSetting =  $multipleMopSettings->$trans_from;
			} else {
				$multipleMopSetting =  $multipleMopSettings->DEFAULT;
			}
		}

		return $multipleMopSetting;
	}

	public function getStoreSetting($params)
	{
		$params['name'] = 'store';
		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];

		$storeSettings = json_decode('{"enable_shopping_radius":{"status":0,"default_radius":"1.5","apply_type":"store_wise","store_wise":{"17":{"radius":10},"18":{"radius":10}},"editable":[{"status":"boolean"}]},"cart_max_item":{"ANDROID":50,"ANDROID_KIOSK":10,"DEFAULT":50,"editable":[{"ANDROID":"integer"},{"ANDROID_KIOSK":"integer"},{"DEFAULT":"integer"}]},"credit":{"DEFAULT":{"display_status":0},"CLOUD_TAB_WEB":{"display_status":0}}}');

		$settings = $this->getSetting($params);
		if ($settings) {
			$template = $settings->template;
			$settings = $settings->first()->settings;
			$storeSettings = json_decode($settings);
			$storeSettings->template = $template;
		}
    
		return $storeSettings;
	}

	public function getProductSetting($params)
	{
		$params['name'] = 'product';
		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];

		$productSettings = json_decode('{"review_rating":{"used":"TOGETHER","review":{"status":"ON","display_type":"STORE_WISE"},"rating":{"status":"ON","display_type":"STORE_WISE"}},"max_qty":{"ANDROID" : 10, "ANDROID_KIOSK": 5,"DEFAULT": 50},"max_item_in_cart":{"ANDROID" : 10, "ANDROID_KIOSK": 5,"DEFAULT": 50},"default_image":{"ANDROID" : "zwing_default.png","IOS" : "zwing_default.png", "ANDROID_KIOSK": "zwing_default.png","DEFAULT": "zwing_default.png"}}');

		$settings = $this->getSetting($params);
		if ($settings) {
			$template = $settings->template;
			$settings = $settings->first()->settings;
			$productSettings = json_decode($settings);
			$productSettings->template = $template;




		}
		return $productSettings;
	}

	public function getMaxItemInCart($params)
	{
		$trans_from = $params['trans_from'];

		$productSettings = $this->getProductSetting($params);
		$max_item_in_cart = $productSettings->max_item_in_cart;

		if (isset($max_item_in_cart->$trans_from)) {
			$maxCartItem =  $max_item_in_cart->$trans_from;
		} else {
			// $maxCartItem =  $max_item_in_cart;
			if(isset($max_item_in_cart->options[0]->no_of_items->value)){
				$maxCartItem =  $max_item_in_cart->options[0]->no_of_items->value;	
			}else{
				$maxCartItem =0;
			}
		}
		
		return $maxCartItem;
	}

	public function getProductMaxQty($params)
	{
		$trans_from = $params['trans_from'];

		$productSettings = $this->getProductSetting($params);
		$max_qty = $productSettings->max_qty;

		if (isset($max_qty->$trans_from)) {
			$maxQty =  $max_qty->$trans_from;
		} else {
			$maxQty =  $max_qty->DEFAULT;
		}

		return $maxQty;
	}

	public function getProductDefaultImage($params)
	{
		$trans_from = $params['trans_from'];

		$productSettings = $this->getProductSetting($params);
		$default_image = $productSettings->default_image;

		if (isset($default_image->$trans_from)) {
			$defaultImage =  $default_image->$trans_from;
		} else {
			$defaultImage =  $default_image->DEFAULT;
		}

		return $defaultImage;
	}


	public function getVendorAppSetting($params)
	{
		$params['name'] = 'vendor_app';
         //dd($params);
		//$trans_from = $params['trans_from'];

		$vendorAppSettings = json_decode('{"main_menu":[{"name":"shop_by_catalog","status":0,"order_seq":1},{"name":"verify_order","status":1,"order_seq":2},{"name":"scan_for_customer","status":1,"order_seq":3}],"bottom_menu":[{"name":"orders","status":1,"order_seq":1},{"name":"statistics","status":1,"order_seq":2},{"name":"inventory","status":1,"order_seq":3},{"name":"profile","status":1,"order_seq":4}],"profile_tab_menu":[{"name":"mpos","status":1,"display_text":"mPos","data":[{"name":"orders","status":1,"display_text":"Your Oders"},{"name":"exchange_return","status":1,"display_text":"Exchange / Return"},{"name":"previous_scan","status":1,"display_text":"Previous Scan"}]},{"name":"setting","status":1,"display_text":"Setting","data":[{"name":"notification","status":1,"display_text":"Notification"},{"name":"edit_information","status":1,"display_text":"Edit Information"},{"name":"change_pin","status":1,"display_text":"change_pin"}]},{"name":"support","status":1,"display_text":"Support","data":[{"name":"support","status":1,"display_text":"Support"},{"name":"contact_us","status":0,"display_text":"Contact Us"},{"name":"faq","status":1,"display_text":"FAQ"},{"name":"term_condition","status":1,"display_text":"Terms & Conditions"}]}]}');

		$settings = $this->getSetting($params);
		$storeSetting = $this->getStoreSetting($params);
		// dd($storeSetting);
		if ($settings) {
			$template = $settings->template;
			$settings = $settings->first()->settings;

			$vendorAppSettings = json_decode($settings);
			$vendorAppSettings->template = $template;
			if(isset($storeSetting->cashmanagement)){
				$cash[]= (object)['name'=>'cashmanagement','status'=>$storeSetting->cashmanagement->DEFAULT->status,'display_text'=>$storeSetting->cashmanagement->DEFAULT->display_text,'display_name'=>$storeSetting->cashmanagement->DEFAULT->display_name,'store_cash_limit'=>$storeSetting->cashmanagement->DEFAULT->options[0]->store_cash->type,'petty_cash_limit'=>$storeSetting->cashmanagement->DEFAULT->options[0]->petty_cash->type,'store_cash_negative_billing'=>$storeSetting->cashmanagement->store_cash_negative_billing->status];
			    $cash1=$vendorAppSettings->profile_tab_menu[0]->data;
			    $cash2=array_merge($cash1,$cash);
			    $vendorAppSettings->profile_tab_menu[0]->data=$cash2;
          }
			if(isset($storeSetting->offline) && $storeSetting->offline->status == 1){
				
				$storeSetting->offline->offline_invoice_id_format = $this->invoiceFormatSetting($params); 

            // For offline settings
				if($params['trans_from'] == 'CLOUD_TAB_WEB' || $params['trans_from'] == 'ANDROID_VENDOR'){
					$cartCon = new CartController;
					$request = new \Illuminate\Http\Request();
					
					$manufacturer_name = '';

					if($params['trans_from'] == 'CLOUD_TAB_WEB'){
						$manufacturer_name = 'Posiflex PP8800 Printer';
					} else if($params['trans_from'] == 'ANDROID_VENDOR'){
						$manufacturer_name = 'basewin|W9110';
					}

					$request->merge([
						'v_id' => $params['v_id'],
						'store_id' => $params['store_id'],
						'vu_id' => $params['user_id'],
						'return_type' => 'JSON',
						'manufacturer_name' => $manufacturer_name
					]);
					//dd($cartCon->get_print_offline($request));
					$storeSetting->offline->invoice_format = $cartCon->get_print_receipt($request);
					$vendorAppSettings->offline = $storeSetting->offline;
				}
		// For offline settings

			}else{
				if(isset($storeSetting->offline)){
				 $storeSetting->offline->offline_invoice_id_format=(object)[];
				 $vendorAppSettings->offline = $storeSetting->offline;
			    }
			}

			//dd($vendorAppSettings);
		}

		$order_seq  = array_column($vendorAppSettings->main_menu, 'order_seq');

		$order_seq_bottom  = array_column($vendorAppSettings->bottom_menu, 'order_seq');
		//dd($order_seq);
		array_multisort($order_seq, SORT_ASC, $vendorAppSettings->main_menu);
		array_multisort($order_seq_bottom, SORT_ASC, $vendorAppSettings->bottom_menu);

		if (isset($vendorAppSettings->inventory_menu)) {
			$seq_inv_menu = array_column($vendorAppSettings->inventory_menu, 'order_seq');
			array_multisort($seq_inv_menu, SORT_ASC, $vendorAppSettings->inventory_menu);
		}
		return $vendorAppSettings;
	}

	public function getVendorUserLogin($params)
	{
		$trans_from = $params['trans_from'];
		$vendorAppSettings = $this->getVendorAppSetting($params);
		//dd($vendorAppSettings);
		$vendorUserLogin = $vendorAppSettings->user_login;

		if (isset($vendorUserLogin->$trans_from)) {
			$userLogin = $vendorUserLogin->$trans_from;
		} else {
			$userLogin = $vendorUserLogin->DEFAULT;
		}
		return $userLogin;
	}

	public function getVendorCustomerLogin($params)
	{
		$trans_from = $params['trans_from'];
		$vendorAppSettings = $this->getVendorAppSetting($params);

		return $vendorAppSettings->customer_login;
	}

	public function getVendorCustomerLogins($params)
	{
		$trans_from = $params['trans_from'];

		//dd($trans_from);
		$vendorAppSettings = $this->getVendorAppSetting($params);

		$vendorUserLogin = $vendorAppSettings->customer_logins;

		if (isset($vendorUserLogin->$trans_from)) {
			$userLogin = $vendorUserLogin->$trans_from;
		} else {
			$userLogin = $vendorUserLogin->DEFAULT;
		}

		return $userLogin;
	}

	public function getColorSetting($params)
	{
		$params['name'] = 'color';
		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');

		$settings = $this->getSetting($params);
		if ($settings) {
			$template = $settings->template;
			$settings = $settings->first()->settings;
			$colorSettings = json_decode($settings);
			$colorSettings->$template = $template;
		}

		return $colorSettings;
	}


	public function getToolbarSetting($params)
	{
		$params['name'] = 'toolbar';
		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$toolbarSettings = json_decode('{"bg_color":{"color_top":{"r":218,"g":97,"b":38, "hex": "#F43746"},"color_bottom":{"r":240,"g":116,"b":55,"hex": "#B2212D"}},"txt_color":{"r":255,"g":255,"b":255,"hex": "#FFFFFF","black":0}}');

		$settings = $this->getSetting($params);
		if ($settings) {
			$template = $settings->template;
			$settings = $settings->first()->settings;
			$toolbarSettings = json_decode($settings);
			$toolbarSettings->template = $template;
		}

		return $toolbarSettings;
	}

	public function getFeatureSetting($params)
	{
		$params['name'] = 'feature';
		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$featureSettings = json_decode('{"feedback":{"ANDROID":{"status": 0},"ANDROID_VENDOR":{"status": 1},"DEFAULT":{"status": 0}},"invoice" : {"ANDROID":[{"name":"send_verification_invoice","status": 0}],"ANDROID_VENDOR":[{"name":"send_verification_invoice","status": 0}],"DEFAULT":[{"name":"send_verification_invoice","status": 0}]},"print":{"ANDROID_VENDOR" :[ {"name":"bill_repirnt" ,"status" : 1, "width" : 80}],"DEFAULT" :[ {"name":"bill_repirnt" ,"status" : 1, "width" : 80}] }}');

		$settings = $this->getSetting($params);
		if ($settings) {
			$template = $settings->template;
			$settings = $settings->first()->settings;
			$featureSettings = json_decode($settings);
			$featureSettings->template = $template;
		}

		return $featureSettings;
	}

	public function getFeedbackSetting($params)
	{

		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$feedbackSettings = $featureSettings->feedback;

		if (isset($feedbackSettings->$trans_from)) {
			$feedbackSetting =  $feedbackSettings->$trans_from;
		} else {
			$feedbackSetting =  $feedbackSettings->DEFAULT;
		}

		return $feedbackSetting;
	}

	public function getInvoiceCopiesSetting($params)
	{

		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$copiesLimitSettings = $featureSettings->invoice->copies_limit;

		if (isset($copiesLimitSettings->$trans_from)) {
			$copiesLimitSetting =  $copiesLimitSettings->$trans_from;
		} else {
			$copiesLimitSetting =  $copiesLimitSettings;
		}

		return $copiesLimitSetting;
	}

	public function getCreditNoteCopiesSetting($params)
	{

		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$CreditNoteCopiesSettings = $featureSettings->credit_note_copies;

		if (isset($CreditNoteCopiesSettings->$trans_from)) {
			$CreditNoteCopiesSetting =  $CreditNoteCopiesSettings->$trans_from;
		} else {
			$CreditNoteCopiesSetting =  $CreditNoteCopiesSettings->DEFAULT;
		}

		return $CreditNoteCopiesSetting;
	}

	public function getInvoiceSetting($params)
	{

		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$invoiceSettings = $featureSettings->invoice;

		if (isset($invoiceSettings->$trans_from)) {
			$invoiceSetting =  $invoiceSettings->$trans_from;
		} else {
			$invoiceSetting =  $invoiceSettings->DEFAULT;
		}

		return $invoiceSetting;
	}

	public function getPrintSetting($params)
	{

		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$printSettings = $featureSettings->print;

		if (isset($printSettings->$trans_from)) {
			$printSetting =  $printSettings->$trans_from;
		} else {
			$printSetting =  $printSettings->DEFAULT;
		}
		
		return $printSetting;
	}

	public function getBarcodeSetting($params)
	{

		$trans_from = $params['trans_from'];
		$featureSettings = $this->getFeatureSetting($params);

		$barcodeSetting = ['min_length' => 5, 'max_length' => 13, 'numeric' => 0, 'accept_char_list' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'];
		if (isset($featureSettings->barcode)) {
			$barcodeSettings = $featureSettings->barcode;

			if (isset($barcodeSettings->$trans_from)) {
				$barcodeSetting =  $barcodeSettings->$trans_from;
			} else {
				$barcodeSetting =  $barcodeSettings->DEFAULT;
			}
		}

		return $barcodeSetting;
	}

	public function getOptimizeFlowSetting($params)
	{
		$trans_from = $params['trans_from'];
		$optimize_flow = '';
		$featureSettings = $this->getFeatureSetting($params);
		$optimize_flow = @$featureSettings->optimize_flow;

		/*if(isset($barcodeSettings->$trans_from)){
            $barcodeSetting =  $barcodeSettings->$trans_from;
        }else{
        	$barcodeSetting =  $barcodeSettings->DEFAULT;
        }*/

        return $optimize_flow;
    }

    public function getReturnAuthorizationSetting($params)
    {

    	$trans_from = $params['trans_from'];
    	$featureSettings = $this->getFeatureSetting($params);
    	if (isset($featureSettings->return_authorization)) {

    		$invoiceSettings = $featureSettings->return_authorization;

    		if (isset($invoiceSettings->$trans_from)) {
    			$invoiceSetting =  $invoiceSettings->$trans_from;
    		} else {
    			$invoiceSetting =  $invoiceSettings->DEFAULT;
    		}
    	} else {

    		$invoiceSetting = ["status" => 0];
    	}

    	return $invoiceSetting;
    }

    public function getVoucherSetting($params)
    {

    	$trans_from = $params['trans_from'];

    	$featureSettings = $this->getFeatureSetting($params);
    	if (isset($featureSettings->voucher)) {
    		$voucherSettings = $featureSettings->voucher;
    	} else {
    		$voucherSettings = null;
    	}

		/*if(isset($invoiceSettings->$trans_from)){
            $invoiceSetting =  $invoiceSettings->$trans_from;
        }else{
        	$invoiceSetting =  $invoiceSettings->DEFAULT;
        }*/

        return $voucherSettings;
    }

	###########################################
	####### Cart Setting Start here ###########
	###########################################

    public function getCartSetting($params)
    {
    	$params['name'] = 'cart';
    	$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

    	$cartSettings = json_decode('{"avail_offers":{"ANDROID":{"status": 1},"ANDROID_VENDOR":{"status": 1},"DEFAULT":{"status": 1}}}');

    	$settings = $this->getSetting($params);
    	if ($settings) {
    		$template = $settings->template;
    		$settings = $settings->first()->settings;
    		$cartSettings = json_decode($settings);
    		$cartSettings->template = $template;
    	}

		// For offline settings
       if($params['trans_from'] == 'CLOUD_TAB_WEB' || $params['trans_from'] == 'ANDROID_VENDOR'){
    		$cartCon = new CartController;
    		$request = new \Illuminate\Http\Request();
    		$request->merge([
    			'v_id' => $params['v_id'],
    			'store_id' => $params['store_id'],
    			'vu_id' => $params['user_id']
    		]);
    		$carry_bags = $cartCon->get_carry_bags_offline($request);
    		$bags = [];
    		if(!empty($carry_bags)){
    			foreach ($carry_bags['data'] as $key => $value) {
    				$bags[] = $value['BAG_ID'];
    			}
    			$cartSettings->carry_bag->DEFAULT->data = $bags;
    		}
    	}
		// For offline settings

    	return $cartSettings;
    }

    public function getCommonCartSettingFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = $params['property'];

    	$cartSettings = $this->getCartSetting($params);
    	$propertySettings = $cartSettings->$property;
    	if (isset($propertySettings->$trans_from)) {
    		$propertySetting =  $propertySettings->$trans_from;
    	} else {
    		$propertySetting =  $propertySettings->DEFAULT;
    	}
    	return $propertySetting;
    }

    public function getCartAvailSetting($params)
    {
    	$params['property'] = 'avail_offers';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartRPriceSetting($params)
    {
    	$params['property'] = 'r_price_flag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartSPriceSetting($params)
    {

    	$params['property'] = 's_price_flag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartTotalSetting($params)
    {

    	$params['property'] = 'total_flag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartSavingSetting($params)
    {

    	$params['property'] = 'saving_flag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartSubTotalSetting($params)
    {

    	$params['property'] = 'sub_total_flag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartTaxTotalSetting($params)
    {

    	$params['property'] = 'tax_total_flag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartCarrayBagTotalSetting($params)
    {

    	$params['property'] = 'carry_bag_total_flag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartCarrayBagSetting($params)
    {

    	$params['property'] = 'carry_bag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartVoucherTotalSetting($params)
    {

    	$params['property'] = 'voucher_total_flag';
    	return  $this->getCommonCartSettingFunction($params);
    }

    public function getCartPriceOverrideSetting($params)
    {
    	$params['property'] = 'price_override';
    	return  $this->getCommonCartSettingFunction($params);
    }

	###########################################
	####### Cart Setting Ends here ############
	###########################################


	###########################################
	#### Scan Screen Setting Start here #######
	###########################################

    public function getScanScreenSetting($params)
    {
    	$params['name'] = 'scan_screen';
    	$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

    	$scanSettings = json_decode('{"cart_qty_total_flag":{"ANDROID":{"status":1},"ANDROID_VENDOR":{"status":1},"DEFAULT":{"status":1}},"total_flag":{"DEFAULT":{"status":1}},"offline_scan":{"ANDROID_VENDOR":{"status":0},"IOS_VENDOR":{"status":0},"DEFAULT":{"status":0}}}');

    	$settings = $this->getSetting($params);
    	if ($settings) {
    		$template = $settings->template;
    		$settings = $settings->first()->settings;
    		$scanSettings = json_decode($settings);
    		$scanSettings->template = $template;
    	}
    	return $scanSettings;
    }

    public function getCommonScanScreenSettingFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = $params['property'];

    	$scanSettings = $this->getScanScreenSetting($params);
    	$propertySettings = $scanSettings->$property;

    	if (isset($propertySettings->$trans_from)) {
    		$propertySetting =  $propertySettings->$trans_from;
    	} else {
    		$propertySetting =  $propertySettings->DEFAULT;
    	}

    	return $propertySetting;
    }

    public function getScanScreenTotalSetting($params)
    {

    	$params['property'] = 'total_flag';
    	return  $this->getCommonScanScreenSettingFunction($params);
    }

    public function getScanScreenCartQtyTotalSetting($params)
    {

    	$params['property'] = 'cart_qty_total_flag';
    	return  $this->getCommonScanScreenSettingFunction($params);
    }

    public function getScanScreenOffilneSetting($params)
    {
    	$params['property'] = 'offline_scan';
    	return  $this->getCommonScanScreenSettingFunction($params);
    }

	###########################################
	##### Scan Screen Setting Emds here #######
	###########################################


	###########################################
	####### Order Setting Start here ##########
	###########################################

    public function getOrderSetting($params)
    {
    	$params['name'] = 'order';
    	$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

    	$orderSettings = json_decode('{"history":{"total_flag":{"DEFAULT":{"status":1}}},"details":{"r_price_flag":{"DEFAULT":{"status":1}},"s_price_flag":{"DEFAULT":{"status":1}},"total_flag":{"DEFAULT":{"status":1}},"saving_flag":{"DEFAULT":{"status":1}},"sub_total_flag":{"DEFAULT":{"status":1}},"tax_total_flag":{"DEFAULT":{"status":1}},"carry_bag_total_flag":{"DEFAULT":{"status":1}},"voucher_total_flag":{"DEFAULT":{"status":1}}}}');

    	$settings = $this->getSetting($params);
    	if ($settings) {
    		$template = $settings->template;
    		$settings = $settings->first()->settings;
    		$orderSettings = json_decode($settings);
    		$orderSettings->template = $template;
    	}

    	return $orderSettings;
    }

    public function getCommonOrderSettingFunction($params)
    {
		//$trans_from = $params['trans_from'];
    	$property = $params['property'];

    	$orderSettings = $this->getOrderSetting($params);
    	$propertySetting = null;
    	if (isset($orderSettings->$property)) {

    		$propertySetting = $orderSettings->$property;
    	}

		/*if(isset($propertySettings->$trans_from)){
            $propertySetting =  $propertySettings->$trans_from;
        }else{
        	$propertySetting =  $propertySettings->DEFAULT;
        }*/

        return $propertySetting;
    }


    public function getOrderLayBySetting($params)
    {

    	$trans_from = $params['trans_from'];
    	$params['property'] = 'lay_by';
    	$propertySettings =  $this->getCommonOrderSettingFunction($params);

    	$propertySetting = ['status' => 0];
    	if ($propertySettings != '' && $propertySettings != null) {
    		if (isset($propertySettings->$trans_from)) {
    			$propertySetting =  $propertySettings->$trans_from;
    		} else {
    			$propertySetting =  $propertySettings->DEFAULT;
    		}
    	}

    	return $propertySetting;
    }

    public function getOrderOnAccountSetting($params)
    {

    	$trans_from = $params['trans_from'];
    	$params['property'] = 'on_account';
    	$propertySettings =  $this->getCommonOrderSettingFunction($params);

    	$propertySetting = ['status' => 0];
    	if ($propertySettings != '' && $propertySettings != null) {
    		if (isset($propertySettings->$trans_from)) {
    			$propertySetting =  $propertySettings->$trans_from;
    		} else {
    			$propertySetting =  $propertySettings->DEFAULT;
    		}
    	}

    	return $propertySetting;
    }

	#### Order history start here ####
    public function getOrderHistorySetting($params)
    {

    	$params['property'] = 'history';
    	return $this->getCommonOrderSettingFunction($params);
    }

    public function getCommonOrderHistoryFunction($params)
    {

    	$trans_from = $params['trans_from'];
    	$property = $params['property'];

    	$orderSettings = $this->getOrderHistorySetting($params);
    	$propertySettings = $orderSettings->$property;

    	if (isset($propertySettings->$trans_from)) {
    		$propertySetting =  $propertySettings->$trans_from;
    	} else {
    		$propertySetting =  $propertySettings->DEFAULT;
    	}

    	return $propertySetting;
    }

    public function getOrderHistoryTotalSetting($params)
    {
    	$params['property'] = 'total_flag';
    	return $this->getCommonOrderHistoryFunction($params);
    }

	#### Order history Ends here ####

	#### Order Details start here ####
    public function getOrderDetailSetting($params)
    {
    	$params['property'] = 'details';
    	return $this->getCommonOrderSettingFunction($params);
    }

    public function getCommonOrderDetailFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = $params['property'];

    	$orderSettings = $this->getOrderDetailSetting($params);
    	$propertySettings = $orderSettings->$property;

    	if (isset($propertySettings->$trans_from)) {
    		$propertySetting =  $propertySettings->$trans_from;
    	} else {
    		$propertySetting =  $propertySettings->DEFAULT;
    	}

    	return $propertySetting;
    }

    public function getOrderDetailRPriceSetting($params)
    {

    	$params['property'] = 'r_price_flag';
    	return  $this->getCommonOrderDetailFunction($params);
    }

    public function getOrderDetailSPriceSetting($params)
    {

    	$params['property'] = 's_price_flag';

    	return  $this->getCommonOrderDetailFunction($params);
    }

    public function getOrderDetailTotalSetting($params)
    {

    	$params['property'] = 'total_flag';
    	return  $this->getCommonOrderDetailFunction($params);
    }

    public function getOrderDetailSavingSetting($params)
    {

    	$params['property'] = 'saving_flag';
    	return  $this->getCommonOrderDetailFunction($params);
    }

    public function getOrderDetailSubTotalSetting($params)
    {

    	$params['property'] = 'sub_total_flag';
    	return  $this->getCommonOrderDetailFunction($params);
    }

    public function getOrderDetailTaxTotalSetting($params)
    {

    	$params['property'] = 'tax_total_flag';
    	return  $this->getCommonOrderDetailFunction($params);
    }

    public function getOrderDetailCarrayBagTotalSetting($params)
    {

    	$params['property'] = 'carry_bag_total_flag';
    	return  $this->getCommonOrderDetailFunction($params);
    }

    public function getOrderDetailVoucherTotalSetting($params)
    {

    	$params['property'] = 'voucher_total_flag';
    	return  $this->getCommonOrderDetailFunction($params);
    }

    public function getInvoiceButton($params)
    {

    	$params['property'] = 'invoice_button_flag';
    	return  $this->getCommonOrderDetailFunction($params);
    }




	#### Order Details ends here ####


	###########################################
	####### Order Setting Ends here ##########
	###########################################


	###########################################
	####### Price Setting Start here ##########
	###########################################

    public function getPriceSetting($params)
    {
    	$params['name'] = 'price';
    	$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

    	$priceSettings = json_decode('{"price_flag":{"DEFAULT":{"status":1}}}');

    	$settings = $this->getSetting($params);
    	if ($settings) {
    		$template = $settings->template;
    		$settings = $settings->first()->settings;
    		$priceSettings = json_decode($settings);
    		$priceSettings->template = $template;
    	}

    	return $priceSettings;
    }

    public function getCommonPriceSettingFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = $params['property'];

    	$priceSettings = $this->getPriceSetting($params);
    	$propertySettings = $priceSettings->$property;

    	if (isset($propertySettings->$trans_from)) {
    		$propertySetting =  $propertySettings->$trans_from;
    	} else {
    		$propertySetting =  $propertySettings->DEFAULT;
    	}
    	return $propertySetting;
    }

    public function getPricePriceSetting($params)
    {

    	$params['property'] = 'price_flag';
    	return  $this->getCommonPriceSettingFunction($params);
    }


    public function getSettlementSetting($params)
    {
    	$params['name'] = 'settlement';
    	$v_id = $params['v_id'];
    	$trans_from = $params['trans_from'];
    	$settlementSettings = json_decode('{"open_session_compulsory":{"ANDROID":{"status":0},"ANDROID_VENDOR":{"status":0},"DEFAULT":{"status":0}}}');

    	$settings = $this->getSetting($params);
    	if ($settings) {
    		$template = $settings->template;
    		$settings = $settings->first()->settings;
    		$settlementSettings = json_decode($settings);
    		$settlementSettings->template = $template;
    	}

    	return $settlementSettings;
    }

   


    public function getSettlementOpenSessionFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'open_session_compulsory';

    	$cartSettings = $this->getSettlementSetting($params);
    	$propertySetting = ['status' => 0];

    	if ($cartSettings) {
    		$propertySettings = $cartSettings->$property;

    		if (isset($propertySettings->$trans_from)) {
    			$propertySetting =  $propertySettings->$trans_from;
    		} else {
    			$propertySetting =  $propertySettings->DEFAULT;
    		}
    	}

    	return $propertySetting;
    }

    public function getItemExchangeFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'allow_item_exchange';

    	$settlementSettings = $this->getSettlementSetting($params);
    	$propertySetting = [
    			'status' => 0 ,
				'display_name' => '' ,
				'display_text' => '', 
				'adhoc_exchange' => [
                    'status' => 0 ,  
                    'display_text' => 'Adhoc Exchange',
                    'display_name' => 'Adhoc Exchange',
                    'editable' => [ [ 'status' => 'boolean' ] ],
                    ],
                'allow_inter_store_exchange' => [
                    'status' => 0 ,  
                    'display_text' => 'Allow Inter Store Exchange',
                    'display_name' => 'Allow Inter Store Exchange',
                    'editable' => [ [ 'status' => 'boolean' ] ],
                    ],
                'allow_price_override_exchange' => [
                    'status' => 0 ,  
                    'display_text' => 'Allow Price Override Exchange',
                    'display_name' => 'Allow Price Override Exchange',
                    'editable' => [ [ 'status' => 'boolean' ] ],
                    ],
                'against_invoice_exchange' => [ 
                    'status' => 0 ,  
                    'display_text' => 'Exchange Against Invoice',
                    'display_name' => 'Exchange Against Invoice',
                    'value' => 'optional',
                    'internal_options' => []
                ]

    		];

    	if ($settlementSettings) {
    		if(isset($settlementSettings->$property) ){
	    		$propertySettings = $settlementSettings->$property;

	    		if (isset($propertySettings->$trans_from)) {
	    			$propertySetting =  $propertySettings->$trans_from;
	    		} else {
	    			$propertySetting =  $propertySettings->DEFAULT;
	    		}
    			
    		}
    	}

    	return $propertySetting;
    }

    public function getAdhocExchange($params){

    	// $propertySetting = $this->getItemExchangeFunction($params);
    	// if(isset($propertySetting->options[0])){
    	// 	$prop = $propertySetting->options[0]->adhoc_exchange;
    	// 	unset($prop->editable);
    	// 	return $prop;
    	// }
    	

    }

    public function getInterStoreExchange($params){

    	// $propertySetting = $this->getItemExchangeFunction($params);
    	// $prop = $propertySetting->options[0]->allow_inter_store_exchange;
    	// unset($prop->editable);

    	// return $prop;

    }

    public function getPriceOverrideExchange($params){

    	// $propertySetting = $this->getItemExchangeFunction($params);
    	// $prop = $propertySetting->options[0]->allow_price_override_exchange;
    	// unset($prop->editable);

    	// return $prop;

    }

    public function getAgainstInvoiceExchange($params){

    	// $propertySetting = $this->getItemExchangeFunction($params);

    	// $prop = $propertySetting->options[0]->against_invoice_exchange;
    	// unset($prop->internal_options);

    	// return $prop;

    }

    public function getActiveSessionday($params)
    {
    	$params['name'] = 'settlement';
    	$v_id = $params['v_id'];
    	$trans_from = $params['trans_from'];
        $active_session_day=1;
    	$settings = $this->getSetting($params);
    	if ($settings) {
    		$settings = $settings->first()->settings;
    		$settlementSettings = json_decode($settings);
    		if(isset($settlementSettings->session_compulsory->DEFAULT->options[0]->session_alive->value)){
              $active_session_day = $settlementSettings->session_compulsory->DEFAULT->options[0]->session_alive->value; 
    		}else{
    	      $active_session_day =1;		
    		}
    	}
    	return (int)$active_session_day;
    }

    public function getStoreDaySettlement($params)
    {
    	$params['name'] = 'settlement';

    	$defaultSettings = json_decode('{"active_session_day":{"DEFAULT":{"status":0,"level":"2","for":2,"display_text":"Allows store manager to perform daily settlement prior to store closing","display_name":"Store day settlement","options":[{"minimun_no_days":{"display_text":"Maximum number of days a store can remain active without completing a day settlement","display_name":"minimum no of day settlement","type":null,"value":"1","editable":[{"type":"input"}]}}],"editable":[{"status":"boolean"}]}}}');
    	
    	$settings = $this->getSetting($params);
		$settings = $settings->first()->settings;
		$settlementSettings = json_decode($settings);
		if(isset($settlementSettings->active_session_day)) {
          $store_day_settlement = $settlementSettings->active_session_day->DEFAULT; 
		}else{
			$store_day_settlement = $defaultSettings->active_session_day->DEFAULT; 	
		}
    	
    	return $store_day_settlement;
    }
    public function getAllowItemExchangeSettings($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'allow_item_exchange';

    	$settlementSettings = $this->getSettlementSetting($params);
    	$propertySetting=0;
    	if ($settlementSettings->$property) {
    		$propertySetting =  $settlementSettings->$property->DEFAULT->status;
    	}

    	return $propertySetting;
    }
    public function getSessionForceClosure($params)
    {
    	$params['name'] = 'settlement';
    	$session_force_closure=0;
    	$settings = $this->getSetting($params);
		$settings = $settings->first()->settings;
		$sessionForceClosureSetting = json_decode($settings);
		if(isset($sessionForceClosureSetting->session_force_closure->DEFAULT->status)) {
          $session_force_closure = $sessionForceClosureSetting->session_force_closure->DEFAULT->status; 
		}
    	
    	return $session_force_closure;
    }

    public function getSettlementPreviousCloseSessionFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'close_session_compulsory';

    	$cartSettings = $this->getSettlementSetting($params);

    	$propertySetting = ['status' => 0];

    	if ($cartSettings) {
    		if (isset($cartSettings->$property)) {
    			$propertySettings = $cartSettings->$property;

    			if (isset($propertySettings->$trans_from)) {
    				$propertySetting =  $propertySettings->$trans_from;
    			} else {
    				$propertySetting =  $propertySettings->DEFAULT;
    			}
    		}
    	}

    	return $propertySetting;
    }

    public function getSettlementDenominationFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'denomination';

    	$cartSettings = $this->getSettlementSetting($params);

    	$propertySetting = (object) ['status' => 0];

    	if ($cartSettings) {
    		if (isset($cartSettings->$property)) {
    			$propertySettings = $cartSettings->$property;

    			if (isset($propertySettings->$trans_from)) {
    				$propertySetting =  $propertySettings->$trans_from;
    			} else {
    				if(!empty($cartSettings->session_compulsory->DEFAULT->options[0]->denomination)){
    				     $propertySetting =  $cartSettings->session_compulsory->DEFAULT->options[0]->denomination;
    					}
    			}
    		}
    	}
    	return $propertySetting;
    }

    public function getSettlementDenominationStatus($params)
    {
    	if ($pro = $this->getSettlementDenominationFunction($params)) {
    		if($pro->value == 1){
    			$pro->value = 1;
    		}elseif($pro->value == 0){
    			$pro->value = 0;
    		}elseif($pro->value == true){
    			$pro->value = 1;
    		}elseif($pro->value == false){
    			$pro->value = 0;
    		}
    		return $pro->value;
    	} else {
    		return 0;
    	}
    }

	###########################################
	####### Offer Setting Start here ##########
	###########################################


    public function getOfferSetting($params)
    { 
    	$params['name'] = 'offer';
    	$v_id = $params['v_id'];
    	$trans_from = $params['trans_from'];

    	$offerSettings = null;

    	$settings = $this->getSetting($params);
    	if ($settings) { 
    		$template = $settings->template;
    		$settings = $settings->first()->settings;
    		$offerSettings = json_decode($settings);
    		$offerSettings->template = $template;
    	}
    	// dd($settings);
    	return $offerSettings;
    }

    public function getOfferManualDiscountSetting($params)
    {

    	$trans_from = $params['trans_from'];
    	$property = 'manual_discount';

    	$offerSettings = $this->getOfferSetting($params);
    	$propertySetting = [
    		'status' => 0, "max_percentage" => 40,
    		"max_amount" => 30
    	];

    	if ($offerSettings) {
    		$propertySettings = $offerSettings->$property;

		
    		if (isset($propertySettings->$trans_from)) {
    			$propertySetting =  $propertySettings->$trans_from;
    		} else {
    			$propertySetting =  $propertySettings->DEFAULT;
    		}


    			 
    		if(isset($propertySetting->options[0]->bill_level_percentage_amount->status) ){
	            if($propertySetting->options[0]->bill_level_percentage_amount->status == true){
	              $max_percentage = $propertySetting->options[0]->bill_level_percentage_amount->internal_options->max_item_level_percentage->value;
	              $max_amount = $propertySetting->options[0]->bill_level_percentage_amount->internal_options->max_item_level_amount->value;
	            }
	            if(isset($max_percentage)){
					$propertySetting->max_percentage = $max_percentage;
					$propertySetting->max_amount     = $max_amount;
	            }
	        }else{
	        	if(empty($propertySetting->max_percentage) || empty($propertySetting->max_percentage)){
	        	   $propertySetting->max_percentage = 40;
				   $propertySetting->max_amount     = 40;	
	        	}
	        }

	        if(empty($propertySetting->max_percentage) || empty($propertySetting->max_percentage)){
	        	$propertySetting->max_percentage = 100;
				$propertySetting->max_amount     = 10000;
			}	
    	}
    	return $propertySetting;
    }

    public function getOfferItemManualDiscountSetting($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'item_level_manual_discount';

    	$offerSettings = $this->getOfferSetting($params);

    	$propertySetting = [
    		'status' => 0, "max_percentage" => 40,
    		"max_amount" => 30
    	];

    	if ($offerSettings) {
    		$propertySettings = $offerSettings->$property;

    		if (isset($propertySettings->$trans_from)) {
    			$propertySetting =  $propertySettings->$trans_from;
    		} else {
    			$propertySetting =  $propertySettings->DEFAULT;
    		}



    		if(isset($propertySetting->options[0]->item_level_percentage_amount->status) ){
	            if($propertySetting->options[0]->item_level_percentage_amount->status == true){
	              $max_percentage = $propertySetting->options[0]->item_level_percentage_amount->internal_options->max_item_level_percentage->value;
	              $max_amount = $propertySetting->options[0]->item_level_percentage_amount->internal_options->max_item_level_amount->value;
	            }
	            if(isset($max_percentage)){
					$propertySetting->max_percentage = $max_percentage;
					$propertySetting->max_amount     = $max_amount;
	            }
	        }else{

	        	$propertySetting->max_percentage = 40;
				$propertySetting->max_amount     = 40;

	        }


    	}

    	return $propertySetting;
    }

    public function getPromotionSetting($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'promotion';

    	$offerSettings = $this->getOfferSetting($params);

    	if ($offerSettings) {
    		$propertySettings = $offerSettings->$property;

    		if (isset($propertySettings->$trans_from)) {
    			$propertySetting =  $propertySettings->$trans_from;
    		} else {
    			$propertySetting =  $propertySettings->DEFAULT;
    		}
    	}

    	return $propertySetting;	
    }

	###########################################
	####### Offer Setting Ends here ##########
	###########################################


	###########################################
	####### Tax Setting Start here ##########
	###########################################
    public function getTaxSetting($params)
    {
    	$params['name'] = 'tax';
    	$v_id = $params['v_id'];
    	$trans_from = $params['trans_from'];

    	$offerSettings = null;

    	$settings = $this->getSetting($params);
    	if ($settings) {
    		$settings = $settings->first()->settings;
    		$offerSettings = json_decode($settings);
    	}

    	return $offerSettings;
    }

    public function getTaxManualSetting($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'manual_apply';

    	$offerSettings = $this->getTaxSetting($params);

    	$propertySetting = ['status' => 0];

    	if ($offerSettings) {
    		$propertySettings = $offerSettings->$property;

    		if (isset($propertySettings->$trans_from)) {
    			$propertySetting =  $propertySettings->$trans_from;
    		} else {
    			$propertySetting =  $propertySettings->DEFAULT;
    		}
    	}

    	return $propertySetting;
    }

    public function getStockSetting($params)
    {
    	$params['name'] = 'stock';
    	$v_id = $params['v_id'];
    	$data = [];

    	$stockSettings = json_decode('{"negative_stock_billing":{"status":0,"is_warning":0,"warning_msg":"Your item is out of stock do you want to allow billing","display_text":"Negative Stock Billing","display_name":"Negative Stock Billing","editable":[{"status":"boolean"}]},"damage_stock":{"stock_countable":{"status":0,"display_name":"Damage Stock Countable","display_text":"Allow Damage Stock Countable","editable":[{"status":"boolean"}]}}}');

    	$settings = $this->getSetting($params);
    	if (!empty($settings)) {
    		$template = $settings->template;
    		$settings = $settings->first()->settings;
    		$stockSettings = json_decode($settings);
    		$stockSettings->template = $template;
    	}

    	$stockSettings->negative_stock_billing = $this->getNegativeStockBilling($stockSettings->negative_stock_billing);

    	if(property_exists($stockSettings, 'damage_stock')) {
    		$stockSettings->damage_stock = $this->damageStock($stockSettings->damage_stock);
    	}

    	return $stockSettings;
    }

    //get inventory settings
    public function getInventorySetting($params)
    {
    	$params['name'] = 'stock';
    	$v_id = $params['v_id'];
    	$data = [];

    	$stockSettings = json_decode('{"negative_stock_billing":{"status":0,"is_warning":0,"warning_msg":"Your item is out of stock do you want to allow billing","display_text":"Negative Stock Billing","display_name":"Negative Stock Billing","editable":[{"status":"boolean"}]},"damage_stock":{"stock_countable":{"status":0,"display_name":"Damage Stock Countable","display_text":"Allow Damage Stock Countable","editable":[{"status":"boolean"}]}}}');

    	$settings = $this->getSetting($params);
    	if (!empty($settings)) {
    		$settings = $settings->first()->settings;
    		$stockSettings = json_decode($settings);
    	}


    	return $stockSettings;
    }

    public function getNegativeStockBilling($settings)
    {
    	return (object) [
    		'status'        => $settings->status,
    		'is_warning'    => $settings->is_warning,
    		'warning_msg'   => $settings->warning_msg
    	];
    }

    public function damageStock($settings)
    {
    	return (object) [
    		'status'        => $settings->stock_countable->status
    	];
    }

    public function getStoreCredit($params)
    {
    	$trans_from = $params['trans_from'];

    	$productSettings = $this->getStoreSetting($params);
    	$storeCredit = (object) ['display_status' => 0];
    	if (isset($productSettings->credit)) {
    		$credit = $productSettings->credit;

    		if (isset($credit->$trans_from)) {
    			$storeCredit =  $credit->$trans_from;
    		} else {
    			$storeCredit =  $credit->DEFAULT;
    		}
    	}

    	return $storeCredit;
    }

	###########################################
	####### Tax Setting Ends here ##########
	###########################################

    public function invoiceFormatSetting($params){
    	
    	return  offline_invoice_id_generate($params);
    	
    }


     public function getSessionCompulsorySettingFunction($params)
    {
    	$trans_from = $params['trans_from'];
    	$property = 'session_compulsory';
    	$cartSettings = $this->getSettlementSetting($params);
        //dd($cartSettings);
    	$propertySetting = ['status' => 0];

    	if ($cartSettings) {
    		if (isset($cartSettings->$property)) {
    			$propertySettings = $cartSettings->$property;
               
    			if (isset($propertySettings->$trans_from)) {
    				$propertySetting =  $propertySettings->$trans_from;
    			} else {
    				$propertySetting =  $propertySettings->DEFAULT;
    			}
    		}
    		
    	}
    	return $propertySetting;
    	//dd($propertySetting);
    }

    public function getOMSSetting($params)
    {
    	$params['name'] = 'order';
    	$v_id = $params['v_id'];
    	$omsSettings = '{"DEFAULT":{"status":0,"level":"2,3,4","display_text":"Allow taking orders on POS","display_name":"Order POS","editable":[{"status":"boolean"}],"options":[{"is_advance_mandatory":{"status":0,"display_text":"Advance collection Mandatory","display_name":"Advance collection Mandatory","internal_options":{"amount":{"type":"","value":"","display_text":"Minimum amount value","display_name":"","editable":[{"type":"input"}]},"max_item_level_amount":{"type":"","value":"","display_text":"Minimum percentage value","display_name":"","editable":[{"type":"input"}]}},"editable":[{"status":"boolean"}]}}]}}';
    	$settings = $this->getSetting($params);
    	$filterSettings = json_decode($settings->first()->settings);
    	if (property_exists($filterSettings, "oms")) {
    		$omsSettings = $filterSettings->oms;
    	} else {
    		$omsSettings = json_decode($omsSettings);
    	}

    	return $omsSettings;
    }

    public function getAccountSaleSetting($params){
    	$vsetting  = $this->getVendorAppSetting($params);
    	$allowAccountSale = false;
    	if($vsetting){
    		if(!empty($vsetting->customer_logins->DEFAULT->options[0]->on_account_sale)){
    			$allowAccountSale = $vsetting->customer_logins->DEFAULT->options[0]->on_account_sale->value;
    		}
    	}
    	return $allowAccountSale;
    	
    }

     public function getAccountSaleActiveInactive($params){
    	
    	$checkSetting = $this->getAccountSaleSetting($params);
    	$account_sale = ['status' => 0];
    	if($checkSetting=='mandatory'){
        	$account_sale = ['status' => 1];
        }
    	return $account_sale;	
    }

}
