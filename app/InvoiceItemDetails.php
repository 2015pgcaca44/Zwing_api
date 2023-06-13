<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvoiceItemDetails extends Model
{
    protected $table = 'invoice_items';

    // protected $primaryKey = 'od_id';

    protected $fillable = ['pinvoice_id', 'barcode', 'qty', 'mrp', 'price', 'discount', 'ext_price', 'tax', 'taxes', 'message', 'ru_prdv', 'type', 'type_id', 'promo_id', 'is_promo','channel_id'];

}
