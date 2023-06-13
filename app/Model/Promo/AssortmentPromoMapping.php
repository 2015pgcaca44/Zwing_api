<?php

namespace App\Model\Promo;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PromoBuy
 * 
 * @property int $id
 * @property int $CODE
 * @property int $PROMO_CODE
 * @property int $ASSORTMENT_CODE
 * @property int $FACTOR
 * @property string $ASSORTMENT_NAME
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @package App\Model\Promo
 */

class AssortmentPromoMapping extends Model
{
    protected $table = 'assortment_promo_mapping';
    
    protected $fillable = [
        'v_id',
        'promo_code',
        'assortment_code',
        'assortment_type'
        ];

}