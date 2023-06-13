<?php

namespace App\Model\Grn;
use Illuminate\Database\Eloquent\Model;

class GrnList extends Model
{
    protected $table = 'grn_list';

    protected $fillable = ['id','v_id','store_id','vu_id','grn_id','advice_list_id','ref_sku_code','sku_code','re_advice_list_id','barcode','item_desc','name','request_qty','qty','short_qty','excess_qty','unit_mrp','cost_price','unit_supply_price','subtotal','discount','discount_details','tax','tax_details','charges','total','damage_qty','lost_qty','remarks','batch_id','is_batch','is_serial','status','created_at','updated_at','updated_by', 'packet_id', 'packet_code'];    

    public function Items(){
		return $this->hasOne('App\Model\Items\VendorSkuDetails','sku_code','sku_code')->with('Item')->where('v_id',$this->v_id);
	}

	public function batches(){
		return $this->belongsToMany('App\Model\Stock\Batch','grn_batch','grnlist_id','batch_id','id');
	}

	public function serials(){
		return $this->belongsToMany('App\Model\Stock\Serial','grn_serial','grnlist_id','serial_id','id');
	}

	public function getItemNoAttribute()
    {
        return $this->barcode;
    }
}
