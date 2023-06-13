<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class ItemMediaAttributes extends Model
{
    protected $table 	  = 'item_media_attributes';
	protected $primaryKey = 'id';
	protected $fillable   = ['name','code','type'];
}
