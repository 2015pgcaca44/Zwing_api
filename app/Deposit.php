<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected $fillable = ['id', 'v_id' ,'store_id', 'c_id', 'amount', 'type', 'ref_id' ];
}
