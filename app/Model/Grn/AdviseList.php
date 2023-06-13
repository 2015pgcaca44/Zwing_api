<?php

namespace App\Model\Grn;

use Illuminate\Database\Eloquent\Model;

class AdviseList extends Model
{
   protected $table = 'advice_list';
   protected $fillable = ['v_id', 'store_id', 'ref_advice_detail_id', 'advice_id', 'ref_advice_id', 'packet_id', 'packet_code', 'ref_sku_code', 'sku_code', 'batch_id', 'batch_code', 'serial_id', 'serial_code', 'item_no', 'ref_item_id', 'qty', 'unit_mrp', 'cost_price', 'item_desc', 'subtotal', 'discount', 'tax', 'tax_details', 'charge', 'charge_details', 'total', 'is_received', 'created_at', 'updated_at', 'supply_price'];

    public function Items(){
		// return $this->hasOne('App\Model\Items\VendorSkuDetails','barcode','item_no')->with('Item');
		return $this->hasOneThrough(
        'App\Model\Items\VendorSkuDetails',
        'App\Model\Items\VendorSkuDetailBarcode',
        'barcode', // Foreign key on the VendorSkuDetailBarcode table...
        'id', // Foreign key on the VendorSkuDetails table...
        'item_no', // Local key on advice list a table...
        'vendor_sku_detail_id' // Local key on the VendorSkuDetailBarcode table...
      )->with('Item');
	}

}
