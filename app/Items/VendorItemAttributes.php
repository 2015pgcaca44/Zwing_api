<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemAttributes extends Model
{
	protected $table 	  = 'vendor_item_attributes';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','item_attribute_id'];
}
