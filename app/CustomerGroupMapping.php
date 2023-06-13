<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerGroupMapping extends Model
{
    protected $table = 'customer_group_mappings';

    protected $primaryKey = 'id';

    protected $fillable = ['c_id' ,'group_id'];

   
    
}
