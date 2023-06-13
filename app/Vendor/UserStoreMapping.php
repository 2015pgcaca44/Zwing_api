<?php

namespace App\model\vendor;

use Illuminate\Database\Eloquent\Model;

class UserStoreMapping extends Model
{
    protected $table = 'user_store_mapping';

    protected $primaryKey = 'id';

    protected $fillable = ['user_id','store_id'];
}
