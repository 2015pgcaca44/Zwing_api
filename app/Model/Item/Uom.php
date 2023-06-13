<?php

namespace App\Model\Item;

use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    protected $table = 'uom';

    protected $primarykey = 'id';

    protected $fillable = ['id', 'name', 'code','type'];

    public static $rules = array(
        'name' 		=> 'required',
        'code'      => 'required'
    );
}
