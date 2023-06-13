<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GVRedemptionCluster extends Model
{
    protected $table = 'gv_redemption_cluster';
    protected $fillable = ['v_id','store_cluster_id','gv_group_id','pack_id'];
}
