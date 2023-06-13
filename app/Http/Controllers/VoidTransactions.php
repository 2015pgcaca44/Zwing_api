<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Traits\VendorFactoryTrait;
use App\Address;
use App\Cart;
use App\CartOffers;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\VendorSettingController;
use App\Http\CustomClasses\PrintInvoice;

use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;

use Barryvdh\DomPDF\Facade as PDF;

use App\Order;
use App\Payment;
use App\Store;
use App\User;
use App\Reason;
use App\VendorImage;
use Auth;
use DB;
use Log;


use Razorpay\Api\Api;
use App\Invoice;
use App\OrderDetails;
use App\OrderItemDetails;
use App\CartDetails;
use App\InvoiceDetails;
use App\InvoiceItemDetails;

use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockTransactions;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;
use App\Model\Stock\Serial;
use App\Model\Stock\SerialSold;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\VendorItem;

use Event;
use App\Events\Loyalty;
use App\Events\InvoiceCreated;

use App\LoyaltyBill;
use App\Http\Controllers\LoyaltyController;
use App\Organisation;
use App\SyncReports;
use App\OrderExtra;
use App\Carry;
use App\Vendor;
use App\Deposit;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Http\Controllers\CloudPos\AccountsaleController;
use App\Http\Controllers\Ginesys\PromotionController;
use App\Http\Controllers\Ginesys\CartController as GiniCartController;
use App\OrderDiscount;
use App\CartDiscount;
use App\VendorSetting;
use App\Events\OrderPush;
use App\Vendor\VendorRoleUserMapping;
use App\OrderOffers;
use App\InvoiceOffers;
use App\SettlementSession;
use App\CashRegister;
use App\CashTransactionLog;
use App\CashPointSummary;
use App\CashPoint;
use App\OperationVerificationLog;
use App\Http\Controllers\CloudPos\ReturnController;
use App\Events\SaleItemReport;
use App\Model\PriceOverRideLog;
use App\PhonepeTransactions;
use App\DepRfdTrans;    
use App\Http\Controllers\CashManagementController;

class VoidTransactions extends Controller
{
    use VendorFactoryTrait;

	public function __construct()
	{
		
		$this->cartconfig  = new CartconfigController;
	}

	public function voidForSalesInvoice(Request $request)
	{
		


	}

	public function voidForReturnInvoice(Request $request)
	{



	}
}
