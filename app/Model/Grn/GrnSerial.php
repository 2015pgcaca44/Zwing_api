<?php

namespace App\Model\Grn;

use Illuminate\Database\Eloquent\Model;

class GrnSerial extends Model
{
    protected $table = 'grn_serial';
    protected $fillable = ['grnlist_id','serial_id', 'serial_code','is_moved','is_damage'];
    public $timestamps = false;
}
