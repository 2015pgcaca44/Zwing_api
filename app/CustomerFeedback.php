<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerFeedback extends Model
{
    protected $table = 'customer_feedback';

    protected $primaryKey = 'id';

    protected $fillable = ['vendor_id', 'store_id', 'order_id', 'user_id', 'question_id', 'answer'];


}
