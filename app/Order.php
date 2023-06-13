<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    use \Awobaz\Compoships\Compoships;
    protected $table = 'orders';

    protected $primaryKey = 'od_id';

    protected $fillable = ['order_id', 'custom_order_id', 'ref_order_id', 'transaction_type', 'transaction_sub_type','comm_trans','cust_gstin','cust_gstin_state_id','store_gstin','store_gstin_state_id', 'o_id', 'v_id', 'store_id', 'user_id', 'address_id', 'partner_offer_id', 'qty', 'subtotal', 'discount', 'lpdiscount', 'manual_discount', 'coupon_discount', 'employee_id', 'employee_discount', 'employee_available_discount', 'bill_buster_discount', 'md_added_by', 'bill_buster_data', 'tax','net_amount','extra_charge','charge_details', 'total', 'status', 'payment_type', 'payment_via', 'is_invoice', 'error_description', 'trans_from', 'vu_id', 'verify_status', 'verified_by', 'verify_status_guard', 'verified_by_guard', 'invoice_name', 'transaction_no', 'return_by', 'return_code', 'qty','remark', 'date', 'time', 'month', 'year', 'deleted_at','round_off', 'due_date'];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'c_id');
    }

    public function vuser()
    {
        return $this->belongsTo('App\Vendor', 'vu_id', 'id');
    }

    public function payment()
    {
        return $this->belongsTo('App\Payment',  [ 'v_id', 'store_id', 'order_id' ], [ 'v_id', 'store_id', 'order_id' ]);
    }

    public function store()
    {
        return $this->belongsTo('App\Store', [ 'v_id', 'store_id' ], [ 'v_id', 'store_id' ]);
    }

    public function payments()
    {
        return $this->hasMany('App\Payment',  [ 'v_id', 'store_id', 'order_id' ], [ 'v_id', 'store_id', 'order_id' ]);
    }

    public function details()
    {
        return $this->hasMany('App\OrderDetails', ['v_id', 'store_id', 't_order_id', 'user_id', 'vu_id'], ['v_id', 'store_id', 'od_id', 'user_id', 'vu_id']);
    }

    public function discounts()
    {
        return $this->hasMany('App\OrderDiscount',  [ 'v_id', 'store_id', 'order_id' ], [ 'v_id', 'store_id', 'order_id' ]);
    }

    public function getTotalPaymentAttribute()
    {
        return $this->payments->where('status','success')->sum('amount');
    }

    public function getRemainingPaymentAttribute()
    {
        $totalAmount = $this->payments->where('status','success')->sum('amount');
        $totalDiscount = $this->discounts->where('type', '!=', 'MD')->sum('amount');
        return $this->total - $totalAmount - $totalDiscount;
    }

    public function getAfterDiscountTotalAttribute() 
    {
        $totalDiscount = $this->discounts->where('type', '!=', 'MD')->sum('amount');
        return $this->total - $totalDiscount;
    }

    public function getPaymentStatusAttribute() 
    {
        $totalAmount = $this->payments->where('status','success')->sum('amount');
        if($totalAmount == $this->total) {
            return 'Complete';
        } elseif ($totalAmount < $this->total && $totalAmount != 0) {
            return 'Partial';
        } else {
            return 'Incomplete';
        }
    }

    public function getPaymentListAttribute() 
    {
        if(!empty($this->payments)) {
            return $this->payments->where('method', '!=', 'credit_note_issued')->unique('method')->pluck('method');
        } else {
            return [];
        }
    }

    public function getChannelNameAttribute() {
        if($this->channel_id == '1') {
            return 'In Store Order';
        }
    }

    public function getCustomerNameAttribute() {
        return $this->user->first_name.' '.$this->user->last_name;
    }

    public function getCashierNameAttribute() {
        return $this->vuser->first_name.' '.$this->user->last_name;
    }

    public function list()
    {
        return $this->hasMany('App\OrderDetails', ['v_id', 'store_id', 't_order_id', 'user_id', 'vu_id'], ['v_id', 'store_id', 'od_id', 'user_id', 'vu_id']);
    }

    public function getProductTotalAmountAttribute() 
    {
        return $this->details->sum('total');
    }

    public function getMopNameListAttribute() 
    {
        if(!empty($this->payments)) {
            $data = [];
            $this->payments->map(function($item) use(&$data) {
                if(!empty($item->mop_id) && !empty($item->mop->name)) {
                    $data[] = ucwords($item->mop->name);
                } else {
                    $data[] = $item->method;
                }
            });
            return array_unique($data);
        } else {
            return [];
        }
    }

}
