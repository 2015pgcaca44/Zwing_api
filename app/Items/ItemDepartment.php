<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class ItemDepartment extends Model
{
    protected $table 	  = 'item_department';
    protected $primaryKey = 'id';
    protected $fillable   = ['name'];
}
