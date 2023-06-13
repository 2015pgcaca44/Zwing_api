<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvoicePush extends Model {

	protected $table = 'invoice_push';

	// protected $primaryKey = 'od_id';

	protected $fillable = ['v_id', 'store_id', 'invoice_no', 'status', 'response'];

}
