<?php

namespace App\Model\Tax;

use Illuminate\Database\Eloquent\Model;

class HsnCode extends Model
{
	protected $connection = 'mysql';
	
	protected $table = 'hsncode';
	//protected $fillable = ['name','code','v_id','slab','applicable_on'];

}
