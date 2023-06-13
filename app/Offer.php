<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $table = 'offers';

    protected $primaryKey = 'offer_id';

    protected $fillable = ['name', 'terms', 'type', 'value','offer_default'];
}
