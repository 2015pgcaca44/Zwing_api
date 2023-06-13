<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemMediaAttribute extends Model
{
	protected $table 	  = 'vendor_item_media_attribute';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','item_media_attribute_id'];
}
