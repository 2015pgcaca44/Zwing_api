<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
// use Illuminate\Queue\Events\JobProcessed;
// use Illuminate\Queue\Events\JobProcessing;
// use Illuminate\Support\Facades\Queue;
use Queue;
// use Illuminate\Queue\Events\JobFailed;
use Log;
use App\QueueMonitor;
use App\FailedSyncReports;
use App\Model\Grn\Grn;
use App\Model\OutboundApi;
use App\Invoice;
use App\StockPointTransfer;
use App\Packet;
use App\GrtHeader;
use App\Model\Stock\Adjustment;
use App\CashTransaction;
use App\StoreExpense;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */

    public const MAX_BYTES_LONGTEXT = 4294967295;

    public const MAX_BYTES_TEXT = 65535;

    public function register()
    {
        //
    }

    public function boot()
    {
    	// Queue::before(function (JobProcessing $event) {
     //        // $event->connectionName
     //        // $event->job
     //        // $event->job->payload()
     //        Log::info('Before Queue', [$event]);
     //    });

        Queue::after(function ($event) {
            // $event->connectionName
            // $event->job
            // $event->job->payload()
            // $decodeJob = json_decode($event->job->payload());
            // Log::info('After Queue', [$decodeJob->displayName]);
            QueueMonitor::where('job_id', $event->job->getJobId())->update([ 'status' => 'Success' ]);
        });

        Queue::failing(function ($event) {
            // $event->connectionName
            // $event->job
            // $event->job->payload()
            $monitorCreate = [ 'job_id' => $event->job->getJobId(), 'name' => $event->job->resolveName(), 'queue' => $event->job->getQueue(), 'attempt' => $event->job->attempts(), 'exception' => mb_strcut((string) $event->exception, 0, self::MAX_BYTES_LONGTEXT), 'exception_class' => get_class($event->exception), 'exception_message' => mb_strcut($event->exception->getMessage(), 0, self::MAX_BYTES_TEXT), 'data' => json_encode($event->job->payload()), 'status' => 'Fail', 'platform' => 'API' ];
            $decodeJob = json_encode($event->job->payload());
            $getVendorId = getStringBetween($decodeJob, '<ZWINGV>', '<EZWINGV>');
            if($getVendorId) {
                $monitorCreate['v_id'] = $getVendorId;
            }
            $getStoreId = getStringBetween($decodeJob, '<ZWINGSO>', '<EZWINGSO>');
            if($getStoreId) {
                $monitorCreate['store_id'] = $getStoreId;
            }

            $grtTransactionId = getStringBetween($decodeJob, '<ZWINGTRAN>', '<EZWINGTRAN>');
            if($grtTransactionId) {
                $monitorCreate['transaction_id'] = $grtTransactionId;
            }
            $getEventType = getStringBetween($decodeJob, '<ZWINGTYPE>', '<EZWINGTYPE>');
            $queueExists = QueueMonitor::where('job_id', $event->job->getJobId())->whereNull('parent_id')->first();
            if(empty($queueExists)) {
                QueueMonitor::create($monitorCreate);
            } else {
                $queueExists->status = 'Fail';
                $queueExists->save();
                $monitorCreate['status'] = 'Fail';
                $monitorCreate['parent_id'] = $queueExists->id;
                QueueMonitor::create($monitorCreate);
            }
            $decodeJob = $event->job->payload();
            Log::info('Error Queue', [json_encode($monitorCreate)]);

            // Failed Sync Report

            if(!empty($getVendorId) && !empty($getStoreId) && !empty($grtTransactionId)) {
                $queueData = [ 'Invoice_Push' => 'App\Listeners\InvoicePush', 'grt_Push' => 'App\Listeners\GrtPush', 'Packet_Push' => 'App\Listeners\PacketPush', 'Petty_Cash_Push' => 'App\Listeners\PettyCashBillPush', 'Stock_Aduit_Push' => 'App\Listeners\StockAduitPush', 'Item_Stock_Transfer' => 'App\Listeners\StockPointTransfer', 'Pos_Mis_Push' => 'App\Listeners\PosMisPush', 'Grn_Push' => 'App\Listeners\GrnPush', 'Day_Settlement_Push' => 'App\Listeners\DaySettlementPush' ];
                if(in_array($event->job->resolveName(), $queueData)) {
                    JobdynamicConnection($getVendorId);
                    $queueType = array_search($event->job->resolveName(), $queueData);
                    $queueTypeSlugRemove = str_replace("_", " ", $queueType);
                    $docNo = '';
                    $createdAt = date('Y-m-d H:i:s');
                    $response = mb_strcut($event->exception->getMessage(), 0, self::MAX_BYTES_TEXT);
                    if($queueType == 'Grn_Push') {
                        $checkGrn = Grn::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->first();
                        if(!empty($checkGrn)) {
                            $docNo = $checkGrn->grn_no;
                            $createdAt = $checkGrn->created_at;
                        }
                    }
                    if($queueType == 'Invoice_Push') {
                        $checkInvoice = Invoice::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->first();
                        if(!empty($checkInvoice)) {
                            $docNo = $checkInvoice->invoice_id;
                            $createdAt = $checkInvoice->created_at;
                        }
                    }
                    if($queueType == 'Item_Stock_Transfer') {
                        $checkPoint = StockPointTransfer::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->first();
                        if(!empty($checkPoint)) {
                            $docNo = $checkPoint->doc_no;
                            $createdAt = $checkPoint->created_at;
                        }
                    }
                    if($queueType == 'Packet_Push') {
                        $checkPacket = Packet::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->first();
                        if(!empty($checkPacket)) {
                            $docNo = $checkPacket->packet_code;
                            $createdAt = $checkPacket->created_at;
                        }
                    }
                    if($queueType == 'grt_Push') {
                        $checkGrt = GrtHeader::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->first();
                        if(!empty($checkGrt)) {
                            $docNo = $checkGrt->grt_no;
                            $createdAt = $checkGrt->created_at;
                        }
                    }
                    if($queueType == 'Pos_Mis_Push') {
                        $checkAdj = Adjustment::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->first();
                        if(!empty($checkAdj)) {
                            $docNo = $checkAdj->doc_no;
                            $createdAt = $checkAdj->created_at;
                        }
                    }
                    if($queueType == 'Petty_Cash_Push') {
                        if($getEventType) {
                            if($getEventType == 1) {
                                $checkCash = CashTransaction::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->first();
                                if(!empty($checkCash)) {
                                    $docNo = $checkCash->doc_no;
                                    $createdAt = $checkCash->created_at;
                                }
                            }
                            if($getEventType == 2) {
                                $checkExpense = StoreExpense::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->first();
                                if(!empty($checkExpense)) {
                                    $docNo = $checkExpense->doc_no;
                                    $createdAt = $checkExpense->created_at;
                                }
                            }
                        }
                    }
                    $checkFailedSync = FailedSyncReports::withTrashed()->where([ 'v_id' => $getVendorId, 'store_id' => $getStoreId, 'transaction_id' => $grtTransactionId, 'transaction_type_slug' => $queueType ])->first();
                    $outBoundEntry = OutboundApi::where([ 'v_id' => (int)$getVendorId, 'store_id' => (int)$getStoreId, 'for_transaction' => $queueTypeSlugRemove, 'transaction_id' => (int)$grtTransactionId ])->latest()->first(); 
                    if(!empty($outBoundEntry)) {
                        if(empty($outBoundEntry->api_response)) {
                            $responseArray = explode('\n', $outBoundEntry->error_before_call);
                            // Log::info('Find Queue', [$responseArray[0]]);
                            if(is_array($responseArray)) {
                                $response = $responseArray[0];
                            } else {
                                $response = $outBoundEntry->error_before_call;
                            }
                        } else { 
                            $response = $outBoundEntry->api_response;
                        }
                    }
                    if(empty($checkFailedSync)) {
                        FailedSyncReports::create([ 'v_id' => $getVendorId, 'store_id' => $getStoreId, 'job_id' => $event->job->getJobId(), 'doc_no' => $docNo, 'transaction_id' => $grtTransactionId, 'transaction_type_slug' => $queueType, 'transaction_type' => $queueTypeSlugRemove, 'response' => $response, 'date' => $createdAt ]);
                    } else {
                        $checkFailedSync->response = $response;
                        $checkFailedSync->date = $createdAt;
                        $checkFailedSync->job_id = $event->job->getJobId();
                        $checkFailedSync->doc_no = $docNo;
                        $checkFailedSync->save();
                    }

                    // Status Checker
                    if(strpos($response, "already exist for this")) {
                        if($queueType == 'Grn_Push') {
                            Grn::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->update([ 'sync_status' => '1' ]);
                        }
                        if($queueType == 'Invoice_Push') {
                            Invoice::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->update([ 'sync_status' => '1' ]);
                        }
                        if($queueType == 'Item_Stock_Transfer') {
                            StockPointTransfer::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->update([ 'sync_status' => '1' ]);
                        }
                        if($queueType == 'Packet_Push') {
                            Packet::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->update([ 'sync_status' => '1' ]);
                        }
                        if($queueType == 'grt_Push') {
                            GrtHeader::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->update([ 'sync_status' => '1' ]);
                        }
                        if($queueType == 'Pos_Mis_Push') {
                            Adjustment::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->update([ 'sync_status' => '1' ]);
                        }
                        if($queueType == 'Petty_Cash_Push') {
                            if($getEventType) {
                                if($getEventType == 1) {
                                    CashTransaction::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->update([ 'sync_status' => '1' ]);
                                }
                                if($getEventType == 2) {
                                    StoreExpense::where([ 'v_id' => $getVendorId, 'id' => $grtTransactionId ])->update([ 'sync_status' => '1' ]);
                                }
                            }
                        }
                        FailedSyncReports::where([ 'v_id' => $getVendorId, 'store_id' => $getStoreId, 'doc_no' => $docNo, 'transaction_id' => $grtTransactionId, 'transaction_type_slug' => $queueType ])->delete();
                        if(!empty($outBoundEntry)) {
                            $outBoundEntry->response_status_code = 201;
                            $outBoundEntry->save();
                        }
                    }

                    // Log::info('Find Queue', [array_search($event->job->resolveName(), $queueData)]);
                }
            }

        });
    }
}
