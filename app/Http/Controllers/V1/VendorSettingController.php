<?php
namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\VendorSetting;
use Auth;

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

		$settings = VendorSetting::where('v_id', $v_id)->where('name',$name)->first();

		if($settings){

			$settings->type = $type;
			$settings->user_id = $c_id;
			$settings->save();

		}else{

			$settings = new VendorSetting;

			$settings->v_id = $v_id;
			$settings->name = $name;
			$settings->settings = $settings;
			$settings->status = '1';

			$settings->save();
		}
		
		return response()->json(['status' => 'save', 'message' => 'Vendor setting  Save Successfully' ],200);
	}

	public function get_setting(Request $request){

		$v_id = $request->v_id;

		$settings = VendorSetting::select('name','settings')->where('v_id',$v_id);

		if($request->has('name')){
			$settings = $settings->where('name',$request->name);
		}

		$settings = $settings->get();
		

		if($settings->isEmpty()){
			return response()->json(['status' => 'fail', 'message' => 'No record found' ]);
		}else{
			return response()->json(['status' => 'sucess', 'data' => $settings ]);
		}

	}

	public function getSetting($v_id , $name=''){

		$settings = VendorSetting::select('name','settings')->where('v_id',$v_id);

		if($name !=''){
			$settings = $settings->where('name',$name);
		}

		$settings = $settings->get();


		if($settings->isEmpty()){
			return null;
		}else{
			return $settings;
		}

	}

	public function getPaymentSetting($params){

		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];

		$paymentSettings = json_decode('{"charges":{"status":1,"for":{"card":{"type":"PERCENTAGE","value":1},"netbanking":{"type":"PERCENTAGE","value":1},"wallet ":{"type":"PERCENTAGE","value":1},"emi ":{"type":"PERCENTAGE","value":1}}},"settlement_day":{"default_day":7,"added_day":1},"type":{"ANDROID":[{"name":"razor_pay_online","status": 1},{"name":"ezetap","status": 1},{"name":"cash","status": 1}],"ANDROID_VENDOR":[{"name":"razor_pay_online","status": 1},{"name":"ezetap","status": 1},{"name":"cash","status": 1}],"IOS":[{"name":"razor_pay_online","status": 1},{"name":"ezetap","status": 0},{"name":"cash","status": 1}],"DEFAULT":[{"name":"razor_pay_online","status": 1},{"name":"ezetap","status": 0},{"name":"cash","status": 0}]}}');
		
		$settings = $this->getSetting($v_id , 'payment');
        if($settings){
            $settings = $settings->first()->settings;
            $paymentSettings = json_decode($settings);
        }

        return $paymentSettings;
	}

	public function getPaymentTypeSetting($params){

		$trans_from = $params['trans_from'];

		$paymentSettings = $this->getPaymentSetting($params);
		$paymentTypeSettings = $paymentSettings->type;

		if(isset($paymentTypeSettings->$trans_from)){
            $paymentTypeSetting =  $paymentTypeSettings->$trans_from;
        }else{
        	$paymentTypeSetting =  $paymentTypeSettings->DEFAULT;
        }

        return $paymentTypeSetting;
	}

	public function getStoreSetting($params){

		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];

		$storeSettings = json_decode('{"enable_shopping_radius":{"status":0,"default_radius":"1.5 ","apply_type":"store_wise","store_wise":{"18":{"radius":10},"17":{"radius":10}}},"cart_max_item":{"ANDROID":50,"ANDROID_KIOSK":10,"DEFAULT":50}}');
		
		$settings = $this->getSetting($v_id , 'store');
        if($settings){
            $settings = $settings->first()->settings;
            $storeSettings = json_decode($settings);
        }

        return $storeSettings;
	}

	public function getProductSetting($params){

		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];

		$productSettings = json_decode('{"review_rating":{"used":"TOGETHER","review":{"status":"ON","display_type":"STORE_WISE"},"rating":{"status":"ON","display_type":"STORE_WISE"}},"max_qty":{"ANDROID" : 10, "ANDROID_KIOSK": 5,"DEFAULT": 50},"max_item_in_cart":{"ANDROID" : 10, "ANDROID_KIOSK": 5,"DEFAULT": 50},"default_image":{"ANDROID" : "zwing_default.png","IOS" : "zwing_default.png", "ANDROID_KIOSK": "zwing_default.png","DEFAULT": "zwing_default.png"}}');
		
		$settings = $this->getSetting($v_id , 'product');
        if($settings){
            $settings = $settings->first()->settings;
            $productSettings = json_decode($settings);
        }

        return $productSettings;
	}

	public function getMaxItemInCart($params){
		$trans_from = $params['trans_from'];

		$productSettings = $this->getProductSetting($params);
		$max_item_in_cart = $productSettings->max_item_in_cart;

		if(isset($max_item_in_cart->$trans_from)){
            $maxCartItem =  $max_item_in_cart->$trans_from;
        }else{
        	$maxCartItem =  $max_item_in_cart->DEFAULT;
        }

        return $maxCartItem;
	}

	public function getProductMaxQty($params){
		$trans_from = $params['trans_from'];

		$productSettings = $this->getProductSetting($params);
		$max_qty = $productSettings->max_qty;

		if(isset($max_qty->$trans_from)){
            $maxQty =  $max_qty->$trans_from;
        }else{
        	$maxQty =  $max_qty->DEFAULT;
        }

        return $maxQty;
	}

	public function getProductDefaultImage($params){
		$trans_from = $params['trans_from'];

		$productSettings = $this->getProductSetting($params);
		$default_image = $productSettings->default_image;

		if(isset($default_image->$trans_from)){
            $defaultImage =  $default_image->$trans_from;
        }else{
        	$defaultImage =  $default_image->DEFAULT;
        }

        return $defaultImage;
	}


	public function getVendorAppSetting($params){

		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$vendorAppSettings = json_decode('{"main_menu":[{"name":"shop_by_catalog","status":0,"order_seq":1},{"name":"verify_order","status":1,"order_seq":2},{"name":"scan_for_customer","status":1,"order_seq":3}],"bottom_menu":[{"name":"orders","status":1,"order_seq":1},{"name":"statistics","status":1,"order_seq":2},{"name":"inventory","status":1,"order_seq":3},{"name":"profile","status":1,"order_seq":4}],"profile_tab_menu":[{"name":"mpos","status":1,"display_text":"mPos","data":[{"name":"orders","status":1,"display_text":"Your Oders"},{"name":"exchange_return","status":1,"display_text":"Exchange / Return"},{"name":"previous_scan","status":1,"display_text":"Previous Scan"}]},{"name":"setting","status":1,"display_text":"Setting","data":[{"name":"notification","status":1,"display_text":"Notification"},{"name":"edit_information","status":1,"display_text":"Edit Information"},{"name":"change_pin","status":1,"display_text":"change_pin"}]},{"name":"support","status":1,"display_text":"Support","data":[{"name":"support","status":1,"display_text":"Support"},{"name":"contact_us","status":0,"display_text":"Contact Us"},{"name":"faq","status":1,"display_text":"FAQ"},{"name":"term_condition","status":1,"display_text":"Terms & Conditions"}]}]}');
		
		$settings = $this->getSetting($v_id , 'vendor_app');
        if($settings){
            $settings = $settings->first()->settings;
            $vendorAppSettings = json_decode($settings);
        }

       
        $order_seq  = array_column($vendorAppSettings->main_menu, 'order_seq');
        $order_seq_bottom  = array_column($vendorAppSettings->bottom_menu, 'order_seq');
        //dd($order_seq);
        array_multisort($order_seq,SORT_ASC,$vendorAppSettings->main_menu);
        array_multisort($order_seq_bottom,SORT_ASC,$vendorAppSettings->bottom_menu);


        return $vendorAppSettings;
	}

	public function getVendorUserLogin($params){
		$trans_from = $params['trans_from'];
		$vendorAppSettings = $this->getVendorAppSetting($params);
		//dd($vendorAppSettings);
		$vendorUserLogin = $vendorAppSettings->user_login;

		if(isset($vendorUserLogin->$trans_from)){
			$userLogin = $vendorUserLogin->$trans_from;
		}else{
			$userLogin = $vendorUserLogin->DEFAULT;
		}

		return $userLogin;

	}

	public function getVendorCustomerLogin($params){
		$trans_from = $params['trans_from'];
		$vendorAppSettings = $this->getVendorAppSetting($params);

		return $vendorAppSettings->customer_login;

	}

	public function getColorSetting($params){

		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');
		
		$settings = $this->getSetting($v_id , 'color');
        if($settings){
            $settings = $settings->first()->settings;
            $colorSettings = json_decode($settings);
        }

        return $colorSettings;
	}

	
	public function getToolbarSetting($params){

		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$toolbarSettings = json_decode('{"bg_color":{"color_top":{"r":218,"g":97,"b":38, "hex": "#F43746"},"color_bottom":{"r":240,"g":116,"b":55,"hex": "#B2212D"}},"txt_color":{"r":255,"g":255,"b":255,"hex": "#FFFFFF","black":0}}');
		
		$settings = $this->getSetting($v_id , 'toolbar');
        if($settings){
            $settings = $settings->first()->settings;
            $toolbarSettings = json_decode($settings);
        }

        return $toolbarSettings;
	}

	public function getFeatureSetting($params){

		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$featureSettings = json_decode('{"feedback":{"ANDROID":{"status": 0},"ANDROID_VENDOR":{"status": 1},"DEFAULT":{"status": 0}},"invoice" : {"ANDROID":[{"name":"send_verification_invoice","status": 0}],"ANDROID_VENDOR":[{"name":"send_verification_invoice","status": 0}],"DEFAULT":[{"name":"send_verification_invoice","status": 0}]},"print":{"ANDROID_VENDOR" :[ {"name":"bill_repirnt" ,"status" : 1, "width" : 80}],"DEFAULT" :[ {"name":"bill_repirnt" ,"status" : 1, "width" : 80}] }}');
		
		$settings = $this->getSetting($v_id , 'feature');
        if($settings){
            $settings = $settings->first()->settings;
            $featureSettings = json_decode($settings);
        }

        return $featureSettings;
	}

	public function getFeedbackSetting($params){
		
		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$feedbackSettings = $featureSettings->feedback;

		if(isset($feedbackSettings->$trans_from)){
            $feedbackSetting =  $feedbackSettings->$trans_from;
        }else{
        	$feedbackSetting =  $feedbackSettings->DEFAULT;
        }

        return $feedbackSetting;

	}

	public function getInvoiceSetting($params){
		
		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$invoiceSettings = $featureSettings->invoice;

		if(isset($invoiceSettings->$trans_from)){
            $invoiceSetting =  $invoiceSettings->$trans_from;
        }else{
        	$invoiceSetting =  $invoiceSettings->DEFAULT;
        }

        return $invoiceSetting;

	}

	public function getPrintSetting($params){
		
		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$printSettings = $featureSettings->print;

		if(isset($printSettings->$trans_from)){
            $printSetting =  $printSettings->$trans_from;
        }else{
        	$printSetting =  $printSettings->DEFAULT;
        }

        return $printSetting;

	}

	public function getBarcodeSetting($params){
		
		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$barcodeSettings = $featureSettings->barcode;

		if(isset($barcodeSettings->$trans_from)){
            $barcodeSetting =  $barcodeSettings->$trans_from;
        }else{
        	$barcodeSetting =  $barcodeSettings->DEFAULT;
        }

        return $barcodeSetting;

	}

	public function getOptimizeFlowSetting($params){
		
		$trans_from = $params['trans_from'];

		$featureSettings = $this->getFeatureSetting($params);
		$optimize_flow = $featureSettings->optimize_flow;

		/*if(isset($barcodeSettings->$trans_from)){
            $barcodeSetting =  $barcodeSettings->$trans_from;
        }else{
        	$barcodeSetting =  $barcodeSettings->DEFAULT;
        }*/

        return $optimize_flow;

	}

	public function getReturnAuthorizationSetting($params){
		
		$trans_from = $params['trans_from'];
		$featureSettings = $this->getFeatureSetting($params);
		if(isset( $featureSettings->return_authorization) ){

			$invoiceSettings = $featureSettings->return_authorization;

			if(isset($invoiceSettings->$trans_from)){
	            $invoiceSetting =  $invoiceSettings->$trans_from;
	        }else{
	        	$invoiceSetting =  $invoiceSettings->DEFAULT;
	        }
		}else{

			$invoiceSetting = [ "status" => 0 ];
		}

        return $invoiceSetting;
	}

	###########################################
	####### Cart Setting Start here ###########
	###########################################
	
	public function getCartSetting($params){

		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$cartSettings = json_decode('{"avail_offers":{"ANDROID":{"status": 1},"ANDROID_VENDOR":{"status": 1},"DEFAULT":{"status": 1}}}');
		
		$settings = $this->getSetting($v_id , 'cart');
        if($settings){
            $settings = $settings->first()->settings;
            $cartSettings = json_decode($settings);
        }

        return $cartSettings;
	}

	public function getCommonCartSettingFunction($params ){
		$trans_from = $params['trans_from'];
		$property = $params['property'];

		$cartSettings = $this->getCartSetting($params);
		$propertySettings = $cartSettings->$property;

		if(isset($propertySettings->$trans_from)){
            $propertySetting =  $propertySettings->$trans_from;
        }else{
        	$propertySetting =  $propertySettings->DEFAULT;
        }

        return $propertySetting;

	}

	public function getCartAvailSetting($params){
		
		$params['property'] = 'avail_offers';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartRPriceSetting($params){
		
		$params['property'] = 'r_price_flag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartSPriceSetting($params){

		$params['property'] = 's_price_flag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartTotalSetting($params){
		
		$params['property'] = 'total_flag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartSavingSetting($params){
		
		$params['property'] = 'saving_flag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartSubTotalSetting($params){
		
		$params['property'] = 'sub_total_flag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartTaxTotalSetting($params){
		
		$params['property'] = 'tax_total_flag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartCarrayBagTotalSetting($params){
		
		$params['property'] = 'carry_bag_total_flag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartCarrayBagSetting($params){
		
		$params['property'] = 'carry_bag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartVoucherTotalSetting($params){
		
		$params['property'] = 'voucher_total_flag';
        return  $this->getCommonCartSettingFunction($params);

	}

	public function getCartPriceOverrideSetting($params){
		
		$params['property'] = 'price_override';
        return  $this->getCommonCartSettingFunction($params);

	}

	###########################################
	####### Cart Setting Ends here ############
	###########################################


	###########################################
	#### Scan Screen Setting Start here #######
	###########################################
	
	public function getScanScreenSetting($params){

		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$scanSettings = json_decode('{"cart_qty_total_flag":{"ANDROID":{"status":1},"ANDROID_VENDOR":{"status":1},"DEFAULT":{"status":1}},"total_flag":{"DEFAULT":{"status":1}},"offline_scan":{"ANDROID_VENDOR":{"status":0},"IOS_VENDOR":{"status":0},"DEFAULT":{"status":0}}}');
		
		$settings = $this->getSetting($v_id , 'scan_screen');
        if($settings){
            $settings = $settings->first()->settings;
            $scanSettings = json_decode($settings);
        }

        return $scanSettings;
	}

	public function getCommonScanScreenSettingFunction($params ){
		$trans_from = $params['trans_from'];
		$property = $params['property'];

		$scanSettings = $this->getScanScreenSetting($params);
		$propertySettings = $scanSettings->$property;

		if(isset($propertySettings->$trans_from)){
            $propertySetting =  $propertySettings->$trans_from;
        }else{
        	$propertySetting =  $propertySettings->DEFAULT;
        }

        return $propertySetting;

	}

	public function getScanScreenTotalSetting($params){
		
		$params['property'] = 'total_flag';
        return  $this->getCommonScanScreenSettingFunction($params);

	}

	public function getScanScreenCartQtyTotalSetting($params){
		
		$params['property'] = 'cart_qty_total_flag';
        return  $this->getCommonScanScreenSettingFunction($params);

	}

	public function getScanScreenOffilneSetting($params){
		$params['property'] = 'offline_scan';
        return  $this->getCommonScanScreenSettingFunction($params);
	}

	###########################################
	##### Scan Screen Setting Emds here #######
	###########################################
	

	###########################################
	####### Order Setting Start here ##########
	###########################################
	
	public function getOrderSetting($params){

		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$orderSettings = json_decode('{"history":{"total_flag":{"DEFAULT":{"status":1}}},"details":{"r_price_flag":{"DEFAULT":{"status":1}},"s_price_flag":{"DEFAULT":{"status":1}},"total_flag":{"DEFAULT":{"status":1}},"saving_flag":{"DEFAULT":{"status":1}},"sub_total_flag":{"DEFAULT":{"status":1}},"tax_total_flag":{"DEFAULT":{"status":1}},"carry_bag_total_flag":{"DEFAULT":{"status":1}},"voucher_total_flag":{"DEFAULT":{"status":1}}}}');
		
		$settings = $this->getSetting($v_id , 'order');
        if($settings){
            $settings = $settings->first()->settings;
            $orderSettings = json_decode($settings);
        }

        return $orderSettings;
	}

	public function getCommonOrderSettingFunction($params ){
		//$trans_from = $params['trans_from'];
		$property = $params['property'];

		$orderSettings = $this->getOrderSetting($params);
		$propertySetting = $orderSettings->$property;

		/*if(isset($propertySettings->$trans_from)){
            $propertySetting =  $propertySettings->$trans_from;
        }else{
        	$propertySetting =  $propertySettings->DEFAULT;
        }*/

        return $propertySetting;

	}

	#### Order history start here ####
	public function getOrderHistorySetting($params){

		$params['property'] = 'history';
		return $this->getCommonOrderSettingFunction($params);

	}

	public function getCommonOrderHistoryFunction($params){
		
		$trans_from = $params['trans_from'];
		$property = $params['property'];

		$orderSettings = $this->getOrderHistorySetting($params);
		$propertySettings = $orderSettings->$property;

		if(isset($propertySettings->$trans_from)){
            $propertySetting =  $propertySettings->$trans_from;
        }else{
        	$propertySetting =  $propertySettings->DEFAULT;
        }

        return $propertySetting;
	}

	public function getOrderHistoryTotalSetting($params){
		$params['property'] = 'total_flag';
		return $this->getCommonOrderHistoryFunction($params);	
	}

	#### Order history Ends here ####

	#### Order Details start here ####
	public function getOrderDetailSetting($params){
		$params['property'] = 'details';
		return $this->getCommonOrderSettingFunction($params);

	}

	public function getCommonOrderDetailFunction($params){

		$trans_from = $params['trans_from'];
		$property = $params['property'];

		$orderSettings = $this->getOrderDetailSetting($params);
		$propertySettings = $orderSettings->$property;

		if(isset($propertySettings->$trans_from)){
            $propertySetting =  $propertySettings->$trans_from;
        }else{
        	$propertySetting =  $propertySettings->DEFAULT;
        }

        return $propertySetting;

	}

	public function getOrderDetailRPriceSetting($params){
		
		$params['property'] = 'r_price_flag';
        return  $this->getCommonOrderDetailFunction($params);

	}

	public function getOrderDetailSPriceSetting($params){

		$params['property'] = 's_price_flag';

        return  $this->getCommonOrderDetailFunction($params);
	}

	public function getOrderDetailTotalSetting($params){
		
		$params['property'] = 'total_flag';
        return  $this->getCommonOrderDetailFunction($params);

	}

	public function getOrderDetailSavingSetting($params){
		
		$params['property'] = 'saving_flag';
        return  $this->getCommonOrderDetailFunction($params);

	}

	public function getOrderDetailSubTotalSetting($params){
		
		$params['property'] = 'sub_total_flag';
        return  $this->getCommonOrderDetailFunction($params);

	}

	public function getOrderDetailTaxTotalSetting($params){
		
		$params['property'] = 'tax_total_flag';
        return  $this->getCommonOrderDetailFunction($params);

	}

	public function getOrderDetailCarrayBagTotalSetting($params){
		
		$params['property'] = 'carry_bag_total_flag';
        return  $this->getCommonOrderDetailFunction($params);

	}

	public function getOrderDetailVoucherTotalSetting($params){
		
		$params['property'] = 'voucher_total_flag';
        return  $this->getCommonOrderDetailFunction($params);

	}

	public function getInvoiceButton($params){
		
		$params['property'] = 'invoice_button_flag';
        return  $this->getCommonOrderDetailFunction($params);

	}
	#### Order Details ends here ####


	###########################################
	####### Order Setting Ends here ##########
	###########################################
	

	###########################################
	####### Order Setting Start here ##########
	###########################################
	
	public function getPriceSetting($params){

		$v_id = $params['v_id'];
		//$trans_from = $params['trans_from'];

		$priceSettings = json_decode('{"price_flag":{"DEFAULT":{"status":1}}}');
		
		$settings = $this->getSetting($v_id , 'price');
        if($settings){
            $settings = $settings->first()->settings;
            $priceSettings = json_decode($settings);
        }

        return $priceSettings;
	}

	public function getCommonPriceSettingFunction($params ){
		$trans_from = $params['trans_from'];
		$property = $params['property'];

		$priceSettings = $this->getPriceSetting($params);
		$propertySettings = $priceSettings->$property;

		if(isset($propertySettings->$trans_from)){
            $propertySetting =  $propertySettings->$trans_from;
        }else{
        	$propertySetting =  $propertySettings->DEFAULT;
        }

        return $propertySetting;

	}

	public function getPricePriceSetting($params){
		
		$params['property'] = 'price_flag';
        return  $this->getCommonPriceSettingFunction($params);

	}

	public function getSettlementSetting($params){

		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];

		$settlementSettings = json_decode('{"open_session_compulsory":{"ANDROID":{"status":0},"ANDROID_VENDOR":{"status":0},"DEFAULT":{"status":0}}}');
		
		$settings = $this->getSetting($v_id , 'settlement');
        if($settings){
            $settings = $settings->first()->settings;
            $settlementSettings = json_decode($settings);
        }

        return $settlementSettings;
	}

	public function getSettlementOpenSessionFunction($params ){
		$trans_from = $params['trans_from'];
		$property = 'open_session_compulsory';

		$cartSettings = $this->getSettlementSetting($params);
		$propertySettings = $cartSettings->$property;

		if(isset($propertySettings->$trans_from)){
            $propertySetting =  $propertySettings->$trans_from;
        }else{
        	$propertySetting =  $propertySettings->DEFAULT;
        }

        return $propertySetting;
	}

}
