<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderOffers extends Model
{
	protected $table = 'order_offers';

 	public $timestamps = false;
	protected $fillable = ['order_details_id', 'item_id', 'mrp', 'qty', 'offers'];
}
