<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PartnerOfferUsed extends Model
{
    protected $table = 'partner_offer_used';

    //protected $primaryKey = 'offer_id';

    protected $fillable = ['user_id','offer_id', 'partner_offer_id', 'offered_amount'];
}
