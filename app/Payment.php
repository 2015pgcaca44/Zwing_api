<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
 
class Payment extends Model
{
	use \Awobaz\Compoships\Compoships;
    protected $table = 'payments';

    protected $primaryKey = 'payment_id';
    
    protected $fillable = ['store_id', 'v_id', 'order_id', 'invoice_id', 'user_id', 'pay_id', 'amount', 'method', 'cash_collected', 'cash_return', 'payment_invoice_id', 'bank', 'wallet', 'vpa', 'error_description', 'status', 'payment_type', 'payment_gateway_type', 'gateway_response','type', 'date', 'time', 'month', 'year','session_id','terminal_id','channel_id','mop_id'];

    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    public function mop()
    {
        return $this->belongsTo('App\Model\Payment\Mop', 'mop_id', 'id');
    }
}
