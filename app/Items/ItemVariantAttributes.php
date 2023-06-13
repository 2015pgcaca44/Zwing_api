<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class ItemVariantAttributes extends Model
{
	protected $table 	  =  'item_variant_attributes';
	protected $primaryKey = 'id';
	protected $fillable   =  ['name','code'];
}
