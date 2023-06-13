<?php

namespace App\Model\Item;

use Illuminate\Database\Eloquent\Model;

class ItemList extends Model
{
	protected $table = 'v_item_list';

	protected $fillable = ['item_id', 'v_id','barcode','sku', 'item_name', 'category', 'batch', 'serial', 'uom_name'];

	protected $timestamp = false;
}
