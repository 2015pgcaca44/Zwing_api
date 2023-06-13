<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherCategory extends Model
{
    protected $table = 'gv_category';
    protected $fillable = ['v_id','gv_cat_name','gv_cat_description','status','created_by','updated_by','deleted_at','deleted_by'];
}
