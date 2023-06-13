<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemDepartment extends Model
{
    protected $table 	  = 'vendor_item_department_mapping';
    protected $primaryKey = 'id';
    protected $fillable   = ['v_id' ,'department_id', 'department_code' , 'ref_department_code' ];

    public function department(){
    	return $this->hasOne(
    		'App\Model\Items\ItemDepartment',
    		'id',
    		'department_id'
    		);

    }
}
