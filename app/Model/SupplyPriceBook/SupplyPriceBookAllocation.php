<?php

namespace App\Model\SupplyPriceBook;

use Illuminate\Database\Eloquent\Model;

class SupplyPriceBookAllocation extends Model
{
    protected $table = 'spb_allocation';

	 protected $primaryKey = 'spb_allocation_id';

	 protected $fillable = ['v_id','spb_id','first_node_type','first_node_id','secound_node_type','secound_node_id','effective_date','valid_to'];
}
