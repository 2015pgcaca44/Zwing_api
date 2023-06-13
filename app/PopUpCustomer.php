<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PopUpCustomer extends Model
{
    protected $table = 'pop_up_customers';

    protected $primarykey = 'id';

    protected $fillable = ['v_id', 'store_id', 'pop_up_id', 'c_id', 'viewed'];
}
