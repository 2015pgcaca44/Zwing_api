<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EinvoiceDetails extends Model
{
    protected $table    = 'einvoice_details';
	protected $fillable = ['v_id', 'invoice_id', 'request_id', 'response','response_json', 'ack_no', 'ack_date', 'irn','signed_invoice','signed_qr_code','status','ewd_no','ewd_date','ewd_valid_till','qrcode_image_path'];
}
