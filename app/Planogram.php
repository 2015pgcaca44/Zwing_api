<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Planogram extends Model
{
    protected $table = 'planograms';

    protected $primaryKey = 'id';

    protected $fillable = ['v_id', 'store_id', 'barcode','column','row','face', 'created_at', 'updated_at'];
}
