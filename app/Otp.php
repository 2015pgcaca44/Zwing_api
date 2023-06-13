<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $table = 'otps';

    //protected $primaryKey = 'offer_id';
    protected $fillable = ['user_id','mobile','user_type','expired_at','operation'];
}
