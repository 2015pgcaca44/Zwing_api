<?php

namespace App\Model\Item;

use Illuminate\Database\Eloquent\Model;

class UomConversions extends Model
{
    protected $table = 'uom_conversions';

    protected $primarykey = 'id';

    protected $fillable = ['v_id', 'purchase_uom_id', 'sell_uom_id', 'factor'];

    public function purchase() {
        return $this->hasOne(
            'App\Model\Item\Uom',
            'id',
            'purchase_uom_id'
        );
    }

    public function selling() {
        return $this->hasOne(
            'App\Model\Item\Uom',
            'id',
            'sell_uom_id'
        );
    }
}
