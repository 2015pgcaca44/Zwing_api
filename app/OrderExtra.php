<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderExtra extends Model
{
    protected $table = 'order_extra';

    protected $primaryKey = 'ex_id';

    protected $fillable = ['v_id', 'store_id', 'seat_no','third_party_repsonse','usersession','hall_no'];
}
