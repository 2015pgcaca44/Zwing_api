<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $table = 'template_details';

	protected $primaryKey = 'id';

	protected $fillable = ['template_id','type','vendor_setting_id', 'created_at','updated_at'];

}
