<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MPOList extends Model
{
	protected $table = 'payment_modes';

	protected $fillable = ['name', 'code'];
}
