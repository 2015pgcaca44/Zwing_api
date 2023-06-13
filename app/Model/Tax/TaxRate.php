<?php

namespace App\Model\Tax;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $table = 'tax_rates';

    protected $fillable = ['name','code','rate','v_id'];

    
}

