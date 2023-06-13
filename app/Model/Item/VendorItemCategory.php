<?php

namespace App\Model\Item;

use Illuminate\Database\Eloquent\Model;

class VendorItemCategory extends Model
{
    protected $table = 'vendor_item_category_ids';

    protected $primarykey = 'id';

    protected $fillable = ['v_id','item_category_id','level','parent_id','ref_category_code','category_code'];
}
