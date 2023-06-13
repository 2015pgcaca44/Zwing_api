<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class ItemBrand extends Model
{
    protected $table 	  = 'item_brand';
    protected $primaryKey = 'id';
    protected $fillable   = ['name'];
}
