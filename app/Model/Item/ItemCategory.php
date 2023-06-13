<?php

namespace App\Model\Item;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ItemCategory extends Model
{
    use SoftDeletes;

    protected $table = 'item_category';

    protected $primaryKey = 'id';

    protected $fillable = ['id', 'name','code','parent_id', 'deleted'];

    //for join store
    public function categories() {
        return $this->hasMany('App\Model\Item\ItemCategory', 'parent_id', 'id')
            ->where('deleted', '=', 0);
    }

    public function category_count() {
        return $this->hasMany('App\Model\Item\ItemCategory', 'parent_id', 'id')
            ->where('deleted', '=', 0)->count();
    }

    public function parent() {
        return $this->belongsTo('App\Model\Item\VendorItemCategory', 'parent_id', 'id');
    }

    public function vendor() {
        return $this->belongsTo('App\Model\Item\VendorItemCategory', 'id', 'item_category_id');
    }

    public static $get_rule = array(
        'id' 		=> 'required|numeric'
    );

    public static $put_rule = array(
        'name' 		=> 'required',
//        'code' 		=> 'required',
        'parent_id' 		=> 'numeric|required'
    );
}
