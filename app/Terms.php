<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Terms extends Model
{
    protected $table = 'terms';

	protected $fillable = ['v_id', 'store_id', 'terms_conditions', 'status', 'created_at', 'updated_at'];

}
