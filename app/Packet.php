<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Packet extends Model
{
    protected $table 	  = 'packets';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','store_id','destination_store_id','packect_id','packet_code','description', 'stock_point_id','total_qty','status','created_by','packet_barcode','remarks','updated_by','sealed_on','sealed_by','date','sync_status'];
}
