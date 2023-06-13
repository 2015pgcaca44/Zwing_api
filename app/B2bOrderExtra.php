<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class B2bOrderExtra extends Model
{
    protected $table = 'b2b_order_extra';

    protected $primaryKey = 'ex_id';

    protected $fillable = ['v_id', 'store_id', 'order_id', 'agent_id','destination_site','size_matrix','remarks'];
}
