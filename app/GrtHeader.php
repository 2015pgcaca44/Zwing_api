<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GrtHeader extends Model
{
    //

    protected $table = 'grt_headers';

	protected $primaryKey = 'id';

	protected $fillable = ['grt_no', 'v_id', 'vu_id', 'src_store_id', 'supplier_id', 'transfer_type', 'trans_src_doc_type', 'trans_src_doc_id', 'ref_trans_src_doc_id', 'creation_mode', 'sent_qty', 'subtotal', 'discount_amount', 'discount_details', 'tax_amount', 'tax_details', 'charge_amount', 'charge_details', 'total', 'remark', 'status', 'created_by', 'updated_by','deleted_by','packet_no','deleted_at', 'sync_status','ref_grt_no'];


	public function supplier()
	{
		return $this->hasOne('App\Model\Supplier\Supplier', 'id', 'supplier_id');
	}
	public function user(){
      return $this->hasOne('App\VendorAuth','id','created_by');
   	}
}
