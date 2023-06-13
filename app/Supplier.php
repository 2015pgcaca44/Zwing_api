<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
	protected $table = 'suppliers';

	protected $fillable = ['v_id', 'name', 'contact_number', 'alternate_contact_number', 'address_1', 'address_2', 'city_id', 'state_id', 'status'];
}
