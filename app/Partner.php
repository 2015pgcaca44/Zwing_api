<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'partners';

    //protected $primaryKey = 'offer_id';

    protected $fillable = ['name'];
}
