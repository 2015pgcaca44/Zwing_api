<?php

namespace App\Model\Client;

use Illuminate\Database\Eloquent\Model;

class ClientVendorMapping extends Model
{
    protected $table = 'client_vendor_mappings';
    protected $connection = 'mysql';

    protected $primaryKey = 'id';

    protected $fillable = ['v_id','source_currency','client_id','username','password','token','api_url','api_token','token_expair_at'];

    public function stores(){
    	return $this->hasMany('App\Client\ClientStoreMapping', 'cvm_id');
    }
}
