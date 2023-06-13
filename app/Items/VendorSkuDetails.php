<?php
//Not Use -- Only use App\Model\Items\VendorSkuDetails
namespace App\Items;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorSkuDetails extends Model
{
    

    protected $table = 'vendor_sku_details';

    protected $fillbale = ['v_id', 'item_id', 'variant_combi', 'sku','ref_sku_code','sku_code', 'qty', 'barcode', 'hsn_code'];

    //protected $primaryKey = 'variant_combi';

    public function variantPrices() {
        return $this->belongsToMany(
            'App\Items\ItemPrices',
            'vendor_item_price_mapping',
            'variant_combi',
            'item_price_id',
            'name'
        );
    }

    public function variantAttributes() {
        return $this->hasMany(
            'App\Items\VendorItemVariantAttributeValueMatrixMapping',
            'variant_combi',
            'name'
        )->with('attribute:name,id', 'value:id,value');
    }

    public function hsnCodeDetail() {
        return $this->hasOne(
            'App\Model\Tax\HsnCode',
            'hsncode',
            'hsn_code'
        );
    }

    public function taxGroupDetail() {
        return $this->hasOne(
            'App\Model\Tax\TaxGroup',
            'id',
            'tax_group_id'
        );
    }

    public function Item(){
         return $this->hasOne(
            'App\Item',
            'id',
            'item_id'
        );
    }

    
}
