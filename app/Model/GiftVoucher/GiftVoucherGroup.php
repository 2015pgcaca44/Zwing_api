<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherGroup extends Model
{
    protected $table = 'gv_group';
    protected $fillable = ['v_id','gv_group_name','gv_group_description','config_preset_id','gv_type','is_assortment','validity','effective_from','valid_upto','is_with_pack','quantity_of_packs','pack_size','value_type','gift_value','sale_value','status','created_by','updated_by','deleted_at','deleted_by','tax_type','hsncode','tax_group_id','is_cluster','image_name','image_path','category_id','tax_status'];

    public function group(){
        return $this->hasMany(
            'App\Model\Tax\TaxRateGroupMapping',
            'tax_group_id',
            'tax_group_id'
        );
    }

    public function groups(){
        return $this->hasOne(
            'App\Model\Tax\TaxGroup',
            'id',
            'tax_group_id'
        );
    }

    public function slab(){
        return $this->hasMany(
            'App\Model\Tax\TaxCategorySlab',
            'tax_group_id',
            'tax_group_id'
        );
    }

    public function slabs(){
        return $this->hasMany(
            'App\Model\Tax\TaxCategorySlab',
            'tax_group_id',
            'tax_group_id'
        );
    }
}
