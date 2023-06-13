<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Carry extends Model
{
    protected $table = 'carry_bags';

	protected $primaryKey = 'id';

	public $timestamps = false;

	protected $fillable = ['v_id', 'store_id','barcode','status','deleted_status','deleted_by','deleted_at'];

	//for join store
	public function store()
    {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }

}
