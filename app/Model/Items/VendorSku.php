<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Controllers\Search\SearchableTrait;
use DB;

class VendorSku extends Model
{
    use SoftDeletes;
    use SearchableTrait;
    protected $table = 'vendor_sku_flat_table';
    protected $fillable = ['*'];

    //Search _id column
    protected $searchId = 'sku_code';
    #protected $searchable = ['v_id', 'name', 'barcode', 'store_id'];

    //for serach engine
    protected $documentType = 'products';

    //For Custom fields
    protected $customFields = [
        'store_id' => 'store_allocation',
        'barcodes' => 'bar'
    ];

    //protected $primaryKey = 'variant_combi';

    public function variantPrices() {
        return $this->belongsToMany(
            'App\Model\Items\ItemPrices',
            'vendor_item_price_mapping',
            'variant_combi',
            'variant_combi',
            'item_id'
        );
    }

    public function vprice(){
        return $this->hasMany(
            'App\Model\Items\VendorItemPriceMapping',
            'item_id',
            'item_id'
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

    public function vendorItem(){
        return $this->hasOne(
            'App\Model\Items\VendorItem',
            'item_id',
            'item_id'
        );
        //->where('v_id', $this->v_id)
    }

    public function Item(){
        return $this->hasOne(
            'App\Model\Items\Item',
            'id',
            'item_id'
        );
    }

    public function uom() {
        return $this->hasOne(
            'App\Model\Item\UomConversions',
            'id',
            'uom_conversion_id'
        )->with(['selling:id,name,type', 'purchase:id,name,type']);
    }

    public function StockCurrentStatus(){
        return $this->belongsTo('App\Model\Stock\StockCurrentStatus','item_id','item_id')->where('variant_sku', $this->sku)->where('v_id', $this->v_id );
    }

    public function currentStoreStock(){
        return $this->hasOne('App\Model\Stock\StockCurrentStatus','item_id','item_id')->where('variant_sku', $this->sku)->where('v_id', $this->v_id )->where('store_id', $this->store_id)->orderBy('id','desc');
    }

    public function currentStock(){
        //$stockCurrentStatus = (new \App\Model\Stock\StockCurrentStatus)->getTable();
        return $this->hasMany('App\Model\Stock\StockCurrentStatus','item_id','item_id')->where('variant_sku', $this->sku)->where('v_id', $this->v_id )->orderBy('id','desc');
    }

    public function tax(){
        return $this->hasOne(
            'App\Model\Tax\TaxHsnCat',
            'hsncode',
            'hsn_code' 
        );
    }

    public function category(){

        $relation = DB::table($this->table.' as sku')
            ->join('vendor_item_category_mapping as vicm' ,'sku.item_id' ,'vicm.item_id')
            ->join('vendor_item_category_ids as vic', function ($join) {
                $join->on('vic.item_category_id', '=', 'vicm.item_category_id')
                ->on('vic.v_id','=','vicm.v_id');
            })
            ->join('item_category as cat','cat.id','vicm.item_category_id')
            ->select('cat.id','cat.name','vic.category_code as code','vic.category_code','vic.parent_id')
            ->where('sku.v_id', $this->v_id)
            ->where('sku.item_id', $this->item_id)
            // ->where('sku.id', $this->id)
            ->orderBy('vic.parent_id')
            ->get();

        return $relation;

        // return $this->hasManyThrough(
        //     'App\Model\Item\ItemCategory', 
        //     'App\Model\Item\VendorItemCategoryMapping',
        //     'item_id',
        //     'id'
        // );
        
    }

    public function department(){
        $relation = DB::table('vendor_sku_details as sku')
            ->join('items as i' ,'i.id' ,'sku.item_id')
            ->join('item_department as id','id.id','i.department_id')
            ->select('id.*')
            ->where('sku.v_id', $this->v_id)
            ->where('sku.item_id', $this->item_id)
            ->where('sku.id', $this->id)
            ->get();

        return $relation;
    }

    public function media($type=''){
       if($type==''){$type = 'IMAGE';}
        $response = DB::table('item_media_attribute_values')
                    ->select('item_media_attribute_values.value')
                    ->join('item_media_attribute_values','item_media_attribute_values.id','vendor_item_media_attribute_value_mapping.item_media_attribute_value_id')
                    ->join('item_media_attributes','item_media_attributes.id','vendor_item_media_attribute_value_mapping.item_media_attribute_id')
                    ->where('item_media_attributes.type',$type)
                    ->where('vendor_item_media_attribute_value_mapping.v_id',$this->v_id)
                    ->where('vendor_item_media_attribute_value_mapping.item_id',$this->item_id)
                    ->get();
        return $response;
    }

    public function barcodes(){
        return $this->hasMany('App\Model\Items\VendorSkuDetailBarcode','vendor_sku_detail_id','vendor_sku_detail_id');
    }

    public function stores(){
        return $this->hasMany('App\Model\Store\StoreItems','item_id','item_id')
        ->where('variant_sku', $this->sku)->where('status', '1');
    }
    
    public function getBarAttribute(){
        return $this->barcodes->pluck('barcode')->all();
    }

    public function getStoreAllocationAttribute(){
        return array_unique($this->stores->pluck('store_id')->all() );
    }

    public function searchCustomAttributes(){


    }
}
