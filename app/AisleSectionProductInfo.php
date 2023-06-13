<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AisleSectionProductInfo extends Model
{
    protected $table = 'aisle_section_product_infos';

    protected $primaryKey = 'id';

    protected $fillable = [ 'aisle_section_product_id','manufacturing_date','expiring_type','expiring_date', 'best_before', 'remind_before',  'rows','columns' ];
}
