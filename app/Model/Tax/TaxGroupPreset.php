<?php

namespace App\Model\Tax;

use Illuminate\Database\Eloquent\Model;
 
class TaxGroupPreset extends Model
{
 	protected $table 	= 'tax_group_preset';
	protected $fillable = ['v_id','code','preset_group_name','is_region_specific','region_id','status','created_by','updated_by'];


	public function details(){
   		return $this->hasMany('App\Model\Tax\TaxGroupPresetDetails','preset_id','id');
   }

	
}
