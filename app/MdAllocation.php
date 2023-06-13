<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MdAllocation extends Model
{
   
    protected $table 	  = 'md_allocations';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','md_id','allocate_to','store_id','cg_id'];
}
