<?php

namespace App\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\Authlog' => [
            'App\Listeners\Userlogin',
        ],

        'App\Events\EdcLog' => [
            'App\Listeners\EdcMappingReport',
        ],

        'App\Events\Loyalty' => [
            'App\Listeners\LoyaltySearchCustomer',
            'App\Listeners\LoyaltyBillPush',
        ],

        'App\Events\DataFetchCurl' => [
            'App\Listeners\DataRetrieve',
        ],
        'App\Events\OrderPush' => [
            'App\Listeners\PushOrderCinema',
        ],

        'App\Events\InvoiceCreated' => [
            'App\Listeners\InvoicePush',
        ],

        'App\Events\GrnCreated' => [
            'App\Listeners\GrnPush',
        ],

        'App\Events\DeviceStatusChange' => [//Api Status Change
            'App\Listeners\OperationStarted',
        ],
         
         'App\Events\CashPointTransfer' => [
            'App\Listeners\PettyCashBillPush',
        ],
        
        'App\Events\StockTransfer' => [
            'App\Listeners\StockPointTransfer',
        ],
        'App\Events\StockAdjust' => [
            'App\Listeners\PosMisPush',
        ],

        'App\Events\CreateOpeningStock' => [
            'App\Listeners\OpeningStockPush',
        ],
        'App\Events\DaySettlementCreated' => [
            'App\Listeners\DaySettlementPush',
        ],
        'App\Events\PacketCreated' => [
            'App\Listeners\PacketPush',
        ],
        'App\Events\PacketVoid' => [
            'App\Listeners\PacketVoidPush',
        ],
        'App\Events\SaleItemReport' => [
            'App\Listeners\SaleItemLevelReport',
        ],
        'App\Events\GrtCreated' => [
            'App\Listeners\GrtPush',
        ],
        'App\Events\StoreTransferCreated' => [
            'App\Listeners\StoreTransferPush',
        ],
        'App\Events\StockAduit' => [
            'App\Listeners\StockAduitPush',
        ],
        'App\Events\DepositeRefund' => [
            'App\Listeners\DepositeRefundPush',
        ],
        'Illuminate\Mail\Events\MessageSending' => [
            'App\Listeners\LogSendingMessage',
        ],
        'Illuminate\Mail\Events\MessageSent' => [
            'App\Listeners\LogSentMessage',
        ],
    ];
}
