<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Invoice;
use App\Model\Grn\Grn;
use App\GrtHeader;
use App\StockPointTransfer;
use App\Model\Stock\Adjustment;
use App\Packet;
use App\Model\Audit\AuditPlanAllocation;
use App\Organisation;

class IntegrationSyncStatus extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $v_id;
    public function __construct($v_id)
    {
        $this->v_id = $v_id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        JobdynamicConnection($this->v_id);
        $orgDetails = Organisation::find($this->v_id);
        $date = '2021-02-24';
        $data = [];
        $data[] = array_merge([ 'name' => 'Sales Invoices', 'code' => 'sale' ], $this->fetchSyncData('sale', $date));
        $data[] = array_merge([ 'name' => 'Return Invoices', 'code' => 'return' ], $this->fetchSyncData('return', $date));
        $data[] = array_merge([ 'name' => 'Goods Receipt', 'code' => 'grn' ], $this->fetchSyncData('grn', $date));
        $data[] = array_merge([ 'name' => 'Goods Return', 'code' => 'grt' ], $this->fetchSyncData('grt', $date));
        $data[] = array_merge([ 'name' => 'Stockpoint Transfers', 'code' => 'spt' ], $this->fetchSyncData('spt', $date));
        $data[] = array_merge([ 'name' => 'Stock Adjustments', 'code' => 'adj' ], $this->fetchSyncData('adj', $date));
        $data[] = array_merge([ 'name' => 'Packets', 'code' => 'packet' ], $this->fetchSyncData('packet', $date));
        $data[] = array_merge([ 'name' => 'Stock Audit', 'code' => 'audit' ], $this->fetchSyncData('audit', $date));
        return $this->subject('Sync Summary - '.date('d F Y', strtotime($date)))->view('emails.integration-sync', [ 'data' => $data, 'org_name' => @$orgDetails->name, 'date' => $date, 'report_name' => 'Daily Sync Summary', 'v_id' => $this->v_id ]);
    }

    public function fetchSyncData($type, $date, $store_id = 0)
    {
        JobdynamicConnection($this->v_id);
        $whereClause = [ 'v_id' => $this->v_id ];
        // $whereClause = [ 'v_id' => $this->v_id, 'store_id' => $store_id ];
        $syncStats = [ 'total' => 0, 'done' => 0, 'class' => 'text-grey', 'fail' => 0, 'not_synced' => 0 ];
        if($type == 'sale') {
            $invoiceList = Invoice::where($whereClause)->where('date', $date)->where('transaction_type', 'sales');
            if($invoiceList->count() > 0) {
                $invoiceListFail = clone $invoiceList;
                $syncStats['total'] = $invoiceList->count();
                $syncStats['done'] = $invoiceList->where('sync_status', '1')->count();
                $syncStats['fail'] = $invoiceListFail->where('sync_status', '2')->count();
            }
        }
        if($type == 'return') {
            $invoiceList = Invoice::where($whereClause)->where('date', $date)->where('transaction_type', 'return');
            if($invoiceList->count() > 0) {
                $invoiceListFail = clone $invoiceList;
                $syncStats['total'] = $invoiceList->count();
                $syncStats['done'] = $invoiceList->where('sync_status', '1')->count();
                $syncStats['fail'] = $invoiceListFail->where('sync_status', '2')->count();
            }
        }
        if($type == 'grn') {
            $grnList = Grn::where($whereClause)->whereDate('created_at', '=', $date)->where('status', 'posted');
            if($grnList->count() > 0) {
                $grnListFail = clone $grnList;
                $syncStats['total'] = $grnList->count();
                $syncStats['done'] = $grnList->where('sync_status', '1')->count();
                $syncStats['fail'] = $grnListFail->where('sync_status', '2')->count();
            }
        }
        if($type == 'grt') {
            $grtList = GrtHeader::where([ 'v_id' => $this->v_id, 'src_store_id' => $store_id, 'status' => 'POST' ])->whereDate('created_at', '=', $date)->whereNotNull('grt_no');
            if($grtList->count() > 0) {
                $grtListFail = clone $grtList;
                $syncStats['total'] = $grtList->count();
                $syncStats['done'] = $grtList->where('sync_status', '1')->count();
                $syncStats['fail'] = $grtListFail->where('sync_status', '2')->count();
            }
        }
        if($type == 'spt') {
            $stockTransfer = StockPointTransfer::where($whereClause)->whereDate('created_at', '=', $date)->where('status', 'POST')->whereNotNull('doc_no');
            if($stockTransfer->count() > 0) {
                $stockTransferFail = clone $stockTransfer;
                $syncStats['total'] = $stockTransfer->count();
                $syncStats['done'] = $stockTransfer->where('sync_status', '1')->count();
                $syncStats['fail'] = $stockTransferFail->where('sync_status', '2')->count();
            }
        }
        if($type == 'adj') {
            $adjustmentList = Adjustment::where($whereClause)->whereDate('created_at', '=', $date)->where('is_post', '1')->whereNotNull('doc_no');
            if($adjustmentList->count() > 0) {
                $adjustmentListFail = clone $adjustmentList;
                $syncStats['total'] = $adjustmentList->count();
                $syncStats['done'] = $adjustmentList->where('sync_status', '1')->count();
                $syncStats['fail'] = $adjustmentListFail->where('sync_status', '2')->count();
            }
        }
        if($type == 'packet') {
            $packetList = Packet::where($whereClause)->whereDate('created_at', '=', $date);
            if($packetList->count() > 0) {
                $packetListFail = clone $packetList;
                $syncStats['total'] = $packetList->count();
                $syncStats['done'] = $packetList->where('sync_status', '1')->count();
                $syncStats['fail'] = $packetListFail->where('sync_status', '2')->count();
            }
        }
        if($type == 'audit') {
            $stockAuditList = AuditPlanAllocation::where($whereClause)->whereDate('created_at', '=', $date)->where('status', 'C');
            if($stockAuditList->count() > 0) {
                $stockAuditListFail = clone $stockAuditList;
                $syncStats['total'] = $stockAuditList->count();
                $syncStats['done'] = $stockAuditList->where('sync_status', '1')->count();
                $syncStats['fail'] = $stockAuditListFail->where('sync_status', '2')->count();
            }
        }
        if($syncStats['total'] == $syncStats['done'] && $syncStats['total'] != 0) {
          $syncStats['class'] = 'text-success';
        }
        if($syncStats['total'] > $syncStats['done']) {
          $syncStats['class'] = 'text-danger';
        }
        $syncStats['not_synced'] = $syncStats['total'] - $syncStats['done'] - $syncStats['fail'];
        return $syncStats;
    }
}
