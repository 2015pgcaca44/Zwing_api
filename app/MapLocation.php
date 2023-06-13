<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MapLocation extends Model
{
    protected $table = 'map_location';

    protected $primaryKey = 'id';

    protected $fillable = ['latitude', 'longitude','address','locality', 'google_response'];
}
