<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ThirdPartyInformation extends Model
{
	protected $table = 'third_party_informations';

	protected $fillable = ['name', 'code'];
}
