<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockTransferOrder extends Model
{
	protected $table = 'stock_transfer_order';
	public $timestamps = true;
    protected $fillable = [
			"sto_no",
			"v_id",                    
			"vu_id",                   
			"src_store_id",
			"dest_store_id",          
			"transfer_type",         
			"trans_src_doc_type",      
			"trans_src_doc_id",        		
			"ref_trans_src_doc_id",    	
			"creation_mode",  
			"sent_qty",         
			"subtotal",              
			"discount_amount",         
			"discount_details",        
			"tax_amount",             
			"tax_details",            
			"total",           
			"remark",                 
			"status",                
			"created_by",              
			"updated_by" 
    ];

    public function advice(){
   		return $this->hasOne('App\Model\Grn\Advice','id','trans_src_doc_id');
    }//End of advice

   	public function detail(){
   		return $this->hasMany('App\Model\Stock\StockTransferOrderDetails','sto_trf_ord_id','id');
   	} 

   	public function source(){
   		return $this->hasOne('App\Store','store_id','src_store_id');	
   	}

   	public function destination(){
   		return $this->hasOne('App\Store','store_id','dest_store_id');	
   	}
}
