<?php

namespace App\Model\Grn;

use Illuminate\Database\Eloquent\Model;

class GrnBatch extends Model
{
    protected $table 	= 'grn_batch';
    protected $fillable = ['grnlist_id','batch_id','batch_code','move_qty','qty','damage_qty']; 
    public $timestamps 	= false;
}
