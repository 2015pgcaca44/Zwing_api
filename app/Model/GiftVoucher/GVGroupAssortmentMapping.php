<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GVGroupAssortmentMapping extends Model
{
    protected $table = 'gv_grp_assortment_mapping';
    protected $fillable = ['v_id','assortment_id','gv_group_id'];
}
