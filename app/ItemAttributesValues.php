<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ItemAttributesValues extends Model
{
	protected $table 	  = 'item_attribute_values';
	protected $primaryKey = 'id';
	protected $fillable   = ['value', 'type'];
}
