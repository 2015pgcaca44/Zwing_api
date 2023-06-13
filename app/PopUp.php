<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PopUp extends Model
{
    protected $table = 'pop_ups';

    protected $primarykey = 'id';

    protected $fillable = ['v_id', 'store_id', 'offer_title', 'offer_description', 'status'];
}
