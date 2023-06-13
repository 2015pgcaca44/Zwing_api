<?php

namespace App\Model\Grn;

use Illuminate\Database\Eloquent\Model;
use App\Model\Grn\GrnList;
use DB;
use App\Model\Grn\AdviseList;

class Advise extends Model
{
  
 protected $table = 'advice';

 protected $fillable = ['v_id', 'store_id', 'destination_short_code', 'source_store_code', 'creation_mode', 'vu_id', 'advice_no', 'client_advice_no','client_advice_id', 'no_of_packets', 'qty', 'subtotal', 'discount', 'tax', 'tax_details', 'charge_details', 'charge', 'total', 'type', 'status', 'supplier_id', 'advice_type', 'origin_from', 'advice_created_date', 'against_id', 'against_created_date', 'is_received'];


 public function grn()
 {
   return $this->hasOne('App\Model\Grn\Grn','advice_id','id');
 }

 public function grnlist()
 {
   return $this->hasMany('App\Model\Grn\Grn','advice_id','id');
 }

 public function supplier()
 {
   return $this->hasOne('App\Model\Supplier\Supplier', 'id', 'supplier_id');
 }

 public function store(){
      return $this->hasOne('App\Store','short_code','destination_short_code');
   }

 public function advicelist()
 {
   return $this->hasMany('App\Model\Grn\AdviseList', 'advice_id');
 }

 public function getAdviceProductDetailsAttribute() 
 {
  $data = $serial = $batch = [];
  // $grnList = $this->grnlist;
  if(count($this->grnlist) > 0) {
    $finalItemList = $this->advicelist->filter(function($item) {
      $grnList = GrnList::whereIn('grn_id', $this->grnlist->pluck('id'))->get()->pluck('item_no');
      // dd($grnList->contains($item->item_no));
      if(!$grnList->contains($item->item_no)) {
        return $item;
      }
    });
    $this->advicelist = $finalItemList;
  }
  // dd($this->advicelist->count());
  foreach ($this->advicelist as $key => $value) {
    $is_batch = $is_serial = $received_qty = $damage_qty = $lost_qty = 0;
    $remaining_qty = $value->qty;
    $status     = '';
    $itemInfo = $value->Items->where('v_id', $this->v_id)->where('barcode', $value->item_no)->first();
    if(!empty($itemInfo) ){
      $is_batch = $itemInfo->vendorItem->has_batch;
      $is_serial= $itemInfo->vendorItem->has_serial;
      if($itemInfo->vendorItem->has_batch == 1){
        $status = "Batch";
      }if($itemInfo->vendorItem->has_serial == 1){
        $status = "Serial";
      }if($itemInfo->vendorItem->has_batch == 1 && $itemInfo->vendorItem->has_serial == 1){
        $status = "Batch/Serial";
      } 
    }
    if (strpos($status, 'Batch') !== false) {
      $batch = array('batch_no'=>'','mfg_date'=>'','exp_date'=>'','valid_months'=>'','qty'=>''); 
    }

    if (strpos($status, 'Serial') !== false) {
      $serial[]      = array('serial_no'=>''); 
    }

      // Check Remaining Qty
    // if($this->grnlist->isNotEmpty()){
    //   $grnList = GrnList::whereIn('grn_id', $this->grnlist->pluck('id'))->where('item_no', $value->item_no)->get();

    //   $totalQty = $grnList->sum('qty') + $grnList->sum('damage_qty') + $grnList->sum('lost_qty');
    //   $remaining_qty = $value->qty - $totalQty;
    // }
    // if(count($grnList) > 0){
    // if($remaining_qty > 0) {
      $data[] = [
        'id'                  => $value->id,
        'barcode'             => $value->item_no,
        'name'                => $value->Items->Item->name,
        'item_desc'           => $value->item_desc,
        'is_batch'            => $is_batch,
        'is_serial'           => $is_serial,
        'batch'               => [],
        'serial'              => [],
        'status'              => $status,
        'order_qty'           => $value->qty,
        'remaining_qty'       => $remaining_qty,
        'received_qty'        => $received_qty,
        'damage_qty'          => $received_qty,
        'lost_qty'            => $lost_qty,
        'remarks'             => '',
        'subtotal'            => $value->subtotal,
        'discount'            => $value->discount,
        'tax'                 => $value->tax,
        'total'               => $value->total
      ];
    // }
  // }
  
  }
  return $data;
  // dd($data);


 }

  public function getCurrentStatusAttribute()
  {
    if(count($this->grnlist) > 0) {
      $grnCount = GrnList::selectRaw('COUNT(DISTINCT sku_code) as qty')->where([ 'v_id' => $this->v_id ])->whereIn('grn_id', $this->grnlist->pluck('id'))->first();
      $adviceCount = AdviseList::selectRaw('COUNT(DISTINCT sku_code) as qty')->where([ 'v_id' => $this->v_id, 'advice_id' => $this->id ])->first();
      if($grnCount->qty === $adviceCount->qty) {
        return 'COMPLETE';
      } else {
        return 'PARTIAL';
      }
    } else {
      return 'PENDING';
    }
    // $joinQuery = DB::table('advice_list')->leftJoin('grn_list', 'advice_list.id', 'grn_list.advice_list_id')->where('advice_list.advice_id', $this->id)->where('advice_list.v_id', $this->v_id)->whereNull('grn_list.id');
    // if(count($this->grnlist) > 0) {
    //   if(!empty($this->no_of_packets)) {
    //     $uniquePacketCode = GrnList::select('packet_code')->distinct()->whereIn('grn_id', $this->grnlist->pluck('id'))->get();
    //     if(!$uniquePacketCode->isEmpty()) {
    //         $joinQuery = $joinQuery->whereNotIn('advice_list.packet_code', $uniquePacketCode);
    //     }
    //     if($joinQuery->count() === 0) {
    //       return 'COMPLETE';
    //     } else {
    //       return 'PARTIAL';
    //     }
    //   } else {
    //     if($joinQuery->count() === 0) {
    //       return 'COMPLETE';
    //     } else {
    //       return 'PARTIAL';
    //     }
    //   }
    // } else {
    //   return 'PENDING';
    // }
  }

}
