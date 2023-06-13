<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PartnerOffer extends Model
{
    protected $table = 'partner_offers';

    //protected $primaryKey = 'offer_id';

    protected $fillable = ['partner_id','name', 'description', 'type', 'value', 'start_at', 'end_at'];
}
