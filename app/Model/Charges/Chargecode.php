<?php

namespace App\Model\Charges;

use Illuminate\Database\Eloquent\Model;

class Chargecode extends Model
{
    protected $table = 'chargecode';
    protected $fillable = ['chargecode','description','country_code'];
}
