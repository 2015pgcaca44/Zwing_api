<?php

namespace App\Model\Client;

use Illuminate\Database\Eloquent\Model;

class ClientItemBrandMapping extends Model
{
    protected $table = 'client_item_brand_mappings';

    protected $primaryKey = 'id';

    protected $fillable = ['client_id', 'v_id', 'brand_code', 'client_brand_code'];

    
}
