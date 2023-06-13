<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;
    use \Awobaz\Compoships\Compoships;
    protected $table = 'stores';

    protected $primaryKey = 'store_id';

    protected $fillable = ['store_random', 'store_code', 'mapping_store_id', 'store_db_name', 'short_code', 'v_id', 'name', 'type', 'email', 'pincode', 'address1', 'address2', 'location', 'district', 'state', 'city', 'country','state_id','time_zone_id', 'latitude', 'longitude', 'opening_time', 'closing_time', 'display_status', 'weekly_off', 'store_icon', 'store_logo', 'store_details_img', 'store_list_bg', 'store_bg', 'contact_person', 'contact_number', 'contact_designation', 'a_contact_number', 'helpline', 'description', 'tagline', 'agree', 'status', 'api_status', 'max_qty', 'gst', 'tin', 'cin', 'delivery', 'd_status', 'is_restaurant', 'restaurant_bg', 'deleted_by', 'deleted_at'];

    public function stock_points()
    {
        return $this->hasMany('App\Model\Stock\StockPoints', 'store_id', 'store_id');
    }

    public function getDefaultStockPointAttribute() 
    {
        return $this->stock_points->where('is_editable', '0')->pluck('id','code');
    }

    public function countryDetail()
    {
     return $this->hasOne('App\Country', 'id','country');
    }

    public function timezone()
    {
     return $this->hasOne('App\Model\TimeZone', 'id','time_zone_id');
    }


    public function getSellableStockPointAttribute() 
    {
        return $this->stock_points->where('is_sellable', '1')->first();
    }
    
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('deleted', function (Builder $builder) {
            $builder->where('d_status',  0);
        });
    }

    public function getOrderStockPointAttribute() 
    {
        return $this->stock_points->where('code', 'RESERVE')->first();
    }
   
}
