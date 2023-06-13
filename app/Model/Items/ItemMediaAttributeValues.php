<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class ItemMediaAttributeValues extends Model
{
	protected $table 	  = 'item_media_attribute_values';
	protected $primaryKey = 'id';
	protected $fillable   = ['value'];
}
