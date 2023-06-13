<?php

namespace App\Model\Client;

use Illuminate\Database\Eloquent\Model;

class ClientStoreMapping extends Model
{
    protected $table = 'client_store_mappings';

    protected $primaryKey = 'id';

    protected $fillable = ['client_id', 'store_id', 'short_code', 'client_store_code'];

    public function vendor(){
    	return $this->hasOne('App\Client\ClientVendorMapping','cvm_id');
    }
}
