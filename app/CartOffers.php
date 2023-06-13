<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CartOffers extends Model
{
	protected $table = 'cart_offers';

 	public $timestamps = false;
	protected $fillable = ['cart_id', 'item_id', 'mrp', 'qty', 'offers'];
}
