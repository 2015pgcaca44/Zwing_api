<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
	protected $table 	  = 'item_category';
	protected $primaryKey = 'id';
	protected $fillable   = ['name', 'code', 'parent_id'];
}
