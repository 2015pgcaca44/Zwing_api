<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ItemAttributes extends Model
{
	protected $table 	  = 'item_attributes';
	protected $primaryKey = 'id';
	protected $fillable   = ['name'];
}
