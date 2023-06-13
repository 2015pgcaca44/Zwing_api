<?php

namespace App\Model\Grn;

use Illuminate\Database\Eloquent\Model;

class Grn extends Model
{
    protected $table = 'grn';
    
    protected $fillable = ['v_id', 'store_id', 'vu_id', 'grn_no', 'grn_no_seq', 'advice_id', 'ref_advice_id', 'requested_packets', 'received_packets', 'request_qty', 'qty', 'short_qty', 'excess_qty', 'subtotal', 'discount', 'discount_details', 'tax', 'tax_details', 'charges', 'total', 'damage_qty', 'lost_qty', 'remarks', 'is_imported', 'grn_from', 'origin_from', 'supplier_id', 'deleted_by', 'status', 'sync_status'];

   public function advice(){
   		return $this->hasOne('App\Model\Grn\Advise','id','advice_id');
   }

   public function grnlist() {
   		return $this->hasMany('App\Model\Grn\GrnList','grn_id','id');
   }


  public static $rules = array(
    'advice' => 'required',
    'qty'		=> 'required|numeric',
    'subtotal'  => 'required|numeric',
    'discount'  => 'required',
    'tax'       => 'required',
    'total'     => 'required',
  );	

  public function cashier(){
    return $this->hasOne('App\VendorUserAuth','vu_id','vu_id');
  }//End of casshier

  public function getGrnProductDetailsAttribute() 
  {
    $data = $serial = $batch = [];
    
    // dd($grnList);
    foreach ($this->grnlist as $key => $value) {

      $is_batch = $is_serial = $received_qty = $damage_qty = $lost_qty = 0;
      $status     = '';
      $itemInfo = $value->Items->where('v_id', $this->v_id)->where('sku_code', $value->sku_code)->first();
      if(!empty($itemInfo) ){
        $is_batch = $value->is_batch;
        $is_serial= $value->is_serial;
        if($value->is_batch == 1){
          $status = "Batch";
        }if($value->is_serial == 1){
          $status = "Serial";
        }if($value->is_batch == 1 && $value->is_serial == 1){
          $status = "Batch/Serial";
        } 
      }
      if (strpos($status, 'Batch') !== false) {
        $batch = array('batch_no'=>'','mfg_date'=>'','exp_date'=>'','valid_months'=>'','qty'=>''); 
      }

      if (strpos($status, 'Serial') !== false) {
        $serial[]      = array('serial_no'=>''); 
      }

      
      $data[] = [
        'id'                  => $value->id,
        'barcode'             => $value->item_no,
        'name'                => $value->Items->Item->name,
        'item_desc'           => $value->item_desc,
        'is_batch'            => $is_batch,
        'is_serial'           => $is_serial,
        'batch'               => $value->batches,
        'serial'              => $value->serials,
        'status'              => $status,
        'order_qty'           => $value->request_qty,
        'received_qty'        => $value->qty,
        'damage_qty'          => $value->damage_qty,
        'lost_qty'            => $value->lost_qty,
        'supply_price'        => $value->cost_price,
        'remarks'             => $value->remarks,
        'subtotal'            => $value->subtotal,
        'discount'            => $value->discount,
        'tax'                 => $value->tax,
        'total'               => $value->total
      ];
    }
    return $data;
  }

  public function supplier()
  {
     return $this->hasOne('App\Supplier', 'id', 'supplier_id');
  }
    public function user(){
      return $this->hasOne('App\VendorAuth','id','vu_id');
   }

}
