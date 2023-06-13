<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
 */

use Illuminate\Support\Facades\Artisan;
use  App\Http\Controllers\Loyality\EaseMyRetailController;
use App\Http\Controllers\SchedulerController;
use App\Jobs\TestSyncJob;

$versions = ['', 'v1'];
foreach ($versions as $version) {
	$group['prefix'] = $version;
	if ($version != '') {
		$group['namespace'] = ucfirst($version);
		// echo 'ok';
		// if ($version == 'v1') {
		// 	DB::disconnect();
		// 	config(["database.connections.dynamic" => [
		// 		"driver"     => "mysql",
		//               "host"      => "localhost",
		//               "port"      => "3306",
		//               "databazse"  => "dev_zwing",
		//               "username"  => "root",
		//               "password"  => ""
		//           ]]);
		//           config(['database.default' => 'dynamic']);
		// 	Artisan::call('cache:clear');
		// 	DB::reconnect();
		// 	echo 'Cool';
		// } else {
		// 	echo 'Not Cool';
		// 	DB::disconnect();
		// 	config(['database.default' => 'mysql']);
		// 	Artisan::call('cache:clear');
		// 	DB::reconnect();
		// }
	}
	$app->group($group, function () use ($app) {
		//		$app->get('mongo', function () use ($app) {
		//			//    $collection = (new Mongo)->mydatabase->mycollection;
		//			//    return $collection->find()->toArray();
		//			//    $collection = Mongo::get()->mydatabase->mycollection;
		//
		//			$collection = \App\LogCollection::all();
		//			return $collection;
		//			//    return $collection->find()->toArray()->pretty();
		//		});
		$app->get('/solr',  'SolariumController@select');
		$app->group(['middleware' => 'throttle:2,1'], function () use ($app) {
		$app->get('/', function () use ($app) {

			$test = new  App\Http\Controllers\TestController;
			$test->testJob();
			
			//dd("ok");
			// event(new App\Events\GrnCreated(["v_id" => 1]));
			// event(new App\Events\InvoiceCreated(["v_id" => 1]));
			 //event(new App\Events\DepositeRefund(["payment_id"=>15869,"v_id" => 1,"db_structure"=>2]));
			// dispatch(new App\Jobs\ItemCreate(["v_id" => 1]));
			
			// $request = new Illuminate\Http\Request(['v_id' =>74 , 'client_id' => 'VT2345RTZW87'  , 'ack_id' => '807001K8J0001' ]);
			// $itemC = new  App\Http\Controllers\ItemController;
			// $itemC->processItemMasterCreationJob($request);
			
			
			// $opening = new  App\Http\Controllers\Erp\OpeningStockController;
			// $opening->OpeningStockPush(['v_id' => 22 , 'store_id' =>'116','os_id' => '19','client_id'=>1]);
			
			// $grn = new  App\Http\Controllers\Erp\GrnController;
			// $grn->grnPush(['v_id' => 74 ,'store_id' => 177,'grn_id' =>887]);
		
			// $invoice = new  App\Http\Controllers\Erp\InvoiceController;
			// $invoice->InvoicePush(['v_id' =>74 , 'store_id' => 177  , 'invoice_id' =>14136,  'client_id' => '1','type' => 'SALE' ]);

			// $cash = new  App\Http\Controllers\Erp\CashManagementController;
			// $cash->pettyCashPush(['v_id' => 22 , 'store_id' => 116  , 'cash_transaction_id'=>'62',  'client_id' => '1','transfer_type'=>'1','PTCHeadCode'=>'360']);

		// $cash = new  App\Http\Controllers\Erp\StockPointTransferController;
		// 	$cash->itemStockTransfer(['v_id' => 22 , 'store_id' => 116 ,'spt_id'=>'1','client_id' => '1']);

			//  $adj = new  App\Http\Controllers\Erp\StockAdjustmentController;
			// $adj->posMisPush(['v_id' => 22 , 'store_id' => 116 ,'adj_id'=>'13','client_id' => '1']);

	
			// $cool = new EaseMyRetailController;
			// dd($cool->createBillPushResponse('Z2001002J4900004'));
			
			// $store = new  App\Http\Controllers\Erp\StoreController;
			// $store->operationStarted(['v_id' => 22 , 'store_id' => 116 ,'client_id' => 1]);

			// try {
			// 	DB::connection()->getPdo();
			// 	if (DB::connection()->getDatabaseName()) {
			// 		echo "Yes! Successfully connected to the DB: " . DB::connection()->getDatabaseName();
			// 	} else {
			// 		die("Could not find the database. Please check your configuration.");
			// 	}
			// } catch (\Exception $e) {
			// 	die("Could not open connection to database server.  Please check your configuration.");
			// }
			// return $app->app->version();
			// return date('D F Y H:i:s');
			// $scheduler = new SchedulerController;
			// dd($scheduler->makeOpeningStock());
			//echo phpinfo();
			// return order_id_generate(1,3);
			// $final = [];
			// $data = 'https://demo.api.gozwing.com/my-orders<br/><br/><strong>api_token</strong> = 95tE3XEJ1F4rJoTR0J4kdNItnvQXm41WxAEzMEPcIkgMrAT283<br/><strong>vu_id</strong> = 91<br/><strong>store_id</strong> = 14<br/><strong>start_date</strong> = <br/><strong>end_date</strong> = <br/><strong>search_term</strong> = <br/><strong>sort</strong> = <br/><strong>page</strong> = 1<br/><strong>trans_from</strong> = ANDROID_VENDOR</strong>';
			// $data = explode('<br/>', $data);
			// foreach ($data as $key => $value) {
			//     if (!empty($value)) {
			//         $replace = str_replace("<strong>", '', $value);
			//         $replace = str_replace("</strong>", '', $replace);
			//         if ($replace == url('/').Request::getPathInfo()) {
			//             $final[] = substr($replace, 0, 35);
			//         }

			//     }
			// }
			// dd($final);
		});
		});

		$app->post('/import-test', function(Illuminate\Http\Request $request) use ($app){

			$sheet = new App\Http\Sheet\Sheet;
			$sheet->import(new App\Http\Controllers\Imports\ItemImportController, $request->file('test') );
		});
		$app->post('/test-job', 'TestController@testJob');
	
		$app->get('/search', function (Illuminate\Http\Request $request) use ($app){
			$product = new App\Model\Items\VendorSku;
			$v_id = 127;
			$store_id = 233;
			$var = function ($response) use(&$v_id, &$store_id){

				$price = App\Model\Items\VendorSkuDetails::where('sku_code',$response->sku_code)->first();
				$priceArr  = ['v_id' => $v_id, 'store_id' => $store_id, 'item' => $price, 'unit_mrp' => ''];
		        $config = new App\Http\Controllers\CloudPos\CartconfigController;
		        $getPrice = $config->getprice($priceArr);
		    
				$data = [
					'name' => $response->name,
					'barcode' => $response->barcodes[0],
					'barcodes' => $response->barcodes,
					'weight' => $response->purchase_uom_type,
					'category' => $response->cat_name_1,
					'brand' => $response->brand_name,
					'variant' => $response->sku,
					'item_id' => $response->item_id,
					'variant_combi' => $response->sku,
					'mrp' => $getPrice['mrp']
				];
				return (Object)$data;
			};

			dd ($product->search()->select('sku_code','name','barcodes','purchase_uom_type','cat_name_1','brand_name','sku','item_id')->where('name','LIKE','%ro%')->where('barcode','LIKE','%ro%')->mapResponse($var)->paginate() );
			//$product = App\Model\Items\VendorSku::where('sku_code','11247000003')->first();
			//dd($product->store_allocation);
			dd($product->search()->saveAll());
			// dd(  );
			return response()->json(['status' => 'get_product_search', 'message' => $message, 'data' => $productList, 'product_image_link' => product_image_link()], 200);

		});
		$app->group(['prefix' => '/oauth' , 'namespace' => 'Oauth'], function () use ($app) {
			$app->post('/token', 'OauthController@getToken');

		});
		$app->group(['prefix'=> '/client' , 'middleware' => 'oauth' ], function () use ($app) {
			$app->group(['prefix'=> '/advice'], function () use ($app) {
				$app->post('/create', 'AdviseController@adviceCreate');
			});
			
			$app->group(['prefix'=> '/report'], function () use ($app) {
				$app->post('/sales','PathFinder\ReportController@getSalesReport');
			});

			//$app->group(['prefix'=> '/client' , 'middleware' => 'oauth'], function () use ($app) {
			//$app->group(['prefix'=> '/advice'], function () use ($app) {
			//    $app->group(['prefix'=> '/report'], function () use ($app) {
			// 	$app->post('/sales','PathFinder\ReportController@getSalesReport');
			// });

			$app->group(['prefix'=> '/item'], function () use ($app) {
				$app->post('/create', 'ItemController@create');
				$app->post('/create/status', 'ItemController@checkItemCreationStatus');
			});

			$app->group(['prefix'=> '/ad-hoc-grt'], function () use ($app) {
				$app->post('/create', 'GrtController@create');
			});


			$app->group(['prefix'=> '/ad-hoc-grn'], function () use ($app) {
				$app->post('/create', 'GrnController@create');

			});


		});
		$app->get('/grt-receipt/{c_id}/{v_id}/{store_id}/{order_id}', 'GrtController@get_print_recipt');
		$app->get('/sst-receipt/{c_id}/{v_id}/{store_id}/{order_id}', 'GrtController@sst_print_reciept');
		$app->get('/grn-receipt/{c_id}/{v_id}/{store_id}/{order_id}', 'GrtController@grn_print_recipt');

		$app->get('/test', 'CloudPos\CartController@testTax');




		//$app->group(['prefix'=> '/excel-item'], function () use ($app) {
        $app->post('/excel-item-import', 'ItemController@itemImport');

        $app->post('/sudo-session', 'SudoSessionController@sudoSessionClosed');

        $app->post('/item-price', 'CloudPos\CartconfigController@getItemPrice');
        $app->post('/item-exist', 'ItemController@item_exist_check');
        $app->post('/last-inward', 'ItemController@getLastInwardPrice');
        $app->post('/save-last-inward-price', 'ItemController@saveLastInwardPrice');
		// $app->get('/verify-order-for-test', 'TestController@verify_order_for_test');
		// $app->get('/apportion', 'TestController@apportion');
		// $app->get('/bill-response', 'TestController@billResponse');
		// $app->get('/copoun-url', 'TestController@couponResposne');
		// $app->get('/phpinfo', 'TestController@phpinfo');
		// $app->get('/order_summary', 'TestController@orderSummary');
		// $app->post('/get-vendor-settings', 'TestController@get_vendor_settings');
		// $app->post('/update-vendor-settings', 'TestController@update_vendor_settings');
		$app->get('/debug-tax-calculation', 'TestController@taxCalculation');
		$app->get('/email-template', 'TestController@emailTemplate');
		$app->post('/item-supply-price', 'CloudPos\CartconfigController@getItemSupplyPrice');
		$app->post('/get-price-spb', 'SupplyPriceBookController@getPriceFormSPB');
		$app->post('/post-job', 'TestController@postJob');
		$app->get('/inbound-api', 'DataPushApiController@inbound_api');

		$app->post('/invoice-push-status', 'InvoicePushController@invoicePushStatus');

		// $app->get('/data-sync', 'DataPushApiController@dataSync');
		$app->post('/switch-to-online', 'TableSyncController@switchToOnline');
		$app->post('/latest-invoice-id', 'TableSyncController@latestInvoiceId');
		// $app->group(['prefix' => '/data-fetch'], function () use ($app) {
		// 	$app->get('/vmart-data-sync', 'Vmart\DataPushApiController@dataSync');
		// });
		$app->post('/get-settings-console', 'VendorController@get_settings_console');
		$app->get('/store-uniqueid', function () use ($app) {
			$randomletter = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4);
			$store_id = store_uniquneid($randomletter);
			DB::table('testing_table')->insert(['order_id' => $store_id, 'created_at' => date('Y-m-d H:i:s')]);
			return 'Store ID :- ' . $store_id;
		});

		$app->post('/register-mobile', 'AuthController@register_mobile');
		$app->post('/verify-mobile', 'AuthController@verify_mobile');
		$app->post('/setup-pin', 'AuthController@setup_pin');
		$app->post('/register-user-details', 'AuthController@register_user_details');
		$app->post('/login', 'AuthController@login');
		$app->get('/logout', 'AuthController@logout');
		$app->post('/forgot-pin', 'AuthController@forgot_pin');
		$app->post('/forgot-pin-verify', 'AuthController@forgot_pin_verify');
		$app->post('/change-pin', 'AuthController@change_pin');
		$app->post('/send-verification-email', 'AuthController@send_verification_email');
		$app->post('/delete-user', 'AuthController@delete_user');
		$app->post('/retry-jobs', 'AuthController@retryFailedJobs');
		$app->post('/retry-inbound-jobs', 'AuthController@retryInboundJobs');

		$app->post('/get-profile', [
			'middleware' => 'settings',
			'uses'	=> 'CustomerController@profile'
		]);

		$app->post('/store-list', 'StoreController@store_list');
		$app->post('/store-search-list', 'StoreController@store_search_list');
		$app->post('/store-details', 'StoreController@store_details');
		$app->get('/store-qr-code', 'StoreController@store_qr_code');

		$app->post('/qr-store-details', 'AuthController@qr_store_details');
		$app->post('/no-auth-store-list', 'AuthController@no_auth_stote_list');
		$app->post('/no-auth-store-details', 'AuthController@store_details');
		//$app->post('/product-details', 'ProductController@product_details');
		$app->post('/product-details', ['middleware' => 'settings','uses'	=> 'ProductController@product_details']);
		$app->post('/product-search', 'ProductController@product_search');
		//$app->post('/add-to-cart', 'CartController@add_to_cart');
		$app->post('/add-to-cart', ['middleware' => 'settings','uses'	=> 'CartController@add_to_cart']);
		$app->post('/bulk-add-to-cart', 'CartController@bulk_add_to_cart');
		//$app->post('/product-qty-update', 'CartController@product_qty_update');
		$app->post('/product-qty-update', ['middleware' => 'settings','uses'	=> 'CartController@product_qty_update']);
		$app->post('/calculate-promotions', 'CartController@calculatePromotions');
		//$app->post('/cart-details', 'CartController@cart_details');
		$app->post('/cart-details', ['uses'	=> 'CartController@cart_details']);
		$app->post('/apply-employee-discount', 'Spar\CartController@apply_employee_discount');
		$app->post('/remove-employee-discount', 'Spar\CartController@remove_employee_discount');
		$app->post('/order-verify-status', 'Spar\CartController@order_verify_status');
		$app->post('/remove-product-from-cart', 'CartController@remove_product');
		$app->post('/add-remark', 'CartController@add_remark');

		$app->post('/cart-email', 'CloudPos\CartController@orderEmail');
		$app->post('/tax-calculation', 'CloudPos\CartController@inboundTaxCalculation');
		
		// b2b implmenations
		$app->post('/b2b-product-details', 'ProductController@product_b2b_details');
		$app->post('/b2b-add-to-cart', 'CartController@b2b_add_to_cart');
		$app->post('/b2b-cart-detail', 'CartController@b2b_cart_details');
		$app->post('/b2b-create-order', 'CartController@b2b_create_order');
		$app->post('/b2b-order-details', 'CartController@b2b_order_details');
		//cash register 
		$app->post('cash-register/tag-licence', 'CashRegisterTagController@cash_register_tag');
		//linence Details
		$app->post('/device-licence-detail','LicenceDetailController@getDevicesLicenceDetail');
		$app->post('/revoke-licence','LicenceDetailController@revokeLicence');

		// cashmanagement api 

          $app->post('/cash-point-type','CashManagementController@getCashPointTypeList');
          $app->post('/cash-point','CashManagementController@cashPointList');
          $app->post('/cash-point-transfer','CashManagementController@cashPointTransfer');
          $app->post('/current-transactions-list','CashManagementController@getCurrentTransactions');
          $app->post('/update-transaction-status','CashManagementController@updateTransactionStatus');
         $app->post('/cash-transaction-history','CashManagementController@getTrnsastionHistory');
         $app->post('/sales-item-report','ReportController@getSalesItemDetail');
	     $app->post('/print-sales-item','ReportController@salesItemPrint');
	     $app->post('/update-all-pending-transaction-status','CashManagementController@updateAllTransactions');

	     //einvoice
		$app->post('/einvoice-generate','CloudPos\EinvoiceController@callEinvoice');
		$app->get('/einvoice-download','CloudPos\EinvoiceController@downloadEinvoice');



		// agent list vmart

		$app->post('/agent-list', 'AgentController@getAgentList');
		$app->post(
			'/process-to-payment',
			[
				'middleware' => 'settings',
				'uses' =>  'CartController@process_to_payment'
			]
		);

		$app->post(
			'/save-payment',
			[
				'middleware' => 'settings',
				'uses' => 'CartController@payment_details'
			]
		);
		$app->post(
			'/gv-list-for-redeem',
			[
				'middleware' => 'settings',
				'uses' => 'CartController@gift_voucher_list_for_redeem'
			]
		);
		$app->post(
			'/void-bill',
			[
				'middleware' => 'settings',
				'uses' => 'VoidTransactions@voidForSalesInvoice'
			]
		);
		$app->post('/add-to-wishlist', 'WishlistController@add_to_wishlist');
		$app->post('/wishlist-details', 'WishlistController@wishlist_details');
		$app->post('/remove-product-from-wishlist', 'WishlistController@remove_product_from_wishlist');
		$app->post('/my-wishlist', 'WishlistController@wishlist');
		$app->get('/order-qr-code', 'CartController@order_qr_code');
		$app->post('/order-details', 'CartController@order_details');
		$app->post('/get-carry-bags', 'CartController@get_carry_bags');
		$app->post('/save-carry-bags', 'CartController@save_carry_bags');
		$app->get('/order-receipt/{c_id}/{v_id}/{store_id}/{order_id}', 'CartController@order_receipt');
		$app->post('/print-ack', 'CartController@print_ack');
		$app->post('/get-print-receipt', 'CartController@get_print_receipt');
		$app->post('/get-print-receipt-invoice', 'CartController@get_print_receipt_invoice');
		$app->post('/get-duplicate-receipt', 'CartController@get_duplicate_receipt');
		$app->post('/order-pre-verify-guide', 'CartController@order_pre_verify_guide');

		$app->post('/return-order-mail/{c_id}/{v_id}/{store_id}/{order_id}', 'AuthController@returnMail');

		/////// get all unsync invoice /////
		// $app->post('/get-all-unsync-invoice', 'Erp\InvoiceController@getAllUnsyncInvoice');
		
		$app->post(
			'/get-all-unsync-invoice',
			[
				'middleware' => 'oauth',
				'uses' => 'Erp\InvoiceController@getAllUnsyncInvoice'
			]
		);

		$app->get('/receipt/{encoded_data}', 'Spar\ReceiptController@sms_order_receipt');
		$app->post('/send-bill-receipt', 'SmsController@send_bill_receipt');
		$app->post('/send-otp', 'SmsController@send_otp');
		$app->post('/verify-otp', 'SmsController@verify_otp');

		$app->post('/save-rating', 'RatingController@save_rating');

		$app->post('/save-product-rating', 'ProductRatingController@save_rating');
		$app->post('/get-product-rating', 'ProductRatingController@get_rating');
		$app->post('/save-product-review', 'ProductReviewController@save_review');
		$app->post('/get-product-review', 'ProductReviewController@get_review');

		$app->post('/add-address', 'AddressController@add');
		$app->post('/check-address', 'AddressController@check');
		$app->post('/address-list', 'AddressController@lists');
		$app->post('/update-address', 'AddressController@update');
		$app->post('/delete-address', 'AddressController@delete');
		$app->post('/delivery-status', 'CartController@deliveryStatus');
		$app->post('/set-primary-address', 'AddressController@setPrimary');

		$app->post('/get-offers', 'OfferController@get_offers');
		$app->post('/apply-voucher', 'OfferController@apply_voucher');
		$app->post('/remove-voucher', 'OfferController@remove_voucher');
		$app->post('/manual-discount', 'OfferController@manualDiscount');
		$app->post('/manual-discount-list', 'OfferController@manualDiscountList');
		$app->post('/check-override-limit', 'ProductController@checkOverrideLimit');
		$app->post('/check-product-inventory', 'ProductController@checkProductInventory');
		$app->group(['prefix' => '/account-sale'], function () use ($app) {
			$app->post('/', 'CloudPos\AccountsaleController@account_sale');
			$app->post('/debit-purchase-list', 'CloudPos\AccountsaleController@getDebitPurchasedList');
			$app->post('/credit-note-list', 'CloudPos\AccountsaleController@getCreditNoteList');
			$app->post('/pay-balance', 'CloudPos\AccountsaleController@payAccountBalance');
			$app->post('/pay-balance-process', 'CloudPos\AccountsaleController@payAccountBalanceRequest'); // Temp
			$app->post('/pay-balance-approve', 'CloudPos\AccountsaleController@payAccountBalanceApprove'); // Temp
		});


		// My Order

		$app->post('/my-orders', 'ProfileController@my_order');
		$app->post('/b2b-my-orders', 'ProfileController@b2b_my_order');
		$app->post('/email-invoice', 'CartController@orderEmailRecipt'); 

		$app->group(['middleware' => 'auth'], function () use ($app) {

			$app->post('/profile-update', 'AuthController@profile_update');
		});

		$app->post('/send-email-invoice', 'Spar\EmailController@send_invoice');

		$app->group(['prefix' => '/vendor'], function () use ($app) {

			$app->post('/get-vendor-details', 'VendorController@get_vendor_details');
			$app->post('/get-store-data', 'VendorController@get_store_data');

			$app->post('/register-mobile', 'VendorController@register_mobile');
			$app->post('/verify-mobile', 'VendorController@verify_mobile');
			$app->post('/verify-password', 'VendorController@verify_password');
			$app->post('/setup-pin', 'VendorController@setup_pin');
			$app->post('/get-store-details', 'VendorController@get_store_details');
			$app->post('/verify-vendor', 'VendorController@verify_vendor');
			$app->post('/register-vendor-details', 'VendorController@register_vendor_details');

			$app->post('/login', [
				'middleware' => 'settings',
				'uses'	=> 'VendorController@login'
			]);

			$app->get('/logout', 'VendorController@logout');
			$app->post('/forgot-pin', 'VendorController@forgot_pin');
			$app->post('/forgot-pin-verify', 'VendorController@forgot_pin_verify');
			$app->post('/change-pin', 'VendorController@change_pin');
			$app->post('/sync-success', 'TableSyncController@success');
			$app->group(['middleware' => ['auth', 'vendor_m']], function () use ($app) {

				$app->post('/profile-update', 'VendorController@profile_update');
				$app->post('/change-store', 'VendorController@change_store');
				$app->post('/get-profile', 'VendorController@profile');
				$app->post('/order-details', 'VendorController@order_details');
				$app->post('/verify-order', 'VendorController@verify_order');
				$app->post('/scan-for-customer', 'VendorController@scan_for_customer');
				$app->post('/un-tag-customer', 'VendorController@unTagCustomer');
				$app->post('/login-for-customer', [
					'middleware' => 'settings',
					'uses'	=> 'VendorController@login_for_customer'
				]);
				$app->post('/operation-verification', 'VendorController@operation_verification');
				$app->post('/get-settings', 'VendorController@get_settings');

				$app->post('/opening-balance', [ 'middleware' => 'settings', 'uses' => 'VendorSettlementController@opening_balance' ]);
				$app->post('/closing-balance', 'VendorSettlementController@closing_balance');
				$app->post('/force-closed', 'VendorSettlementController@forceClosed');
				$app->post('/print-settlement', 'VendorSettlementController@print_settlement');
				$app->post('/print-settlement-record', 'VendorSettlementController@print_settlement_record');

				$app->post('/price-override', 'PriceController@override');

				$app->post('/table-sync', [
				    'middleware' => 'gzip',
				    'as' => 'data',
				    'uses'=>'TableSyncController@sync'
				]);
				
                $app->post('/out-bound-table-sync', 'OutBoundTableSyncController@outBoundSync');
				$app->post('/get-catalog', 'CatalogController@getCatalog');
				$app->post('/save-catalog', 'CatalogController@saveCatalog');

				$app->post('/order-recall', 'OrderController@recall');
				$app->post('/process-lay-by', 'OrderController@processLayBy');
			});

			$app->post('/get-item', 'BillingOperationController@getItem');
			$app->post('/save-item', 'BillingOperationController@saveItem');
			$app->post('/save-item-details', 'BillingOperationController@saveOtherDetails');
			$app->post('/print-item-receipt', 'BillingOperationController@printReceipt');

			$app->post('/verify-order-by-guard', 'VendorController@verify_order_by_guard');

			$app->post('/get-salesman', 'VendorUserController@getSalesMan');
			$app->post('/tag-salesman', 'VendorUserController@tagSalesMan');
			$app->post('/untag-salesman', 'VendorUserController@untagSalesMan');


			//$app->post('/get-advice' , 'AdviseController@getAdvices');
			//$app->post('/advice-list' , 'AdviseController@adviceList');


			$app->post('/advice-list', 'AdviseController@getAdvices');
			$app->post('/advice-details', 'AdviseController@adviceList');
			$app->post('/advice-det', 'AdviseController@adviceDetails');
			$app->post('/advice-create', 'AdviseController@adviceCreate');
			$app->post('/create-advice-new', 'AdviseController@createAdviceNew');
		
			$app->post('/get-grn', 'GrnController@getGrn');
			$app->post('/grn-list', 'GrnController@grnList');
			$app->post('/create-grn', 'GrnController@createGrn');
			$app->post('/create-adhoc', 'GrnController@createAdhoc');
			$app->post('/grn-details', 'GrnController@grnDetails');
			$app->post('/grn-stock', 'StockController@stockAdj');
			$app->get('/grn-print', 'GrnController@printGrn');
			$app->post('/item-detail', 'ItemController@getItem');
			$app->post('/stock-in', 'StockController@stockIn');
			$app->post('/stock-out', 'StockController@stockOut');
			$app->post('/stock-transfer', 'StockController@stockTransfer');
			$app->post('/create-grn-new', 'GrnController@newCreateGrn');
			$app->post('/stock-adj', 'StockController@stockAdj');
			$app->post('/create-opening-stock', 'OpeningStockController@createOpeningStock');
		});

		$app->group(['prefix' => '/shopping-list'], function () use ($app) {

			$app->post('/list', 'ShoppingListController@list');
			$app->post('/update-list', 'ShoppingListController@update_list');
			$app->post('/create', 'ShoppingListController@create');
			$app->post('/product-qty-update', 'ShoppingListController@product_qty_update');
			$app->post('/remove-product', 'ShoppingListController@remove_product');
		});
		$app->group(['prefix' => '/edc'], function () use ($app) {

			$app->post('/add', 'EdcDeviceController@add');
			$app->post('/view', 'EdcDeviceController@view');
			$app->post('/update-serialnumber', 'EdcDeviceController@updateSerialnumber');
			$app->post('/update-udid', 'EdcDeviceController@updateUdid');
		});

		$app->group(['prefix' => '/location'], function () use ($app) {
			$app->post('/get-country', 'LocationController@getCountry');
			$app->post('/get-city', 'LocationController@getCities');
			$app->post('/get-state', 'LocationController@getState');
		});

		$app->group(['prefix' => '/scan'], function () use ($app) {

			$app->post('/list', 'ScanController@list');
			$app->post('/create', 'ScanController@create');
			$app->post('/details', 'ScanController@details');
		});

		$app->group(['prefix' => '/offer'], function () use ($app) {

			$app->post('/list', 'OfferController@list');
			$app->post('/popup-offer-view', 'OfferController@popup_offer_viewed');
		});

		$app->group(['prefix' => '/partner'], function () use ($app) {

			$app->post('/available-offer', 'PartnerOfferController@available_offer');
			$app->post('/apply-offer', 'PartnerOfferController@apply');
			$app->post('/remove-offer', 'PartnerOfferController@remove');
		});

		$app->group(['prefix' => '/map'], function () use ($app) {

			$app->post('/address-by-lat-long', 'MapLocationController@addressBylatLong');
		});

		$app->group(['prefix' => '/spar'], function () use ($app) {

			$app->post('/create-offers', 'Spar\DatabaseOfferController@create_offers');
			$app->post('/create-promotions', 'Spar\DatabasePromotionController@index');
		});

		$app->group(['prefix' => '/return'], function () use ($app) {

			$app->post('/request', 'ReturnController@return_request');

			$app->post('/approve', [
				'middleware' => 'settings',
				'uses' => 'ReturnController@approve'
			]);

			$app->post('/update', 'ReturnController@update');
			$app->post('/delete', 'ReturnController@delete');

			$app->post('/authorized', 'ReturnController@authorized');
			$app->post('/get-order', 'ReturnController@get_order');
		});

		$app->group(['prefix' => '/cinepolis'], function () use ($app) {
			$app->get('/fetch-data', 'CloudPos\DataFetchingApi@dataFetchRequest');
			$app->post('/create-product', 'CloudPos\DataFetchingApi@createProduct');
			$app->post('/kds-orders', 'Cinepolis\KdsController@index');
			$app->post('/kds-od', 'Cinepolis\KdsController@orderDetails');
			$app->post('/kds-sms', 'Cinepolis\KdsController@sendSms');
		});


		$app->post('/rt-log', 'Spar\CartController@rt_log');

		$app->post('/get-feedback-questions', 'CustomerFeedbackController@getFeedbackQuestions');
		$app->post('/submit-feedback-answer', 'CustomerFeedbackController@submitAnswer');

		$app->group(['prefix' => 'loyalty'], function () use ($app) {

			$app->post('/get-points', [
				'middleware' => 'settings',
				'uses'	=> 'LoyaltyController@getPoints'
			]);
		});


		$app->group(['prefix' => 'sync'], function () use ($app) {
			$app->group(['prefix' => 'tables'], function () use ($app) {
				$app->post('all', 'Sync\TableController@all');
			});
		});




		$app->post('/product-search', 'ProductController@product_search');
		$app->post('/product-field-search', 'ProductSearchController@fieldSearch');

		$app->post('/sync-item-list', 'TableSyncController@syncItemList');

		// $app->post('/sync-item-list', 'TableSyncController@syncItemList');
		$app->post('/edc-device-list', 'EdcDeviceController@getAllEdcDeviceList');
		$app->post('/sudosession', 'SudoSessionController@sudoSessionClosed');
		$app->post('/sudo-cashsummary', 'SudoSessionController@cashPointSummaryupdate');

		$app->group(['prefix' => '/payment-integration'], function () use ($app) {
			$app->post('/pinelab-upload-bill', 'PaymentIntegration\PineLabsController@uploadBill');
			$app->post('/pinelab-get-status', 'PaymentIntegration\PineLabsController@GetStatus');
			$app->post('/pinelab-cancel-transaction', 'PaymentIntegration\PineLabsController@CancelTransaction');
			/*** @author: Shweta T * Date: 18/06/2020 */
			$app->post('/charge-request', 'PaymentIntegration\PhonePeController@charge');  
			$app->post('/payment-status', 'PaymentIntegration\PhonePeController@status'); 
			$app->post('/cancel-payment', 'PaymentIntegration\PhonePeController@cancel'); 
			$app->post('/remind-payment', 'PaymentIntegration\PhonePeController@remind'); 
			$app->post('/refund-payment', 'PaymentIntegration\PhonePeController@refund');
			$app->post('/callback', 'PaymentIntegration\PhonePeController@callback'); 
			$app->get('/get-transaction', 'PaymentIntegration\PhonePeController@gv'); 
			$app->post('/window-close', 'PaymentIntegration\PhonePeController@windowClose'); 
			
			
		});
		//gv api all
		$app->group(['prefix' => '/gv'], function () use ($app) {
			$app->post('/getGvGroupList', 'GiftVoucher\GiftVoucherController@getGvGroupList');
			$app->get('/getVoucherList', 'GiftVoucher\GiftVoucherController@getVoucherList');
			$app->post('/getGroupDetails', 'GiftVoucher\GiftVoucherController@getGroupDetails');
			$app->post('/getRangeList', 'GiftVoucher\GiftVoucherController@getRangeList');
			$app->post('/cart-update', 'GiftVoucher\GiftVoucherCartController@cartUpdate');
			$app->post('/process-to-checkout', 'GiftVoucher\GiftVoucherOrderController@processToCheckout');
			$app->post('/cart-details', 'GiftVoucher\GiftVoucherCartController@getCartDetails');
			$app->post('/save-payment', 'GiftVoucher\GiftVoucherPaymentsController@savePayment');
			$app->post('/add-user', 'GiftVoucher\GiftVoucherCartController@addCustomerForGv');
			$app->post('/cal-gift-value', 'GiftVoucher\GiftVoucherCartController@calculateGiftValue');
		});

		$app->group(['prefix' => '/amazon'], function () use ($app) {
		
			$app->get('/get-access-token', 'AmazonConnectors\AuthenticationController@getAccessToken');
			$app->get('/get-security-token', 'AmazonConnectors\AuthenticationController@getSecurityToken');
			
			$app->get('/get-signature', 'AmazonConnectors\AuthenticationController@getSignature');
			$app->get('/get-inventory', 'AmazonConnectors\InventoryController@getInventory');
			$app->get('/get-inventory2', 'AmazonConnectors\InventoryController@getInventory2');
			$app->get('/update-inventory', 'AmazonConnectors\InventoryController@updateInventory');
			$app->get('/update-prices', 'AmazonConnectors\InventoryController@updatePrices');
			$app->get('/create-order', 'AmazonConnectors\OrderController@createOrder');
			$app->get('/get-order', 'AmazonConnectors\OrderController@getOrder');
			$app->get('/list-order', 'AmazonConnectors\OrderController@listOrder');
			$app->get('/confirm-order', 'AmazonConnectors\OrderController@confirmOrder');
			$app->post('/create-package', 'AmazonConnectors\OrderController@createPackages');
			$app->post('/update-package', 'AmazonConnectors\OrderController@updatePackages');
			$app->get('/retrieve-pickup-slot', 'AmazonConnectors\OrderController@retrievePickupSlot');
			$app->get('/generate-invoice', 'AmazonConnectors\OrderController@generateInvoice');
			$app->get('/generate-shiplable', 'AmazonConnectors\OrderController@generateShipLabel');
			$app->get('/ship-order', 'AmazonConnectors\OrderController@shipOrder');
			$app->post('/cancel-order', 'AmazonConnectors\OrderController@cancelOrder');
			$app->get('/retrieve-invoice', 'AmazonConnectors\OrderController@retrieveInvoice');
			
		});


		
		//$app->get('/apilog_job1', 'AmazonConnectors\AuthenticationController@apilog_job');
		$app->get('/apilog_job2', function () {
		    echo "----------";
		    $exitCode = Artisan::call('queue:work');
			echo $exitCode;
		});
				 
		$app->group(['prefix' => '/customer'], function () use ($app) {
			$app->post('/list', 'CustomerController@getCustomerList');
			$app->post('/group-list', 'CustomerController@getCustomerGroupList');
			$app->post('/update-group-list', 'CustomerController@updateCustomerGroupList');

			$app->group(['prefix' => '/gst'], function () use ($app) {
				$app->post('/create', 'TaxController@create');
				$app->post('/list', 'TaxController@list');
				$app->post('/update', 'TaxController@update');
				$app->post('/remove', 'TaxController@remove');
			});
			
		});

		$app->group(['prefix' => '/audit'], function () use ($app) {
			$app->post('/list', 'AuditController@list');
			$app->post('/stockpoint-wise-list', 'AuditController@stockpointWiseList');
			$app->post('/product-checker', 'AuditController@productChecker');
			$app->post('/save-count', 'AuditController@saveCount');
			$app->post('/get-stockpoint-products', 'AuditController@getStockpointProducts');
			$app->post('/complete-audit', 'AuditController@completeAudit');
			$app->post('/reconsile-checker', 'AuditController@reconsileChecker');
		});

		$app->group(['prefix' => '/oms'], function () use ($app) {
			$app->post('/inventory-check', 'OrderController@orderInventoryCheck');
			$app->post('/order-creation', 'OrderController@orderCreation');
			$app->post('/order-list', 'OrderController@oredrList');
			$app->post('/cancel-order', 'OrderController@cancelOrder');
			$app->post('/get-order-details', 'OrderController@orderConfirmDetails');
			$app->post('/get-product-details', 'OrderController@getProductDetails');
			$app->post('/confirm-order', 'OrderController@confirmOrder');
			$app->post('/pack-order-details', 'OrderController@getOrderPackerProductDetails');
			$app->post('/order-pack', 'OrderController@orderPack');
			$app->post('/generate-invoice', 'OrderController@generateInvoice');
			$app->post('/mark-fulfilled', 'OrderController@markAsFulfilled');
			$app->post('/get-product-details-by-inventory', 'OrderController@getProductDetailsByInventory');
			$app->post('/order-receipt', 'OrderController@getOrderPrintReceipt');
		});

		

	});
}
