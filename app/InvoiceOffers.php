<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvoiceOffers extends Model
{
	protected $table = 'invoice_offers';

 	public $timestamps = false;
	protected $fillable = ['invoice_details_id', 'item_id', 'mrp', 'qty', 'offers'];
}
