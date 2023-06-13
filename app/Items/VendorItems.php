<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItems extends Model
{
	protected $table 	  = 'vendor_items';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','item_id','track_inventory'];
}
