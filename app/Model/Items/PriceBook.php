<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceBook extends Model
{
	use SoftDeletes;
	protected $table 	  = 'price_book';
	protected $fillable   = ['v_id','name','effective_date'];
}
