<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VendorImage extends Model
{
 	protected $table = 'vendor_image';
	protected $primaryKey = 'id';
	protected $fillable = ['v_id', 'type', 'name','path', 'status','deleted'];
	public static $rules = array(
			'imagetype' 	=> 'required',
			'image'			=> 'required',
	);

	public static $rulesWithoutimage = array(
			'imagetype' 	=> 'required',
 	);
	public function imgtype()
    {
        return $this->hasOne('App\ImageType', 'id', 'type');
    }



}
